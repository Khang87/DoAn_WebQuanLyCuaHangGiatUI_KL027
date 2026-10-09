/* Shared create/edit item controls. The server remains authoritative on save. */
(function (root) {
    root.OrderItemPricing = function ({ prices, garments, minimumWeight }) {
        function eligiblePrices(row) {
            const service = row.querySelector('.item-service').value;
            const garment = row.querySelector('.item-garment').value;
            return prices.filter(price => String(price.service_id) === service && String(price.garment_id) === garment);
        }

        function syncGarmentOptions(row) {
            const service = row.querySelector('.item-service').value;
            const select = row.querySelector('.item-garment');
            const selected = select.value;
            const eligible = new Set(prices.filter(price => String(price.service_id) === service).map(price => String(price.garment_id)));
            select.replaceChildren(new Option('Chọn loại đồ', ''));
            Object.entries(garments).forEach(([id, label]) => {
                if (eligible.has(String(id))) select.add(new Option(label, id));
            });
            select.value = eligible.has(selected) ? selected : '';
        }

        function updateRowState(row) {
            const select = row.querySelector('.item-unit-value');
            const selected = select.value;
            const eligible = eligiblePrices(row);
            select.replaceChildren(new Option('Chọn ĐVT', ''));
            eligible.forEach(price => select.add(new Option(price.unit, price.unit_id)));
            const validSelection = eligible.some(price => String(price.unit_id) === selected);
            select.value = validSelection ? selected : (eligible.length === 1 ? String(eligible[0].unit_id) : '');
            const match = eligible.find(price => String(price.unit_id) === select.value);
            const serviceSelected = row.querySelector('.item-service').value !== '';
            let warning = row.querySelector('[data-price-warning]');
            if (!warning) {
                warning = document.createElement('div');
                warning.dataset.priceWarning = '';
                warning.className = 'form-text text-danger';
                warning.setAttribute('role', 'status');
                select.parentElement.append(warning);
            }
            warning.textContent = serviceSelected && ((!prices.some(price => String(price.service_id) === row.querySelector('.item-service').value)) || (row.querySelector('.item-garment').value !== '' && eligible.length === 0))
                ? 'Chưa có bảng giá hiệu lực cho dịch vụ/loại đồ này. Chọn tổ hợp khác hoặc thiết lập bảng giá trước khi tạo đơn.' : '';
            select.setCustomValidity(match ? '' : 'Hãy chọn đơn vị có bảng giá đang hiệu lực.');

            const isKg = ['kg', 'kgs', 'kilogram'].includes(String(match?.unit || '').trim().toLowerCase());
            const quantity = row.querySelector('.item-quantity');
            const weight = row.querySelector('.item-weight');
            const price = row.querySelector('.item-price');
            price.readOnly = true;
            price.value = match?.price ?? 0;
            quantity.disabled = isKg;
            quantity.required = !isKg;
            if (isKg) quantity.value = '1';
            weight.disabled = !isKg;
            weight.required = isKg;
            if (!isKg) weight.value = '0';
            return { match, isKg };
        }

        function updateRow(row) {
            const { match, isKg } = updateRowState(row);
            const quantity = Math.max(1, Number(row.querySelector('.item-quantity').value) || 1);
            const weight = Math.max(0, Number(row.querySelector('.item-weight').value) || 0);
            const amount = Math.round((Number(match?.price) || 0) * (isKg ? Math.max(weight, minimumWeight) : quantity) * 100) / 100;
            row.querySelector('.item-subtotal').value = amount;
            return amount;
        }

        return { syncGarmentOptions, updateRowState, updateRow };
    };
})(typeof window === 'undefined' ? globalThis : window);
