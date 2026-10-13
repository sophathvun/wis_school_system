import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/staffManagement.js', import.meta.url), 'utf8')
    .replace(/^import .*;\r?\n/gm, '');

function setup(confirm) {
    const handlers = {};
    const navigations = [];
    const submissions = [];
    const notices = [];
    const edit = { dataset: { staffEdit: 'Test Staff' }, href: '/hrm/staff-management?edit=42', addEventListener: (event, fn) => handlers.edit = fn };
    const deletion = { dataset: { staffDelete: 'Test Staff' }, addEventListener: (event, fn) => handlers.delete = fn };
    const document = {
        addEventListener: (event, fn) => fn(),
        getElementById: () => null,
        querySelector: selector => selector === '[data-staff-success]' ? { dataset: { staffSuccess: 'Staff profile updated successfully.' } } : null,
        querySelectorAll: selector => selector === '[data-staff-edit]' ? [edit] : selector === '[data-staff-delete]' ? [deletion] : [],
    };
    vm.runInNewContext(source, {
        document,
        window: { innerWidth: 1440, addEventListener: () => {}, location: { assign: href => navigations.push(href) } },
        HTMLFormElement: { prototype: { submit() { submissions.push(this); } } },
        showConfirm: confirm,
        showSuccess: (...args) => notices.push(args),
        showError: () => {},
    });
    const event = () => ({ prevented: false, preventDefault() { this.prevented = true; } });
    return { handlers, navigations, submissions, notices, deletion, event };
}

test('cancelled edit and delete do not navigate or submit; confirmed actions do', async () => {
    let confirmed = false;
    const page = setup(async () => ({ isConfirmed: confirmed }));
    for (const action of ['edit', 'delete']) {
        const event = page.event();
        await page.handlers[action](event);
        assert.equal(event.prevented, true);
    }
    assert.equal(page.navigations.length, 0);
    assert.equal(page.submissions.length, 0);
    confirmed = true;
    await page.handlers.edit(page.event());
    await page.handlers.delete(page.event());
    assert.deepEqual(page.navigations, ['/hrm/staff-management?edit=42']);
    assert.deepEqual(page.submissions, [page.deletion]);
    assert.deepEqual(page.notices, [['Success', 'Staff profile updated successfully.']]);
});

test('repeated delete clicks while confirmation is open submit only once', async () => {
    let resolve;
    let confirmations = 0;
    const page = setup(() => {
        confirmations++;
        return new Promise(done => { resolve = done; });
    });
    const first = page.handlers.delete(page.event());
    await page.handlers.delete(page.event());
    assert.equal(confirmations, 1);
    assert.equal(page.submissions.length, 0);
    resolve({ isConfirmed: true });
    await first;
    assert.equal(page.submissions.length, 1);
});
