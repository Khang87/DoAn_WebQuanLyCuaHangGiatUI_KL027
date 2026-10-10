import test from 'node:test';
import assert from 'node:assert/strict';
import { startMessageRealtime } from '../../resources/js/message-realtime.js';

function setup(status = 200) {
    let now = 2000000000000, next = 0, reads = 0, denied = 0, connected = false, requests = 0;
    const timers = new Map(), channels = [];
    const controller = startMessageRealtime({ endpoint: '/admin/messages/realtime', body: { order_id: 7 }, csrf: 'csrf',
        clock: () => now, later: (fn, ms) => { timers.set(++next, { fn, ms }); return next; }, cancel: id => timers.delete(id),
        refresh: () => { reads++; }, connection: value => { connected = value; }, denied: () => { denied++; },
        request: async (_url, options) => {
            requests++;
            assert.equal(options.credentials, 'same-origin');
            assert.equal(options.headers['X-CSRF-TOKEN'], 'csrf');
            assert.deepEqual(JSON.parse(options.body), { order_id: 7 });
            return { status, ok: status === 200, json: async () => ({ url: 'https://example.supabase.co',
                key: 'sb_publishable_test', topic: 'web-message:00000000-0000-0000-0000-000000000001', expires_at: now / 1000 + 600, expires_in: 600 }) };
        },
        clientFactory: (_url, _key, options) => {
            assert.equal(options.auth.persistSession, false);
            const c = { on(_type, _filter, callback) { this.signal = callback; return this; },
                subscribe(callback) { this.state = callback; return this; } };
            channels.push(c);
            return { channel: () => c, removeChannel: () => { c.removed = true; }, realtime: { disconnect() {} } };
        },
    });
    const run = async ms => { const [id, t] = [...timers].find(([, t]) => t.ms === ms) ?? [];
        assert.ok(t, `missing timer ${ms}`); timers.delete(id); now += ms; await t.fn(); await Promise.resolve(); };
    return { controller, channels, timers, run, get reads() { return reads; }, get denied() { return denied; },
        get connected() { return connected; }, get requests() { return requests; } };
}

test('subscription and reconnect catch up; signals never render payload and floods are coalesced', async () => {
    const h = setup(); await h.controller.resume();
    h.channels[0].state('SUBSCRIBED'); await h.run(0); assert.equal(h.reads, 1); assert.equal(h.connected, true);
    for (let i = 0; i < 100; i++) h.channels[0].signal({ payload: { content: '<img onerror=attack()>' } });
    assert.equal([...h.timers.values()].filter(t => t.ms === 1000).length, 1);
    await h.run(1000); assert.equal(h.reads, 2);
    h.channels[0].state('SUBSCRIBED'); await h.run(1000); assert.equal(h.reads, 3);
    h.controller.pause(); assert.equal(h.timers.size, 0);
});

test('expiry rotates topics and obsolete channel callbacks cannot refresh', async () => {
    const h = setup(); await h.controller.resume(); const old = h.channels[0];
    await h.run(580000); assert.equal(h.requests, 2); assert.equal(old.removed, true);
    old.signal(); old.state('SUBSCRIBED'); assert.equal(h.reads, 0); assert.equal(h.connected, false);
    h.channels[1].state('SUBSCRIBED'); await h.run(0); assert.equal(h.reads, 1);
    h.controller.pause();
});

test('channel failures retry and pause cancels retry, renewal and pending invalidation', async () => {
    const h = setup(); await h.controller.resume(); h.channels[0].state('CHANNEL_ERROR');
    await h.run(15000); assert.equal(h.requests, 2); assert.equal(h.channels[0].removed, true);
    h.channels[1].state('SUBSCRIBED'); h.controller.pause(); assert.equal(h.timers.size, 0);
    h.channels[1].signal(); assert.equal(h.timers.size, 0);
});

for (const status of [401, 403, 404, 419]) test(`configuration ${status} stops subscription and asks authorized reader to recheck`, async () => {
    const h = setup(status); await h.controller.resume(); assert.equal(h.denied, 1);
    assert.equal(h.channels.length, 0); assert.equal(h.timers.size, 0);
});

test('unavailable configuration leaves polling usable and retries later', async () => {
    const h = setup(503); await h.controller.resume(); assert.equal(h.connected, false);
    await h.run(60000); assert.equal(h.requests, 2); h.controller.pause(); assert.equal(h.timers.size, 0);
});


test('renewal uses server lifetime even when browser wall clock is decades ahead', async () => {
    const h = setup(); await h.controller.resume();
    assert.ok([...h.timers.values()].some(t => t.ms === 580000));
    await h.run(580000); assert.equal(h.requests, 2);
    assert.ok([...h.timers.values()].some(t => t.ms === 580000));
    h.controller.pause();
});
