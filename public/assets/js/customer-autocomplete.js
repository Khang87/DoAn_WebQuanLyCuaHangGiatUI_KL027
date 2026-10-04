(function () {
    'use strict';

    function normalize(value) {
        return value
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/đ/g, 'd')
            .replace(/Đ/g, 'D')
            .trim()
            .toLocaleLowerCase('vi');
    }

    function initialize() {
        const form = document.getElementById('orderForm');
        const searchInput = document.getElementById('customer_search');
        const customerSelect = document.getElementById('customer_id');
        const suggestions = document.getElementById('customer_suggestions');

        if (!form || !searchInput || !customerSelect || !suggestions) {
            return;
        }

        const options = Array.from(customerSelect.options).filter(option => option.value);
        let activeIndex = -1;

        function closeSuggestions() {
            suggestions.classList.add('d-none');
            searchInput.setAttribute('aria-expanded', 'false');
            searchInput.removeAttribute('aria-activedescendant');
            activeIndex = -1;
        }

        function selectCustomer(option) {
            customerSelect.value = option.value;
            searchInput.value = option.dataset.name || option.textContent.trim();
            closeSuggestions();
            customerSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function showSuggestions() {
            const query = normalize(searchInput.value);
            suggestions.replaceChildren();
            activeIndex = -1;

            if (!query) {
                closeSuggestions();
                return;
            }

            const matches = options.filter(option => {
                const name = normalize(option.dataset.name || option.textContent);
                const nameStartsWithQuery = name.startsWith(query)
                    || name.split(/\s+/).some(part => part.startsWith(query));
                const phone = normalize(option.dataset.phone || '');

                return nameStartsWithQuery || phone.startsWith(query);
            }).slice(0, 8);

            matches.forEach((option, index) => {
                const button = document.createElement('button');
                const name = option.dataset.name || option.textContent.trim();
                const phone = option.dataset.phone;

                button.type = 'button';
                button.id = `customer_suggestion_${index}`;
                button.className = 'list-group-item list-group-item-action text-start';
                button.setAttribute('role', 'option');
                button.textContent = phone ? `${name} · ${phone}` : name;
                button.addEventListener('click', () => selectCustomer(option));
                suggestions.append(button);
            });

            if (matches.length === 0) {
                const emptyMessage = document.createElement('div');
                emptyMessage.className = 'list-group-item text-muted';
                emptyMessage.textContent = 'Không tìm thấy khách hàng phù hợp';
                suggestions.append(emptyMessage);
            }

            suggestions.classList.remove('d-none');
            searchInput.setAttribute('aria-expanded', 'true');
        }

        function setActiveSuggestion(direction) {
            const buttons = suggestions.querySelectorAll('button[role="option"]');
            if (buttons.length === 0) {
                return;
            }

            activeIndex = (activeIndex + direction + buttons.length) % buttons.length;
            buttons.forEach((button, index) => {
                button.classList.toggle('active', index === activeIndex);
                button.setAttribute('aria-selected', index === activeIndex ? 'true' : 'false');
            });
            searchInput.setAttribute('aria-activedescendant', buttons[activeIndex].id);
        }

        const selectedOption = customerSelect.options[customerSelect.selectedIndex];
        if (selectedOption && selectedOption.value) {
            searchInput.value = selectedOption.dataset.name || selectedOption.textContent.trim();
        }

        searchInput.addEventListener('input', () => {
            customerSelect.value = '';
            customerSelect.dispatchEvent(new Event('change', { bubbles: true }));
            showSuggestions();
        });
        searchInput.addEventListener('focus', showSuggestions);
        searchInput.addEventListener('keydown', event => {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                setActiveSuggestion(1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                setActiveSuggestion(-1);
            } else if (event.key === 'Enter' && activeIndex >= 0) {
                event.preventDefault();
                const activeButton = suggestions.querySelectorAll('button[role="option"]')[activeIndex];
                activeButton?.click();
            } else if (event.key === 'Escape') {
                closeSuggestions();
            }
        });
        document.addEventListener('click', event => {
            if (!form.contains(event.target) || !suggestions.contains(event.target)) {
                closeSuggestions();
            }
        });
        form.addEventListener('submit', event => {
            if (!customerSelect.value) {
                event.preventDefault();
                searchInput.setCustomValidity('Vui lòng chọn khách hàng từ danh sách gợi ý.');
                searchInput.reportValidity();
                searchInput.focus();
            }
        });
        searchInput.addEventListener('input', () => searchInput.setCustomValidity(''));
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
})();
