import { createClient } from '@supabase/supabase-js';

// Broadcast is an invalidation only. Message content always comes from Laravel's ACL-checked API.
export function startMessageRealtime({ endpoint, body, csrf, refresh, connection, denied,
    request = fetch, clientFactory = createClient, clock = () => performance.now(),
    later = setTimeout, cancel = clearTimeout }) {
    let client, channel, renewal, reconnect, signalTimer, abort;
    let active = false, generation = 0, lastRead = 0;

    function invalidate() {
        if (!active || signalTimer) return;
        signalTimer = later(() => {
            signalTimer = null;
            if (!active) return;
            lastRead = clock();
            refresh();
        }, Math.max(0, 1000 - (clock() - lastRead)));
    }

    function pause() {
        active = false;
        generation++;
        cancel(renewal); cancel(reconnect); cancel(signalTimer);
        renewal = reconnect = signalTimer = null;
        abort?.abort();
        if (channel) client.removeChannel(channel);
        client?.realtime.disconnect();
        channel = client = null;
        connection(false);
    }

    async function resume() {
        pause();
        active = true;
        const current = generation;
        const valid = () => active && current === generation;
        const started = clock();
        const controller = new AbortController();
        abort = controller;
        const deadline = later(() => controller.abort(), 15000);
        try {
            const response = await request(endpoint, { method: 'POST', credentials: 'same-origin', cache: 'no-store',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(body), signal: controller.signal });
            if (!valid()) return;
            if ([401, 403, 404, 419].includes(response.status)) { pause(); denied(); return; }
            if (!response.ok) throw new Error('Realtime configuration unavailable');
            const config = await response.json();
            if (!valid()) return;
            const remaining = config.expires_in * 1000 - Math.max(0, clock() - started);
            if (!/^https:\/\/[a-z0-9-]+\.supabase\.co$/.test(config.url)
                || typeof config.key !== 'string' || typeof config.topic !== 'string'
                || !/^web-message:[0-9a-f-]{36}$/.test(config.topic) || !Number.isFinite(remaining) || remaining < 10000 || remaining > 660000) {
                throw new Error('Invalid Realtime configuration');
            }
            client = clientFactory(config.url, config.key, {
                auth: { persistSession: false, autoRefreshToken: false, detectSessionInUrl: false },
            });
            channel = client.channel(config.topic, { config: { private: false } })
                .on('broadcast', { event: 'changed' }, () => { if (valid()) invalidate(); })
                .subscribe(state => {
                    if (!valid()) return;
                    connection(state === 'SUBSCRIBED');
                    if (state === 'SUBSCRIBED') invalidate(); // Catch the read/subscribe race and reconnect gaps.
                    if (['CHANNEL_ERROR', 'TIMED_OUT', 'CLOSED'].includes(state)) {
                        cancel(reconnect);
                        reconnect = later(() => { if (valid()) void resume(); }, 15000);
                    }
                });
            renewal = later(() => { if (valid()) void resume(); }, remaining - 20000);
        } catch {
            if (valid()) {
                connection(false);
                reconnect = later(() => { if (valid()) void resume(); }, 60000);
            }
        } finally {
            cancel(deadline);
        }
    }

    return { resume, pause };
}
