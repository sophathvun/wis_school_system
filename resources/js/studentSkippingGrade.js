import { existingPromotedPlacement, minimumRequestedGradeOrder } from './helpers/existingPromotion';
import Swal from 'sweetalert2';
import { Tooltip } from 'bootstrap';
import { initPremiumDatePicker } from './premiumDatePicker';
import { initSkippingImageUpload } from './helpers/skippingImageUpload';
import { initSkippingApprovalShare } from './helpers/skippingApprovalShare';

document.querySelectorAll('[data-skipping-workspace]').forEach((workspace) => {
    const toggle = workspace.querySelector('[data-skipping-tabs-toggle]');
    const desktop = window.matchMedia('(min-width: 992px)');
    const tooltips = [...workspace.querySelectorAll('[data-skipping-tab-tooltip]')].map((element) => {
        const tooltip = Tooltip.getOrCreateInstance(element, {
            container: 'body',
            placement: 'right',
            customClass: 'skipping-tabs-tooltip',
            delay: { show: 200, hide: 0 },
            title: () => element === toggle ? element.getAttribute('aria-label') : element.dataset.bsTitle,
        });
        element.addEventListener('click', () => tooltip.hide());
        return tooltip;
    });
    const syncTabs = () => {
        const collapsed = workspace.classList.contains('skipping-tabs-collapsed');
        toggle?.setAttribute('aria-expanded', String(!collapsed));
        toggle?.setAttribute('aria-label', `${collapsed ? 'Expand' : 'Collapse'} skipping grade tabs`);
        const icon = toggle?.querySelector('i');
        if (icon) icon.className = `ti ti-layout-sidebar-left-${collapsed ? 'expand' : 'collapse'}`;
        tooltips.forEach((tooltip) => {
            tooltip.hide();
            if (collapsed && desktop.matches) tooltip.enable();
            else tooltip.disable();
        });
    };
    toggle?.addEventListener('click', () => {
        workspace.classList.toggle('skipping-tabs-collapsed');
        syncTabs();
    });
    desktop.addEventListener('change', syncTabs);
    syncTabs();
});

const root = document.querySelector('[data-skipping-page]');
if (root) {
    initSkippingApprovalShare(root);
    root.querySelectorAll('[data-premium-date-picker]').forEach(initPremiumDatePicker);
    const parentNameInput = root.querySelector('[name="parent_name"]');
    const uppercaseParentName = (event) => {
        if (!parentNameInput || event?.isComposing) return;
        const original = parentNameInput.value;
        const uppercase = original.toUpperCase();
        if (original === uppercase) return;
        const start = parentNameInput.selectionStart;
        const end = parentNameInput.selectionEnd;
        parentNameInput.value = uppercase;
        if (start !== null && end !== null) parentNameInput.setSelectionRange(original.slice(0, start).toUpperCase().length, original.slice(0, end).toUpperCase().length);
    };
    parentNameInput?.addEventListener('input', uppercaseParentName);
    parentNameInput?.addEventListener('compositionend', uppercaseParentName);
    uppercaseParentName();
    const notice = (icon, title, text) => Swal.fire({ icon, title, text });
    root.querySelectorAll('[data-skipping-image-upload]').forEach((wrapper) => initSkippingImageUpload(wrapper, notice));
    if (root.dataset.success) notice('success', 'Saved', root.dataset.success);
    const request = async (url, options = {}) => {
        const response = await fetch(url, { ...options, headers: { Accept: 'application/json', ...options.headers } });
        const json = response.headers.get('content-type')?.includes('application/json') ? await response.json() : null;
        if (!response.ok || !json || response.redirected) {
            const message = response.status === 413
                ? 'The server rejected this upload because its upload limit was exceeded. Please ask your administrator to check the server upload limits, then try again.'
                : json?.message || ({ 419: 'Your session expired. Refresh this page and try again.', 403: 'You do not have permission for this action.' })[response.status] || 'Unable to complete this action. Refresh the page and try again.';
            throw Object.assign(new Error(message), { errors: json?.errors });
        }
        return json;
    };
    root.querySelectorAll('[data-skipping-form]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            if (event.defaultPrevented) return;
            event.preventDefault();
            if (form.dataset.busy) return;
            if (form.querySelector('[data-upload-pending="true"]')) {
                notice('info', 'Preparing image', 'Please wait for the image preview before saving settings.');
                return;
            }
            if (form.dataset.campusReadOnly === 'true') return;
            if (form.dataset.confirm && !(await Swal.fire({ icon: 'question', title: 'Confirm action', text: form.dataset.confirm, showCancelButton: true, confirmButtonText: 'Confirm' })).isConfirmed) return;
            form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
            form.querySelectorAll('[data-skipping-error]').forEach((el) => el.remove());
            const buttons = [...form.querySelectorAll('button[type="submit"]')];
            form.dataset.busy = '1';
            buttons.forEach((button) => button.disabled = true);
            try {
                const json = await request(form.action, { method: 'POST', body: new FormData(form) });
                await notice('success', 'Saved', json.message);
                if (json.redirect) location.assign(json.redirect);
            } catch (error) {
                const messages = [];
                Object.entries(error.errors || {}).forEach(([key, values]) => {
                    const name = key.replace(/\.([^.]*)/g, '[$1]');
                    let input = [...form.elements].find((el) => el.name === name);
                    if (input?.matches('[data-premium-date-value]')) input = input.closest('[data-premium-date-picker]').querySelector('[data-premium-date-input]');
                    if (input?.matches('[data-upload-input]')) input = input.closest('[data-skipping-image-upload]').querySelector('[data-upload-dropzone]');
                    if (key === 'enrollment_id') input = form.querySelector('[data-student-search]') || form.querySelector('[data-student-summary]');
                    if (input) {
                        input.classList.add('is-invalid');
                        const feedback = document.createElement('div');
                        feedback.className = 'invalid-feedback d-block';
                        feedback.dataset.skippingError = '';
                        feedback.textContent = values.join(' ');
                        input.insertAdjacentElement('afterend', feedback);
                    }
                    messages.push(...values);
                });
                await notice('error', messages.length ? 'Please check the information' : 'Unable to save', messages.length ? [...new Set(messages)].join('\n') : error.message);
                form.querySelector('.is-invalid')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } finally {
                delete form.dataset.busy;
                buttons.forEach((button) => button.disabled = form.dataset.campusReadOnly === 'true');
            }
        });
    });
    const search = root.querySelector('[data-student-search]');
    root.querySelector('[data-campus-settings-campus]')?.addEventListener('change', (event) => event.target.form.requestSubmit());
    const enrollment = root.querySelector('[name="enrollment_id"]');
    const grade = root.querySelector('[data-target-grade]');
    const targetYear = root.querySelector('[data-target-year]');
    const classes = root.querySelector('[data-target-class]');
    const group = root.querySelector('[name="target_session_id"]');
    const studentFilter = root.querySelector('[data-student-name]');
    const photo = root.querySelector('[data-student-photo]');
    const noPhoto = root.querySelector('[data-student-no-photo]');
    photo?.addEventListener('error', () => { photo.hidden = true; noPhoto.hidden = false; });
    let selectedStudent = null;
    const syncExistingPromotion = () => {
        const placement = existingPromotedPlacement(selectedStudent, targetYear?.value);
        const note = root.querySelector('[data-existing-promotion]');
        if (note) {
            note.hidden = !placement;
            note.textContent = placement ? `Already promoted to ${placement.grade} / ${placement.academic_year}. Approval will update this existing enrollment. It stays unchanged while the request is pending or rejected.` : '';
        }
        if (!selectedStudent || !grade) return;
        const minimum = minimumRequestedGradeOrder(selectedStudent, targetYear?.value);
        [...grade.options].forEach((item) => {
            if (!item.value) return;
            item.hidden = Number(item.dataset.order) <= minimum;
            item.disabled = item.hidden;
        });
        if (grade.selectedOptions[0]?.disabled) grade.value = '';
    };
    const renderStudentSummary = (row, message = 'No student selected.') => {
        const empty = root.querySelector('[data-student-empty]');
        const selected = root.querySelector('[data-selected-student]');
        if (!empty || !selected) return;
        selectedStudent = row;
        if (!row) syncExistingPromotion();
        empty.hidden = Boolean(row); empty.textContent = message;
        selected.hidden = !row;
        if (!row) { photo.removeAttribute('src'); photo.hidden = true; noPhoto.hidden = false; return; }
        const values = {
            '[data-student-name-kh]': row.name_kh, '[data-student-name-en]': row.name_en,
            '[data-student-id]': row.student_id, '[data-student-campus-label]': row.campus,
            '[data-student-grade-label]': row.grade, '[data-student-year-label]': row.year,
            '[data-student-dob]': row.dob,
        };
        Object.entries(values).forEach(([selector, value]) => root.querySelector(selector).textContent = value || '—');
        photo.hidden = !row.photo_url; noPhoto.hidden = Boolean(row.photo_url);
        if (row.photo_url) { photo.src = row.photo_url; photo.alt = `Student photo: ${row.name_en || row.name_kh || row.student_id}`; }
        else photo.removeAttribute('src');
    };
    const searchableFilters = new Map();
    root.querySelectorAll('[data-skipping-searchable]').forEach((select, index) => {
        const label = select.dataset.skippingSearchable;
        const wrapper = document.createElement('div');
        wrapper.className = 'skipping-searchable';
        const toggle = document.createElement('button');
        toggle.type = 'button'; toggle.className = 'form-select skipping-searchable-toggle';
        const floatingLabel = select.parentElement.querySelector(':scope > .form-label');
        if (floatingLabel) { toggle.id = `${select.id}Toggle`; floatingLabel.htmlFor = toggle.id; }
        toggle.setAttribute('aria-haspopup', 'listbox'); toggle.setAttribute('aria-expanded', 'false');
        const menu = document.createElement('div');
        menu.className = 'skipping-searchable-menu'; menu.hidden = true;
        const input = document.createElement('input');
        input.type = 'search'; input.className = 'form-control'; input.placeholder = select === studentFilter ? 'Search by student ID or name' : `Search ${label}`;
        input.setAttribute('aria-label', select === studentFilter ? 'Search by Student ID, English name or Khmer name' : `Search ${label}`); input.autocomplete = 'off';
        const results = document.createElement('div');
        results.className = 'skipping-searchable-options'; results.id = `skippingFilterOptions${index}`;
        results.setAttribute('role', 'listbox'); results.setAttribute('aria-label', label);
        toggle.setAttribute('aria-controls', results.id);
        menu.append(input, results); wrapper.append(toggle, menu); select.after(wrapper); select.hidden = true;
        const close = () => { menu.hidden = true; toggle.setAttribute('aria-expanded', 'false'); };
        const refresh = () => {
            toggle.textContent = select.selectedOptions[0]?.textContent || label;
            select.parentElement.classList.toggle('has-value', Boolean(select.value));
            if (floatingLabel) floatingLabel.textContent = select.value ? label : select.options[0]?.textContent || label;
            toggle.setAttribute('aria-label', `${label}: ${toggle.textContent}`);
            const term = input.value.trim().toLocaleLowerCase();
            results.replaceChildren();
            [...select.options].filter((item) => !item.hidden && !item.disabled && (!item.value || (item.dataset.searchText || item.textContent).toLocaleLowerCase().includes(term))).forEach((item) => {
                const button = document.createElement('button');
                button.type = 'button'; button.textContent = item.textContent;
                button.setAttribute('role', 'option'); button.setAttribute('aria-selected', String(item.selected));
                button.addEventListener('click', () => {
                    select.value = item.value; close(); toggle.focus();
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                });
                results.append(button);
            });
            if (results.childElementCount <= 1 && term) {
                const empty = document.createElement('div'); empty.className = 'text-secondary p-2';
                empty.textContent = select === studentFilter ? 'No matching students. Type a name or ID to search.' : 'No matching options.';
                results.append(empty);
            }
        };
        const open = () => {
            searchableFilters.forEach((item) => item.close());
            input.value = ''; menu.hidden = false; toggle.setAttribute('aria-expanded', 'true');
            refresh(); input.focus();
            if (select === studentFilter) input.dispatchEvent(new Event('input'));
        };
        toggle.addEventListener('click', () => menu.hidden ? open() : close());
        toggle.addEventListener('keydown', (event) => { if (event.key === 'ArrowDown') { event.preventDefault(); open(); } });
        input.addEventListener('input', refresh);
        select.addEventListener('change', refresh);
        menu.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') { event.preventDefault(); close(); toggle.focus(); }
            if (['ArrowDown', 'ArrowUp'].includes(event.key)) {
                event.preventDefault();
                const buttons = [...results.querySelectorAll('button')];
                const current = buttons.indexOf(document.activeElement);
                const next = current + (event.key === 'ArrowDown' ? 1 : -1);
                buttons[Math.max(0, Math.min(buttons.length - 1, next))]?.focus();
            }
        });
        wrapper.addEventListener('focusout', (event) => { if (!wrapper.contains(event.relatedTarget)) close(); });
        searchableFilters.set(select, { refresh, close, input, toggle });
        refresh();
    });
    document.addEventListener('click', (event) => { if (!event.target.closest('.skipping-searchable')) searchableFilters.forEach((item) => item.close()); });
    const listFilters = root.querySelector('[data-skipping-list-filters]');
    if (listFilters) {
        const filterCard = listFilters.closest('[data-skipping-filter-card]');
        const filterToggle = filterCard?.querySelector('[data-skipping-filters-toggle]');
        const filterBody = filterCard?.querySelector('.skipping-filter-body');
        if (filterToggle && filterBody) {
            let expanded = window.matchMedia('(min-width: 992px)').matches;
            const syncFilterPanel = () => {
                filterCard.classList.toggle('skipping-filters-expanded', expanded);
                filterBody.hidden = !expanded;
                filterToggle.setAttribute('aria-expanded', String(expanded));
                filterToggle.setAttribute('aria-label', `${expanded ? 'Collapse' : 'Expand'} data filters`);
                filterToggle.querySelector('i').className = `ti ti-chevron-${expanded ? 'up' : 'down'}`;
                if (!expanded) searchableFilters.forEach((item) => item.close());
            };
            filterToggle.addEventListener('click', () => { expanded = !expanded; syncFilterPanel(); });
            syncFilterPanel();
        }
        const yearFilter = listFilters.querySelector('[name="academic_year_id"]');
        const campusFilter = listFilters.querySelector('[name="campus_id"]');
        const statusFilter = listFilters.querySelector('[name="status"]');
        const textFilter = listFilters.querySelector('[name="search"]');
        let filterTimer;
        const applyFilters = () => { clearTimeout(filterTimer); listFilters.requestSubmit(); };
        yearFilter.addEventListener('change', () => { campusFilter.value = ''; statusFilter.value = ''; applyFilters(); });
        campusFilter.addEventListener('change', () => { statusFilter.value = ''; applyFilters(); });
        statusFilter.addEventListener('change', applyFilters);
        const searchFilters = (event) => {
            clearTimeout(filterTimer);
            if (!event.isComposing) filterTimer = setTimeout(applyFilters, 650);
        };
        textFilter.addEventListener('input', searchFilters);
        textFilter.addEventListener('compositionend', searchFilters);
        listFilters.addEventListener('submit', () => clearTimeout(filterTimer));
    }
    let parents = [], searchController, detailsController, classController, timer;
    const option = (value, label) => new Option(label, value);
    const loadClasses = async (preserve = '') => {
        classController?.abort();
        classes.replaceChildren(option('', 'Select Class'));
        classes.disabled = false;
        classes.closest('.premium-form-field')?.classList.remove('has-value');
        if (!enrollment.value || !grade.value || !targetYear.value) return;
        classController = new AbortController();
        classes.disabled = true;
        const signal = classController.signal;
        try {
            const url = new URL(root.dataset.classesUrl, location.origin);
            url.searchParams.set('enrollment_id', enrollment.value);
            url.searchParams.set('grade_id', grade.value);
            url.searchParams.set('target_academic_year_id', targetYear.value);
            const json = await request(url, { signal });
            if (signal.aborted) return;
            json.classes.forEach((row) => {
                const item = option(row.id, row.class_name);
                item.dataset.session = row.session_id || '';
                classes.add(item);
            });
            classes.value = preserve;
            classes.closest('.premium-form-field')?.classList.toggle('has-value', Boolean(classes.value));
        } catch (error) {
            if (error.name !== 'AbortError') notice('error', 'Unable to load classes', error.message);
        } finally { if (!signal.aborted) classes.disabled = false; }
    };
    const selectStudent = async (id, label, initial = false) => {
        detailsController?.abort();
        classController?.abort();
        detailsController = new AbortController();
        const signal = detailsController.signal;
        if (studentFilter) {
            if (![...studentFilter.options].some((item) => item.value === String(id))) studentFilter.add(option(id, label));
            studentFilter.value = id;
            searchableFilters.get(studentFilter)?.refresh();
        }
        root.querySelector('[data-student-results]')?.setAttribute('hidden', '');
        enrollment.value = '';
        const requestForm = enrollment.closest('form');
        requestForm.dataset.campusReadOnly = 'true';
        requestForm.querySelector('[type="submit"]').disabled = true;
        if (!initial) renderStudentSummary(null, 'Loading student information…');
        try {
            const row = await request(root.dataset.enrollmentUrl.replace('__id__', id), { signal });
            if (signal.aborted) return;
            enrollment.value = row.id;
            const canSave = requestForm.dataset.requestAction === 'update' ? row.can_update : row.can_request;
            requestForm.dataset.campusReadOnly = String(!canSave);
            requestForm.querySelector('[type="submit"]').disabled = !canSave;
            requestForm.querySelector('[data-request-campus-note]').hidden = canSave;
            if (search && !initial) search.value = row.name_en || row.name_kh || row.student_id;
            renderStudentSummary(row);
            if (!initial) {
                root.querySelectorAll('[data-campus-committee-name]').forEach((input) => {
                    input.value = row.campus_committee_names?.[input.dataset.campusCommitteeName] || '';
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                });
            }
            const requestedYear = initial ? targetYear.value : String(row.enrollment_status === 'completed' ? row.target_academic_years[0]?.id || '' : row.academic_year_id);
            targetYear.replaceChildren(option('', 'Select Academic Year'));
            row.target_academic_years.forEach((year) => targetYear.add(option(year.id, year.label)));
            targetYear.value = requestedYear;
            targetYear.closest('.premium-form-field')?.classList.toggle('has-value', Boolean(targetYear.value));
            enrollment.dispatchEvent(new Event('change', { bubbles: true }));
            syncExistingPromotion();
            if (!initial) { grade.value = ''; classes.replaceChildren(option('', 'Select Class')); classes.disabled = false; group.value = ''; }
            else if (grade.value) await loadClasses(classes.value);
            if (signal.aborted) return;
            parents = row.parents.filter((parent) => ['mother', 'father'].includes(parent.relationship));
            const parentPicker = root.querySelector('[data-parent-picker]');
            parentPicker.replaceChildren(option('', 'Enter parent information below'));
            parents.forEach((parent, index) => parentPicker.add(option(String(index), `${parent.relationship}: ${parent.name}`)));
            parentPicker.add(option('guardian', 'Guardian'));
            if (!initial) {
                root.querySelector('[name="parent_name"]').value = '';
                root.querySelector('[name="parent_phone"]').value = '';
                if (parents.length) { parentPicker.value = '0'; parentPicker.dispatchEvent(new Event('change')); }
            }
        } catch (error) {
            if (error.name !== 'AbortError') { renderStudentSummary(null, 'Unable to load student information. Please select the student again.'); notice('error', 'Unable to select student', error.message); }
        }
    };
    root.querySelector('[data-parent-picker]')?.addEventListener('change', (event) => {
        const parent = parents[event.target.value];
        if (parent || event.target.value === 'guardian') {
            const name = root.querySelector('[name="parent_name"]');
            const phone = root.querySelector('[name="parent_phone"]');
            name.value = parent?.name || '';
            phone.value = parent?.phone || '';
            [name, phone].forEach((input) => input.dispatchEvent(new Event('input', { bubbles: true })));
        }
    });
    grade?.addEventListener('change', () => loadClasses());
    targetYear?.addEventListener('change', () => { syncExistingPromotion(); group.value = ''; loadClasses(); });
    classes?.addEventListener('change', () => { if (classes.selectedOptions[0]?.dataset.session) group.value = classes.selectedOptions[0].dataset.session; });
    if (enrollment?.value) selectStudent(enrollment.value, '', true);
    if (search) {
        const results = root.querySelector('[data-student-results]');
        const hint = root.querySelector('[data-student-hint]');
        const sourceGradeFilter = root.querySelector('[data-student-grade]');
        let sourceClassController;
        const loadSourceClasses = async () => {
            sourceClassController?.abort();
            sourceClassController = new AbortController();
            const signal = sourceClassController.signal;
            const year = root.querySelector('[data-student-year]').value;
            const campus = root.querySelector('[data-student-campus]').value;
            const control = searchableFilters.get(sourceGradeFilter);
            control.close();
            sourceGradeFilter.replaceChildren(option('', year || campus ? 'Loading Grades…' : 'All Grades'));
            sourceGradeFilter.disabled = control.toggle.disabled = Boolean(year || campus);
            control.refresh();
            if (!(year || campus)) return true;
            try {
                const url = new URL(root.dataset.sourceClassesUrl, location.origin);
                url.searchParams.set('academic_year_id', year);
                url.searchParams.set('campus_id', campus);
                const json = await request(url, { signal });
                if (signal.aborted) return false;
                sourceGradeFilter.replaceChildren(option('', 'All Grades'));
                json.classes.forEach((row) => sourceGradeFilter.add(option(row.value, row.label)));
                return true;
            } catch (error) {
                if (error.name !== 'AbortError') {
                    sourceGradeFilter.replaceChildren(option('', 'All Grades'));
                    hint.textContent = `Unable to load grades. ${error.message}`;
                }
                return false;
            } finally {
                if (!signal.aborted) {
                    sourceGradeFilter.disabled = control.toggle.disabled = false;
                    control.refresh();
                }
            }
        };
        const clearSelection = () => {
            root.querySelectorAll('[data-campus-committee-name]').forEach((input) => {
                input.value = '';
                input.dispatchEvent(new Event('input', { bubbles: true }));
            });
            enrollment.value = ''; detailsController?.abort(); classController?.abort();
            const form = enrollment.closest('form');
            delete form.dataset.campusReadOnly;
            form.querySelector('[type="submit"]').disabled = false;
            form.querySelector('[data-request-campus-note]').hidden = true;
            studentFilter.value = ''; searchableFilters.get(studentFilter)?.refresh();
            grade.value = ''; classes.replaceChildren(option('', 'Select Class')); classes.disabled = false; group.value = '';
            targetYear.replaceChildren(option('', 'Select Academic Year'));
            targetYear.closest('.premium-form-field')?.classList.remove('has-value');
            root.querySelector('[data-parent-picker]').replaceChildren(option('', 'Enter parent information below'), option('guardian', 'Guardian'));
            parents = [];
            root.querySelector('[name="parent_name"]').value = '';
            root.querySelector('[name="parent_phone"]').value = '';
            renderStudentSummary(null);
        };
        const findStudents = async (term = search.value.trim(), showSuggestions = false) => {
            searchController?.abort();
            const year = root.querySelector('[data-student-year]').value;
            const campus = root.querySelector('[data-student-campus]').value;
            const sourceGrade = root.querySelector('[data-student-grade]').value;
            if (term.length < 2 && !(year || campus || sourceGrade)) {
                results.hidden = true;
                studentFilter.replaceChildren(option('', 'Select Student'));
                searchableFilters.get(studentFilter)?.refresh();
                hint.textContent = 'Select a filter, or type at least 2 characters to find a student.';
                return;
            }
            searchController = new AbortController();
            const signal = searchController.signal;
            hint.textContent = 'Searching…';
            try {
                const url = new URL(root.dataset.studentsUrl, location.origin);
                url.searchParams.set('q', term);
                url.searchParams.set('academic_year_id', year);
                url.searchParams.set('campus_id', campus);
                url.searchParams.set('grade_class', sourceGrade);
                const json = await request(url, { signal });
                if (signal.aborted) return;
                results.replaceChildren();
                const selected = studentFilter.selectedOptions[0];
                studentFilter.replaceChildren(option('', 'Select Student'));
                json.students.forEach((row) => {
                    const item = option(row.id, row.label);
                    item.dataset.searchText = row.search_text;
                    studentFilter.add(item);
                    const button = document.createElement('button');
                    button.type = 'button'; button.textContent = row.label;
                    button.addEventListener('click', () => selectStudent(row.id, row.label));
                    results.append(button);
                });
                if (selected?.value && enrollment.value === selected.value) {
                    if (![...studentFilter.options].some((item) => item.value === selected.value)) studentFilter.add(selected);
                    studentFilter.value = selected.value;
                }
                searchableFilters.get(studentFilter)?.refresh();
                results.hidden = !showSuggestions || !json.students.length;
                hint.textContent = json.has_more ? 'Showing 30 results. Type a name or ID to narrow the search.' : `${json.students.length} matching student(s).`;
            } catch (error) { if (error.name !== 'AbortError') hint.textContent = error.message; }
        };
        search.addEventListener('input', () => {
            clearSelection(); searchController?.abort();
            renderStudentSummary(null, 'Select a student from the search results.');
            results.hidden = true; clearTimeout(timer); timer = setTimeout(() => findStudents(search.value.trim(), true), 250);
        });
        ['[data-student-year]', '[data-student-campus]'].forEach((selector) => root.querySelector(selector).addEventListener('change', async () => {
            clearTimeout(timer); searchController?.abort(); clearSelection(); search.value = ''; results.hidden = true;
            studentFilter.replaceChildren(option('', 'Select Student'));
            if (await loadSourceClasses()) findStudents();
        }));
        sourceGradeFilter.addEventListener('change', () => {
            clearTimeout(timer); clearSelection(); search.value = ''; results.hidden = true;
            studentFilter.replaceChildren(option('', 'Select Student'));
            findStudents();
        });
        studentFilter.addEventListener('change', () => {
            clearTimeout(timer); searchController?.abort();
            if (studentFilter.value) selectStudent(studentFilter.value, studentFilter.selectedOptions[0].textContent);
            else { clearSelection(); search.value = ''; }
        });
        searchableFilters.get(studentFilter).input.addEventListener('input', (event) => {
            clearTimeout(timer); searchController?.abort(); results.hidden = true;
            const term = event.target.value.trim();
            timer = setTimeout(() => findStudents(term), 250);
        });
        document.addEventListener('click', (event) => { if (!event.target.closest('.skipping-student-picker')) results.hidden = true; });
        search.addEventListener('focus', () => findStudents(search.value.trim(), true));
        loadSourceClasses();
    }
}
