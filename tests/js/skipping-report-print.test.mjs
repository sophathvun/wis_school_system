import test from 'node:test';
import assert from 'node:assert/strict';
import { initSkippingReportPrint } from '../../resources/js/helpers/skippingReportPrint.js';

const deferred = () => {
    let resolve;
    const promise = new Promise(done => { resolve = done; });
    return { promise, resolve };
};
const flush = () => new Promise(resolve => setImmediate(resolve));

function setup({ url = 'http://localhost/print-report', frame = false, loading = false, fonts = Promise.resolve(), images = [] } = {}) {
    const button = { disabled: false, addEventListener(type, handler) { this[type] = handler; } };
    const doc = { readyState: loading ? 'loading' : 'complete', fonts: { ready: fonts }, images, querySelector: () => button };
    const win = new EventTarget();
    Object.assign(win, {
        location: { href: url },
        printCount: 0,
        print() { this.printCount++; },
        requestAnimationFrame(callback) { callback(); },
    });
    win.self = win;
    win.top = frame ? {} : win;
    initSkippingReportPrint(doc, win);
    return { button, doc, win };
}

test('automatic print preview waits for the page, fonts and student photos and opens only once', async () => {
    const fonts = deferred();
    const photo = deferred();
    const { button, doc, win } = setup({ url: 'http://localhost/print-report?autoprint=1', loading: true, fonts: fonts.promise, images: [{ decode: () => photo.promise }] });
    assert.equal(button.disabled, true);
    assert.equal(win.printCount, 0);
    await button.click();
    assert.equal(win.printCount, 0);
    doc.readyState = 'complete';
    win.dispatchEvent(new Event('load'));
    await flush();
    assert.equal(win.printCount, 0);
    fonts.resolve();
    await flush();
    assert.equal(win.printCount, 0);
    photo.resolve();
    await flush();
    assert.equal(win.printCount, 1);
    assert.equal(button.disabled, false);
});

test('ordinary report previews retain manual printing and tolerate a missing photo', async () => {
    const { button, win } = setup({ images: [{ decode: () => Promise.reject(new Error('Missing photo')) }] });
    await flush();
    assert.equal(win.printCount, 0);
    await button.click();
    assert.equal(win.printCount, 1);
    assert.equal(button.disabled, false);
});

test('statistics print actions open print preview while image renderer frames never auto-print', async () => {
    const printable = setup({ url: 'http://localhost/print-statistics?status=approved&autoprint=1' });
    const renderer = setup({ url: 'http://localhost/print-statistics?autoprint=1', frame: true });
    await flush();
    assert.equal(printable.win.printCount, 1);
    assert.equal(renderer.win.printCount, 0);
    assert.equal(renderer.button.disabled, false);
});
