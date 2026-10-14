const panel = document.querySelector('[data-booking-estimate]');

if (panel) {
    const form = document.querySelector('#booking-edit-form');
    const total = panel.querySelector('[data-estimate-total]');
    const details = panel.querySelector('[data-estimate-details]');
    const itemBreakdown = panel.querySelector('[data-estimate-items]');
    const warning = panel.querySelector('[data-estimate-warning]');
    const quoteButton = document.querySelector('#booking-quote-delivery');
    const quoteStatus = document.querySelector('#booking-delivery-quote-status');
    const quoteResult = document.querySelector('#booking-delivery-quote-result');
    const recalculateInput = form.querySelector('[name="recalculate_delivery_fee"]');
    const money = value => new Intl.NumberFormat('vi-VN').format(value) + ' VNĐ';
    let timer;
    let controller;
    let quoteController;
    let generation = 0;
    let quoteGeneration = 0;
    let latestEstimate;
    let deliveryQuote;

    function renderEstimate(data) {
        latestEstimate = data;
        const pickupFee = deliveryQuote?.pickup_fee ?? data.PhiNhanDo;
        const returnFee = deliveryQuote?.return_fee ?? data.PhiGiaoDo;
        const deliveryFee = pickupFee + returnFee;
        const amountDue = Math.max(
            0,
            data.TongTien - data.TienGiamKhuyenMai - data.TienGiamDoDiem,
        ) + deliveryFee;

        total.textContent = money(amountDue);
        details.textContent = [
            `Tổng tiền dịch vụ: ${money(data.TongTien)}`,
            `Giảm từ mã: −${money(data.TienGiamKhuyenMai)}`,
            `Điểm đã dùng (${data.DiemSuDung} điểm): −${money(data.TienGiamDoDiem)}`,
            `Phí nhận đồ: ${money(pickupFee)}`,
            `Phí giao đồ: ${money(returnFee)}`,
        ].join(' · ');
        warning.textContent = data.promotion_warning ?? '';

        const table = document.createElement('table');
        table.className = 'table table-sm table-bordered align-middle mb-0';
        const thead = table.createTHead();
        const header = thead.insertRow();

        for (const label of ['Dịch vụ', 'Loại đồ', 'Số lượng / khối lượng', 'Đơn giá', 'Thành tiền']) {
            const cell = document.createElement('th');
            cell.scope = 'col';
            cell.textContent = label;
            header.append(cell);
        }

        const body = table.createTBody();
        for (const item of data.items) {
            const row = body.insertRow();
            for (const value of [
                item.service_name,
                item.garment_name,
                item.amount,
                money(item.unit_price),
                money(item.subtotal),
            ]) {
                row.insertCell().textContent = value;
            }
        }

        itemBreakdown.replaceChildren(table);
    }

    function renderDeliveryQuote() {
        if (!latestEstimate) {
            return;
        }

        renderEstimate(latestEstimate);
    }

    function changedDeliveryDetails() {
        deliveryQuote = null;
        quoteController?.abort();
        quoteGeneration++;
        recalculateInput.value = '0';
        quoteResult.hidden = true;
        quoteStatus.textContent =
            'Phí đang dùng là phí đã lưu từ Booking. Nhấn nút để tính lại theo thông tin hiện tại.';
    }

    async function requestEstimate() {
        clearTimeout(timer);
        controller?.abort();
        const version = ++generation;
        total.textContent = 'Đang tính…';
        details.textContent = '';
        warning.textContent = '';
        itemBreakdown.replaceChildren();

        timer = setTimeout(async () => {
            const requestController = new AbortController();
            controller = requestController;
            const deadline = setTimeout(() => requestController.abort(), 30000);

            try {
                const items = [...form.querySelectorAll('.booking-item')].map(row => {
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
                });
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
                    signal: requestController.signal,
                });
                const data = await response.json();

                if (version !== generation) {
                    return;
                }
                if (!response.ok) {
                    throw Error(response.status === 422
                        ? 'Chọn đủ dịch vụ, loại đồ, đơn vị có giá và nhập số lượng/khối lượng hợp lệ.'
                        : 'Không thể tính ước tính. Vui lòng thử lại.');
                }
                for (const key of [
                    'TongTien', 'TienGiamKhuyenMai', 'DiemSuDung', 'TienGiamDoDiem',
                    'PhiNhanDo', 'PhiGiaoDo', 'ThanhTien',
                ]) {
                    if (typeof data[key] !== 'number' || !Number.isFinite(data[key]) || data[key] < 0) {
                        throw Error('Không thể đọc ước tính.');
                    }
                }
                if (!Array.isArray(data.items)) {
                    throw Error('Không thể đọc chi tiết dịch vụ.');
                }

                renderEstimate(data);
            } catch (error) {
                if (version === generation) {
                    total.textContent = 'Chưa có ước tính';
                    warning.textContent = error.name === 'AbortError'
                        ? 'Kết nối chậm. Thay đổi dữ liệu để tính lại.'
                        : error.message;
                }
            } finally {
                clearTimeout(deadline);
            }
        }, 350);
    }

    quoteButton.addEventListener('click', async () => {
        if (quoteButton.disabled) {
            return;
        }

        const method = form.querySelector('[name="method"]');
        const address = form.querySelector('[name="address"]');
        const returnMethod = form.querySelector('[name="return_method"]');
        const returnAddress = form.querySelector('[name="return_address"]');

        if (method.value === 'Tại nhà' && !address.value.trim()) {
            quoteStatus.textContent = 'Vui lòng nhập địa chỉ nhận đồ tại nhà.';
            address.focus();
            return;
        }
        if (returnMethod.value === 'Tại nhà' && !returnAddress.value.trim()) {
            quoteStatus.textContent = 'Vui lòng nhập địa chỉ trả đồ tại nhà.';
            returnAddress.focus();
            return;
        }

        quoteButton.disabled = true;
        quoteStatus.textContent = 'Đang tính phí giao nhận...';
        quoteController?.abort();
        const requestController = new AbortController();
        quoteController = requestController;
        const version = ++quoteGeneration;

        try {
            const response = await fetch(panel.dataset.deliveryQuote, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    method: method.value,
                    address: method.value === 'Tại nhà' ? address.value.trim() : null,
                    return_method: returnMethod.value,
                    return_address: returnMethod.value === 'Tại nhà' ? returnAddress.value.trim() : null,
                }),
                signal: requestController.signal,
            });
            const data = await response.json().catch(() => null);

            if (version !== quoteGeneration) {
                return;
            }
            if (!response.ok) {
                const validationMessage = Object.values(data?.errors ?? {}).flat()[0];
                throw Error(
                    validationMessage
                    || data?.message
                    || `Máy chủ không thể tính phí giao nhận (HTTP ${response.status}). Vui lòng thử lại.`,
                );
            }
            if (
                !data
                || !Number.isFinite(Number(data.pickup_fee))
                || !Number.isFinite(Number(data.return_fee))
            ) {
                throw Error('Máy chủ trả về dữ liệu phí giao nhận không hợp lệ. Vui lòng thử lại.');
            }

            deliveryQuote = {
                pickup_fee: Number(data.pickup_fee),
                return_fee: Number(data.return_fee),
            };
            recalculateInput.value = '1';
            quoteResult.querySelector('[data-pickup-fee]').textContent = money(deliveryQuote.pickup_fee);
            quoteResult.querySelector('[data-return-fee]').textContent = money(deliveryQuote.return_fee);
            quoteResult.hidden = false;
            quoteStatus.textContent = 'Đã báo giá theo địa chỉ hiện tại. Phí sẽ được máy chủ tính lại khi xác nhận.';
            renderDeliveryQuote();
        } catch (error) {
            if (version === quoteGeneration) {
                quoteStatus.textContent = error.name === 'AbortError'
                    ? 'Yêu cầu tính phí đã bị hủy.'
                    : error instanceof TypeError
                        ? 'Không thể kết nối đến máy chủ tính phí giao nhận. Vui lòng thử lại.'
                        : error.message;
            }
        } finally {
            quoteButton.disabled = false;
        }
    });

    const deliveryFields = [
        form.querySelector('[name="method"]'),
        form.querySelector('[name="address"]'),
        form.querySelector('[name="return_method"]'),
        form.querySelector('[name="return_address"]'),
    ];
    for (const field of deliveryFields) {
        field.addEventListener('input', changedDeliveryDetails);
        field.addEventListener('change', changedDeliveryDetails);
    }

    form.addEventListener('reset', () => {
        setTimeout(() => {
            changedDeliveryDetails();
            requestEstimate();
        }, 0);
    });
    form.addEventListener('input', requestEstimate);
    form.addEventListener('change', requestEstimate);
    const observer = new MutationObserver(requestEstimate);
    observer.observe(form.querySelector('#booking-items'), { childList: true });
    window.addEventListener('pagehide', () => {
        clearTimeout(timer);
        generation++;
        quoteGeneration++;
        controller?.abort();
        quoteController?.abort();
        observer.disconnect();
    });
    window.addEventListener('pageshow', event => {
        if (event.persisted) {
            observer.observe(form.querySelector('#booking-items'), { childList: true });
            requestEstimate();
        }
    });

    requestEstimate();
}
