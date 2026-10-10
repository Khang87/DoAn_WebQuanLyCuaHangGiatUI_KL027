import { spawn } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { setTimeout as delay } from 'node:timers/promises';

export async function openBrowser(runtime, origin, {assetDelayMs = 0, alertGate = null, avatarStorage = null, messageRealtime = false, allowedConsoleWarnings = []} = {}) {
    const child = spawn(process.env.CHROMIUM_BIN || 'chromium', ['--headless', '--no-sandbox', '--disable-dev-shm-usage', '--disable-background-networking', '--no-first-run', '--remote-debugging-port=0', `--user-data-dir=${runtime}/browser`, 'about:blank'], {stdio: 'ignore'});
    const errors = [], requests = [], responses = [], excluded = new Set(), canceled = new Set(), loaded = new Set(), pending = new Map();
    let socket, id = 0, startupError;
    child.on('error', error => {startupError=error;});
    async function waitFor(check, label) {
        for (let i = 0; i < 150; i++) { if (startupError) throw startupError; const value = await check(); if (value) return value; await delay(100); }
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
        if (result.exceptionDetails) throw Error('Page JavaScript must complete: '+result.exceptionDetails.text);
        return result.result.value;
    }
    async function navigate(path) {
        const start = responses.length;
        const navigation = await command('Page.navigate', {url: origin + path});
        if (navigation.errorText) throw Error(navigation.errorText);
        await waitFor(async () => loaded.has(navigation.loaderId) && responses.slice(start).some(r => r.type === 'Document') && await evaluate('document.readyState === "complete"'), path);
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
            if (m.method === 'Page.lifecycleEvent' && m.params.name === 'load') loaded.add(m.params.loaderId);
            if (m.method === 'Runtime.consoleAPICalled' && ['error','warning'].includes(m.params.type)) {
                const expectedWarning = m.params.type === 'warning' && allowedConsoleWarnings.includes(m.params.args?.[0]?.value);
                if (!expectedWarning) errors.push('Console '+m.params.type);
            }
            if (m.method === 'Runtime.exceptionThrown') errors.push('Page exception: ' + m.params.exceptionDetails.text);
            if (m.method === 'Network.requestWillBeSent') requests.push(m.params.request);
            if (m.method === 'Network.responseReceived') responses.push({...m.params.response, type: m.params.type});
            if (m.method === 'Network.loadingFailed' && m.params.canceled) canceled.add(m.params.requestId);
            if (m.method === 'Network.loadingFailed' && !m.params.canceled) errors.push('Request failed: ' + m.params.errorText);
            if (m.method === 'Fetch.requestPaused') {
                const {requestId, request, networkId} = m.params;
                (async () => {
                    if (request.url === 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js') {
                        await delay(assetDelayMs);
                        if (alertGate) await alertGate();
                    }
                    if (request.url === 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js') {
                        await command('Fetch.fulfillRequest', {requestId, responseCode: 200, responseHeaders: [{name: 'Content-Type', value: 'application/javascript'}], body: readFileSync(`${runtime}/swal.js`).toString('base64')});
                    } else if (request.url.startsWith('https://fonts.googleapis.com/css2?') || request.url === 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css') {
                        excluded.add(request.url);
                        await command('Fetch.fulfillRequest', {requestId, responseCode: 200, responseHeaders: [{name: 'Content-Type', value: 'text/css'}], body: ''});
                    } else if (messageRealtime && new URL(request.url).pathname.includes('/message-realtime-')) {
                        // Substitute only the external SDK boundary; run the production controller module unchanged.
                        const source = readFileSync(new URL('../../resources/js/message-realtime.js', import.meta.url), 'utf8')
                            .replace("import { createClient } from '@supabase/supabase-js';", `
                                function createClient() {
                                    const channel = { on(type, filter, callback) { window.messageSignal=callback; return this; },
                                        subscribe(callback) { window.messageConnection=callback; queueMicrotask(()=>callback('SUBSCRIBED')); return this; } };
                                    return { channel:()=>channel, removeChannel:()=>Promise.resolve('ok'), realtime:{disconnect(){}} };
                                }
                            `);
                        await command('Fetch.fulfillRequest', { requestId, responseCode:200,
                            responseHeaders:[{name:'Content-Type',value:'application/javascript'}], body:Buffer.from(source).toString('base64') });
                    } else if (avatarStorage && new URL(request.url).origin === 'https://avatar-fixture.supabase.co') {
                        const response=await avatarStorage(request);
                        await command('Fetch.fulfillRequest',{requestId,...response});
                    } else if (new URL(request.url).origin === origin) {
                        await command('Fetch.continueRequest', {requestId});
                    } else {
                        errors.push('Unexpected external asset: ' + request.url);
                        await command('Fetch.failRequest', {requestId, errorReason: 'BlockedByClient'});
                    }
                })().catch(async e => {
                    // Chromium can finish/cancel a cached font before the paused-request command arrives.
                    // An expired CDP interception is not an application failure; Network.loadingFailed
                    // and runtime errors above still report actual uncanceled failures independently.
                    if (e.message === 'Invalid InterceptionId.') return;
                    errors.push(`${e.message} (${request.url})`);
                });
            }
        };
        await command('Page.enable'); await command('Page.setLifecycleEventsEnabled', {enabled:true}); await command('Runtime.enable'); await command('Network.enable');
        // Local font preloads can be canceled during navigation; let Chromium load them natively.
        // Network failures remain observed above; only resources with test substitutions need interception.
        await command('Fetch.enable', {patterns: ['Document', 'Script', 'Stylesheet', 'Image', 'XHR', 'Fetch'].map(resourceType => ({urlPattern: '*', resourceType}))});
        return {command, evaluate, navigate, waitFor, close, errors, requests, responses, excluded};
    } catch (error) { await close(); throw error; }
}
