import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
const source = readFileSync(new URL('../../resources/js/message-inbox.js', import.meta.url), 'utf8');
function harness({withInbox = true, withBadge = false, hidden = false, online = true,
    tab = 'orders', selectedId = '2', query = 'áo trắng & <b>', initialIds = [], nextPage = '2', abortFetch = true} = {}) {
    let document;
    const htmlWrites = [];
    // Both pollers share event names. Support multiple listeners and one-shot image errors.
    const eventTarget = () => {
        const events = {}, listeners = new Map();
        return {
            events,
            addEventListener(name, fn, options = {}) {
                if (!listeners.has(name)) listeners.set(name, new Map());
                listeners.get(name).set(fn, options.once);
                events[name] = (event = {type: name}) => {
                    for (const [listener, once] of [...listeners.get(name)]) {
                        if (once) listeners.get(name).delete(listener);
                        listener.call(this, event);
                    }
                };
            },
            removeEventListener(name, fn) { listeners.get(name)?.delete(fn); },
            dispatchEvent(event) { events[event.type]?.(event); return true; },
        };
    };
    const dataKey = name => name.slice(5).replace(/-([a-z])/g, (_, letter) => letter.toUpperCase());
    const element = (tag = 'div') => {
        const attributes = new Map();
        let text = '';
        const node = {
            ...eventTarget(), tagName: tag.toUpperCase(), nodeType: tag === '#fragment' ? 11 : 1,
            dataset: {}, style: {}, children: [], parentNode: null, className: '', hidden: false, disabled: false,
            get textContent() { return text + this.children.map(child => child.textContent).join(''); },
            set textContent(value) { this.replaceChildren(); text = String(value ?? ''); },
            append(...children) {
                for (const child of children) {
                    if (child.nodeType === 11) this.append(...child.children);
                    else { child.remove(); child.parentNode = this; this.children.push(child); }
                }
            },
            replaceChildren(...children) {
                for (const child of [...this.children]) child.remove();
                text = '';
                this.append(...children);
            },
            remove() {
                if (!this.parentNode) return;
                const siblings = this.parentNode.children;
                siblings.splice(siblings.indexOf(this), 1);
                if (this.contains(document?.activeElement)) document.activeElement = null;
                this.parentNode = null;
            },
            replaceWith(replacement) {
                const parent = this.parentNode;
                if (!parent || replacement === this) return;
                replacement.remove();
                const index = parent.children.indexOf(this);
                this.remove();
                parent.children.splice(index, 0, replacement);
                replacement.parentNode = parent;
            },
            contains(other) {
                for (let current = other; current; current = current.parentNode) if (current === this) return true;
                return false;
            },
            matches(selector) {
                const attribute = selector.match(/^\[([\w-]+)(?:="([^"]*)")?\]$/);
                if (attribute) {
                    const value = this.getAttribute(attribute[1]);
                    return value !== null && (attribute[2] === undefined || value === attribute[2]);
                }
                if (selector.startsWith('.')) return this.classList.contains(selector.slice(1));
                return this.tagName === selector.toUpperCase();
            },
            querySelectorAll(selector) {
                return this.children.flatMap(child => [
                    ...(child.matches(selector) ? [child] : []), ...child.querySelectorAll(selector),
                ]);
            },
            querySelector(selector) { return this.querySelectorAll(selector)[0] ?? null; },
            closest(selector) {
                for (let current = this; current; current = current.parentNode) if (current.matches(selector)) return current;
                return null;
            },
            setAttribute(name, value) {
                attributes.set(name, String(value));
                if (name.startsWith('data-')) this.dataset[dataKey(name)] = String(value);
                if (name === 'class') this.className = String(value);
                if (name === 'title') this.title = String(value);
                if (name === 'hidden') this.hidden = true;
            },
            getAttribute(name) {
                if (name.startsWith('data-')) return this.dataset[dataKey(name)] ?? null;
                if (name === 'class') return this.className;
                return name === 'title' ? this.title ?? null : attributes.get(name) ?? null;
            },
            removeAttribute(name) {
                attributes.delete(name);
                if (name.startsWith('data-')) delete this.dataset[dataKey(name)];
                if (name === 'hidden') this.hidden = false;
            },
            focus(options) { document.activeElement = this; this.focusOptions = options; },
            click() { if (!this.disabled) this.dispatchEvent({type: 'click', target: this}); },
            getBoundingClientRect() {
                const row = this.closest('[data-inbox-id]');
                const top = row ? 100 + rows.children.indexOf(row) * 48 - inbox.scrollTop : 100;
                const height = row ? 48 : inbox.clientHeight;
                return {top, bottom: top + height, height};
            },
            set innerHTML(value) { htmlWrites.push(value); assert.fail(`Use textContent, not innerHTML: ${value}`); },
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
    const inbox = element(), rows = element(), more = element('button'), status = element();
    const search = new URLSearchParams({tab, q: query});
    inbox.dataset = {messageInbox: `/inbox?${search}`, messageLink: `/admin/messages?${search}`,
        inboxTab: tab, inboxPage: '1', nextPage, selectedId};
    rows.dataset.inboxRows = '';
    more.dataset.inboxMore = '';
    status.dataset.inboxStatus = '';
    inbox.append(rows, more, status);
    inbox.scrollTop = 0;
    inbox.clientHeight = 240;
    Object.defineProperty(inbox, 'scrollHeight', {get: () => rows.children.length * 48 + 40});
    for (const id of initialIds) {
        const row = element('a');
        row.dataset.inboxId = String(id);
        row.textContent = `Server row ${id}`;
        rows.append(row);
    }
    const link = element('a'), badge = element('span'), body = element('body');
    link.dataset.messageUnread = '/unread-count';
    link.href = '/admin/messages';
    badge.dataset.messageUnreadCount = '';
    badge.hidden = true;
    link.append(badge);
    if (withInbox) body.append(inbox);
    if (withBadge) body.append(link);
    const timers = new Map(), calls = [];
    let seq = 0;
    document = {
        ...eventTarget(), hidden, activeElement: null, body,
        querySelector: selector => body.querySelector(selector),
        querySelectorAll: selector => body.querySelectorAll(selector),
        createElement: element, createDocumentFragment: () => element('#fragment'),
    };
    const window = eventTarget(), navigator = {onLine: online};
    vm.runInNewContext(source, {
        document, window, navigator, location: {origin: 'https://test.local'}, URL, Event, AbortController,
        fetch: (url, options) => new Promise((resolve, reject) => {
            calls.push({url, resolve, reject, options});
            // Disabling rejection simulates a late result whose delivery races with cancellation.
            if (abortFetch) options.signal.addEventListener('abort', () => reject(Object.assign(Error('aborted'), {name: 'AbortError'})));
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
    const inboxCalls = () => calls.filter(call => new URL(call.url, 'https://test.local').pathname === '/inbox');
    const reply = (conversations, {page = 1, nextPage = null, status = 200, tab: responseTab = tab} = {}, call = inboxCalls().at(-1)) =>
        respond({tab: responseTab, page, next_page: nextPage, conversations}, status, call);
    // Invoke only after settling a response: the remaining timer is the next poll, not its deadline.
    const runTimer = () => {
        assert.equal(timers.size, 1, 'Expected exactly one scheduled poll');
        const [id, timer] = [...timers][0];
        timers.delete(id);
        timer.fn();
        return timer.ms;
    };
    return {inbox, rows, more, status, link, badge, htmlWrites, events: window.events, documentEvents: document.events,
        timers, calls, inboxCalls, document, window, navigator, settle, respond, reply, runTimer};
}
const badgeHarness = options => harness({withInbox: false, withBadge: true, ...options});
const isHidden = node => node.hidden || node.classList.contains('d-none') || node.style.display === 'none';
const conversation = (id, overrides = {}) => ({id, number: `DH${id}`, customer: `Khách ${id}`, avatar_url: null,
    date: '10-10-2026', preview: `Tin nhắn ${id}`, unread: false, ...overrides});
const batch = (start = 1, count = 20) => Array.from({length: count}, (_, index) => conversation(start + index));
const ids = h => h.rows.querySelectorAll('[data-inbox-id]').map(row => Number(row.dataset.inboxId));
const rowFor = (h, id) => h.rows.querySelector(`[data-inbox-id="${id}"]`);
const requestPage = call => Number(new URL(call.url).searchParams.get('page'));
const scroll = h => h.inbox.dispatchEvent({type: 'scroll'});

for (const tab of ['orders', 'support']) {
    test(`${tab}: syncs page 1 only, preserving search, selected link and request options`, async () => {
        const h = harness({tab, initialIds: [1, 2]});
        const serverRows = [...h.rows.children];
        assert.equal(h.calls.length, 1);
        assert.equal(requestPage(h.calls[0]), 1);
        assert.equal(new URL(h.calls[0].url).searchParams.get('tab'), tab);
        assert.equal(new URL(h.calls[0].url).searchParams.get('q'), 'áo trắng & <b>');
        assert.deepEqual(h.rows.children, serverRows, 'Keep server rows while synchronizing');
        assert.equal(h.inbox.getAttribute('aria-busy'), 'true');
        assert.equal(h.more.disabled, true);
        h.more.click();
        scroll(h);
        h.events.resize();
        h.events['message-inbox-changed']();
        h.events.online();
        assert.equal(h.calls.length, 1, 'Only one inbox request can be pending');
        await h.reply(batch(), {nextPage: 2});
        assert.deepEqual(ids(h), batch().map(item => item.id));
        assert.equal(h.calls.length, 1, 'A full first page must not eagerly fetch page 2');
        assert.equal(h.inbox.getAttribute('aria-busy'), 'false');
        assert.equal(h.more.disabled, false);
        assert.equal(h.more.hidden, false);
        for (const row of h.rows.children) {
            const url = new URL(row.href);
            const parameter = tab === 'support' ? 'customer_id' : 'order_id';
            assert.equal(url.origin + url.pathname, 'https://test.local/admin/messages');
            assert.equal(url.searchParams.get('tab'), tab);
            assert.equal(url.searchParams.get('q'), 'áo trắng & <b>');
            assert.equal(url.searchParams.get(parameter), row.dataset.inboxId);
            assert.equal(url.searchParams.has(tab === 'support' ? 'order_id' : 'customer_id'), false);
            assert.equal(row.classList.contains('active'), row.dataset.inboxId === '2');
        }
        const {options} = h.calls[0];
        assert.equal(options.method ?? 'GET', 'GET');
        assert.equal(options.credentials, 'same-origin');
        assert.equal(options.cache, 'no-store');
        assert.equal(options.headers.Accept, 'application/json');
        assert.equal(options.body, undefined);
        assert.equal(options.signal.aborted, false);
    });

    test(`${tab}: loads 20 more near the bottom or by button and stops paging at the end`, async () => {
        const h = harness({tab});
        await h.reply(batch(), {nextPage: 2});
        h.inbox.scrollTop = h.inbox.scrollHeight - h.inbox.clientHeight - 100;
        scroll(h);
        assert.equal(h.calls.length, 1);
        h.inbox.scrollTop++;
        scroll(h);
        assert.equal(requestPage(h.calls.at(-1)), 2);
        scroll(h);
        h.events.resize();
        h.more.click();
        h.events['message-inbox-changed']();
        assert.equal(h.calls.length, 2, 'Scroll, click and refresh must not overlap a page load');
        await h.reply(batch(21), {page: 2, nextPage: 3});
        assert.deepEqual(ids(h), batch(1, 40).map(item => item.id));
        assert.equal(h.calls.length, 2);
        h.more.click();
        assert.equal(requestPage(h.calls.at(-1)), 3);
        await h.reply([], {page: 3});
        assert.equal(h.rows.children.length, 40, 'An empty final page must not erase loaded rows');
        assert.equal(h.more.hidden, true);
        assert.equal(h.status.textContent, 'Đã tải hết hội thoại.');
        h.inbox.scrollTop = h.inbox.scrollHeight - h.inbox.clientHeight;
        scroll(h);
        h.events.resize();
        h.more.click();
        assert.equal(h.calls.length, 3, 'No more-page requests after next_page becomes null');
        assert.equal(h.runTimer(), 3000);
        assert.equal(requestPage(h.calls.at(-1)), 1, 'Polling always refreshes the base page');
        await h.reply(batch(), {nextPage: 2});
        assert.equal(h.rows.children.length, 40);
        assert.equal(h.more.hidden, true, 'A base refresh must not reset the exhausted page cursor');
        assert.equal(h.calls.length, 4);
    });

    test(`${tab}: empty first page renders the tab-specific empty state`, async () => {
        const h = harness({tab, initialIds: [1], nextPage: ''});
        assert.equal(h.more.hidden, true, 'An empty DOM cursor hides load more immediately');
        await h.reply([]);
        assert.deepEqual(ids(h), []);
        assert.equal(h.rows.textContent, tab === 'support' ? 'Không tìm thấy khách hàng tư vấn.' : 'Không tìm thấy hội thoại đơn hàng.');
        assert.equal(h.more.hidden, true);
        h.more.click();
        scroll(h);
        h.events.resize();
        assert.equal(h.calls.length, 1);
    });
}

test('refresh promotes conversations and deduplicates pages without losing loaded rows, anchor or focus', async () => {
    const h = harness();
    await h.reply(batch(), {nextPage: 2});
    h.more.click();
    await h.reply([conversation(20, {preview: 'Updated overlap'}), ...batch(21, 19)], {page: 2, nextPage: 3});
    assert.deepEqual(ids(h), batch(1, 39).map(item => item.id));
    assert.equal(rowFor(h, 20).querySelector('.message-inbox-preview').textContent, 'Updated overlap');
    h.inbox.scrollTop = 23 * 48 + 13;
    rowFor(h, 25).focus();
    const retained = rowFor(h, 25);
    const anchorTop = rowFor(h, 24).getBoundingClientRect().top;
    const refreshed = [conversation(39, {unread: true}), ...batch(1, 19)];
    h.events['message-inbox-changed']();
    await h.reply(refreshed, {nextPage: 2});
    assert.deepEqual(ids(h), [39, ...batch(1, 38).map(item => item.id)]);
    assert.equal(new Set(ids(h)).size, 39);
    assert.equal(rowFor(h, 24).getBoundingClientRect().top, anchorTop);
    assert.equal(h.document.activeElement, retained);
    assert.equal(retained.focusOptions.preventScroll, true);
    assert.ok(rowFor(h, 39).querySelector('[data-unread-dot]'));
    const unchangedRows = [...h.rows.children], unchangedScroll = h.inbox.scrollTop;
    h.events['message-inbox-changed']();
    await h.reply(refreshed, {nextPage: 2});
    assert.deepEqual(h.rows.children, unchangedRows, 'Unchanged base data should not recreate rows');
    assert.equal(h.inbox.scrollTop, unchangedScroll);
    assert.equal(h.document.activeElement, retained);
    h.inbox.scrollTop = 0;
    h.more.click();
    assert.equal(requestPage(h.calls.at(-1)), 3, 'Refreshing page 1 must preserve the loaded-page cursor');
    await h.reply([conversation(39, {preview: 'Updated again'}), conversation(40)], {page: 3});
    assert.equal(h.rows.children.length, 40);
    assert.equal(new Set(ids(h)).size, 40);
    assert.equal(rowFor(h, 39).querySelector('.message-inbox-preview').textContent, 'Updated again');
});

test('row replacement preserves focused row and uses literal text, lazy avatars and initials on error', async () => {
    const h = harness();
    const hostile = conversation(1, {customer: '  đặng <img src=x onerror=alert(1)>  ',
        number: '<b>DH1</b>', preview: '<script>alert(1)</script>', date: '<svg onload=alert(1)>', unread: true});
    const photo = conversation(2, {avatar_url: '/avatars/2.png', preview: null, date: null});
    await h.reply([hostile, photo, conversation(3, {customer: '  \u{10428} Laundry', preview: ''})]);
    const first = rowFor(h, 1), avatar = first.querySelector('[role="img"]');
    assert.equal(first.querySelector('.fw-semibold').textContent, hostile.customer);
    assert.equal(first.querySelector('.d-block').textContent, hostile.number);
    assert.equal(first.querySelector('.message-inbox-preview').textContent, hostile.preview);
    assert.equal(first.querySelector('small').textContent, hostile.date);
    assert.equal(first.querySelector('img'), null);
    assert.equal(first.querySelector('script'), null);
    assert.equal(first.querySelector('svg'), null);
    assert.equal(avatar.textContent, 'Đ');
    assert.equal(avatar.getAttribute('aria-label'), `Ảnh đại diện của ${hostile.customer.trim()}`);
    assert.equal(avatar.children[0].getAttribute('aria-hidden'), 'true');
    assert.equal(rowFor(h, 3).querySelector('[role="img"]').textContent, '\u{10400}');
    const second = rowFor(h, 2), image = second.querySelector('img'), initial = image.parentNode.children[0];
    assert.equal(image.loading, 'lazy');
    assert.equal(image.src, '/avatars/2.png');
    assert.equal(image.alt, '');
    assert.equal(initial.hidden, true);
    assert.equal(second.querySelector('.message-inbox-preview').textContent, 'Chưa có tin nhắn');
    assert.equal(rowFor(h, 3).querySelector('.message-inbox-preview').textContent, 'Chưa có tin nhắn');
    assert.equal(second.querySelector('small').textContent, '');
    image.dispatchEvent({type: 'error'});
    assert.equal(initial.hidden, false);
    assert.equal(initial.textContent, 'K');
    assert.equal(second.querySelector('img'), null);
    image.dispatchEvent({type: 'error'});
    first.focus();
    h.events['message-inbox-changed']();
    await h.reply([{...photo, unread: true}, {...hostile, unread: false}]);
    assert.deepEqual(ids(h), [2, 1]);
    assert.equal(rowFor(h, 1).querySelector('[data-unread-dot]'), null);
    assert.equal(rowFor(h, 2).querySelector('[data-unread-dot]').getAttribute('aria-label'), 'Tin nhắn chưa đọc');
    assert.equal(h.document.activeElement, rowFor(h, 1));
    assert.notEqual(h.document.activeElement, first, 'Focus must transfer to the replacement row');
    assert.equal(h.document.activeElement.focusOptions.preventScroll, true);
    assert.deepEqual(h.htmlWrites, []);
    assert.equal(h.document.querySelector('[data-message-inbox]'), h.inbox);
});

test('failed more requests preserve rows and page, require an explicit retry and reset backoff after success', async () => {
    const h = harness();
    await h.reply(batch(), {nextPage: 2});
    const previous = [...h.rows.children];
    rowFor(h, 2).focus();
    h.inbox.scrollTop = 100;
    h.more.click();
    h.calls.at(-1).reject(Error('Network failure'));
    await h.settle();
    for (const delay of [6000, 12000]) {
        assert.deepEqual(h.rows.children, previous);
        assert.equal(h.document.activeElement, previous[1]);
        assert.equal(h.inbox.scrollTop, 100);
        assert.equal(h.more.textContent, 'Thử lại');
        assert.equal(h.more.disabled, false);
        assert.equal(h.inbox.getAttribute('aria-busy'), 'false');
        assert.equal(h.status.textContent, 'Chưa tải được thêm hội thoại. Vui lòng thử lại.');
        assert.equal([...h.timers.values()][0].ms, delay);
        const count = h.calls.length;
        h.inbox.scrollTop = h.inbox.scrollHeight - h.inbox.clientHeight;
        scroll(h);
        h.events.resize();
        assert.equal(h.calls.length, count, 'Do not automatically loop on a failed page');
        h.inbox.scrollTop = 100;
        h.more.click();
        assert.equal(requestPage(h.calls.at(-1)), 2);
        assert.equal(h.more.disabled, true);
        if (delay === 6000) await h.reply([], {page: 2, status: 503});
    }
    await h.reply(batch(21), {page: 2, nextPage: 3});
    assert.equal(h.rows.children.length, 40);
    assert.equal(h.more.textContent, 'Tải thêm hội thoại');
    assert.equal(h.runTimer(), 3000);
    assert.equal(requestPage(h.calls.at(-1)), 1);
    await h.reply(batch(), {nextPage: 2});
    h.more.click();
    assert.equal(requestPage(h.calls.at(-1)), 3);
});

test('invalid payloads never mutate loaded rows or advance the retry page', async t => {
    const valid = {tab: 'orders', page: 2, next_page: 3, conversations: batch(21)};
    const cases = [
        ['wrong tab', {...valid, tab: 'support'}],
        ['wrong page', {...valid, page: 1}],
        ['string page', {...valid, page: '2'}],
        ['skipped next page', {...valid, next_page: 4}],
        ['repeated next page', {...valid, next_page: 2}],
        ['string next page', {...valid, next_page: '3'}],
        ['missing next page', {tab: 'orders', page: 2, conversations: []}],
        ['more than 20 rows', {...valid, conversations: batch(21, 21)}],
        ['duplicate IDs', {...valid, conversations: [conversation(21), conversation(21)]}],
        ['non-array conversations', {...valid, conversations: {id: 21}}],
        ['null payload', null],
        ['null row', {...valid, conversations: [null]}],
        ...[
            ['zero ID', {id: 0}], ['negative ID', {id: -1}], ['fractional ID', {id: 1.5}],
            ['unsafe ID', {id: Number.MAX_SAFE_INTEGER + 1}], ['string ID', {id: '21'}],
            ['invalid number', {number: null}], ['invalid customer', {customer: null}],
            ['invalid unread', {unread: 1}], ['invalid avatar', {avatar_url: {url: '/avatar'}}],
            ['missing avatar', {avatar_url: undefined}], ['invalid preview', {preview: 7}],
            ['invalid date', {date: []}],
        ].map(([name, fields]) => [name, {...valid, conversations: [conversation(21, fields)]}]),
    ];
    for (const [name, payload] of cases) await t.test(name, async () => {
        const h = harness();
        await h.reply(batch(), {nextPage: 2});
        const previous = [...h.rows.children];
        h.more.click();
        await h.respond(payload);
        assert.deepEqual(h.rows.children, previous);
        assert.equal(h.more.textContent, 'Thử lại');
        assert.equal(h.more.hidden, false);
        assert.equal(h.more.disabled, false);
        assert.equal(h.inbox.getAttribute('aria-busy'), 'false');
        assert.equal(h.calls.length, 2);
        assert.equal([...h.timers.values()][0].ms, 6000);
        h.more.click();
        assert.equal(requestPage(h.calls.at(-1)), 2);
        await h.reply([conversation(21)], {page: 2});
        assert.deepEqual(ids(h), batch(1, 21).map(item => item.id));
        assert.equal(h.more.hidden, true);
    });
});

test('failed base refresh at the end exposes a base-page retry with bounded backoff', async () => {
    const h = harness();
    await h.reply([conversation(1)]);
    assert.equal(h.more.hidden, true);
    h.events['message-inbox-changed']();
    for (const delay of [6000, 12000, 24000, 48000, 96000, 120000, 120000]) {
        await h.reply([], {status: 500});
        assert.deepEqual(ids(h), [1]);
        assert.equal(h.more.hidden, false);
        assert.equal(h.more.textContent, 'Thử lại');
        assert.equal(h.status.textContent, 'Chưa cập nhật được hội thoại. Vui lòng thử lại.');
        assert.equal(h.runTimer(), delay);
        assert.equal(requestPage(h.calls.at(-1)), 1);
    }
    await h.respond({tab: 'support', page: 1, next_page: null, conversations: []});
    h.more.click();
    assert.equal(requestPage(h.calls.at(-1)), 1);
    await h.reply([conversation(2)]);
    assert.deepEqual(ids(h), [2]);
    assert.equal(h.more.hidden, true);
    assert.equal(h.runTimer(), 3000);
});

test('inbox request deadline aborts and releases the busy state for retry', async () => {
    const h = harness({initialIds: [1]});
    const pending = h.calls[0];
    assert.equal(h.runTimer(), 30000);
    assert.equal(pending.options.signal.aborted, true);
    await h.settle();
    assert.deepEqual(ids(h), [1]);
    assert.equal(h.inbox.getAttribute('aria-busy'), 'false');
    assert.equal(h.more.disabled, false);
    assert.equal(h.more.textContent, 'Thử lại');
    h.more.click();
    assert.equal(requestPage(h.calls.at(-1)), 1);
    await h.reply([conversation(2)]);
    assert.deepEqual(ids(h), [2]);
});

for (const status of [401, 403, 404]) test(`inbox clears rows and permanently stops after ${status}`, async () => {
    const h = harness();
    await h.reply(batch(), {nextPage: 2});
    h.more.click();
    await h.reply([], {page: 2, status});
    assert.equal(h.rows.children.length, 0);
    assert.equal(h.more.hidden, true);
    assert.equal(h.inbox.getAttribute('aria-busy'), 'false');
    assert.equal(h.status.textContent, 'Không thể truy cập danh sách hội thoại.');
    assert.equal(h.timers.size, 0);
    h.events['message-inbox-changed']();
    h.events.online();
    h.events.resize();
    h.documentEvents.visibilitychange();
    h.events.pagehide();
    h.events.pageshow({persisted: true});
    h.more.click();
    scroll(h);
    assert.equal(h.calls.length, 2);
    assert.equal(h.timers.size, 0);
});

const lifecycle = {
    hidden: {
        pause(h) { h.document.hidden = true; h.documentEvents.visibilitychange(); },
        resume(h) { h.document.hidden = false; h.documentEvents.visibilitychange(); },
    },
    offline: {
        pause(h) { h.navigator.onLine = false; h.events.offline(); },
        resume(h) { h.navigator.onLine = true; h.events.online(); },
    },
    disposed: {
        pause(h) { h.events.pagehide(); },
        resume(h) { h.events.pageshow({persisted: true}); },
    },
};
for (const state of ['hidden', 'offline']) test(`inbox starts paused when ${state} and synchronizes on return`, async () => {
    const h = harness(state === 'hidden' ? {hidden: true} : {online: false});
    h.more.click();
    scroll(h);
    h.events['message-inbox-changed']();
    assert.equal(h.calls.length, 0);
    assert.equal(h.timers.size, 0);
    lifecycle[state].resume(h);
    assert.equal(h.calls.length, 1);
    assert.equal(requestPage(h.calls[0]), 1);
    await h.reply(batch(), {nextPage: 2});
    assert.equal(h.rows.children.length, 20);
});

for (const [state, actions] of Object.entries(lifecycle)) {
    test(`inbox aborts pending more, pauses while ${state}, and resumes the base page`, async () => {
        const h = harness();
        await h.reply(batch(), {nextPage: 2});
        h.more.click();
        const pending = h.calls.at(-1);
        actions.pause(h);
        assert.equal(pending.options.signal.aborted, true);
        await h.settle();
        assert.equal(h.timers.size, 0);
        assert.equal(h.inbox.getAttribute('aria-busy'), 'false');
        assert.equal(h.more.textContent, 'Tải thêm hội thoại', 'Cancellation is not a failed-more state');
        await h.reply(batch(21), {page: 2, nextPage: 3}, pending);
        assert.deepEqual(ids(h), batch().map(item => item.id));
        h.more.click();
        scroll(h);
        h.events.resize();
        h.events['message-inbox-changed']();
        h.events.pageshow({persisted: false});
        assert.equal(h.calls.length, 2);
        actions.resume(h);
        assert.equal(requestPage(h.calls.at(-1)), 1);
        await h.reply(batch(), {nextPage: 2});
        h.more.click();
        assert.equal(requestPage(h.calls.at(-1)), 2, 'Cancellation must not advance nextPage');
        await h.reply(batch(21), {page: 2});
        assert.equal(h.rows.children.length, 40);
    });

    for (const phase of ['fetch', 'json']) test(`inbox ignores late ${phase} results while ${state}`, async () => {
        const h = harness({abortFetch: false});
        await h.reply(batch(), {nextPage: 2});
        const previous = [...h.rows.children];
        h.more.click();
        const pending = h.calls.at(-1);
        let finishJson;
        if (phase === 'json') {
            const body = new Promise(resolve => { finishJson = resolve; });
            await h.respond(body, 200, pending);
        }
        actions.pause(h);
        assert.equal(pending.options.signal.aborted, true);
        const payload = {tab: 'orders', page: 2, next_page: 3, conversations: batch(21)};
        if (phase === 'json') { finishJson(payload); await h.settle(); }
        else await h.respond(payload, 200, pending);
        assert.equal(h.rows.children.length, 20, 'Late responses must not render on an inactive page');
        assert.deepEqual(h.rows.children, previous);
        assert.equal(h.timers.size, 0);
        actions.resume(h);
        await h.reply(batch(), {nextPage: 2});
        h.more.click();
        assert.equal(requestPage(h.calls.at(-1)), 2, 'Late responses must not advance the paging cursor');
    });
}

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
    await h.reply([conversation(1, {unread: true})]);
    await h.respond({unread_count: 1}, 200, h.calls.find(call => call.url === '/unread-count'));
    h.events['message-inbox-changed']();
    assert.equal(h.calls.length, 4);
    assert.equal(h.inboxCalls().length, 2);
    assert.equal(h.calls.filter(call => call.url === '/unread-count').length, 2);
    h.document.hidden = true;
    h.documentEvents.visibilitychange();
    await h.settle();
    assert.equal(h.timers.size, 0);
});

for (const stoppedPoller of ['inbox', 'badge']) test(`${stoppedPoller} permission loss does not stop the other poller`, async () => {
    const h = harness({withBadge: true});
    const badgeCalls = () => h.calls.filter(call => call.url === '/unread-count');
    await h.reply(batch(), {nextPage: 2});
    await h.respond({unread_count: 7}, 200, badgeCalls().at(-1));
    h.more.click();
    await h.reply(batch(21), {page: 2});
    assert.equal(badgeCalls().length, 1, 'Loading more must not issue a badge request');
    assert.equal(h.badge.textContent, '7', 'Inbox unread dots do not replace the global count');
    assert.equal(isHidden(h.badge), false);
    h.events['message-inbox-changed']();
    if (stoppedPoller === 'inbox') {
        await h.reply([], {status: 403});
        await h.respond({unread_count: 8}, 200, badgeCalls().at(-1));
        assert.equal(h.rows.children.length, 0);
        assert.equal(isHidden(h.link), false);
        assert.equal(h.badge.textContent, '8');
        assert.equal(h.runTimer(), 15000);
        assert.equal(h.calls.at(-1).url, '/unread-count');
    } else {
        await h.respond({}, 403, badgeCalls().at(-1));
        await h.reply(batch(), {nextPage: 2});
        assert.equal(isHidden(h.link), true);
        assert.equal(h.rows.children.length, 40);
        assert.equal(h.runTimer(), 3000);
        assert.equal(requestPage(h.calls.at(-1)), 1);
    }
});
