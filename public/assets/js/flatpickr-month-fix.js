/**
 * Flatpickr Month/Year Display Fix
 * - Thu nhỏ dropdown tháng
 * - Hiển thị năm dưới dạng text inline (không phải dropdown)
 */
(function () {
    function applyCustomStyles() {
        const styleId = 'flatpickr-custom-styles';

        if (document.getElementById(styleId)) {
            return;
        }

        const style = document.createElement('style');
        style.id = styleId;
        style.textContent = `
            div.flatpickr-current-month span.arrowUp,
            div.flatpickr-current-month span.arrowDown,
            .flatpickr-monthDropdown::after,
            .cur-year::after {
                display: none !important;
                visibility: hidden !important;
                opacity: 0 !important;
            }

            .flatpickr-monthDropdown-months {
                font-size: 0.9rem !important;
                padding: 3px 5px !important;
                min-width: 70px !important;
                cursor: pointer !important;
            }

            .cur-year {
                font-size: 1rem !important;
                padding: 4px 6px !important;
                border-radius: 4px !important;
                background: rgba(0, 0, 0, 0.02) !important;
            }

            .flatpickr-monthDropdown-months::-ms-expand,
            .cur-year::-ms-expand,
            .flatpickr-monthDropdown-months::-webkit-inner-spin-button,
            .flatpickr-monthDropdown-months::-webkit-outer-spin-button,
            .cur-year::-webkit-inner-spin-button,
            .cur-year::-webkit-outer-spin-button {
                display: none !important;
            }

            .flatpickr-monthDropdown-months::-webkit-inner-spin-button,
            .flatpickr-monthDropdown-months::-webkit-outer-spin-button,
            .cur-year::-webkit-inner-spin-button,
            .cur-year::-webkit-outer-spin-button {
                -webkit-appearance: none !important;
                margin: 0 !important;
            }

            .flatpickr-monthDropdown-months:focus,
            .cur-year:focus {
                outline: none !important;
                box-shadow: none !important;
            }

            .flatpickr-monthDropdown-months:hover {
                background-image: none !important;
            }
        `;
        document.head.appendChild(style);
    }

    function styleCalendar(calendar) {
        const currentMonthContainer = calendar.querySelector('.flatpickr-current-month');

        if (!currentMonthContainer) {
            return;
        }

        currentMonthContainer.querySelectorAll('.arrowUp, .arrowDown').forEach(function (arrow) {
            arrow.style.display = 'none';
            arrow.style.visibility = 'hidden';
            arrow.style.opacity = '0';
        });

        const monthSelect = currentMonthContainer.querySelector('.flatpickr-monthDropdown-months');
        if (monthSelect) {
            monthSelect.style.fontSize = '0.9rem';
            monthSelect.style.padding = '3px 5px';
            monthSelect.style.minWidth = '70px';
            monthSelect.style.appearance = 'none';
            monthSelect.style.webkitAppearance = 'none';
            monthSelect.style.mozAppearance = 'none';
            monthSelect.style.backgroundImage = 'none';

            if (monthSelect.parentElement) {
                monthSelect.parentElement.style.position = 'relative';
            }
        }

        const yearSelect = currentMonthContainer.querySelector('.cur-year');
        if (yearSelect) {
            yearSelect.style.fontSize = '1rem';
            yearSelect.style.padding = '4px 6px';
        }

        applyCustomStyles();
    }

    function styleCalendars(root) {
        if (root.nodeType !== Node.ELEMENT_NODE) {
            return;
        }

        if (root.matches('.flatpickr-calendar')) {
            styleCalendar(root);
        }

        root.querySelectorAll('.flatpickr-calendar').forEach(styleCalendar);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.flatpickr-calendar').forEach(styleCalendar);

        const observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(styleCalendars);
            });
        });

        observer.observe(document.body, { childList: true, subtree: true });
    });
})();
