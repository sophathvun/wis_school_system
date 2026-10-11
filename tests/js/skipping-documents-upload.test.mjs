import test from 'node:test';
import assert from 'node:assert/strict';
import { initSkippingDocumentsUpload } from '../../resources/js/helpers/skippingDocumentsUpload.js';

class Element extends EventTarget {
    dataset = {};
    value = '';
    files = [];
    disabled = false;
    clickCount = 0;
    innerHTML = '';
    textContent = '';
    classes = new Set();
    classList = {
        add: (value) => this.classes.add(value),
        remove: (value) => this.classes.delete(value),
        toggle: (value, enabled) => enabled ? this.classes.add(value) : this.classes.delete(value),
    };
    closest() { return null; }
    contains(element) { return this === element; }
    focus() {}
    click() { this.clickCount++; }
    fire(type, properties = {}) {
        const event = new Event(type, { cancelable: true });
        Object.assign(event, properties);
        this.dispatchEvent(event);
        return event;
    }
}

const setup = (existingCount = 0) => {
    const form = new Element();
    const input = new Element();
    input.name = 'documents[]';
    input.form = form;
    const zone = new Element();
    const list = new Element();
    const status = new Element();
    const nodes = { '[data-documents-input]': input, '[data-documents-dropzone]': zone, '[data-documents-file-list]': list, '[data-documents-status]': status };
    const uploader = initSkippingDocumentsUpload({ dataset: { existingCount }, querySelector: (selector) => nodes[selector], querySelectorAll: () => [] });
    const uploadData = () => {
        const data = new FormData();
        data.append('parent_name', 'SOK RITH');
        // The real form may have a stale native file selection after removal.
        data.append('documents[]', pdf('stale.pdf'));
        uploader.appendTo(data);
        assert.equal(data.get('parent_name'), 'SOK RITH');
        return data;
    };
    const uploadNames = () => uploadData().getAll('documents[]').map((file) => file.name);
    return { form, input, zone, list, status, uploadNames, uploadData, uploader };
};
const pdf = (name = 'evidence.pdf') => new File(['%PDF-1.7'], name, { type: 'application/pdf', lastModified: 100 });

test('browse, repeated drops and clipboard files accumulate once and all reach the multipart submission', () => {
    const { input, zone, list, uploadNames } = setup();
    const file = pdf();
    input.files = [file];
    input.fire('change');
    zone.fire('drop', { dataTransfer: { files: [file, file, pdf('results.pdf')] } });
    const paste = zone.fire('paste', { clipboardData: { files: [new File(['image'], 'image.png', { type: 'image/png' })] } });
    assert.equal(paste.defaultPrevented, true);
    assert.deepEqual(uploadNames(), ['evidence.pdf', 'results.pdf', 'image.png']);
    assert.match(list.innerHTML, /Ready to upload/);
    assert.equal((list.innerHTML.match(/premium-document-file-item/g) || []).length, 3);
});

test('pasting clipboard file items works and ordinary text paste keeps its native behavior', () => {
    const { zone, uploadNames } = setup();
    zone.fire('paste', { clipboardData: { items: [{ kind: 'string' }, { kind: 'file', getAsFile: () => pdf() }] } });
    assert.deepEqual(uploadNames(), ['evidence.pdf']);
    const textPaste = zone.fire('paste', { clipboardData: { files: [], items: [{ kind: 'string' }] } });
    assert.equal(textPaste.defaultPrevented, false);
});

test('removing queued files changes the submission and keeps saved document capacity separate', () => {
    const { zone, list, status, uploadNames } = setup(19);
    zone.fire('drop', { dataTransfer: { files: [pdf(), pdf('extra.pdf')] } });
    assert.deepEqual(uploadNames(), ['evidence.pdf']);
    assert.match(status.textContent, /20 supporting documents/);
    const remove = new Element();
    remove.dataset.documentsRemove = '0';
    remove.closest = () => remove;
    // File remove clicks bubble through the list in the browser.
    const click = new Event('click');
    Object.defineProperty(click, 'target', { value: remove });
    list.dispatchEvent(click);
    assert.deepEqual(uploadNames(), []);
    assert.equal(status.textContent, '');
    zone.fire('drop', { dataTransfer: { files: [pdf('replacement.pdf')] } });
    assert.deepEqual(uploadNames(), ['replacement.pdf']);
});

test('unsupported, oversized and excess files are reported without losing accepted files', () => {
    const { zone, status, uploadNames } = setup();
    zone.fire('drop', { dataTransfer: { files: [pdf('unsafe.exe'), new File([new Uint8Array(5 * 1024 * 1024 + 1)], 'large.pdf'), ...Array.from({ length: 11 }, (_, i) => pdf(`evidence-${i}.pdf`))] } });
    assert.equal(uploadNames().length, 10);
    assert.match(status.textContent, /Please choose PDF/);
    assert.match(status.textContent, /5 MB or smaller/);
    assert.match(status.textContent, /10 files/);
});

test('keyboard browse, drag state and busy form protection preserve the queued files', () => {
    const { form, input, zone, uploadNames } = setup();
    assert.equal(zone.fire('keydown', { key: ' ' }).defaultPrevented, true);
    assert.equal(input.clickCount, 1);
    zone.fire('dragover');
    assert.equal(zone.classes.has('is-dragging'), true);
    zone.fire('drop', { dataTransfer: { files: [pdf()] } });
    assert.equal(zone.classes.has('is-dragging'), false);
    form.dataset.busy = '1';
    zone.fire('drop', { dataTransfer: { files: [pdf('ignored.pdf')] } });
    zone.fire('paste', { clipboardData: { files: [pdf('ignored-too.pdf')] } });
    zone.fire('keydown', { key: 'Enter' });
    assert.equal(input.clickCount, 1);
    assert.deepEqual(uploadNames(), ['evidence.pdf']);
    delete form.dataset.busy;
    form.fire('reset');
    assert.deepEqual(uploadNames(), []);
});

test('file names are escaped in cards while preserving their names in submitted files', () => {
    const { zone, list, uploadNames } = setup();
    zone.fire('drop', { dataTransfer: { files: [pdf('<img src=x onerror=alert(1)>.pdf')] } });
    assert.equal(list.innerHTML.includes('<img'), false);
    assert.match(list.innerHTML, /&lt;img/);
    assert.deepEqual(uploadNames(), ['<img src=x onerror=alert(1)>.pdf']);
});

test('parent-signed file markers follow queued files after removal and do not open the file browser', () => {
    const { zone, list, input, uploadData, uploader } = setup(2);
    zone.fire('drop', { dataTransfer: { files: [pdf('other.pdf'), pdf('signed.pdf')] } });
    const checkbox = new Element();
    checkbox.dataset.documentsParentSigned = '1';
    checkbox.checked = true;
    checkbox.closest = () => checkbox;
    const change = new Event('change');
    Object.defineProperty(change, 'target', { value: checkbox });
    list.dispatchEvent(change);
    assert.deepEqual(uploadData().getAll('parent_signed_documents[]'), ['3']);
    const existingAndNew = new FormData();
    existingAndNew.append('parent_signed_documents[]', '1');
    uploader.appendTo(existingAndNew);
    assert.deepEqual(existingAndNew.getAll('parent_signed_documents[]'), ['1', '3']);
    const click = new Event('click');
    Object.defineProperty(click, 'target', { value: checkbox });
    zone.dispatchEvent(click);
    assert.equal(input.clickCount, 0);
    const remove = new Element();
    remove.dataset.documentsRemove = '0';
    remove.closest = () => remove;
    const removeClick = new Event('click');
    Object.defineProperty(removeClick, 'target', { value: remove });
    list.dispatchEvent(removeClick);
    assert.deepEqual(uploadData().getAll('parent_signed_documents[]'), ['2']);
    assert.match(list.innerHTML, /data-documents-parent-signed="0" checked/);
    remove.dataset.documentsRemove = '0';
    list.dispatchEvent(removeClick);
    zone.fire('drop', { dataTransfer: { files: [pdf('signed.pdf')] } });
    assert.deepEqual(uploadData().getAll('parent_signed_documents[]'), []);
});

test('queued cards preview the selected document with a fixed document MIME type and release removed previews', async () => {
    const { zone, list, input, form, uploadNames } = setup();
    const file = new File(['%PDF preview content'], 'preview.pdf', { type: 'text/html' });
    zone.fire('drop', { dataTransfer: { files: [file] } });
    const url = list.innerHTML.match(/href="([^"]+)"/)[1];
    const response = await fetch(url);
    assert.equal(response.headers.get('content-type'), 'application/pdf');
    assert.equal(await response.text(), '%PDF preview content');
    const anchor = new Element();
    anchor.closest = () => anchor;
    const click = new Event('click');
    Object.defineProperty(click, 'target', { value: anchor });
    zone.dispatchEvent(click);
    assert.equal(click.defaultPrevented, false);
    assert.equal(input.clickCount, 0);
    assert.deepEqual(uploadNames(), ['preview.pdf']);
    const remove = new Element();
    remove.dataset.documentsRemove = '0';
    remove.closest = () => remove;
    const removeClick = new Event('click');
    Object.defineProperty(removeClick, 'target', { value: remove });
    list.dispatchEvent(removeClick);
    await assert.rejects(fetch(url));
    zone.fire('drop', { dataTransfer: { files: [file] } });
    const resetUrl = list.innerHTML.match(/href="([^"]+)"/)[1];
    form.fire('reset');
    await assert.rejects(fetch(resetUrl));
});
