import assert from 'node:assert/strict';
import {test} from 'node:test';
import {setupG9SettingsNavigation} from '../../resources/js/helpers/g9SettingsNavigation.js';

class Element {
    constructor(dataset = {}) { this.dataset = dataset; this.attrs = {}; this.events = {}; this.hidden = false; this.value = ''; }
    setAttribute(key, value) { this.attrs[key] = value; }
    getAttribute(key) { return this.attrs[key]; }
    addEventListener(key, handler) { this.events[key] = handler; }
    focus() { this.focused = true; }
    emit(key, event = {}) { this.events[key]?.(event); }
}
function page(isMobile = false) {
    const toggle = new Element(), body = new Element(), form = new Element(), icon = {};
    const tabs = ['center', 'dates', 'campus'].map(key => new Element({settingsTab: key}));
    const panels = ['center', 'dates', 'campus'].map(key => new Element({settingsPanel: key}));
    const classes = new Set();
    const media = new Element(); media.matches = isMobile;
    globalThis.window = {matchMedia: () => media};
    toggle.querySelector = () => icon;
    const root = {
        querySelector: selector => ({'[data-settings-nav-toggle]': toggle, '[data-settings-nav-body]': body, '[data-date-settings]': form}[selector]),
        querySelectorAll: selector => selector === '[data-settings-tab]' ? tabs : panels,
        classList: {toggle: (name, enabled) => enabled ? classes.add(name) : classes.delete(name)},
    };
    let changes = 0;
    return {toggle, body, form, tabs, panels, media, classes, get changes() { return changes; }, navigation: setupG9SettingsNavigation(root, () => changes++)};
}

test('desktop navigation starts expanded and switching tabs preserves entered values', () => {
    const pageState = page();
    assert.equal(pageState.toggle.getAttribute('aria-expanded'), 'true');
    assert.equal(pageState.body.hidden, false);
    pageState.panels[0].value = 'Unsaved province';
    pageState.tabs[1].emit('click');
    assert.equal(pageState.panels[0].hidden, true);
    assert.equal(pageState.panels[1].hidden, false);
    assert.equal(pageState.tabs[1].getAttribute('aria-selected'), 'true');
    assert.equal(pageState.tabs[1].tabIndex, 0);
    assert.equal(pageState.tabs[0].tabIndex, -1);
    pageState.tabs[2].emit('click');
    assert.equal(pageState.form.hidden, true);
    assert.equal(pageState.panels[2].hidden, false);
    pageState.tabs[0].emit('click');
    assert.equal(pageState.form.hidden, false);
    assert.equal(pageState.panels[0].value, 'Unsaved province');
});

test('phone navigation starts collapsed, opens on request and collapses after selection', () => {
    const pageState = page(true);
    assert.equal(pageState.body.hidden, true);
    assert.equal(pageState.toggle.getAttribute('aria-label'), 'Expand settings navigation');
    pageState.toggle.emit('click');
    assert.equal(pageState.body.hidden, false);
    pageState.tabs[1].emit('click');
    assert.equal(pageState.panels[1].hidden, false);
    assert.equal(pageState.body.hidden, true);
    assert.equal(pageState.toggle.focused, true);
    pageState.media.matches = false;
    pageState.media.emit('change');
    assert.equal(pageState.body.hidden, false);
    assert.equal(pageState.classes.has('is-nav-collapsed'), false);
});

test('keyboard arrows wrap between tabs and Home / End select first / last', () => {
    const pageState = page(); let prevented = 0;
    pageState.tabs[0].emit('keydown', {key: 'ArrowUp', preventDefault() { prevented++; }});
    assert.equal(pageState.tabs[2].focused, true);
    assert.equal(pageState.form.hidden, true);
    pageState.tabs[2].emit('keydown', {key: 'Home', preventDefault() { prevented++; }});
    assert.equal(pageState.panels[0].hidden, false);
    pageState.tabs[0].emit('keydown', {key: 'End', preventDefault() { prevented++; }});
    assert.equal(pageState.panels[2].hidden, false);
    assert.equal(prevented, 3);
});

test('validation can reveal the dates panel while navigation stays collapsed', () => {
    const pageState = page(true);
    pageState.navigation.activate('dates');
    assert.equal(pageState.changes, 2);
    assert.equal(pageState.form.hidden, false);
    assert.equal(pageState.panels[1].hidden, false);
    assert.equal(pageState.body.hidden, true);
    pageState.navigation.activate('unknown');
    assert.equal(pageState.changes, 2);
    assert.equal(pageState.panels[1].hidden, false);
});
