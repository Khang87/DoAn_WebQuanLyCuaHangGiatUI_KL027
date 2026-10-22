import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/payment-create.js', import.meta.url), 'utf8');

test('QR waits for bank confirmation while only cash can submit manually', async () => {
    const timers = new Map();
    const listeners = {};
    let timerId = 0;
    let statusChecks = 0;
    const element = (classes = []) => {
        const values = new Set(classes);
        return {
            dataset: {}, value: '', disabled: false, textContent: '', firstElementChild: null,
            classList: {
                add: (...items) => items.forEach(item => values.add(item)),
                remove: (...items) => items.forEach(item => values.delete(item)),
                toggle: (item, force) => force ? values.add(item) : values.delete(item),
                contains: item => values.has(item),
            },
            addEventListener(name, callback) { this[`on${name}`] = callback; },
        };
    };
    const method = element(); method.value = 'cash';
    const order = element(); order.value = '37'; order.selectedOptions = [{ value: '37', dataset: { orderCode: 'DH00000037', paymentReference: 'DH00000037', invoiceReady: '1', amount: '50000' } }];
    const invoice = element(); invoice.value = '19'; invoice.selectedOptions = [{ value: '19', dataset: { orderId: '37', orderCode: 'DH00000037', paymentReference: 'DH00000037', invoiceReady: '1', amount: '50000' } }];
    const code = element(), amount = element(), qr = element(['d-none']);
    const image = element(); image.dataset.quicklinkBase = 'https://img.vietqr.io/image/970422-123-compact2.png';
    const notice = element(['d-none']), message = element(), spinner = element();
    notice.firstElementChild = element(['alert-info']);
    notice.querySelector = selector => selector.includes('status-text') ? message : spinner;
    const submit = element(), complete = element(['d-none']);
    const cashFields = [element(), element(), element()];
    const form = {
        dataset: { bankStartUrl: '/payments/bank-transfer', bankStatusUrl: '/payments/bank-transfer/status' },
        querySelector(selector) {
            return ({ '[name="method"]': method, '[name="order_id"]:not([type="hidden"])': order,
                '[name="invoice_id"]': invoice, '#transaction_code': code, 'input[name="amount"]': amount })[selector];
        },
        querySelectorAll: () => cashFields,
        addEventListener(name, callback) { this[`on${name}`] = callback; },
    };
    const elements = {
        paymentForm: form, paymentVietQrPanel: qr, 'payment-vietqr-image': image,
        'payment-vietqr-amount': element(), 'payment-vietqr-memo': element(),
        paymentBankNotice: notice, paymentCashSubmit: submit, paymentBankComplete: complete,
    };
    const document = {
        hidden: false,
        getElementById: id => elements[id],
        querySelector: () => ({ content: 'csrf' }),
        addEventListener(name, callback) { listeners[name] = callback; },
    };
    vm.runInNewContext(source, {
        document, window: {}, navigator: { onLine: true }, location: { origin: 'https://test.local' }, URL,
        setTimeout(callback) { timers.set(++timerId, callback); return timerId; },
        clearTimeout(id) { timers.delete(id); },
        fetch: async (url, options) => {
            if (options?.method === 'POST') {
                assert.deepEqual(JSON.parse(options.body), { order_id: 37, invoice_id: 19 });
                return { ok: true, json: async () => ({ order_id: 37, amount: 50000, reference: 'DH00000037' }) };
            }
            assert.match(String(url), /order_id=37/);
            statusChecks++;
            return { ok: true, json: async () => statusChecks === 1
                ? { paid: false } : { paid: true, transaction_code: 'SEPAY-123' } };
        },
    });
    listeners.DOMContentLoaded();
    assert.equal(submit.disabled, false);

    method.value = 'bank_transfer'; method.onchange();
    assert.equal(submit.disabled, true);
    assert.equal(submit.classList.contains('d-none'), true);
    assert.equal(qr.classList.contains('d-none'), true);
    let prevented = false;
    form.onsubmit({ preventDefault() { prevented = true; } });
    assert.equal(prevented, true);

    const runNext = async () => {
        const [id, callback] = timers.entries().next().value;
        timers.delete(id);
        callback();
        for (let i = 0; i < 20; i++) await Promise.resolve();
    };
    await runNext();
    assert.equal(qr.classList.contains('d-none'), false);
    assert.match(message.textContent, /Đang chờ khách chuyển khoản/);
    await runNext();
    assert.equal(qr.classList.contains('d-none'), true);
    assert.equal(complete.classList.contains('d-none'), false);
    assert.match(message.textContent, /SEPAY-123/);

    method.value = 'cash'; method.onchange();
    assert.equal(submit.disabled, false);
    assert.equal(submit.classList.contains('d-none'), false);
    prevented = false; form.onsubmit({ preventDefault() { prevented = true; } });
    assert.equal(prevented, false);
});
