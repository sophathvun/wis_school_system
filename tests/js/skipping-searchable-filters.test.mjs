import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const pageSource = readFileSync(new URL('../../resources/js/studentSkippingGrade.js', import.meta.url), 'utf8');
const source = pageSource.slice(
    pageSource.indexOf('    const searchableFilters = new Map();'),
    pageSource.indexOf("    if (root.querySelector('.skipping-request-report-filters'))"),
);

class Element extends EventTarget {
    children = [];
    attributes = {};
    style = {};
    value = '';
    focusCount = 0;
    classList = { toggle() {} };
    setAttribute(name, value) { this.attributes[name] = value; }
    append(...children) {
        children.forEach(child => { child.parentElement = this; this.children.push(child); });
    }
    after(sibling) { this.parentElement.append(sibling); }
    replaceChildren() { this.children = []; }
    get childElementCount() { return this.children.length; }
    querySelector() { return null; }
    contains(target) { return this === target || this.children.some(child => child.contains(target)); }
    closest(selector) {
        return selector === '.skipping-searchable' && this.className === 'skipping-searchable'
            ? this : this.parentElement?.closest(selector);
    }
    focus() { this.focusCount++; }
    hasAttribute(name) { return name in this.attributes; }
}

function setup(touch = true) {
    const field = new Element();
    const select = new Element();
    select.dataset = { skippingSearchable: 'Campus' };
    select.options = [
        { value: '', textContent: 'All Campuses', dataset: {} },
        { value: '5', textContent: 'BCH', dataset: {} },
    ];
    Object.defineProperty(select, 'selectedOptions', { get: () => select.options.filter(option => option.value === select.value) });
    field.append(select);
    const document = new Element();
    document.createElement = () => new Element();
    const root = { querySelector: () => null, querySelectorAll: () => [select] };
    vm.runInNewContext(source, {
        root, document, studentFilter: null, Event,
        window: { matchMedia: () => ({ matches: touch }) },
    });
    const wrapper = field.children[1];
    const [toggle, menu] = wrapper.children;
    const [input, results] = menu.children;
    const blurTo = target => {
        const event = new Event('focusout');
        Object.defineProperty(event, 'relatedTarget', { value: target });
        wrapper.dispatchEvent(event);
    };
    const outsideClick = () => {
        const event = new Event('click');
        Object.defineProperty(event, 'target', { value: new Element() });
        document.dispatchEvent(event);
    };
    return { select, toggle, menu, input, results, blurTo, outsideClick };
}

test('Safari blur with no next focused element keeps options available for a tap', () => {
    const page = setup();
    let changes = 0;
    page.select.addEventListener('change', () => changes++);
    page.toggle.dispatchEvent(new Event('click'));
    assert.equal(page.menu.hidden, false);
    assert.equal(page.input.focusCount, 0, 'touch opening does not force the keyboard open');
    page.input.focus();
    page.blurTo(null);
    assert.equal(page.menu.hidden, false, 'blur before the option click must not hide it');
    page.results.children[1].dispatchEvent(new Event('click'));
    assert.equal(page.select.value, '5');
    assert.equal(changes, 1);
    assert.equal(page.menu.hidden, true);
    assert.equal(page.toggle.textContent, 'BCH');
});

test('desktop search focus, keyboard navigation, and outside dismissal remain available', () => {
    const page = setup(false);
    page.toggle.dispatchEvent(new Event('click'));
    assert.equal(page.input.focusCount, 1);
    page.blurTo(page.results.children[0]);
    assert.equal(page.menu.hidden, false);
    page.blurTo(new Element());
    assert.equal(page.menu.hidden, true);
    page.toggle.dispatchEvent(new Event('click'));
    page.blurTo(null);
    page.outsideClick();
    assert.equal(page.menu.hidden, true);
});
