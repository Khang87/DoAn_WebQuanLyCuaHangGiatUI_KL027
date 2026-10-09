import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync, mkdirSync, copyFileSync} from 'node:fs';
import {execFileSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';
import {openBrowser} from './browser.mjs';

test('remaining testcase UI variants with HTTP sessions and PostgreSQL', {timeout: 600000}, async t => {
    const php=process.env.PHP_BIN || 'php';
    const fixture=(action)=>JSON.parse(execFileSync(php,[fileURLToPath(new URL('./remaining-state.php',import.meta.url)),action],{timeout:30000}));
    const state=(kind,id)=>JSON.parse(execFileSync(php,[fileURLToPath(new URL('./state.php',import.meta.url)),kind,String(id)],{timeout:30000}));
    const cases=fixture('prepare');
    const original=JSON.parse(readFileSync(process.env.WEB_E2E_RUNTIME+'/cases.json'));
    const runtime=process.env.WEB_E2E_RUNTIME+'/remaining';mkdirSync(runtime,{mode:0o700});copyFileSync(process.env.WEB_E2E_RUNTIME+'/swal.js',runtime+'/swal.js');
    const b=await openBrowser(runtime,process.env.WEB_E2E_URL);
    try {
        await b.navigate('/login');
        await b.evaluate(`document.querySelector('[name=email]').value='staff@example.test';document.querySelector('[name=password]').value=${JSON.stringify(process.env.WEB_E2E_PASSWORD)};document.querySelector('form').requestSubmit()`);
        await b.waitFor(async()=>await b.evaluate('location.pathname')==='/orders','local login');
        const change=async(selector,value,type='change')=>b.evaluate(`(()=>{const f=document.querySelector(${JSON.stringify(selector)});if(!f)throw Error('Missing field');f.value=${JSON.stringify(value)};f.dispatchEvent(new Event(${JSON.stringify(type)},{bubbles:true}))})()`);
        const setup=async(service='1')=>{
            await b.navigate('/orders/create');
            assert.equal(b.responses.filter(r=>r.type==='Document').at(-1).status,200);
            await change('.item-service-category','1');await change('.item-service',service);await change('.item-garment','1');
            await change('#customer_search','Local customer','input');await b.evaluate(`document.querySelector('#customer_suggestions button').click()`);await change('#employee_id','1');
            await change('[name$="[TinhTrangTruocKhiGiat]"]','Local inspected condition','input');
        };
        const submit=async()=>{
            await b.evaluate(`(()=>{const f=document.querySelector('#order-form')||document.querySelector('form[action$="/orders"]');if(!f.checkValidity())throw Error('Order form invalid');f.requestSubmit()})()`);
            await b.waitFor(async()=>/^\/orders\/\d+$/.test(await b.evaluate('location.pathname')),'saved order');
            await b.waitFor(async()=>await b.evaluate('document.readyState')==='complete','loaded order');
            return state('order',await b.evaluate('location.pathname.split("/").at(-1)'));
        };
        await t.test('13 readonly create edit and booking inspection',async()=>{
            for(const path of ['/orders/create',`/orders/${cases.legacy}/edit`,`/bookings/${cases.booking}/inspection`]) {
                await b.navigate(path);
                const priceFields=await b.evaluate(`(()=>{const p=[...document.querySelectorAll('input[name$="[DonGia]"]:not([type="hidden"])')];return {count:p.length,locked:p.every(f=>f.readOnly||f.disabled),inspection:Boolean(document.querySelector('.booking-item'))}})()`);
                assert.equal(priceFields.locked,true,path);
                assert.ok(priceFields.count>0||priceFields.inspection,'Inspection has no editable price field');
            }
        });
        await t.test('14 single Cái 15000 automatically selected',async()=>{
            await setup('2');
            assert.deepEqual(await b.evaluate(`[document.querySelector('.item-unit-value').value,Number(document.querySelector('.item-price').value)]`),['2',15000]);
        });
        await t.test('15 ambiguous units block submit then explicit selection saves',async()=>{
            await setup();
            assert.equal(await b.evaluate(`document.querySelector('.item-unit-value').checkValidity()`),false);
            const posts=b.requests.filter(r=>r.method==='POST'&&r.url.endsWith('/orders')).length;
            await b.evaluate(`document.querySelector('form[action$="/orders"]').requestSubmit()`);
            assert.equal(b.requests.filter(r=>r.method==='POST'&&r.url.endsWith('/orders')).length,posts);
            await change('.item-unit-value','2');const saved=await submit();assert.equal(Number(saved.details[0].DonViTinhID),2);
        });
        await t.test('16 retain valid unit when service changes',async()=>{
            await setup();await change('.item-unit-value','2');await change('.item-service','2');
            assert.deepEqual(await b.evaluate(`[document.querySelector('.item-unit-value').value,Number(document.querySelector('.item-price').value)]`),['2',15000]);
        });
        await t.test('17 clear stale unit and select only KG for new service',async()=>{
            await setup();await change('.item-unit-value','2');await change('.item-service-category','2');await change('.item-service','3');await change('.item-garment','1');
            assert.deepEqual(await b.evaluate(`[document.querySelector('.item-unit-value').value,Number(document.querySelector('.item-price').value),document.querySelector('.item-quantity').disabled,document.querySelector('.item-weight').disabled]`),['1',40000,true,false]);
        });
        await t.test('18 missing tuple clears stale price and blocks submission',async()=>{
            await setup();await change('.item-unit-value','2');await change('.item-service','4');
            assert.deepEqual(await b.evaluate(`[Number(document.querySelector('.item-price').value),document.querySelector('.item-unit-value').value,document.querySelector('.item-unit-value').checkValidity()]`),[0,'',false]);
            assert.equal(await b.evaluate(`Boolean(document.querySelector('.item-unit-value').validationMessage)`),true);
        });
        for(const [stt,unit,amount,total] of [[21,'2','2',30000],[22,'1','1.5',120000],[23,'1','4',160000]]) {
            await t.test(`${stt} exact preview and persisted quantity/weight`,async()=>{
                await setup();await change('.item-unit-value',unit);await change(unit==='1'?'.item-weight':'.item-quantity',amount,'input');
                assert.equal(await b.evaluate(`Number(document.querySelector('.item-subtotal').value)`),total);
                const saved=await submit();assert.equal(Number(saved.details[0].ThanhTien),total);
                assert.equal(Number(saved.details[0][unit==='1'?'KhoiLuong':'SoLuong']),Number(amount));
            });
        }
        await t.test('24 switch Cái KG Cái and persist only current measurement',async()=>{
            await setup();await change('.item-unit-value','2');await change('.item-quantity','2','input');await change('.item-unit-value','1');await change('.item-weight','1.5','input');await change('.item-unit-value','2');await change('.item-quantity','2','input');
            assert.equal(await b.evaluate(`document.querySelector('.item-weight').disabled`),true);
            const saved=await submit();assert.equal(Number(saved.details[0].SoLuong),2);assert.equal(Number(saved.details[0].KhoiLuong),0);assert.equal(Number(saved.details[0].ThanhTien),30000);
        });
        await t.test('25 remove middle of three rows add row and save every remaining row once',async()=>{
            await setup();await b.evaluate(`document.querySelector('#addItem').click();document.querySelector('#addItem').click();document.querySelectorAll('.remove-item')[1].click();document.querySelector('#addItem').click()`);
            await b.evaluate(`(()=>{for(const row of document.querySelectorAll('#itemsTable tbody tr')){const set=(s,v)=>{const f=row.querySelector(s);f.value=v;f.dispatchEvent(new Event('change',{bubbles:true}))};set('.item-service-category','1');set('.item-service','2');set('.item-garment','1');set('.item-quantity','2');set('[name$="[TinhTrangTruocKhiGiat]"]','Local condition')}const names=[...document.querySelectorAll('#itemsTable [name]')].map(f=>f.name);if(new Set(names).size!==names.length)throw Error('Duplicate names')})()`);
            const saved=await submit();assert.equal(saved.details.length,3);assert.equal(Number(saved.orders[0].TongTien),90000);
        });
        await t.test('92 employee dropdown includes held inactive and active excludes locked other',async()=>{
            for(const path of [`/orders/${cases.legacy}/edit`,`/deliveries/${cases.delivery}/edit`]){
                await b.navigate(path);
                assert.deepEqual(await b.evaluate(`[...document.querySelector('#employee_id').options].map(o=>o.value).filter(Boolean).sort()`),['1','2']);
            }
        });
        await t.test('93 employee deactivated after GET rejected by real session POST',async()=>{
            await setup('2');fixture('inactivate');
            const before=state('order',cases.legacy);const count=fixture('order-count');await b.evaluate(`document.querySelector('form[action$="/orders"]').requestSubmit()`);
            
            await b.waitFor(async()=>await b.evaluate(`Boolean(document.querySelector('#employee_id.is-invalid'))`),'staff validation');
            assert.deepEqual(state('order',cases.legacy),before);assert.deepEqual(fixture('order-count'),count);fixture('reactivate');
        });
        await t.test('210 Booking category A B empty C filters options and locks empty service',async()=>{
            await b.navigate(`/bookings/${cases.booking}/edit`);
            await change('.booking-service-category','1');await change('.booking-service','2');await change('.booking-garment','1');
            assert.equal(await b.evaluate(`document.querySelector('.booking-unit').value`),'2');
            await change('.booking-service-category','2');await change('.booking-service','3');await change('.booking-garment','1');
            assert.equal(await b.evaluate(`document.querySelector('.booking-unit').value`),'1');
            await change('.booking-service-category','3');
            assert.equal(await b.evaluate(`document.querySelector('.booking-service').disabled`),true);
            assert.equal(await b.evaluate(`document.querySelector('.booking-service-empty').classList.contains('d-none')`),false);
        });
        await t.test('211 linked Booking employee field disabled gray and raw change rejected',async()=>{
            await b.navigate(`/bookings/${original.store}/edit`);
            assert.equal(await b.evaluate(`document.querySelector('#booking-staff-id').disabled`),true);
            assert.equal(await b.evaluate(`getComputedStyle(document.querySelector('#booking-staff-id')).backgroundColor`),'rgb(233, 236, 239)');
            const before=state('booking',original.store);
            const result=await b.evaluate(`(async()=>{const f=document.querySelector('#booking-edit-form');const d=new URLSearchParams(new FormData(f));d.set('staff_id','3');const r=await fetch(f.action,{method:'POST',body:d});return r.status})()`);
            assert.equal(result,200);assert.deepEqual(state('booking',original.store),before);
        });
        await t.test('212 promotion fixed discount disables gray max then percentage enables',async()=>{
            await b.navigate('/promotions/create');assert.equal(b.responses.filter(r=>r.type==='Document').at(-1).status,200);
            await change('#LoaiKhuyenMai','Tiền mặt');
            assert.deepEqual(await b.evaluate(`[document.querySelector('#MucGiamToiDa').disabled,document.querySelector('#MucGiamToiDa').classList.contains('bg-light')]`),[true,true]);
            await change('#LoaiKhuyenMai','Phần trăm');assert.equal(await b.evaluate(`document.querySelector('#MucGiamToiDa').disabled`),false);
        });
        await t.test('82 pending return schedule optional execution requires date and time including server validation',async()=>{
            await b.navigate(`/deliveries/${cases.delivery}/edit`);
            assert.equal(await b.evaluate(`document.querySelector('#pickup_date').required||document.querySelector('#pickup_time').required`),false);
            await change('#status','delivering');
            assert.equal(await b.evaluate(`document.querySelector('#pickup_date').required&&document.querySelector('#pickup_time').required`),true);
            const result=await b.evaluate(`(async()=>{const f=document.querySelector('form[action$="/deliveries/${cases.delivery}"]');const r=await fetch(f.action,{method:'POST',body:new URLSearchParams(new FormData(f))});const doc=new DOMParser().parseFromString(await r.text(),'text/html');return {status:r.status,dateError:doc.querySelector('#pickup_date').nextElementSibling?.classList.contains('text-danger'),timeError:doc.querySelector('#pickup_time').nextElementSibling?.classList.contains('text-danger')}})()`);
            assert.deepEqual(result,{status:200,dateError:true,timeError:true});
        });
        await t.test('305 actor without complete receiving permission gets 403 unchanged',async()=>{
            fixture('deny-edit');await b.navigate(`/orders/${cases.legacy}`);const before=state('order',cases.legacy);
            const result=await b.evaluate(`(async()=>{const d=new URLSearchParams({_token:document.querySelector('meta[name="csrf-token"]').content,'items[0][DichVuID]':'1','items[0][LoaiDoGiatID]':'1','items[0][DonViTinhID]':'2','items[0][SoLuong]':'1','items[0][TinhTrangTruocKhiGiat]':'Valid condition'});return (await fetch('/orders/${cases.legacy}/complete-receiving',{method:'POST',body:d})).status})()`);
            assert.equal(result,403);assert.deepEqual(state('order',cases.legacy),before);
        });
        assert.deepEqual(b.errors,[]);
    } finally {fixture('reactivate');await b.close();}
});
