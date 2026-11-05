import Swal from 'sweetalert2';

const root = document.querySelector('[data-g9-imports]');
if (root) {
    const form = root.querySelector('[data-g9-import-form]'), preview = root.querySelector('[data-g9-preview-panel]');
    const error = root.querySelector('[data-g9-import-error]'), success = root.querySelector('[data-g9-import-success]');
    const confirm = root.querySelector('[data-g9-confirm]'), button = root.querySelector('[data-g9-preview]');
    let token = null, busy = false;
    const api = async (path, body) => {
        const response = await fetch(root.dataset.base + path, {method: body ? 'POST' : 'GET', credentials: 'same-origin', headers: {Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}, body});
        const result = await response.json();
        if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join('\n') || result.message || 'Import unavailable.');
        return result;
    };
    const cell = (row, value) => {const element = row.insertCell(); element.textContent = value ?? '-'; return element;};
    const history = async () => {
        const rows = root.querySelector('[data-g9-import-history]');
        if(!rows)return;
        try {
            const batches = await api('/history'); rows.replaceChildren();
            batches.forEach(batch => {
                const summary = JSON.parse(batch.summary), row = rows.insertRow();
                [batch.filename, batch.created_at, batch.status, summary.new, summary.updates, summary.errors.length, (summary.warnings || []).length].forEach(value => cell(row, value));
            });
            if (!batches.length) {const row = rows.insertRow(); const empty = cell(row, 'No imports yet.'); empty.colSpan = 7; empty.className = 'text-center text-secondary py-4';}
        } catch (failure) {root.querySelector('[data-g9-history-error]').textContent = failure.message;}
    };
    if(form){
    form.elements.file.addEventListener('change', () => {token = null; preview.hidden = true; confirm.disabled = true; success.textContent = ''; error.textContent = '';});
    form.addEventListener('submit', async event => {
        event.preventDefault(); if (busy) return;
        busy = true; token = null; preview.hidden = true; error.textContent = ''; success.textContent = ''; button.disabled = true;
        try {
            const upload = new FormData(form); form.elements.file.disabled = true;
            const result = await api('/preview', upload); token = result.token;
            const summary = root.querySelector('[data-g9-summary]'); summary.replaceChildren();
            [['Total Rows', result.summary.total], ['New', result.summary.new], ['Updates', result.summary.updates], ['Rows With Errors', result.summary.errors.length]].forEach(([label, value]) => {const tile = document.createElement('div'); const name = document.createElement('span'); name.textContent = label; const count = document.createElement('strong'); count.textContent = value; tile.append(name, count); summary.append(tile);});
            const errors = root.querySelector('[data-g9-preview-errors]'); errors.replaceChildren();
            result.summary.errors.forEach(issue => {const line = document.createElement('p'); line.className = 'text-danger small mb-2'; line.textContent = `Row ${issue.row} (${issue.student_code || 'No ID'}): ${issue.messages.join(' ')}`; errors.append(line);});
            (result.summary.warnings || []).forEach(issue => {const line = document.createElement('p'); line.className = 'text-warning small mb-2'; line.textContent = `Review row ${issue.row}: ${issue.message}`; errors.append(line);});
            const rows = root.querySelector('[data-g9-preview-rows]'); rows.replaceChildren();
            result.rows.forEach(record => {const row = rows.insertRow(); [record.row, record.action, record.student_code, record.name_kh, record.name_en].forEach(value => cell(row, value));});
            preview.hidden = false; confirm.disabled = result.summary.errors.length > 0 || result.summary.total === 0;
            await history();
        } catch (failure) {error.textContent = failure.message;} finally {busy = false; button.disabled = false; form.elements.file.disabled = false;}
    });
    confirm.addEventListener('click', async () => {
        if (busy || !token) return;
        const approval = await Swal.fire({icon: 'question', title: 'Import G9 candidates?', text: 'Add new records and update matching candidates using the previewed data.', showCancelButton: true, confirmButtonText: 'Import Candidates'});
        if (!approval.isConfirmed) return;
        busy = true; confirm.disabled = true; button.disabled = true; form.elements.file.disabled = true; error.textContent = '';
        try {
            const data = new FormData(); data.set('token', token);
            const result = await api('/confirm', data); token = null; preview.hidden = true;
            success.textContent = `Imported successfully: ${result.summary.new} added, ${result.summary.updates} updated.`;
            await history();
        } catch (failure) {error.textContent = failure.message; token = null; preview.hidden = true;}
        finally {busy = false; button.disabled = false; form.elements.file.disabled = false;}
    });
    }
    history();
}
