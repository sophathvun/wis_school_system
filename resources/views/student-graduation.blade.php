@extends('layouts.app')

@section('title', 'Student Graduation')

@section('content')
<style>
    .graduation-filter-bar { gap: .75rem; }
    .graduation-history-filters { gap: .5rem; }
    .graduation-history-filters .location-combobox { min-width: 170px; }
    .graduation-search { min-width: min(100%, 280px); }
    .graduation-student-photo { width: 42px; height: 42px; border-radius: 15%; object-fit: cover; border: 1px solid var(--tblr-border-color); background: var(--tblr-bg-surface-secondary); }
    .graduation-student-photo-placeholder { width: 42px; height: 42px; border-radius: 15%; display: inline-flex; align-items: center; justify-content: center; color: var(--tblr-secondary); background: var(--tblr-bg-surface-secondary); border: 1px solid var(--tblr-border-color); }
    .graduation-student-name-kh { font-size: .95rem; line-height: 1.2; font-weight: 400; color: var(--tblr-secondary); margin-bottom: .2rem; }
    .graduation-student-name-en { font-size: .88rem; line-height: 1.2; color: var(--tblr-secondary); }
    .graduation-multiselect .location-combobox-menu { max-height: 340px; overflow-y: auto; }
    .graduation-multiselect-option { width: 100%; border: 0; background: transparent; display: flex; align-items: flex-start; gap: .65rem; padding: .55rem .75rem; text-align: left; color: var(--tblr-body-color); border-radius: .5rem; }
    .graduation-multiselect-option:hover { background: var(--tblr-bg-surface-secondary); }
    .graduation-multiselect-option .form-check-input { margin-top: .15rem; flex: 0 0 auto; pointer-events: none; }
    .graduation-multiselect-option-title { font-weight: 600; line-height: 1.2; }
    .graduation-multiselect-option-meta { font-size: .78rem; color: var(--tblr-secondary); line-height: 1.25; }
    .graduation-selected-pill { display: inline-flex; align-items: center; gap: .35rem; border: 1px solid var(--tblr-border-color); border-radius: 999px; padding: .2rem .5rem; margin: .15rem; background: var(--tblr-bg-surface); font-size: .82rem; }
    .graduation-selected-pill button { border: 0; background: transparent; color: var(--tblr-secondary); padding: 0; line-height: 1; }
    #graduationModal .modal-dialog { max-width: 980px; }
    #graduationModal .modal-body { max-height: calc(100vh - 11rem); overflow-y: auto; }
    @media (max-width: 991.98px) {
        #graduationModal .modal-dialog { max-width: calc(100% - 1rem); margin-left: .5rem; margin-right: .5rem; }
        .graduation-history-filters,
        .graduation-history-filters .location-combobox,
        .graduation-search { width: 100%; min-width: 0; }
    }
</style>

<div class="card">
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title mb-0">Graduated Students</h3>
        <button class="btn btn-primary ms-auto" id="newGraduation">
            <i class="ti ti-school icon"></i> Graduate Grade 12 Student
        </button>
    </div>

    <div class="card-body border-bottom py-3 d-flex flex-wrap justify-content-between graduation-filter-bar">
        <div class="d-flex align-items-center flex-wrap graduation-history-filters">
            <select id="graduation-year" class="form-select form-select-sm"><option value=""></option></select>
            <select id="graduation-campus" class="form-select form-select-sm"><option value=""></option></select>
            <select id="graduation-class" class="form-select form-select-sm"><option value=""></option></select>
            <select id="graduation-per-page" class="form-control form-control-sm d-none">
                <option selected>10</option>
                <option>25</option>
                <option>50</option>
                <option>100</option>
            </select>
        </div>
        <div class="input-icon graduation-search">
            <span class="input-icon-addon"><i class="ti ti-search"></i></span>
            <input id="graduation-search" class="form-control form-control-sm" placeholder="Search student">
        </div>
    </div>

    <div class="table-responsive">
        <table class="table card-table">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Student Photo</th>
                    <th><button type="button" class="table-sort" data-graduation-sort="student_id">Student ID <span data-graduation-sort-icon="student_id"></span></button></th>
                    <th><button type="button" class="table-sort" data-graduation-sort="student_name">Student Name <span data-graduation-sort-icon="student_name"></span></button></th>
                    <th><button type="button" class="table-sort" data-graduation-sort="academic_year">Academic Year <span data-graduation-sort-icon="academic_year"></span></button></th>
                    <th><button type="button" class="table-sort" data-graduation-sort="class">Grade <span data-graduation-sort-icon="class"></span></button></th>
                    <th><button type="button" class="table-sort" data-graduation-sort="group">Group <span data-graduation-sort-icon="group"></span></button></th>
                    <th><button type="button" class="table-sort" data-graduation-sort="campus">Campus <span data-graduation-sort-icon="campus"></span></button></th>
                    <th><button type="button" class="table-sort" data-graduation-sort="graduation_date">Graduation Date <span data-graduation-sort-icon="graduation_date"></span></button></th>
                    <th><button type="button" class="table-sort" data-graduation-sort="certificate">Certificate <span data-graduation-sort-icon="certificate"></span></button></th>
                    <th><button type="button" class="table-sort" data-graduation-sort="alumni">Alumni <span data-graduation-sort-icon="alumni"></span></button></th>
                    <th><button type="button" class="table-sort" data-graduation-sort="graduated_by">Graduated By <span data-graduation-sort-icon="graduated_by"></span></button></th>
                </tr>
            </thead>
            <tbody id="graduationTable">
                <tr><td colspan="12" class="text-center">Loading...</td></tr>
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        <div id="graduation-pagination-container"></div>
    </div>
</div>

<div class="modal modal-blur fade" id="graduationModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="graduationForm">
                <div class="modal-header">
                    <h3 class="modal-title">Graduate Grade 12 Student</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none" id="graduationError"></div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Graduation Scope *</label>
                            <select id="graduation-scope" class="form-select">
                                <option value="student">Individual Student</option>
                                <option value="class">Entire Class</option>
                                <option value="campus">Entire Campus</option>
                                <option value="all_campuses">All Campuses</option>
                            </select>
                        </div>
                        <div class="col-md-8 graduation-student-field">
                            <label class="form-label">Grade 12 Student *</label>
                            <select id="graduation-enrollment" class="form-select" multiple></select>
                            <div class="small text-secondary mt-2" id="graduation-current"></div>
                        </div>

                        <div class="col-12 d-none" id="graduation-batch-block">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Academic Year *</label>
                                    <select id="batch-year" class="form-select"></select>
                                </div>
                                <div class="col-md-4" id="batch-campus-block">
                                    <label class="form-label">Campus *</label>
                                    <select id="batch-campus" class="form-select"></select>
                                </div>
                                <div class="col-md-4" id="batch-class-block">
                                    <label class="form-label">Class *</label>
                                    <select id="batch-class" class="form-select"></select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Graduation Date *</label>
                            <input type="date" id="graduation-date" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Certificate Prefix *</label>
                            <input id="certificate-number" class="form-control" inputmode="numeric" maxlength="4" pattern="\d{4}" placeholder="Ex: 2026" required>
                            <div class="form-hint">Final number: 4 digits + auto 3 digits, e.g. 2026001.</div>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <label class="form-check mb-2">
                                <input type="checkbox" id="is-alumni" class="form-check-input">
                                <span class="form-check-label">Mark as Alumni</span>
                            </label>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea id="graduation-notes" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn me-auto" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary">Graduate Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(() => {
    window.addEventListener('load', () => {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const field = (id) => document.getElementById(id);
        const modal = bootstrap.Modal.getOrCreateInstance(field('graduationModal'));
        let options = {};
        let currentPage = 1;
        let graduationSortBy = 'graduation_date';
        let graduationSortDir = 'desc';
        const searchable = {};
        const selectedGraduateEnrollmentIds = new Set();
        let enrollmentMulti = null;

        const esc = (value = '') => String(value ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
        const pad = (number) => String(number).padStart(2, '0');
        const formatDate = (value = '') => {
            if (!value) return '-';
            const date = new Date(`${String(value).slice(0, 10)}T00:00:00`);
            return Number.isNaN(date.getTime()) ? String(value).slice(0, 10) : `${pad(date.getDate())}-${pad(date.getMonth() + 1)}-${date.getFullYear()}`;
        };
        const studentName = (student = {}) => student.full_name_en || '';
        const studentNameKh = (student = {}) => student.full_name_kh || '';
        const gradeClassLabel = (item = {}) => {
            const grade = String(item.grade?.grade || '').replace(/^grade\s*/i, '').trim();
            const cls = String(item.school_class?.class_name || '').replace(/^grade\s*/i, '').trim();
            return cls && grade && cls.toLowerCase().startsWith(grade.toLowerCase()) ? cls : `${grade}${cls}` || '-';
        };
        const trackLabel = (item = {}) => {
            const track = item.enrollment?.academic_track || item.enrollment?.academicTrack || {};
            return track.name_en || track.code || '';
        };
        const classOptionLabel = (item = {}) => {
            if (item.display_name) return item.display_name;
            const grade = String(item.grade?.grade || '').replace(/^grade\s*/i, '').trim();
            const cls = String(item.class_name || '').replace(/^grade\s*/i, '').trim();
            return cls && grade && cls.toLowerCase().startsWith(grade.toLowerCase()) ? `Grade ${cls}` : `Grade ${grade}${cls}` || cls || '-';
        };
        const studentPhoto = (student = {}) => student.photo_path
            ? `<img class="graduation-student-photo" src="/storage/${esc(student.photo_path)}" alt="${esc(studentName(student) || 'Student photo')}">`
            : `<span class="graduation-student-photo-placeholder"><i class="ti ti-user"></i></span>`;
        const fill = (id, items, textKey, allLabel = '') => {
            const select = field(id);
            if (!select) return;
            const selected = select.value;
            const label = typeof textKey === 'function' ? textKey : (item) => item[textKey] || '';
            select.innerHTML = `<option value="">${esc(allLabel)}</option>` + (items || []).map((item) => `<option value="${item.id}">${esc(label(item))}</option>`).join('');
            select.value = Array.from(select.options).some((option) => option.value === selected) ? selected : '';
            refreshSearchableValue(id);
            renderSearchable(id);
        };

        const renderSearchable = (id) => {
            const item = searchable[id];
            if (!item) return;
            const term = item.search.value.trim().toLowerCase();
            const matches = Array.from(item.select.options).slice(1).filter((option) => option.textContent.toLowerCase().includes(term));
            const allOption = ['graduation-year', 'graduation-campus', 'graduation-class'].includes(id) ? `<button type="button" class="location-combobox-option" data-value="">All ${id === 'graduation-year' ? 'Academic Years' : id === 'graduation-campus' ? 'Campuses' : 'Grades'}</button>` : '';
            item.results.innerHTML = allOption + (matches.length ? matches.slice(0, 60).map((option) => `<button type="button" class="location-combobox-option" data-value="${option.value}">${esc(option.textContent)}</button>`).join('') : '<div class="text-secondary px-2 py-2">No results found</div>');
            item.results.querySelectorAll('[data-value]').forEach((button) => {
                button.onclick = () => {
                    item.select.value = button.dataset.value;
                    item.select.dispatchEvent(new Event('change', { bubbles: true }));
                    refreshSearchableValue(id);
                    item.menu.classList.add('d-none');
                };
            });
        };
        const makeSearchable = (id) => {
            if (searchable[id]) return;
            const select = field(id);
            if (!select) return;
            select.classList.add('d-none');
            const wrapper = document.createElement('div');
            wrapper.className = 'location-combobox';
            wrapper.innerHTML = `<button type="button" class="location-combobox-toggle"><span class="location-combobox-selected"></span><i class="ti ti-chevron-down"></i></button><div class="location-combobox-menu d-none"><input type="search" class="form-control location-combobox-search" placeholder="Search"><div class="location-combobox-results"></div></div>`;
            select.after(wrapper);
            searchable[id] = { select, selected: wrapper.querySelector('.location-combobox-selected'), menu: wrapper.querySelector('.location-combobox-menu'), search: wrapper.querySelector('.location-combobox-search'), results: wrapper.querySelector('.location-combobox-results') };
            const item = searchable[id];
            wrapper.querySelector('.location-combobox-toggle').onclick = () => {
                Object.values(searchable).forEach((other) => { if (other !== item) other.menu.classList.add('d-none'); });
                item.menu.classList.toggle('d-none');
                item.search.value = '';
                renderSearchable(id);
                item.search.focus();
            };
            item.search.oninput = () => renderSearchable(id);
            select.addEventListener('change', () => refreshSearchableValue(id));
            refreshSearchableValue(id);
        };
        const refreshSearchableValue = (id) => {
            const item = searchable[id];
            if (!item) return;
            const selected = item.select.options[item.select.selectedIndex];
            item.selected.textContent = selected?.value ? selected.textContent : (id === 'graduation-year' ? 'All Academic Years' : id === 'graduation-campus' ? 'All Campuses' : id === 'graduation-class' ? 'All Grades' : '');
        };
        const setupSearchables = () => ['graduation-year', 'graduation-campus', 'graduation-class', 'batch-year', 'batch-campus', 'batch-class'].forEach(makeSearchable);

        const enrollmentOptions = () => Array.from(field('graduation-enrollment').options);
        const selectedEnrollmentOptions = () => enrollmentOptions().filter((option) => selectedGraduateEnrollmentIds.has(option.value));
        const refreshEnrollmentMultiValue = () => {
            if (!enrollmentMulti) return;
            const selected = selectedEnrollmentOptions();
            enrollmentMulti.selected.textContent = selected.length ? `${selected.length} student${selected.length > 1 ? 's' : ''} selected` : '';
            field('graduation-current').innerHTML = selected.length
                ? selected.map((option) => `<span class="graduation-selected-pill">${esc(option.textContent)} <button type="button" data-remove-enrollment="${option.value}" aria-label="Remove student">&times;</button></span>`).join('')
                : '';
            field('graduation-current').querySelectorAll('[data-remove-enrollment]').forEach((button) => {
                button.onclick = () => {
                    selectedGraduateEnrollmentIds.delete(button.dataset.removeEnrollment);
                    const option = enrollmentOptions().find((item) => item.value === button.dataset.removeEnrollment);
                    if (option) option.selected = false;
                    refreshEnrollmentMultiValue();
                    renderEnrollmentMultiOptions();
                };
            });
        };
        const renderEnrollmentMultiOptions = () => {
            if (!enrollmentMulti) return;
            const term = enrollmentMulti.search.value.trim().toLowerCase();
            const matches = enrollmentOptions().filter((option) => option.textContent.toLowerCase().includes(term) || (option.dataset.info || '').toLowerCase().includes(term));
            enrollmentMulti.results.innerHTML = matches.length ? matches.slice(0, 120).map((option) => `
                <button type="button" class="graduation-multiselect-option" data-value="${option.value}">
                    <input class="form-check-input" type="checkbox" ${selectedGraduateEnrollmentIds.has(option.value) ? 'checked' : ''}>
                    <span>
                        <span class="graduation-multiselect-option-title">${esc(option.textContent)}</span>
                        <span class="graduation-multiselect-option-meta d-block">${esc(option.dataset.info || '')}</span>
                    </span>
                </button>
            `).join('') : '<div class="text-secondary px-2 py-2">No results found</div>';
            enrollmentMulti.results.querySelectorAll('[data-value]').forEach((button) => {
                button.onclick = () => {
                    const value = button.dataset.value;
                    const option = enrollmentOptions().find((item) => item.value === value);
                    if (selectedGraduateEnrollmentIds.has(value)) {
                        selectedGraduateEnrollmentIds.delete(value);
                        if (option) option.selected = false;
                    } else {
                        selectedGraduateEnrollmentIds.add(value);
                        if (option) option.selected = true;
                    }
                    refreshEnrollmentMultiValue();
                    renderEnrollmentMultiOptions();
                };
            });
        };
        const makeEnrollmentMultiselect = () => {
            if (enrollmentMulti) {
                refreshEnrollmentMultiValue();
                renderEnrollmentMultiOptions();
                return;
            }
            const select = field('graduation-enrollment');
            select.classList.add('d-none');
            const wrapper = document.createElement('div');
            wrapper.className = 'location-combobox graduation-multiselect';
            wrapper.innerHTML = `<button type="button" class="location-combobox-toggle"><span class="location-combobox-selected"></span><i class="ti ti-chevron-down"></i></button><div class="location-combobox-menu d-none"><input type="search" class="form-control location-combobox-search" placeholder="Search student"><div class="location-combobox-results"></div></div>`;
            select.after(wrapper);
            enrollmentMulti = {
                selected: wrapper.querySelector('.location-combobox-selected'),
                menu: wrapper.querySelector('.location-combobox-menu'),
                search: wrapper.querySelector('.location-combobox-search'),
                results: wrapper.querySelector('.location-combobox-results'),
            };
            wrapper.querySelector('.location-combobox-toggle').onclick = () => {
                Object.values(searchable).forEach((item) => item.menu.classList.add('d-none'));
                enrollmentMulti.menu.classList.toggle('d-none');
                enrollmentMulti.search.value = '';
                renderEnrollmentMultiOptions();
                enrollmentMulti.search.focus();
            };
            enrollmentMulti.search.oninput = renderEnrollmentMultiOptions;
            refreshEnrollmentMultiValue();
        };

        const loadOptions = async (list = false, params = {}) => {
            const query = new URLSearchParams({ ...params, list: list ? '1' : '' });
            const response = await fetch(`/student-graduations/options?${query.toString()}`, { headers: { Accept: 'application/json' } });
            options = await response.json();
            return options;
        };
        const loadListOptions = async () => {
            const data = await loadOptions(true);
            fill('graduation-year', data.academicYears || [], 'academic_year', 'All Academic Years');
            fill('graduation-campus', data.campuses || [], 'campus_name_en', 'All Campuses');
            fill('graduation-class', data.classes || [], classOptionLabel, 'All Grades');
            setupSearchables();
        };
        const refreshCampuses = async (yearId, list, targetId) => {
            const data = await loadOptions(list, { academic_year_id: yearId || '' });
            fill(targetId, data.campuses || [], 'campus_name_en');
            return data;
        };
        const refreshClasses = async (campusId, yearId, targetId, list = true) => {
            const data = await loadOptions(list, { campus_id: campusId || '', academic_year_id: yearId || '' });
            fill(targetId, data.classes || [], classOptionLabel);
            return data;
        };

        const renderPagination = (result, pageSize) => {
            const container = field('graduation-pagination-container');
            const totalPages = Number(result.last_page || 1);
            const current = Number(result.current_page || 1);
            const pageSet = new Set([1, totalPages, current, current - 1, current + 1].filter((page) => page >= 1 && page <= totalPages));
            const pages = [];
            [...pageSet].sort((a, b) => a - b).forEach((page) => {
                const previous = pages[pages.length - 1];
                if (typeof previous === 'number' && page - previous > 1) pages.push('ellipsis');
                pages.push(page);
            });
            container.innerHTML = `<div class="premium-pagination"><ul class="pagination premium-pagination-list m-0"><li class="page-item ${result.current_page === 1 ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${result.current_page - 1}"><i class="ti ti-chevron-left icon icon-1"></i></a></li>${pages.map((page) => page === 'ellipsis' ? '<li class="premium-pagination-ellipsis">...</li>' : `<li class="page-item ${page === result.current_page ? 'active' : ''}"><a class="page-link" href="#" data-page="${page}">${page}</a></li>`).join('')}<li class="page-item ${result.current_page === result.last_page ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${result.current_page + 1}"><i class="ti ti-chevron-right icon icon-1"></i></a></li></ul><p class="premium-pagination-info m-0">Showing <strong>${result.from ?? 0} to ${result.to ?? 0}</strong> of <strong>${result.total || 0} entries</strong></p><div class="premium-pagination-controls"><label class="premium-pagination-select"><select class="form-select form-select-sm">${[10,25,50,100].map((value) => `<option value="${value}" ${String(value) === String(pageSize) ? 'selected' : ''}>${value} / page</option>`).join('')}</select></label></div></div>`;
            container.querySelectorAll('.page-link').forEach((link) => {
                link.onclick = (event) => {
                    event.preventDefault();
                    const page = Number(link.dataset.page);
                    if (page >= 1 && page <= totalPages && page !== Number(result.current_page)) render(page, pageSize);
                };
            });
            container.querySelector('select')?.addEventListener('change', (event) => {
                field('graduation-per-page').value = event.target.value;
                render(1, Number(event.target.value));
            });
        };
        const updateSortIcons = () => {
            document.querySelectorAll('[data-graduation-sort-icon]').forEach((icon) => {
                icon.textContent = icon.dataset.graduationSortIcon === graduationSortBy ? (graduationSortDir === 'asc' ? '↑' : '↓') : '';
            });
            document.querySelectorAll('[data-graduation-sort]').forEach((button) => {
                button.classList.toggle('text-primary', button.dataset.graduationSort === graduationSortBy);
            });
        };
        const render = async (page = 1, pageSize = Number(field('graduation-per-page').value || 10)) => {
            currentPage = page;
            const query = new URLSearchParams({
                page,
                perPage: pageSize,
                academic_year_id: field('graduation-year').value,
                campus_id: field('graduation-campus').value,
                class_id: field('graduation-class').value,
                search: field('graduation-search').value,
                sortBy: graduationSortBy,
                sortDir: graduationSortDir,
            });
            const response = await fetch(`/student-graduations/fetch?${query.toString()}`, { headers: { Accept: 'application/json' } });
            const result = await response.json();
            const rows = result.data || [];
            field('graduationTable').innerHTML = rows.length ? rows.map((item, index) => {
                const student = item.student || {};
                const rowNumber = Number(result.from || 1) + index;
                const track = trackLabel(item);
                return `<tr><td>${rowNumber}</td><td>${studentPhoto(student)}</td><td>${esc(student.student_id || student.student_no || '-')}</td><td><div class="graduation-student-name-kh school-profile-khmer">${esc(studentNameKh(student) || '-')}</div><div class="graduation-student-name-en">${esc(studentName(student) || '-')}</div></td><td>${esc(item.academic_year?.academic_year || '-')}</td><td><div>${esc(gradeClassLabel(item))}</div>${track ? `<div class="text-secondary small">${esc(track)}</div>` : ''}</td><td>${esc(item.session?.session_short_name || '-')}</td><td>${esc(item.campus?.campus_name_en || '-')}</td><td>${esc(formatDate(item.graduation_date))}</td><td>${esc(item.certificate_number || '-')}</td><td><span class="badge bg-${item.is_alumni ? 'success' : 'secondary'}-lt">${item.is_alumni ? 'Yes' : 'No'}</span></td><td>${esc(item.changed_by?.name || 'System')}</td></tr>`;
            }).join('') : '<tr><td colspan="12" class="text-center">No graduated students found.</td></tr>';
            renderPagination(result, pageSize);
            updateSortIcons();
        };

        const refreshEnrollmentOptions = async () => {
            const data = await loadOptions(false);
            selectedGraduateEnrollmentIds.clear();
            field('graduation-enrollment').innerHTML = (data.enrollments || []).map((item) => {
                const name = item.student?.full_name_en || '';
                const info = `${item.academic_year?.academic_year || '-'} | ${item.campus?.campus_name_en || '-'} | Class ${item.school_class?.class_name || '-'} | Group ${item.session?.session_short_name || '-'}`;
                return `<option value="${item.id}" data-info="${esc(info)}">${esc(`${item.student?.student_id || item.student?.student_no || ''} - ${name}`)}</option>`;
            }).join('');
            fill('batch-year', data.academicYears || [], 'academic_year');
            fill('batch-campus', data.campuses || [], 'campus_name_en');
            fill('batch-class', data.classes || [], classOptionLabel);
            setupSearchables();
            makeEnrollmentMultiselect();
            refreshEnrollmentMultiValue();
            renderEnrollmentMultiOptions();
        };
        const applyScope = () => {
            const scope = field('graduation-scope').value;
            const batch = scope !== 'student';
            document.querySelectorAll('.graduation-student-field').forEach((element) => element.classList.toggle('d-none', batch));
            field('graduation-batch-block').classList.toggle('d-none', !batch);
            field('batch-campus-block').classList.toggle('d-none', scope === 'all_campuses');
            field('batch-class-block').classList.toggle('d-none', scope !== 'class');
            field('batch-year').required = batch;
            field('batch-campus').required = ['class', 'campus'].includes(scope);
            field('batch-class').required = scope === 'class';
        };

        field('graduation-year').onchange = async () => { await refreshCampuses(field('graduation-year').value, true, 'graduation-campus'); await refreshClasses(field('graduation-campus').value, field('graduation-year').value, 'graduation-class', true); await render(1); };
        field('graduation-campus').onchange = async () => { await refreshClasses(field('graduation-campus').value, field('graduation-year').value, 'graduation-class', true); await render(1); };
        field('graduation-class').onchange = () => render(1);
        field('graduation-search').oninput = () => render(1);
        document.querySelectorAll('[data-graduation-sort]').forEach((button) => button.addEventListener('click', () => {
            if (graduationSortBy === button.dataset.graduationSort) graduationSortDir = graduationSortDir === 'asc' ? 'desc' : 'asc';
            else { graduationSortBy = button.dataset.graduationSort; graduationSortDir = 'asc'; }
            render(1);
        }));
        field('graduation-scope').onchange = applyScope;
        field('certificate-number').oninput = () => { field('certificate-number').value = field('certificate-number').value.replace(/\D/g, '').slice(0, 4); };
        field('batch-campus').onchange = () => refreshClasses(field('batch-campus').value, field('batch-year').value, 'batch-class', false);
        field('batch-year').onchange = async () => { await refreshCampuses(field('batch-year').value, false, 'batch-campus'); await refreshClasses(field('batch-campus').value, field('batch-year').value, 'batch-class', false); };
        document.addEventListener('click', (event) => {
            if (!event.target.closest('.location-combobox')) {
                Object.values(searchable).forEach((item) => item.menu.classList.add('d-none'));
                enrollmentMulti?.menu.classList.add('d-none');
            }
        });

        field('newGraduation').onclick = async () => {
            await refreshEnrollmentOptions();
            field('graduationForm').reset();
            field('graduation-scope').value = 'student';
            applyScope();
            field('graduation-date').value = new Date().toISOString().slice(0,10);
            field('certificate-number').value = '';
            selectedGraduateEnrollmentIds.clear();
            field('graduation-current').textContent = '';
            refreshEnrollmentMultiValue();
            field('graduationError').classList.add('d-none');
            modal.show();
            Object.keys(searchable).forEach(refreshSearchableValue);
        };
        field('graduationForm').onsubmit = async (event) => {
            event.preventDefault();
            const certificatePrefix = field('certificate-number').value.trim();
            if (!/^\d{4}$/.test(certificatePrefix)) {
                field('graduationError').textContent = 'Please enter exactly 4 digits for the certificate prefix.';
                field('graduationError').classList.remove('d-none');
                return;
            }
            const scope = field('graduation-scope').value;
            const payload = { graduation_date: field('graduation-date').value, certificate_number: certificatePrefix, is_alumni: field('is-alumni').checked ? 1 : 0, notes: field('graduation-notes').value };
            const endpoint = scope === 'student' ? 'graduate' : 'graduate-batch';
            if (scope === 'student') {
                const enrollmentIds = Array.from(selectedGraduateEnrollmentIds);
                if (!enrollmentIds.length) {
                    field('graduationError').textContent = 'Please select at least one Grade 12 student.';
                    field('graduationError').classList.remove('d-none');
                    return;
                }
                payload.enrollment_ids = enrollmentIds;
            }
            else { payload.scope = scope; payload.academic_year_id = field('batch-year').value; payload.campus_id = scope === 'all_campuses' ? '' : field('batch-campus').value; payload.class_id = scope === 'class' ? field('batch-class').value : ''; }
            const response = await fetch(`/student-graduations/${endpoint}`, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(payload) });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) { field('graduationError').textContent = result.message || Object.values(result.errors || {})[0]?.[0] || 'Unable to graduate student.'; field('graduationError').classList.remove('d-none'); return; }
            modal.hide();
            await loadListOptions();
            await render(currentPage);
        };

        loadListOptions().then(() => render()).catch(() => {
            field('graduationTable').innerHTML = '<tr><td colspan="12" class="text-center text-danger">Unable to load graduation records.</td></tr>';
        });
    });
})();
</script>
@endsection
