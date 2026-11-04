import {setupStaffLocationSelect} from './helpers/staffBirthplaceSelect.js';
import {renderPagination} from './helpers/pagination.js';
import {initPremiumDateRangePicker} from './helpers/premiumDateRangePicker.js';

export async function initAttendanceStatistics(root) {
    const form = root.querySelector('[data-statistics-filters]');
    const rows = root.querySelector('[data-statistics-rows]');
    const error = root.querySelector('[data-statistics-error]');
    const combos = new Map();
    const permissions = JSON.parse(root.dataset.permissions || '{}');
    let requestId = 0;
    let exportQuery = null;
    const exportButtons = [...root.querySelectorAll('[data-statistics-export]')];
    const setExportQuery = query => {exportQuery = query; exportButtons.forEach(button => button.disabled = !query);};
    exportButtons.forEach(button => button.addEventListener('click', () => {
        if (exportQuery) window.open(`${root.dataset.base}/statistics/export/${button.dataset.statisticsExport}?${exportQuery}`, '_blank', 'noopener');
    }));
    const params = () => new URLSearchParams([...new FormData(form)].filter(([, value]) => value !== ''));
    const get = async (path, query = '') => {
        const response = await fetch(`${root.dataset.base}/${path}?${query}`, {credentials: 'same-origin', headers: {Accept: 'application/json'}});
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Attendance statistics unavailable.');
        return data;
    };
    const sync = () => {
        combos.forEach(combo => combo.sync());
        form.querySelectorAll('.premium-form-field').forEach(field => {
            const input = field.querySelector('select,input:not([type="hidden"])');
            field.classList.toggle('has-value', input.tagName === 'SELECT' ? Boolean(input.selectedOptions[0]?.textContent) : Boolean(input.value));
        });
    };
    const fill = (name, values, label, placeholder) => {
        const select = form.elements[name], previous = select.value;
        select.replaceChildren(...(placeholder ? [new Option(placeholder, '')] : []), ...values.map(value => new Option(value[label], value.id)));
        if ([...select.options].some(option => option.value === previous)) select.value = previous;
        [...select.options].forEach(option => option.dataset.en = option.textContent);
    };
    form.querySelectorAll('select').forEach(select => combos.set(select, setupStaffLocationSelect(form, select, {
        labelId: select.id + 'Label', optionsId: select.id + 'Options', locationLabel: select.getAttribute('aria-label'),
    })));
    const line = (cell, value, className = '') => {
        const span = document.createElement('div'); span.className = className; span.textContent = value || '-'; cell.append(span);
    };
    const message = text => {
        rows.replaceChildren(); const cell = rows.insertRow().insertCell(); cell.colSpan = 9; cell.className = 'text-center text-secondary py-4'; cell.textContent = text;
    };
    const load = async (page = 1, updateOptions = false) => {
        if (!range.valid()) return;
        const current = ++requestId;
        setExportQuery(null);
        message('Loading statistics...'); error.textContent = '';
        document.getElementById('attendance-statistics-pagination').replaceChildren();
        try {
            if (updateOptions) {
                const options = await get('options', params()); if (current !== requestId) return;
                fill('campus_id', options.campuses, 'campus_name_en', 'All campuses');
                fill('grade_class', options.grades, 'label', 'All grades');
                fill('student_id', options.students, 'label', 'All students'); sync();
            }
            const query = params(); query.set('page', page); query.set('per_page', document.getElementById('attendance-statistics-per-page').value);
            const result = await get('statistics', query); if (current !== requestId) return;
            rows.replaceChildren();
            setExportQuery(query.toString());
            result.data.forEach((record, index) => {
                const row = rows.insertRow(), cells = Array.from({length: 9}, () => row.insertCell());
                line(cells[0], String(result.from + index));
                if (record.photo_url) {
                    const image = document.createElement('img'); image.src = record.photo_url; image.alt = record.name_en || 'Student photo'; image.className = 'att-report-photo'; image.loading = 'lazy'; cells[1].append(image);
                } else {const blank = document.createElement('span'); blank.className = 'att-report-photo att-report-photo-placeholder'; blank.textContent = '-'; cells[1].append(blank);}
                line(cells[2], record.student_code, 'text-secondary small'); line(cells[2], record.name_kh, 'fw-semibold'); line(cells[2], record.name_en);
                line(cells[3], record.grade);
                [['total_absent', 'purple'], ['EX', 'blue'], ['UX', 'red'], ['L', 'orange']].forEach(([key, color], i) => {
                    const count = document.createElement('span'); count.className = `badge bg-${color}-lt att-statistics-count`; count.textContent = String(record[key]); cells[4 + i].append(count);
                });
                const action = document.createElement('button'); action.type = 'button'; action.className = 'btn btn-sm btn-outline-primary att-view-record';
                const icon = document.createElement('i'); icon.className = 'ti ti-file-description me-1'; icon.setAttribute('aria-hidden', 'true'); action.append(icon, document.createTextNode('View Record'));
                action.setAttribute('aria-label', `View absent record for ${record.name_en || record.student_code}`);
                action.addEventListener('click', () => root.dispatchEvent(new CustomEvent('attendance-view-student', {detail: {...record, date_from: form.elements.date_from.value, date_to: form.elements.date_to.value}})));
                if (permissions.detail) cells[8].append(action);
            });
            if (!result.data.length) message('No students found for the selected filters.');
            renderPagination(result, 'attendance-statistics-pagination', 'attendance-statistics-per-page', page => load(page), ['all', 15, 25, 50, 75, 100]);
        } catch (failure) {if (current === requestId) {message('Unable to load statistics.'); error.textContent = failure.message;}}
    };
    const range = initPremiumDateRangePicker(root.querySelector('[data-statistics-date-range]'), () => {sync(); load();});
    const toggle = root.querySelector('[data-statistics-filter-toggle]'), mobile = matchMedia('(max-width:767.98px)');
    const expand = expanded => {
        form.hidden = !expanded; toggle.setAttribute('aria-expanded', String(expanded)); toggle.setAttribute('aria-label', `${expanded ? 'Collapse' : 'Expand'} data filters`);
        toggle.querySelector('i').className = `ti ti-chevron-${expanded ? 'up' : 'down'}`;
        if (!expanded) {combos.forEach(combo => combo.close()); range.close();}
    };
    expand(!mobile.matches); mobile.addEventListener('change', () => expand(!mobile.matches));
    toggle.addEventListener('click', () => expand(toggle.getAttribute('aria-expanded') !== 'true'));
    form.addEventListener('submit', event => event.preventDefault());
    form.addEventListener('change', event => {
        const fields = ['academic_year_id', 'campus_id', 'grade_class', 'student_id'], index = fields.indexOf(event.target.name);
        if (index < 0) return;
        fields.slice(index + 1).forEach(name => form.elements[name].value = ''); sync(); load(1, event.target.name !== 'student_id');
    });
    try {
        const years = await get('years'); fill('academic_year_id', years, 'academic_year'); sync();
        if (years.length) await load(1, true); else message('No academic years available.');
    } catch (failure) {message('Unable to load filters.'); error.textContent = failure.message;}
}
