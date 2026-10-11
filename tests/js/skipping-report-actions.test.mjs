import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { skippingReportUrl } from '../../resources/js/helpers/skippingReportUrl.js';

const helperUrl = new URL('../../resources/js/helpers/skippingStatisticsShare.js', import.meta.url);
const helperSource = (await readFile(helperUrl, 'utf8'))
    .replace("import Swal from 'sweetalert2';", 'const Swal = { fire: options => globalThis.captureStatisticsPopup(options) };')
    .replace("'./pngResolution'", JSON.stringify(new URL('./pngResolution.js', helperUrl).href))
    .replace("'./skippingReportUrl'", JSON.stringify(new URL('./skippingReportUrl.js', helperUrl).href));
const { initSkippingStatisticsShare } = await import(`data:text/javascript;base64,${Buffer.from(helperSource).toString('base64')}`);

function globals(t, values) {
    const previous = Object.fromEntries(Object.keys(values).map(key => [key, Object.getOwnPropertyDescriptor(globalThis, key)]));
    for (const [key, value] of Object.entries(values)) Object.defineProperty(globalThis, key, { value, configurable: true, writable: true });
    t.after(() => {
        for (const [key, descriptor] of Object.entries(previous)) {
            if (descriptor) Object.defineProperty(globalThis, key, descriptor);
            else delete globalThis[key];
        }
    });
}

test('print and export URLs use every current filter and omit list pagination', t => {
    globals(t, { location: { href: 'http://localhost/students/skipping-grade' } });
    const filters = new FormData();
    const values = { academic_year_id: '1', campus_id: '5', status: 'rejected', grade_class: '7:3:none', approval_number: 'BCH-001', search: 'SOK DARA', per_page: '100' };
    for (const [key, value] of Object.entries(values)) filters.append(key, value);
    for (const path of ['/print-report', '/print-statistics', '/reports/list/export/xlsx', '/reports/list/export/pdf', '/reports/statistics/export/xlsx', '/reports/statistics/export/pdf']) {
        const url = skippingReportUrl(`/students/skipping-grade${path}?search=old&page=2`, filters, path.includes('statistics'));
        assert.equal(url.pathname, `/students/skipping-grade${path}`);
        for (const [key, value] of Object.entries(values)) {
            const expected = key === 'per_page' ? null : key === 'status' && path.includes('statistics') ? 'approved' : value;
            assert.equal(url.searchParams.get(key), expected);
        }
        assert.equal(url.searchParams.has('page'), false);
    }
    filters.set('status', '');
    assert.equal(skippingReportUrl('/print-report', filters).searchParams.get('status'), '');
    assert.equal(skippingReportUrl('/print-statistics', filters, true).searchParams.get('status'), 'approved');
    assert.equal(skippingReportUrl('/print-statistics?status=', null, true).searchParams.get('status'), 'approved');
    assert.equal(skippingReportUrl('/print-statistics?status=draft', null, true).searchParams.get('status'), 'approved');
});

class Element extends EventTarget {
    dataset = {};
    textContent = '';
    hidden = true;
    disabled = true;
    removed = false;
    attributes = {};
    setAttribute(name, value) { this.attributes[name] = value; }
    remove() { this.removed = true; }
}

function shareSetup(t, { load = true, renderer = true } = {}) {
    let options, frame;
    const NativeFormData = FormData;
    const NativeURL = URL;
    const revoked = [];
    const data = new NativeFormData();
    data.append('academic_year_id', '1');
    data.append('campus_id', '5');
    data.append('status', '');
    data.append('search', 'SOK DARA');
    data.append('per_page', '100');
    const trigger = new Element();
    trigger.dataset.skippingShareStatistics = 'http://localhost/students/skipping-grade/print-statistics?search=old';
    const nodes = Object.fromEntries(['status', 'copy', 'download'].map(name => [`[data-statistics-${name === 'status' ? 'share-status' : name}]`, new Element()]));
    nodes['.skipping-statistics-share-preview'] = new Element();
    const apps = ['Telegram', 'WhatsApp', 'Facebook Messenger'].map(name => Object.assign(new Element(), { dataset: { statisticsShareApp: name } }));
    const popup = { querySelector: selector => nodes[selector], querySelectorAll: () => apps };
    const root = { querySelector: selector => selector === '[data-skipping-share-statistics]' ? trigger : { data } };
    globals(t, {
        captureStatisticsPopup: value => { options = value; },
        location: { href: 'http://localhost/students/skipping-grade', origin: 'http://localhost' },
        navigator: {},
        FormData: class { constructor(form) { return form.data; } },
        URL: class extends NativeURL {
            static createObjectURL() { return 'blob:statistics-image'; }
            static revokeObjectURL(url) { revoked.push(url); }
        },
        document: {
            createElement: () => {
                frame = new Element();
                frame.contentWindow = renderer ? { createSkippingStatisticsImage: async () => ({ blob: new Blob(['png'], { type: 'image/png' }), filename: 'grade-skipping-statistics-20261011.png' }) } : {};
                return frame;
            },
            body: { append: element => { if (load) element.dispatchEvent(new Event('load')); } },
        },
    });
    initSkippingStatisticsShare(root);
    trigger.dispatchEvent(new Event('click'));
    return { options, popup, nodes, apps, revoked, frame: () => frame };
}

test('sharing from the request list prepares the filtered statistics image in the existing popup', async t => {
    const setup = shareSetup(t);
    await setup.options.didOpen(setup.popup);
    const url = new URL(setup.frame().src);
    assert.equal(url.searchParams.get('campus_id'), '5');
    assert.equal(url.searchParams.get('search'), 'SOK DARA');
    assert.equal(url.searchParams.get('status'), 'approved');
    assert.equal(url.searchParams.has('per_page'), false);
    assert.equal(setup.frame().attributes['aria-hidden'], 'true');
    assert.equal(setup.frame().removed, true);
    assert.equal(setup.nodes['[data-statistics-share-status]'].textContent, 'A4 landscape image ready (300 DPI).');
    assert.equal(setup.nodes['.skipping-statistics-share-preview'].hidden, false);
    assert.equal(setup.nodes['[data-statistics-download]'].download, 'grade-skipping-statistics-20261011.png');
    assert.equal(setup.nodes['[data-statistics-copy]'].disabled, true);
    assert.ok(setup.apps.every(button => !button.disabled));
    setup.options.didClose();
    assert.deepEqual(setup.revoked, ['blob:statistics-image']);
});

test('closing the share popup during loading removes the renderer and cancels its pending load', async t => {
    const setup = shareSetup(t, { load: false });
    const preparing = setup.options.didOpen(setup.popup);
    setup.options.didClose();
    await preparing;
    assert.equal(setup.frame().removed, true);
    assert.equal(setup.nodes['.skipping-statistics-share-preview'].hidden, true);
    assert.equal(setup.nodes['[data-statistics-share-status]'].textContent, '');
    assert.ok(setup.apps.every(button => button.disabled));
});

test('a login or forbidden response cannot become a shared image', async t => {
    const setup = shareSetup(t, { renderer: false });
    await setup.options.didOpen(setup.popup);
    assert.match(setup.nodes['[data-statistics-share-status]'].textContent, /Check your permission or sign in again/);
    assert.equal(setup.frame().removed, true);
    assert.equal(setup.nodes['[data-statistics-download]'].hidden, true);
    assert.ok(setup.apps.every(button => button.disabled));
});
