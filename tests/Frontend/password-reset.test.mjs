import test from 'node:test';
import assert from 'node:assert/strict';
import { togglePasswordVisibility, updateOtpCooldown } from '../../resources/js/password-reset.js';

test('resend stays disabled until deadline, then becomes available exactly at 60 seconds', () => {
    const button = {};
    assert.equal(updateOtpCooldown(button, 60000, 0), 60);
    assert.equal(button.disabled, true);
    assert.equal(button.textContent, 'Gửi lại mã (60s)');
    assert.equal(updateOtpCooldown(button, 60000, 1000), 59);
    assert.equal(button.textContent, 'Gửi lại mã (59s)');
    assert.equal(updateOtpCooldown(button, 60000, 59999), 1);
    assert.equal(button.disabled, true);
    assert.equal(updateOtpCooldown(button, 60000, 60000), 0);
    assert.equal(button.disabled, false);
    assert.equal(button.textContent, 'Gửi lại mã OTP');
});

test('clock catches up after a hidden tab instead of counting interval callbacks', () => {
    const button = {};
    updateOtpCooldown(button, 60000, 5000);
    assert.equal(updateOtpCooldown(button, 60000, 75000), 0);
    assert.equal(button.disabled, false);
});

test('password visibility toggle switches input type, icon, and accessible label', () => {
    const input = { type: 'password' };
    const iconClasses = new Set(['bi', 'bi-eye']);
    const icon = {
        classList: {
            toggle(name, enabled) {
                if (enabled) {
                    iconClasses.add(name);
                } else {
                    iconClasses.delete(name);
                }
            },
        },
    };
    const attributes = {};
    const button = {
        querySelector: () => icon,
        setAttribute(name, value) {
            attributes[name] = value;
        },
    };

    togglePasswordVisibility(input, button);
    assert.equal(input.type, 'text');
    assert.equal(iconClasses.has('bi-eye-slash'), true);
    assert.equal(iconClasses.has('bi-eye'), false);
    assert.equal(attributes['aria-label'], 'Ẩn mật khẩu');
    assert.equal(attributes['aria-pressed'], 'true');

    togglePasswordVisibility(input, button);
    assert.equal(input.type, 'password');
    assert.equal(iconClasses.has('bi-eye'), true);
    assert.equal(iconClasses.has('bi-eye-slash'), false);
    assert.equal(attributes['aria-label'], 'Hiện mật khẩu');
    assert.equal(attributes['aria-pressed'], 'false');
});
