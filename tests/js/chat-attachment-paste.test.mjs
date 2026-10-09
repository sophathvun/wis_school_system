import test from 'node:test';
import assert from 'node:assert/strict';
import { bindChatAttachmentPaste, clipboardAttachmentFiles, prepareChatAttachment } from '../../resources/js/helpers/chatAttachmentPaste.js';

const eventWith = (clipboardData) => {
    const event = new Event('paste', { cancelable: true });
    Object.defineProperty(event, 'clipboardData', { value: clipboardData });
    return event;
};

test('pastes a screenshot once when the clipboard exposes both items and files', () => {
    const image = new File(['image bytes'], 'image.png', { type: 'image/png' });
    const form = new EventTarget();
    const chosen = [];
    const errors = [];
    bindChatAttachmentPaste(form, { enabled: () => true, selectFile: file => chosen.push(file), onError: error => errors.push(error) });
    const event = eventWith({ items: [{ kind: 'file', getAsFile: () => image }], files: [image] });
    form.dispatchEvent(event);
    assert.equal(event.defaultPrevented, true);
    assert.deepEqual(chosen, [image]);
    assert.deepEqual(errors, []);
});

test('file-list clipboard fallback accepts a copied document', () => {
    const file = new File(['PDF bytes'], 'school-form.pdf', { type: 'application/pdf' });
    assert.deepEqual(clipboardAttachmentFiles({ items: [{ kind: 'file', getAsFile: () => null }], files: [file] }), [file]);
    const form = new EventTarget();
    let chosen;
    bindChatAttachmentPaste(form, { enabled: () => true, selectFile: file => chosen = file, onError: () => assert.fail('Unexpected error') });
    const event = eventWith({ files: [file] });
    form.dispatchEvent(event);
    assert.equal(chosen, file);
    assert.equal(event.defaultPrevented, true);
});

test('text and URL paste keep the browsers normal behavior', () => {
    const form = new EventTarget();
    bindChatAttachmentPaste(form, { enabled: () => true, selectFile: () => assert.fail('No attachment'), onError: () => assert.fail('No error') });
    for (const data of [null, { items: [{ kind: 'string', type: 'text/plain' }], files: [] }]) {
        const event = eventWith(data);
        form.dispatchEvent(event);
        assert.equal(event.defaultPrevented, false);
    }
});

test('unnamed clipboard images retain their bytes and get an upload filename', async () => {
    const original = new File(['PNG bytes'], '', { type: 'image/png', lastModified: 123 });
    const { file, error } = prepareChatAttachment(original);
    assert.equal(error, undefined);
    assert.equal(file.name, 'pasted-file.png');
    assert.equal(file.type, 'image/png');
    assert.equal(file.lastModified, 123);
    assert.equal(await file.text(), await original.text());
    const formData = new FormData();
    formData.append('attachment', file, file.name);
    assert.equal(formData.get('attachment').name, 'pasted-file.png');
});

test('supported files accept the 20 MB boundary and reject larger or unsupported files', () => {
    const file = { name: 'FILE.PDF', size: 20 * 1024 * 1024, type: '' };
    assert.equal(prepareChatAttachment(file).file, file);
    assert.match(prepareChatAttachment({ ...file, size: file.size + 1 }).error, /20 MB/);
    for (const file of [new File(['x'], 'program.exe'), new File(['<svg>'], 'image.svg', { type: 'image/svg+xml' })]) {
        assert.ok(prepareChatAttachment(file).error);
    }
});

test('multiple clipboard files are rejected without silently replacing the current attachment', () => {
    const form = new EventTarget();
    const previous = new File(['x'], 'previous.png');
    let chosen = previous;
    let error;
    bindChatAttachmentPaste(form, { enabled: () => true, selectFile: file => chosen = file, onError: text => error = text });
    const event = eventWith({ files: [new File(['a'], 'a.pdf'), new File(['b'], 'b.pdf')] });
    form.dispatchEvent(event);
    assert.equal(event.defaultPrevented, true);
    assert.equal(chosen, previous);
    assert.match(error, /one file per message/);
});

test('paste only attaches files in an active chat and stops after unbinding', () => {
    const form = new EventTarget();
    let active = false;
    let count = 0;
    const unbind = bindChatAttachmentPaste(form, { enabled: () => active, selectFile: () => count++, onError: () => assert.fail() });
    const data = { files: [new File(['a'], 'a.png', { type: 'image/png' })] };
    const event = eventWith(data);
    form.dispatchEvent(event);
    assert.equal(event.defaultPrevented, false);
    active = true;
    form.dispatchEvent(eventWith(data));
    assert.equal(count, 1);
    unbind();
    form.dispatchEvent(eventWith(data));
    assert.equal(count, 1);
});
