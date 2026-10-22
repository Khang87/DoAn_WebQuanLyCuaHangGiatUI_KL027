import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/staff-dashboard.js', import.meta.url), 'utf8');

function harness() {
    const windowEvents = {};
    const selectEvents = {};
    const calls = [];
    const reloads = [];
    const select = {
        dataset: {orderId: '42'},
        value: 'Đang giặt',
        disabled: false,
        selectedIndex: 2,
        addEventListener: (name, callback) => { selectEvents[name] = callback; },
    };
    const table = {dataset: {staffOrderStatusUrl: '/staff/dashboard/orders/__ID__/status'}};
    let alert;

    const context = {
        document: {
            querySelector: selector => selector === '[data-staff-order-status-url]'
                ? table
                : selector === 'meta[name="csrf-token"]' ? {content: 'test-token'} : null,
            querySelectorAll: () => [select],
        },
        window: {
            location: {reload: () => reloads.push(true)},
            addEventListener: (name, callback) => { windowEvents[name] = callback; },
        },
        fetch: (url, options) => {
            calls.push({url, options});
            return Promise.resolve({
                ok: alert?.ok ?? true,
                json: async () => alert?.body ?? {success: true, status_label: 'Đang giặt'},
            });
        },
        Swal: {
            fire: async options => { alert = {options, ok: alert?.ok, body: alert?.body}; },
        },
    };

    vm.runInNewContext(source, context);

    return {
        calls,
        reloads,
        select,
        selectEvents,
        windowEvents,
        setResponse(ok, body) { alert = {ok, body}; },
    };
}

test('successful PATCH reloads dashboard to refresh the row and server-calculated KPIs', async () => {
    const page = harness();
    await page.selectEvents.change.call(page.select);

    assert.equal(page.calls.length, 1);
    assert.equal(page.calls[0].url, '/staff/dashboard/orders/42/status');
    assert.equal(page.calls[0].options.method, 'PATCH');
    assert.equal(JSON.parse(page.calls[0].options.body).status, 'Đang giặt');
    assert.deepEqual(page.reloads, [true]);
});

test('failed PATCH keeps the row and resets the selector without reloading', async () => {
    const page = harness();
    page.setResponse(false, {success: false, message: 'Trạng thái không hợp lệ.'});
    await page.selectEvents.change.call(page.select);

    assert.equal(page.reloads.length, 0);
    assert.equal(page.select.selectedIndex, 0);
    assert.equal(page.select.disabled, false);
});

test('restoring dashboard from back-forward cache reloads authoritative state without PATCHing', () => {
    const page = harness();
    page.windowEvents.pageshow({persisted: false});
    assert.equal(page.reloads.length, 0);
    assert.equal(page.calls.length, 0);

    page.windowEvents.pageshow({persisted: true});
    assert.deepEqual(page.reloads, [true]);
    assert.equal(page.calls.length, 0);
});
