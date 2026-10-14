import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/webPush.js', import.meta.url), 'utf8')
    .replace(/^import .*;\r?\n/gm, '').replace('export function', 'function');

function setup({ permission = 'default', configured = true, iosBrowser = false, saveOk = true, previousKey = null, testOk = true } = {}) {
    const button = new EventTarget(), testButton = new EventTarget(), dialogs = [], requests = [], events = [];
    let requested = false, subscribed = 0, unsubscribed = 0;
    const subscription = { toJSON: () => ({ endpoint: 'https://push.example.test/device' }) };
    const existing = previousKey ? { ...subscription, options: { applicationServerKey: Uint8Array.from(previousKey).buffer }, unsubscribe: async () => { unsubscribed++; return true; } } : null;
    const registration = { pushManager: {
        getSubscription: async () => existing,
        subscribe: async () => { subscribed++; return subscription; },
    } };
    class Notification {
        static permission = permission;
        static async requestPermission() { requested = true; this.permission = 'granted'; return 'granted'; }
    }
    const window = {
        Notification, atob: value => Buffer.from(value, 'base64').toString('binary'),
        dispatchEvent: event => events.push(event.type),
    };
    if (!iosBrowser) window.PushManager = {};
    vm.runInNewContext(source + '\nsetupWebPush();', {
        Event, Uint8Array, Notification, window,
        navigator: { serviceWorker: { register: async () => ({}), ready: Promise.resolve(registration) } },
        document: {
            body: { dataset: { webPushPublicKey: configured ? 'YWJj' : '' } },
            querySelectorAll: selector => selector === '[data-enable-phone-notifications]' ? [button] : [testButton], querySelector: () => ({ content: 'csrf' }),
        },
        isIosDevice: () => iosBrowser,
        initPhoneNotificationOnboarding() {},
        Swal: { fire: async options => dialogs.push(options) },
        fetch: async (url, options) => { requests.push({ url, options }); return url === '/push/test' ? { ok: testOk, json: async () => ({ ok: testOk, message: 'Push rejected (HTTP 403)' }) } : { ok: saveOk }; },
    });
    return { button, testButton, dialogs, requests, events, requested: () => requested, subscribed: () => subscribed, unsubscribed: () => unsubscribed,
        settle: () => new Promise(resolve => setImmediate(resolve)) };
}

test('explicit enable requests permission from the tap and persists a device subscription', async () => {
    const app = setup();
    app.button.dispatchEvent(new Event('click'));
    assert.equal(app.requested(), true);
    await app.settle();
    assert.equal(app.requests.length, 1);
    assert.equal(app.requests[0].url, '/push/subscriptions');
    assert.equal(app.requests[0].options.headers['X-CSRF-TOKEN'], 'csrf');
    assert.equal(app.dialogs[0].icon, 'success');
    assert.deepEqual(app.events, ['school:notifications-enabled']);
});

test('ordinary iPhone browser tabs show Home Screen guidance instead of pretending to enable push', async () => {
    const app = setup({ iosBrowser: true });
    app.button.dispatchEvent(new Event('click'));
    await app.settle();
    assert.match(app.dialogs[0].text, /Add to Home Screen/);
    assert.equal(app.requests.length, 0);
});

test('blocked permissions and missing server configuration provide actionable guidance', async () => {
    for (const [options, expected] of [[{ permission: 'denied' }, /phone or browser settings/], [{ configured: false }, /server/]]) {
        const app = setup(options);
        app.button.dispatchEvent(new Event('click'));
        await app.settle();
        assert.match(app.dialogs[0].text, expected);
        assert.equal(app.requests.length, 0);
    }
});

test('subscription save failure never displays a success message and enables retry', async () => {
    const app = setup({ saveOk: false });
    app.button.dispatchEvent(new Event('click'));
    await app.settle();
    assert.equal(app.dialogs[0].icon, 'info');
    assert.match(app.dialogs[0].text, /Unable to save/);
    assert.equal(app.button.disabled, false);
    assert.deepEqual(app.events, []);
});

test('a subscription with an old server key is renewed while a matching subscription is retained', async () => {
    for (const [key, renew] of [[[1, 2, 3], 1], [[97, 98, 99], 0]]) {
        const app = setup({ previousKey: key });
        app.button.dispatchEvent(new Event('click'));
        await app.settle();
        assert.equal(app.unsubscribed(), renew);
        assert.equal(app.subscribed(), renew);
    }
});

test('device test registers this device and reports push acceptance separately from phone delivery', async () => {
    const app = setup();
    app.testButton.dispatchEvent(new Event('click'));
    await app.settle();
    assert.deepEqual(app.requests.map(request => request.url), ['/push/subscriptions', '/push/test']);
    assert.equal(JSON.parse(app.requests[1].options.body).endpoint, 'https://push.example.test/device');
    assert.equal(app.dialogs[0].title, 'Push service accepted the test');
    assert.match(app.dialogs[0].text, /has not reached your phone yet/);
});

test('device test surfaces a server push rejection without claiming success', async () => {
    const app = setup({ testOk: false });
    app.testButton.dispatchEvent(new Event('click'));
    await app.settle();
    assert.equal(app.dialogs[0].icon, 'error');
    assert.match(app.dialogs[0].text, /403/);
    assert.equal(app.testButton.disabled, false);
});
