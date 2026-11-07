(function () {
    'use strict';

    const addButtonSelector = '.btn-add, .btn-add-all, [data-add-submit]';
    const addButtonLabel = /^(thêm|tạo|add)\b/i;

    function isAddSubmitButton(button) {
        return button.matches(addButtonSelector)
            || addButtonLabel.test((button.textContent || '').trim());
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

    function updateForm(form) {
        updateAddButtons(form);
    }

    document.querySelectorAll('form').forEach(function (form) {
        updateForm(form);

        form.addEventListener('input', function () {
            updateForm(form);
        });

        form.addEventListener('change', function () {
            updateForm(form);
        });
    });
})();
