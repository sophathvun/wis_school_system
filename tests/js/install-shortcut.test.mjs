import test from 'node:test';
import assert from 'node:assert/strict';
import { isIosDevice, setupInstallShortcut } from '../../resources/js/installShortcut.js';

function browser({ userAgent = 'iPhone Safari', platform = 'iPhone', maxTouchPoints = 1, standalone = false, narrow = true, swal = true } = {}) {
    const classes = new Set(['d-none']);
    const buttonEvents = {};
    const windowEvents = {};
    const dialogs = [];
    const alerts = [];
    const label = { textContent: 'Add Shortcut' };
    const button = {
        classList: { add: (value) => classes.add(value), remove: (value) => classes.delete(value) },
        querySelector: () => label,
        addEventListener: (name, handler) => { buttonEvents[name] = handler; },
    };
    globalThis.document = { getElementById: () => button };
    globalThis.window = {
        navigator: { userAgent, platform, maxTouchPoints, standalone },
        matchMedia: (query) => ({ matches: query.includes('display-mode') ? standalone : narrow }),
        addEventListener: (name, handler) => { windowEvents[name] = handler; },
        alert: (message) => alerts.push(message),
        Swal: swal ? { fire: (options) => { dialogs.push(options); return Promise.resolve({ isConfirmed: true }); } } : null,
    };
    setupInstallShortcut();
    return { classes, label, dialogs, alerts, buttonEvents, windowEvents };
}

test('detects iPhone and desktop-mode iPad without treating a Mac as iOS', () => {
    assert.equal(isIosDevice({ userAgent: 'iPhone', platform: 'iPhone' }), true);
    assert.equal(isIosDevice({ userAgent: 'Macintosh Safari', platform: 'MacIntel', maxTouchPoints: 5 }), true);
    assert.equal(isIosDevice({ userAgent: 'Macintosh Safari', platform: 'MacIntel', maxTouchPoints: 0 }), false);
});

test('iPhone browsers show all three manual steps and do not pretend the dialog installs the app', async () => {
    for (const userAgent of ['iPhone Safari', 'iPhone CriOS/143', 'iPhone FxiOS/143', 'iPhone EdgiOS/143']) {
        const state = browser({ userAgent });
        assert.equal(state.classes.has('d-none'), false);
        assert.equal(state.label.textContent, 'How to Add Shortcut');
        await state.buttonEvents.click();
        const dialog = state.dialogs[0];
        assert.equal(dialog.title, 'Add to iPhone Home Screen');
        assert.equal(dialog.confirmButtonText, 'Close instructions');
        assert.match(dialog.html, /<strong>Share<\/strong>/);
        assert.match(dialog.html, /Add to Home Screen/);
        assert.match(dialog.html, /Tap <strong>Add<\/strong>/);
        assert.match(dialog.html, /Safari/);
        assert.equal(state.classes.has('d-none'), false);
    }
});

test('desktop-mode iPad gets instructions even with a wide screen', async () => {
    const state = browser({ userAgent: 'Macintosh Safari', platform: 'MacIntel', maxTouchPoints: 5, narrow: false });
    assert.equal(state.classes.has('is-ios'), true);
    assert.equal(state.classes.has('d-none'), false);
    await state.buttonEvents.click();
    assert.equal(state.dialogs[0].title, 'Add to iPad Home Screen');
});

test('already-installed standalone apps do not offer another shortcut', () => {
    const state = browser({ standalone: true });
    assert.equal(state.classes.has('d-none'), true);
    assert.equal(state.buttonEvents.click, undefined);
});

test('Android still uses the browser installation prompt and hides the button after installation', async () => {
    const state = browser({ userAgent: 'Android Chrome', platform: 'Linux armv8l' });
    assert.equal(state.classes.has('d-none'), true);
    let promptCount = 0;
    let prevented = false;
    state.windowEvents.beforeinstallprompt({ preventDefault: () => { prevented = true; }, prompt: () => { promptCount++; }, userChoice: Promise.resolve({ outcome: 'accepted' }) });
    assert.equal(prevented, true);
    assert.equal(state.classes.has('d-none'), false);
    await state.buttonEvents.click();
    assert.equal(promptCount, 1);
    assert.equal(state.dialogs.length, 0);
    assert.equal(state.label.textContent, 'Add Shortcut');
    state.windowEvents.appinstalled();
    assert.equal(state.classes.has('d-none'), true);
});

test('iPhone fallback instructions include the final Add step without SweetAlert', async () => {
    const state = browser({ swal: false });
    await state.buttonEvents.click();
    assert.match(state.alerts[0], /3\. Tap Add/);
});
