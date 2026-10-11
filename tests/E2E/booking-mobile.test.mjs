import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {execFileSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';
import {openBrowser} from './browser.mjs';

test('persisted mobile Booking → real-session preview → order and payment preference', {timeout:120000}, async () => {
    const {booking,storeBooking}=JSON.parse(readFileSync(process.env.WEB_E2E_RUNTIME+'/cases.json'));
    const state=(id=booking)=>JSON.parse(execFileSync(process.env.PHP_BIN||'php',[fileURLToPath(new URL('./state.php',import.meta.url)),'booking',String(id)]));
    const b=await openBrowser(process.env.WEB_E2E_RUNTIME,process.env.WEB_E2E_URL);
    try {
        await b.navigate('/login');
        await b.evaluate(`document.querySelector('[name="email"]').value='staff@example.test';document.querySelector('[name="password"]').value=${JSON.stringify(process.env.WEB_E2E_PASSWORD)};document.querySelector('form').requestSubmit()`);
        await b.waitFor(async()=>await b.evaluate('location.pathname')==='/orders','Login');
        await b.navigate(`/bookings/${booking}`);
        const text=await b.evaluate('document.body.innerText');
        for(const expected of ['Khách mobile','0900000001','mobile@example.test','Địa chỉ hồ sơ mobile','Địa chỉ giao mobile','11.111','22.222','1.234','5.678','Chuyển khoản','Ghi chú đồ từ mobile'])assert.ok(text.includes(expected),expected);
        const before=state();
        await b.navigate(`/bookings/${booking}/inspection`);
        assert.equal(await b.evaluate(`document.querySelector('[name="return_method"]').value`),'Tại nhà');
        assert.equal(await b.evaluate(`document.querySelector('[name="return_address"]').value`),'Địa chỉ giao mobile');
        const preview=await b.evaluate(`(async()=>{
            const csrf=document.querySelector('meta[name="csrf-token"]').content;
            const response=await fetch('/bookings/${booking}/estimate',{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify({items:[{DichVuID:1,LoaiDoGiatID:1,DonViTinhID:2,SoLuong:1}],use_points:false,method:'Tại cửa hàng',return_method:'Tại nhà',PickupDeliveryFee:999999,DeliveryFee:999999})});
            return {status:response.status,data:await response.json()};
        })()`);
        assert.equal(preview.status,200);
        assert.equal(preview.data.PhiGiaoHang,22222);
        assert.equal(preview.data.ThanhTien,32222);
        assert.deepEqual(state(),before,'GET and preview are read-only');
        await b.evaluate(`document.querySelector('[name$="[TinhTrangTruocKhiGiat]"]').value='Đồ đã kiểm kê';document.querySelector('#booking-use-points').checked=false;document.querySelector('#booking-edit-form').requestSubmit()`);
        await b.waitFor(async()=> (await b.evaluate('location.pathname')).startsWith('/orders/'),'Converted order');
        await b.waitFor(async()=>await b.evaluate('document.readyState')==='complete','Order rendered');
        const after=state();
        assert.equal(after.orders.length,1);
        assert.equal(Number(after.orders[0].PhiGiaoHang),33333);
        assert.equal(Number(after.orders[0].ThanhTien),43333);
        assert.deepEqual(after.estimate,before.estimate);
        assert.equal(after.legs.length,2);
        assert.equal(Number(after.legs.find(l=>l.LoaiGiaoNhan==='NHAN_DO').PhiGiaoNhan),11111);
        assert.equal(Number(after.legs.find(l=>l.LoaiGiaoNhan==='GIAO_DO').PhiGiaoNhan),22222);
        assert.equal(Number(after.booking[0].DeliveryFee),22222);
        assert.equal(after.payments.length,0,'Preference never marks order paid');
        assert.equal(Number(after.invoices[0].ThanhTien),43333);
        await b.navigate(`/payments/create?order_id=${after.orders[0].DonHangID}`);
        assert.equal(await b.evaluate(`document.querySelector('[name="method"]').value`),'bank_transfer');
        assert.equal(state().payments.length,0);
        // Nonzero mobile pickup fee must remain stored but be excluded after store inspection.
        await b.navigate(`/bookings/${storeBooking}/inspection`);
        await b.evaluate(`const method=document.querySelector('[name="method"]');method.value='Tại cửa hàng';method.dispatchEvent(new Event('change',{bubbles:true}));document.querySelector('[name$="[TinhTrangTruocKhiGiat]"]').value='Kiểm kê tại cửa hàng';document.querySelector('#booking-use-points').checked=false;document.querySelector('#booking-edit-form').requestSubmit()`);
        await b.waitFor(async()=> (await b.evaluate('location.pathname')).startsWith('/orders/'),'Store conversion');
        const store=state(storeBooking);
        assert.equal(Number(store.booking[0].PickupDeliveryFee),11111,'Accepted mobile snapshot stays unchanged');
        assert.equal(store.booking[0].HinhThucNhanDo,'Tại cửa hàng');
        assert.equal(Number(store.orders[0].PhiGiaoHang),22222,'DB trigger excludes the store pickup leg');
        assert.equal(Number(store.orders[0].ThanhTien),32222);
        assert.equal(Number(store.invoices[0].ThanhTien),32222,'Invoice agrees with effective fee');
        assert.equal(store.legs.length,1);
        assert.equal(store.legs[0].LoaiGiaoNhan,'GIAO_DO');
        assert.equal(Number(store.legs[0].PhiGiaoNhan),22222);
        assert.equal(b.errors.length,0,JSON.stringify(b.errors));
    } finally {await b.close();}
});
