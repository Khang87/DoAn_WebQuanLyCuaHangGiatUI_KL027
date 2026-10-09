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
        await t.test('E2E-06: actual pages preserve both units, minimum kg and readonly prices', async () => {
            await b.navigate('/orders/create');
            const preview = await b.evaluate(`(() => {
                const row=document.querySelector('#itemsTable tbody tr');
                const set=(selector,value,type='change')=>{const f=row.querySelector(selector);f.value=value;f.dispatchEvent(new Event(type,{bubbles:true}))};
                set('.item-service-category','1');set('.item-service','1');set('.item-garment','1');
                const units=[...row.querySelector('.item-unit-value').options].map(o=>o.value).filter(Boolean).sort();
                set('.item-unit-value','2');set('.item-quantity','2','input');
                const price=row.querySelector('.item-price');const piece=[price.readOnly,Number(price.value),Number(row.querySelector('.item-subtotal').value)];
                price.value='1';set('.item-quantity','2','input');const restored=Number(price.value);
                set('.item-unit-value','1');set('.item-weight','1.5','input');
                return {units,piece,restored,kg:[Number(price.value),Number(row.querySelector('.item-subtotal').value),row.querySelector('.item-quantity').disabled]};
            })()`);
            assert.deepEqual(preview, {units:['1','2'],piece:[true,10000,20000],restored:10000,kg:[2500,7500,true]});
            await b.navigate(`/orders/${cases.legacy}/edit`);
            assert.equal(b.responses.filter(r => r.type === 'Document').at(-1).status, 200, 'Legacy edit page really rendered');
            assert.equal(await b.evaluate(`(()=>{const p=[...document.querySelectorAll('.item-price')];return p.length>0&&p.every(f=>f.readOnly)})()`), true);
            const {saved} = await inspect('kg', {unit:'1', measurement:'1.5'});
            assert.equal(Number(saved.details[0].DonViTinhID), 1);
            assert.equal(Number(saved.details[0].DonGia), 2500);
            assert.equal(Number(saved.orders[0].TongTien), 7500);
        });
        await t.test('E2E-09: display row numbers remain continuous without rewriting submitted indexes', async () => {
            const before = state('order', cases.legacy);
            for (const [path, table, add, remove] of [
                ['/orders/create', '#itemsTable', '#addItem', '.remove-item'],
                [`/orders/${cases.legacy}/edit`, '#itemsTable', '#addItem', '.remove-item'],
                [`/orders/${cases.legacy}`, '#receivingItemsTable', '#addInspectionItem', '[data-remove-inspection-row]'],
            ]) {
                await b.navigate(path);
                const result = await b.evaluate(`(() => {
                    const table=document.querySelector(${JSON.stringify(table)});
                    const rows=()=>[...table.querySelectorAll('tbody tr')];
                    const snapshot=()=>rows().map(row=>({
                        number:Number(row.querySelector('[data-row-number]').textContent),
                        names:[...row.querySelectorAll('[name]')].map(input=>input.name)
                    }));
                    const original=snapshot();
                    const add=document.querySelector(${JSON.stringify(add)});
                    add.click();add.click();
                    const added=snapshot();
                    rows()[0].querySelector(${JSON.stringify(remove)}).click();
                    const afterFirst=snapshot();
                    rows().at(-1).querySelector(${JSON.stringify(remove)}).click();
                    return {original,added,afterFirst,afterLast:snapshot()};
                })()`);
                for (const rows of Object.values(result)) {
                    assert.deepEqual(rows.map(row=>row.number), rows.map((_,i)=>i+1), path+' has no gaps');
                }
                assert.deepEqual(result.afterFirst.map(row=>row.names), result.added.slice(1).map(row=>row.names), 'Removing first row preserves remaining input names');
                assert.deepEqual(result.afterLast.map(row=>row.names), result.afterFirst.slice(0,-1).map(row=>row.names), 'Removing last row preserves input names');
                const indexes=result.added.map(row=>row.names[0].match(/^items\[(\d+)\]/)[1]);
                for (const width of [320,768,1024,1440]) {
                    await b.command('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
                    assert.equal(await b.evaluate(`(() => {
                        const table=document.querySelector(${JSON.stringify(table)});
                        const wrapper=table.closest('.table-responsive');
                        return wrapper.clientWidth<=innerWidth && [...table.querySelectorAll('tbody tr')].every((row,i)=>Number(row.querySelector('[data-row-number]').textContent)===i+1);
                    })()`),true,path+' preserves table numbering at '+width+'px');
                }
                await b.command('Emulation.clearDeviceMetricsOverride');
                assert.equal(new Set(indexes).size,indexes.length,'New item input indexes remain unique');
            }
            assert.deepEqual(state('order',cases.legacy),before,'Display-only edits never write PostgreSQL');
        });
        await t.test('E2E-07: voucher then points leave both delivery fees payable', async () => {
            const {before, saved} = await inspect('voucher', {measurement:'10',points:true});
            const o=saved.orders[0];
            assert.deepEqual([o.TongTien,o.TienGiamKhuyenMai,o.DiemSuDung,o.TienGiamDoDiem,o.PhiGiaoHang,o.ThanhTien].map(Number), [100000,10000,90000,90000,30000,30000]);
            assert.equal(before.balance-saved.balance,90000);
            assert.equal(saved.legs.length,2);
        });
        await t.test('E2E-08: staff HTTP writes respect paid-order guards and owner-only deletion', async () => {
            await b.navigate(`/orders/${cases.paid}`);
            assert.equal(b.responses.filter(r => r.type === 'Document').at(-1).status,200,'Paid Order is readable by permitted staff');
            const before=state('order',cases.paid);
            assert.equal(before.orders[0].TrangThai,'Đã thanh toán');
            assert.equal(before.legs.length,1);
            async function post(path, method, values) {
                return b.evaluate(`(async()=>{const body=new URLSearchParams(${JSON.stringify(values)});body.set('_method',${JSON.stringify(method)});body.set('_token',document.querySelector('meta[name="csrf-token"]').content);const r=await fetch(${JSON.stringify(path)},{method:'POST',headers:{'Accept':'application/json','Content-Type':'application/x-www-form-urlencoded'},body});const text=await r.text();return {status:r.status,text}})()`);
            }
            const edit=await post(`/orders/${cases.paid}`,'PATCH',{KhachHangID:'1',NhanVienID:'1',TrangThai:'Đã thanh toán',GhiChu:'Attempted paid edit'});
            assert.equal(edit.status,403);assert.match(JSON.parse(edit.text).message,/Chi tiết đơn hàng đã khóa/);
            const remove=await post(`/orders/${cases.paid}`,'DELETE',{});
            assert.equal(remove.status,403);assert.match(JSON.parse(remove.text).message,/Bạn không có quyền thực hiện thao tác này/,'Deletion is owner-only, not financial-guard evidence');
            const values={order_id:String(cases.paid),method:'giao_do',fulfillment:'Tại nhà',address:'Attempted paid delivery',status:'pending'};
            for(const [path,method] of [['/deliveries','POST'],[`/deliveries/${before.legs[0].GiaoNhanID}`,'PATCH']]){
                const result=await post(path,method,values);
                assert.equal(result.status,200,'Service refusal redirects to readable Order page');
                const flash=result.text.match(/text:\s*("(?:[^"\\]|\\.)*")/);
                assert.ok(flash,'Actual redirected page contains flash message');
                assert.match(JSON.parse(flash[1]),/đã hoàn thành hoặc đã thanh toán nên chỉ có thể xem/,'Rejection comes from financial guard');
            }
            assert.deepEqual(state('order',cases.paid),before,'Order money/details/invoice/payments/points/legs/audit unchanged');
        });
        const unexpected=b.responses.filter(r=>r.status>=400 && !(r.status===403 && ((r.type==='Document' && new URL(r.url).pathname==='/reports') || (r.type==='Fetch' && new URL(r.url).pathname===`/orders/${cases.paid}`))));
        assert.deepEqual(unexpected.map(r=>({status:r.status,path:new URL(r.url).pathname})), [], 'No unexpected failed HTTP requests or core assets');
        assert.deepEqual(b.errors, []);
        console.log('Decorative external CSS excluded:', [...b.excluded].join(', '));
    } finally {await b.close();}
});
