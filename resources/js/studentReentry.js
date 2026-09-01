document.addEventListener('DOMContentLoaded', () => {
    const modalElement = document.getElementById('reentryModal');
    if (!modalElement || modalElement.dataset.enhanced === '1') return;
    modalElement.dataset.enhanced = '1';

    const field = id => document.getElementById(id);
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const state = {
        withdrawals: [],
        academicYears: [],
        campuses: [],
        grades: [],
        classes: [],
        groups: [],
    };
    const optionsUrl = modalElement.dataset.optionsUrl;
    const saveUrl = modalElement.dataset.saveUrl;
    const csrf = modalElement.dataset.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '';
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[char]));

    const formatDate = value => {
        if (!value) return '-';
        const raw = String(value);
        const date = new Date(raw.includes('T') ? raw : `${raw}T00:00:00`);
        if (Number.isNaN(date.getTime())) return value;
        const day = String(date.getDate()).padStart(2, '0');
        const month = date.toLocaleString('en-US', { month: 'short' });
        return `${day}-${month}-${date.getFullYear()}`;
    };
    const name = item => item.student?.full_name_en || item.student?.full_name_kh || '-';
    const label = item => [item.student?.student_id || item.student?.student_no, name(item)].filter(Boolean).join(' - ');
    const grade = item => `${item.grade?.grade || '-'}${item.school_class?.class_name || ''}`;
    const fill = (id, items, key, empty) => {
        field(id).innerHTML = `<option value="">${empty}</option>` + (items || []).map(item =>
            `<option value="${item.id}" data-grade-id="${item.grade_id || ''}">${escapeHtml(item[key])}</option>`).join('');
    };

    const searchable = {};
    const setupSearchable = (id, labelText) => {
        const select = field(id);
        if (!select || searchable[id]) return;
        select.classList.add('d-none');
        const wrapper = document.createElement('div');
        wrapper.className = 'location-combobox reentry-search-combobox';
        wrapper.innerHTML = `<button type="button" class="location-combobox-toggle"><span class="location-combobox-selected">${labelText}</span><i class="ti ti-chevron-down"></i></button><div class="location-combobox-menu d-none"><input type="search" class="form-control location-combobox-search" placeholder="Search ${labelText}"><div class="location-combobox-results"></div></div>`;
        select.after(wrapper);
        const menu = wrapper.querySelector('.location-combobox-menu');
        const selected = wrapper.querySelector('.location-combobox-selected');
        const search = wrapper.querySelector('.location-combobox-search');
        const results = wrapper.querySelector('.location-combobox-results');
        const sync = () => {
            selected.textContent = select.value ? (select.selectedOptions[0]?.textContent || labelText) : labelText;
        };
        const render = () => {
            const term = search.value.toLowerCase().trim();
            const options = Array.from(select.options).slice(1).filter(option => !option.hidden && (!term || option.textContent.toLowerCase().includes(term)));
            results.innerHTML = options.length ? options.map(option =>
                `<button type="button" class="location-combobox-option" data-searchable-value="${escapeHtml(option.value)}">${escapeHtml(option.textContent)}</button>`).join('') : '<div class="text-secondary px-2 py-3">No options found</div>';
        };
        wrapper.querySelector('.location-combobox-toggle').addEventListener('click', () => {
            document.querySelectorAll('.reentry-search-combobox .location-combobox-menu').forEach(other => {
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
            select.dispatchEvent(new Event('change', { bubbles: true }));
            menu.classList.add('d-none');
        });
        select.addEventListener('change', () => {
            sync();
            render();
        });
        searchable[id] = { sync, render };
    };

    setupSearchable('reentry-source', 'Withdrawn Student');
    setupSearchable('reentry-year', 'Academic Year');
    setupSearchable('reentry-campus', 'Campus');
    setupSearchable('reentry-grade', 'Grade');
    setupSearchable('reentry-class', 'Class');
    setupSearchable('reentry-group', 'Group');

    const renderSource = () => {
        const select = field('reentry-source');
        select.innerHTML = '<option value=""></option>' + state.withdrawals.map(item => `<option value="${item.id}">${escapeHtml(label(item))}</option>`).join('');
        searchable['reentry-source']?.sync();
        searchable['reentry-source']?.render();
    };

    const renderTable = () => {
        const term = field('reentry-search').value.toLowerCase().trim();
        const rows = state.withdrawals.filter(item => !term || `${label(item)} ${item.student?.full_name_kh || ''}`.toLowerCase().includes(term));
        field('reentry-count').textContent = rows.length;
        field('reentry-table').innerHTML = rows.length ? rows.map(item =>
            `<tr><td><div class="d-flex align-items-center gap-3">${item.student?.photo_path ? `<img class="reentry-table-photo" src="/storage/${escapeHtml(item.student.photo_path)}" alt="Student photo">` : '<span class="reentry-table-photo d-grid place-items-center"><i class="ti ti-user"></i></span>'}<div><div class="school-profile-khmer text-secondary small">${escapeHtml(item.student?.full_name_kh || '-')}</div><div class="fw-semibold">${escapeHtml(item.student?.full_name_en || '-')}</div><div class="text-secondary small">${escapeHtml(item.student?.student_id || item.student?.student_no || '-')}</div></div></div></td><td>${escapeHtml(item.academic_year?.academic_year || '-')}</td><td>${escapeHtml(item.campus?.campus_name_en || '-')}</td><td>${escapeHtml(grade(item))}</td><td>${escapeHtml(item.session?.session_short_name || '-')}</td><td>${escapeHtml(formatDate(item.effective_on))}</td><td><span class="badge bg-success-lt text-success">Eligible</span></td><td class="text-center"><button type="button" class="btn btn-sm btn-primary" data-reentry-student="${item.id}"><i class="ti ti-user-check me-1"></i>Re-entry</button></td></tr>`
        ).join('') : '<tr><td colspan="8"><div class="reentry-empty">No eligible withdrawn students found.</div></td></tr>';
    };

    const showSource = () => {
        const item = state.withdrawals.find(row => String(row.id) === field('reentry-source').value);
        const card = field('reentry-source-card');
        if (!item) {
            card.classList.add('d-none');
            return;
        }
        card.classList.remove('d-none');
        field('reentry-source-photo').src = item.student?.photo_path ? `/storage/${item.student.photo_path}` : '';
        field('reentry-source-id').textContent = item.student?.student_id || item.student?.student_no || '';
        field('reentry-source-name').textContent = name(item);
        field('reentry-source-info').textContent = `Withdrawn ${formatDate(item.effective_on)} · ${item.academic_year?.academic_year || '-'} · ${item.campus?.campus_name_en || '-'} · ${grade(item)} · ${item.session?.session_short_name || '-'}`;
    };

    const filterClasses = () => {
        const gradeId = field('reentry-grade').value;
        Array.from(field('reentry-class').options).forEach(option => {
            option.hidden = Boolean(option.value && gradeId && option.dataset.gradeId !== gradeId);
        });
        if (field('reentry-class').selectedOptions[0]?.hidden) field('reentry-class').value = '';
        searchable['reentry-class']?.sync();
        searchable['reentry-class']?.render();
    };

    const load = async () => {
        const response = await fetch(optionsUrl, { headers: { Accept: 'application/json' } });
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
    field('reentry-table').addEventListener('click', event => {
        const button = event.target.closest('[data-reentry-student]');
        if (button) openReentry(button.dataset.reentryStudent);
    });
    field('reentryForm').addEventListener('submit', async event => {
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
            notes: field('reentry-notes').value,
        };
        try {
            const response = await fetch(saveUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify(payload),
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || Object.values(result.errors || {})[0]?.[0] || 'Unable to save re-entry.');
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
        if (!event.target.closest('.reentry-search-combobox')) document.querySelectorAll('.reentry-search-combobox .location-combobox-menu').forEach(menu => menu.classList.add('d-none'));
    });
    load().catch(error => {
        field('reentry-table').innerHTML = `<tr><td colspan="8"><div class="reentry-empty text-danger">${escapeHtml(error.message)}</div></td></tr>`;
    });
});
