import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync, mkdirSync, copyFileSync} from 'node:fs';
import {execFileSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';
import {setTimeout as delay} from 'node:timers/promises';
import {openBrowser} from './browser.mjs';

test('open conversation receives mobile PostgreSQL messages through real Laravel session', {timeout: 90000}, async () => {
    const cases=JSON.parse(readFileSync(process.env.WEB_E2E_RUNTIME+'/cases.json'));
    const runtime=process.env.WEB_E2E_RUNTIME+'/messages';mkdirSync(runtime,{mode:0o700});copyFileSync(process.env.WEB_E2E_RUNTIME+'/swal.js',runtime+'/swal.js');
    const b=await openBrowser(runtime,process.env.WEB_E2E_URL);
    try {
        await b.navigate('/login');
        await b.evaluate(`document.querySelector('[name=email]').value='staff@example.test';document.querySelector('[name=password]').value=${JSON.stringify(process.env.WEB_E2E_PASSWORD)};document.querySelector('form').requestSubmit()`);
        await b.waitFor(async()=>await b.evaluate('location.pathname')==='/orders','staff session');
        await b.navigate('/admin/messages?order_id='+cases.paid);
        await b.waitFor(()=>b.evaluate(`document.querySelector('[data-message-status]')?.textContent==='Tự động cập nhật tin nhắn'`),'initial sync');
        await b.evaluate(`window.messagePageMarker=true;document.querySelector('[data-message-form] textarea').value='Unsaved draft'`);
        const documents=b.responses.filter(r=>r.type==='Document').length;
        execFileSync(process.env.PHP_BIN||'php',[fileURLToPath(new URL('./messages-state.php',import.meta.url)),'insert'],{timeout:30000});
        // Wait for the normal 15-second polling cycle, without synthetic events or reload.
        await delay(16000);
        await b.waitFor(()=>b.evaluate(`document.querySelector('[data-message-updates]').textContent.includes('Mobile message')`),'new mobile message');
        assert.deepEqual(await b.evaluate(`({marker:window.messagePageMarker,draft:document.querySelector('[data-message-form] textarea').value,rows:document.querySelectorAll('[data-message-id]').length,foreign:document.querySelector('[data-message-updates]').textContent.includes('Foreign conversation'),xss:Boolean(window.messageXss),images:document.querySelector('[data-message-updates]').querySelectorAll('img').length})`),{marker:true,draft:'Unsaved draft',rows:1,foreign:false,xss:false,images:0});
        assert.equal(b.responses.filter(r=>r.type==='Document').length,documents);
        await b.evaluate(`window.dispatchEvent(new Event('online'))`);
        await b.waitFor(()=>b.responses.filter(r=>r.url.includes('/updates')).length>=3,'resync');
        assert.equal(await b.evaluate(`document.querySelectorAll('[data-message-id]').length`),1);
        assert.deepEqual(b.errors,[]);
    } finally {await b.close();}
});
