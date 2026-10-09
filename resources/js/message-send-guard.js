const form = document.querySelector('[data-message-form]');
if (form) {
    let sending = false;
    const button = form.querySelector('button[type="submit"]');
    const original = button?.textContent;
    form.addEventListener('submit', event => {
        if (sending || form.dataset.accessLost === 'true') { event.preventDefault(); return; }
        sending = true;
        form.setAttribute('aria-busy', 'true');
        if (button) {button.disabled = true; button.textContent = 'Đang gửi…';}
    });
    window.addEventListener('pageshow', () => {
        if (form.dataset.accessLost === 'true') return;
        sending = false; form.removeAttribute('aria-busy');
        if (button) {button.disabled = false; button.textContent = original;}
    });
}
