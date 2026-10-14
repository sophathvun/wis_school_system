import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const workerSource = readFileSync(new URL('../../public/sw.js', import.meta.url), 'utf8');
const navbarSource = readFileSync(new URL('../../resources/js/navbar.js', import.meta.url), 'utf8');

function worker({ supported = true, denied = false } = {}) {
    const handlers = {}, badges = [], notifications = [];
    const navigator = supported ? { setAppBadge: async count => {
        if (denied) throw new Error('Badges disabled');
        badges.push(count);
    } } : {};
    vm.runInNewContext(workerSource, {
        self: {
            navigator,
            addEventListener: (type, handler) => { handlers[type] = handler; },
            registration: { showNotification: async (...args) => notifications.push(args) },
        },
    });
    return {
        badges, notifications,
        async push(payload) {
            const work = [];
            handlers.push({ data: { json: () => payload }, waitUntil: promise => work.push(promise) });
            await Promise.all(work);
        },
    };
}

test('background push updates the installed icon through WorkerNavigator and can clear its count', async () => {
    const app = worker();
    await app.push({ title: 'New message', badgeCount: 7 });
    await app.push({ title: 'Update', badgeCount: 0 });
    assert.deepEqual(app.badges, [7, 0]);
    assert.equal(app.notifications.length, 2, 'every push remains user-visible');
});

test('Android without the Badging API still receives a visible push notification', async () => {
    const app = worker({ supported: false });
    await app.push({ title: 'New notification', badgeCount: 3 });
    assert.equal(app.notifications.length, 1);
    assert.equal(app.badges.length, 0);
});

test('disabled icon badges do not prevent push notification delivery', async () => {
    const app = worker({ denied: true });
    await app.push({ title: 'New message', badgeCount: 3 });
    assert.equal(app.notifications.length, 1);
});

test('pushes with no unread count do not accidentally clear an existing badge', async () => {
    const app = worker();
    await app.push({ title: 'New message' });
    assert.deepEqual(app.badges, []);
    assert.equal(app.notifications.length, 1);
});

class Element extends EventTarget {
    textContent = '';
    classList = { toggle() {} };
    closest() { return this.link; }
}

function navbar({ registration = true, permission = 'granted' } = {}) {
    const chat = new Element(), notification = new Element(), mobile = new Element();
    mobile.link = new Element();
    const intervals = new Map(), badges = [], shown = [];
    let unread = { chat: 2, notifications: 3 }, subscriptions = 0, permissionRequests = 0;
    class Notification {
        static permission = permission;
        constructor() { throw new Error('Mobile does not support the Notification constructor'); }
        static async requestPermission() {
            permissionRequests++;
            this.permission = 'granted';
            return 'granted';
        }
    }
    const context = {
        Event, Notification,
        CustomEvent: class extends Event { constructor(type, options) { super(type); this.detail = options.detail; } },
        document: {
            addEventListener: (type, handler) => handler(),
            getElementById: id => id === 'chat-unread-badge' ? chat : null,
            querySelectorAll: () => [],
            querySelector: selector => ({
                '[data-navbar-notification-badge]': notification,
                '[data-navbar-mobile-notification-badge]': mobile,
            })[selector] || null,
        },
        navigator: {
            setAppBadge: async count => badges.push(count),
            clearAppBadge: async () => badges.push(0),
            serviceWorker: { getRegistration: async () => registration ? {
                active: {}, showNotification: async (...args) => shown.push(args),
            } : undefined },
        },
        window: {
            Notification,
            addEventListener() {},
            dispatchEvent() {},
            setInterval: (callback, delay) => intervals.set(delay, callback),
            schoolEnsurePushSubscription: async () => subscriptions++,
        },
        fetch: async url => ({ ok: true, json: async () => ({
            unread: url.includes('/chat/') ? unread.chat : unread.notifications,
            latest: { id: unread.notifications, title: 'School update' }, items: [],
        }) }),
    };
    vm.runInNewContext(navbarSource, context);
    return {
        chat, notification, mobile, badges, shown,
        subscriptionCount: () => subscriptions,
        permissionRequestCount: () => permissionRequests,
        async settle() { await new Promise(resolve => setImmediate(resolve)); },
        async refresh(counts) {
            unread = counts;
            await Promise.all([...intervals.values()].map(callback => callback()));
            await this.settle();
        },
    };
}

test('mobile foreground polling badges notifications plus messages and clears after reading', async () => {
    const app = navbar();
    await app.settle();
    assert.equal(app.badges.at(-1), 5);
    await app.refresh({ chat: 4, notifications: 5 });
    assert.equal(app.badges.at(-1), 9);
    assert.equal(app.chat.textContent, 4);
    assert.equal(app.notification.textContent, 5);
    assert.equal(app.shown.length, 2, 'mobile notifications use the service worker');
    await app.refresh({ chat: 0, notifications: 0 });
    assert.equal(app.badges.at(-1), 0);
});

test('an unavailable notification constructor cannot interrupt foreground unread badge updates', async () => {
    const app = navbar({ registration: false });
    await app.settle();
    await app.refresh({ chat: 3, notifications: 4 });
    assert.equal(app.badges.at(-1), 7);
});

test('tapping the mobile notification bell can enable notifications and push subscriptions', async () => {
    const app = navbar({ permission: 'default' });
    await app.settle();
    app.mobile.link.dispatchEvent(new Event('click'));
    await app.settle();
    assert.equal(app.permissionRequestCount(), 1);
    assert.equal(app.subscriptionCount(), 1);
});
