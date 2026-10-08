import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { setTimeout as delay } from 'node:timers/promises';

export async function openBrowser(runtime, origin) {
    const child = spawn(process.env.CHROMIUM_BIN || 'chromium', ['--headless', '--no-sandbox', '--disable-dev-shm-usage', '--disable-background-networking', '--no-first-run', '--remote-debugging-port=0', `--user-data-dir=${runtime}/browser`, 'about:blank'], {stdio: 'ignore'});
    const errors = [], requests = [], responses = [], excluded = new Set(), pending = new Map();
    let socket, id = 0;
    async function waitFor(check, label) {
        for (let i = 0; i < 150; i++) { const value = await check(); if (value) return value; await delay(100); }
        throw Error(`Browser deadline: ${label}`);
    }
    async function command(method, params = {}) {
        const messageId = ++id;
        return new Promise((resolve, reject) => {
            const timer = setTimeout(() => { pending.delete(messageId); reject(Error(`CDP deadline: ${method}`)); }, 10000);
            pending.set(messageId, message => { clearTimeout(timer); message.error ? reject(Error(message.error.message)) : resolve(message.result); });
            socket.send(JSON.stringify({id: messageId, method, params}));
        });
    }
    async function evaluate(expression) {
        const result = await command('Runtime.evaluate', {expression, returnByValue: true, awaitPromise: true});
        assert.equal(result.exceptionDetails, undefined, 'Page JavaScript must complete');
        return result.result.value;
    }
    async function navigate(path) {
        const start = responses.length;
        await command('Page.navigate', {url: origin + path});
        await waitFor(async () => responses.slice(start).some(r => r.type === 'Document') && await evaluate('document.readyState === "complete"'), path);
        return evaluate('location.pathname');
    }
    async function close() {
        socket?.close();
        child.kill('SIGTERM');
        await Promise.race([new Promise(resolve => child.once('exit', resolve)), delay(3000).then(() => child.kill('SIGKILL'))]);
    }
    try {
        const port = await waitFor(() => { try { return readFileSync(`${runtime}/browser/DevToolsActivePort`, 'utf8').split('\n')[0]; } catch { return null; } }, 'Chrome startup');
        const targets = await (await fetch(`http://127.0.0.1:${port}/json/list`, {signal: AbortSignal.timeout(5000)})).json();
        socket = new WebSocket(targets.find(t => t.type === 'page').webSocketDebuggerUrl);
        await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject; });
        socket.onmessage = event => {
            const m = JSON.parse(event.data);
            if (m.id) { pending.get(m.id)?.(m); pending.delete(m.id); return; }
            if (m.method === 'Runtime.exceptionThrown') errors.push('Page exception: ' + m.params.exceptionDetails.text);
            if (m.method === 'Network.requestWillBeSent') requests.push(m.params.request);
            if (m.method === 'Network.responseReceived') responses.push({...m.params.response, type: m.params.type});
            if (m.method === 'Network.loadingFailed' && !m.params.canceled) errors.push('Request failed: ' + m.params.errorText);
            if (m.method === 'Fetch.requestPaused') {
                const {requestId, request} = m.params;
                (async () => {
                    if (request.url === 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js') {
                        await command('Fetch.fulfillRequest', {requestId, responseCode: 200, responseHeaders: [{name: 'Content-Type', value: 'application/javascript'}], body: readFileSync(`${runtime}/swal.js`).toString('base64')});
                    } else if (request.url.startsWith('https://fonts.googleapis.com/css2?') || request.url === 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css') {
                        excluded.add(request.url);
                        await command('Fetch.fulfillRequest', {requestId, responseCode: 200, responseHeaders: [{name: 'Content-Type', value: 'text/css'}], body: ''});
                    } else if (new URL(request.url).origin === origin) {
                        await command('Fetch.continueRequest', {requestId});
                    } else {
                        errors.push('Unexpected external asset: ' + request.url);
                        await command('Fetch.failRequest', {requestId, errorReason: 'BlockedByClient'});
                    }
                })().catch(e => errors.push(e.message));
            }
        };
        await command('Page.enable'); await command('Runtime.enable'); await command('Network.enable');
        await command('Fetch.enable', {patterns: [{urlPattern: '*'}]});
        return {command, evaluate, navigate, waitFor, close, errors, requests, responses, excluded};
    } catch (error) { await close(); throw error; }
}
