            document.addEventListener('DOMContentLoaded', () => {
                const root = document.querySelector('[data-enrollment-workflows]');
                if (!root) return;
                const mode = root.dataset.workflowMode || 'transfer';
                const isPromotion = mode === 'promotion';
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('workflowModal'));
                const form = document.getElementById('workflowForm');
                const field = (id) => document.getElementById(id);
                const searchable = {};
                let options = {};
                let currentPage = 1;
                let enrollmentSearchTimer = null;
                let workflowSortBy = 'promoted_date';
                let workflowSortDir = 'desc';

                const esc = (value = '') => String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g,
                    '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                let workflowRows = new Map();
                const formatDateTime = (value = '') => {
                    if (!value) return '-';
                    const date = new Date(value);
                    if (Number.isNaN(date.getTime())) return value;
                    const pad = (number) => String(number).padStart(2, '0');
                    return `${pad(date.getDate())}-${pad(date.getMonth() + 1)}-${date.getFullYear()} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
                };
                const studentName = (student = {}) => student.full_name_en || '';
                const studentNameKh = (student = {}) => student.full_name_kh || '';
                const enrollmentLabel = (item) =>
                    `${item.student?.student_id || item.student?.student_no || ''} - ${studentName(item.student) || 'Student'}`;
                const isSelectedStudentAction = () => ['selected_promotion', 'selected_transfer'].includes(field(
                    'action_type').value);
                const actionLabel = (action) => ({
                    promotion: 'Student Promotion',
                    class_promotion: 'Class Promotion',
                    selected_promotion: 'Selected Promotion',
                    re_promotion: 'Promoted Again',
                    transfer: 'Student Transfer',
                    class_transfer: 'Class Transfer',
                    selected_transfer: 'Selected Transfer',
                } [action] || action);
                const workflowStatusLabel = (status) => ({
                    cancelled: 'Cancelled',
                    reversed: 'Reversed',
                    completed: 'Active',
                } [status] || 'Active');
                const workflowStatusBadge = (status) => ({
                    cancelled: 'yellow',
                    reversed: 'secondary',
                    completed: 'green',
                } [status] || 'green');

                const fill = (id, items, value, label) => {
                    field(id).innerHTML = `<option value=""></option>` + items.map((item) =>
                        `<option value="${item.id}">${esc(item[value] || '')}</option>`).join('');
                };
                const orderedAcademicYearsAsc = () => [...(options.academicYears || [])].sort((a, b) => String(a
                    .academic_year || '').localeCompare(String(b.academic_year || ''), undefined, {
                    numeric: true,
                    sensitivity: 'base'
                }));
                const setNextTargetAcademicYear = (sourceAcademicYearId = '') => {
                    if (!isPromotion || !sourceAcademicYearId) return;
                    const years = orderedAcademicYearsAsc();
                    const index = years.findIndex((year) => String(year.id) === String(sourceAcademicYearId));
                    const nextYear = index >= 0 ? years[index + 1] : null;
                    field('to_academic_year_id').value = nextYear?.id || '';
                    refreshSearchableValue('to_academic_year_id');
                };
                const currentSourceAcademicYearId = () => {
                    const action = field('action_type').value;
                    return ['class_promotion', 'selected_promotion'].includes(action) ? field(
                        'from_academic_year_id').value : field('student_from_academic_year_id').value;
                };
                const gradeClassLabel = (row = {}) => `${row.grade || ''}${row.class_name || ''}`.replace(/^Grade\s+/i,
                    '').trim();
                const workflowGradeClass = (item = {}) => gradeClassLabel({
                    grade: item.to_grade?.grade,
                    class_name: item.to_class?.class_name
                }) || '-';
                const workflowFromGradeClass = (item = {}) => gradeClassLabel({
                    grade: item.from_grade?.grade,
                    class_name: item.from_class?.class_name
                }) || '-';
                const workflowGradeGroup = (gradeClass, group) => {
                    const cleanGradeClass = String(gradeClass || '').trim();
                    const cleanGroup = String(group || '').trim();
                    if (!cleanGradeClass || cleanGradeClass === '-') return cleanGroup || '-';
                    return cleanGroup && cleanGroup !== '-' ? `${cleanGradeClass}-${cleanGroup}` : cleanGradeClass;
                };
                const sameWorkflowValue = (fromValue, toValue) => String(fromValue || '').trim().toLowerCase() === String(toValue || '').trim().toLowerCase();
                const workflowInfoBlock = (academicYear, campus, gradeGroup, changed = {}) => `
                    <div class="workflow-info-stack">
                        <div><strong class="${changed.academicYear ? 'workflow-changed-value' : ''}">${esc(academicYear || '-')}</strong></div>
                        <div><strong class="${changed.campusGradeGroup ? 'workflow-changed-value' : ''}">${esc(`${campus || '-'}-${gradeGroup || '-'}`)}</strong></div>
                    </div>
                `;
                const workflowStudentPhoto = (student = {}) => student.photo_path ?
                    `<img class="workflow-student-photo" src="/storage/${esc(student.photo_path)}" alt="${esc(studentName(student) || 'Student photo')}">` :
                    `<span class="workflow-student-photo-placeholder"><i class="ti ti-user"></i></span>`;
                const uniqueBy = (items, keyGetter) => {
                    const map = new Map();
                    items.forEach((item) => {
                        const key = keyGetter(item);
                        if (key !== undefined && key !== null && key !== '' && !map.has(String(key))) map
                            .set(String(key), item);
                    });
                    return Array.from(map.values());
                };
                const fillSourceSelect = (id, items, valueGetter, labelGetter) => {
                    const select = field(id);
                    const selected = select.value;
                    const normalized = items
                        .map((item) => ({
                            value: String(valueGetter(item) ?? ''),
                            label: String(labelGetter(item) ?? '')
                        }))
                        .filter((item) => item.value && item.label);
                    const isHistoryFilter = id.startsWith('workflow-history-');
                    const emptyLabel = id === 'workflow-history-academic-year' ? 'All Academic Years' : id ===
                        'workflow-history-campus' ? 'All Campuses' : id === 'workflow-history-grade-class' ?
                        'All Grades' : '';
                    select.innerHTML = `<option value="">${emptyLabel}</option>` + normalized.map((item) =>
                        `<option value="${esc(item.value)}">${esc(item.label)}</option>`).join('');
                    select.value = normalized.some((item) => item.value === selected) ? selected : '';
                    refreshSearchableValue(id);
                    renderSearchable(id);
                };
                const populateWorkflowHistoryFilters = () => {
                    const rows = options.workflowHistoryFilters || [];
                    const years = uniqueBy(rows, (row) => row.academic_year_id)
                        .sort((a, b) => String(b.academic_year || '').localeCompare(String(a.academic_year || ''),
                            undefined, {
                                numeric: true,
                                sensitivity: 'base'
                            }));
                    const campuses = uniqueBy(rows, (row) => row.campus_id)
                        .sort((a, b) => String(a.campus_name_en || '').localeCompare(String(b.campus_name_en || ''),
                            undefined, {
                                numeric: true,
                                sensitivity: 'base'
                            }));
                    const gradeClasses = uniqueBy(rows, (row) => `${row.grade_id}:${row.class_id}`)
                        .map((row) => ({
                            ...row,
                            label: gradeClassLabel(row)
                        }))
                        .sort((a, b) => String(a.label || '').localeCompare(String(b.label || ''), undefined, {
                            numeric: true,
                            sensitivity: 'base'
                        }));
                    fillSourceSelect('workflow-history-academic-year', years, (row) => row.academic_year_id, (
                        row) => row.academic_year);
                    fillSourceSelect('workflow-history-campus', campuses, (row) => row.campus_id, (row) => row
                        .campus_name_en);
                    fillSourceSelect('workflow-history-grade-class', gradeClasses, (row) =>
                        `${row.grade_id}:${row.class_id}`, (row) => row.label);
                };
                const sourceFilterRows = ({
                    academicYearId = '',
                    campusId = '',
                    gradeId = ''
                } = {}) => (options.sourceClassFilters || []).filter((row) => {
                    if (academicYearId && String(row.academic_year_id) !== String(academicYearId)) return false;
                    if (campusId && String(row.campus_id) !== String(campusId)) return false;
                    if (gradeId && String(row.grade_id) !== String(gradeId)) return false;
                    return true;
                });
                const refreshSourceClassFilters = (changed = '') => {
                    if (changed === 'academic_year') {
                        field('from_campus_id').value = '';
                        field('from_grade_id').value = '';
                        field('from_class_id').value = '';
                    } else if (changed === 'campus') {
                        field('from_grade_id').value = '';
                        field('from_class_id').value = '';
                    } else if (changed === 'grade') {
                        field('from_class_id').value = '';
                    }

                    const academicYearId = field('from_academic_year_id').value;
                    const campusOptions = uniqueBy(sourceFilterRows({
                            academicYearId
                        }), (row) => row.campus_id)
                        .sort((a, b) => String(a.campus_name_en || '').localeCompare(String(b.campus_name_en || ''),
                            undefined, {
                                numeric: true,
                                sensitivity: 'base'
                            }));
                    fillSourceSelect('from_campus_id', campusOptions, (row) => row.campus_id, (row) => row
                        .campus_name_en);

                    const campusId = field('from_campus_id').value;
                    const gradeOptions = uniqueBy(sourceFilterRows({
                            academicYearId,
                            campusId
                        }), (row) => row.grade_id)
                        .sort((a, b) => Number(a.grade_order || 0) - Number(b.grade_order || 0));
                    fillSourceSelect('from_grade_id', gradeOptions, (row) => row.grade_id, (row) => row.grade);

                    const gradeId = field('from_grade_id').value;
                    const classOptions = uniqueBy(sourceFilterRows({
                            academicYearId,
                            campusId,
                            gradeId
                        }), (row) => row.class_id)
                        .sort((a, b) => String(a.class_name || '').localeCompare(String(b.class_name || ''),
                            undefined, {
                                numeric: true,
                                sensitivity: 'base'
                            }));
                    fillSourceSelect('from_class_id', classOptions, (row) => row.class_id, (row) => row.class_name);
                    setTargetGradeOptions();
                };
                const orderedGrades = () => [...(options.grades || [])].sort((a, b) => Number(a.grade_order || 0) -
                    Number(b.grade_order || 0));
                const nextPromotionGrade = (sourceGradeId) => {
                    const grades = orderedGrades();
                    const index = grades.findIndex((grade) => String(grade.id) === String(sourceGradeId || ''));
                    return index >= 0 ? grades[index + 1] : null;
                };
                const selectedEnrollmentGradeId = () => field('enrollment_id')?.selectedOptions?.[0]?.dataset.gradeId ||
                    '';
                const currentPromotionSourceGradeId = () => {
                    const action = field('action_type').value;
                    return ['class_promotion', 'selected_promotion'].includes(action) ? field('from_grade_id')
                        .value : selectedEnrollmentGradeId();
                };
                const setTargetGradeOptions = () => {
                    if (!isPromotion) return;
                    const sourceGradeId = currentPromotionSourceGradeId();
                    const nextGrade = nextPromotionGrade(sourceGradeId);
                    if (!sourceGradeId) {
                        fill('to_grade_id', options.grades || [], 'grade', 'Grade');
                        refreshSearchableValue('to_grade_id');
                        renderSearchable('to_grade_id');
                        return;
                    }
                    if (!nextGrade) {
                        const sourceGrade = orderedGrades().find((grade) => String(grade.id) === String(
                            sourceGradeId));
                        field('to_grade_id').innerHTML = `<option value=""></option>`;
                        field('to_grade_id').value = '';
                        refreshSearchableValue('to_grade_id');
                        renderSearchable('to_grade_id');
                        return;
                    }
                    field('to_grade_id').innerHTML =
                        `<option value=""></option><option value="${nextGrade.id}">${esc(nextGrade.grade || '')}</option>`;
                    field('to_grade_id').value = nextGrade.id;
                    refreshSearchableValue('to_grade_id');
                    renderSearchable('to_grade_id');
                };

                const sourceYearId = () => field('student_from_academic_year_id')?.value || options
                    .currentAcademicYearId || '';

                const loadEnrollmentOptions = async (params = {}) => {
                    const query = new URLSearchParams({
                        academic_year_id: params.academic_year_id || sourceYearId(),
                        search: params.search || '',
                        limit: params.limit || 50,
                    });
                    if (params.campus_id) query.set('campus_id', params.campus_id);
                    if (params.grade_id) query.set('grade_id', params.grade_id);
                    if (params.class_id) query.set('class_id', params.class_id);
                    if (isPromotion && params.target_academic_year_id) query.set('target_academic_year_id',
                        params.target_academic_year_id);
                    const response = await fetch(
                        `/student-enrollment-workflows/enrollments?${query.toString()}`);
                    return response.json();
                };

                const fillStudentEnrollments = async (search = '') => {
                    const enrollments = await loadEnrollmentOptions({
                        search,
                        target_academic_year_id: field('to_academic_year_id')?.value || '',
                        limit: 50,
                    });
                    field('enrollment_id').innerHTML = '<option value=""></option>' + enrollments.map((
                    item) => {
                        const info =
                            `${item.academic_year?.academic_year || '-'} | ${item.campus?.campus_name_en || '-'} | ${item.grade?.grade || '-'} | ${item.school_class?.class_name || '-'} | Group ${item.session?.session_short_name || '-'}`;
                        return `<option value="${item.id}" data-grade-id="${esc(item.grade_id || '')}" data-info="${esc(info)}" data-search="${esc(`${enrollmentLabel(item)} ${info}`.toLowerCase())}">${esc(enrollmentLabel(item))}</option>`;
                    }).join('');
                    refreshSearchableValue('enrollment_id');
                    renderSearchable('enrollment_id');
                    setTargetGradeOptions();
                };

                const renderSearchable = (id) => {
                    const item = searchable[id];
                    if (!item) return;
                    const term = item.search.value.trim().toLowerCase();
                    const sourceOptions = id === 'action_type'
                        ? Array.from(item.select.options)
                        : Array.from(item.select.options).slice(1);
                    const matches = sourceOptions
                        .filter((option) => (option.dataset.search ||
                            `${option.textContent} ${option.dataset.info || ''}`.toLowerCase()).includes(term));
                    const visibleMatches = matches.slice(0, 50);
                    const allOption = ['workflow-history-academic-year', 'workflow-history-campus',
                            'workflow-history-grade-class', 'workflow-history-status'
                        ].includes(id) ?
                        `<button type="button" class="location-combobox-option" data-value="">${id === 'workflow-history-academic-year' ? 'All Academic Years' : id === 'workflow-history-campus' ? 'All Campuses' : id === 'workflow-history-grade-class' ? 'All Grades' : 'All Status'}</button>` :
                        '';
                    item.results.innerHTML = allOption + (visibleMatches.length ? visibleMatches.map((option) => `
            <button type="button" class="location-combobox-option" data-value="${option.value}">
                <span class="d-block">${esc(option.textContent)}</span>
                ${option.dataset.info ? `<small class="text-secondary d-block">${esc(option.dataset.info)}</small>` : ''}
            </button>
        `).join('') + (matches.length > visibleMatches.length ?
                        '<div class="text-secondary px-2 py-2 small">Type more to narrow the list.</div>' :
                        '') : '<div class="text-secondary px-2 py-2">No results found</div>');
                    item.results.querySelectorAll('[data-value]').forEach((button) => {
                        button.onclick = () => {
                            item.select.value = button.dataset.value;
                            item.select.dispatchEvent(new Event('change', {
                                bubbles: true
                            }));
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
                    wrapper.innerHTML = `
            <button type="button" class="location-combobox-toggle">
                <span class="location-combobox-selected"></span>
                <i class="ti ti-chevron-down"></i>
            </button>
            <div class="location-combobox-menu d-none">
                <input type="search" class="form-control location-combobox-search" placeholder="${id === 'enrollment_id' ? 'Search by Student ID or name' : 'Search'}">
                <div class="location-combobox-results"></div>
            </div>
            ${id === 'enrollment_id' ? '<div class="workflow-current-info text-secondary small mt-2"></div>' : ''}
        `;
                    select.after(wrapper);
                    const item = searchable[id] = {
                        select,
                        selected: wrapper.querySelector('.location-combobox-selected'),
                        menu: wrapper.querySelector('.location-combobox-menu'),
                        search: wrapper.querySelector('.location-combobox-search'),
                        results: wrapper.querySelector('.location-combobox-results'),
                        info: wrapper.querySelector('.workflow-current-info'),
                    };
                    wrapper.querySelector('.location-combobox-toggle').onclick = () => {
                        Object.values(searchable).forEach((other) => {
                            if (other !== item) other.menu.classList.add('d-none');
                        });
                        item.menu.classList.toggle('d-none');
                        item.search.value = '';
                        renderSearchable(id);
                        item.search.focus();
                    };
                    item.search.oninput = () => renderSearchable(id);
                    if (id === 'enrollment_id') {
                        item.search.oninput = () => {
                            window.clearTimeout(enrollmentSearchTimer);
                            enrollmentSearchTimer = window.setTimeout(() => {
                                fillStudentEnrollments(item.search.value).catch(() => {
                                    item.results.innerHTML =
                                        '<div class="text-danger px-2 py-2">Unable to search students.</div>';
                                });
                            }, 250);
                        };
                    }
                };

                const refreshSearchables = () => ['action_type', 'workflow-history-academic-year', 'workflow-history-campus',
                    'workflow-history-grade-class', 'workflow-history-status', 'student_from_academic_year_id', 'enrollment_id',
                    'from_academic_year_id', 'from_campus_id', 'from_grade_id', 'from_class_id',
                    'to_academic_year_id', 'to_campus_id', 'to_grade_id', 'to_class_id', 'to_session_id'
                ].forEach(makeSearchable);

                function refreshSearchableValues() {
                    Object.keys(searchable).forEach(refreshSearchableValue);
                }

                function refreshSearchableValue(id) {
                    const item = searchable[id];
                    if (!item) return;
                    const selected = item.select.options[item.select.selectedIndex];
                    item.selected.textContent = selected?.value ? selected.textContent : (id ===
                        'workflow-history-academic-year' ? 'All Academic Years' : id === 'workflow-history-campus' ?
                        'All Campuses' : id === 'workflow-history-grade-class' ? 'All Grades' : id === 'workflow-history-status' ? 'All Status' : '');
                    item.select.closest('.premium-form-field')?.classList.toggle('has-value', Boolean(selected?.value));
                    if (item.info) item.info.textContent = selected?.dataset.info || '';
                }

                const loadOptions = async () => {
                    const response = await fetch(`/student-enrollment-workflows/options?mode=${mode}`);
                    options = await response.json();
                    populateWorkflowHistoryFilters();
                    fill('to_academic_year_id', options.academicYears || [], 'academic_year', 'Academic Year');
                    fill('from_academic_year_id', options.academicYears || [], 'academic_year',
                    'Academic Year');
                    fill('student_from_academic_year_id', options.academicYears || [], 'academic_year',
                        'Academic Year');
                    fill('to_campus_id', options.campuses || [], 'campus_name_en', 'Campus');
                    fill('to_grade_id', options.grades || [], 'grade', 'Grade');
                    fill('to_class_id', options.classes || [], 'class_name', 'Class');
                    fill('to_session_id', options.groups || [], 'session_short_name', 'Group');
                    field('student_from_academic_year_id').value = options.currentAcademicYearId || '';
                    field('from_academic_year_id').value = options.currentAcademicYearId || '';
                    refreshSourceClassFilters();
                    if (isPromotion && options.nextAcademicYearId) field('to_academic_year_id').value = options
                        .nextAcademicYearId;
                    setTargetGradeOptions();
                    refreshSearchables();
                    refreshSearchableValues();
                };

                const renderPagination = (result, pageSize) => {
                    const container = document.getElementById('workflow-pagination-container');
                    const totalPages = Number(result.last_page || 1);
                    const currentPage = Number(result.current_page || 1);
                    const pages = [];
                    const addPage = (page) => pages.push(page);
                    const addEllipsis = () => pages.push('ellipsis');

                    if (totalPages <= 5) {
                        for (let page = 1; page <= totalPages; page++) addPage(page);
                    } else if (currentPage <= 3) {
                        [1, 2, 3].forEach(addPage);
                        addEllipsis();
                        addPage(totalPages);
                    } else if (currentPage >= totalPages - 2) {
                        addPage(1);
                        addEllipsis();
                        for (let page = totalPages - 2; page <= totalPages; page++) addPage(page);
                    } else {
                        addPage(1);
                        addEllipsis();
                        [currentPage - 1, currentPage, currentPage + 1].forEach(addPage);
                        addEllipsis();
                        addPage(totalPages);
                    }
                    container.innerHTML = `
            <div class="premium-pagination">
                <ul class="pagination premium-pagination-list m-0">
                    <li class="page-item ${currentPage === 1 ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${currentPage - 1}"><i class="ti ti-chevron-left icon icon-1"></i></a></li>
                    ${pages.map((page) => page === 'ellipsis' ? '<li class="premium-pagination-ellipsis">...</li>' : `<li class="page-item ${page === currentPage ? 'active' : ''}"><a class="page-link" href="#" data-page="${page}">${page}</a></li>`).join('')}
                    <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${currentPage + 1}"><i class="ti ti-chevron-right icon icon-1"></i></a></li>
                </ul>
                <p class="premium-pagination-info m-0">Showing <strong>${result.from ?? 0} to ${result.to ?? 0}</strong> of <strong>${result.total || 0} entries</strong></p>
                <div class="premium-pagination-controls">
                    <label class="premium-pagination-select">
                        <select class="form-select form-select-sm">${[10,25,50,100].map((value) => `<option value="${value}" ${String(value) === String(pageSize) ? 'selected' : ''}>${value} / page</option>`).join('')}</select>
                    </label>
                </div>
            </div>
        `;
                    container.querySelectorAll('.page-link').forEach((link) => {
                        link.onclick = (event) => {
                            event.preventDefault();
                            const page = Number(link.dataset.page);
                            if (page >= 1 && page <= totalPages && page !== Number(result.current_page))
                                render(page, pageSize);
                        };
                    });
                    container.querySelector('select')?.addEventListener('change', (event) => {
                        field('workflow-per-page').value = event.target.value;
                        render(1, Number(event.target.value));
                    });
                };

                const renderSelectedStudents = async () => {
                    const ids = ['from_academic_year_id', 'from_campus_id', 'from_grade_id', 'from_class_id']
                        .map((id) => field(id).value);
                    const list = document.getElementById('selected-students-list');
                    if (ids.some((value) => !value)) {
                        list.innerHTML =
                            '<div class="text-secondary small">Select academic year, campus, grade, and class first.</div>';
                        return;
                    }
                    list.innerHTML = '<div class="text-secondary small">Loading students...</div>';
                    const matching = await loadEnrollmentOptions({
                        academic_year_id: ids[0],
                        campus_id: ids[1],
                        grade_id: ids[2],
                        class_id: ids[3],
                        target_academic_year_id: field('to_academic_year_id').value,
                        limit: 5000,
                    });
                    matching.sort((a, b) => studentName(a.student).localeCompare(studentName(b.student),
                        undefined, {
                            numeric: true,
                            sensitivity: 'base'
                        }));
                    list.innerHTML = matching.length ? matching.map((item) => `
            <label class="form-check mb-1">
                <input class="form-check-input selected-class-student" type="checkbox" value="${item.id}">
                <span class="form-check-label">${esc(enrollmentLabel(item))}</span>
            </label>
        `).join('') : `<div class="text-secondary small">No active students found in the selected class.</div>`;
                };

                const refreshSelectedStudentsIfNeeded = () => {
                    if (!isSelectedStudentAction()) return;
                    renderSelectedStudents().catch(() => {
                        document.getElementById('selected-students-list').innerHTML =
                            '<div class="text-danger small">Unable to load students.</div>';
                    });
                };

                const render = async (page = 1, pageSize = Number(field('workflow-per-page').value)) => {
                    currentPage = page;
                    const term = field('workflow-search').value || '';
                    const params = new URLSearchParams({
                        mode,
                        page,
                        perPage: pageSize,
                        search: term,
                        sortBy: workflowSortBy,
                        sortDir: workflowSortDir,
                        academic_year_id: field('workflow-history-academic-year')?.value || '',
                        campus_id: field('workflow-history-campus')?.value || '',
                        grade_class: field('workflow-history-grade-class')?.value || '',
                        status: field('workflow-history-status')?.value || '',
                    });
                    const response = await fetch(`/student-enrollment-workflows/fetch?${params.toString()}`);
                    const result = await response.json();
                    const rows = result.data || [];
                    workflowRows = new Map(rows.map((item) => [String(item.id), item]));
                    document.getElementById('workflowTable').innerHTML = rows.length ? rows.map((item,
                        index) => {
                            const student = item.student || {};
                            const nameKh = studentNameKh(student);
                            const nameEn = studentName(student);
                            const badge = item.action_type?.includes('promotion') ? 'green' : 'blue';
                            const isPromotionRow = isPromotion && item.action_type?.includes('promotion');
                            const isCancelled = item.status === 'cancelled';
                            const statusBadge = workflowStatusBadge(item.status);
                            const actionButtons = isPromotionRow ? (isCancelled ?
                                `<button type="button" class="btn btn-sm btn-outline-success workflow-row-action" data-workflow-action="repromote" data-workflow-id="${item.id}"><i class="ti ti-arrow-back-up"></i> Promote Again</button>` :
                                `<button type="button" class="btn btn-sm btn-outline-danger workflow-row-action" data-workflow-action="cancel" data-workflow-id="${item.id}"><i class="ti ti-ban"></i> Cancel Promotion</button>`
                            ) : '-';
                            const rowNumber = Number(result.from || 1) + index;
                            const mobileRowNumber = String(rowNumber).padStart(2, '0');
                            const dateLabel = isPromotion ? 'Promoted Date' : 'Transferred Date';
                            const byLabel = isPromotion ? 'Promoted By' : 'Transferred By';
                            const fromAcademicYear = item.from_academic_year?.academic_year || '-';
                            const toAcademicYear = item.to_academic_year?.academic_year || '-';
                            const fromCampus = item.from_campus?.campus_name_en || '-';
                            const toCampus = item.to_campus?.campus_name_en || '-';
                            const fromGradeGroup = workflowGradeGroup(workflowFromGradeClass(item), item.from_session?.session_short_name || '-');
                            const toGradeGroup = workflowGradeGroup(workflowGradeClass(item), item.to_session?.session_short_name || '-');
                            const workflowInformationCells = isPromotion ? `
                    <td data-label="Academic Year">${esc(toAcademicYear)}</td>
                    <td data-label="Grade">${esc(workflowGradeClass(item))}</td>
                    <td data-label="Group">${esc(item.to_session?.session_short_name || '-')}</td>
                    <td data-label="Campus">${esc(toCampus)}</td>` : `
                    <td class="workflow-info-cell workflow-info-from" data-label="Transfer From">${workflowInfoBlock(fromAcademicYear, fromCampus, fromGradeGroup)}</td>
                    <td class="workflow-info-cell workflow-info-to" data-label="Transfer To">${workflowInfoBlock(toAcademicYear, toCampus, toGradeGroup, {
                        academicYear: !sameWorkflowValue(fromAcademicYear, toAcademicYear),
                        campusGradeGroup: !sameWorkflowValue(fromCampus, toCampus) || !sameWorkflowValue(fromGradeGroup, toGradeGroup),
                    })}</td>
                    <td class="workflow-transfer-mobile-pair" colspan="2">
                        <div class="workflow-transfer-mobile-column">
                            <span>Transfer From</span>
                            ${workflowInfoBlock(fromAcademicYear, fromCampus, fromGradeGroup)}
                        </div>
                        <div class="workflow-transfer-mobile-column">
                            <span>Transfer To</span>
                            ${workflowInfoBlock(toAcademicYear, toCampus, toGradeGroup, {
                                academicYear: !sameWorkflowValue(fromAcademicYear, toAcademicYear),
                                campusGradeGroup: !sameWorkflowValue(fromCampus, toCampus) || !sameWorkflowValue(fromGradeGroup, toGradeGroup),
                            })}
                        </div>
                    </td>`;
                            return `
                <tr class="workflow-history-row ${isPromotion ? '' : 'workflow-transfer-row'}">
                    <td class="workflow-cell-number" data-label="No.">${mobileRowNumber}</td>
                    <td class="workflow-cell-photo" data-label="Photo">${workflowStudentPhoto(student)}</td>
                    <td class="workflow-cell-student-id" data-label="Student ID">${esc(student.student_id || student.student_no || '-')}</td>
                    <td class="workflow-cell-student-name" data-label="Student Name">
                        <div class="workflow-student-name-kh school-profile-khmer">${esc(nameKh || '-')}</div>
                        <div class="workflow-student-name-en">${esc(nameEn || '-')}</div>
                    </td>
                    ${workflowInformationCells}
                    <td data-label="Action"><span class="badge bg-${badge}-lt">${esc(actionLabel(item.action_type))}</span></td>
                    <td data-label="Status"><span class="badge bg-${statusBadge}-lt" title="${esc(item.cancellation_reason || '')}">${esc(workflowStatusLabel(item.status))}</span></td>
                    <td data-label="${dateLabel}">${esc(formatDateTime(item.updated_at || item.effective_on))}</td>
                    <td data-label="${byLabel}">${esc(item.changed_by?.name || item.changed_by?.username || 'System')}</td>
                    ${isPromotion ? `<td class="workflow-cell-actions text-nowrap" data-label="Actions">${actionButtons}</td>` : ''}
                </tr>
            `;
                        }).join('') :
                        `<tr><td colspan="${isPromotion ? 13 : 10}" class="text-center">No workflow actions found.</td></tr>`;
                    renderPagination(result, pageSize);
                    updateWorkflowSortIcons();
                };
                const updateWorkflowSortIcons = () => {
                    document.querySelectorAll('[data-workflow-sort-icon]').forEach((icon) => {
                        const selected = icon.dataset.workflowSortIcon === workflowSortBy;
                        icon.innerHTML = selected ?
                            `<i class="ti ${workflowSortDir === 'asc' ? 'ti-chevron-up' : 'ti-chevron-down'}"></i>` :
                            '<i class="ti ti-selector"></i>';
                    });
                    document.querySelectorAll('[data-workflow-sort]').forEach((button) => {
                        button.classList.toggle('text-primary', button.dataset.workflowSort ===
                            workflowSortBy);
                    });
                };

                const applyActionMode = () => {
                    const action = field('action_type').value;
                    const classAction = ['class_promotion', 'selected_promotion', 'selected_transfer',
                        'class_transfer'
                    ].includes(action);
                    const selectedAction = ['selected_promotion', 'selected_transfer'].includes(action);
                    document.querySelector('.class-action').classList.toggle('d-none', !classAction);
                    document.querySelectorAll('.student-action').forEach((item) => item.classList.toggle('d-none',
                        classAction));
                    document.querySelector('.student-source-title').classList.toggle('d-none', classAction);
                    document.querySelector('.student-source-title').textContent = 'Current Enrollment';
                    document.querySelector('.target-year').classList.toggle('d-none', !isPromotion);
                    document.querySelector('.selected-students-action').classList.toggle('d-none', !selectedAction);
                    document.querySelector('.selected-students-title').textContent = isPromotion ?
                        'Select Students to Promote' : 'Select Students to Transfer';
                    document.querySelector('.selected-students-help').textContent = isPromotion ?
                        'Only selected students will be promoted. Unselected students remain in the current class.' :
                        'Only selected students will be transferred. Unselected students remain in the current class.';
                    setNextTargetAcademicYear(currentSourceAcademicYearId());
                    setTargetGradeOptions();
                    refreshSelectedStudentsIfNeeded();
                };

                document.getElementById('newWorkflow').onclick = async () => {
                    form.reset();
                    field('action_type').value = isPromotion ? 'promotion' : 'transfer';
                    field('effective_on').value = new Date().toISOString().slice(0, 10);
                    field('workflowError').classList.add('d-none');
                    applyActionMode();
                    refreshSearchableValues();
                    modal.show();
                    try {
                        await loadOptions();
                        field('action_type').value = isPromotion ? 'promotion' : 'transfer';
                        if (options.currentAcademicYearId) {
                            field('student_from_academic_year_id').value = options.currentAcademicYearId;
                            field('from_academic_year_id').value = options.currentAcademicYearId;
                            refreshSourceClassFilters();
                        }
                        setNextTargetAcademicYear(currentSourceAcademicYearId());
                        await fillStudentEnrollments();
                        applyActionMode();
                        refreshSearchableValues();
                    } catch (error) {
                        field('workflowError').textContent =
                            'Unable to load promotion/transfer options. Please refresh and try again.';
                        field('workflowError').classList.remove('d-none');
                    }
                };

                field('action_type').addEventListener('change', applyActionMode);
                field('student_from_academic_year_id').addEventListener('change', async () => {
                    setNextTargetAcademicYear(field('student_from_academic_year_id').value);
                    await fillStudentEnrollments();
                    refreshSearchableValue('student_from_academic_year_id');
                    refreshSearchableValue('enrollment_id');
                    setTargetGradeOptions();
                });
                field('enrollment_id').addEventListener('change', () => {
                    setTargetGradeOptions();
                });
                field('from_academic_year_id').addEventListener('change', () => {
                    refreshSourceClassFilters('academic_year');
                    setNextTargetAcademicYear(field('from_academic_year_id').value);
                    refreshSelectedStudentsIfNeeded();
                });
                field('from_campus_id').addEventListener('change', () => {
                    refreshSourceClassFilters('campus');
                    refreshSelectedStudentsIfNeeded();
                });
                field('from_grade_id').addEventListener('change', () => {
                    refreshSourceClassFilters('grade');
                    refreshSelectedStudentsIfNeeded();
                });
                field('from_class_id').addEventListener('change', refreshSelectedStudentsIfNeeded);
                field('to_academic_year_id').addEventListener('change', refreshSelectedStudentsIfNeeded);
                document.addEventListener('click', (event) => {
                    if (event.target.id === 'select-all-class-students') {
                        document.querySelectorAll('.selected-class-student').forEach((checkbox) => {
                            checkbox.checked = true;
                        });
                    }
                    if (!event.target.closest('.location-combobox')) {
                        Object.values(searchable).forEach((item) => item.menu.classList.add('d-none'));
                    }
                });
                document.getElementById('workflow-search').oninput = () => render(1);
                ['workflow-history-academic-year', 'workflow-history-campus', 'workflow-history-grade-class', 'workflow-history-status'].forEach((
                    id) => {
                    field(id)?.addEventListener('change', () => render(1));
                });
                document.querySelectorAll('[data-workflow-sort]').forEach((button) => {
                    button.addEventListener('click', () => {
                        if (workflowSortBy === button.dataset.workflowSort) {
                            workflowSortDir = workflowSortDir === 'asc' ? 'desc' : 'asc';
                        } else {
                            workflowSortBy = button.dataset.workflowSort;
                            workflowSortDir = 'asc';
                        }
                        render(1);
                    });
                });

                document.getElementById('workflowTable').addEventListener('click', async (event) => {
                    const button = event.target.closest('.workflow-row-action');
                    if (!button) return;
                    const workflowId = button.dataset.workflowId;
                    const action = button.dataset.workflowAction;
                    const row = workflowRows.get(String(workflowId));
                    let reason = '';
                    let notes = '';
                    let effectiveOn = new Date().toISOString().slice(0, 10);

                    if (action === 'cancel') {
                        if (window.Swal) {
                            const result = await window.Swal.fire({
                                icon: 'warning',
                                title: 'Cancel this promotion?',
                                text: 'The enrollment will be kept in history and marked as cancelled.',
                                input: 'text',
                                inputLabel: 'Cancellation reason',
                                inputPlaceholder: 'Example: Student will not study this academic year',
                                inputValidator: (value) => value ? undefined : 'Please enter a reason.',
                                showCancelButton: true,
                                confirmButtonText: 'Cancel Promotion',
                                confirmButtonColor: '#d63939',
                            });
                            if (!result.isConfirmed) return;
                            reason = result.value;
                        } else {
                            reason = window.prompt('Cancellation reason:') || '';
                            if (!reason) return;
                        }
                    } else {
                        if (window.Swal) {
                            const result = await window.Swal.fire({
                                icon: 'question',
                                title: 'Promote this student again?',
                                text: 'The cancelled target enrollment will be reactivated and a new history event will be recorded.',
                                showCancelButton: true,
                                confirmButtonText: 'Promote Again',
                            });
                            if (!result.isConfirmed) return;
                        } else if (!window.confirm('Promote this student again?')) {
                            return;
                        }
                        reason = 'Student returned and was promoted again';
                    }

                    button.disabled = true;
                    const endpoint = action === 'cancel' ? 'cancel-promotion' : 'repromote';
                    const response = await fetch(`/student-enrollment-workflows/${workflowId}/${endpoint}`, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({ effective_on: effectiveOn, reason, notes }),
                    });
                    const result = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        const message = result.message || Object.values(result.errors || {}).flat()[0] || 'Unable to update workflow status.';
                        if (window.Swal) {
                            await window.Swal.fire({ icon: 'error', title: 'Action Failed', text: message });
                        } else {
                            window.alert(message);
                        }
                        button.disabled = false;
                        return;
                    }
                    if (window.Swal) {
                        await window.Swal.fire({
                            icon: 'success',
                            title: action === 'cancel' ? 'Promotion Cancelled' : 'Promoted Again',
                            text: result.message || 'Workflow status updated successfully.',
                            timer: 1800,
                            showConfirmButton: false,
                        });
                    }
                    await render(currentPage);
                });

                form.onsubmit = async (event) => {
                    event.preventDefault();
                    const action = field('action_type').value;
                    const classAction = ['class_promotion', 'selected_promotion', 'selected_transfer',
                        'class_transfer'
                    ].includes(action);
                    const ids = classAction ?
                        ['from_campus_id', 'from_academic_year_id', 'from_grade_id', 'from_class_id',
                            'to_campus_id', 'to_academic_year_id', 'to_grade_id', 'to_class_id',
                            'to_session_id', 'effective_on', 'reason', 'notes'
                        ] :
                        ['enrollment_id', 'to_academic_year_id', 'to_campus_id', 'to_grade_id', 'to_class_id',
                            'to_session_id', 'effective_on', 'reason', 'notes'
                        ];
                    const payload = Object.fromEntries(ids.map((id) => [id, field(id).value]));
                    if (!isPromotion) delete payload.to_academic_year_id;
                    if (['selected_promotion', 'selected_transfer'].includes(action)) {
                        payload.enrollment_ids = Array.from(document.querySelectorAll(
                            '.selected-class-student:checked')).map((checkbox) => Number(checkbox.value));
                    }
                    const endpoint = action === 'class_promotion' ? 'class-promote' :
                        action === 'selected_promotion' ? 'selected-promote' :
                        action === 'selected_transfer' ? 'selected-transfer' :
                        action === 'class_transfer' ? 'class-transfer' :
                        action === 'promotion' ? 'promote' :
                        'transfer';
                    const response = await fetch(`/student-enrollment-workflows/${endpoint}`, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf
                        },
                        body: JSON.stringify(payload),
                    });
                    const result = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        const message = result.message || Object.values(result.errors || {}).flat()[0] || 'Unable to save workflow action.';
                        field('workflowError').textContent = message;
                        field('workflowError').classList.remove('d-none');
                        const alreadyPromoted = /already (has an enrollment|promot)|already enrolled|have already been promoted/i.test(message);
                        if (window.Swal) {
                            await window.Swal.fire({
                                icon: alreadyPromoted ? 'warning' : 'error',
                                title: alreadyPromoted ? 'Already Promoted' : 'Promotion Failed',
                                text: message,
                                confirmButtonText: 'OK',
                            });
                        }
                        return;
                    }
                    modal.hide();
                    if (window.Swal) {
                        await window.Swal.fire({
                            icon: 'success',
                            title: isPromotion ? 'Promotion Completed' : 'Transfer Completed',
                            text: result.message || 'The workflow action was completed successfully.',
                            timer: 1800,
                            showConfirmButton: false,
                        });
                    }
                    await render(currentPage);
                };

                loadOptions()
                    .catch(() => {})
                    .finally(() => render().catch(() => {
                        document.getElementById('workflowTable').innerHTML =
                            '<tr><td colspan="11" class="text-center text-danger">Unable to load workflow history.</td></tr>';
                    }));
            });


