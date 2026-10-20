import { Modal } from 'bootstrap';
import { createPhotoEditor } from './studentPhotoEditor';
import { showError, showSuccess } from './sweet-alert2';

export function setupPersonPhotoEditor({ editorId, input, dropzone, preview, previewContainer = preview, editButton, parent = null, pasteTarget = dropzone, saveMessage }) {
    if (!input || !dropzone || !preview || input.dataset.photoStudioReady) return;
    input.dataset.photoStudioReady = '1';
    const editor = createPhotoEditor({
        bootstrap: { Modal }, csrf: document.querySelector('meta[name="csrf-token"]')?.content,
        showError, showSuccess, editorId, saveMessage,
        elements: { input, dropzone, preview, previewContainer, editButton, parent, pasteTarget },
    });
    const initial = preview.getAttribute('src');
    if (initial && initial !== '#') editor.showPreview(initial);
    input.form?.addEventListener('reset', () => {
        editor.resetPreview();
        if (initial && initial !== '#') editor.showPreview(initial);
    });
    return editor;
}
