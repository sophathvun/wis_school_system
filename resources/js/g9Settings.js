import './khmer-calendar.js';
import {setupStaffLocationSelect} from './helpers/staffBirthplaceSelect.js';
import {enhanceSubjectTemporal} from './scholarshipTemporalInputs.js';

const root = document.querySelector('[data-g9-settings]');
if (root) {
    const year = root.querySelector('[data-setting-year]'), location = root.querySelector('[data-setting-location]');
    const form = root.querySelector('[data-date-settings]'), save = root.querySelector('[data-setting-save]'), message = root.querySelector('[data-setting-message]');
    const sections = new Map();
    const centers = root.querySelector('[data-g9-report-centers]');
    const centerSettings = () => [...centers.querySelectorAll('tr[data-center]')].map(row => ({exam_center: row.dataset.center,
        ...Object.fromEntries([...row.querySelectorAll('input[data-center-field]')].map(input => [input.dataset.centerField, input.value.trim()])),
        ...(row.signatureChanged ? {photo_signature: row.signatureValue || null} : {})}));
    let version = 0, dirty = false, busy = false, loading = false, lastYear = year.value;
    [...year.options].forEach(option => option.dataset.en = option.textContent);
    const combo = setupStaffLocationSelect(root, year, {labelId: 'g9SettingYearLabel', optionsId: 'g9SettingYearOptions', locationLabel: 'Academic Year'});
    const sync = () => {
        combo.sync();
        root.querySelectorAll('.premium-form-field').forEach(field => {
            const input = field.querySelector('select,input:not([type=hidden])');
            field.classList.toggle('has-value', input.tagName === 'SELECT' ? Boolean(input.selectedOptions[0]?.textContent) : Boolean(input.value));
        });
    };
    const changed = () => {if (!loading) {dirty = true; message.textContent = 'Unsaved changes.';} sync();};
    root.querySelectorAll('[data-date-section]').forEach(section => {
        const input = section.querySelector('[data-setting-date]');
        let picker;
        picker = enhanceSubjectTemporal(input, {onChange: () => {
            if (!loading && picker && picker.input.validity.valid) {
                const date = input.value;
                section.querySelector('[data-setting-solar]').value = date ? window.formatKhmerSolarDate(date, location.value.trim()) : '';
                section.querySelector('[data-setting-lunar]').value = date ? window.formatKhmerLunarDate(date) : '';
                changed();
            }
        }});
        section.querySelector('.premium-form-field>label').htmlFor = picker.input.id;
        sections.set(section.dataset.dateSection, {section, input, picker});
    });
    const api = async body => {
        const response = await fetch(root.dataset.base + '/dates' + (body ? '' : '?' + new URLSearchParams({academic_year_id: year.value})), {
            method: body ? 'POST' : 'GET', credentials: 'same-origin', headers: {Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, ...(body ? {'Content-Type': 'application/json'} : {})}, body: body ? JSON.stringify(body) : undefined,
        });
        const result = await response.json();
        if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join('\n') || result.message || 'Date settings unavailable.');
        return result;
    };
    const apply = data => {
        loading = true; version = data.version; location.value = data.location;
        centers.replaceChildren();
        (data.report_centers || []).forEach(center => {
            const row = centers.insertRow(); row.dataset.center = center.exam_center; row.insertCell().textContent = center.exam_center;
            [['location','City / Province'],['department','Education Department'],['signer','Signer Title']].forEach(([key,label]) => {
                const input = document.createElement('input'); input.className = 'form-control school-profile-khmer'; input.dataset.centerField = key;
                input.value = center[key] || ''; input.maxLength = key === 'department' ? 255 : 150; input.disabled = !data.can_manage;
                input.setAttribute('aria-label', `${label} - ${center.exam_center}`); row.insertCell().append(input);
            });
            const cell = row.insertCell(), preview = document.createElement('img'), file = document.createElement('input'), remove = document.createElement('button');
            const dropzone = document.createElement('button'), icon = document.createElement('i'), uploadTitle = document.createElement('strong'), uploadHint = document.createElement('span');
            dropzone.type = 'button'; dropzone.className = 'g9-signature-dropzone'; dropzone.disabled = !data.can_manage;
            dropzone.setAttribute('aria-label', `Upload signature for ${center.exam_center}`);
            icon.className = 'ti ti-cloud-upload'; icon.setAttribute('aria-hidden','true');
            uploadTitle.textContent = 'Drop signature here'; uploadHint.textContent = 'or click to browse · PNG / JPG · Max 2 MB';
            dropzone.append(icon,uploadTitle,uploadHint);
            preview.style.cssText = 'display:block;max-width:140px;max-height:65px;margin-bottom:8px'; preview.alt = 'Exam center signature'; preview.hidden = !center.photo_signature;
            if (center.photo_signature) preview.src = center.photo_signature;
            file.type = 'file'; file.accept = '.png,.jpg,.jpeg'; file.hidden = true; file.disabled = !data.can_manage;
            file.setAttribute('aria-label', `Photo signature - ${center.exam_center}`);
            let uploadVersion = 0;
            const readSignature = selected => {
                if (!selected || !data.can_manage || busy) return;
                if (!['image/png','image/jpeg'].includes(selected.type) || selected.size > 2 * 1024 * 1024) {message.textContent = 'Choose a PNG or JPG signature up to 2 MB.'; file.value = ''; return;}
                const selectedYear = year.value, currentUpload = ++uploadVersion;
                const reader = new FileReader(); reader.onload = () => {if (!row.isConnected || year.value !== selectedYear || currentUpload !== uploadVersion) return; row.signatureValue = reader.result; row.signatureChanged = true; preview.src = reader.result; preview.hidden = false; uploadTitle.textContent = selected.name; changed();};
                reader.onerror = () => {message.textContent = 'Signature image could not be read.';}; reader.readAsDataURL(selected);
            };
            file.addEventListener('change', () => readSignature(file.files[0]));
            dropzone.addEventListener('click', () => {if (!busy) file.click();});
            dropzone.addEventListener('dragover', event => {event.preventDefault(); if (data.can_manage && !busy) {event.dataTransfer.dropEffect = 'copy'; dropzone.classList.add('is-dragging');}});
            dropzone.addEventListener('dragleave', event => {if (!dropzone.contains(event.relatedTarget)) dropzone.classList.remove('is-dragging');});
            dropzone.addEventListener('drop', event => {
                event.preventDefault(); dropzone.classList.remove('is-dragging');
                if (!data.can_manage || busy) return;
                if (event.dataTransfer.files.length !== 1) {message.textContent = 'Drop one signature image per exam center.'; return;}
                readSignature(event.dataTransfer.files[0]);
            });
            remove.type = 'button'; remove.className = 'btn btn-sm btn-outline-danger mt-2'; remove.textContent = 'Remove Signature'; remove.disabled = !data.can_manage;
            remove.addEventListener('click', () => {if (busy) return; uploadVersion++; row.signatureValue = null; row.signatureChanged = true; preview.removeAttribute('src'); preview.hidden = true; file.value = ''; uploadTitle.textContent = 'Drop signature here'; changed();});
            cell.append(preview,dropzone,file,remove);
        });
        if (!centers.children.length) {const cell = centers.insertRow().insertCell(); cell.colSpan = 5; cell.className = 'text-center text-secondary'; cell.textContent = 'Import candidates to configure their exam centers.';}
        sections.forEach(({section, picker}, key) => {
            picker.setValue(data.dates[key].date);
            section.querySelector('[data-setting-solar]').value = data.dates[key].solar;
            section.querySelector('[data-setting-lunar]').value = data.dates[key].lunar;
        });
        dirty = false; lastYear = year.value; loading = false; sync();
    };
    const load = async () => {
        if (!year.value) {message.textContent = 'No academic years available.'; return;}
        busy = true; if (save) save.disabled = true; combo.toggle.disabled = true;
        try {apply(await api()); message.textContent = version ? 'Shared dates loaded for this academic year.' : 'No shared dates saved for this academic year yet.'; if (save) save.disabled = false;}
        catch (error) {message.textContent = error.message;}
        finally {busy = false; combo.toggle.disabled = false;}
    };
    year.addEventListener('change', () => {
        sections.forEach(({picker}) => picker.close());
        if (busy || dirty && !confirm('Discard unsaved date changes?')) {year.value = lastYear; sync(); return;}
        load();
    });
    form.addEventListener('input', event => {if (!event.target.closest('.date-picker')) changed();});
    location.addEventListener('input', () => {
        sections.forEach(({section, input, picker}) => {if (input.value && picker.valid()) section.querySelector('[data-setting-solar]').value = window.formatKhmerSolarDate(input.value, location.value.trim());});
        changed();
    });
    form.addEventListener('submit', async event => {
        event.preventDefault(); if (busy || !save) return;
        loading = true;
        const invalid = [...sections.values()].find(({picker}) => !picker.valid());
        loading = false;
        if (invalid) {invalid.picker.input.focus(); invalid.picker.input.reportValidity(); return;}
        const dates = {};
        sections.forEach(({section, input}, key) => dates[key] = {date: input.value, solar: section.querySelector('[data-setting-solar]').value, lunar: section.querySelector('[data-setting-lunar]').value});
        busy = true; save.disabled = true; combo.toggle.disabled = true;
        try {apply(await api({academic_year_id: year.value, version, location: location.value.trim(), dates, report_centers: centerSettings()})); message.textContent = 'Dates and exam-center report settings saved for this academic year.';}
        catch (error) {message.textContent = error.message;}
        finally {busy = false; save.disabled = false; combo.toggle.disabled = false;}
    });
    window.addEventListener('beforeunload', event => {if (dirty) {event.preventDefault(); event.returnValue = '';}});
    load();
}
