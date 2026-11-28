const root = document.querySelector('[data-attendance-transfer]');
if (root) {
    const base = root.dataset.base.replace(/\/$/, '');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const find = s => root.querySelector(s);
    const error = find('[data-transfer-error]');
    const upload = find('[data-transfer-upload]');
    const fileInput = upload?.querySelector('input[type=file]');
    const drop = find('[data-transfer-drop]');
    let selected, busy = false, batch, page = 1, lastPage = 1, historyPage = 1, historyLastPage = 1;
    const fail = e => { error.textContent = e.message || 'The request failed. Please retry.'; error.hidden = false; };
    const cell = (tr, value) => { const td = document.createElement('td'); td.textContent = value ?? ''; tr.append(td); return td; };
    const json = async (url, options = {}) => {
        const response = await fetch(url, { ...options, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, ...options.headers } });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || `Request failed (${response.status}).`);
        return data;
    };
    const setBusy = value => {
        busy = value;
        root.querySelectorAll('button').forEach(button => { button.disabled = value; });
        if (fileInput) fileInput.disabled = value;
        drop?.setAttribute('aria-disabled', String(value));
    };
    const progress = (title, percent, detail) => {
        find('[data-transfer-progress]').hidden = false;
        find('[data-progress-title]').textContent = title;
        find('[data-progress-value]').textContent = percent === null ? 'Please wait' : `${percent}%`;
        find('[data-progress-detail]').textContent = detail;
        const bar = find('[role=progressbar]');
        if (percent === null) bar.removeAttribute('aria-valuenow'); else bar.setAttribute('aria-valuenow', percent);
        bar.firstElementChild.style.width = `${percent ?? 100}%`;
        bar.firstElementChild.classList.toggle('progress-bar-striped', percent === null);
        bar.firstElementChild.classList.toggle('progress-bar-animated', percent === null);
    };
    const render = data => {
        batch = data.batch.id; page = data.rows.current_page; lastPage = data.rows.last_page;
        find('[data-transfer-preview]').hidden = false;
        find('[data-batch-name]').textContent = data.batch.filename;
        const counts = find('[data-transfer-counts]'); counts.replaceChildren();
        for (const [key, label, color] of [['total', 'Total rows', ''], ['pending', 'Ready', 'text-primary'], ['imported', 'Imported', 'text-success'], ['review', 'Needs review', 'text-warning'], ['duplicate', 'Duplicates skipped', 'text-secondary']]) {
            const card = document.createElement('div'), title = document.createElement('span'), value = document.createElement('strong');
            title.textContent = label; value.textContent = key === 'total' ? data.total : (data.counts[key] || 0); value.className = color; card.append(title, value); counts.append(card);
        }
        const tbody = find('[data-preview-rows]'); tbody.replaceChildren();
        const labels = { pending: 'Ready', imported: 'Imported', review: 'Needs review', duplicate: 'Duplicate' };
        for (const row of data.rows.data) {
            const tr = document.createElement('tr');
            const periodDetail=row.auto_periods?`Calculated from: ${row.periods.map(period=>`${period.name} (${period.starts_at}–${period.ends_at})`).join(', ')}`:'';
            for (const value of [row.csv_row, row.source.student_id, row.source.academic_year, row.source.attendance_date, row.source.attendance, row.missed_sessions??'—', labels[row.outcome], [row.reason,periodDetail].filter(Boolean).join(' · ')]) cell(tr, value);
            tbody.append(tr);
        }
        find('[data-review-download]').href = data.review_url;
        find('[data-confirm-import]').disabled = busy || !data.counts.pending;
        find('[data-confirm-import]').textContent = data.batch.status === 'importing' ? 'Resume import' : 'Import valid rows';
        find('[data-preview-page]').textContent = `Page ${page} of ${lastPage}`;
        find('[data-preview-prev]').disabled = busy || page <= 1;
        find('[data-preview-next]').disabled = busy || page >= lastPage;
        return data;
    };
    const inspect = (id, targetPage = 1) => json(`${base}/${encodeURIComponent(id)}?page=${targetPage}`).then(render);
    const history = async () => {
        const data = await json(`${base}/history?page=${historyPage}`); historyLastPage = data.last_page;
        const tbody = find('[data-transfer-history]'); tbody.replaceChildren();
        for (const record of data.data) {
            const tr = document.createElement('tr'); cell(tr, record.filename); cell(tr, record.created_at); cell(tr, record.status);
            const button = document.createElement('button'); button.type = 'button'; button.className = 'btn btn-sm btn-outline-primary'; button.textContent = 'Review'; button.disabled = busy;
            button.addEventListener('click', () => { if (!busy) inspect(record.id).then(() => find('[data-transfer-preview]').scrollIntoView({ block: 'start', behavior: 'smooth' })).catch(fail); });
            cell(tr, '').append(button); tbody.append(tr);
        }
        if (!data.data.length) { const tr = document.createElement('tr'); cell(tr, 'No imports yet.').colSpan = 4; tbody.append(tr); }
        find('[data-history-page]').textContent = `Page ${historyPage} of ${historyLastPage}`;
        find('[data-history-prev]').disabled = busy || historyPage <= 1; find('[data-history-next]').disabled = busy || historyPage >= historyLastPage;
    };
    const choose = file => {
        if (busy || !file) return;
        if (!/\.csv$/i.test(file.name) || file.size > 12 * 1024 * 1024) { fail(new Error('Choose one CSV file up to 12 MB.')); return; }
        selected = file; error.hidden = true;
        find('[data-selected-file]').hidden = false;
        find('[data-file-name]').textContent = `${file.name} · ${(file.size / 1024 / 1024).toFixed(2)} MB`;
    };
    if (drop) {
        fileInput.hidden = true; fileInput.required = false; drop.hidden = false;
        fileInput.addEventListener('change', () => choose(fileInput.files[0]));
        drop.addEventListener('click', () => { if (!busy) fileInput.click(); });
        drop.addEventListener('keydown', event => { if (!busy && ['Enter', ' '].includes(event.key)) { event.preventDefault(); fileInput.click(); } });
        for (const type of ['dragover', 'dragenter']) drop.addEventListener(type, event => { event.preventDefault(); if (!busy) drop.classList.add('is-dragover'); });
        for (const type of ['dragleave', 'drop']) drop.addEventListener(type, event => { event.preventDefault(); drop.classList.remove('is-dragover'); });
        drop.addEventListener('drop', event => { if (event.dataTransfer.files.length !== 1) return fail(new Error('Choose one CSV file.')); choose(event.dataTransfer.files[0]); });
        drop.addEventListener('paste', event => { if (event.clipboardData.files.length) { event.preventDefault(); choose(event.clipboardData.files[0]); } });
        find('[data-clear-file]').addEventListener('click', () => { if (!busy) { selected = null; fileInput.value = ''; find('[data-selected-file]').hidden = true; drop.focus(); } });
        upload.addEventListener('submit', async event => {
            event.preventDefault(); if (busy) return;
            if (!selected) return fail(new Error('Choose an attendance CSV first.'));
            error.hidden = true; setBusy(true); progress('Uploading file', 0, 'Your file is being uploaded.');
            try {
                const data = await new Promise((resolve, reject) => {
                    const xhr = new XMLHttpRequest(); xhr.open('POST', `${base}/preview`); xhr.setRequestHeader('Accept', 'application/json'); xhr.setRequestHeader('X-CSRF-TOKEN', csrf);
                    xhr.upload.onprogress = e => { if (e.lengthComputable) progress('Uploading file', Math.round(e.loaded / e.total * 100), 'Uploading attendance CSV.'); };
                    xhr.upload.onload = () => progress('Validating records', null, 'Checking Student IDs, enrollment dates, permissions, and duplicates. No attendance has been saved yet.');
                    xhr.onload = () => { let body; try { body = JSON.parse(xhr.responseText); } catch { return reject(new Error('The server did not return a valid response. Check import history before retrying.')); } if (xhr.status >= 200 && xhr.status < 300) resolve(body); else reject(new Error(Object.values(body.errors || {}).flat().join(' ') || body.message || 'Upload failed.')); };
                    xhr.onerror = () => reject(new Error('Connection interrupted. Check import history before retrying.'));
                    const form = new FormData(); form.append('file', selected); xhr.send(form);
                });
                setBusy(false); render(data); progress('Preview ready', 100, 'Review the results below. Confirm to import Ready rows.'); await history();
            } catch (e) { setBusy(false); progress('Upload or validation stopped', 0, 'Check the error below. No attendance is saved until you confirm a preview.'); fail(e); }
        });
    }
    find('[data-confirm-import]').addEventListener('click', async () => {
        if (busy || !batch) return;
        const confirmed = window.Swal ? (await window.Swal.fire({ title: 'Import valid attendance rows?', text: 'Existing periods are preserved. New non-overlapping periods may be added to the same day. Review rows and duplicates are skipped.', icon: 'question', showCancelButton: true, confirmButtonText: 'Import valid rows' })).isConfirmed : window.confirm('Import valid rows? Existing periods are preserved; new non-overlapping periods may be added.');
        if (!confirmed) return;
        error.hidden = true; setBusy(true);
        try {
            let data = await json(`${base}/${batch}`), pending = data.counts.pending || 0, initial = pending;
            while (pending) {
                progress('Importing attendance', Math.round((initial - pending) / initial * 100), `${initial - pending} of ${initial} ready rows processed. Keep this page open.`);
                data = await json(`${base}/${batch}/confirm`, { method: 'POST' }); pending = data.counts.pending || 0; render(data);
            }
            setBusy(false); render(data); progress('Import completed', 100, `${data.counts.imported || 0} imported · ${data.counts.review || 0} need review · ${data.counts.duplicate || 0} duplicates skipped.`); await history();
            if (window.Swal) window.Swal.fire({ title: 'Attendance import completed', text: 'Review the summary and download any rows needing attention.', icon: 'success' });
        } catch (e) { setBusy(false); progress('Import paused', null, 'Saved rows are retained. Review this batch and resume when ready.'); fail(e); await inspect(batch).catch(() => {}); }
    });
    find('[data-preview-prev]').addEventListener('click', () => { if (!busy && page > 1) inspect(batch, page - 1).catch(fail); });
    find('[data-preview-next]').addEventListener('click', () => { if (!busy && page < lastPage) inspect(batch, page + 1).catch(fail); });
    find('[data-history-prev]').addEventListener('click', () => { if (!busy && historyPage > 1) { historyPage--; history().catch(fail); } });
    find('[data-history-next]').addEventListener('click', () => { if (!busy && historyPage < historyLastPage) { historyPage++; history().catch(fail); } });
    window.addEventListener('beforeunload', event => { if (busy) { event.preventDefault(); event.returnValue = ''; } });
    history().catch(fail);
}
