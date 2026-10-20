import test from 'node:test';
import assert from 'node:assert/strict';
import { setupEnrollmentSearch } from '../../resources/js/helpers/enrollment-search.js';

function fixture(t) {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const handlers = {}, runs = [], errors = [];
    let cancelled = 0;
    const input = { value: '', addEventListener: (name, fn) => handlers[name] = fn };
    setupEnrollmentSearch({ input, run: () => runs.push(input.value), cancelPending: () => cancelled++, onError: error => errors.push(error) });
    return { input, handlers, runs, errors, cancelled: () => cancelled };
}

test('typing a full student name starts only one search after the typing pause', async t => {
    const page = fixture(t);
    for (const char of 'VUN SOMALIZA') {
        page.input.value += char; page.handlers.input(); t.mock.timers.tick(80);
    }
    await Promise.resolve(); assert.equal(page.runs.length, 0);
    t.mock.timers.tick(300); await Promise.resolve();
    assert.deepEqual(page.runs, ['VUN SOMALIZA']);
    assert.equal(page.cancelled(), 12);
});

test('Enter runs the pending search immediately without a duplicate request later', async t => {
    const page = fixture(t); page.input.value = 'VUN SOMALIZA'; page.handlers.input();
    let prevented = false;
    page.handlers.keydown({ key: 'Enter', preventDefault() { prevented = true; } });
    await Promise.resolve(); assert.equal(prevented, true);
    assert.deepEqual(page.runs, ['VUN SOMALIZA']);
    t.mock.timers.tick(500); await Promise.resolve(); assert.equal(page.runs.length, 1);
});

test('clearing the search cancels the old term and restores the default list immediately', async t => {
    const page = fixture(t); page.input.value = 'VUN'; page.handlers.input();
    page.input.value = ''; page.handlers.input(); await Promise.resolve();
    assert.deepEqual(page.runs, ['']);
    t.mock.timers.tick(500); await Promise.resolve(); assert.equal(page.runs.length, 1);
});
