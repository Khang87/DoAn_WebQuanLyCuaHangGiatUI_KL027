import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/assets/js/flatpickr-month-fix.js', import.meta.url), 'utf8');

function harness(initialCalendars = []) {
    let ready;
    let observe;
    const styles = new Map();
    const appendedStyles = [];
    const document = {
        body: {},
        head: {
            appendChild: style => {
                appendedStyles.push(style);
                styles.set(style.id, style);
            },
        },
        addEventListener: (event, callback) => {
            if (event === 'DOMContentLoaded') {
                ready = callback;
            }
        },
        createElement: () => ({style: {}}),
        getElementById: id => styles.get(id) ?? null,
        querySelectorAll: selector => selector === '.flatpickr-calendar' ? initialCalendars : [],
    };
    class MutationObserver {
        constructor(callback) {
            this.callback = callback;
        }

        observe(target, options) {
            observe = {target, options, callback: this.callback};
        }
    }

    vm.runInNewContext(source, {
        document,
        MutationObserver,
        Node: {ELEMENT_NODE: 1},
        setTimeout: callback => callback(),
    });

    return {
        appendedStyles,
        document,
        observe: () => observe,
        ready: () => ready(),
    };
}

function calendarFixture() {
    const arrows = [{style: {}}];
    const monthSelect = {style: {}, parentElement: {style: {}}};
    const yearSelect = {style: {}};
    const currentMonth = {
        querySelectorAll: selector => selector === '.arrowUp, .arrowDown' ? arrows : [],
        querySelector: selector => ({
            '.flatpickr-monthDropdown-months': monthSelect,
            '.cur-year': yearSelect,
        })[selector] ?? null,
    };
    const calendar = {
        nodeType: 1,
        matches: selector => selector === '.flatpickr-calendar',
        querySelector: selector => selector === '.flatpickr-current-month' ? currentMonth : null,
        querySelectorAll: () => [],
    };

    return {arrows, calendar, monthSelect, yearSelect};
}

test('styles existing and dynamically created Flatpickr calendars without runtime errors', () => {
    const existing = calendarFixture();
    const page = harness([existing.calendar]);

    assert.doesNotThrow(page.ready);
    assert.equal(existing.arrows[0].style.display, 'none');
    assert.equal(existing.monthSelect.style.minWidth, '70px');
    assert.equal(existing.yearSelect.style.fontSize, '1rem');
    assert.equal(page.appendedStyles.length, 1);
    assert.equal(page.observe().options.childList, true);
    assert.equal(page.observe().options.subtree, true);

    const added = calendarFixture();
    assert.doesNotThrow(() => page.observe().callback([{
        addedNodes: [added.calendar],
    }]));
    assert.equal(added.monthSelect.style.minWidth, '70px');
    assert.equal(page.appendedStyles.length, 1);
});
