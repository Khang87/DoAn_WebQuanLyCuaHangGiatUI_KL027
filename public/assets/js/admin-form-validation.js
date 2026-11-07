(function () {
    'use strict';

    const requiredControlsSelector = 'input[required], select[required], textarea[required]';
    const addButtonSelector = '.btn-add, .btn-add-all, [data-add-submit]';
    const addButtonLabel = /^(thêm|tạo|add)\b/i;

    function isAddSubmitButton(button) {
        return button.matches(addButtonSelector)
            || addButtonLabel.test((button.textContent || '').trim());
    }

    function hasValue(control) {
        if (control.type === 'checkbox' || control.type === 'radio') {
            return control.checked;
        }

        if (control.type === 'file') {
            return control.files.length > 0;
        }

        return control.value !== '';
    }

    function updateControlState(control, touched) {
        if (!control.matches(requiredControlsSelector) || control.disabled) {
            return;
        }

        if (control.validity.valid) {
            control.classList.remove('is-invalid');

            if (hasValue(control)) {
                control.classList.add('is-valid');
            } else {
                control.classList.remove('is-valid');
            }

            return;
        }

        control.classList.remove('is-valid');

        if (touched) {
            control.classList.add('is-invalid');
        }
    }

    function isFormValid(form) {
        return Array.from(form.elements).every(function (control) {
            return !control.willValidate || control.validity.valid;
        });
    }

    function updateAddButtons(form) {
        const valid = isFormValid(form);

        Array.from(form.querySelectorAll('button[type="submit"]'))
            .filter(isAddSubmitButton)
            .forEach(function (button) {
                button.classList.add('btn-add');
                button.disabled = !valid;
                button.setAttribute('aria-disabled', String(!valid));
            });
    }

    function updateForm(form, changedControl, touched) {
        if (changedControl) {
            updateControlState(changedControl, touched);
        }

        updateAddButtons(form);
    }

    document.querySelectorAll('form').forEach(function (form) {
        updateForm(form);

        form.addEventListener('input', function (event) {
            updateForm(form, event.target, true);
        });

        form.addEventListener('change', function (event) {
            updateForm(form, event.target, true);
        });

        form.addEventListener('focusout', function (event) {
            updateForm(form, event.target, true);
        });

        form.addEventListener('invalid', function (event) {
            updateControlState(event.target, true);
        }, true);
    });
})();
