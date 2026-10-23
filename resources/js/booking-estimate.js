import './booking-address-autocomplete.js';

const panel = document.querySelector('[data-booking-estimate]');

if (panel) {
    const form = document.querySelector('#booking-edit-form');
    const total = panel.querySelector('[data-estimate-total]');
    const details = panel.querySelector('[data-estimate-details]');
    const itemBreakdown = panel.querySelector('[data-estimate-items]');
    const warning = panel.querySelector('[data-estimate-warning]');
    const itemsContainer = form.querySelector('#booking-items');
    const pricingOptions = JSON.parse(panel.dataset.pricingOptions);
    const services = JSON.parse(panel.dataset.serviceOptions);
    const garments = JSON.parse(panel.dataset.garmentOptions);
    const minimumWeight = Number(panel.dataset.minimumWeight);
    const itemPricing = window.OrderItemPricing({
        prices: pricingOptions.map(option => ({
            service_id: option.serviceId,
            garment_id: option.garmentId,
            unit_id: option.unitId,
            unit: option.unit,
            price: option.price,
        })),
        garments: Object.fromEntries(garments.map(garment => [garment.id, garment.name])),
        minimumWeight,
    });
    const money = value => new Intl.NumberFormat('vi-VN').format(value) + ' VNĐ';
    let estimateData;
    let estimateSignature;
    let initialEstimateTimer;
    let initialEstimatePending = false;

    function roundMoney(value) {
        return Math.round((value + Number.EPSILON) * 100) / 100;
    }

    function buildItemsTable(items) {
        const table = document.createElement('table');
        table.className = 'table table-sm table-bordered align-middle mb-0';
        const header = table.createTHead().insertRow();

        for (const label of ['Dịch vụ', 'Loại đồ', 'Đơn vị', 'Số lượng / khối lượng', 'Đơn giá', 'Thành tiền']) {
            const cell = document.createElement('th');
            cell.scope = 'col';
            cell.textContent = label;
            header.append(cell);
        }

        const body = table.createTBody();
        for (const item of items) {
            const row = body.insertRow();
            for (const value of [
                item.service_name,
                item.garment_name,
                item.unit_name,
                item.amount,
                money(item.unit_price),
                money(item.subtotal),
            ]) {
                row.insertCell().textContent = value;
            }
        }

        itemBreakdown.replaceChildren(table);
    }

    function buildTotalsTable(estimate) {
        const table = document.createElement('table');
        table.className = 'table table-sm mb-0';
        const body = table.createTBody();
        const rows = [
            ['Tổng tiền dịch vụ:', money(estimate.TongTien)],
            ['Giảm từ mã:', `−${money(estimate.TienGiamKhuyenMai)}`],
            [`Điểm đã dùng (${estimate.DiemSuDung} điểm):`, `−${money(estimate.TienGiamDoDiem)}`],
            ['Phí nhận đồ:', money(estimate.PhiNhanDo)],
            ['Phí giao đồ:', money(estimate.PhiGiaoDo)],
        ];

        for (const [label, value] of rows) {
            const row = body.insertRow();
            row.insertCell().textContent = label;
            const amount = row.insertCell();
            amount.className = 'text-end';
            amount.textContent = value;
        }

        details.replaceChildren(table);
    }

    function calculateCurrentItems() {
        const items = [];
        let subtotal = 0;
        const rowWarnings = [];

        for (const [index, row] of [...itemsContainer.querySelectorAll('.booking-item')].entries()) {
            row.querySelector('[data-row-number]').textContent = index + 1;
            const serviceId = row.querySelector('.booking-service').value;
            const garmentId = row.querySelector('.booking-garment').value;
            const unitId = row.querySelector('.booking-unit').value;
            const price = pricingOptions.find(option =>
                String(option.serviceId) === serviceId
                && String(option.garmentId) === garmentId
                && String(option.unitId) === unitId
            );
            const priceInput = row.querySelector('.booking-item-price');
            const subtotalInput = row.querySelector('.booking-item-subtotal');

            if (!serviceId || !garmentId || !unitId || !price) {
                priceInput.value = '—';
                subtotalInput.value = '—';
                rowWarnings.push('Chọn đủ dịch vụ, loại đồ và đơn vị tính có bảng giá hiệu lực.');
                continue;
            }

            const isWeightUnit = /^(kg|kgs|kilogram)$/i.test(String(price.unit).trim());
            const quantity = row.querySelector('.booking-quantity').value;
            const weight = row.querySelector('.booking-weight').value;
            const parsedQuantity = Number(quantity);
            const parsedWeight = Number(weight);

            if (
                (isWeightUnit && (!Number.isFinite(parsedWeight) || parsedWeight <= 0))
                || (!isWeightUnit && (!Number.isInteger(parsedQuantity) || parsedQuantity < 1))
            ) {
                priceInput.value = money(price.price);
                subtotalInput.value = 'Chờ kiểm tra';
                continue;
            }

            const lineSubtotal = itemPricing.calculateAmount(
                price.price,
                isWeightUnit,
                parsedQuantity,
                parsedWeight,
            );
            const service = services.find(option => String(option.id) === serviceId);
            const garment = garments.find(option => String(option.id) === garmentId);
            const displayedAmount = isWeightUnit
                ? `${parsedWeight} kg`
                : `${parsedQuantity} ${price.unit}`;

            priceInput.value = money(Number(price.price));
            subtotalInput.value = money(lineSubtotal);
            subtotal = roundMoney(subtotal + lineSubtotal);
            items.push({
                service_name: service?.name ?? 'Dịch vụ',
                garment_name: garment?.name ?? 'Loại đồ',
                unit_name: price.unit,
                amount: displayedAmount,
                unit_price: Number(price.price),
                subtotal: lineSubtotal,
            });
        }

        return { items, subtotal, warning: rowWarnings[0] ?? '' };
    }

    function renderLocalEstimate() {
        if (!estimateData) {
            return;
        }

        const { items, subtotal, warning: itemWarning } = calculateCurrentItems();
        const promotion = estimateData.promotion_context;
        let promotionDiscount = 0;
        let promotionWarning = estimateData.promotion_warning ?? '';

        if (promotion?.eligible) {
            if (promotion.minimum !== null && subtotal < Number(promotion.minimum)) {
                promotionWarning = 'Đơn hàng chưa đạt giá trị tối thiểu để dùng mã khuyến mãi.';
            } else {
                promotionWarning = '';
                promotionDiscount = promotion.type === 'Phần trăm'
                    ? subtotal * Number(promotion.value) / 100
                    : promotion.type === 'Tiền mặt'
                        ? Number(promotion.value)
                        : 0;
                if (promotion.maximum && promotionDiscount > Number(promotion.maximum)) {
                    promotionDiscount = Number(promotion.maximum);
                }
                promotionDiscount = roundMoney(Math.min(subtotal, promotionDiscount));
            }
        }

        const remaining = Math.max(0, subtotal - promotionDiscount);
        const pointValue = Number(estimateData.point_value);
        const pointsUsed = pointValue > 0
            ? Math.max(0, Math.min(
                Number(estimateData.requested_points),
                Number(estimateData.available_points),
                Math.floor(remaining / pointValue),
            ))
            : 0;
        const pointsDiscount = roundMoney(pointsUsed * pointValue);
        const pickupFee = Number(estimateData.PhiNhanDo);
        const returnFee = Number(estimateData.PhiGiaoDo);
        const amountDue = roundMoney(
            Math.max(0, remaining - pointsDiscount) + pickupFee + returnFee,
        );
        const localEstimate = {
            TongTien: subtotal,
            TienGiamKhuyenMai: promotionDiscount,
            DiemSuDung: pointsUsed,
            TienGiamDoDiem: pointsDiscount,
            PhiNhanDo: pickupFee,
            PhiGiaoDo: returnFee,
            ThanhTien: amountDue,
        };

        total.textContent = money(amountDue);
        buildTotalsTable(localEstimate);
        buildItemsTable(items);
        warning.textContent = itemWarning || promotionWarning;
    }

    function currentEstimateSignature() {
        const rows = [...itemsContainer.querySelectorAll('.booking-item')].map(row => [
            row.querySelector('.booking-service-category').value,
            row.querySelector('.booking-service').value,
            row.querySelector('.booking-garment').value,
            row.querySelector('.booking-unit').value,
            row.querySelector('.booking-quantity').value,
            row.querySelector('.booking-weight').value,
        ]);

        return JSON.stringify(rows);
    }

    function refreshLocalEstimate() {
        calculateCurrentItems();
        if (!estimateData) {
            if (initialEstimatePending) {
                return;
            }
            clearTimeout(initialEstimateTimer);
            initialEstimateTimer = setTimeout(loadInitialEstimate, 200);
            return;
        }

        const signature = currentEstimateSignature();
        if (signature === estimateSignature) {
            return;
        }

        estimateSignature = signature;
        renderLocalEstimate();
    }

    async function loadInitialEstimate() {
        if (initialEstimatePending) {
            return;
        }

        calculateCurrentItems();
        initialEstimatePending = true;
        const items = [...itemsContainer.querySelectorAll('.booking-item')].map(row => {
            const item = {};
            for (const field of row.querySelectorAll('[name^="items["]')) {
                if (field.disabled) {
                    continue;
                }

                const key = field.name.match(/\[([^\]]+)\]$/)?.[1];
                if (key) {
                    item[key] = field.value;
                }
            }

            return item;
        }).filter(item => item.DichVuID && item.LoaiDoGiatID && item.DonViTinhID
            && (Number(item.SoLuong) > 0 || Number(item.KhoiLuong) > 0));

        total.textContent = 'Đang tính…';
        warning.textContent = '';

        try {
            const response = await fetch(panel.dataset.bookingEstimate, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    items,
                    method: form.querySelector('[name="method"]').value,
                    return_method: form.querySelector('[name="return_method"]').value,
                }),
            });
            const data = await response.json();

            if (!response.ok) {
                throw Error(response.status === 422
                    ? 'Chọn đủ dịch vụ, loại đồ, đơn vị có giá và nhập số lượng/khối lượng hợp lệ.'
                    : `Không thể tải ước tính (HTTP ${response.status}). Vui lòng thử lại.`);
            }
            for (const key of [
                'TongTien', 'TienGiamKhuyenMai', 'DiemSuDung', 'TienGiamDoDiem',
                'PhiNhanDo', 'PhiGiaoDo', 'ThanhTien', 'available_points',
                'requested_points', 'point_value',
            ]) {
                if (typeof data[key] !== 'number' || !Number.isFinite(data[key]) || data[key] < 0) {
                    throw Error('Máy chủ trả về dữ liệu ước tính không hợp lệ.');
                }
            }
            if (!Array.isArray(data.items) || !Object.hasOwn(data, 'promotion_context')) {
                throw Error('Máy chủ trả về dữ liệu chi tiết ước tính không đầy đủ.');
            }

            estimateData = data;
            initialEstimatePending = false;
            estimateSignature = null;
            refreshLocalEstimate();
        } catch (error) {
            initialEstimatePending = false;
            total.textContent = 'Chưa có ước tính';
            warning.textContent = error instanceof TypeError
                ? 'Không thể kết nối đến máy chủ tính giá. Vui lòng thử lại.'
                : error.message;
        }
    }

    form.addEventListener('input', refreshLocalEstimate);
    form.addEventListener('change', refreshLocalEstimate);
    itemsContainer.addEventListener('click', () => setTimeout(refreshLocalEstimate, 0));
    const observer = new MutationObserver(refreshLocalEstimate);
    observer.observe(itemsContainer, { childList: true });
    form.addEventListener('reset', () => {
        setTimeout(() => {
            refreshLocalEstimate();
        }, 0);
    });
    window.addEventListener('pagehide', () => {
        clearTimeout(initialEstimateTimer);
        observer.disconnect();
    });
    window.addEventListener('pageshow', event => {
        if (event.persisted) {
            observer.observe(itemsContainer, { childList: true });
            refreshLocalEstimate();
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadInitialEstimate, { once: true });
    } else {
        loadInitialEstimate();
    }
}
