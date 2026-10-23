import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
const source = readFileSync(new URL('../../resources/js/message-inbox.js', import.meta.url), 'utf8');
function harness({withInbox = true, withBadge = false, hidden = false, online = true} = {}) {
    const element = () => {
        const attributes = new Map();
        const node = {
            dataset: {}, style: {}, children: [], className: '', hidden: false, textContent: '',
            append(...children) { this.children.push(...children); },
            replaceChildren(fragment) { this.children = fragment?.children ?? []; },
            querySelector() { return null; },
            setAttribute(name, value) {
                attributes.set(name, String(value));
                if (name === 'title') this.title = String(value);
                if (name === 'hidden') this.hidden = true;
            },
            getAttribute(name) { return name === 'title' ? this.title ?? null : attributes.get(name) ?? null; },
            removeAttribute(name) { attributes.delete(name); if (name === 'hidden') this.hidden = false; },
            set innerHTML(value) { assert.fail(`Use textContent, not innerHTML: ${value}`); },
        };
        node.classList = {
            contains: name => node.className.split(/\s+/).includes(name),
            add(...names) { node.className = [...new Set([...node.className.split(/\s+/).filter(Boolean), ...names])].join(' '); },
            remove(...names) { node.className = node.className.split(/\s+/).filter(name => !names.includes(name)).join(' '); },
            toggle(name, force) {
                const add = force ?? !this.contains(name);
                if (add) this.add(name); else this.remove(name);
                return add;
            },
        };
        return node;
    };
    // Both pollers register the same events; keep all handlers instead of overwriting one.
    const eventTarget = () => {
        const events = {}, listeners = new Map();
        return {
            events,
            addEventListener(name, fn) {
                if (!listeners.has(name)) listeners.set(name, new Set());
                listeners.get(name).add(fn);
                events[name] = event => { for (const listener of listeners.get(name)) listener(event); };
            },
            removeEventListener(name, fn) { listeners.get(name)?.delete(fn); },
            dispatchEvent(event) { events[event.type]?.(event); return true; },
        };
    };
    const inbox = element();
    inbox.dataset = {messageInbox: '/inbox', messageLink: '/admin/messages', selectedOrder: '2'};
    const link = element(), badge = element();
    link.dataset.messageUnread = '/unread-count';
    link.href = '/admin/messages';
    badge.hidden = true;
    link.append(badge);
    link.querySelector = selector => selector === '[data-message-unread-count]' ? badge : null;
    const timers = new Map(), calls = [];
    let seq = 0;
    const document = {
        ...eventTarget(), hidden,
        querySelector: selector => selector === '[data-message-inbox]' ? (withInbox ? inbox : null)
            : selector === '[data-message-unread]' ? (withBadge ? link : null) : null,
        createElement: element, createDocumentFragment: element,
    };
    const window = eventTarget(), navigator = {onLine: online};
    vm.runInNewContext(source, {
        document, window, navigator, location: {origin: 'https://test.local'}, URL, Event, AbortController,
        fetch: (url, options) => new Promise((resolve, reject) => {
            calls.push({url, resolve, reject, options});
            options.signal.addEventListener('abort', () => reject(Object.assign(Error('aborted'), {name: 'AbortError'})));
        }),
        setTimeout: (fn, ms) => { timers.set(++seq, {fn, ms}); return seq; },
        clearTimeout: id => timers.delete(id),
    });
    const settle = async () => { for (let i = 0; i < 12; i++) await Promise.resolve(); };
    const respond = async (body, status = 200, call = calls.at(-1)) => {
        assert.ok(call, 'Expected a mocked polling request');
        call.resolve({status, ok: status === 200, json: async () => body});
        await settle();
    };
    const reply = (orders, status = 200) => respond({orders}, status);
    // Invoke only after settling a response: the remaining timer is the next poll, not its deadline.
    const runTimer = () => {
        assert.equal(timers.size, 1, 'Expected exactly one scheduled poll');
        const [id, timer] = [...timers][0];
        timers.delete(id);
        timer.fn();
        return timer.ms;
    };
    return {inbox, link, badge, events: window.events, documentEvents: document.events,
        timers, calls, document, window, navigator, settle, respond, reply, runTimer};
}
const badgeHarness = options => harness({withInbox: false, withBadge: true, ...options});
const isHidden = node => node.hidden || node.classList.contains('d-none') || node.style.display === 'none';
const order=(id,unread)=>({id,number:`DH${id}`,customer:'<img onerror=alert(1)>',date:'10-10-2026',preview:'Tin nhắn mới <script>',unread});
test('latest order moves first, unread dot clears and text stays escaped without replacing document',async()=>{
    const h=harness();await h.reply([order(1,true),order(2,false)]);
    assert.equal(h.inbox.children[0].dataset.inboxOrder,'1');
    assert.equal(h.inbox.children[0].children[0].children[0].children.length,1);
    assert.equal(h.inbox.children[0].children[0].children[0].textContent,order(1,true).customer);
    assert.equal(h.inbox.children[0].children[1].textContent,'DH1');
    assert.equal(h.inbox.children[0].children[2].textContent,'Tin nhắn mới <script>');
    h.events['message-inbox-changed']();await h.reply([order(2,true),order(1,false)]);
    assert.equal(h.inbox.children[0].dataset.inboxOrder,'2');
    assert.equal(h.inbox.children[1].children[0].children[0].children.length,0);
    assert.equal(h.inbox.children[0].href,'https://test.local/admin/messages?order_id=2');
});
test('hidden tabs pause and permission loss clears inbox and stops refresh',async()=>{
    const h=harness();await h.reply([order(1,true)]);
    h.document.hidden=true;h.documentEvents.visibilitychange();assert.equal(h.timers.size,0);
    h.document.hidden=false;h.documentEvents.visibilitychange();await h.reply([],403);
    assert.equal(h.inbox.children.length,0);assert.equal(h.timers.size,0);
    h.events.online();assert.equal(h.calls.length,2);
});

test('message badge follows unread counts, caps display and keeps the link at zero', async () => {
    const h = badgeHarness();
    for (const count of [0, 3, 120, 0]) {
        await h.respond({unread_count: count});
        assert.equal(isHidden(h.badge), count === 0);
        assert.equal(isHidden(h.link), false);
        assert.equal(h.badge.textContent, count > 99 ? '99+' : String(count));
        assert.equal(h.link.getAttribute('aria-label'), `Tin nhắn khách hàng, ${count} tin chưa đọc`);
        assert.equal(h.link.getAttribute('title'), `Tin nhắn khách hàng, ${count} tin chưa đọc`);
        assert.equal(h.runTimer(), 15000);
    }
    for (const {url, options} of h.calls) {
        assert.equal(url, '/unread-count');
        assert.equal(options.method ?? 'GET', 'GET');
        assert.equal(options.credentials, 'same-origin');
        assert.equal(options.cache, 'no-store');
        assert.equal(options.headers.Accept, 'application/json');
        assert.equal(options.body, undefined);
    }
});

test('badge refreshes after inbox changes without overlapping requests', async () => {
    const h = badgeHarness();
    h.events['message-inbox-changed']();
    h.events.online();
    assert.equal(h.calls.length, 1);
    await h.respond({unread_count: 2});
    h.events['message-inbox-changed']();
    assert.equal(h.calls.length, 2);
    await h.respond({unread_count: 1});
    assert.equal(h.badge.textContent, '1');
    assert.equal(h.timers.size, 1);
});

test('badge pauses while hidden or offline and resumes on return', async () => {
    const h = badgeHarness();
    await h.respond({unread_count: 2});
    h.document.hidden = true;
    h.documentEvents.visibilitychange();
    assert.equal(h.timers.size, 0);
    h.events['message-inbox-changed']();
    assert.equal(h.calls.length, 1);
    h.document.hidden = false;
    h.documentEvents.visibilitychange();
    await h.respond({unread_count: 3});
    h.navigator.onLine = false;
    h.events.offline();
    assert.equal(h.timers.size, 0);
    h.navigator.onLine = true;
    h.events.online();
    await h.respond({unread_count: 4});
    assert.equal(h.badge.textContent, '4');
});

for (const status of [401, 403, 404]) test(`badge hides and stops after ${status}`, async () => {
    const h = badgeHarness();
    await h.respond({unread_count: 5});
    h.runTimer();
    await h.respond({}, status);
    assert.equal(isHidden(h.link), true);
    assert.equal(isHidden(h.badge), true);
    assert.equal(h.timers.size, 0);
    h.events.online();
    h.events['message-inbox-changed']();
    assert.equal(h.calls.length, 2);
});

test('badge keeps last count on failure and invalid payloads with bounded backoff', async () => {
    const h = badgeHarness();
    await h.respond({unread_count: 5});
    h.runTimer();
    h.calls.at(-1).reject(Error('offline'));
    await h.settle();
    assert.equal(h.runTimer(), 30000);
    for (const [value, delay] of [[-1, 60000], ['7', 120000], [1.5, 120000], [null, 120000]]) {
        await h.respond({unread_count: value});
        assert.equal(h.badge.textContent, '5');
        assert.equal(h.runTimer(), delay);
    }
    await h.respond({unread_count: 6});
    assert.equal(h.runTimer(), 15000);
});

test('badge avoids rendering late responses on a hidden or disposed page', async () => {
    const h = badgeHarness();
    h.document.hidden = true;
    await h.respond({unread_count: 8});
    assert.equal(isHidden(h.badge), true);
    assert.equal(h.timers.size, 0);
    h.document.hidden = false;
    h.documentEvents.visibilitychange();
    await h.respond({unread_count: 2});
    h.events.pagehide();
    assert.equal(h.timers.size, 0);
    h.events['message-inbox-changed']();
    assert.equal(h.calls.length, 2);
    h.events.pageshow({persisted: true});
    await h.respond({unread_count: 3});
    assert.equal(h.badge.textContent, '3');
});

test('no badge permission means no badge requests or timers', () => {
    const h = harness({withInbox: false});
    assert.equal(h.calls.length, 0);
    assert.equal(h.timers.size, 0);
});

test('inbox and badge retain independent listeners on the same page', async () => {
    const h = harness({withBadge: true});
    await h.respond({orders: [order(1, true)]}, 200, h.calls.find(call => call.url === '/inbox'));
    await h.respond({unread_count: 1}, 200, h.calls.find(call => call.url === '/unread-count'));
    h.events['message-inbox-changed']();
    assert.equal(h.calls.length, 4);
    assert.equal(h.calls.filter(call => call.url === '/inbox').length, 2);
    assert.equal(h.calls.filter(call => call.url === '/unread-count').length, 2);
    h.document.hidden = true;
    h.documentEvents.visibilitychange();
    await h.settle();
    assert.equal(h.timers.size, 0);
});
