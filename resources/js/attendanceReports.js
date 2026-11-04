import {initPremiumDateRangePicker} from './helpers/premiumDateRangePicker.js';
import {Tooltip} from 'bootstrap';
import {setupStaffLocationSelect} from './helpers/staffBirthplaceSelect.js';
import {renderPagination} from './helpers/pagination.js';
import {initAttendanceStatistics} from './attendanceStatistics.js';
const root = document.querySelector('[data-att-reports]');
const permissions = root ? JSON.parse(root.dataset.permissions || '{}') : {};
if (root) {
    const mobile = matchMedia('(max-width:767.98px)');
    const toggle = root.querySelector('[data-reports-toggle]');
    const tabs = [...root.querySelectorAll('[data-report-tab]:not([disabled])')];
    const tooltips = [toggle, ...tabs].map(element => {
        const label = element.getAttribute('title');
        element.removeAttribute('title');
        const tooltip = Tooltip.getOrCreateInstance(element, {
            container: 'body', placement: 'right', trigger: 'hover focus',
            customClass: 'att-report-tabs-tooltip', delay: {show: 200, hide: 0},
            title: () => label || element.getAttribute('aria-label'),
        });
        element.addEventListener('click', () => tooltip.hide());
        element.addEventListener('keydown', event => {if (event.key === 'Escape') tooltip.hide();});
        return tooltip;
    });
    const expand = expanded => {
        tooltips.forEach(tooltip => tooltip.hide());
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
    if (tabs.length) activate(tabs[0]);
    else root.querySelectorAll('[data-report-panel]').forEach(panel => panel.hidden = true);
}

async function initAttendanceDetails(root) {
    const form = root.querySelector('[data-report-filters]');
    const error = root.querySelector('[data-report-error]');
    const rows = root.querySelector('[data-report-rows]');
    let exportQuery = null;
    const exportButtons = [...root.querySelectorAll('[data-attendance-export]')];
    const setExportQuery = query => {exportQuery = query; exportButtons.forEach(button => button.disabled = !query);};
    exportButtons.forEach(button => button.addEventListener('click', () => {
        if (!exportQuery) return;
        window.open(`${root.dataset.base}/export/${button.dataset.attendanceExport}?${exportQuery}`, '_blank', 'noopener');
    }));
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
        setExportQuery(null);
        if (!form.elements.student_id.value) {
            profile.hidden = true; error.textContent = ''; empty('Select a student to view their absent and late records.'); return;
        }
        if (!form.querySelector('[data-report-date-range] input:not([type=hidden])').checkValidity()) return;
        const query = params(); query.set('page', page); query.set('per_page', document.getElementById('attendance-report-per-page').value);
        rows.closest('table').setAttribute('aria-busy', 'true');
        try {
            const result = await get('details', query);
            if (serial !== rowsRequest) return;
            rows.replaceChildren(); error.textContent = '';
            setExportQuery(params().toString());
            profile.hidden = false; profileBody.replaceChildren();
            const student = result.student;
            if (student.photo_url) {
                const photo = document.createElement('img'); photo.src = student.photo_url; photo.alt = student.name_en || 'Student photo'; photo.className = 'att-report-photo'; profileBody.append(photo);
            }
            const identity = document.createElement('div');
            text(identity, student.student_code, 'text-secondary small'); text(identity, student.name_kh, 'fw-semibold'); text(identity, student.name_en); profileBody.append(identity);
            const grade = document.createElement('div'); text(grade, student.academic_year, 'text-secondary small'); text(grade, student.grade); profileBody.append(grade);
            const totals = document.createElement('div'); totals.className = 'att-student-totals'; totals.setAttribute('aria-label', 'Attendance totals for selected date range');
            [['absent', 'Absent', 'green', 'user-minus'], ['EX', 'E', 'blue', 'shield-check'], ['UX', 'U', 'red', 'x'], ['L', 'L', 'orange', 'clock']].forEach(([status, label, color, icon]) => {
                const card = document.createElement('div'); card.className = `att-student-total bg-${color}-lt`;
                const symbol = document.createElement('i'); symbol.className = `ti ti-${icon}`; symbol.setAttribute('aria-hidden', 'true'); card.append(symbol);
                const count = status === 'absent' ? Number(result.totals.EX ?? 0) + Number(result.totals.UX ?? 0) : result.totals[status] ?? 0;
                const content = document.createElement('div'); text(content, `Total ${label}`, 'att-student-total-label'); text(content, String(count), 'att-student-total-value'); card.append(content); totals.append(card);
            });
            profileBody.append(totals);
            result.data.forEach((record, index) => {
                const row = rows.insertRow();
                const cells = Array.from({length: 4}, () => row.insertCell());
                text(cells[0], String(result.from + index));
                text(cells[1], new Intl.DateTimeFormat('en-GB', {day: '2-digit', month: 'short', year: 'numeric', timeZone: 'UTC'}).format(new Date(record.date + 'T00:00:00Z')).replace(/ /g, '-'));
                const badge = document.createElement('span'); badge.className = `badge bg-${{P: 'green', EX: 'blue', UX: 'red', L: 'orange'}[record.status]}-lt`; badge.textContent = {EX: 'E', UX: 'U'}[record.status] || record.status; badge.title = {P: 'Present', EX: 'Excused absence', UX: 'Unexcused absence', L: 'Late'}[record.status]; cells[2].append(badge);
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
        setExportQuery(null);
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
    const range = initPremiumDateRangePicker(root.querySelector('[data-report-date-range]'), () => {sync(); clearTimeout(timer); loadRows();});
    root.addEventListener('attendance-view-student', async event => {
        const record = event.detail;
        root.querySelector('[data-report-tab="detail"]').click();
        range.close();
        clearTimeout(timer);
        form.elements.search.value = '';
        form.elements.date_from.value = ''; form.elements.date_to.value = '';
        const rangeInput = root.querySelector('[data-report-date-range] input:not([type=hidden])');
        rangeInput.value = ''; rangeInput.setCustomValidity('');
        form.elements.academic_year_id.value = record.academic_year_id;
        ['campus_id', 'grade_class', 'student_id'].forEach(name => form.elements[name].value = '');
        await loadOptions();
        form.elements.campus_id.value = record.campus_id; await loadOptions();
        form.elements.grade_class.value = record.grade_class; await loadOptions();
        form.elements.student_id.value = record.student_id;
        if (record.date_from && record.date_to) {
            form.elements.date_from.value = record.date_from; form.elements.date_to.value = record.date_to;
            const format = value => new Intl.DateTimeFormat('en-GB', {day: '2-digit', month: 'short', year: 'numeric', timeZone: 'UTC'}).format(new Date(value + 'T00:00:00Z')).replace(/ /g, '-');
            rangeInput.value = `${format(record.date_from)} to ${format(record.date_to)}`;
        }
        sync(); await loadRows();
    });
    const filterToggle = root.querySelector('[data-report-filter-toggle]');
    const phone = matchMedia('(max-width:767.98px)');
    const expandFilters = expanded => {
        form.hidden = !expanded;
        filterToggle.setAttribute('aria-expanded', String(expanded));
        filterToggle.setAttribute('aria-label', `${expanded ? 'Collapse' : 'Expand'} data filters`);
        filterToggle.querySelector('i').className = `ti ti-chevron-${expanded ? 'up' : 'down'}`;
        if (!expanded) {range.close(); combos.forEach(combo => combo.close());}
    };
    expandFilters(!phone.matches);
    phone.addEventListener('change', () => expandFilters(!phone.matches));
    filterToggle.addEventListener('click', () => expandFilters(filterToggle.getAttribute('aria-expanded') !== 'true'));
    form.elements.search.addEventListener('input', () => {sync(); clearTimeout(timer); timer = setTimeout(() => loadRows(), 300);});
    try {
        const years = await get('years'); fill('academic_year_id', years, 'academic_year'); sync();
        if (years.length) await loadOptions();
        else {rows.replaceChildren(); error.textContent = 'No academic years available.';}
    } catch (failure) {rows.replaceChildren(); profile.hidden = true; error.textContent = failure.message;}
}
if (root && permissions.detail) initAttendanceDetails(root);
if (root && permissions.statistics) initAttendanceStatistics(root);
