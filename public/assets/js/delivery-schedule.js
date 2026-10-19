(function () {
    const method = document.getElementById('method');
    const status = document.getElementById('status');
    const date = document.getElementById('pickup_date');
    const time = document.getElementById('pickup_time');
    const fulfillment = document.getElementById('fulfillment');
    const address = document.getElementById('address');
    const order = document.getElementById('order_id');
    if (!method || !status || !date || !time) return;
    function prefillFromBooking() {
        if (!order || !method.form?.hasAttribute('data-booking-prefill')) return;
        const option = order.selectedOptions[0];
        if (!option?.dataset.bookingId) return;
        const prefix = method.value === 'nhan_do' ? 'receive' : 'return';
        const fulfillmentValue = option.dataset[`${prefix}Method`];
        const addressValue = option.dataset[`${prefix}Address`] || '';
        if (fulfillmentValue && fulfillment) fulfillment.value = fulfillmentValue;
        if (address) address.value = addressValue;
        if (method.value === 'nhan_do') {
            date.value = option.dataset.pickupDate || '';
            time.value = option.dataset.pickupTime || '';
        } else {
            date.value = '';
            time.value = '';
        }
        update();
    }
    function update() {
        const executing = ['picking', 'delivering', 'completed'].includes(status.value);
        const required = method.value === 'nhan_do' || executing;
        date.required = required || Boolean(time.value);
        time.required = required || Boolean(date.value);
        address.required = fulfillment.value === 'Tại nhà';
        address.disabled = !address.required;
        address.classList.toggle('bg-light', address.disabled);
        address.classList.toggle('text-muted', address.disabled);
        document.getElementById('schedule-help').textContent = required
            ? 'Chặng nhận đồ hoặc chặng đang thực hiện cần đủ ngày và giờ.'
            : 'Phiếu trả đang chờ có thể chưa đặt lịch. Khi đặt lịch, hãy nhập đủ ngày và giờ.';
    }
    [method, status, date, time, fulfillment].forEach(control => control.addEventListener('change', update));
    if (order && method.form?.hasAttribute('data-booking-prefill')) {
        order.addEventListener('change', prefillFromBooking);
        method.addEventListener('change', prefillFromBooking);
    }
    fulfillment.form.addEventListener('reset', () => setTimeout(update, 0));
    prefillFromBooking();
    update();

    const employeePicker = document.querySelector('[data-employee-picker]');
    if (employeePicker) {
        const employeeSearch = employeePicker.querySelector('[data-employee-search]');
        const employeeId = employeePicker.querySelector('[data-employee-id]');
        const selectedEmployeeSearch = employeePicker.querySelector('[data-selected-employee-search]');
        const dropdown = employeePicker.querySelector('[data-employee-dropdown]');
        const toggle = employeePicker.querySelector('[data-employee-toggle]');
        const emptyMessage = employeePicker.querySelector('[data-employee-empty]');
        const selectedName = employeePicker.querySelector('[data-selected-employee-name]');
        const options = [...employeePicker.querySelectorAll('[data-employee-option]')];
        let activeOptionIndex = -1;

        const normalize = value => value
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/đ/g, 'd')
            .replace(/Đ/g, 'D')
            .toLocaleLowerCase('vi');

        const visibleOptions = () => options.filter(option => !option.hidden);

        const filterOptions = () => {
            const query = normalize(employeeSearch.value.trim());
            options.forEach(option => {
                option.hidden = !normalize(option.dataset.employeeSearchText || '').includes(query);
            });
            emptyMessage.hidden = visibleOptions().length > 0;
            activeOptionIndex = -1;
            options.forEach(option => option.removeAttribute('tabindex'));
        };

        const closeDropdown = ({ restoreFocus = false } = {}) => {
            dropdown.hidden = true;
            dropdown.classList.remove('show');
            toggle.setAttribute('aria-expanded', 'false');
            employeeSearch.value = '';
            filterOptions();
            if (restoreFocus) toggle.focus();
        };

        const openDropdown = () => {
            dropdown.hidden = false;
            dropdown.classList.add('show');
            toggle.setAttribute('aria-expanded', 'true');
            employeeSearch.value = '';
            filterOptions();
            employeeSearch.focus();
        };

        const chooseEmployee = option => {
            employeeId.value = option.dataset.employeeId;
            selectedName.textContent = option.dataset.employeeName;
            selectedEmployeeSearch.value = option.dataset.employeeName;
            options.forEach(item => {
                const isSelected = item === option;
                item.setAttribute('aria-selected', String(isSelected));
                item.querySelector('[data-selected-indicator]').hidden = !isSelected;
            });
            closeDropdown({ restoreFocus: true });
        };

        const focusOption = index => {
            const visible = visibleOptions();
            if (visible.length === 0) return;
            activeOptionIndex = (index + visible.length) % visible.length;
            visible[activeOptionIndex].focus();
        };

        toggle.addEventListener('click', () => {
            if (dropdown.hidden) {
                openDropdown();
            } else {
                closeDropdown();
            }
        });

        employeeSearch.addEventListener('input', filterOptions);
        employeeSearch.addEventListener('keydown', event => {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                focusOption(0);
            } else if (event.key === 'Escape') {
                event.preventDefault();
                closeDropdown({ restoreFocus: true });
            } else if (event.key === 'Enter') {
                event.preventDefault();
                if (visibleOptions().length === 1) {
                    chooseEmployee(visibleOptions()[0]);
                }
            }
        });

        options.forEach(option => {
            option.addEventListener('click', () => chooseEmployee(option));
            option.addEventListener('keydown', event => {
                const visible = visibleOptions();
                const index = visible.indexOf(option);
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    focusOption(index + 1);
                } else if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    if (index === 0) employeeSearch.focus();
                    else focusOption(index - 1);
                } else if (event.key === 'Escape') {
                    event.preventDefault();
                    closeDropdown({ restoreFocus: true });
                }
            });
        });

        employeePicker.closest('form').addEventListener('submit', () => {
            if (!dropdown.hidden && employeeSearch.value.trim()) {
                employeeId.value = '';
            }
        });
        document.addEventListener('pointerdown', event => {
            if (!dropdown.hidden && !employeePicker.contains(event.target)) {
                if (event.target.closest('button[type="submit"], input[type="submit"]')) return;
                closeDropdown();
            }
        });

        filterOptions();
    }
})();
