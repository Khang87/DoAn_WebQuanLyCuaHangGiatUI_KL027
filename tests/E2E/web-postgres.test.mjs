import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {execFileSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';
import {openBrowser} from './browser.mjs';

test('browser → HTTP → PostgreSQL business flows with real session, CSRF and permissions', {timeout: 240000}, async t => {
    const cases = JSON.parse(readFileSync(process.env.WEB_E2E_RUNTIME+'/cases.json'));
    const state = (kind, id) => JSON.parse(execFileSync(process.env.PHP_BIN || 'php', [fileURLToPath(new URL('./state.php', import.meta.url)), kind, String(id)], {timeout: 30000, maxBuffer: 1048576}));
    const b = await openBrowser(process.env.WEB_E2E_RUNTIME, process.env.WEB_E2E_URL);
    try {
        assert.equal(await b.navigate('/orders'), '/login', 'Anonymous request redirects to login');
        const missing = await fetch(process.env.WEB_E2E_URL+'/login', {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: 'email=staff%40example.test&password=irrelevant'});
        assert.equal(missing.status, 419, 'Missing CSRF rejected by actual HTTP middleware');
        await b.evaluate(`document.querySelector('[name="email"]').value='staff@example.test';document.querySelector('[name="password"]').value=${JSON.stringify(process.env.WEB_E2E_PASSWORD)};document.querySelector('form').requestSubmit()`);
        await b.waitFor(async () => await b.evaluate('location.pathname') === '/orders', 'Authenticated login redirects to orders');
        await b.waitFor(async () => await b.evaluate('document.readyState') === 'complete', 'Orders loaded');
        assert.equal(await b.navigate('/orders/create'), '/orders/create', 'Session persists after navigation');
        assert.equal(await b.navigate('/reports'), '/reports');
        assert.equal(b.responses.filter(r => r.type === 'Document').at(-1).status, 403, 'Seeded staff lacks reporting permission');
        await t.test('E2E-01: store inspection creates one actual Order and preserves estimates', async () => {
            const before = state('booking', cases.store);
            assert.equal(await b.navigate(`/bookings/${cases.store}/inspection`), `/bookings/${cases.store}/inspection`);
            assert.deepEqual(state('booking', cases.store), before, 'GET inspection is read-only');
            assert.equal(await b.evaluate(`Boolean(document.querySelector('input[name$="[DonGia]"]:not([readonly])'))`), false, 'No editable inspection price');
            await b.evaluate(`document.querySelector('[name="staff_id"]').value='1';document.querySelector('[name$="[TinhTrangTruocKhiGiat]"]').value='E2E inspected condition';document.querySelector('#booking-edit-form').requestSubmit()`);
            await b.waitFor(async () => /^\/orders\/\d+$/.test(await b.evaluate('location.pathname')), 'Inspection creates Order');
            await b.waitFor(async () => await b.evaluate('document.readyState') === 'complete', 'Order detail loaded');
            const saved = state('booking', cases.store);
            assert.equal(saved.orders.length, 1);
            assert.equal(saved.orders[0].TrangThai, 'Đã tiếp nhận');
            assert.equal(Number(saved.orders[0].TongTien), 10000);
            assert.equal(saved.details[0].TinhTrangTruocKhiGiat, 'E2E inspected condition');
            assert.equal(Number(saved.details[0].DonGia), 10000);
            assert.deepEqual(saved.estimate, before.estimate);
            assert.deepEqual(saved.legs, []);
        });
        assert.deepEqual(b.errors, []);
    } finally {await b.close();}
});
