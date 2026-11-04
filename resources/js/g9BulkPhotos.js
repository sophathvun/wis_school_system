import Swal from 'sweetalert2';
import {setupStaffLocationSelect} from './helpers/staffBirthplaceSelect.js';

const root = document.querySelector('[data-g9-bulk-photos]');
if (root) {
    const form = root.querySelector('[data-photo-upload-form]'), year = form.elements.academic_year_id;
    const files = form.querySelector('input[type=file]'), replace = form.elements.replace_existing;
    const previewButton = root.querySelector('[data-photo-preview]'), panel = root.querySelector('[data-photo-preview-panel]'), confirmButton = root.querySelector('[data-photo-confirm]');
    const progress = root.querySelector('[data-photo-progress]'), error = root.querySelector('[data-photo-error]'), success = root.querySelector('[data-photo-success]');
    let token = null, busy = false;
    [...year.options].forEach(option => option.dataset.en = option.textContent);
    const combo = setupStaffLocationSelect(form, year, {labelId: 'g9PhotoYearLabel', optionsId: 'g9PhotoYearOptions', locationLabel: 'Academic Year'});
    combo.sync(); year.closest('.premium-form-field').classList.toggle('has-value', Boolean(year.value));
    const lock = value => {
        busy = value; if (previewButton) previewButton.disabled = value; files.disabled = value || !previewButton;
        combo.toggle.disabled = value; if (replace) replace.disabled = value;
    };
    const invalidate = () => {token = null; panel.hidden = true; confirmButton.disabled = true; error.textContent = ''; success.textContent = '';};
    form.addEventListener('change', invalidate);
    const api = async (path, body) => {
        const response = await fetch(root.dataset.base + path, {method: 'POST', credentials: 'same-origin', headers: {Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}, body});
        const result = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join('\n') || result.message || 'The upload could not be processed. Check file sizes and server upload limits.');
        return result;
    };
    const cell = (row, value) => {const element = row.insertCell(); element.textContent = value || '-'; return element;};
    form.addEventListener('submit', async event => {
        event.preventDefault(); if (busy || !previewButton) return;
        if (!year.value || !files.files.length) {error.textContent = 'Select an academic year and choose photos or a ZIP file.'; return;}
        if (files.files.length > 200) {error.textContent = 'Use a ZIP file when uploading more than 200 individual images.'; return;}
        const body = new FormData(form); invalidate(); lock(true); progress.textContent = 'Uploading, matching and optimizing photos. Please wait...';
        try {
            const data = await api('/preview', body); token = data.token;
            const summary = root.querySelector('[data-photo-summary]'); summary.replaceChildren();
            [['images','Uploaded Images'],['ready','Ready'],['existing','Existing Preserved'],['unmatched','Unmatched Files'],['duplicates','Duplicate / Ambiguous'],['invalid','Invalid Images'],['missing','Missing Uploads']].forEach(([key,label]) => {
                const tile = document.createElement('div'), title = document.createElement('span'), count = document.createElement('strong'); title.textContent = label; count.textContent = data.summary[key]; tile.append(title,count); summary.append(tile);
            });
            const rows = root.querySelector('[data-photo-rows]'); rows.replaceChildren();
            data.rows.forEach(record => {
                const row = rows.insertRow(); cell(row, record.filename); cell(row, record.student_code); cell(row, [record.name_kh,record.name_en].filter(Boolean).join(' | '));
                const status = cell(row, ''); status.replaceChildren(); const badge = document.createElement('span'); badge.className = `badge bg-${record.status === 'ready' ? 'green' : record.status === 'existing' ? 'blue' : 'orange'}-lt`; badge.textContent = record.status; status.append(badge); cell(row, record.message);
            });
            const missing = root.querySelector('[data-photo-missing]'); missing.replaceChildren();
            data.missing.forEach(record => {const row = missing.insertRow(); cell(row,record.student_code); cell(row,record.name_en); cell(row,record.photo_filename);});
            panel.hidden = false; confirmButton.disabled = !data.summary.ready; progress.textContent = `${data.summary.ready} matched photos ready for confirmation.`;
        } catch (failure) {error.textContent = failure.message; progress.textContent = '';}
        finally {lock(false);}
    });
    confirmButton.addEventListener('click', async () => {
        if (busy || !token) return;
        const approval = await Swal.fire({icon: 'question', title: 'Upload matched student photos?', text: 'Only ready photos will be attached to candidates in the selected academic year.', showCancelButton: true, confirmButtonText: 'Upload Photos'});
        if (!approval.isConfirmed) return;
        const body = new FormData(); body.set('token',token); lock(true); confirmButton.disabled = true; error.textContent = ''; progress.textContent = 'Saving matched photos...';
        try {const result = await api('/confirm',body); token = null; panel.hidden = true; success.textContent = `${result.summary.ready} student photos uploaded successfully.`; progress.textContent = ''; files.value = '';}
        catch (failure) {error.textContent = failure.message; progress.textContent = 'Preview the files again before retrying.'; token = null;}
        finally {lock(false);}
    });
}
