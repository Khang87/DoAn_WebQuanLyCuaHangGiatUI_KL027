import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/admin-period-filter.js', import.meta.url), 'utf8');

function harness({
    url = '/admin/dashboard',
    filter = 'this_week',
    hasFilter = true,
    customRange = false,
    dateFrom = '',
    dateTo = '',
} = {}) {
    const selectEvents = {};
    const windowEvents = {};
    const submissions = [];
    const historyCalls = [];
    const location = {href: new URL(url, 'https://laundry.test').toString()};
    const select = {
        value: filter,
        dataset: {activeFilter: filter, allowCustom: String(customRange)},
        addEventListener: (name, callback) => { selectEvents[name] = callback; },
    };
    const dateEvents = {};
    const dateRangeFields = {
        classList: {
            contains: name => dateRangeFields.hidden && name === 'd-none',
            toggle: (name, hidden) => { dateRangeFields.hidden = name === 'd-none' && hidden; },
        },
        setAttribute: (name, value) => { dateRangeFields[name] = value; },
        hidden: filter !== 'all_time',
    };
    const fromInput = {
        value: dateFrom,
        dataset: {activeDate: dateFrom},
        addEventListener: (name, callback) => { dateEvents[`from:${name}`] = callback; },
    };
    const toInput = {
        value: dateTo,
        dataset: {activeDate: dateTo},
        addEventListener: (name, callback) => { dateEvents[`to:${name}`] = callback; },
    };
    const applyButton = {disabled: true};
    const form = {
        requestSubmit: () => submissions.push(select.value),
    };

    vm.runInNewContext(source, {
        URL,
        document: {
            getElementById: id => hasFilter ? ({
                'period-filter-select': select,
                'period-filter-form': form,
                ...(customRange ? {
                    'period-date-range-fields': dateRangeFields,
                    'period-date-from': fromInput,
                    'period-date-to': toInput,
                    'period-filter-apply': applyButton,
                } : {}),
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

    return {
        select,
        selectEvents,
        windowEvents,
        submissions,
        historyCalls,
        location,
        dateEvents,
        dateRangeFields,
        fromInput,
        toInput,
        applyButton,
    };
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

test('all-time date range controls are optional and apply only when both dates are valid', () => {
    const page = harness({url: '/reports', filter: 'all_time', customRange: true});

    assert.equal(page.dateRangeFields.hidden, false);
    assert.equal(page.dateRangeFields['aria-hidden'], 'false');
    assert.equal(page.fromInput.disabled, false);
    assert.equal(page.fromInput.required, false);
    assert.equal(page.applyButton.disabled, true);
    assert.deepEqual(page.submissions, []);

    page.fromInput.value = '2026-01-10';
    page.dateEvents['from:input']();
    assert.equal(page.applyButton.disabled, true);

    page.toInput.value = '2026-01-09';
    page.dateEvents['to:input']();
    assert.equal(page.applyButton.disabled, true);

    page.toInput.value = '2026-01-10';
    page.dateEvents['to:input']();
    assert.equal(page.applyButton.disabled, false);
});

test('all-time with no dates means all data and changing period disables the date inputs', () => {
    const page = harness({url: '/reports?filter=all_time', filter: 'all_time', customRange: true});
    assert.equal(page.dateRangeFields.hidden, false);
    assert.equal(page.applyButton.disabled, true);

    page.select.value = 'this_month';
    page.selectEvents.change();

    assert.equal(page.dateRangeFields.hidden, true);
    assert.equal(page.fromInput.disabled, true);
    assert.equal(page.toInput.disabled, true);
    assert.deepEqual(page.submissions, ['this_month']);
});

test('Back and Forward restore the dates matching the rendered all-time report', () => {
    const page = harness({filter: 'all_time', customRange: true, dateFrom: '2026-01-01', dateTo: '2026-01-10'});
    page.select.value = 'this_month';
    page.fromInput.value = '2026-02-01';
    page.toInput.value = '2026-02-10';
    page.windowEvents.pageshow();

    assert.equal(page.select.value, 'all_time');
    assert.equal(page.fromInput.value, '2026-01-01');
    assert.equal(page.toInput.value, '2026-01-10');
});
