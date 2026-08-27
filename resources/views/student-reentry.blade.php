@extends('layouts.app')
@section('title', 'Student Re-entry')
@section('page-header')
    <div class="container-fluid">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">Students</div>
                <h2 class="page-title">Student Re-entry</h2>
            </div>
            <div class="col-auto"><button type="button" class="btn btn-primary" id="newReentry"><i
                        class="ti ti-user-check me-1"></i> New Re-entry</button></div>
        </div>
    </div>
@endsection
@section('content')

    <div class="card mb-3">
        <div class="card-body">
            <div class="reentry-hero">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="reentry-hero-icon"><i class="ti ti-user-check"></i></div>
                    <div class="flex-fill">
                        <div class="text-secondary text-uppercase small fw-bold">Return to school workflow</div>
                        <h3 class="mb-1">Students eligible for re-entry</h3>
                        <div class="text-secondary">Only officially withdrawn students are shown. Pending, rejected, and
                            cancelled requests remain active.</div>
                    </div>
                    <div>
                        <div class="text-secondary small">Eligible students</div>
                        <div class="h2 mb-0" id="reentry-count">—</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title mb-1">Re-entry History</h3>
                <div class="text-secondary small">Review the previous withdrawal before creating a new enrollment.</div>
            </div>
            <div class="card-actions">
                <div class="input-icon" style="width:360px;max-width:100%"><span class="input-icon-addon"><i
                            class="ti ti-search"></i></span><input id="reentry-search" class="form-control"
                        placeholder="Search Student ID or Full Name"></div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Withdrawn Academic Year</th>
                        <th>Campus</th>
                        <th>Grade</th>
                        <th>Group</th>
                        <th>Withdrawal Date</th>
                        <th>Status</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody id="reentry-table">
                    <tr>
                        <td colspan="8">
                            <div class="reentry-empty">Loading eligible students...</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="modal modal-blur fade reentry-modal" id="reentryModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <form id="reentryForm">
                    <div class="modal-header">
                        <div>
                            <h3 class="modal-title">Create Student Re-entry</h3>
                            <div class="text-secondary small">Create a new active enrollment while preserving the previous
                                withdrawal history.</div>
                        </div><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger d-none" id="reentry-error"></div>
                        <div class="card bg-transparent mb-3">
                            <div class="card-header">
                                <h3 class="card-title"><i class="ti ti-user me-2"></i>Withdrawn Student</h3>
                            </div>
                            <div class="card-body"><label class="form-label">Select Withdrawn Student <span
                                        class="text-danger">*</span></label><select id="reentry-source" class="form-select"
                                    required></select>
                                <div class="reentry-source-card mt-3 d-none" id="reentry-source-card">
                                    <div class="d-flex gap-3 align-items-center"><img id="reentry-source-photo"
                                            class="reentry-table-photo" alt="Student photo">
                                        <div>
                                            <div class="text-secondary small" id="reentry-source-id"></div>
                                            <div class="fw-bold fs-3" id="reentry-source-name"></div>
                                            <div class="text-secondary" id="reentry-source-info"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card bg-transparent">
                            <div class="card-header">
                                <h3 class="card-title"><i class="ti ti-school me-2"></i>New Enrollment Information</h3>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-4"><label class="form-label">Academic Year <span
                                                class="text-danger">*</span></label><select id="reentry-year"
                                            class="form-select" required></select></div>
                                    <div class="col-md-4"><label class="form-label">Campus <span
                                                class="text-danger">*</span></label><select id="reentry-campus"
                                            class="form-select" required></select></div>
                                    <div class="col-md-4"><label class="form-label">Grade <span
                                                class="text-danger">*</span></label><select id="reentry-grade"
                                            class="form-select" required></select></div>
                                    <div class="col-md-4"><label class="form-label">Class <span
                                                class="text-danger">*</span></label><select id="reentry-class"
                                            class="form-select" required></select></div>
                                    <div class="col-md-4"><label class="form-label">Group <span
                                                class="text-danger">*</span></label><select id="reentry-group"
                                            class="form-select" required></select></div>
                                    <div class="col-md-4"><label class="form-label">Re-entry Date <span
                                                class="text-danger">*</span></label><input type="date"
                                            id="reentry-date" class="form-control" required></div>
                                    <div class="col-12"><label class="form-label">Notes</label>
                                        <textarea id="reentry-notes" class="form-control" rows="3"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn me-auto"
                            data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"
                            id="saveReentry"><i class="ti ti-check me-1"></i> Confirm Re-entry</button></div>
                </form>
            </div>
        </div>
    </div>
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const field = (id) => document.getElementById(id);
                const modal = bootstrap.Modal.getOrCreateInstance(field('reentryModal'));
                const state = {
                    withdrawals: [],
                    academicYears: [],
                    campuses: [],
                    grades: [],
                    classes: [],
                    groups: []
                };
                const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                } [char]));
                const formatDate = (value) => {
                    if (!value) return '-';
                    const raw = String(value);
                    const date = new Date(raw.includes('T') ? raw : `${raw}T00:00:00`);
                    if (Number.isNaN(date.getTime())) return value;
                    const day = String(date.getDate()).padStart(2, '0');
                    const month = date.toLocaleString('en-US', {
                        month: 'short'
                    });
                    return `${day}-${month}-${date.getFullYear()}`;
                };
                const name = (item) => item.student?.full_name_en || item.student?.full_name_kh || '-';
                const label = (item) => [item.student?.student_id || item.student?.student_no, name(item)].filter(
                    Boolean).join(' - ');
                const grade = (item) => `${item.grade?.grade || '-'}${item.school_class?.class_name || ''}`;
                const fill = (id, items, key, empty) => {
                    field(id).innerHTML = `<option value="">${empty}</option>` + (items || []).map(item =>
                        `<option value="${item.id}" data-grade-id="${item.grade_id || ''}">${escapeHtml(item[key])}</option>`
                        ).join('');
                };
                const searchable = {};
                const setupSearchable = (id, label) => {
                    const select = field(id);
                    if (!select || searchable[id]) return;
                    select.classList.add('d-none');
                    const wrapper = document.createElement('div');
                    wrapper.className = 'location-combobox reentry-search-combobox';
                    wrapper.innerHTML =
                        `<button type="button" class="location-combobox-toggle"><span class="location-combobox-selected">${label}</span><i class="ti ti-chevron-down"></i></button><div class="location-combobox-menu d-none"><input type="search" class="form-control location-combobox-search" placeholder="Search ${label}"><div class="location-combobox-results"></div></div>`;
                    select.after(wrapper);
                    const menu = wrapper.querySelector('.location-combobox-menu');
                    const selected = wrapper.querySelector('.location-combobox-selected');
                    const search = wrapper.querySelector('.location-combobox-search');
                    const results = wrapper.querySelector('.location-combobox-results');
                    const sync = () => {
                        selected.textContent = select.value ? (select.selectedOptions[0]?.textContent ||
                            label) : label;
                    };
                    const render = () => {
                        const term = search.value.toLowerCase().trim();
                        const options = Array.from(select.options).slice(1).filter(option => !option.hidden && (
                            !term || option.textContent.toLowerCase().includes(term)));
                        results.innerHTML = options.length ? options.map(option =>
                            `<button type="button" class="location-combobox-option" data-searchable-value="${escapeHtml(option.value)}">${escapeHtml(option.textContent)}</button>`
                            ).join('') : '<div class="text-secondary px-2 py-3">No options found</div>';
                    };
                    wrapper.querySelector('.location-combobox-toggle').addEventListener('click', () => {
                        document.querySelectorAll('.reentry-search-combobox .location-combobox-menu')
                            .forEach(other => {
                                if (other !== menu) other.classList.add('d-none');
                            });
                        menu.classList.toggle('d-none');
                        if (!menu.classList.contains('d-none')) {
                            search.value = '';
                            render();
                            search.focus();
                        }
                    });
                    search.addEventListener('input', render);
                    results.addEventListener('click', event => {
                        const option = event.target.closest('[data-searchable-value]');
                        if (!option) return;
                        select.value = option.dataset.searchableValue;
                        select.dispatchEvent(new Event('change', {
                            bubbles: true
                        }));
                        menu.classList.add('d-none');
                    });
                    select.addEventListener('change', () => {
                        sync();
                        render();
                    });
                    searchable[id] = {
                        sync,
                        render
                    };
                };
                setupSearchable('reentry-source', 'Withdrawn Student');
                setupSearchable('reentry-year', 'Academic Year');
                setupSearchable('reentry-campus', 'Campus');
                setupSearchable('reentry-grade', 'Grade');
                setupSearchable('reentry-class', 'Class');
                setupSearchable('reentry-group', 'Group');
                const renderSource = () => {
                    const select = field('reentry-source');
                    select.innerHTML = '<option value=""></option>' + state.withdrawals.map(item =>
                        `<option value="${item.id}">${escapeHtml(label(item))}</option>`).join('');
                    searchable['reentry-source']?.sync();
                    searchable['reentry-source']?.render();
                };
                const renderTable = () => {
                    const term = field('reentry-search').value.toLowerCase().trim();
                    const rows = state.withdrawals.filter(item => !term ||
                        `${label(item)} ${item.student?.full_name_kh || ''}`.toLowerCase().includes(term));
                    field('reentry-count').textContent = rows.length;
                    field('reentry-table').innerHTML = rows.length ? rows.map(item =>
                            `<tr><td><div class="d-flex align-items-center gap-3">${item.student?.photo_path ? `<img class="reentry-table-photo" src="/storage/${escapeHtml(item.student.photo_path)}" alt="Student photo">` : '<span class="reentry-table-photo d-grid place-items-center"><i class="ti ti-user"></i></span>'}<div><div class="school-profile-khmer text-secondary small">${escapeHtml(item.student?.full_name_kh || '-')}</div><div class="fw-semibold">${escapeHtml(item.student?.full_name_en || '-')}</div><div class="text-secondary small">${escapeHtml(item.student?.student_id || item.student?.student_no || '-')}</div></div></div></td><td>${escapeHtml(item.academic_year?.academic_year || '-')}</td><td>${escapeHtml(item.campus?.campus_name_en || '-')}</td><td>${escapeHtml(grade(item))}</td><td>${escapeHtml(item.session?.session_short_name || '-')}</td><td>${escapeHtml(formatDate(item.effective_on))}</td><td><span class="badge bg-success-lt text-success">Eligible</span></td><td class="text-center"><button type="button" class="btn btn-sm btn-primary" data-reentry-student="${item.id}"><i class="ti ti-user-check me-1"></i>Re-entry</button></td></tr>`
                            ).join('') :
                        '<tr><td colspan="8"><div class="reentry-empty">No eligible withdrawn students found.</div></td></tr>';
                };
                const showSource = () => {
                    const item = state.withdrawals.find(row => String(row.id) === field('reentry-source').value);
                    const card = field('reentry-source-card');
                    if (!item) {
                        card.classList.add('d-none');
                        return;
                    }
                    card.classList.remove('d-none');
                    field('reentry-source-photo').src = item.student?.photo_path ?
                        `/storage/${item.student.photo_path}` : '';
                    field('reentry-source-id').textContent = item.student?.student_id || item.student?.student_no ||
                        '';
                    field('reentry-source-name').textContent = name(item);
                    field('reentry-source-info').textContent =
                        `Withdrawn ${formatDate(item.effective_on)} · ${item.academic_year?.academic_year || '-'} · ${item.campus?.campus_name_en || '-'} · ${grade(item)} · ${item.session?.session_short_name || '-'}`;
                };
                const filterClasses = () => {
                    const gradeId = field('reentry-grade').value;
                    Array.from(field('reentry-class').options).forEach(option => {
                        option.hidden = Boolean(option.value && gradeId && option.dataset.gradeId !==
                            gradeId);
                    });
                    if (field('reentry-class').selectedOptions[0]?.hidden) field('reentry-class').value = '';
                    searchable['reentry-class']?.sync();
                    searchable['reentry-class']?.render();
                };
                const load = async () => {
                    const response = await fetch('{{ route('student-reentry.options') }}', {
                        headers: {
                            Accept: 'application/json'
                        }
                    });
                    if (!response.ok) throw new Error('Unable to load re-entry data.');
                    Object.assign(state, await response.json());
                    renderSource();
                    fill('reentry-year', state.academicYears, 'academic_year', '');
                    fill('reentry-campus', state.campuses, 'campus_name_en', '');
                    fill('reentry-grade', state.grades, 'grade', '');
                    fill('reentry-class', state.classes, 'class_name', '');
                    fill('reentry-group', state.groups, 'session_short_name', '');
                    Object.values(searchable).forEach(combo => {
                        combo.sync();
                        combo.render();
                    });
                    renderTable();
                };
                field('reentry-search').addEventListener('input', renderTable);
                field('reentry-source').addEventListener('change', showSource);
                field('reentry-grade').addEventListener('change', filterClasses);
                const openReentry = async (sourceId = '') => {
                    field('reentryForm').reset();
                    Object.values(searchable).forEach(combo => combo.sync());
                    field('reentry-error').classList.add('d-none');
                    field('reentry-source-card').classList.add('d-none');
                    field('reentry-date').value = new Date().toISOString().slice(0, 10);
                    try {
                        if (!state.withdrawals.length) await load();
                        if (sourceId) {
                            field('reentry-source').value = String(sourceId);
                            searchable['reentry-source']?.sync();
                            showSource();
                        }
                        modal.show();
                    } catch (error) {
                        field('reentry-error').textContent = error.message;
                        field('reentry-error').classList.remove('d-none');
                        modal.show();
                    }
                };
                field('newReentry').addEventListener('click', () => openReentry());
                field('reentry-table').addEventListener('click', (event) => {
                    const button = event.target.closest('[data-reentry-student]');
                    if (button) openReentry(button.dataset.reentryStudent);
                });
                field('reentryForm').addEventListener('submit', async (event) => {
                    event.preventDefault();
                    const button = field('saveReentry');
                    button.disabled = true;
                    field('reentry-error').classList.add('d-none');
                    const payload = {
                        source_history_id: field('reentry-source').value,
                        academic_year_id: field('reentry-year').value,
                        campus_id: field('reentry-campus').value,
                        grade_id: field('reentry-grade').value,
                        class_id: field('reentry-class').value,
                        session_id: field('reentry-group').value,
                        effective_on: field('reentry-date').value,
                        notes: field('reentry-notes').value
                    };
                    try {
                        const response = await fetch('{{ route('student-reentry.save') }}', {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify(payload)
                        });
                        const result = await response.json();
                        if (!response.ok) throw new Error(result.message || Object.values(result.errors ||
                            {})[0]?.[0] || 'Unable to save re-entry.');
                        modal.hide();
                        state.withdrawals = [];
                        await load();
                    } catch (error) {
                        field('reentry-error').textContent = error.message;
                        field('reentry-error').classList.remove('d-none');
                    } finally {
                        button.disabled = false;
                    }
                });
                document.addEventListener('click', event => {
                    if (!event.target.closest('.reentry-search-combobox')) document.querySelectorAll(
                        '.reentry-search-combobox .location-combobox-menu').forEach(menu => menu.classList
                        .add('d-none'));
                });
                load().catch(error => {
                    field('reentry-table').innerHTML =
                        `<tr><td colspan="8"><div class="reentry-empty text-danger">${escapeHtml(error.message)}</div></td></tr>`;
                });
            });
        </script>
    @endpush
    @vite('resources/css/pages/student-reentry.css')
@endsection
