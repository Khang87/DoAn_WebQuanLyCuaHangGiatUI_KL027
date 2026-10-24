import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/admin-period-filter.js', import.meta.url), 'utf8');

function harness({url = '/admin/dashboard', filter = 'this_week', hasFilter = true} = {}) {
    const selectEvents = {};
    const windowEvents = {};
    const submissions = [];
    const historyCalls = [];
    const location = {href: new URL(url, 'https://laundry.test').toString()};
    const select = {
        value: filter,
        dataset: {activeFilter: filter},
        addEventListener: (name, callback) => { selectEvents[name] = callback; },
    };
    const form = {
        requestSubmit: () => submissions.push(select.value),
    };

    vm.runInNewContext(source, {
        URL,
        document: {
            getElementById: id => hasFilter ? ({
                'period-filter-select': select,
                'period-filter-form': form,
            })[id] ?? null : null,
        },
        window: {
            location,
            history: {
                replaceState: (state, ignored, nextUrl) => {
                    historyCalls.push(nextUrl);
                    location.href = nextUrl;
                },
            },
            addEventListener: (name, callback) => { windowEvents[name] = callback; },
        },
        fetch: () => assert.fail('Filtering must navigate the whole Dashboard, not fetch a chart alone.'),
    });

    return {select, selectEvents, windowEvents, submissions, historyCalls, location};
}

test('each period submits the global GET form without requiring a revenue chart', () => {
    for (const filter of ['today', 'this_week', 'this_month', 'all_time']) {
        const page = harness({filter: filter === 'this_week' ? 'today' : 'this_week'});
        page.select.value = filter;
        page.selectEvents.change();
        assert.deepEqual(page.submissions, [filter]);
        assert.equal(page.historyCalls.length, 0);
    }
});

test('loading a valid period URL uses server data without extra requests or navigation', () => {
    for (const filter of ['today', 'this_week', 'this_month', 'all_time']) {
        const page = harness({url: `/admin/dashboard?filter=${filter}`, filter});
        assert.deepEqual(page.submissions, []);
        assert.equal(page.historyCalls.length, 0);
        assert.equal(page.select.value, filter);
    }
});

test('Back and Forward restore the selection matching the cached page metrics', () => {
    for (const filter of ['today', 'this_week', 'this_month', 'all_time']) {
        const page = harness({filter});
        page.select.value = filter === 'today' ? 'all_time' : 'today';
        page.windowEvents.pageshow();
        assert.equal(page.select.value, filter);
        assert.deepEqual(page.submissions, []);
        assert.equal(page.historyCalls.length, 0);
    }
});

test('invalid, array and duplicate query filters normalize to the server-rendered period', () => {
    for (const query of ['filter=invalid', 'filter[]=today', 'filter=today&filter=all_time', 'filter=today&filter[x]=all_time']) {
        const page = harness({url: `/admin/dashboard?${query}&other=keep#charts`});
        const url = new URL(page.location.href);
        assert.deepEqual(url.searchParams.getAll('filter'), ['this_week']);
        assert.equal([...url.searchParams.keys()].some(key => key.startsWith('filter[')), false);
        assert.equal(url.searchParams.get('other'), 'keep');
        assert.equal(url.hash, '#charts');
        assert.equal(page.historyCalls.length, 1);
        assert.deepEqual(page.submissions, []);
    }
});

test('default page and unchanged selection do not create extra history or submit', () => {
    const page = harness();
    page.selectEvents.change();
    assert.deepEqual(page.submissions, []);
    assert.deepEqual(page.historyCalls, []);
    assert.equal(page.select.value, 'this_week');
});

test('unsupported selections restore the active period without navigating', () => {
    const page = harness();
    page.select.value = 'this_year';
    page.selectEvents.change();
    assert.equal(page.select.value, 'this_week');
    assert.deepEqual(page.submissions, []);
});

test('pages without the period filter do not install event handlers', () => {
    const page = harness({hasFilter: false});
    assert.deepEqual(page.selectEvents, {});
    assert.deepEqual(page.windowEvents, {});
});
