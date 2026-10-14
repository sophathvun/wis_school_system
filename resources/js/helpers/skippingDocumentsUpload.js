const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]);
const fileKey = (file) => `${file.name}:${file.size}:${file.lastModified}`;
const fileSize = (bytes) => bytes < 1024 * 1024 ? `${Math.max(1, Math.round(bytes / 1024))} KB` : `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
const clipboardFiles = (clipboard) => {
    const files = Array.from(clipboard?.files || []);
    return files.length ? files : Array.from(clipboard?.items || []).filter((item) => item.kind === 'file').map((item) => item.getAsFile()).filter(Boolean);
};

export const initSkippingDocumentsUpload = (wrapper) => {
    const input = wrapper.querySelector('[data-documents-input]');
    const zone = wrapper.querySelector('[data-documents-dropzone]');
    const list = wrapper.querySelector('[data-documents-file-list]');
    const status = wrapper.querySelector('[data-documents-status]');
    const existingCount = Number(wrapper.dataset.existingCount || 0);
    let selected = [];
    const parentSigned = new Set();
    const previewUrls = new Map();
    const previewUrl = (file) => {
        const key = fileKey(file);
        if (!previewUrls.has(key)) {
            const extension = file.name.split('.').pop().toLowerCase();
            const type = { pdf: 'application/pdf', jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png', webp: 'image/webp' }[extension];
            previewUrls.set(key, URL.createObjectURL(new Blob([file], { type })));
        }
        return previewUrls.get(key);
    };
    const releasePreview = (key) => {
        if (previewUrls.has(key)) URL.revokeObjectURL(previewUrls.get(key));
        previewUrls.delete(key);
    };
    const editable = () => !input.disabled && !input.form?.dataset.busy;
    const setStatus = (message = '') => {
        status.textContent = message;
        zone.classList.toggle('is-invalid', Boolean(message));
    };
    const render = () => {
        list.innerHTML = selected.map((file, index) => `
            <div class="premium-document-file-item document-file-card">
                <span class="document-file-icon"><i class="ti ${/\.pdf$/i.test(file.name) ? 'ti-file-type-pdf' : 'ti-photo'}"></i></span>
                <div class="document-file-details"><a class="document-file-open d-block" href="${previewUrl(file)}" target="_blank" rel="noopener" aria-label="View ${escapeHtml(file.name)}"><span class="document-file-name d-block">${escapeHtml(file.name)}</span></a><div class="document-file-meta">${fileSize(file.size)} &middot; Ready to upload</div><label class="document-parent-signed"><input type="checkbox" class="form-check-input" data-documents-parent-signed="${index}" ${parentSigned.has(fileKey(file)) ? 'checked' : ''}> Parent-signed Request</label></div>
                <button type="button" class="document-file-remove" data-documents-remove="${index}" aria-label="Remove ${escapeHtml(file.name)}"><i class="ti ti-x"></i></button>
            </div>`).join('');
        try {
            const transfer = new DataTransfer();
            selected.forEach((file) => transfer.items.add(file));
            input.files = transfer.files;
        } catch {
            // Submission below also uses the queue directly, including pasted files.
            input.value = '';
        }
    };
    const addFiles = (files) => {
        if (!editable()) return;
        const keys = new Set(selected.map(fileKey));
        const errors = [];
        for (const file of Array.from(files || [])) {
            if (!/\.(pdf|jpe?g|png|webp)$/i.test(file.name)) {
                errors.push('Please choose PDF, JPG, PNG, or WEBP files.');
            } else if (file.size > 25 * 1024 * 1024) {
                errors.push('Each file must be 25 MB or smaller.');
            } else if (!keys.has(fileKey(file))) {
                if (selected.length >= 10) errors.push('Upload at most 10 files at a time.');
                else if (existingCount + selected.length >= 20) errors.push('A request can have up to 20 supporting documents.');
                else {
                    selected.push(file);
                    keys.add(fileKey(file));
                }
            }
        }
        render();
        wrapper.querySelectorAll('[data-skipping-error]').forEach((element) => element.remove());
        setStatus([...new Set(errors)].join(' '));
    };
    zone.addEventListener('click', (event) => {
        if (!editable() || event.target === input || event.target.closest('a, button, input, label')) return;
        zone.focus({ preventScroll: true });
        input.click();
    });
    zone.addEventListener('keydown', (event) => {
        if (event.target === zone && ['Enter', ' '].includes(event.key) && editable()) {
            event.preventDefault();
            input.click();
        }
    });
    zone.addEventListener('dragover', (event) => {
        event.preventDefault();
        if (editable()) zone.classList.add('is-dragging');
    });
    zone.addEventListener('dragleave', (event) => {
        if (!zone.contains(event.relatedTarget)) zone.classList.remove('is-dragging');
    });
    zone.addEventListener('drop', (event) => {
        event.preventDefault();
        zone.classList.remove('is-dragging');
        if (editable()) zone.focus({ preventScroll: true });
        addFiles(event.dataTransfer?.files);
    });
    zone.addEventListener('paste', (event) => {
        const files = clipboardFiles(event.clipboardData);
        if (!files.length || !editable()) return;
        event.preventDefault();
        addFiles(files);
    });
    input.addEventListener('change', () => addFiles(input.files));
    list.addEventListener('change', (event) => {
        const checkbox = event.target.closest('[data-documents-parent-signed]');
        if (!checkbox || !editable()) return;
        const file = selected[Number(checkbox.dataset.documentsParentSigned)];
        if (!file) return;
        if (checkbox.checked) parentSigned.add(fileKey(file));
        else parentSigned.delete(fileKey(file));
    });
    list.addEventListener('click', (event) => {
        const button = event.target.closest('[data-documents-remove]');
        if (!button || !editable()) return;
        const index = Number(button.dataset.documentsRemove);
        if (!Number.isInteger(index) || index < 0 || index >= selected.length) return;
        releasePreview(fileKey(selected[index]));
        parentSigned.delete(fileKey(selected[index]));
        selected.splice(index, 1);
        render();
        setStatus();
        zone.focus({ preventScroll: true });
    });
    input.form?.addEventListener('reset', () => {
        [...previewUrls.keys()].forEach(releasePreview);
        selected = [];
        parentSigned.clear();
        render();
        setStatus();
    });
    globalThis.addEventListener?.('pagehide', () => [...previewUrls.keys()].forEach(releasePreview), { once: true });
    return {
        form: input.form,
        appendTo: (data) => {
            data.delete(input.name);
            selected.forEach((file) => data.append(input.name, file, file.name));
            selected.forEach((file, index) => {
                if (parentSigned.has(fileKey(file))) data.append('parent_signed_documents[]', String(existingCount + index));
            });
        },
    };
};
