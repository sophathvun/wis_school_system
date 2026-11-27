import '../css/pages/gep-data-transfer.css';
import Swal from 'sweetalert2';
import {readGepTransferProgress} from './helpers/gep-transfer-progress.js';

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
        item.querySelector('.document-file-meta').textContent = `${size} · Ready to preview`;
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

if (document.querySelector('[data-gep-transfer-progress]')) {
    const dialog = document.createElement('dialog');
    dialog.className = 'gep-transfer-progress';
    dialog.setAttribute('aria-labelledby', 'gep-transfer-progress-title');
    dialog.setAttribute('aria-describedby', 'gep-transfer-progress-message');
    dialog.innerHTML = `<div class="gep-transfer-progress-icon"><i class="ti ti-file-spreadsheet" aria-hidden="true"></i></div>
        <div class="gep-transfer-progress-eyebrow">GEP Import/Export</div>
        <h2 id="gep-transfer-progress-title"></h2>
        <p id="gep-transfer-progress-message" role="status" aria-live="polite"></p>
        <div class="gep-transfer-progress-file"></div>
        <div class="gep-transfer-progress-track" role="progressbar" aria-label="CSV import progress"><span></span></div>
        <div class="gep-transfer-progress-footer"><span class="gep-transfer-progress-dot"></span>Processing<span>Please keep this page open.</span></div>`;
    document.body.append(dialog);
    dialog.addEventListener('cancel', event => event.preventDefault());
    let activeForm = null;
    let submitValue = null;
    const buttons = new Map();
    const reset = () => {
        if (dialog.open) dialog.close();
        activeForm?.removeAttribute('aria-busy');
        buttons.forEach((disabled, button) => { button.disabled = disabled; });
        buttons.clear(); submitValue?.remove(); submitValue = null; activeForm = null;
    };
    window.addEventListener('pageshow', reset);
    document.addEventListener('submit', async event => {
        const form = event.target;
        if (!form.matches('[data-gep-transfer-progress]') || event.defaultPrevented) return;
        if (activeForm) { event.preventDefault(); return; }
        event.preventDefault();
        activeForm = form;
        const applying = form.dataset.gepTransferProgress === 'apply';
        dialog.querySelector('h2').textContent = applying ? 'Applying your import' : 'Preparing your preview';
        const message = applying ? 'Rechecking your preview and saving records.' : 'Uploading your CSV, checking rows and matching records.';
        dialog.querySelector('[role="status"]').textContent = message;
        dialog.querySelector('[role="progressbar"]').setAttribute('aria-valuetext', message);
        dialog.querySelector('.gep-transfer-progress-file').textContent = form.dataset.gepTransferFilename || form.querySelector('input[type="file"]')?.files?.[0]?.name || '';
        // Preserve the clicked action before disabling buttons; disabled submitters are not posted.
        if (event.submitter?.name) {
            submitValue = document.createElement('input'); submitValue.type = 'hidden';
            submitValue.name = event.submitter.name; submitValue.value = event.submitter.value; form.append(submitValue);
        }
        document.querySelectorAll('[data-gep-transfer-progress] button').forEach(button => {
            buttons.set(button, button.disabled); button.disabled = true;
        });
        form.setAttribute('aria-busy', 'true');
        const track = dialog.querySelector('[role="progressbar"]'), bar = track.firstElementChild;
        track.removeAttribute('aria-valuenow');track.classList.remove('is-determinate');bar.style.width = '';
        dialog.showModal();
        try {
            const response = await fetch(form.action, {method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: {Accept: 'application/x-ndjson', 'X-GEP-Progress': '1'}});
            const complete = await readGepTransferProgress(response, message => {
                if (message.event !== 'progress') return;
                track.classList.add('is-determinate');bar.style.width = `${message.percent}%`;
                track.setAttribute('aria-valuenow', String(message.percent));track.setAttribute('aria-valuemin','0');track.setAttribute('aria-valuemax','100');
                const text = `${message.percent}% · ${message.processed.toLocaleString()} of ${message.total.toLocaleString()} records ${applying ? 'processed' : 'checked'}`;
                track.setAttribute('aria-valuetext',text);
                dialog.querySelector('[role="status"]').textContent = text;
                dialog.querySelector('h2').textContent = message.processed === message.total ? (applying ? 'Finalizing your import' : 'Finalizing your preview') : (applying ? 'Processing your import' : 'Checking your CSV');
            });
            reset();
            const result = complete.result;
            await Swal.fire({icon:'success',title:result.applied?'Import completed':'Preview ready',text:`${result.rows.toLocaleString()} records ${result.applied?'saved':'checked'}: ${result.created.toLocaleString()} new, ${result.updated.toLocaleString()} existing.${result.applied?'':' No records saved yet.'}`,confirmButtonText:result.applied?'Done':'Review preview',allowOutsideClick:false});
            window.location.assign(complete.redirect);
        } catch (error) {
            reset();
            await Swal.fire({icon:'error',title:'GEP import',text:error.message,confirmButtonText:'OK'});
        }
    });
}
