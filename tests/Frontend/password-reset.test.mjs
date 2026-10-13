import test from 'node:test';
import assert from 'node:assert/strict';
import { updateOtpCooldown } from '../../resources/js/password-reset.js';

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
