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
        const transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));
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
    input.addEventListener('change', () => showPreview(input.files?.[0]));
});
