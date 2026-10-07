import test from 'node:test';
import assert from 'node:assert/strict';
import { createEnrollmentFamilyOptionsLoader } from '../../resources/js/helpers/enrollment-family-options.js';

const response = (data) => ({ ok: true, json: async () => data });
const family = { families: [{ family_number: 'F001', full_name_en: 'SAMPLE STUDENT' }], hasMore: true };

test('preloading and opening the picker share one small request and reuse its result', async () => {
    let calls = 0;
    const loader = createEnrollmentFamilyOptionsLoader(async () => { calls++; return response(family); });
    const preload = loader.load();
    assert.equal(loader.load(), preload);
    assert.deepEqual(await preload, family);
    assert.deepEqual(await loader.load(), family);
    assert.equal(calls, 1);
});

test('searches on the server by query and reuses matching searches', async () => {
    const calls = [];
    const loader = createEnrollmentFamilyOptionsLoader(async (url, options) => {
        calls.push([url, options.headers.Accept]);
        return response(family);
    });
    await loader.load(' SIBLING & NAME ');
    await loader.load('SIBLING & NAME');
    assert.deepEqual(calls, [['/student-enrollments/quick-options?q=SIBLING%20%26%20NAME', 'application/json']]);
});

test('a failed request can be retried instead of leaving the mobile picker stuck', async () => {
    let calls = 0;
    const loader = createEnrollmentFamilyOptionsLoader(async () => {
        calls++;
        if (calls === 1) throw new Error('Network interrupted');
        return response(family);
    });
    await assert.rejects(loader.load(), /Network interrupted/);
    assert.deepEqual(await loader.load(), family);
    assert.equal(calls, 2);
});

test('rejects invalid or failed server responses and retries them', async () => {
    const responses = [{ ok: false }, response({}), response(family)];
    const loader = createEnrollmentFamilyOptionsLoader(async () => responses.shift());
    await assert.rejects(loader.load(), /Unable to load families/);
    await assert.rejects(loader.load(), /Unable to load families/);
    assert.deepEqual(await loader.load(), family);
});

test('clearing cached searches after saving fetches newly added families', async () => {
    let calls = 0;
    const loader = createEnrollmentFamilyOptionsLoader(async () => { calls++; return response(family); });
    await loader.load();
    loader.clear();
    await loader.load();
    assert.equal(calls, 2);
});
