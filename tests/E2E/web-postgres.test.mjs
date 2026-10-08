import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {execFileSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';
import {openBrowser} from './browser.mjs';

test('browser → HTTP → PostgreSQL business flows with real session, CSRF and permissions', {timeout: 600000}, async t => {
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
        async function inspect(name, {unit = '2', measurement = '1', points = false} = {}) {
            const before = state('booking', cases[name]);
            await b.navigate(`/bookings/${cases[name]}/inspection`);
            assert.equal(b.responses.filter(r => r.type === 'Document').at(-1).status, 200);
            await b.evaluate(`(() => {
                const form = document.querySelector('#booking-edit-form');
                const set = (selector, value) => { const field=form.querySelector(selector);field.value=value;field.dispatchEvent(new Event('change',{bubbles:true})); };
                set('[name="staff_id"]','1');
                set('.booking-unit',${JSON.stringify(unit)});
                set(${JSON.stringify(unit === '1' ? '.booking-weight' : '.booking-quantity')},${JSON.stringify(measurement)});
                set('[name$="[TinhTrangTruocKhiGiat]"]','E2E inspected condition');
                form.querySelector('#booking-use-points').checked=${JSON.stringify(points)};
                if (!form.checkValidity()) throw Error('Inspection form invalid');
                form.requestSubmit();
            })()`);
            await b.waitFor(async () => /^\/orders\/\d+$/.test(await b.evaluate('location.pathname')), `Inspection ${name}`);
            await b.waitFor(async () => await b.evaluate('document.readyState') === 'complete', 'Order loaded');
            assert.equal(b.responses.filter(r => r.type === 'Document').at(-1).status, 200, 'Order page must render');
            const saved = state('booking', cases[name]);
            assert.equal(saved.orders.length, 1);
            assert.equal(saved.orders[0].TrangThai, 'Đã tiếp nhận');
            assert.deepEqual(saved.estimate, before.estimate);
            assert.equal(saved.details[0].TinhTrangTruocKhiGiat, 'E2E inspected condition');
            assert.equal(saved.audit.filter(a => a.HanhDong === 'Xác nhận Booking').length, 1);
            return {before, saved};
        }
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
        for (const [id, name, expected] of [['02','pickup',['NHAN_DO']], ['03','return',['GIAO_DO']], ['04','both',['GIAO_DO','NHAN_DO']]]) {
            await t.test(`E2E-${id}: ${name} creates only matching home legs`, async () => {
                const {saved} = await inspect(name);
                assert.deepEqual(saved.legs.map(l => l.LoaiGiaoNhan), expected);
                for (const leg of saved.legs) {
                    assert.equal(leg.DiaChi, leg.LoaiGiaoNhan === 'NHAN_DO' ? 'Local pickup' : 'Local return');
                    assert.equal(leg.ThoiGianDuKien === null, leg.LoaiGiaoNhan === 'GIAO_DO');
                }
            });
        }
        await t.test('E2E-05: authenticated HTTP replay preserves one Order, redemption and audit', async () => {
            const {before, saved} = await inspect('replay', {points: true});
            assert.equal(Number(saved.orders[0].DiemSuDung), 10000);
            assert.equal(before.balance - saved.balance, 10000);
            const request = b.requests.findLast(r => r.url.endsWith(`/bookings/${cases.replay}/confirm`) && r.method === 'POST');
            assert.ok(request?.postData, 'Real browser POST captured for replay');
            const result = await b.evaluate(`(async () => {const r=await fetch(${JSON.stringify(request.url)}, {method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:${JSON.stringify(request.postData)}});return {status:r.status,path:new URL(r.url).pathname}})()`);
            assert.deepEqual(result, {status:200, path:`/orders/${saved.orders[0].DonHangID}`});
            assert.deepEqual(state('booking', cases.replay), saved, 'No duplicate money, detail, legs or audit');
        });
        assert.deepEqual(b.responses.filter(r => r.status >= 500).map(r => r.status), [], 'No HTTP server errors');
        assert.deepEqual(b.errors, []);
        console.log('Decorative external CSS excluded:', [...b.excluded].join(', '));
    } finally {await b.close();}
});
