import { Modal } from 'bootstrap';
import { createPhotoEditor } from './helpers/studentPhotoEditor';
import { showError, showSuccess } from './helpers/sweet-alert2';

const workspace = document.getElementById('photoEditorWorkspace');
if (workspace) {
    const input = document.getElementById('photoEditorInput');
    const dropzone = document.getElementById('photoEditorDropzone');
    const empty = document.getElementById('photoEditorEmpty');
    const clear = document.getElementById('photoEditorClear');
    const status = document.getElementById('photoEditorDownloadStatus');
    const downloads = [...workspace.querySelectorAll('[data-photo-download]')];
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    let editedFile, webpAvailable = true;
    const editor = createPhotoEditor({
        bootstrap: { Modal }, csrf, showError, showSuccess, editorId: 'photoEditorModal',
        elements: { input, dropzone, preview: document.getElementById('photoEditorPreview'),
            previewContainer: document.getElementById('photoEditorPreviewContainer'),
            editButton: document.getElementById('photoEditorEdit'), parent: null, pasteTarget: dropzone },
        saveMessage: 'Your edited photo is ready to download.',
        onApply(file, size) {
            editedFile = file;
            webpAvailable = size.webp_available !== false;
            empty.classList.add('d-none');
            clear.disabled = false;
            downloads.forEach(button => button.disabled = button.dataset.photoDownload === 'webp' && !webpAvailable);
            status.textContent = '';
        },
    });
    clear.addEventListener('click', () => {
        editor.resetPreview(); editedFile = null;
        empty.classList.remove('d-none'); clear.disabled = true;
        downloads.forEach(button => button.disabled = true);
        status.textContent = '';
    });
    downloads.forEach(button => button.addEventListener('click', async () => {
        if (!editedFile) return;
        downloads.forEach(control => control.disabled = true);
        clear.disabled = true;
        status.textContent = 'Preparing your download…';
        try {
            const data = new FormData(); data.append('photo', editedFile);
            data.append('format', button.dataset.photoDownload);
            const response = await fetch(workspace.dataset.downloadUrl, { method: 'POST', credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf || '', Accept: 'application/json' }, body: data });
            if (!response.ok) {
                const error = await response.json().catch(() => ({}));
                throw new Error(error.errors?.photo?.[0] || error.message || 'The edited photo could not be downloaded. Please try again.');
            }
            const blob = await response.blob();
            const mime = button.dataset.photoDownload === 'jpg' ? 'image/jpeg' : 'image/webp';
            if (blob.type !== mime) throw new Error('The server did not return a photo. Please sign in again and retry.');
            const url = URL.createObjectURL(blob), link = document.createElement('a');
            link.href = url; link.download = `edited-photo.${button.dataset.photoDownload}`;
            document.body.append(link); link.click(); link.remove();
            setTimeout(() => URL.revokeObjectURL(url), 60000);
            status.textContent = `${button.dataset.photoDownload.toUpperCase()} ready · ${(blob.size / 1024).toFixed(2)} KB`;
        } catch (error) { status.textContent = ''; showError('Photo download', error.message); }
        finally { downloads.forEach(control => control.disabled = !editedFile || (control.dataset.photoDownload === 'webp' && !webpAvailable)); clear.disabled = !editedFile; }
    }));
    workspace.dataset.ready = 'true';
}
