import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawn } from 'node:child_process';
import { setTimeout as delay } from 'node:timers/promises';

// Run the production controls in a real browser DOM, without an npm dependency.
test('unit selection preserves tuples and updates price, quantity, weight and totals', async () => {
    const dir = mkdtempSync(join(tmpdir(), 'order-pricing-'));
    const script = readFileSync(new URL('../../public/assets/js/order-item-pricing.js', import.meta.url), 'utf8');
    const html = `<!doctype html><html><body><table><tbody><tr id="row">
        <td><select class="item-service"><option value="1" selected>Giặt</option><option value="2">Khác</option></select></td>
        <td><select class="item-garment"><option value="1" selected>Áo</option></select></td>
        <td><select class="item-unit-value"><option value="">Chọn ĐVT</option></select></td>
        <td><input class="item-quantity" type="number" value="2"></td>
        <td><input class="item-weight" type="number" value="0"></td>
        <td><input class="item-price" value="5000"></td><td><input class="item-subtotal"></td>
    </tr></tbody></table><output id="result"></output><script>${script}</script><script>
    try {
        const row = document.getElementById('row');
        const unit = row.querySelector('.item-unit-value');
        const price = row.querySelector('.item-price');
        const quantity = row.querySelector('.item-quantity');
        const weight = row.querySelector('.item-weight');
        const controls = OrderItemPricing({minimumWeight: 3, garments: {1: 'Áo'}, prices: [
            {service_id: 1, garment_id: 1, unit_id: 1, unit: 'Cái', price: 15000},
            {service_id: 1, garment_id: 1, unit_id: 2, unit: 'KG', price: 40000},
            {service_id: 2, garment_id: 1, unit_id: 3, unit: 'Đôi', price: 9000}
        ]});
        function check(condition, message) { if (!condition) throw Error(message); }
        controls.updateRow(row);
        check(unit.options.length === 3 && unit.value === '', 'both units must remain selectable, without ambiguous default');
        check(!unit.checkValidity(), 'missing unit must prevent submit');
        unit.value = '1';
        check(controls.updateRow(row) === 30000, 'Cái uses selected tuple');
        check(price.value === '15000' && price.readOnly, 'server price is readonly');
        price.value = '1';
        check(controls.updateRow(row) === 30000, 'tampered price cannot alter preview');
        unit.value = '2'; weight.value = '1.5';
        check(controls.updateRow(row) === 120000, 'KG minimum is charged');
        check(unit.value === '2' && quantity.disabled && !weight.disabled, 'unit survives repeated updates and changes enabled fields');
        weight.value = '4';
        check(controls.updateRow(row) === 160000, 'KG above minimum is charged');
        unit.value = '1';
        controls.updateRow(row); quantity.value = '3';
        check(controls.updateRow(row) === 45000 && weight.disabled && weight.value === '0', 'switch back to quantity');
        row.querySelector('.item-service').value = '2';
        controls.syncGarmentOptions(row); controls.updateRow(row);
        check(unit.value === '3' && price.value === '9000', 'single eligible unit is auto-selected');
        row.querySelector('.item-garment').value = '';
        check(controls.updateRow(row) === 0 && unit.value === '' && price.value === '0', 'missing tuple clears stale price');
        document.getElementById('result').textContent = 'PASS: multi-unit browser regression';
    } catch (error) { document.getElementById('result').textContent = 'FAIL: ' + error.message; }
    </script></body></html>`;
    let browser;
    let socket;
    try {
        browser = spawn(process.env.CHROMIUM_BIN || 'chromium', ['--headless', '--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu', '--disable-background-networking', '--no-first-run', '--remote-debugging-port=0', `--user-data-dir=${join(dir, 'profile')}`, 'about:blank'], { stdio: 'ignore', detached: true });
        let port;
        for (let attempt = 0; attempt < 100; attempt++) {
            try { port = readFileSync(join(dir, 'profile', 'DevToolsActivePort'), 'utf8').split('\n')[0]; break; }
            catch { await delay(100); }
        }
        assert.ok(port, 'Chromium debugging endpoint did not start');
        const targets = await (await fetch(`http://127.0.0.1:${port}/json/list`, { signal: AbortSignal.timeout(5000) })).json();
        socket = new WebSocket(targets.find(target => target.type === 'page').webSocketDebuggerUrl);
        await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject; });
        let id = 0;
        const pending = new Map();
        socket.onmessage = event => {
            const message = JSON.parse(event.data);
            if (message.id) { pending.get(message.id)?.(message); pending.delete(message.id); }
        };
        async function command(method, params = {}) {
            const messageId = ++id;
            const response = new Promise(resolve => pending.set(messageId, resolve));
            socket.send(JSON.stringify({ id: messageId, method, params }));
            return Promise.race([response, delay(5000).then(() => { throw Error(`CDP timeout: ${method}`); })]);
        }
        const frame = await command('Page.getFrameTree');
        await command('Page.setDocumentContent', { frameId: frame.result.frameTree.frame.id, html });
        let result = '';
        for (let attempt = 0; attempt < 50; attempt++) {
            const response = await command('Runtime.evaluate', { expression: 'document.getElementById("result")?.textContent', returnByValue: true });
            result = response.result?.result?.value || '';
            if (result) break;
            await delay(100);
        }
        if (!result) { const page = await command('Runtime.evaluate', { expression: 'document.documentElement.outerHTML', returnByValue: true }); assert.fail(JSON.stringify(page).slice(0, 1800)); }
        assert.equal(result, 'PASS: multi-unit browser regression');
    } finally {
        socket?.close();
        if (browser?.pid) { try { process.kill(-browser.pid, 'SIGKILL'); } catch {} }
        await delay(100);
        rmSync(dir, { recursive: true, force: true });
    }
});
