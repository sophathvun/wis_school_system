const forms = [...document.querySelectorAll('[data-csv-import]')];
let importing = false;
const fileSize = bytes => bytes < 1048576 ? `${Math.ceil(bytes / 1024)} KB` : `${(bytes / 1048576).toFixed(2)} MB`;
const modalMarkup = `<div class="csv-import-progress text-start" data-import-modal>
    <div class="d-flex justify-content-between align-items-center gap-2"><strong data-live-stage>Uploading file</strong><span class="csv-import-percent" data-live-percent>0%</span></div>
    <div class="progress mt-2" role="progressbar" aria-label="Import progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><div class="progress-bar" data-live-bar style="width:0%"></div></div>
    <div class="text-secondary small mt-2" data-live-detail aria-live="polite"></div>
    <div class="csv-import-counts mt-3" data-live-counts hidden><span>Imported <strong class="text-success" data-live-imported>0</strong></span><span>Needs review <strong class="text-warning" data-live-failed>0</strong></span></div>
    </div><p class="small text-secondary mt-3 mb-0">Keep this page open until the import finishes.</p>`;

for (const form of forms) {
    const input = form.querySelector('input[type="file"]');
    const zone = form.querySelector('[data-csv-dropzone]');
    const list = form.querySelector('[data-csv-file-list]');
    const error = form.querySelector('[data-csv-error]');
    const panel = form.querySelector('[data-csv-progress]');
    let selected = null;
    let busy = false;
    let pollTimer;
    let pollController;
    let finished = false;
    input.hidden = true;
    input.required = false;
    zone.hidden = false;
    const fail = message => { error.textContent = message; error.hidden = false; zone.classList.add('is-invalid'); };
    const clearError = () => { error.textContent = ''; error.hidden = true; zone.classList.remove('is-invalid'); };
    const renderFile = () => {
        zone.classList.toggle('has-file', !!selected);
        list.replaceChildren();
        if (!selected) return;
        const item = document.createElement('div');
        item.className = 'premium-document-file-item';
        item.innerHTML = '<span class="document-file-icon"><i class="ti ti-file-spreadsheet"></i></span><div class="document-file-details"><div class="document-file-name"></div><div class="document-file-meta"></div></div><button type="button" class="document-file-remove" aria-label="Remove selected file"><i class="ti ti-x"></i></button>';
        item.querySelector('.document-file-name').textContent = selected.name;
        item.querySelector('.document-file-meta').textContent = `${fileSize(selected.size)} · Ready to import`;
        item.querySelector('button').addEventListener('click', event => {
            event.stopPropagation();
            if (importing) return;
            selected = null; input.value = ''; renderFile(); clearError(); zone.focus();
        });
        list.append(item);
    };
    const choose = files => {
        if (importing || !files?.length) return;
        if (files.length !== 1) { fail('Choose one CSV file at a time.'); return; }
        const file = files[0];
        if (!/\.(csv|txt)$/i.test(file.name)) { fail('Choose a CSV or TXT file.'); return; }
        if (file.size > 10 * 1024 * 1024) { fail('The file must be 10 MB or smaller.'); return; }
        if (!file.size) { fail('This file is empty. Choose a CSV containing records.'); return; }
        selected = file; clearError(); renderFile();
    };
    zone.addEventListener('click', event => { if (!importing && !event.target.closest('.document-file-remove')) input.click(); });
    zone.addEventListener('keydown', event => {
        if (event.target.closest('.document-file-remove')) return;
        if (['Enter', ' '].includes(event.key)) { event.preventDefault(); if (!importing) input.click(); }
    });
    input.addEventListener('change', () => choose(input.files));
    zone.addEventListener('dragover', event => { event.preventDefault(); if (!importing) zone.classList.add('is-dragging'); });
    zone.addEventListener('dragleave', event => { if (!zone.contains(event.relatedTarget)) zone.classList.remove('is-dragging'); });
    zone.addEventListener('drop', event => { event.preventDefault(); zone.classList.remove('is-dragging'); choose(event.dataTransfer?.files); });
    zone.addEventListener('paste', event => {
        const clipboard = event.clipboardData;
        const files = clipboard?.files?.length ? [...clipboard.files] : [...(clipboard?.items || [])].filter(item => item.kind === 'file').map(item => item.getAsFile()).filter(Boolean);
        if (files.length) { event.preventDefault(); choose(files); }
    });
    const paint = ({stage, percent, detail, counts}) => {
        const modal = window.Swal?.getHtmlContainer()?.querySelector('[data-import-modal]');
        for (const [root, prefix] of [[panel, 'csv'], [modal, 'live']]) {
            if (!root) continue;
            const get = key => root.querySelector(`[data-${prefix}-${key}]`);
            root.classList.toggle('is-waiting', percent === null);
            get('stage').textContent = stage;
            get('percent').textContent = percent === null ? '—' : `${percent}%`;
            get('detail').textContent = detail;
            get('bar').style.width = `${percent ?? 0}%`;
            const progress = root.querySelector('[role="progressbar"]');
            if (percent === null) progress.removeAttribute('aria-valuenow');
            else progress.setAttribute('aria-valuenow', String(percent));
            progress.setAttribute('aria-valuetext', percent === null ? stage : `${stage}: ${percent}%`);
            get('counts').hidden = !counts;
            if (counts) { get('imported').textContent = counts.imported.toLocaleString(); get('failed').textContent = counts.failed.toLocaleString(); }
        }
    };
    const lock = value => {
        importing = value; busy = value;
        form.setAttribute('aria-busy', String(value));
        for (const other of forms) {
            other.querySelector('button[type="submit"]').disabled = value;
            other.querySelector('input[type="file"]').disabled = value;
            other.querySelector('[data-csv-dropzone]').setAttribute('aria-disabled', String(value));
            other.querySelectorAll('.document-file-remove').forEach(button => button.disabled = value);
        }
    };
    const stopPolling = () => { finished = true; clearTimeout(pollTimer); pollController?.abort(); };
    const poll = async () => {
        if (finished) return;
        pollController = new AbortController();
        const timeout = setTimeout(() => pollController.abort(), 8000);
        try {
            const response = await fetch(form.dataset.progressUrl, {credentials: 'omit', cache: 'no-store', headers: {Accept: 'application/json'}, signal: pollController.signal});
            if (!response.ok) throw new Error('Progress unavailable');
            const data = await response.json();
            if (finished) return;
            if (data.phase === 'processing' || data.phase === 'complete') {
                paint({stage: data.processed === data.total ? 'Finalizing results' : 'Importing records', percent: data.percent,
                    detail: `${data.processed.toLocaleString()} of ${data.total.toLocaleString()} rows processed`, counts: data});
            } else paint({stage: 'Preparing import', percent: null, detail: 'File uploaded. Reading and checking CSV columns…'});
        } catch {
            if (!finished) paint({stage: 'Import in progress', percent: null, detail: 'Waiting for a progress update. Your import request is still running.'});
        } finally {
            clearTimeout(timeout);
            if (!finished) pollTimer = setTimeout(poll, 750);
        }
    };
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (importing || busy) return;
        if (!selected) { fail('Choose a CSV file to import.'); zone.focus(); return; }
        clearError(); panel.hidden = false; finished = false;
        const payload = new FormData(form);
        payload.set('file', selected, selected.name);
        lock(true);
        if (window.Swal) {
            const dark = document.documentElement.dataset.bsTheme === 'dark';
            void window.Swal.fire({titleText: `Importing ${form.dataset.importTitle}`, html: modalMarkup, width: 560,
                background: dark ? '#1f2937' : '#fff', color: dark ? '#f8fafc' : '#1f2937',
                showConfirmButton: false, allowOutsideClick: false, allowEscapeKey: false});
        }
        paint({stage: 'Uploading file', percent: 0, detail: `0 KB of ${fileSize(selected.size)}`});
        let serverStarted = false;
        try {
            const result = await new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', form.action);
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.upload.addEventListener('progress', upload => {
                    paint({stage: 'Uploading file', percent: upload.lengthComputable ? Math.floor(upload.loaded * 100 / upload.total) : null,
                        detail: upload.lengthComputable ? `${fileSize(upload.loaded)} of ${fileSize(upload.total)}` : 'Sending your file…'});
                });
                xhr.upload.addEventListener('load', () => {
                    serverStarted = true;
                    paint({stage: 'Preparing import', percent: null, detail: 'File uploaded. Reading and checking CSV columns…'});
                    pollTimer = setTimeout(poll, 250);
                });
                xhr.addEventListener('load', () => {
                    let data;
                    try { data = JSON.parse(xhr.responseText); } catch { reject(new Error('The server did not return an import result. Refresh the page and review records before retrying.')); return; }
                    if (xhr.status >= 200 && xhr.status < 300 && data.redirect) resolve(data);
                    else reject(new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'The import could not be completed.'));
                });
                xhr.addEventListener('error', () => reject(new Error(serverStarted ? 'The connection was interrupted. Some rows may have imported. Refresh the page and review results before retrying.' : 'The upload could not reach the server. Check your connection and try again.')));
                xhr.addEventListener('abort', () => reject(new Error('The import connection was interrupted. Review results before retrying.')));
                xhr.send(payload);
            });
            stopPolling();
            paint({stage: 'Import complete', percent: 100, detail: `${result.total.toLocaleString()} rows processed`, counts: result});
            importing = false;
            window.location.assign(result.redirect);
        } catch (exception) {
            stopPolling(); lock(false);
            paint({stage: 'Import needs attention', percent: null, detail: exception.message});
            error.textContent = exception.message; error.hidden = false;
            if (window.Swal) await window.Swal.fire({icon: 'error', titleText: 'Import Needs Attention', text: exception.message});
        }
    });
}
window.addEventListener('beforeunload', event => { if (importing) { event.preventDefault(); event.returnValue = ''; } });
window.addEventListener('pageshow', event => { if (event.persisted) window.location.reload(); });
