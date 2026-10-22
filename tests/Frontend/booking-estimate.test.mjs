import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/booking-estimate.js', import.meta.url), 'utf8')
    .replace(/^import .*;\r?\n/, '');

test('booking prices are requested only after inspection fields are initialized', () => {
    const listeners = {};
    let requests = 0;
    const element = () => ({ textContent: '', dataset: {} });
    const fields = {
        '[name="method"]': { value: 'Tại nhà' },
        '[name="return_method"]': { value: 'Tại cửa hàng' },
    };
    const itemsContainer = {
        addEventListener() {},
        querySelectorAll: () => [],
    };
    const form = {
        querySelector: selector => selector === '#booking-items' ? itemsContainer : fields[selector],
        addEventListener() {},
    };
    const panel = {
        dataset: {
            bookingEstimate: '/bookings/1/estimate',
            pricingOptions: '[]',
            serviceOptions: '[]',
            garmentOptions: '[]',
            minimumWeight: '3',
        },
        querySelector: element,
    };
    const document = {
        readyState: 'loading',
        querySelector: selector => ({
            '[data-booking-estimate]': panel,
            '#booking-edit-form': form,
            'meta[name="csrf-token"]': { content: 'csrf-token' },
        })[selector],
        addEventListener: (name, callback) => { listeners[name] = callback; },
    };

    vm.runInNewContext(source, {
        document,
        window: {
            OrderItemPricing: () => ({ calculateAmount: () => 0 }),
            addEventListener() {},
        },
        MutationObserver: class {
            observe() {}

            disconnect() {}
        },
        fetch: () => {
            requests++;
            return new Promise(() => {});
        },
    });

    assert.equal(requests, 0);
    assert.equal(typeof listeners.DOMContentLoaded, 'function');

    listeners.DOMContentLoaded();

    assert.equal(requests, 1);
});
