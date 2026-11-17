import test from 'node:test';
import assert from 'node:assert/strict';
import {preserveSidebarScroll} from '../../resources/js/helpers/sidebar-scroll.js';

function fixture(storage = new Map(), userId = '7', enabled = () => true) {
    const callbacks = {};
    const groups = ['Students', 'Attendance'].map((label) => ({
        scrollTop: 0,
        closest: () => ({querySelector: () => ({textContent: label})}),
    }));
    const menu = {scrollTop: 0, querySelectorAll: () => groups};
    const sidebar = {dataset: {userId}, addEventListener: (type, callback) => {callbacks[type] = callback;}};
    const events = {addEventListener: (type, callback) => {callbacks[type] = callback;}};
    const api = preserveSidebarScroll(sidebar, menu, {
        enabled, events,
        storage: {getItem: (key) => storage.get(key), setItem: (key, value) => storage.set(key, value)},
    });
    return {api, callbacks, menu, groups, storage};
}

test('submenu navigation restores both sidebar and inner submenu positions on the next page', () => {
    const first = fixture();
    first.menu.scrollTop = 410;
    first.groups[0].scrollTop = 275;
    first.callbacks.click({target: {closest: () => ({getAttribute: () => '/students/enrollment'})}});
    const next = fixture(first.storage);
    assert.equal(next.menu.scrollTop, 410);
    assert.equal(next.groups[0].scrollTop, 275);
    assert.equal(next.groups[1].scrollTop, 0);
});

test('reload and browser history restore navigation without changing document scroll', () => {
    const page = fixture();
    page.menu.scrollTop = 180;
    page.callbacks.pagehide();
    page.menu.scrollTop = 0;
    page.callbacks.pageshow();
    assert.equal(page.menu.scrollTop, 180);
});

test('mobile collapse and parent toggles do not replace saved desktop navigation state', () => {
    const desktop = fixture();
    desktop.menu.scrollTop = 190;
    desktop.api.save();
    desktop.menu.scrollTop = 0;
    desktop.callbacks.click({target: {closest: () => ({getAttribute: () => '#'})}});
    const mobile = fixture(desktop.storage, '7', () => false);
    mobile.callbacks.pagehide();
    assert.equal(fixture(desktop.storage).menu.scrollTop, 190);
});

test('saved positions are separated by user and corrupt values are ignored', () => {
    const page = fixture();
    page.menu.scrollTop = 70;
    page.api.save();
    assert.equal(fixture(page.storage, '8').menu.scrollTop, 0);
    page.storage.set('schoolSidebarScroll:7', '{broken');
    assert.doesNotThrow(() => page.api.restore());
    page.storage.set('schoolSidebarScroll:7', JSON.stringify({main: -20, 'group:Students': '500'}));
    const next = fixture(page.storage);
    assert.equal(next.menu.scrollTop, 0);
    assert.equal(next.groups[0].scrollTop, 0);
});

test('blocked session storage does not prevent navigation setup or saving', () => {
    const blocked = {get() {throw Error('blocked');}, set() {throw Error('blocked');}};
    const page = fixture(blocked);
    assert.doesNotThrow(() => page.api.save());
});
