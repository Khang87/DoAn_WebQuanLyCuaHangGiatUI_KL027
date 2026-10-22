// The server enforces cooldown; this timer only explains when resend is available.
export function updateOtpCooldown(button, deadline, currentTime = Date.now()) {
    const seconds = Math.max(0, Math.ceil((deadline - currentTime) / 1000));
    button.disabled = seconds > 0;
    button.textContent = seconds > 0 ? `Gửi lại mã (${seconds}s)` : 'Gửi lại mã OTP';
    return seconds;
}

export function togglePasswordVisibility(input, button) {
    const isVisible = input.type === 'password';
    input.type = isVisible ? 'text' : 'password';

    const icon = button.querySelector('i');
    icon?.classList.toggle('bi-eye', !isVisible);
    icon?.classList.toggle('bi-eye-slash', isVisible);
    button.setAttribute('aria-label', isVisible ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
    button.setAttribute('aria-pressed', String(isVisible));
}

if (typeof document !== 'undefined') {
    document.querySelectorAll('[data-toggle-password]').forEach(button => {
        const input = document.getElementById(button.dataset.togglePassword);
        if (input) {
            button.addEventListener('click', () => togglePasswordVisibility(input, button));
        }
    });
}

const button = typeof document === 'undefined' ? null : document.querySelector('[data-otp-resend]');
if (button) {
    const seconds = Number(button.dataset.cooldownSeconds);
    const deadline = Date.now() + (Number.isFinite(seconds) ? Math.max(0, seconds) : 60) * 1000;
    let timer, submitting = false;
    const tick = () => {
        if (!submitting && updateOtpCooldown(button, deadline) === 0) clearInterval(timer);
    };
    tick();
    if (button.disabled) timer = setInterval(tick, 1000);
    document.addEventListener('visibilitychange', tick);
    window.addEventListener('pageshow', () => { submitting = false; tick(); });
    button.form.addEventListener('submit', event => {
        if (button.disabled || submitting) {
            event.preventDefault();
            return;
        }
        submitting = true;
        button.disabled = true;
        button.textContent = 'Đang gửi mã OTP…';
    });
}
