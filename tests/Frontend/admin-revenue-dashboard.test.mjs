import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/admin-revenue-dashboard.js', import.meta.url), 'utf8');

function chartPayload(filter) {
    return {
        filter,
        filter_label: `Doanh thu ${filter}`,
        labels: [`label-${filter}`],
        data: [123],
    };
}

function harness({url = '/admin/dashboard', filter = '7_days'} = {}) {
    const selectEvents = {};
    const windowEvents = {};
    const requests = [];
    const historyCalls = [];
    const chartUpdates = [];
    const apiResponses = [];
    const chartUpdateResponses = [];
    const location = {href: new URL(url, 'https://laundry.test').toString()};
    const select = {
        value: filter,
        disabled: false,
        dataset: {revenueChartUrl: '/admin/dashboard/revenue-chart'},
        addEventListener: (name, callback) => { selectEvents[name] = callback; },
    };
    const title = {textContent: `Doanh thu ${filter}`};
    const status = {
        textContent: '',
        hidden: true,
        classes: new Set(),
        classList: {
            toggle(name, enabled) {
                if (enabled) {
                    status.classes.add(name);
                } else {
                    status.classes.delete(name);
                }
            },
        },
    };
    const chart = {
        updateOptions: options => {
            chartUpdates.push(options);
            return chartUpdateResponses.shift() ?? Promise.resolve();
        },
    };
    const context = {
        AbortController,
        URL,
        document: {
            getElementById: id => ({
                revenueFilter: select,
                'revenue-chart-title': title,
                'revenue-filter-status': status,
            })[id] ?? null,
        },
        window: {
            adminRevenueChart: chart,
            adminRevenueChartData: {labels: ['initial-label'], data: [0]},
            location,
            history: {
                pushState: (state, ignored, nextUrl) => {
                    historyCalls.push({method: 'pushState', url: nextUrl});
                    location.href = new URL(nextUrl, location.href).toString();
                },
                replaceState: (state, ignored, nextUrl) => {
                    historyCalls.push({method: 'replaceState', url: nextUrl});
                    location.href = new URL(nextUrl, location.href).toString();
                },
            },
            addEventListener: (name, callback) => { windowEvents[name] = callback; },
        },
        fetch: (endpoint, options) => {
            requests.push({endpoint: endpoint.toString(), options});
            const next = apiResponses.shift();

            return next
                ? Promise.resolve(next)
                : Promise.reject(new Error('No API response configured.'));
        },
    };

    vm.runInNewContext(source, context);

    return {
        chartUpdates,
        historyCalls,
        location,
        requests,
        select,
        selectEvents,
        status,
        title,
        windowEvents,
        respond(body, {ok = true, statusCode = 200} = {}) {
            apiResponses.push({ok, status: statusCode, json: async () => body});
        },
        deferNextChartUpdate() {
            let resolve;
            const promise = new Promise(resolvePromise => { resolve = resolvePromise; });
            chartUpdateResponses.push(promise);

            return {resolve};
        },
    };
}

test('filter changes fetch each supported range and push a matching URL after success', async () => {
    const page = harness();

    for (const filter of ['this_month', 'this_year', 'today']) {
        page.respond(chartPayload(filter));
        page.select.value = filter;
        await page.selectEvents.change();

        assert.equal(new URL(page.requests.at(-1).endpoint).searchParams.get('filter'), filter);
        assert.equal(new URL(page.location.href).searchParams.get('filter'), filter);
        assert.equal(page.select.value, filter);
        assert.equal(page.title.textContent, `Doanh thu ${filter}`);
        assert.deepEqual(page.chartUpdates.at(-1).xaxis.categories, [`label-${filter}`]);
    }

    assert.deepEqual(page.historyCalls.map(call => call.method), ['pushState', 'pushState', 'pushState']);
});

test('Back and Forward load the URL filter without adding extra history entries', async () => {
    const page = harness({url: '/admin/dashboard?filter=this_year', filter: 'this_year'});
    const existingHistoryCalls = page.historyCalls.length;

    page.location.href = 'https://laundry.test/admin/dashboard?filter=this_month';
    page.respond(chartPayload('this_month'));
    await page.windowEvents.popstate();

    assert.equal(page.select.value, 'this_month');
    assert.equal(page.title.textContent, 'Doanh thu this_month');
    assert.equal(new URL(page.location.href).searchParams.get('filter'), 'this_month');
    assert.equal(page.historyCalls.length, existingHistoryCalls);

    page.location.href = 'https://laundry.test/admin/dashboard?filter=this_year';
    page.respond(chartPayload('this_year'));
    await page.windowEvents.popstate();

    assert.equal(page.select.value, 'this_year');
    assert.equal(page.title.textContent, 'Doanh thu this_year');
    assert.equal(page.historyCalls.length, existingHistoryCalls);
});

test('reload uses the filter and chart rendered by the server without a duplicate fetch', () => {
    const page = harness({url: '/admin/dashboard?filter=this_year', filter: 'this_year'});

    assert.equal(page.requests.length, 0);
    assert.equal(page.select.value, 'this_year');
    assert.equal(page.title.textContent, 'Doanh thu this_year');
});

test('invalid query filters are normalized to the rendered default', () => {
    const page = harness({url: '/admin/dashboard?filter=invalid', filter: '7_days'});

    assert.equal(page.requests.length, 0);
    assert.equal(new URL(page.location.href).searchParams.get('filter'), '7_days');
    assert.deepEqual(page.historyCalls.map(call => call.method), ['replaceState']);
});

test('failed API requests retain the last valid chart and restore the filter selection', async () => {
    const page = harness();
    page.respond({message: 'forbidden'}, {ok: false, statusCode: 403});
    page.select.value = 'this_month';

    await page.selectEvents.change();

    assert.equal(page.select.value, '7_days');
    assert.equal(page.title.textContent, 'Doanh thu 7_days');
    assert.equal(page.location.href, 'https://laundry.test/admin/dashboard');
    assert.equal(page.chartUpdates.length, 0);
    assert.equal(page.historyCalls.length, 0);
    assert.equal(page.status.hidden, false);
    assert.equal(page.status.classes.has('text-danger'), true);
    assert.equal(page.select.disabled, false);
});

test('Back to the active filter cancels an unfinished filter request', async () => {
    const page = harness();
    page.respond(chartPayload('this_month'));
    page.select.value = 'this_month';
    const pendingChange = page.selectEvents.change();

    page.location.href = 'https://laundry.test/admin/dashboard';
    await page.windowEvents.popstate();
    await pendingChange;

    assert.equal(page.select.value, '7_days');
    assert.equal(page.chartUpdates.length, 0);
    assert.equal(page.historyCalls.length, 0);
    assert.equal(page.status.hidden, true);
});

test('Back during chart rendering restores the last committed chart', async () => {
    const page = harness();
    const chartUpdate = page.deferNextChartUpdate();
    page.respond(chartPayload('this_month'));
    page.select.value = 'this_month';
    const pendingChange = page.selectEvents.change();

    await new Promise(resolve => setImmediate(resolve));
    assert.equal(page.chartUpdates.length, 1);

    page.location.href = 'https://laundry.test/admin/dashboard';
    await page.windowEvents.popstate();
    chartUpdate.resolve();
    await pendingChange;

    assert.deepEqual(Array.from(page.chartUpdates[1].xaxis.categories), ['initial-label']);
    assert.deepEqual(Array.from(page.chartUpdates[1].series[0].data), [0]);
    assert.equal(page.select.value, '7_days');
    assert.equal(page.title.textContent, 'Doanh thu 7_days');
    assert.equal(page.historyCalls.length, 0);
});
