import {setupStaffLocationSelect} from './helpers/staffBirthplaceSelect.js';
import {renderPagination} from './helpers/pagination.js';
import {enhanceSubjectTemporal} from './scholarshipTemporalInputs.js';
import {Modal} from 'bootstrap';

const root = document.querySelector('[data-g9-candidates]');
if (root) {
    const form = root.querySelector('[data-g9-filters]');
    const toggle = root.querySelector('[data-g9-filter-toggle]');
    const mobile = matchMedia('(max-width:767.98px)');
    const combos = new Map();
    form.querySelectorAll('select').forEach(select => {
        [...select.options].forEach(option => option.dataset.en = option.textContent);
        combos.set(select, setupStaffLocationSelect(form, select, {
            labelId: select.id + 'Label', optionsId: select.id + 'Options', locationLabel: select.getAttribute('aria-label'),
        }));
    });
    const sync = () => {
        combos.forEach(combo => combo.sync());
        form.querySelectorAll('.premium-form-field').forEach(field => {
            const input = field.querySelector('select,input');
            field.classList.toggle('has-value', input.tagName === 'SELECT' ? Boolean(input.selectedOptions[0]?.textContent) : Boolean(input.value));
        });
    };
    const expand = expanded => {
        form.hidden = !expanded;
        toggle.setAttribute('aria-expanded', String(expanded));
        toggle.setAttribute('aria-label', `${expanded ? 'Collapse' : 'Expand'} filters`);
        toggle.querySelector('i').className = `ti ti-chevron-${expanded ? 'up' : 'down'}`;
        if (!expanded) combos.forEach(combo => combo.close());
    };
    expand(!mobile.matches);
    mobile.addEventListener('change', () => expand(!mobile.matches));
    toggle.addEventListener('click', () => expand(toggle.getAttribute('aria-expanded') !== 'true'));
    form.addEventListener('submit', event => event.preventDefault());
    form.addEventListener('input', sync);
    form.addEventListener('change', sync);
    sync();
    initCandidates(root, form, combos, sync);
}

function initCandidates(root, filters, combos, syncFilters) {
    const rows = root.querySelector('[data-g9-rows]'), error = root.querySelector('[data-g9-error]');
    const summaryCard = root.querySelector('[data-g9-summary]');
    const renderSummary = summary => {
        root.querySelector('[data-g9-total]').textContent = summary.total;
        root.querySelector('[data-g9-male]').textContent = `ប្រុស៖ ${summary.male}`;
        root.querySelector('[data-g9-female]').textContent = `ស្រី៖ ${summary.female}`;
        const breakdown = (selector, entries) => {
            const container = root.querySelector(selector); container.replaceChildren();
            entries.forEach(entry => {
                const row = document.createElement('div'), label = document.createElement('span'), total = document.createElement('strong'), female = document.createElement('span');
                row.className = 'g9-summary-row'; label.textContent = entry.label; total.textContent = entry.total;
                female.className = 'text-secondary'; female.textContent = `ស្រី៖ ${entry.female}`;
                row.append(label,total,female); container.append(row);
            });
        };
        const languages = root.querySelector('[data-g9-languages]'); languages.replaceChildren();
        const order = ['ខ្មែរ', 'អង់គ្លេស'];
        [...summary.languages].sort((a, b) => {
            const rank = value => order.includes(value) ? order.indexOf(value) : order.length;
            return rank(a.label) - rank(b.label);
        }).forEach((entry, index) => {
            const row = document.createElement('div'), label = document.createElement('h3'), total = document.createElement('strong');
            const counts = document.createElement('div'), male = document.createElement('span'), female = document.createElement('span');
            row.className = `card card-body g9-summary-tile ${index % 2 === 0 ? 'g9-summary-purple' : 'g9-summary-blue'}`;
            label.className = 'g9-summary-title'; label.textContent = `ប្រឡងភាសា${entry.label}`;
            total.className = 'g9-summary-total'; total.textContent = entry.total;
            counts.className = 'g9-summary-genders'; male.textContent = `ប្រុស៖ ${entry.male}`; female.textContent = `ស្រី៖ ${entry.female}`;
            counts.append(male, female); row.append(label, total, counts); languages.append(row);
        });
        breakdown('[data-g9-campuses]', summary.campuses); summaryCard.hidden = false;
    };
    const modal = document.getElementById('g9CandidateModal'), editor = modal.querySelector('[data-g9-editor]');
    const photoViewer = document.getElementById('g9PhotoViewer'), photoImage = photoViewer.querySelector('[data-g9-photo-image]');
    const photoDownload = photoViewer.querySelector('[data-g9-photo-download]'), photoStatus = photoViewer.querySelector('[data-g9-photo-status]');
    const photoReset = photoViewer.querySelector('[data-g9-photo-reset]');
    let photoZoom = 1, photoName = 'student-photo', photoReady = false;
    const zoomPhoto = value => {
        photoZoom = Math.max(.5, Math.min(4, value));
        photoImage.style.width = `${Math.round(300 * photoZoom)}px`;
        photoReset.textContent = `${Math.round(photoZoom * 100)}%`;
    };
    photoViewer.querySelectorAll('[data-g9-zoom]').forEach(button => button.addEventListener('click', () => zoomPhoto(photoZoom + Number(button.dataset.g9Zoom))));
    photoReset.addEventListener('click', () => zoomPhoto(1));
    photoImage.addEventListener('load', () => {
        photoReady = true; photoDownload.disabled = false;
        photoStatus.textContent = `${photoImage.naturalWidth} × ${photoImage.naturalHeight}px · JPG uses the full stored image resolution.`;
    });
    photoImage.addEventListener('error', () => {photoReady = false; photoDownload.disabled = true; photoStatus.textContent = 'The student photo could not be loaded.';});
    photoViewer.addEventListener('hidden.bs.modal', () => {photoReady = false; photoDownload.disabled = true; photoImage.removeAttribute('src');});
    photoDownload.addEventListener('click', () => {
        if (!photoReady) return;
        try {
            const canvas = document.createElement('canvas'); canvas.width = photoImage.naturalWidth; canvas.height = photoImage.naturalHeight;
            const context = canvas.getContext('2d'); context.fillStyle = '#fff'; context.fillRect(0, 0, canvas.width, canvas.height); context.drawImage(photoImage, 0, 0);
            canvas.toBlob(blob => {
                if (!blob) {photoStatus.textContent = 'JPG conversion failed. Please try again.'; return;}
                const url = URL.createObjectURL(blob), link = document.createElement('a'); link.href = url; link.download = `${photoName}.jpg`;
                document.body.append(link); link.click(); link.remove(); setTimeout(() => URL.revokeObjectURL(url), 1000);
            }, 'image/jpeg', 1);
        } catch {photoStatus.textContent = 'The photo could not be downloaded. Please try again.';}
    });
    const viewPhoto = record => {
        photoReady = false; photoDownload.disabled = true; photoStatus.textContent = 'Loading photo...'; zoomPhoto(1);
        photoName = String(record.student_code || record.name_en || 'student-photo').replace(/[^\p{L}\p{N}_-]+/gu, '-');
        photoViewer.querySelector('.modal-title').textContent = record.name_kh || record.name_en || 'Student Photo';
        photoImage.alt = record.name_kh || record.name_en || 'Student photo'; photoImage.src = record.photo_url;
        Modal.getOrCreateInstance(photoViewer).show();
    };
    const save = editor.querySelector('[data-g9-save]'), editorError = editor.querySelector('[data-g9-editor-error]');
    const editorCombos = new Map(), dates = new Map();
    let editing = null, requestId = 0, timer, busy = false;
    const params = () => new URLSearchParams([...new FormData(filters)].filter(([, value]) => value !== ''));
    root.querySelectorAll('[data-g9-report]').forEach(button => button.addEventListener('click', async () => {
        error.textContent = '';
        if (!filters.elements.academic_year.value) {error.textContent = 'Select an academic year before exporting a report.'; return;}
        const url = `${root.dataset.base}/candidates/reports/${button.dataset.g9Report}/${button.dataset.format}?${params()}`;
        if (button.dataset.format === 'print') {window.open(url, '_blank', 'noopener'); return;}
        button.disabled = true;
        try {
            const response = await fetch(url, {credentials:'same-origin', headers:{Accept:'application/json'}});
            if (!response.ok) {const result = await response.json(); throw new Error(Object.values(result.errors || {}).flat().join('\n') || result.message || 'Report export failed.');}
            const blob = await response.blob(), href = URL.createObjectURL(blob), link = document.createElement('a');
            link.href = href; link.download = `g9-${button.dataset.g9Report}.${button.dataset.format === 'pdf' ? 'pdf' : 'xlsx'}`;
            document.body.append(link); link.click(); link.remove(); setTimeout(() => URL.revokeObjectURL(href), 1000);
        } catch (failure) {error.textContent = failure.message;}
        finally {button.disabled = false;}
    }));
    const api = async (path, body) => {
        const response = await fetch(root.dataset.base + path, {method: body ? 'POST' : 'GET', credentials: 'same-origin', headers: {Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}, body});
        const result = await response.json();
        if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join('\n') || result.message || 'G9 candidates unavailable.');
        return result;
    };
    const fill = (name, values) => {
        const select = filters.elements[name], previous = select.value;
        select.replaceChildren(new Option(name === 'student' ? 'All students' : `All ${select.getAttribute('aria-label').toLowerCase()}`, ''), ...values.map(value => new Option(typeof value === 'object' ? value.label : value, typeof value === 'object' ? value.id : value)));
        if ([...select.options].some(option => option.value === previous)) select.value = previous;
        [...select.options].forEach(option => option.dataset.en = option.textContent);
    };
    const line = (cell, value, className = '') => {const element = document.createElement('div'); element.className = className; element.textContent = value ?? '-'; cell.append(element);};
    const detail = (cell, label, value) => {
        const element = document.createElement('div'), title = document.createElement('span'), text = document.createElement('span');
        element.className = 'g9-candidate-detail'; title.className = 'text-secondary'; title.textContent = label;
        text.textContent = `៖ ${value || '-'}`; element.append(title, text); cell.append(element);
    };
    const dateText = value => {
        const match = /^(\d{4})-(\d{2})-(\d{2})(?:T.*)?$/.exec(value || '');
        return match ? `${match[3]}-${match[2]}-${match[1]}` : value || '-';
    };
    const empty = text => {rows.replaceChildren(); const cell = rows.insertRow().insertCell(); cell.colSpan = 7; cell.className = 'text-center text-secondary py-5'; cell.textContent = text;};
    const load = async (page = 1, options = false) => {
        const current = ++requestId; error.textContent = ''; summaryCard.hidden = true;
        if (!filters.elements.academic_year.value) {
            ['exam_center','room','seat','student'].forEach(name => fill(name, []));
            syncFilters();
            empty('Select an academic year to view the candidate list.');
            document.getElementById('g9-pagination').replaceChildren();
            return;
        }
        empty('Loading candidates...');
        try {
            if (options) {
                const query = params(); query.set('cascade', '1'); query.delete('student'); query.delete('search');
                const choices = await api('/candidates/options?' + query); if (current !== requestId) return;
                ['exam_center','room','seat','student'].forEach(name => fill(name, choices[name])); syncFilters();
            }
            const query = params(); query.set('page', page); query.set('per_page', document.getElementById('g9-per-page').value);
            const result = await api('/candidates/list?' + query); if (current !== requestId) return;
            renderSummary(result.summary);
            rows.replaceChildren();
            result.data.forEach((record, index) => {
                const row = rows.insertRow(), columns = Array.from({length: 7}, () => row.insertCell());
                const numberBadge = document.createElement('span');
                numberBadge.className = 'badge bg-blue-lt g9-number-badge';
                numberBadge.textContent = result.from + index; columns[0].append(numberBadge);
                if (record.photo_url) {
                    const trigger = document.createElement('button'), photo = document.createElement('img');
                    trigger.type = 'button'; trigger.className = 'g9-photo-trigger'; trigger.setAttribute('aria-label', `View photo of ${record.name_en || record.student_code}`);
                    photo.src = record.photo_url; photo.alt = record.name_en; photo.className = 'g9-student-photo'; photo.loading = 'lazy';
                    trigger.append(photo); trigger.addEventListener('click', () => viewPhoto(record)); columns[1].append(trigger);
                } else line(columns[1], '-');
                columns[1].className = 'g9-candidate-photo-cell';
                line(columns[1], record.student_code, 'g9-candidate-photo-id');
                line(columns[2], record.name_kh, 'fw-semibold mb-1');
                const data = record.data || {};
                const gender = {male:'ប្រុស',female:'ស្រី',m:'ប្រុស',f:'ស្រី'}[String(data.gender || '').toLowerCase()] || data.gender;
                detail(columns[2], 'ភេទ', gender);
                detail(columns[2], 'ថ្ងៃ ខែ ឆ្នាំកំណើត', dateText(data.date_of_birth));
                detail(columns[2], 'លេខទូរសព្ទ', data.phone_number);
                detail(columns[3], 'ឈ្មោះឪពុក', data.father_name);
                detail(columns[3], 'មុខរបរ', data.father_occupation);
                detail(columns[3], 'ឈ្មោះម្តាយ', data.mother_name);
                detail(columns[3], 'មុខរបរ', data.mother_occupation);
                line(columns[4], data.exam_center, 'fw-semibold');
                detail(columns[4], 'ប្រឡងភាសា', data.exam_language);
                detail(columns[4], 'សម័យប្រឡង', dateText(data.exam_date));
                const seatInfo = document.createElement('div'); seatInfo.className = 'g9-exam-seat d-flex align-items-center flex-wrap gap-2 mt-1';
                [['លេខបន្ទប់៖', data.room_number, 'bg-blue-lt'], ['លេខតុ៖', data.seat_number, 'bg-purple-lt']].forEach(([label, value, color]) => {
                    const item = document.createElement('span'), badge = document.createElement('span');
                    item.className = 'd-inline-flex align-items-center gap-1'; item.append(document.createTextNode(label));
                    badge.className = `badge ${color}`; badge.textContent = value || '-'; item.append(badge); seatInfo.append(item);
                });
                columns[4].append(seatInfo);
                line(columns[5], data.school_name, 'fw-semibold');
                detail(columns[5], 'សាខា', record.campus?.campus_name_en);
                detail(columns[5], 'ឆ្នាំសិក្សា', record.academic_year?.academic_year);
                detail(columns[5], 'ថ្នាក់ទី', data.grade);
                const button = document.createElement('button'); button.type = 'button'; button.className = 'btn btn-sm btn-outline-primary'; button.textContent = record.can_edit ? 'Edit' : 'View'; button.addEventListener('click', () => open(record.id, !record.can_edit)); columns[6].append(button);
            });
            if (!result.data.length) empty('No candidates found. Add a candidate to begin.');
            renderPagination(result, 'g9-pagination', 'g9-per-page', page => load(page), ['all',15,25,50,75,100]);
        } catch (failure) {if (current === requestId) {empty('Unable to load candidates.'); error.textContent = failure.message; document.getElementById('g9-pagination').replaceChildren();}}
    };
    const syncEditor = () => {
        editorCombos.forEach(combo => combo.sync());
        editor.querySelectorAll('.premium-form-field').forEach(field => {const input = field.querySelector('select,input:not([type=hidden])'); field.classList.toggle('has-value', input.tagName === 'SELECT' ? Boolean(input.selectedOptions[0]?.textContent) : Boolean(input.value));});
    };
    editor.querySelectorAll('select').forEach(select => {
        [...select.options].forEach(option => option.dataset.en = option.textContent);
        editorCombos.set(select, setupStaffLocationSelect(editor, select, {labelId: select.id + 'Label', optionsId: select.id + 'Options', locationLabel: select.parentElement.querySelector('label').textContent}));
    });
    editor.querySelectorAll('input[type=date]').forEach(input => {const label = input.parentElement.querySelector('label'); const picker = enhanceSubjectTemporal(input, {onChange: syncEditor}); picker.element.querySelector('.date-picker-popup').classList.add('g9-calendar-popup'); label.htmlFor = picker.input.id; dates.set(input.name, picker);});
    let linkRequest = 0, linkLoading = false;
    const loadStudentLinks = async (selected = '', readonly = false) => {
        const current = ++linkRequest, select = editor.elements.sis_student_id, combo = editorCombos.get(select);
        select.replaceChildren(new Option('Automatic match by exact Student ID', ''));
        if (readonly) {
            if (selected) select.add(new Option('Linked Student Info record', String(selected)));
            select.value = String(selected || ''); select.disabled = true; combo.toggle.disabled = true;
            [...select.options].forEach(option => option.dataset.en = option.textContent); combo.sync(); return;
        }
        const year = editor.elements.academic_year_id.value, campus = editor.elements.campus_id.value;
        select.disabled = true; combo.toggle.disabled = true; save.disabled = true; linkLoading = true;
        let failed = false;
        try {
            if (year && campus) {
                const choices = await api('/candidates/student-links?' + new URLSearchParams({academic_year_id: year, campus_id: campus}));
                if (current !== linkRequest) return;
                choices.forEach(student => select.add(new Option(student.label, student.id)));
            }
            if (selected && ![...select.options].some(option => option.value === String(selected))) select.add(new Option('Previously linked record - select another student or automatic matching', String(selected)));
            select.value = String(selected || '');
        } catch (failure) {
            failed = true;
            if (current === linkRequest) {editorError.textContent = failure.message; editorError.hidden = false;}
        } finally {
            if (current === linkRequest) {
                linkLoading = failed; select.disabled = failed; combo.toggle.disabled = failed; save.disabled = failed;
                [...select.options].forEach(option => option.dataset.en = option.textContent); syncEditor();
            }
        }
    };
    ['academic_year_id','campus_id'].forEach(name => editor.elements[name].addEventListener('change', () => {if (!busy) loadStudentLinks();}));
    async function open(id = null, readonly = false) {
        if (busy) return;
        busy = true;
        try {
            const record = id ? await api('/candidates/' + id) : null; editing = id;
            editor.reset(); editorError.hidden = true; editorError.textContent = '';
            editor.querySelectorAll('input,select,button[type=submit]').forEach(input => input.disabled = readonly);
            editor.querySelectorAll('.date-picker-calendar-button').forEach(button => button.disabled = readonly);
            const values = record ? {...record.data, ...record} : {academic_year_id: filters.elements.academic_year.value};
            Object.entries(values).forEach(([name, value]) => {
                const input = editor.elements[name];
                if (!input || name === 'photo' || name === 'data') return;
                if (input.tagName === 'SELECT' && ['gender','exam_language'].includes(name) && value && ![...input.options].some(option => option.value === value)) {
                    const option = new Option(value, value); option.dataset.en = value; input.add(option);
                }
                input.value = value ?? '';
            });
            dates.forEach((picker, name) => {picker.setValue(values[name] || ''); picker.input.disabled = readonly;});
            await loadStudentLinks(values.sis_student_id || '', readonly);
            const allowed = JSON.parse(root.dataset[id ? 'editCampuses' : 'createCampuses']);
            [...editor.elements.campus_id.options].forEach(option => option.disabled = Boolean(option.value) && !allowed.includes(Number(option.value)) && !readonly);
            editorCombos.forEach((combo, select) => combo.toggle.disabled = select.disabled);
            save.hidden = readonly; modal.querySelector('.modal-title').textContent = readonly ? 'View Candidate' : id ? 'Edit Candidate' : 'Add Candidate';
            syncEditor(); Modal.getOrCreateInstance(modal).show();
        } catch (failure) {error.textContent = failure.message;} finally {busy = false;}
    }
    root.querySelector('[data-g9-add]')?.addEventListener('click', () => open());
    modal.addEventListener('hide.bs.modal', () => {dates.forEach(picker => picker.close()); editorCombos.forEach(combo => combo.close());});
    editor.addEventListener('input', syncEditor); editor.addEventListener('change', syncEditor);
    editor.addEventListener('submit', async event => {
        event.preventDefault(); if (busy || linkLoading || ![...dates.values()].every(picker => picker.valid())) return;
        const invalid = [...editor.elements].find(input => input.willValidate && !input.checkValidity());
        if (invalid) {
            if (editorCombos.has(invalid)) {editorCombos.get(invalid).toggle.focus(); editorError.textContent = 'Select ' + invalid.parentElement.querySelector('label').textContent.replace(' *', '') + '.'; editorError.hidden = false;}
            else {invalid.focus(); invalid.reportValidity();}
            return;
        }
        busy = true; save.disabled = true; editorError.hidden = true;
        try {await api('/candidates' + (editing ? '/' + editing : ''), new FormData(editor)); Modal.getOrCreateInstance(modal).hide(); await load(1, true);}
        catch (failure) {editorError.textContent = failure.message; editorError.hidden = false; editorError.scrollIntoView({block: 'nearest'});}
        finally {busy = false; save.disabled = false;}
    });
    filters.addEventListener('change', event => {
        const fields = ['academic_year','exam_center','room','seat','student'], index = fields.indexOf(event.target.name);
        if (index < 0) return;
        fields.slice(index + 1).forEach(name => filters.elements[name].value = ''); clearTimeout(timer); load(1, true);
    });
    filters.elements.search.addEventListener('input', () => {clearTimeout(timer); timer = setTimeout(() => load(), 300);});
    load(1, true);
}
