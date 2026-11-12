document.addEventListener('DOMContentLoaded', () => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || document.getElementById('graduationModal')?.dataset.csrf || '';
    const field = id => document.getElementById(id);
    const listCard = field('graduationListCard');
    const fullscreenButton = field('graduationFullscreen');
    const sizeList = () => {
        if (!listCard.classList.contains('is-fullscreen')) {
            listCard.style.setProperty('--graduation-list-top', `${Math.max(0, listCard.getBoundingClientRect().top)}px`);
        }
    };
    const setFullscreen = expanded => {
        listCard.classList.toggle('is-fullscreen', expanded);
        document.body.classList.toggle('graduation-list-expanded', expanded);
        fullscreenButton.setAttribute('aria-pressed', String(expanded));
        fullscreenButton.setAttribute('aria-label', expanded ? 'Exit graduation list full screen' : 'Expand graduation list to full screen');
        fullscreenButton.innerHTML = `<i class="ti ti-${expanded ? 'minimize' : 'maximize'}"></i><span>${expanded ? 'Exit full screen' : 'Full screen'}</span>`;
        if (!expanded) { sizeList(); fullscreenButton.focus(); }
    };
    fullscreenButton.addEventListener('click', () => setFullscreen(!listCard.classList.contains('is-fullscreen')));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !document.querySelector('.modal.show') && listCard.classList.contains('is-fullscreen')) setFullscreen(false);
    });
    window.addEventListener('resize', sizeList);
    requestAnimationFrame(sizeList);
    const modal = bootstrap.Modal.getOrCreateInstance(field('graduationModal'));
    let options = {};
    let currentPage = 1;
    let graduationSortBy = 'graduation_date';
    let graduationSortDir = 'desc';
    const searchable = {};
    const selectedGraduateEnrollmentIds = new Set();
    let enrollmentMulti = null;

    const esc = (value = '') => String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    const pad = number => String(number).padStart(2, '0');
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
    const gradeTrackCell = (item = {}) => {
        const track = trackLabel(item);
        return `<div class="graduation-grade-value">${esc(gradeClassLabel(item))}</div>${track ? `<div class="graduation-track-value">${esc(track)}</div>` : ''}`;
    };
    const classOptionLabel = (item = {}) => {
        if (item.display_name) return item.display_name;
        const grade = String(item.grade?.grade || '').replace(/^grade\s*/i, '').trim();
        const cls = String(item.class_name || '').replace(/^grade\s*/i, '').trim();
        return cls && grade && cls.toLowerCase().startsWith(grade.toLowerCase()) ? `Grade ${cls}` : `Grade ${grade}${cls}` || cls || '-';
    };
    const studentPhoto = (student = {}) => student.photo_path
        ? `<img class="graduation-student-photo" src="/storage/${esc(student.photo_path)}" alt="${esc(studentName(student) || 'Student photo')}">`
        : '<span class="graduation-student-photo-placeholder"><i class="ti ti-user"></i></span>';
    const graduationStatusBadge = (item = {}) =>
        `<span class="badge bg-${item.status === 'cancelled' ? 'danger' : (item.status === 'pending_result' ? 'warning' : 'success')}-lt" title="${esc(item.cancellation_reason || '')}">${item.status === 'cancelled' ? 'Cancelled' : (item.status === 'pending_result' ? 'Pending Exam Result' : 'Completed')}</span>`;
    const graduationActionButtons = (item = {}, showStatus = true) =>
        `${showStatus ? graduationStatusBadge(item) : ''}${item.status === 'cancelled' ? '' : `<div class="d-flex flex-wrap gap-1 mt-2">${item.status === 'pending_result' ? `<button class="btn btn-outline-success btn-sm" type="button" data-graduation-pass="${item.id}">Confirm Pass</button>` : ''}<button class="btn btn-outline-danger btn-sm graduation-cancel-btn" type="button" data-graduation-cancel="${item.id}"><i class="ti ti-ban me-1"></i>Cancel Graduation</button></div>`}`;
    const graduationMobileCard = (item = {}, rowNumber = 1) => {
        const student = item.student || {};
        return `
            <div class="graduation-mobile-card">
                <div class="graduation-mobile-number">${pad(rowNumber)}</div>
                <div class="graduation-mobile-status">${graduationStatusBadge(item)}</div>
                <div class="graduation-mobile-student">
                    ${studentPhoto(student)}
                    <div class="min-w-0">
                        <div class="graduation-mobile-student-id">Student ID: ${esc(student.student_id || student.student_no || '-')}</div>
                        <div class="graduation-student-name-kh school-profile-khmer">${esc(studentNameKh(student) || '-')}</div>
                        <div class="graduation-student-name-en">${esc(studentName(student) || '-')}</div>
                    </div>
                </div>
                <div class="graduation-mobile-details">
                    <div><span>Academic Year</span><strong>${esc(item.academic_year?.academic_year || '-')}</strong></div>
                    <div><span>Campus</span><strong>${esc(item.campus?.campus_name_en || '-')}</strong></div>
                    <div><span>Grade</span>${gradeTrackCell(item)}</div>
                    <div><span>Group</span><strong>${esc(item.session?.session_short_name || '-')}</strong></div>
                    <div><span>Graduation Date</span><strong>${esc(formatDate(item.graduation_date))}</strong></div>
                    <div><span>Certificate</span><strong>${esc(item.certificate_number || 'Not assigned')}</strong></div>
                    <div><span>Graduated By</span><strong>${esc(item.changed_by?.name || 'System')}</strong></div>
                </div>
                <div class="graduation-mobile-actions">${graduationActionButtons(item, false)}</div>
            </div>
        `;
    };
    const fill = (id, items, textKey, allLabel = '') => {
        const select = field(id);
        if (!select) return;
        const selected = select.value;
        const label = typeof textKey === 'function' ? textKey : item => item[textKey] || '';
        select.innerHTML = `<option value="">${esc(allLabel)}</option>` + (items || []).map(item => `<option value="${item.id}">${esc(label(item))}</option>`).join('');
        select.value = Array.from(select.options).some(option => option.value === selected) ? selected : '';
        refreshSearchableValue(id);
        renderSearchable(id);
    };

    const renderSearchable = id => {
        const item = searchable[id];
        if (!item) return;
        const term = item.search.value.trim().toLowerCase();
        const matches = Array.from(item.select.options).slice(1).filter(option => option.textContent.toLowerCase().includes(term));
        const allOption = ['graduation-year', 'graduation-campus', 'graduation-class'].includes(id)
            ? `<button type="button" class="location-combobox-option" data-value="">All ${id === 'graduation-year' ? 'Academic Years' : id === 'graduation-campus' ? 'Campuses' : 'Grades'}</button>`
            : '';
        item.results.innerHTML = allOption + (matches.length ? matches.slice(0, 60).map(option => `<button type="button" class="location-combobox-option" data-value="${option.value}">${esc(option.textContent)}</button>`).join('') : '<div class="text-secondary px-2 py-2">No results found</div>');
        item.results.querySelectorAll('[data-value]').forEach(button => {
            button.onclick = () => {
                item.select.value = button.dataset.value;
                item.select.dispatchEvent(new Event('change', { bubbles: true }));
                refreshSearchableValue(id);
                item.menu.classList.add('d-none');
            };
        });
    };
    const makeSearchable = id => {
        if (searchable[id]) return;
        const select = field(id);
        if (!select) return;
        select.classList.add('d-none');
        const wrapper = document.createElement('div');
        wrapper.className = 'location-combobox';
        wrapper.innerHTML = '<button type="button" class="location-combobox-toggle"><span class="location-combobox-selected"></span><i class="ti ti-chevron-down"></i></button><div class="location-combobox-menu d-none"><input type="search" class="form-control location-combobox-search" placeholder="Search"><div class="location-combobox-results"></div></div>';
        select.after(wrapper);
        searchable[id] = {
            select,
            selected: wrapper.querySelector('.location-combobox-selected'),
            menu: wrapper.querySelector('.location-combobox-menu'),
            search: wrapper.querySelector('.location-combobox-search'),
            results: wrapper.querySelector('.location-combobox-results')
        };
        const item = searchable[id];
        wrapper.querySelector('.location-combobox-toggle').onclick = () => {
            Object.values(searchable).forEach(other => { if (other !== item) other.menu.classList.add('d-none'); });
            enrollmentMulti?.menu.classList.add('d-none');
            item.menu.classList.toggle('d-none');
            item.search.value = '';
            renderSearchable(id);
            item.search.focus();
        };
        item.search.oninput = () => renderSearchable(id);
        select.addEventListener('change', () => refreshSearchableValue(id));
        refreshSearchableValue(id);
    };
    const refreshSearchableValue = id => {
        const item = searchable[id];
        if (!item) return;
        const selected = item.select.options[item.select.selectedIndex];
        item.selected.textContent = selected?.value ? selected.textContent : (id === 'graduation-year' ? 'All Academic Years' : id === 'graduation-campus' ? 'All Campuses' : id === 'graduation-class' ? 'All Grades' : id === 'student-year' || id === 'batch-year' ? 'Select Academic Year' : id === 'batch-campus' ? 'Select Campus' : id === 'batch-class' ? 'Select Class' : '');
    };
    const setupSearchables = () => ['graduation-year', 'graduation-campus', 'graduation-class', 'student-year', 'batch-year', 'batch-campus', 'batch-class'].forEach(makeSearchable);

    const enrollmentOptions = () => Array.from(field('graduation-enrollment').options);
    const selectedEnrollmentOptions = () => enrollmentOptions().filter(option => selectedGraduateEnrollmentIds.has(option.value));
    const refreshEnrollmentMultiValue = () => {
        if (!enrollmentMulti) return;
        const selected = selectedEnrollmentOptions();
        enrollmentMulti.selected.textContent = selected.length ? `${selected.length} student${selected.length > 1 ? 's' : ''} selected` : '';
        field('graduation-current').innerHTML = selected.length
            ? selected.map(option => `<span class="graduation-selected-pill">${esc(option.textContent)} <button type="button" data-remove-enrollment="${option.value}" aria-label="Remove student">&times;</button></span>`).join('')
            : '';
        field('graduation-current').querySelectorAll('[data-remove-enrollment]').forEach(button => {
            button.onclick = () => {
                selectedGraduateEnrollmentIds.delete(button.dataset.removeEnrollment);
                const option = enrollmentOptions().find(item => item.value === button.dataset.removeEnrollment);
                if (option) option.selected = false;
                refreshEnrollmentMultiValue();
                renderEnrollmentMultiOptions();
            };
        });
    };
    const renderEnrollmentMultiOptions = () => {
        if (!enrollmentMulti) return;
        const term = enrollmentMulti.search.value.trim().toLowerCase();
        const matches = enrollmentOptions().filter(option => option.textContent.toLowerCase().includes(term) || (option.dataset.info || '').toLowerCase().includes(term));
        enrollmentMulti.results.innerHTML = matches.length ? matches.slice(0, 120).map(option => `
                <button type="button" class="graduation-multiselect-option" data-value="${option.value}">
                    <input class="form-check-input" type="checkbox" ${selectedGraduateEnrollmentIds.has(option.value) ? 'checked' : ''}>
                    <span>
                        <span class="graduation-multiselect-option-title">${esc(option.textContent)}</span>
                        <span class="graduation-multiselect-option-meta d-block">${esc(option.dataset.info || '')}</span>
                    </span>
                </button>
            `).join('') : '<div class="text-secondary px-2 py-2">No results found</div>';
        enrollmentMulti.results.querySelectorAll('[data-value]').forEach(button => {
            button.onclick = () => {
                const value = button.dataset.value;
                const option = enrollmentOptions().find(item => item.value === value);
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
        wrapper.innerHTML = '<button type="button" class="location-combobox-toggle"><span class="location-combobox-selected"></span><i class="ti ti-chevron-down"></i></button><div class="location-combobox-menu d-none"><input type="search" class="form-control location-combobox-search" placeholder="Search student"><div class="location-combobox-results"></div></div>';
        select.after(wrapper);
        enrollmentMulti = {
            selected: wrapper.querySelector('.location-combobox-selected'),
            menu: wrapper.querySelector('.location-combobox-menu'),
            search: wrapper.querySelector('.location-combobox-search'),
            results: wrapper.querySelector('.location-combobox-results')
        };
        wrapper.querySelector('.location-combobox-toggle').onclick = () => {
            Object.values(searchable).forEach(item => item.menu.classList.add('d-none'));
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
        const pageSet = new Set([1, totalPages, current, current - 1, current + 1].filter(page => page >= 1 && page <= totalPages));
        const pages = [];
        [...pageSet].sort((a, b) => a - b).forEach(page => {
            const previous = pages[pages.length - 1];
            if (typeof previous === 'number' && page - previous > 1) pages.push('ellipsis');
            pages.push(page);
        });
        container.innerHTML = `<div class="premium-pagination"><ul class="pagination premium-pagination-list m-0"><li class="page-item ${result.current_page === 1 ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${result.current_page - 1}"><i class="ti ti-chevron-left icon icon-1"></i></a></li>${pages.map(page => page === 'ellipsis' ? '<li class="premium-pagination-ellipsis">...</li>' : `<li class="page-item ${page === result.current_page ? 'active' : ''}"><a class="page-link" href="#" data-page="${page}">${page}</a></li>`).join('')}<li class="page-item ${result.current_page === result.last_page ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${result.current_page + 1}"><i class="ti ti-chevron-right icon icon-1"></i></a></li></ul><p class="premium-pagination-info m-0">Showing <strong>${result.from ?? 0} to ${result.to ?? 0}</strong> of <strong>${result.total || 0} entries</strong></p><div class="premium-pagination-controls"><label class="premium-pagination-select"><select class="form-select form-select-sm">${[10, 25, 50, 100].map(value => `<option value="${value}" ${String(value) === String(pageSize) ? 'selected' : ''}>${value} / page</option>`).join('')}</select></label></div></div>`;
        container.querySelectorAll('.page-link').forEach(link => {
            link.onclick = event => {
                event.preventDefault();
                const page = Number(link.dataset.page);
                if (page >= 1 && page <= totalPages && page !== Number(result.current_page)) render(page, pageSize);
            };
        });
        container.querySelector('select')?.addEventListener('change', event => {
            field('graduation-per-page').value = event.target.value;
            render(1, Number(event.target.value));
        });
    };
    const updateSortIcons = () => {
        document.querySelectorAll('[data-graduation-sort-icon]').forEach(icon => {
            icon.textContent = icon.dataset.graduationSortIcon === graduationSortBy ? (graduationSortDir === 'asc' ? '\u2191' : '\u2193') : '\u2195';
        });
        document.querySelectorAll('[data-graduation-sort]').forEach(button => {
            button.classList.toggle('text-primary', button.dataset.graduationSort === graduationSortBy);
        });
    };
    const updateSummary = (summary = {}) => {
        const format = value => Number(value || 0).toLocaleString();
        field('graduation-total').textContent = format(summary.total);
        field('graduation-male').textContent = format(summary.male);
        field('graduation-female').textContent = format(summary.female);
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
            sortDir: graduationSortDir
        });
        const response = await fetch(`/student-graduations/fetch?${query.toString()}`, { headers: { Accept: 'application/json' } });
        const result = await response.json();
        updateSummary(result.summary || {});
        const rows = result.data || [];
        field('graduationMobileCards').innerHTML = rows.length ? rows.map((item, index) => graduationMobileCard(item, Number(result.from || 1) + index)).join('') : '<div class="graduation-mobile-empty text-center text-secondary">No graduated students found.</div>';
        field('graduationTable').innerHTML = rows.length ? rows.map((item, index) => {
            const student = item.student || {};
            const rowNumber = Number(result.from || 1) + index;
            return `<tr><td>${rowNumber}</td><td><div class="graduation-table-student">${studentPhoto(student)}<div class="min-w-0"><div class="graduation-student-code">${esc(student.student_id || student.student_no || "-")}</div><div class="graduation-student-name-kh school-profile-khmer">${esc(studentNameKh(student) || "-")}</div><div class="graduation-student-name-en">${esc(studentName(student) || "-")}</div></div></div></td><td><div>${esc(item.academic_year?.academic_year || "-")}</div><div class="graduation-cell-meta">${esc(item.campus?.campus_name_en || "-")}</div></td><td>${gradeTrackCell(item)}</td><td>${esc(item.session?.session_short_name || "-")}</td><td><div>${esc(formatDate(item.graduation_date))}</div><div class="graduation-cell-meta">${esc(item.changed_by?.name || "System")}</div></td><td>${esc(item.certificate_number || "Not assigned")}</td><td>${graduationActionButtons(item)}<div class="graduation-cell-meta mt-2">Alumni: ${item.status === "completed" && item.is_alumni ? "Yes" : "No"}</div></td></tr>`;
        }).join('') : '<tr><td colspan="8" class="text-center">No graduated students found.</td></tr>';
        renderPagination(result, pageSize);
        updateSortIcons();
    };

    const refreshStudentEnrollmentOptions = async () => {
        enrollmentMulti?.menu.classList.add('d-none');
        const data = await loadOptions(false, { academic_year_id: field('student-year').value || '' });
        selectedGraduateEnrollmentIds.clear();
        field('graduation-enrollment').innerHTML = (data.enrollments || []).map(item => {
            const name = item.student?.full_name_en || '';
            const info = `${item.academic_year?.academic_year || '-'} | ${item.campus?.campus_name_en || '-'} | Class ${item.school_class?.class_name || '-'} | Group ${item.session?.session_short_name || '-'}`;
            return `<option value="${item.id}" data-info="${esc(info)}">${esc(`${item.student?.student_id || item.student?.student_no || ''} - ${name}`)}</option>`;
        }).join('');
        makeEnrollmentMultiselect();
        refreshEnrollmentMultiValue();
        renderEnrollmentMultiOptions();
    };
    const refreshEnrollmentOptions = async () => {
        const data = await loadOptions(false);
        fill('student-year', data.academicYears || [], 'academic_year');
        if (!field('student-year').value && (data.academicYears || []).length) {
            field('student-year').value = String(data.academicYears[0].id);
        }
        fill('batch-year', data.academicYears || [], 'academic_year');
        fill('batch-campus', data.campuses || [], 'campus_name_en');
        fill('batch-class', data.classes || [], classOptionLabel);
        setupSearchables();
        refreshSearchableValue('student-year');
        await refreshStudentEnrollmentOptions();
    };
    const applyScope = () => {
        const scope = field('graduation-scope').value;
        const batch = scope !== 'student';
        document.querySelectorAll('.graduation-student-year-field').forEach(element => element.classList.toggle('d-none', batch));
        document.querySelectorAll('.graduation-student-field').forEach(element => element.classList.toggle('d-none', batch));
        field('graduation-batch-block').classList.toggle('d-none', !batch);
        field('batch-campus-block').classList.toggle('d-none', scope === 'all_campuses');
        field('batch-class-block').classList.toggle('d-none', scope !== 'class');
        field('student-year').required = !batch;
        field('batch-year').required = batch;
        field('batch-campus').required = ['class', 'campus'].includes(scope);
        field('batch-class').required = scope === 'class';
    };

    field('graduation-year').onchange = async () => {
        await refreshCampuses(field('graduation-year').value, true, 'graduation-campus');
        await refreshClasses(field('graduation-campus').value, field('graduation-year').value, 'graduation-class', true);
        await render(1);
    };
    field('graduation-campus').onchange = async () => {
        await refreshClasses(field('graduation-campus').value, field('graduation-year').value, 'graduation-class', true);
        await render(1);
    };
    field('graduation-class').onchange = () => render(1);
    field('graduation-search').oninput = () => render(1);
    document.querySelectorAll('[data-graduation-sort]').forEach(button => button.addEventListener('click', () => {
        if (graduationSortBy === button.dataset.graduationSort) graduationSortDir = graduationSortDir === 'asc' ? 'desc' : 'asc';
        else {
            graduationSortBy = button.dataset.graduationSort;
            graduationSortDir = 'asc';
        }
        render(1);
    }));
    field('graduation-scope').onchange = applyScope;
    field('student-year').onchange = refreshStudentEnrollmentOptions;
    field('batch-campus').onchange = () => refreshClasses(field('batch-campus').value, field('batch-year').value, 'batch-class', false);
    field('batch-year').onchange = async () => {
        await refreshCampuses(field('batch-year').value, false, 'batch-campus');
        await refreshClasses(field('batch-campus').value, field('batch-year').value, 'batch-class', false);
    };
    document.addEventListener('click', event => {
        if (!event.target.closest('.location-combobox')) {
            Object.values(searchable).forEach(item => item.menu.classList.add('d-none'));
            enrollmentMulti?.menu.classList.add('d-none');
        }
    });

    field('newGraduation').onclick = async () => {
        field('graduationForm').reset();
        field('graduation-scope').value = 'student';
        selectedGraduateEnrollmentIds.clear();
        await refreshEnrollmentOptions();
        applyScope();
        field('graduation-date').value = new Date().toISOString().slice(0, 10);
        field('graduation-current').textContent = '';
        refreshEnrollmentMultiValue();
        field('graduationError').classList.add('d-none');
        modal.show();
        Object.keys(searchable).forEach(refreshSearchableValue);
    };
    field('graduationBulkPass').onclick = async () => {
        const button=field('graduationBulkPass');
        if(!field('graduation-year').value){
            if(window.Swal) await window.Swal.fire({icon:'info',title:'Select an academic year',text:'Choose the academic year before confirming a group of graduates.'});
            return;
        }
        button.disabled=true;
        let confirmed=0, skipped=0;
        try {
            const filters=new URLSearchParams({academic_year_id:field('graduation-year').value,campus_id:field('graduation-campus').value,class_id:field('graduation-class').value,search:field('graduation-search').value});
            const preview=await fetch(`/student-graduations/confirm-pass-preview?${filters}`,{headers:{Accept:'application/json'}});
            const plan=await preview.json();if(!preview.ok) throw new Error(plan.message||'Unable to preview graduates.');
            if(!plan.count){if(window.Swal) await window.Swal.fire({icon:'info',title:'No pending graduates',text:'No pending students match these filters and your permissions.'});return;}
            const scope=['graduation-year','graduation-campus','graduation-class'].map(id=>{const select=field(id);return select.value?select.options[select.selectedIndex].textContent:'';}).filter(Boolean).join(' / ');
            const decision=window.Swal?await window.Swal.fire({icon:'question',title:`Confirm ${plan.count} students passed?`,html:`<p>${esc(scope)}</p><p>This includes all matching pages. Only continue if every included student passed the WIS exam. Their graduations and enrollments will be completed, and linked WIS certificates will become valid.</p>${field('graduation-search').value?`<p>Search: <strong>${esc(field('graduation-search').value)}</strong></p>`:''}`,showCancelButton:true,confirmButtonText:`Confirm Pass for ${plan.count} Students`,cancelButtonText:'Go back'}):{isConfirmed:window.confirm(`Confirm all ${plan.count} matching students passed the WIS exam?`)};
            if(!decision.isConfirmed) return;
            if(window.Swal) window.Swal.fire({title:'Confirming graduates',html:'<div class="progress"><div id="graduationBulkProgress" class="progress-bar bg-success" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" style="width:0%"></div></div><div id="graduationBulkProgressText" class="mt-3" aria-live="polite">0 students processed</div>',allowOutsideClick:false,allowEscapeKey:false,showConfirmButton:false});
            for(let offset=0;offset<plan.ids.length;offset+=25){
                const response=await fetch('/student-graduations/confirm-pass-batch',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,Accept:'application/json'},body:JSON.stringify({ids:plan.ids.slice(offset,offset+25)})});
                const result=await response.json();if(!response.ok) throw new Error(result.message||'Unable to confirm this group.');
                confirmed+=result.confirmed;skipped+=result.skipped;
                const processed=Math.min(offset+25,plan.count),percentage=Math.round(processed/plan.count*100);
                const bar=field('graduationBulkProgress');if(bar){bar.style.width=`${percentage}%`;bar.setAttribute('aria-valuenow',String(percentage));field('graduationBulkProgressText').textContent=`${processed} of ${plan.count} processed (${percentage}%)`;}
            }
            if(window.Swal) await window.Swal.fire({icon:'success',title:'Pass Confirmed',text:`${confirmed} students confirmed.${skipped?` ${skipped} records already changed and were skipped.`:''}`});
        } catch(error){if(window.Swal) await window.Swal.fire({icon:'error',title:'Bulk confirmation stopped',text:`${confirmed} students confirmed so far. ${error.message} Refresh and review the remaining pending records before retrying.`});}
        finally {button.disabled=false;await loadListOptions();await render(currentPage);}
    };
    document.addEventListener('click', async event => {
        const pass = event.target.closest('[data-graduation-pass]');
        if (pass) {
            const decision = window.Swal ? await window.Swal.fire({icon:'question',title:'Confirm WIS Exam Pass?',text:'This completes graduation and makes the linked WIS certificate valid for release.',showCancelButton:true,confirmButtonText:'Confirm Pass'}) : {isConfirmed:window.confirm('Confirm this student passed the WIS exam?')};
            if (!decision.isConfirmed) return;
            pass.disabled = true;
            try {
                const response = await fetch(`/student-graduations/${pass.dataset.graduationPass}/confirm-pass`, {method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,Accept:'application/json'}});
                const result = await response.json();
                if (!response.ok) throw new Error(result.message || 'Unable to confirm result.');
                await loadListOptions(); await render(currentPage);
                if (window.Swal) await window.Swal.fire({icon:'success',title:'Pass Confirmed',text:result.message});
            } catch (error) { pass.disabled = false; if(window.Swal) await window.Swal.fire({icon:'error',title:'Unable to Confirm',text:error.message}); }
            return;
        }
        const button = event.target.closest('[data-graduation-cancel]');
        if (!button) return;
        const graduationId = button.dataset.graduationCancel;
        let cancellationType = 'failed';
        let cancellationReason = '';
        if (window.Swal) {
            const result = await window.Swal.fire({
                icon: 'warning',
                title: 'Cancel graduation and void certificate?',
                html: `
                    <div class="text-start">
                        <label class="form-label">Reason Type</label>
                        <select id="graduation-cancel-type" class="form-select mb-3">
                            <option value="failed">Failed</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="other">Other</option>
                        </select>
                        <label class="form-label">Remark</label>
                        <textarea id="graduation-cancel-reason" class="form-control" rows="3" placeholder="Enter remark"></textarea>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Cancel Graduation',
                confirmButtonColor: '#d63939',
                preConfirm: () => {
                    const type = document.getElementById('graduation-cancel-type')?.value || 'failed';
                    const reason = document.getElementById('graduation-cancel-reason')?.value.trim() || '';
                    if (!reason) {
                        window.Swal.showValidationMessage('Please enter a remark.');
                        return false;
                    }
                    return { type, reason };
                }
            });
            if (!result.isConfirmed) return;
            cancellationType = result.value.type;
            cancellationReason = result.value.reason;
        } else {
            cancellationType = window.prompt('Reason type: failed, cancelled, or other', 'failed') || 'failed';
            cancellationReason = window.prompt('Remark:') || '';
            if (!cancellationReason) return;
        }

        button.disabled = true;
        const response = await fetch(`/student-graduations/${graduationId}/cancel`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf
            },
            body: JSON.stringify({
                cancellation_type: cancellationType,
                cancellation_reason: cancellationReason
            })
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) {
            button.disabled = false;
            const message = result.message || Object.values(result.errors || {})[0]?.[0] || 'Unable to cancel graduation.';
            if (window.Swal) await window.Swal.fire({ icon: 'error', title: 'Action Failed', text: message });
            else window.alert(message);
            return;
        }
        if (window.Swal) {
            await window.Swal.fire({ icon: 'success', title: 'Graduation Cancelled', text: result.message || 'Graduation cancelled successfully.', timer: 1800, showConfirmButton: false });
        }
        await loadListOptions();
        await render(currentPage);
    });
    field('graduationForm').onsubmit = async event => {
        event.preventDefault();
        const scope = field('graduation-scope').value;
        const payload = {
            graduation_date: field('graduation-date').value,
            is_alumni: field('is-alumni').checked ? 1 : 0,
            notes: field('graduation-notes').value
        };
        const endpoint = scope === 'student' ? 'graduate' : 'graduate-batch';
        if (scope === 'student') {
            const enrollmentIds = Array.from(selectedGraduateEnrollmentIds);
            if (!field('student-year').value) {
                field('graduationError').textContent = 'Please select an Academic Year.';
                field('graduationError').classList.remove('d-none');
                return;
            }
            if (!enrollmentIds.length) {
                field('graduationError').textContent = 'Please select at least one Grade 12 student.';
                field('graduationError').classList.remove('d-none');
                return;
            }
            payload.enrollment_ids = enrollmentIds;
        } else {
            payload.scope = scope;
            payload.academic_year_id = field('batch-year').value;
            payload.campus_id = scope === 'all_campuses' ? '' : field('batch-campus').value;
            payload.class_id = scope === 'class' ? field('batch-class').value : '';
        }
        const response = await fetch(`/student-graduations/${endpoint}`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf
            },
            body: JSON.stringify(payload)
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) {
            const message = result.message || Object.values(result.errors || {})[0]?.[0] || 'Unable to graduate student.';
            field('graduationError').textContent = message;
            field('graduationError').classList.remove('d-none');
            if (window.Swal) await window.Swal.fire({ icon: 'error', title: 'Unable to Graduate', text: message });
            else window.alert(message);
            return;
        }
        modal.hide();
        await loadListOptions();
        await render(currentPage);
    };

    loadListOptions().then(() => render()).catch(() => {
        field('graduationTable').innerHTML = '<tr><td colspan="8" class="text-center text-danger">Unable to load graduation records.</td></tr>';
    });
});

