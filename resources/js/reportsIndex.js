document.addEventListener('DOMContentLoaded', () => {
    const workspace = document.querySelector('[data-report-type]');
    if (!workspace) return;

    const reportType = workspace.dataset.reportType || '';
    const reportDate = workspace.dataset.reportDate || '';
    const form = workspace.querySelector('form');
    const periodSelect = form?.querySelector('[data-report-period-select]');
    const isQuietAttendance = ['attendance-list', 'score-list', 'student-id-books-moeys'].includes(reportType);
    let quietRefreshController = null;

    const quietRefreshAttendance = async () => {
        if (!isQuietAttendance || !form) {
            form?.requestSubmit();
            return;
        }

        quietRefreshController?.abort();
        quietRefreshController = new AbortController();

        const url = new URL(form.action, window.location.origin);
        new FormData(form).forEach((value, key) => {
            if (value !== '') url.searchParams.append(key, value);
        });

        workspace.classList.add('reports-quiet-loading');
        try {
            const response = await fetch(url.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: quietRefreshController.signal,
            });
            if (!response.ok) throw new Error('Unable to refresh report.');

            const html = await response.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const nextWorkspace = doc.querySelector('[data-report-type]');
            const nextPreviewBody = doc.querySelector('.report-preview-body');
            const previewBody = workspace.querySelector('.report-preview-body');
            if (nextPreviewBody && previewBody) {
                previewBody.innerHTML = nextPreviewBody.innerHTML;
            }

            ['reportAcademicYearValue', 'reportIdBookLevelValue', 'reportCampusValue', 'reportGradeClassValue'].forEach((id) => {
                const currentTarget = document.getElementById(id);
                const nextTarget = doc.getElementById(id);
                const currentBox = currentTarget?.closest('.report-filter-field')?.querySelector('.report-filter-combobox');
                const nextBox = nextTarget?.closest('.report-filter-field')?.querySelector('.report-filter-combobox');
                if (!currentTarget || !nextTarget || !currentBox || !nextBox) return;

                currentTarget.value = nextTarget.value;
                currentBox.outerHTML = nextBox.outerHTML;
            });

            if (nextWorkspace?.dataset.reportDate) workspace.dataset.reportDate = nextWorkspace.dataset.reportDate;
            window.history.replaceState({}, '', url.toString());
            document.querySelectorAll('.report-filter-combobox').forEach(bindFilterCombobox);
        } catch (error) {
            if (error.name !== 'AbortError') console.warn(error.message || 'Unable to refresh report.');
        } finally {
            workspace.classList.remove('reports-quiet-loading');
        }
    };

    const tabsCard = workspace.querySelector('.reports-tabs-card');
    const tabsToggle = tabsCard?.querySelector('.reports-tabs-toggle');
    if (tabsCard && tabsToggle) {
        const storageKey = 'reportsTabsCollapsed';
        const icon = tabsToggle.querySelector('i');
        const isMobile = () => window.innerWidth < 992;
        const syncTabsToggle = () => {
            const collapsed = workspace.classList.contains('reports-tabs-collapsed');
            tabsToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            tabsToggle.title = collapsed ? 'Maximize report types' : 'Minimize report types';
            if (icon) {
                icon.className = collapsed ? 'ti ti-layout-sidebar-left-expand' : 'ti ti-layout-sidebar-left-collapse';
            }
        };
        const closeTabs = () => {
            tabsCard.classList.remove('is-open');
            if (isMobile()) tabsToggle.setAttribute('aria-expanded', 'false');
        };

        if (localStorage.getItem(storageKey) === '1') {
            workspace.classList.add('reports-tabs-collapsed');
        }
        syncTabsToggle();

        tabsToggle.addEventListener('click', (event) => {
            event.stopPropagation();
            if (isMobile()) {
                const isOpen = tabsCard.classList.toggle('is-open');
                tabsToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                return;
            }

            const collapsed = workspace.classList.toggle('reports-tabs-collapsed');
            localStorage.setItem(storageKey, collapsed ? '1' : '0');
            syncTabsToggle();
        });

        tabsCard.querySelector('.reports-tabs-list')?.addEventListener('click', (event) => {
            event.stopPropagation();
        });

        document.addEventListener('click', () => {
            if (isMobile()) closeTabs();
        });
        window.addEventListener('resize', () => {
            closeTabs();
            syncTabsToggle();
        });
    }
    periodSelect?.addEventListener('change', () => {
        const academicYearValue = document.getElementById('reportAcademicYearValue');
        const campusValue = document.getElementById('reportCampusValue');
        const gradeClassValue = document.getElementById('reportGradeClassValue');
        const idBookLevelValue = document.getElementById('reportIdBookLevelValue');
        const groupValue = document.getElementById('reportGroupValue');

        if (academicYearValue) academicYearValue.value = '';
        if (campusValue) campusValue.value = '';
        if (gradeClassValue) gradeClassValue.value = '';
        if (idBookLevelValue) idBookLevelValue.value = '';
        if (groupValue) groupValue.value = '';

        quietRefreshAttendance();
    });

    const closeComboboxes = () => document.querySelectorAll('.report-filter-combobox.is-open, .report-class-picker.is-open')
        .forEach((box) => box.classList.remove('is-open'));

    const bindFilterCombobox = (box) => {
        if (box.dataset.reportComboboxBound === '1') return;
        box.dataset.reportComboboxBound = '1';

        const toggle = box.querySelector('.report-filter-toggle');
        const search = box.querySelector('.report-filter-search');
        const target = document.getElementById(box.dataset.target);
        const options = [...box.querySelectorAll('.report-filter-options button')];
        if (!toggle || !search || !target) return;

        const syncLabel = () => {
            const selected = options.find((option) => option.dataset.value === target.value);
            toggle.querySelector('span').textContent = selected?.textContent.trim() || '';
            options.forEach((option) => option.classList.toggle('is-selected', option === selected));
        };

        toggle.addEventListener('click', (event) => {
            event.stopPropagation();
            closeComboboxes();
            box.classList.toggle('is-open');
            if (box.classList.contains('is-open')) {
                search.value = '';
                options.forEach((option) => { option.hidden = false; });
                search.focus();
            }
        });

        search.addEventListener('input', () => {
            const query = search.value.trim().toLowerCase();
            options.forEach((option) => {
                option.hidden = Boolean(query) && !option.textContent.trim().toLowerCase().includes(query);
            });
        });

        options.forEach((option) => {
            option.addEventListener('click', () => {
                target.value = option.dataset.value || '';
                target.dispatchEvent(new Event('change', { bubbles: true }));
                syncLabel();
                box.classList.remove('is-open');
                if (['reportAcademicYearValue', 'reportIdBookLevelValue', 'reportCampusValue', 'reportGradeClassValue'].includes(target.id)) {
                    setTimeout(() => isQuietAttendance ? quietRefreshAttendance() : form?.requestSubmit(), 0);
                }
            });
        });

        syncLabel();
    };

    document.querySelectorAll('.report-filter-combobox').forEach(bindFilterCombobox);

    const generateIdBookButton = workspace.querySelector('[data-id-book-generate]');
    const idBookStartInput = workspace.querySelector('[data-id-book-start-number]');
    const showIdBookMessage = (icon, title, text) => {
        if (window.Swal) {
            return window.Swal.fire({ icon, title, text });
        }
        alert(text || title);
        return Promise.resolve();
    };

    generateIdBookButton?.addEventListener('click', async () => {
        if (!form || !idBookStartInput) return;

        const academicYearId = document.getElementById('reportAcademicYearValue')?.value;
        const level = document.getElementById('reportIdBookLevelValue')?.value;
        const campusId = document.getElementById('reportCampusValue')?.value;
        const startNumber = idBookStartInput.value;

        if (!academicYearId || !level || !campusId || !startNumber) {
            await showIdBookMessage('warning', 'Select filters first', 'Please select Academic Year, Book Level, Campus, and enter a start number.');
            return;
        }

        const numericStart = Number.parseInt(startNumber, 10);
        if (!Number.isInteger(numericStart) || numericStart < 1 || numericStart > 99999) {
            await showIdBookMessage('warning', 'Invalid start number', 'Please enter a number from 1 to 99999.');
            return;
        }

        const url = generateIdBookButton.dataset.generateUrl;
        const data = new FormData(form);
        data.set('id_book_start_number', String(numericStart));

        generateIdBookButton.disabled = true;
        generateIdBookButton.classList.add('disabled');

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: data,
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                const errors = payload.errors ? Object.values(payload.errors).flat() : [];
                const error = new Error(errors[0] || payload.message || 'Unable to generate list codes.');
                error.status = response.status;
                throw error;
            }

            await showIdBookMessage('success', 'Generated', payload.message || 'Generated list codes.');
            quietRefreshAttendance();
        } catch (error) {
            await showIdBookMessage(error.status === 409 ? 'info' : 'error', error.status === 409 ? 'Already generated' : 'Unable to generate', error.message || 'Unable to generate list codes.');
        } finally {
            generateIdBookButton.disabled = false;
            generateIdBookButton.classList.remove('disabled');
        }
    });

    if (!['student-list', 'student-contact-list'].includes(reportType)) {
        form?.querySelectorAll('select[name="academic_year_id"], select[name="campus_id"], select[name="grade_id"], input[name="class_id"], input[name="month"], input[name="report_date"], select[name="print_type"]').forEach((field) => {
            field.addEventListener('change', () => isQuietAttendance ? quietRefreshAttendance() : form?.requestSubmit());
        });
    }

    document.addEventListener('click', () => closeComboboxes());

    const syncActionLink = (link) => {
        if (!link || !form) return;

        const url = new URL(link.href);
        url.search = '';
        new FormData(form).forEach((value, key) => {
            if (value !== '') url.searchParams.append(key, value);
        });
        if (link.dataset.reportPrintMode) {
            url.searchParams.set('print_mode', link.dataset.reportPrintMode);
        }
        link.href = url.toString();
    };

    document.querySelectorAll('.report-print-link, .report-excel-link, .report-pdf-link').forEach((link) => {
        link.addEventListener('click', () => syncActionLink(link));
    });

    document.querySelectorAll('.report-pdf-link').forEach((link) => {
        link.addEventListener('click', async (event) => {
            syncActionLink(link);
            const scope = form?.querySelector('select[name="print_scope"]')?.value || 'selected_class';
            if (!['selected_classes', 'all_classes'].includes(scope)) return;

            event.preventDefault();
            const openPdf = (mode) => {
                const url = new URL(link.href);
                url.searchParams.set('pdf_mode', mode);
                window.location.href = url.toString();
            };

            if (window.Swal) {
                const result = await window.Swal.fire({
                    icon: 'question',
                    title: 'PDF Export',
                    text: 'How do you want to export the selected classes?',
                    showCancelButton: true,
                    showDenyButton: true,
                    confirmButtonText: 'Combine in One File',
                    denyButtonText: 'Separate File',
                    cancelButtonText: 'Cancel',
                    reverseButtons: true,
                });

                if (result.isConfirmed) openPdf('combined');
                if (result.isDenied) openPdf('separate');
                return;
            }

            openPdf(window.confirm('Combine all classes in one PDF file? Press Cancel for separate files.') ? 'combined' : 'separate');
        });
    });

    if (!['student-list', 'student-contact-list', 'attendance-list', 'score-list'].includes(reportType)) return;

    const select = document.querySelector('select[name="print_grade_classes[]"]');
    const scope = document.querySelector('select[name="print_scope"]');
    if (select) {
        const picker = document.createElement('div');
        picker.className = 'report-class-picker';
        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'report-class-picker-toggle';
        toggle.innerHTML = '<span></span><i class="ti ti-chevron-down"></i>';
        const menu = document.createElement('div');
        menu.className = 'report-class-picker-menu';
        const search = document.createElement('input');
        search.type = 'search';
        search.className = 'form-control';
        search.placeholder = 'Search Class';
        const options = document.createElement('div');
        options.className = 'report-class-picker-options';
        const label = toggle.querySelector('span');

        const sync = () => {
            const selected = [...select.options].filter((option) => option.selected).map((option) => option.textContent.trim());
            label.textContent = selected.join(', ');
        };

        [...select.options].forEach((option) => {
            const row = document.createElement('label');
            row.className = 'report-class-picker-option';
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.value = option.value;
            checkbox.checked = option.selected;
            checkbox.addEventListener('change', () => {
                option.selected = checkbox.checked;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                sync();
            });
            row.append(checkbox, document.createTextNode(option.textContent.trim()));
            options.append(row);
        });

        menu.append(search, options);
        picker.append(toggle, menu);
        select.classList.add('d-none');
        select.parentElement.insertBefore(picker, select);
        menu.addEventListener('click', (event) => {
            event.stopPropagation();
        });
        toggle.addEventListener('click', (event) => {
            event.stopPropagation();
            document.querySelectorAll('.report-class-picker.is-open').forEach((other) => {
                if (other !== picker) other.classList.remove('is-open');
            });
            picker.classList.toggle('is-open');
            if (picker.classList.contains('is-open')) search.focus();
        });
        search.addEventListener('input', () => {
            const query = search.value.toLowerCase().trim();
            options.querySelectorAll('.report-class-picker-option').forEach((row) => {
                row.hidden = Boolean(query) && !row.textContent.toLowerCase().includes(query);
            });
        });
        document.addEventListener('click', () => picker.classList.remove('is-open'));
        sync();
    }

    if (scope && select) {
        const field = select.closest('[class*="col-"]');
        const update = () => {
            const show = scope.value === 'selected_classes';
            field.hidden = !show;
            if (!show) {
                [...select.options].forEach((option) => { option.selected = false; });
                select.dispatchEvent(new Event('change', { bubbles: true }));
            }
        };
        scope.addEventListener('change', update);
        update();
    }

    const reportDateInput = document.querySelector('input[name="report_date"]');
    if (!reportDateInput && scope) {
        const field = document.createElement('div');
        field.className = 'col-md-3';
        field.innerHTML = `<label class="form-label">Report Date</label><input type="date" name="report_date" class="form-control" value="${reportDate}">`;
        scope.closest('[class*="col-"]')?.insertAdjacentElement('afterend', field);
    }
});
