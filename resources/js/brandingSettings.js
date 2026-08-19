const brandingFile = (field) => document.getElementById(field);

document.querySelectorAll('[data-branding-dropzone]').forEach((dropzone) => {
    const field = dropzone.dataset.brandingDropzone;
    const input = brandingFile(field);
    const preview = document.querySelector(`[data-branding-preview="${field}"]`);
    const wrapper = document.querySelector(`[data-branding-preview-wrap="${field}"]`);
    if (!input) return;

    const showPreview = (file) => {
        if (!file || !file.type.startsWith('image/')) return;
        preview.src = URL.createObjectURL(file);
        wrapper?.classList.remove('d-none');
    };
    const assign = (file) => {
        if (!file || !file.type.startsWith('image/')) return;
        const transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    };
    const imageFileFromPasteEvent = (event, filename = 'pasted-branding-image.png') => {
        const clipboardFiles = Array.from(event.clipboardData?.files || []);
        const directFile = clipboardFiles.find((entry) => entry.type.startsWith('image/'));
        if (directFile) return new File([directFile], filename, { type: directFile.type || 'image/png' });

        const items = Array.from(event.clipboardData?.items || []);
        const item = items.find((entry) => entry.kind === 'file' && entry.type.startsWith('image/'));
        const file = item?.getAsFile();
        return file ? new File([file], filename, { type: file.type || 'image/png' }) : null;
    };

    dropzone.addEventListener('click', () => input.click());
    dropzone.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') input.click();
    });
    dropzone.addEventListener('dragover', (event) => {
        event.preventDefault();
        dropzone.classList.add('is-dragging');
    });
    dropzone.addEventListener('dragleave', () => dropzone.classList.remove('is-dragging'));
    dropzone.addEventListener('drop', (event) => {
        event.preventDefault();
        dropzone.classList.remove('is-dragging');
        const file = event.dataTransfer.files?.[0];
        if (file) assign(file);
    });
    dropzone.addEventListener('paste', (event) => {
        const file = imageFileFromPasteEvent(event);
        if (!file) return;
        event.preventDefault();
        assign(file);
    });
    input.addEventListener('change', () => showPreview(input.files?.[0]));
});

document.addEventListener('paste', (event) => {
    if (event.target?.closest?.('input:not([type="file"]), textarea, [contenteditable="true"]')) return;
    const activeDropzone = document.activeElement?.closest?.('[data-branding-dropzone]');
    if (!activeDropzone) return;
    const field = activeDropzone.dataset.brandingDropzone;
    const input = brandingFile(field);
    const preview = document.querySelector(`[data-branding-preview="${field}"]`);
    const wrapper = document.querySelector(`[data-branding-preview-wrap="${field}"]`);
    const clipboardFiles = Array.from(event.clipboardData?.files || []);
    const directFile = clipboardFiles.find((entry) => entry.type.startsWith('image/'));
    const itemFile = Array.from(event.clipboardData?.items || []).find((entry) => entry.kind === 'file' && entry.type.startsWith('image/'))?.getAsFile();
    const file = directFile || itemFile;
    if (!input || !file) return;
    event.preventDefault();
    const normalized = new File([file], 'pasted-branding-image.png', { type: file.type || 'image/png' });
    const transfer = new DataTransfer();
    transfer.items.add(normalized);
    input.files = transfer.files;
    if (preview) preview.src = URL.createObjectURL(normalized);
    wrapper?.classList.remove('d-none');
});
