import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const blade = readFileSync(new URL('../../resources/views/admin/reports/index.blade.php', import.meta.url), 'utf8');
const plain = value => JSON.parse(JSON.stringify(value));

function harness({ labels = ['10/01', '11/01', '12/01', '13/01'], revenue = [255000, '400000', 100000, 0], hasRevenue = true } = {}) {
    const charts = [];
    const listeners = {};
    const selectMarkup = blade.match(/<select id="chartType"[\s\S]*?<\/select>/)[0];
    const select = {
        value: selectMarkup.match(/<option value="([^"]+)" selected>/)?.[1],
        addEventListener: (event, handler) => { listeners[event] = handler; },
    };
    const elements = { chartType: select, revenueChart: hasRevenue ? { id: 'revenue' } : null, compositionChart: { id: 'composition' } };
    class ApexCharts {
        constructor(element, options) { this.element = element; this.options = options; charts.push(this); }
        render() { this.rendered = true; }
        destroy() { this.destroyed = true; this.options.xaxis?.categories.splice(0); }
    }
    const data = { labels, revenue, orders: [999, 999, 999, 999] };
    const composition = { labels: ['Giặt', 'Ủi'], revenue: [255000, 5000] };
    const script = blade.match(/<script>\s*([\s\S]*?)<\/script>/)[1]
        .replace(/@json\(\$chartData\['(\w+)'\] \?\? \[\]\)/g, (_, key) => JSON.stringify(data[key]))
        .replace(/@json\(\$compositionData\['(\w+)'\] \?\? \[\]\)/g, (_, key) => JSON.stringify(composition[key]));
    vm.runInNewContext(script, {
        document: { getElementById: id => elements[id] ?? null },
        window: { ApexCharts }, ApexCharts, Intl, console,
    });
    return { charts, select, change: () => listeners.change(), revenueCharts: () => charts.filter(chart => chart.element.id === 'revenue') };
}

test('revenue trend defaults to one money series with straight daily connections and visible points', () => {
    const h = harness();
    const chart = h.revenueCharts()[0];
    const options = chart.options;
    assert.equal(h.select.value, 'line');
    assert.equal(options.chart.type, 'line');
    assert.deepEqual(plain(options.series), [{ name: 'Doanh thu', data: [255000, 400000, 100000, 0] }]);
    assert.deepEqual(plain(options.xaxis.categories), ['10/01', '11/01', '12/01', '13/01']);
    assert.equal(options.xaxis.title.text, 'Thời gian');
    assert.equal(Array.isArray(options.yaxis), false);
    assert.equal(options.yaxis.title.text, 'Doanh thu (VNĐ)');
    assert.equal(options.yaxis.min, 0);
    assert.equal(options.yaxis.labels.formatter(400000), '400.000');
    assert.equal(options.tooltip.y.formatter(400000), '400.000 VNĐ');
    assert.equal(options.tooltip.y.formatter(0), '0 VNĐ');
    assert.equal(options.stroke.curve, 'straight');
    assert.ok(options.markers.size > 0);
    assert.equal(chart.rendered, true);
});

test('switching line and bar preserves revenue and dates without adding order counts', () => {
    const h = harness();
    const initial = h.revenueCharts()[0];
    const dates = plain(initial.options.xaxis.categories);
    h.select.value = 'bar';
    h.change();
    const bar = h.revenueCharts()[1];
    assert.equal(initial.destroyed, true);
    assert.equal(bar.options.chart.type, 'bar');
    assert.deepEqual(plain(bar.options.series), plain(initial.options.series));
    assert.deepEqual(plain(bar.options.xaxis.categories), dates);
    assert.equal(bar.options.tooltip.y.formatter(255000), '255.000 VNĐ');
    h.select.value = 'line';
    h.change();
    assert.equal(bar.destroyed, true);
    assert.equal(h.revenueCharts()[2].options.chart.type, 'line');
    assert.deepEqual(plain(h.revenueCharts()[2].options.xaxis.categories), dates);
    assert.equal(h.charts.filter(chart => chart.element.id === 'composition').length, 1);
});

test('a single-day revenue point remains visible', () => {
    const options = harness({ labels: ['10/01'], revenue: [255000] }).revenueCharts()[0].options;
    assert.deepEqual(plain(options.series[0].data), [255000]);
    assert.deepEqual(plain(options.xaxis.categories), ['10/01']);
    assert.ok(options.markers.size > 0);
});

test('empty revenue state does not create a chart or fail when changing chart type', () => {
    const h = harness({ hasRevenue: false, labels: [], revenue: [] });
    assert.equal(h.revenueCharts().length, 0);
    h.select.value = 'bar';
    assert.doesNotThrow(h.change);
    assert.equal(h.revenueCharts().length, 0);
});

test('service composition keeps its donut and small-slice labels', () => {
    const options = harness().charts.find(chart => chart.element.id === 'composition').options;
    assert.equal(options.chart.type, 'donut');
    assert.equal(options.chart.height, 380);
    assert.equal(options.plotOptions.pie.dataLabels.minAngleToShowLabel, 0);
    assert.equal(options.dataLabels.formatter(1.9, { seriesIndex: 1 }), '1.9%');
});
