import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/phoneNotificationOnboarding.js', import.meta.url), 'utf8')
    .replace(/^import .*;\r?\n/gm, '').replace('export function', 'function');

function setup({ ios = false, android = true, installed = true, permission = 'default', signedIn = true, configured = true,
    supported = true, storage = new Map(), now = 1000000000, otherDialog = false, hidden = false, fail = false } = {}) {
    const window = new EventTarget(), document = new EventTarget(), mode = new EventTarget();
    const dialogs = [], validation = [], timers = new Map(), enabledEvents = [], confirmButton = new EventTarget();
    let timerId = 0, requests = 0, resolveDialog, tapping = false, gesturePreserved = false;
    window.navigator = { standalone: ios && installed };
    mode.matches = installed;
    window.matchMedia = () => mode;
    window.localStorage = { getItem: key => storage.get(key) || null, setItem: (key, value) => storage.set(key, value) };
    window.setTimeout = callback => { const id = ++timerId; timers.set(id, callback); return id; };
    window.clearTimeout = id => timers.delete(id);
    if (supported) { window.Notification = {}; window.PushManager = {}; }
    window.addEventListener('school:notifications-enabled', event => enabledEvents.push(event.type));
    document.body = { dataset: { currentUserId: signedIn ? '42' : '', webPushPublicKey: configured ? 'public-key' : '' } };
    document.visibilityState = hidden ? 'hidden' : 'visible';
    const state = { otherDialog };
    vm.runInNewContext(source + '\ninitPhoneNotificationOnboarding(enableNotifications);', {
        window, document, Event, Notification: { permission }, Date: { now: () => now },
        navigator: { userAgent: android ? 'Android Chrome' : ios ? 'iPhone Safari' : 'Desktop Chrome', serviceWorker: {} },
        isIosDevice: () => ios,
        enableNotifications: () => {
            requests++;
            gesturePreserved = tapping;
            return fail ? Promise.reject(new Error('Allow notifications in phone settings.')) : Promise.resolve();
        },
        Swal: {
            isVisible: () => state.otherDialog,
            isLoading: () => false,
            showValidationMessage: message => validation.push(message),
            getConfirmButton: () => confirmButton,
            fire: options => {
                dialogs.push(options);
                options.didOpen?.();
                return options.toast ? Promise.resolve() : new Promise(resolve => { resolveDialog = resolve; });
            },
        },
    });
    return {
        window, document, mode, dialogs, validation, enabledEvents, state,
        requests: () => requests, gesturePreserved: () => gesturePreserved,
        async timers() { const pending = [...timers.values()]; timers.clear(); pending.forEach(callback => callback()); await this.settle(); },
        settle: () => new Promise(resolve => setImmediate(resolve)),
        async confirm() {
            tapping = true;
            confirmButton.dispatchEvent(new Event('click'));
            const pending = dialogs[0].preConfirm();
            tapping = false;
            const result = await pending;
            if (result !== false) resolveDialog({ isConfirmed: true });
            await this.settle();
        },
        async later() { resolveDialog({ isConfirmed: false }); await this.settle(); },
    };
}

test('first Android Home Screen launch offers a guided opt-in without requesting permission automatically', async () => {
    const app = setup();
    await app.timers();
    assert.equal(app.dialogs.length, 1);
    assert.equal(app.dialogs[0].confirmButtonText, 'Yes, enable!');
    assert.equal(app.requests(), 0);
    await app.confirm();
    assert.equal(app.requests(), 1);
    assert.equal(app.gesturePreserved(), true, 'native permission request must run directly from confirmation');
    assert.equal(app.dialogs[1].toast, true);
    assert.equal(app.dialogs[1].title, 'Notifications enabled!');
    assert.deepEqual(app.enabledEvents, ['school:notifications-enabled']);
});

test('iPhone Home Screen launch offers setup without relying on an appinstalled event', async () => {
    const app = setup({ ios: true, android: false });
    await app.timers();
    assert.equal(app.dialogs.length, 1);
});

test('Android installation triggers setup while ordinary browser visits do not', async () => {
    const app = setup({ installed: false });
    await app.timers();
    assert.equal(app.dialogs.length, 0);
    app.window.dispatchEvent(new Event('appinstalled'));
    await app.timers();
    assert.equal(app.dialogs.length, 1);
    app.window.dispatchEvent(new Event('appinstalled'));
    await app.timers();
    assert.equal(app.dialogs.length, 1);
});

test('Later suppresses repeated offers for seven days and never requests notification permission', async () => {
    const storage = new Map();
    const app = setup({ storage });
    await app.timers();
    await app.later();
    assert.equal(app.requests(), 0);
    const nextPage = setup({ storage });
    await nextPage.timers();
    assert.equal(nextPage.dialogs.length, 0);
    const nextWeek = setup({ storage, now: 1000000000 + 8 * 24 * 60 * 60 * 1000 });
    await nextWeek.timers();
    assert.equal(nextWeek.dialogs.length, 1);
});

test('granted, denied, signed-out, unconfigured, unsupported and desktop sessions are not prompted', async () => {
    for (const options of [{ permission: 'granted' }, { permission: 'denied' }, { signedIn: false }, { configured: false }, { supported: false }, { android: false }]) {
        const app = setup(options);
        await app.timers();
        assert.equal(app.dialogs.length, 0);
    }
});

test('subscription failure leaves the guide open with the error and never announces success', async () => {
    const app = setup({ fail: true });
    await app.timers();
    await app.confirm();
    assert.deepEqual(app.validation, ['Allow notifications in phone settings.']);
    assert.equal(app.dialogs.length, 1);
    assert.deepEqual(app.enabledEvents, []);
});

test('onboarding waits for other alerts to close and for the app to be visible', async () => {
    const app = setup({ otherDialog: true });
    await app.timers();
    assert.equal(app.dialogs.length, 0);
    app.state.otherDialog = false;
    await app.timers();
    assert.equal(app.dialogs.length, 1);
    const background = setup({ hidden: true });
    await background.timers();
    assert.equal(background.dialogs.length, 0);
    background.document.visibilityState = 'visible';
    background.document.dispatchEvent(new Event('visibilitychange'));
    await background.timers();
    assert.equal(background.dialogs.length, 1);
});
