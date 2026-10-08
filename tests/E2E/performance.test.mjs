import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {execFileSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';
import {openBrowser} from './browser.mjs';

test('cold-browser page profiling with real HTTP and PostgreSQL', {timeout: 180000}, async () => {
    let alertGate = Promise.resolve(), releaseAlert = () => {};
    const b = await openBrowser(process.env.WEB_E2E_RUNTIME, process.env.WEB_E2E_URL, {assetDelayMs: 500, alertGate: () => alertGate});
    try {
        await b.command('Network.setCacheDisabled', {cacheDisabled: true});
        await b.navigate('/login');
        await b.evaluate(`document.querySelector('[name="email"]').value='staff@example.test';document.querySelector('[name="password"]').value=${JSON.stringify(process.env.WEB_E2E_PASSWORD)};document.querySelector('form').requestSubmit()`);
        await b.waitFor(async () => await b.evaluate('location.pathname') === '/staff/dashboard', 'Profile staff login');
        await b.waitFor(async () => await b.evaluate('document.readyState') === 'complete', 'Dashboard loaded');
        for (const path of ['/orders', '/orders/create', '/staff/dashboard']) {
            const samples = [];
            for (let i = 0; i < 5; i++) {
                await b.evaluate('window.__perfPreviousDocument=true');
                await b.command('Page.navigate', {url: process.env.WEB_E2E_URL+path});
                const sidebarReady = await b.waitFor(async () => {
                    try {
                        return await b.evaluate(`(() => {
                            if (window.__perfPreviousDocument) return false;
                            const button=document.querySelector('#sidebar-toggle'), sidebar=document.querySelector('#sidebar');
                            if (!button || !sidebar) return false;
                            button.click();
                            if (!sidebar.classList.contains('show')) return false;
                            sidebar.classList.remove('show');
                            return performance.now();
                        })()`);
                    } catch (error) {
                        if (/context|navigat/i.test(error.message)) return false;
                        throw error;
                    }
                }, 'Sidebar interactive');
                await b.waitFor(async () => await b.evaluate('document.readyState') === 'complete', 'Profile page loaded');
                const response = b.responses.filter(r => r.type === 'Document').at(-1);
                assert.equal(response.status, 200, path);
                const header = Object.entries(response.headers).find(([name]) => name.toLowerCase() === 'x-e2e-queries');
                assert.ok(header, 'Query instrumentation is active only on test HTTP bootstrap');
                const queries = Number(header[1]);
                const timing = await b.evaluate(`(() => {
                    const n=performance.getEntriesByType('navigation')[0];
                    const resources=performance.getEntriesByType('resource');
                    return {ttfb:n.responseStart,domContentLoaded:n.domContentLoadedEventEnd,load:n.loadEventEnd,
                        firstPaint:performance.getEntriesByName('first-paint')[0]?.startTime ?? null,
                        resources:resources.length,bytes:resources.reduce((sum,r)=>sum+r.encodedBodySize,0)};
                })()`);
                samples.push({queries,sidebarReady, ...timing});
            }
            console.log('PERFORMANCE '+JSON.stringify({path,queryDelayMs:Number(process.env.WEB_E2E_QUERY_DELAY_MS || 0),assetDelayMs:500,samples}));
            if (path === '/orders') {
                assert.ok(samples.every(s => s.queries <= 10), 'Fixture orders list budget: at most 10 queries per request');
            }
            if (path === '/staff/dashboard') {
                assert.ok(samples.every(s => s.queries <= 13), 'Fixture dashboard budget: at most 13 queries per request');
            }
        }
        assert.equal(await b.evaluate(`(() => {
            const button=document.querySelector('.notification-bell-toggle');button.click();
            const open=button.parentElement.querySelector('.dropdown-menu').classList.contains('show');
            button.click();return open;
        })()`), true, 'Actual Bootstrap dropdown works after deferred dependencies load');
        const cases = JSON.parse(readFileSync(process.env.WEB_E2E_RUNTIME+'/cases.json'));
        const state = () => execFileSync(process.env.PHP_BIN || 'php', [fileURLToPath(new URL('./state.php', import.meta.url)), 'booking', String(cases.store)], {timeout:30000}).toString();
        const before = state();
        await b.navigate(`/bookings/${cases.store}`);
        const posts = b.requests.filter(r => r.method === 'POST').length;
        await b.evaluate(`document.querySelector('[data-confirm-form]').requestSubmit()`);
        await b.waitFor(async () => await b.evaluate(`Boolean(document.querySelector('.swal2-cancel'))`), 'Actual confirmation dialog');
        await b.evaluate(`document.querySelector('.swal2-cancel').click()`);
        await b.waitFor(async () => await b.evaluate(`!document.querySelector('.swal2-container')`), 'Confirmation cancelled');
        assert.equal(b.requests.filter(r => r.method === 'POST').length, posts, 'Cancel sends no business mutation');
        assert.equal(state(), before, 'Cancel preserves PostgreSQL state');
        alertGate = new Promise(resolve => {releaseAlert=resolve;});
        await b.evaluate(`document.querySelector('form[action$="/logout"]').requestSubmit()`);
        await b.waitFor(async () => await b.evaluate(`(() => {
            const button=document.querySelector('#toggle-password');if (!button) return false;
            button.click();return document.querySelector('#password').type==='text';
        })()`), 'Login control responds while SweetAlert download is held');
        assert.equal(await b.evaluate('typeof Swal'), 'undefined', 'Early control does not depend on SweetAlert');
        releaseAlert();
        await b.waitFor(async () => await b.evaluate('document.readyState') === 'complete', 'Deferred login scripts ready');
        await b.evaluate(`document.querySelector('#toggle-password').click();document.querySelector('[name="email"]').value='staff@example.test';document.querySelector('[name="password"]').value='InvalidPassword123';document.querySelector('form').requestSubmit()`);
        await b.waitFor(async () => await b.evaluate(`document.querySelector('.swal2-title')?.textContent.includes('Đăng nhập thất bại')`), 'Actual failed-login flash popup');
        assert.deepEqual(b.errors, []);
    } finally {releaseAlert();await b.close();}
});
