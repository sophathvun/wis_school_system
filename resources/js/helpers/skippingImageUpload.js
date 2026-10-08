export const initSkippingImageUpload = (wrapper, notice) => {
    const input = wrapper.querySelector('[data-upload-input]');
    const dropzone = wrapper.querySelector('[data-upload-dropzone]');
    const image = wrapper.querySelector('[data-upload-image]');
    const preview = wrapper.querySelector('[data-upload-preview]');
    const status = wrapper.querySelector('[data-upload-status]');
    const cancel = wrapper.querySelector('[data-upload-cancel]');
    const originalSource = image.getAttribute('src');
    let previewUrl;
    let selectedFile;
    let revision = 0;
    const editable = () => !input.matches(':disabled') && !input.form?.dataset.busy;
    const releasePreview = () => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = null;
    };
    const setInputFile = (file) => {
        const transfer = new DataTransfer();
        if (file) transfer.items.add(file);
        input.files = transfer.files;
    };
    const showError = (message) => {
        status.textContent = message;
        status.classList.add('text-danger');
        dropzone.classList.add('is-invalid');
        notice('error', `Unable to upload ${wrapper.dataset.uploadLabel.toLowerCase()}`, message);
    };
    const acceptFile = async (file) => {
        if (!file || !editable()) return;
        const currentRevision = ++revision;
        delete wrapper.dataset.uploadPending;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            showError('Please choose a JPG, PNG, or WEBP image.');
            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            showError('The image must be 2 MB or smaller.');
            return;
        }
        const nextUrl = URL.createObjectURL(file);
        wrapper.dataset.uploadPending = 'true';
        try {
            await new Promise((resolve, reject) => {
                const check = new Image();
                check.onload = resolve;
                check.onerror = reject;
                check.src = nextUrl;
            });
            if (currentRevision !== revision || !editable()) return;
            setInputFile(file);
            selectedFile = file;
            releasePreview();
            previewUrl = nextUrl;
            image.src = nextUrl;
            preview.hidden = false;
            cancel.hidden = false;
            status.classList.remove('text-danger');
            dropzone.classList.remove('is-invalid');
            wrapper.querySelectorAll('[data-skipping-error]').forEach((el) => el.remove());
            status.textContent = `${file.name} · Ready to upload when you save settings.`;
        } catch {
            if (currentRevision === revision) showError('This image could not be read. Please choose another image.');
        } finally {
            if (currentRevision === revision) delete wrapper.dataset.uploadPending;
            if (previewUrl !== nextUrl) URL.revokeObjectURL(nextUrl);
        }
    };
    dropzone.addEventListener('click', () => { if (editable()) input.click(); });
    input.addEventListener('change', () => {
        const file = input.files?.[0];
        input.value = '';
        if (selectedFile) setInputFile(selectedFile);
        acceptFile(file);
    });
    dropzone.addEventListener('dragover', (event) => {
        event.preventDefault();
        if (editable()) dropzone.classList.add('is-dragging');
    });
    dropzone.addEventListener('dragleave', (event) => {
        if (!dropzone.contains(event.relatedTarget)) dropzone.classList.remove('is-dragging');
    });
    dropzone.addEventListener('drop', (event) => {
        event.preventDefault();
        dropzone.classList.remove('is-dragging');
        acceptFile(event.dataTransfer?.files?.[0]);
    });
    dropzone.addEventListener('paste', (event) => {
        const file = [...(event.clipboardData?.files || [])].find((file) => file.type.startsWith('image/'))
            || [...(event.clipboardData?.items || [])].find((item) => item.kind === 'file' && item.type.startsWith('image/'))?.getAsFile();
        if (file && editable()) { event.preventDefault(); acceptFile(file); }
    });
    cancel.addEventListener('click', () => {
        if (!editable()) return;
        revision++;
        delete wrapper.dataset.uploadPending;
        input.value = '';
        selectedFile = null;
        releasePreview();
        if (originalSource) image.src = originalSource;
        else image.removeAttribute('src');
        preview.hidden = !originalSource;
        cancel.hidden = true;
        status.textContent = '';
        status.classList.remove('text-danger');
        dropzone.classList.remove('is-invalid');
    });
    window.addEventListener('pagehide', releasePreview, { once: true });
};
