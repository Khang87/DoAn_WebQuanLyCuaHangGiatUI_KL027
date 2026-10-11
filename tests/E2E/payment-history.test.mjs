import {test} from 'node:test';
import assert from 'node:assert/strict';
import {mkdirSync,copyFileSync,writeFileSync} from 'node:fs';
import {openBrowser} from './browser.mjs';

test('staff session reads separate payment history with filters, pagination and readonly details', {timeout:60000}, async()=>{
    const base=process.env.WEB_E2E_RUNTIME;
    const runtime=base+'/payment-history';mkdirSync(runtime);copyFileSync(base+'/swal.js',runtime+'/swal.js');
    const b=await openBrowser(runtime,process.env.WEB_E2E_URL);
    try {
        await b.navigate('/login');
        await b.evaluate(`document.querySelector('[name=email]').value='staff@example.test';document.querySelector('[name=password]').value=${JSON.stringify(process.env.WEB_E2E_PASSWORD)};document.querySelector('form').requestSubmit()`);
        await b.waitFor(()=>b.evaluate(`location.pathname==='/orders'`),'staff login');
        await b.navigate('/payment-history');
        assert.equal(await b.evaluate(`document.querySelector('a.sidebar-menu-link.active')?.textContent.trim()`),'Lịch sử thanh toán');
        assert.equal(await b.evaluate(`document.querySelectorAll('[data-history-payment]').length`),10);
        assert.deepEqual(await b.evaluate(`[...document.querySelectorAll('[data-history-payment]')].map(row=>row.dataset.historyPayment)`),['12','11','10','9','8','7','6','5','4','3']);
        for(const width of [320,768,1024,1440]) {
            await b.command('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
            assert.ok(await b.evaluate(`(()=>{const form=document.querySelector('form[action$="/payment-history"]');return form.scrollWidth<=form.clientWidth+1})()`),`filter fits at ${width}`);
        }
        await b.navigate('/payment-history');
        await b.evaluate('window.scrollTo(0,0)');
        if(process.env.PAYMENT_HISTORY_SCREENSHOT){const shot=await b.command('Page.captureScreenshot',{format:'png'});writeFileSync(process.env.PAYMENT_HISTORY_SCREENSHOT,Buffer.from(shot.data,'base64'));}
        await b.evaluate(`document.querySelector('[name=date_from]').value='2026-10-11';document.querySelector('[name=date_to]').value='2026-10-10';document.querySelector('form[action$="/payment-history"]').requestSubmit()`);
        await b.waitFor(()=>b.evaluate(`Boolean(document.querySelector('[role=alert]'))`),'date validation alert');
        assert.equal(await b.evaluate(`document.querySelector('[role=alert]').textContent.includes('Đến ngày phải bằng hoặc sau Từ ngày.')`),true);
        await b.navigate('/payment-history?page=2');
        assert.deepEqual(await b.evaluate(`[...document.querySelectorAll('[data-history-payment] td:first-child')].map(cell=>cell.textContent.trim())`),['11','12']);
        await b.navigate('/payment-history?date_to=2026-10-10&status='+encodeURIComponent('Đã hoàn tiền')+'&method=bank_transfer&search=HISTORY-ORDER');
        assert.equal(await b.evaluate(`document.querySelectorAll('[data-history-payment]').length`),3);
        await b.navigate('/payment-history?search=CK_DH0001_20261011123000');
        assert.equal(await b.evaluate(`document.querySelectorAll('[data-history-payment]').length`),1);
        await b.navigate('/payment-history?search=999999999999999999999');
        assert.equal(await b.evaluate(`document.querySelectorAll('[data-history-payment]').length`),0);
        await b.navigate('/payment-history/12');
        assert.equal(await b.evaluate(`document.querySelector('.detail-header').textContent.includes('TT12')`),true);
        assert.equal(await b.evaluate(`document.body.textContent.includes('<img src=x')`),true);
        assert.equal(await b.evaluate(`Boolean(window.historyXss)`),false);
        assert.equal(await b.evaluate(`document.querySelector('a[href$="/payments/12/edit"],form[action$="/payments/12"]')===null`),true);
        await b.navigate('/payment-history?search=NO-MATCH');
        assert.equal(await b.evaluate(`document.querySelector('table').textContent.includes('Không có giao dịch phù hợp.')`),true);
        await b.navigate('/payments');
        assert.equal(await b.evaluate(`document.querySelectorAll('[data-collection-order]').length`),1);
        const orderId=await b.evaluate(`document.querySelector('[data-collection-order]').dataset.collectionOrder`);
        assert.equal(await b.evaluate(`document.querySelector('[data-collection-order]').textContent.includes('COLLECTION-ORDER')`),true);
        await b.evaluate(`document.querySelector('[data-collection-order] a').click()`);
        await b.waitFor(()=>b.evaluate(`location.pathname==='/payments/create'`),'collection form');
        assert.equal(await b.evaluate(`document.querySelector('[name=amount]').disabled`),false);
        await b.evaluate(`document.querySelector('[name=method]').value='bank_transfer';document.querySelector('[name=method]').dispatchEvent(new Event('change'));document.querySelector('[name=amount]').value='100000'`);
        await b.evaluate(`document.querySelector('[name=amount]').value='50000';document.querySelector('#paymentForm').requestSubmit()`);
        await b.waitFor(()=>b.evaluate(`Boolean(document.querySelector('[role=alert] ul'))`),'partial amount rejection');
        assert.equal(await b.evaluate(`document.querySelector('[role=alert]').textContent.includes('Số tiền thanh toán phải bằng toàn bộ')`),true);
        assert.equal(await b.evaluate(`document.querySelector('[name=amount]').value`),'50000');
        await b.evaluate(`document.querySelector('[name=amount]').value='100000'`);
        const code=await b.evaluate(`document.querySelector('[name=transaction_code]').value`);
        await b.evaluate(`document.querySelector('#paymentForm').requestSubmit()`);
        await b.waitFor(()=>b.evaluate(`location.pathname.split('/')[1]==='payments' && /^[0-9]+$/.test(location.pathname.split('/')[2])`),'saved collection');
        await b.navigate('/orders/'+orderId);
        assert.equal(await b.evaluate(`document.querySelector('.detail-header').textContent.includes('Đã tiếp nhận')`),true);
        await b.navigate('/payment-history?collector_id=2&search='+encodeURIComponent(code));
        assert.equal(await b.evaluate(`document.querySelectorAll('[data-history-payment]').length`),1);
        assert.equal(await b.evaluate(`document.querySelector('[data-history-payment]').textContent.includes('100,000')`),true);
        assert.equal(await b.evaluate(`document.querySelector('option[value="2"]')!==null`),true);
        await b.navigate('/payments');
        assert.equal(await b.evaluate(`document.querySelectorAll('[data-collection-order]').length`),0);
        assert.equal(b.errors.length,0);
    } finally {await b.close();}
});
