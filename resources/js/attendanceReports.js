import {enhanceSubjectTemporal} from './scholarshipTemporalInputs.js';
import {setupStaffLocationSelect} from './helpers/staffBirthplaceSelect.js';
import {renderPagination} from './helpers/pagination.js';
const root = document.querySelector('[data-att-reports]');
if (root) {
    const mobile = matchMedia('(max-width:767.98px)');
    const toggle = root.querySelector('[data-reports-toggle]');
    const tabs = [...root.querySelectorAll('[data-report-tab]')];
    const expand = expanded => {
        root.classList.toggle('is-collapsed', !expanded);
        toggle.setAttribute('aria-expanded', String(expanded));
        toggle.setAttribute('aria-label', `${expanded ? 'Collapse' : 'Expand'} report tabs`);
        toggle.querySelector('i').className = `ti ti-layout-sidebar-left-${expanded ? 'collapse' : 'expand'}`;
    };
    expand(!mobile.matches);
    mobile.addEventListener('change', () => expand(!mobile.matches));
    toggle.addEventListener('click', () => expand(toggle.getAttribute('aria-expanded') !== 'true'));
    const activate = tab => {
        tabs.forEach(button => {
            const selected = button === tab;
            button.setAttribute('aria-selected', String(selected));
            button.tabIndex = selected ? 0 : -1;
        });
        root.querySelectorAll('[data-report-panel]').forEach(panel => {
            panel.hidden = panel.dataset.reportPanel !== tab.dataset.reportTab;
        });
        if (mobile.matches) { expand(false); toggle.focus(); }
    };
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => activate(tab));
        tab.addEventListener('keydown', event => {
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1
                : ['ArrowDown', 'ArrowRight'].includes(event.key) ? (index + 1) % tabs.length
                : ['ArrowUp', 'ArrowLeft'].includes(event.key) ? (index - 1 + tabs.length) % tabs.length : null;
            if (next === null) return;
            event.preventDefault(); activate(tabs[next]); tabs[next].focus();
        });
    });
}

async function initAttendanceDetails(root) {
    const form = root.querySelector('[data-report-filters]');
    const error = root.querySelector('[data-report-error]');
    const rows = root.querySelector('[data-report-rows]');
    const combos = new Map();
    let optionsRequest = 0, rowsRequest = 0, timer;
    const params = () => new URLSearchParams([...new FormData(form)].filter(([, value]) => value !== ''));
    const get = async (path, query = '') => {
        const response = await fetch(`${root.dataset.base}/${path}?${query}`, {headers: {Accept: 'application/json'}, credentials: 'same-origin'});
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Attendance report unavailable.');
        return data;
    };
    const sync = () => {
        combos.forEach(combo => combo.sync());
        form.querySelectorAll('.premium-form-field').forEach(field => {
            const input = field.querySelector('select,input:not([type=hidden])');
            field.classList.toggle('has-value', input.tagName === 'SELECT' ? Boolean(input.selectedOptions[0]?.textContent) : Boolean(input.value));
        });
    };
    const fill = (name, values, label, allLabel) => {
        const select = form.elements[name], previous = select.value;
        select.replaceChildren(...(allLabel ? [new Option(allLabel, '')] : []), ...values.map(value => new Option(value[label], value.id)));
        if ([...select.options].some(option => option.value === previous)) select.value = previous;
        [...select.options].forEach(option => option.dataset.en = option.textContent);
    };
    form.querySelectorAll('select').forEach(select => {
        combos.set(select, setupStaffLocationSelect(form, select, {labelId: select.id + 'Label', optionsId: select.id + 'Options', locationLabel: select.getAttribute('aria-label')}));
    });
    const text = (cell, value, className = '') => {
        const line = document.createElement('div'); line.className = className; line.textContent = value || '-'; cell.append(line);
    };
    const profile = root.querySelector('[data-student-profile]');
    const profileBody = root.querySelector('[data-student-profile-body]');
    const empty = message => {
        rows.replaceChildren();
        const cell = rows.insertRow().insertCell(); cell.colSpan = 4; cell.className = 'text-center text-secondary py-4'; cell.textContent = message;
        document.getElementById('attendance-report-pagination').replaceChildren();
    };
    const loadRows = async (page = 1) => {
        const serial = ++rowsRequest;
        if (!form.elements.student_id.value) {
            profile.hidden = true; error.textContent = ''; empty('Select a student to view their absent and late records.'); return;
        }
        const query = params(); query.set('page', page); query.set('per_page', document.getElementById('attendance-report-per-page').value);
        rows.closest('table').setAttribute('aria-busy', 'true');
        try {
            const result = await get('details', query);
            if (serial !== rowsRequest) return;
            rows.replaceChildren(); error.textContent = '';
            profile.hidden = false; profileBody.replaceChildren();
            const student = result.student;
            if (student.photo_url) {
                const photo = document.createElement('img'); photo.src = student.photo_url; photo.alt = student.name_en || 'Student photo'; photo.className = 'att-report-photo'; profileBody.append(photo);
            }
            const identity = document.createElement('div');
            text(identity, student.student_code, 'text-secondary small'); text(identity, student.name_kh, 'fw-semibold'); text(identity, student.name_en); profileBody.append(identity);
            const grade = document.createElement('div'); text(grade, student.academic_year, 'text-secondary small'); text(grade, student.grade); profileBody.append(grade);
            result.data.forEach((record, index) => {
                const row = rows.insertRow();
                const cells = Array.from({length: 4}, () => row.insertCell());
                text(cells[0], String(result.from + index));
                text(cells[1], new Intl.DateTimeFormat('en-GB', {day: '2-digit', month: 'short', year: 'numeric', timeZone: 'UTC'}).format(new Date(record.date + 'T00:00:00Z')).replace(/ /g, '-'));
                const badge = document.createElement('span'); badge.className = `badge bg-${{P: 'green', EX: 'blue', UX: 'red', L: 'orange'}[record.status]}-lt`; badge.textContent = record.status; badge.title = {P: 'Present', EX: 'Excused absence', UX: 'Unexcused absence', L: 'Late'}[record.status]; cells[2].append(badge);
                text(cells[3], record.reason);
            });
            if (!result.data.length) empty('No absent or late records found for the selected date range.');
            renderPagination(result, 'attendance-report-pagination', 'attendance-report-per-page', loadRows, ['all', 15, 25, 50, 75, 100]);
        } catch (failure) {
            if (serial !== rowsRequest) return;
            rows.replaceChildren(); profile.hidden = true; error.textContent = failure.message;
            document.getElementById('attendance-report-pagination').replaceChildren();
        } finally {if (serial === rowsRequest) rows.closest('table').setAttribute('aria-busy', 'false');}
    };
    const loadOptions = async () => {
        const serial = ++optionsRequest; ++rowsRequest;
        try {
            const data = await get('options', params());
            if (serial !== optionsRequest) return;
            fill('campus_id', data.campuses, 'campus_name_en', 'All campuses');
            fill('grade_class', data.grades, 'label', 'All grades');
            fill('student_id', data.students, 'label', 'Select student');
            sync(); await loadRows();
        } catch (failure) {if (serial === optionsRequest) {rows.replaceChildren(); profile.hidden = true; error.textContent = failure.message;}}
    };
    form.addEventListener('submit', event => event.preventDefault());
    form.addEventListener('change', event => {
        const name = event.target.name;
        const fields = ['academic_year_id', 'campus_id', 'grade_class', 'student_id'];
        if (['date_from', 'date_to'].includes(name)) {sync();loadRows();return;}
        if (!fields.includes(name)) return;
        fields.slice(fields.indexOf(name) + 1).forEach(field => form.elements[field].value = '');
        sync(); clearTimeout(timer);
        if (name === 'student_id') loadRows(); else loadOptions();
    });
    ['date_from', 'date_to'].forEach(name => {
        const input = form.elements[name];
        const picker = enhanceSubjectTemporal(input, {onChange: () => {sync(); clearTimeout(timer); timer = setTimeout(() => loadRows(), 300);}});
        input.parentElement.parentElement.querySelector('label').htmlFor = picker.input.id;
    });
    form.elements.search.addEventListener('input', () => {sync(); clearTimeout(timer); timer = setTimeout(() => loadRows(), 300);});
    try {
        const years = await get('years'); fill('academic_year_id', years, 'academic_year'); sync();
        if (years.length) await loadOptions();
        else {rows.replaceChildren(); error.textContent = 'No academic years available.';}
    } catch (failure) {rows.replaceChildren(); profile.hidden = true; error.textContent = failure.message;}
}
if (root) initAttendanceDetails(root);
