/**
 * Flatpickr Month/Year Display Fix
 * - Thu nhỏ dropdown tháng
 * - Hiển thị năm dưới dạng text inline (không phải dropdown)
 */
(function() {
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof window.flatpickr === 'undefined') {
            return;
        }

        // Store original buildMonthDropdown to customize it
        const originalBuild = window.flatpickr.prototype.buildMonthDropdown;
        
        // Override the month/year display
        const _overrideElementPosition = function(instance) {
            // Hide native arrows and default dropdowns after initialization
            setTimeout(function() {
                const currentMonthContainer = instance.element._flatpickr.calendarContainer
                    .querySelector('.flatpickr-current-month');
                
                if (!currentMonthContainer) return;

                // Force hide all chevron arrows
                const arrows = currentMonthContainer.querySelectorAll('.arrowUp, .arrowDown');
                arrows.forEach(arrow => {
                    arrow.style.display = 'none';
                    arrow.style.visibility = 'hidden';
                    arrow.style.opacity = '0';
                });

                // Compact size for month dropdown
                const monthSelect = currentMonthContainer.querySelector('.flatpickr-monthDropdown-months');
                if (monthSelect) {
                    monthSelect.style.fontSize = '0.9rem';
                    monthSelect.style.padding = '3px 5px';
                    monthSelect.style.minWidth = '70px';
                    monthSelect.style.appearance = 'none';
                    monthSelect.style.webkitAppearance = 'none';
                    monthSelect.style.mozAppearance = 'none';
                    
                    // Hide the default arrow using ::after
                    monthSelect.style.backgroundImage = 'none';
                    
                    // Webkit-specific fix
                    const parentSpan = monthSelect.parentElement;
                    if (parentSpan) {
                        parentSpan.style.position = 'relative';
                    }
                }

                // Year select can be kept as is or made compact
                const yearSelect = currentMonthContainer.querySelector('.cur-year');
                if (yearSelect) {
                    yearSelect.style.fontSize = '1rem';
                    yearSelect.style.padding = '4px 6px';
                }
                
                // Apply CSS fix for hiding native arrows
                applyCustomStyles();
            }, 10);
        };

        // Monkey-patch the onReady hook
        const _onReadyHook = instance.onReady.bind(instance);
        instance.onReady = function(dates, dateStr, options, dateTimeType) {
            _onReadyHook(dates, dateStr, options, dateTimeType);
            _overrideElementPosition(instance);
        };

        // Custom CSS injection for this page only
        function applyCustomStyles() {
            const styleId = 'flatpickr-custom-styles';
            
            // Check if styles already injected
            if (document.getElementById(styleId)) {
                return;
            }

            const style = document.createElement('style');
            style.id = styleId;
            style.textContent = `
                /* Hide native chevrons */
                div.flatpickr-current-month span.arrowUp,
                div.flatpickr-current-month span.arrowDown,
                .flatpickr-monthDropdown::after,
                .cur-year::after {
                    display: none !important;
                    visibility: hidden !important;
                    opacity: 0 !important;
                }

                /* Compact month dropdown */
                .flatpickr-monthDropdown-months {
                    font-size: 0.9rem !important;
                    padding: 3px 5px !important;
                    min-width: 70px !important;
                    cursor: pointer !important;
                }

                /* Compact year display */
                .cur-year {
                    font-size: 1rem !important;
                    padding: 4px 6px !important;
                    border-radius: 4px !important;
                    background: rgba(0,0,0,0.02) !important;
                }

                /* Remove default browser arrow */
                .flatpickr-monthDropdown-months::-ms-expand,
                .cur-year::-ms-expand {
                    display: none !important;
                }

                .flatpickr-monthDropdown-months::-webkit-inner-spin-button,
                .flatpickr-monthDropdown-months::-webkit-outer-spin-button,
                .cur-year::-webkit-inner-spin-button,
                .cur-year::-webkit-outer-spin-button {
                    -webkit-appearance: none !important;
                    margin: 0 !important;
                    display: none !important;
                }

                /* Focus state */
                .flatpickr-monthDropdown-months:focus,
                .cur-year:focus {
                    outline: none !important;
                    box-shadow: none !important;
                }

                /* Ensure no background image overrides */
                .flatpickr-monthDropdown-months:hover {
                    background-image: none !important;
                }
            `;
            document.head.appendChild(style);
        }
    });
})();
