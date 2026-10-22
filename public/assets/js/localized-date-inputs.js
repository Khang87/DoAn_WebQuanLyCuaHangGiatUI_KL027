document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.flatpickr !== 'function') {
        return;
    }

    const vietnameseLocale = {
        firstDayOfWeek: 1,
        weekdays: {
            shorthand: ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'],
            longhand: ['Chủ nhật', 'Thứ hai', 'Thứ ba', 'Thứ tư', 'Thứ năm', 'Thứ sáu', 'Thứ bảy'],
        },
        months: {
            shorthand: ['Th1', 'Th2', 'Th3', 'Th4', 'Th5', 'Th6', 'Th7', 'Th8', 'Th9', 'Th10', 'Th11', 'Th12'],
            longhand: [
                'Tháng 1', 'Tháng 2', 'Tháng 3', 'Tháng 4', 'Tháng 5', 'Tháng 6',
                'Tháng 7', 'Tháng 8', 'Tháng 9', 'Tháng 10', 'Tháng 11', 'Tháng 12',
            ],
        },
        time_24hr: true,
    };

    document.querySelectorAll('input[type="date"], input[type="datetime-local"]').forEach(function (input) {
        const includesTime = input.type === 'datetime-local';
        const displayFormat = includesTime ? 'd/m/Y H:i' : 'd/m/Y';
        const dateFormat = includesTime ? 'Y-m-d\\TH:i' : 'Y-m-d';
        const picker = window.flatpickr(input, {
            allowInput: !input.readOnly,
            altInput: true,
            altFormat: displayFormat,
            dateFormat: dateFormat,
            disableMobile: true,
            enableTime: includesTime,
            locale: vietnameseLocale,
            time_24hr: true,
            onReady: function (_dates, _dateString, instance) {
                instance.altInput.placeholder = displayFormat;
                instance.altInput.setAttribute('inputmode', 'numeric');
                if (input.readOnly) {
                    instance.altInput.readOnly = true;
                }
                if (input.disabled) {
                    instance.altInput.disabled = true;
                }
            },
        });

        if (!picker || !input.form) {
            return;
        }

        input.form.addEventListener('submit', function (event) {
            const visibleValue = picker.altInput.value.trim();
            if (visibleValue === '') {
                picker.altInput.setCustomValidity('');
                picker.clear();
                return;
            }

            const parsedDate = picker.parseDate(visibleValue, displayFormat);
            if (!parsedDate || picker.formatDate(parsedDate, displayFormat) !== visibleValue) {
                picker.altInput.setCustomValidity(
                    includesTime
                        ? 'Vui lòng nhập ngày và giờ hợp lệ theo định dạng dd/MM/yyyy HH:mm.'
                        : 'Vui lòng nhập ngày hợp lệ theo định dạng dd/MM/yyyy.',
                );
                event.preventDefault();
                picker.altInput.reportValidity();

                return;
            }

            picker.altInput.setCustomValidity('');
            picker.setDate(parsedDate, false);
        });

        picker.altInput.addEventListener('input', function () {
            picker.altInput.setCustomValidity('');
        });
    });
});
