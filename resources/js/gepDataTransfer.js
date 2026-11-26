import '../css/pages/gep-data-transfer.css';

for (const form of document.querySelectorAll('[data-gep-csv-import]')) {
    const input = form.querySelector('input[type="file"]');
    const zone = form.querySelector('[data-gep-csv-dropzone]');
    const list = form.querySelector('[data-gep-csv-file-list]');
    const error = form.querySelector('[data-gep-csv-error]');
    let selected = null;
    const setInput = () => {
        const transfer = new DataTransfer();
        if (selected) transfer.items.add(selected);
        input.files = transfer.files;
    };
    const fail = message => {
        error.textContent = message; error.hidden = false;
        zone.classList.add('is-invalid'); zone.setAttribute('aria-invalid', 'true');
    };
    const clearError = () => {
        error.textContent = ''; error.hidden = true;
        zone.classList.remove('is-invalid'); zone.removeAttribute('aria-invalid');
    };
    const render = () => {
        list.replaceChildren(); zone.classList.toggle('has-file', !!selected);
        if (!selected) return;
        const item = document.createElement('div'); item.className = 'premium-document-file-item';
        item.innerHTML = '<span class="document-file-icon"><i class="ti ti-file-spreadsheet" aria-hidden="true"></i></span><div class="document-file-details"><div class="document-file-name"></div><div class="document-file-meta"></div></div><button type="button" class="document-file-remove" aria-label="Remove selected file"><i class="ti ti-x" aria-hidden="true"></i></button>';
        item.querySelector('.document-file-name').textContent = selected.name;
        const size = selected.size < 1048576 ? `${Math.ceil(selected.size / 1024)} KB` : `${(selected.size / 1048576).toFixed(2)} MB`;
        item.querySelector('.document-file-meta').textContent = `${size} · Ready to preview or apply`;
        item.querySelector('button').addEventListener('click', () => {
            selected = null; input.value = ''; clearError(); render(); zone.focus();
        });
        list.append(item);
    };
    const choose = files => {
        if (!files?.length) { setInput(); return; }
        let message = '';
        if (files.length !== 1) message = 'Choose one CSV file at a time.';
        else if (!/\.(csv|txt)$/i.test(files[0].name)) message = 'Choose a CSV or TXT file.';
        else if (files[0].size > 25 * 1024 * 1024) message = 'The file must be 25 MB or smaller.';
        else if (!files[0].size) message = 'This file is empty. Choose a CSV containing records.';
        if (message) { setInput(); fail(message); return; }
        selected = files[0]; setInput(); clearError(); render();
    };
    input.hidden = true; input.required = false; zone.hidden = false;
    zone.addEventListener('click', () => input.click());
    zone.addEventListener('keydown', event => {
        if (['Enter', ' '].includes(event.key)) { event.preventDefault(); input.click(); }
    });
    input.addEventListener('change', () => choose(input.files));
    zone.addEventListener('dragover', event => { event.preventDefault(); zone.classList.add('is-dragging'); });
    zone.addEventListener('dragleave', event => { if (!zone.contains(event.relatedTarget)) zone.classList.remove('is-dragging'); });
    zone.addEventListener('drop', event => { event.preventDefault(); zone.classList.remove('is-dragging'); choose(event.dataTransfer?.files); });
    zone.addEventListener('paste', event => {
        const clipboard = event.clipboardData;
        const files = clipboard?.files?.length ? [...clipboard.files] : [...(clipboard?.items || [])].filter(item => item.kind === 'file').map(item => item.getAsFile()).filter(Boolean);
        if (files.length) { event.preventDefault(); choose(files); }
    });
    form.addEventListener('submit', event => {
        if (!selected) { event.preventDefault(); fail('Choose a CSV file first.'); zone.focus(); }
        // Keep native submission, including the Preview/Apply button value and CSRF token.
    });
}
