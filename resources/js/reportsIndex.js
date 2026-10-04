import { Tooltip } from 'bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    const workspace = document.querySelector('[data-report-type]');
    if (!workspace) return;

    const reportType = workspace.dataset.reportType || '';
    const reportDate = workspace.dataset.reportDate || '';
    const form = workspace.querySelector('form');
    const periodSelect = form?.querySelector('[data-report-period-select]');
    const isQuietAttendance = ['attendance-list', 'score-list', 'student-id-books-moeys', 'moeys-sikkhakarik-book'].includes(reportType);
    const requiresManualApply = ['moeys-id-number-book'].includes(reportType);
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

            ['reportAcademicYearValue', 'reportIdBookLevelValue', 'reportTranscriptLevelValue', 'reportCampusValue', 'reportGradeClassValue', 'reportGroupValue'].forEach((id) => {
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

    const reportDateMonthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const formatReportDisplayDate = (isoDate) => {
        const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(isoDate || '');
        if (!match) return '';
        const [, year, month, day] = match;
        const monthName = reportDateMonthNames[Number(month) - 1] || '';
        return monthName ? day + '-' + monthName + '-' + year : '';
    };

    const parseReportDisplayDate = (displayDate) => {
        const value = (displayDate || '').trim();
        const named = /^(\d{1,2})[-\/\s]([A-Za-z]{3,9})[-\/\s](\d{4})$/.exec(value);
        const numeric = /^(\d{1,2})[-\/\s]?(\d{1,2})[-\/\s]?(\d{4})$/.exec(value);
        let day;
        let month;
        let year;
        if (named) {
            const months = {
                jan: '01', january: '01', feb: '02', february: '02', mar: '03', march: '03', apr: '04', april: '04',
                may: '05', jun: '06', june: '06', jul: '07', july: '07', aug: '08', august: '08', sep: '09', sept: '09', september: '09',
                oct: '10', october: '10', nov: '11', november: '11', dec: '12', december: '12',
            };
            day = named[1].padStart(2, '0');
            month = months[named[2].toLowerCase()];
            year = named[3];
        } else if (numeric) {
            day = numeric[1].padStart(2, '0');
            month = numeric[2].padStart(2, '0');
            year = numeric[3];
        }
        if (!day || !month || !year) return '';
        const date = new Date(Number(year), Number(month) - 1, Number(day));
        if (date.getFullYear() !== Number(year) || date.getMonth() !== Number(month) - 1 || date.getDate() !== Number(day)) return '';
        return year + '-' + month + '-' + day;
    };

    const parseIsoDate = (isoDate) => {
        const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(isoDate || '');
        if (!match) return new Date();
        return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
    };

    const isoFromDate = (date) => date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');

    const transcriptDatePicker = form?.querySelector('[data-report-date-picker]');
    const transcriptDateDisplay = form?.querySelector('[data-report-date-display]');
    const transcriptDateDisplaySpan = form?.querySelector('#report_print_date_display');
    const transcriptDateValue = form?.querySelector('[data-report-date-value]');
    const transcriptDateCalendar = form?.querySelector('[data-report-date-calendar]');
    const transcriptDateToggle = form?.querySelector('[data-report-date-toggle]');
    if (transcriptDatePicker && transcriptDateDisplay && transcriptDateValue && transcriptDateCalendar) {
        const popup = transcriptDateCalendar;
        const days = transcriptDatePicker.querySelector('.date-picker-days');
        const monthButton = transcriptDatePicker.querySelector('[data-date-month]');
        const yearPopup = transcriptDatePicker.querySelector('.date-picker-year-popup');
        const years = transcriptDatePicker.querySelector('.date-picker-years');
        let cursor = parseIsoDate(transcriptDateValue.value);
        cursor = new Date(cursor.getFullYear(), cursor.getMonth(), 1);

        const renderYears = () => {
            if (!years) return;
            const current = cursor.getFullYear();
            const startYear = 1900;
            const endYear = new Date().getFullYear() + 10;
            years.innerHTML = Array.from({ length: endYear - startYear + 1 }, (_, index) => {
                const year = startYear + index;
                return '<button type="button" class="date-picker-year' + (year === current ? ' is-selected' : '') + '" data-date-year="' + year + '">' + year + '</button>';
            }).join('');
            years.querySelector('.is-selected')?.scrollIntoView({ block: 'center' });
        };

        const renderReportDateCalendar = () => {
            if (!days || !monthButton) return;
            monthButton.textContent = cursor.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
            const first = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
            const count = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 0).getDate();
            const cells = [];
            for (let i = 0; i < first.getDay(); i += 1) cells.push(new Date(cursor.getFullYear(), cursor.getMonth(), i - first.getDay() + 1));
            for (let day = 1; day <= count; day += 1) cells.push(new Date(cursor.getFullYear(), cursor.getMonth(), day));
            while (cells.length < 42) cells.push(new Date(cursor.getFullYear(), cursor.getMonth() + 1, cells.length - first.getDay() - count + 1));
            days.innerHTML = cells.map((date) => {
                const isoDate = isoFromDate(date);
                return '<button type="button" class="date-picker-day' + (date.getMonth() !== cursor.getMonth() ? ' is-outside' : '') + (isoDate === transcriptDateValue.value ? ' is-selected' : '') + '" data-date-value="' + isoDate + '">' + date.getDate() + '</button>';
            }).join('');
            renderYears();
        };

        const showReportDateCalendar = () => {
            cursor = parseIsoDate(transcriptDateValue.value || isoFromDate(new Date()));
            cursor = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
            renderReportDateCalendar();
            popup.classList.remove('d-none');
            transcriptDatePicker.classList.add('is-open');
        };

        const hideReportDateCalendar = () => {
            popup.classList.add('d-none');
            yearPopup?.classList.add('d-none');
            transcriptDatePicker.classList.remove('is-open');
        };

        const commitReportDate = (isoDate, dispatch = true) => {
            if (!/^(\d{4})-(\d{2})-(\d{2})$/.test(isoDate || '')) return;
            const parts = isoDate.split('-');
            transcriptDateValue.value = isoDate;
            transcriptDateDisplay.value = parts[2] + '-' + reportDateMonthNames[Number(parts[1]) - 1] + '-' + parts[0];
            cursor = parseIsoDate(isoDate);
            cursor = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
            renderReportDateCalendar();
        };

        const commitTypedReportDate = (dispatch = true) => {
            const parsed = parseReportDisplayDate(transcriptDateDisplay.value);
            if (parsed) {
                commitReportDate(parsed, dispatch);
            } else {
                transcriptDateDisplay.value = formatReportDisplayDate(transcriptDateValue.value) || '';
            }
        };

        transcriptDateDisplay.value = formatReportDisplayDate(transcriptDateValue.value) || transcriptDateDisplay.value;
        if (transcriptDateDisplaySpan) transcriptDateDisplaySpan.textContent = transcriptDateDisplay.value || 'Choose date';
        transcriptDateToggle?.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            popup.classList.contains('d-none') ? showReportDateCalendar() : hideReportDateCalendar();
        });
        transcriptDatePicker.addEventListener('click', (event) => {
            if (event.target.closest('.date-picker-popup') || event.target.closest('[data-report-date-toggle]')) return;
            showReportDateCalendar();
        });
        transcriptDateDisplay.addEventListener('focus', showReportDateCalendar);
        transcriptDateDisplay.addEventListener('change', () => commitTypedReportDate(true));
        transcriptDateDisplay.addEventListener('blur', () => commitTypedReportDate(false));
        transcriptDateDisplay.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter') return;
            event.preventDefault();
            commitTypedReportDate(true);
            hideReportDateCalendar();
        });
        transcriptDatePicker.querySelector('[data-date-prev]')?.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            cursor = new Date(cursor.getFullYear(), cursor.getMonth() - 1, 1);
            renderReportDateCalendar();
        });
        transcriptDatePicker.querySelector('[data-date-next]')?.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            cursor = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 1);
            yearPopup?.classList.add('d-none');
            renderReportDateCalendar();
        });
        monthButton?.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            yearPopup?.classList.toggle('d-none');
            if (!yearPopup?.classList.contains('d-none')) renderYears();
        });
        years?.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            const button = event.target.closest('[data-date-year]');
            if (!button) return;
            cursor = new Date(Number(button.dataset.dateYear), cursor.getMonth(), 1);
            yearPopup?.classList.add('d-none');
            renderReportDateCalendar();
        });
        days?.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            const button = event.target.closest('[data-date-value]');
            if (!button) return;
            commitReportDate(button.dataset.dateValue);
            hideReportDateCalendar();
        });
        document.addEventListener('click', (event) => {
            if (!transcriptDatePicker.contains(event.target)) hideReportDateCalendar();
        });
        renderReportDateCalendar();
    }

    const tabsCard = workspace.querySelector('.reports-tabs-card');
    const tabsToggle = tabsCard?.querySelector('.reports-tabs-toggle');
    if (tabsCard && tabsToggle) {
        const storageKey = 'reportsTabsCollapsed';
        const icon = tabsToggle.querySelector('i');
        const tabTooltips = [...tabsCard.querySelectorAll('[data-bs-toggle="tooltip"]')].map((element) => {
            const tooltip = Tooltip.getOrCreateInstance(element, {
                container: 'body',
                placement: 'right',
                customClass: 'report-tabs-tooltip',
                delay: { show: 200, hide: 0 },
                title: () => element === tabsToggle ? element.getAttribute('aria-label') : element.dataset.reportTabLabel,
            });
            element.addEventListener('click', () => tooltip.hide());
            return tooltip;
        });
        const isMobile = () => window.innerWidth < 992;
        const syncTabsToggle = () => {
            const collapsed = isMobile() ? !tabsCard.classList.contains('is-open') : workspace.classList.contains('reports-tabs-collapsed');
            tabTooltips.forEach((tooltip) => {
                tooltip.hide();
                if (collapsed) tooltip.enable();
                else tooltip.disable();
            });
            tabsToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            tabsToggle.setAttribute('aria-label', collapsed ? 'Expand report types' : 'Collapse report types');
            tabsToggle.setAttribute('data-bs-title', collapsed ? 'Expand report types' : 'Collapse report types');
            if (icon) {
                icon.className = collapsed ? 'ti ti-layout-sidebar-left-expand' : 'ti ti-layout-sidebar-left-collapse';
            }
        };
        const closeTabs = () => {
            tabsCard.classList.remove('is-open');
            syncTabsToggle();
        };

        try {
            if (localStorage.getItem(storageKey) === '1') workspace.classList.add('reports-tabs-collapsed');
        } catch (_) { /* Keep the toggle usable when browser storage is unavailable. */ }
        syncTabsToggle();

        tabsToggle.addEventListener('click', (event) => {
            event.stopPropagation();
            if (isMobile()) {
                tabsCard.classList.toggle('is-open');
                syncTabsToggle();
                return;
            }

            const collapsed = workspace.classList.toggle('reports-tabs-collapsed');
            try {
                localStorage.setItem(storageKey, collapsed ? '1' : '0');
            } catch (_) { /* The current page can still collapse and expand. */ }
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
        const transcriptLevelValue = document.getElementById('reportTranscriptLevelValue');
        const groupValue = document.getElementById('reportGroupValue');

        if (academicYearValue) academicYearValue.value = '';
        if (campusValue) campusValue.value = '';
        if (gradeClassValue) gradeClassValue.value = '';
        if (idBookLevelValue) idBookLevelValue.value = '';
        if (transcriptLevelValue) transcriptLevelValue.value = '';
        if (groupValue) groupValue.value = '';

        if (requiresManualApply) refreshManualFilterOptions();
        else quietRefreshAttendance();
    });

    const closeComboboxes = () => document.querySelectorAll('.report-filter-combobox.is-open, .report-class-picker.is-open, .report-column-dropdown.is-open')
        .forEach((box) => {
            box.classList.remove('is-open');
            box.querySelector('[data-column-toggle]')?.setAttribute('aria-expanded', 'false');
        });

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
                if (target.id === 'reportTranscriptLevelValue') {
                    const gradeClassValue = document.getElementById('reportGradeClassValue');
                    if (gradeClassValue) gradeClassValue.value = '';
                }
                syncLabel();
                box.classList.remove('is-open');
                if (requiresManualApply && ['reportAcademicYearValue', 'reportCampusValue'].includes(target.id)) {
                    refreshManualFilterOptions();
                }
                if (!requiresManualApply && ['reportAcademicYearValue', 'reportIdBookLevelValue', 'reportTranscriptLevelValue', 'reportCampusValue', 'reportGradeClassValue', 'reportGroupValue'].includes(target.id)) {
                    setTimeout(() => isQuietAttendance ? quietRefreshAttendance() : form?.requestSubmit(), 0);
                }
            });
        });

        syncLabel();
    };

    document.querySelectorAll('.report-filter-combobox').forEach(bindFilterCombobox);

    const replaceFilterOptions = (targetId, defaultLabel, entries) => {
        const target = document.getElementById(targetId);
        const box = target?.closest('.report-filter-field')?.querySelector('.report-filter-combobox');
        if (!box) return null;
        const replacement = box.cloneNode(true);
        delete replacement.dataset.reportComboboxBound;
        replacement.classList.remove('is-open');
        replacement.querySelector('.report-filter-toggle').disabled = false;
        replacement.querySelector('.report-filter-search').value = '';
        const options = replacement.querySelector('.report-filter-options');
        options.replaceChildren();
        [{ value: '', label: defaultLabel }, ...entries].forEach((entry) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.dataset.value = String(entry.value);
            button.textContent = entry.label;
            options.append(button);
        });
        box.replaceWith(replacement);
        bindFilterCombobox(replacement);
        return replacement;
    };

    let manualFilterController = null;
    let manualFilterVersion = 0;
    const refreshManualFilterOptions = async () => {
        if (!requiresManualApply || !form) return;
        manualFilterController?.abort();
        manualFilterController = new AbortController();
        const version = ++manualFilterVersion;
        const gradeTarget = document.getElementById('reportGradeClassValue');
        if (gradeTarget) gradeTarget.value = '';
        const gradeBox = replaceFilterOptions('reportGradeClassValue', 'All Grades', []);
        const gradeToggle = gradeBox?.querySelector('.report-filter-toggle');
        if (gradeToggle) {
            gradeToggle.disabled = true;
            gradeToggle.querySelector('span').textContent = 'Loading grades…';
            gradeBox.setAttribute('aria-busy', 'true');
        }
        // Keep labels in sync when changing Period clears the hidden filter values.
        ['reportAcademicYearValue', 'reportCampusValue'].forEach((id) => {
            const target = document.getElementById(id);
            const box = target?.closest('.report-filter-field')?.querySelector('.report-filter-combobox');
            const selected = [...(box?.querySelectorAll('.report-filter-options button') || [])]
                .find((option) => option.dataset.value === target.value);
            if (selected) box.querySelector('.report-filter-toggle span').textContent = selected.textContent.trim();
        });
        const url = new URL(form.action, window.location.origin);
        url.searchParams.set('type', reportType);
        url.searchParams.set('filter_options', '1');
        ['period_type', 'academic_year_id', 'campus_id'].forEach((name) => {
            const value = form.elements.namedItem(name)?.value;
            if (value) url.searchParams.set(name, value);
        });
        try {
            const response = await fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: manualFilterController.signal,
            });
            if (!response.ok) throw new Error('Unable to load grade options.');
            const payload = await response.json();
            if (version !== manualFilterVersion) return;
            const yearTarget = document.getElementById('reportAcademicYearValue');
            if (yearTarget) yearTarget.value = payload.academicYearId;
            replaceFilterOptions('reportAcademicYearValue', 'Select Academic Year', payload.academicYears);
            const loaded = replaceFilterOptions('reportGradeClassValue', 'All Grades', payload.gradeClassOptions);
            loaded?.removeAttribute('aria-busy');
        } catch (error) {
            if (error.name === 'AbortError' || version !== manualFilterVersion) return;
            if (gradeToggle) {
                gradeToggle.disabled = false;
                gradeToggle.querySelector('span').textContent = 'All Grades';
                gradeBox.removeAttribute('aria-busy');
            }
            showReportToast('error', 'Unable to load grades', 'Please select the Academic Year or Campus again.');
        }
    };

    const generateIdBookButton = workspace.querySelector('[data-id-book-generate]');
    const idBookStartInput = workspace.querySelector('[data-id-book-start-number]');
    const showReportToast = (icon, title, text = '') => {
        if (!window.Swal) return Promise.resolve();

        return window.Swal.fire({
            toast: true,
            position: 'top-end',
            icon,
            title,
            text,
            showConfirmButton: false,
            timer: 900,
            timerProgressBar: true,
            width: 'auto',
        });
    };
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

    if (!requiresManualApply && !['student-list', 'student-contact-list'].includes(reportType)) {
        form?.querySelectorAll('select[name="academic_year_id"], select[name="campus_id"], select[name="grade_id"], input[name="class_id"], input[name="month"], input[name="report_date"], select[name="print_type"]').forEach((field) => {
            field.addEventListener('change', () => isQuietAttendance ? quietRefreshAttendance() : form?.requestSubmit());
        });
    }

    document.addEventListener('click', () => closeComboboxes());

    let getStudentListFormData = null;
    if (reportType === 'moeys-id-number-book' && form) {
        const orderList = form.querySelector('[data-selected-column-order-list]');
        const columnCheckboxes = [...form.querySelectorAll('input[name="selected_columns[]"]')];
        const columnLabels = new Map(columnCheckboxes.map((checkbox) => [checkbox.value, checkbox.closest('label')?.textContent.trim() || checkbox.value]));
        let columnOrder = columnCheckboxes.filter((checkbox) => checkbox.checked).map((checkbox) => checkbox.value);
        let draggedColumn = null;

        const normalizeColumnOrder = () => {
            const checkedValues = columnCheckboxes.filter((checkbox) => checkbox.checked).map((checkbox) => checkbox.value);
            columnOrder = columnOrder.filter((value) => checkedValues.includes(value));
            checkedValues.forEach((value) => {
                if (!columnOrder.includes(value)) columnOrder.push(value);
            });
        };

        const renderColumnOrder = () => {
            normalizeColumnOrder();
            if (!orderList) return;
            orderList.innerHTML = '';
            if (columnOrder.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'selected-column-order-empty';
                empty.textContent = 'No columns selected.';
                orderList.append(empty);
                return;
            }

            columnOrder.forEach((value, index) => {
                const label = columnLabels.get(value) || value;
                const item = document.createElement('div');
                item.className = 'selected-column-order-item';
                item.draggable = true;
                item.dataset.column = value;

                const number = document.createElement('span');
                number.className = 'selected-column-order-number';
                number.textContent = String(index + 1);

                const grip = document.createElement('i');
                grip.className = 'ti ti-grip-vertical';
                grip.setAttribute('aria-hidden', 'true');

                const labelText = document.createElement('span');
                labelText.textContent = label;

                const removeButton = document.createElement('button');
                removeButton.type = 'button';
                removeButton.className = 'selected-column-order-remove';
                removeButton.setAttribute('aria-label', `Remove ${label}`);
                removeButton.dataset.removeColumn = value;

                const removeIcon = document.createElement('i');
                removeIcon.className = 'ti ti-x';
                removeIcon.setAttribute('aria-hidden', 'true');
                removeButton.append(removeIcon);

                item.append(number, grip, labelText, removeButton);

                removeButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    const checkbox = columnCheckboxes.find((field) => field.value === value);
                    if (checkbox) {
                        checkbox.checked = false;
                        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                    } else {
                        columnOrder = columnOrder.filter((column) => column !== value);
                        renderColumnOrder();
                    }
                });
                item.addEventListener('dragstart', () => {
                    draggedColumn = value;
                    item.classList.add('is-dragging');
                });
                item.addEventListener('dragend', () => {
                    draggedColumn = null;
                    item.classList.remove('is-dragging');
                });
                item.addEventListener('dragover', (event) => event.preventDefault());
                item.addEventListener('drop', (event) => {
                    event.preventDefault();
                    if (!draggedColumn || draggedColumn === value) return;
                    columnOrder = columnOrder.filter((column) => column !== draggedColumn);
                    const dropIndex = columnOrder.indexOf(value);
                    columnOrder.splice(dropIndex, 0, draggedColumn);
                    renderColumnOrder();
                });
                orderList.append(item);
            });
        };

        getStudentListFormData = () => {
            normalizeColumnOrder();
            const data = new FormData(form);
            data.delete('selected_columns[]');
            data.set('selected_columns_submitted', '1');
            columnOrder.forEach((value) => data.append('selected_columns[]', value));
            return data;
        };

        columnCheckboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', renderColumnOrder);
        });

        form.querySelectorAll('[data-column-dropdown]').forEach((dropdown) => {
            const toggle = dropdown.querySelector('[data-column-toggle]');
            const summary = dropdown.querySelector('[data-column-summary]');
            const search = dropdown.querySelector('[data-column-search]');
            const options = [...dropdown.querySelectorAll('[data-column-option]')];
            const empty = dropdown.querySelector('[data-column-empty]');
            const syncSummary = () => {
                const count = dropdown.querySelectorAll('input[name="selected_columns[]"]:checked').length;
                summary.textContent = count ? `${count} ${count === 1 ? 'column' : 'columns'} selected` : 'Select columns';
            };
            const filterOptions = () => {
                const term = search.value.trim().toLowerCase();
                options.forEach((option) => {
                    option.hidden = term !== '' && !option.textContent.toLowerCase().includes(term);
                });
                empty.classList.toggle('d-none', options.some((option) => !option.hidden));
            };
            toggle.addEventListener('click', (event) => {
                event.stopPropagation();
                const opening = !dropdown.classList.contains('is-open');
                closeComboboxes();
                if (!opening) return;
                dropdown.classList.add('is-open');
                toggle.setAttribute('aria-expanded', 'true');
                search.value = '';
                filterOptions();
                search.focus();
            });
            dropdown.querySelector('[data-column-menu]').addEventListener('click', (event) => event.stopPropagation());
            dropdown.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    event.stopPropagation();
                    closeComboboxes();
                    toggle.focus();
                }
                if (event.key === 'Enter' && event.target === search) event.preventDefault();
            });
            dropdown.querySelectorAll('input[name="selected_columns[]"]').forEach((checkbox) => {
                checkbox.addEventListener('change', syncSummary);
            });
            search.addEventListener('input', () => {
                filterOptions();
            });
            syncSummary();
        });

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const url = new URL(form.action, window.location.origin);
            getStudentListFormData().forEach((value, key) => {
                if (value !== '') url.searchParams.append(key, value);
            });
            showReportToast('success', 'Applied', 'Customize student list filters updated.').finally(() => {
                window.location.href = url.toString();
            });
        });

        form.querySelector('[data-get-student-list-clear]')?.addEventListener('click', (event) => {
            event.preventDefault();
            const url = event.currentTarget.href;
            showReportToast('info', 'Cleared', 'Filters and selected information cleared.').finally(() => {
                window.location.href = url;
            });
        });

        renderColumnOrder();
    }

    const syncActionLink = (link) => {
        if (!link || !form) return;

        const url = new URL(link.href);
        url.search = '';
        const data = getStudentListFormData ? getStudentListFormData() : new FormData(form);
        data.forEach((value, key) => {
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

    document.querySelectorAll('[data-preview-page-size]').forEach((select) => {
        select.addEventListener('change', () => {
            const url = select.selectedOptions[0]?.dataset.url;
            if (url) window.location.href = url;
        });
    });

    document.querySelectorAll('[data-preview-goto]').forEach((input) => {
        input.addEventListener('change', () => {
            const min = Number(input.min || 1);
            const max = Number(input.max || 1);
            const page = Math.min(max, Math.max(min, Number(input.value) || min));
            input.value = String(page);
            const template = input.dataset.previewGotoUrl || '';
            if (template) window.location.href = template.replace('__page__', String(page));
        });
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

    if (!['student-list', 'student-contact-list', 'student-profile-label', 'attendance-list', 'score-list'].includes(reportType)) return;

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
        field.className = 'col-md-3 report-filter-field';
        field.innerHTML = `<label class="form-label">Report Date</label><input type="date" name="report_date" class="form-control" value="${reportDate}">`;
        scope.closest('[class*="col-"]')?.insertAdjacentElement('afterend', field);
    }
});


/* Reports page inline scripts extracted from index.blade.php. */

/* extracted inline report script 1 id="report-inline-date-picker-script" */
document.addEventListener('DOMContentLoaded', function () {
    var picker = document.querySelector('[data-report-date-picker]');
    if (!picker || picker.dataset.reportDateBound === '1') return;
    picker.dataset.reportDateBound = '1';

    var display = picker.querySelector('[data-report-date-display]');
    var displaySpan = picker.querySelector('#report_print_date_display');
    var hidden = picker.querySelector('[data-report-date-value]');
    var toggle = picker.querySelector('[data-report-date-toggle]');
    var popup = picker.querySelector('[data-report-date-calendar]');
    var days = picker.querySelector('.date-picker-days');
    var monthButton = picker.querySelector('[data-date-month]');
    var yearPopup = picker.querySelector('.date-picker-year-popup');
    var years = picker.querySelector('.date-picker-years');
    if (!display || !hidden || !toggle || !popup || !days || !monthButton) return;

    var monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    var monthMap = { jan:'01', january:'01', feb:'02', february:'02', mar:'03', march:'03', apr:'04', april:'04', may:'05', jun:'06', june:'06', jul:'07', july:'07', aug:'08', august:'08', sep:'09', sept:'09', september:'09', oct:'10', october:'10', nov:'11', november:'11', dec:'12', december:'12' };
    var cursor = parseIso(hidden.value || iso(new Date()));
    cursor = new Date(cursor.getFullYear(), cursor.getMonth(), 1);

    function iso(date) {
        return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
    }
    function parseIso(value) {
        var match = /^(d{4})-(d{2})-(d{2})$/.exec(value || '');
        return match ? new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3])) : new Date();
    }
    function displayDate(value) {
        var match = /^(d{4})-(d{2})-(d{2})$/.exec(value || '');
        if (!match) return '';
        var monthName = monthNames[Number(match[2]) - 1] || '';
        return monthName ? match[3] + '-' + monthName + '-' + match[1] : '';
    }
    function parseDisplay(value) {
        value = (value || '').trim();
        var named = /^(d{1,2})[-/s]([A-Za-z]{3,9})[-/s](d{4})$/.exec(value);
        var numeric = /^(d{1,2})[-/s]?(d{1,2})[-/s]?(d{4})$/.exec(value);
        var day, month, year;
        if (named) {
            day = named[1].padStart(2, '0');
            month = monthMap[named[2].toLowerCase()];
            year = named[3];
        } else if (numeric) {
            day = numeric[1].padStart(2, '0');
            month = numeric[2].padStart(2, '0');
            year = numeric[3];
        }
        if (!day || !month || !year) return '';
        var date = new Date(Number(year), Number(month) - 1, Number(day));
        if (date.getFullYear() !== Number(year) || date.getMonth() !== Number(month) - 1 || date.getDate() !== Number(day)) return '';
        return year + '-' + month + '-' + day;
    }
    function renderYears() {
        if (!years) return;
        var current = cursor.getFullYear();
        var html = '';
        for (var year = 1900; year <= new Date().getFullYear() + 10; year += 1) {
            html += '<button type="button" class="date-picker-year' + (year === current ? ' is-selected' : '') + '" data-date-year="' + year + '">' + year + '</button>';
        }
        years.innerHTML = html;
        years.querySelector('.is-selected')?.scrollIntoView({ block: 'center' });
    }
    function render() {
        monthButton.textContent = cursor.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
        var first = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
        var count = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 0).getDate();
        var cells = [];
        for (var i = 0; i < first.getDay(); i += 1) cells.push(new Date(cursor.getFullYear(), cursor.getMonth(), i - first.getDay() + 1));
        for (var d = 1; d <= count; d += 1) cells.push(new Date(cursor.getFullYear(), cursor.getMonth(), d));
        while (cells.length < 42) cells.push(new Date(cursor.getFullYear(), cursor.getMonth() + 1, cells.length - first.getDay() - count + 1));
        days.innerHTML = cells.map(function (date) {
            var value = iso(date);
            return '<button type="button" class="date-picker-day' + (date.getMonth() !== cursor.getMonth() ? ' is-outside' : '') + (value === hidden.value ? ' is-selected' : '') + '" data-date-value="' + value + '">' + date.getDate() + '</button>';
        }).join('');
        renderYears();
    }
    function openCalendar() {
        cursor = parseIso(hidden.value || iso(new Date()));
        cursor = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
        render();
        popup.classList.remove('d-none');
    }
    function closeCalendar() {
        popup.classList.add('d-none');
        yearPopup?.classList.add('d-none');
    }
    function commit(value) {
        if (!/^(\d{4})-(\d{2})-(\d{2})$/.test(value || '')) return;
        var parts = value.split('-');
        hidden.value = value;
        display.value = parts[2] + '-' + monthNames[Number(parts[1]) - 1] + '-' + parts[0];
        cursor = parseIso(value);
        cursor = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
        render();
    }

    display.value = displayDate(hidden.value) || display.value;
    if (displaySpan) displaySpan.textContent = display.value || 'Choose date';
    toggle.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        popup.classList.contains('d-none') ? openCalendar() : closeCalendar();
    });
    display.addEventListener('focus', openCalendar);
    display.addEventListener('change', function () {
        var parsed = parseDisplay(display.value);
        if (parsed) commit(parsed);
        else display.value = displayDate(hidden.value) || '';
    });
    picker.querySelector('[data-date-prev]')?.addEventListener('click', function (event) {
        event.preventDefault(); event.stopPropagation();
        cursor = new Date(cursor.getFullYear(), cursor.getMonth() - 1, 1);
        render();
    });
    picker.querySelector('[data-date-next]')?.addEventListener('click', function (event) {
        event.preventDefault(); event.stopPropagation();
        cursor = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 1);
        yearPopup?.classList.add('d-none');
        render();
    });
    monthButton.addEventListener('click', function (event) {
        event.preventDefault(); event.stopPropagation();
        yearPopup?.classList.toggle('d-none');
        if (!yearPopup?.classList.contains('d-none')) renderYears();
    });
    years?.addEventListener('click', function (event) {
        event.preventDefault(); event.stopPropagation();
        var button = event.target.closest('[data-date-year]');
        if (!button) return;
        cursor = new Date(Number(button.dataset.dateYear), cursor.getMonth(), 1);
        yearPopup?.classList.add('d-none');
        render();
    });
    days.addEventListener('click', function (event) {
        event.preventDefault(); event.stopPropagation();
        var button = event.target.closest('[data-date-value]');
        if (!button) return;
        var selectedValue = button.getAttribute('data-date-value');
        commit(selectedValue);
        setTimeout(function () {
            if (/^(\d{4})-(\d{2})-(\d{2})$/.test(selectedValue || '')) {
                hidden.value = selectedValue;
                display.value = displayDate(selectedValue);
                if (displaySpan) displaySpan.textContent = displayDate(selectedValue);
            }
        }, 50);
        closeCalendar();
    });
    document.addEventListener('click', function (event) {
        if (!picker.contains(event.target)) closeCalendar();
    });
    render();
});

document.addEventListener('click', function (event) {
    var day = event.target.closest('[data-date-value]');
    if (!day) return;
    var picker = day.closest('[data-report-date-picker]');
    if (!picker) return;
    var value = day.getAttribute('data-date-value');
    if (!/^(\d{4})-(\d{2})-(\d{2})$/.test(value || '')) return;

    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();

    var hidden = picker.querySelector('[data-report-date-value]');
    var direct = picker.querySelector('[data-report-date-display]');
    var display = picker.querySelector('#report_print_date_display');
    var popup = picker.querySelector('[data-report-date-calendar]');
    var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    var parts = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
    var formatted = parts[3] + '-' + months[Number(parts[2]) - 1] + '-' + parts[1];

    if (hidden) hidden.value = value;
    if (direct) direct.value = formatted;
    if (display) display.textContent = formatted;
    if (popup) popup.classList.add('d-none');

    picker.querySelectorAll('[data-date-value]').forEach(function (button) {
        button.classList.toggle('is-selected', button.getAttribute('data-date-value') === value);
    });
}, true);


/* extracted inline report script 2 id="report-print-date-force-open-script" */
document.addEventListener('click', function (event) {
    var toggle = event.target.closest('[data-report-date-toggle]');
    if (!toggle) return;
    var picker = toggle.closest('[data-report-date-picker]');
    if (!picker) return;
    var popup = picker.querySelector('[data-report-date-calendar]');
    var hidden = picker.querySelector('[data-report-date-value]');
    var days = picker.querySelector('.date-picker-days');
    var monthLabel = picker.querySelector('#report_print_date_month_label, [data-date-month]');
    if (!popup || !hidden || !days || !monthLabel) return;

    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();

    var monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    function iso(date) {
        return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
    }
    function parseIso(value) {
        var match = /^(d{4})-(d{2})-(d{2})$/.exec(value || '');
        return match ? new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3])) : new Date();
    }
    var cursor = parseIso(hidden.value);
    cursor = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
    function render() {
        monthLabel.textContent = monthNames[cursor.getMonth()] + ' ' + cursor.getFullYear();
        var first = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
        var count = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 0).getDate();
        var cells = [];
        for (var i = 0; i < first.getDay(); i += 1) cells.push(new Date(cursor.getFullYear(), cursor.getMonth(), i - first.getDay() + 1));
        for (var d = 1; d <= count; d += 1) cells.push(new Date(cursor.getFullYear(), cursor.getMonth(), d));
        while (cells.length < 42) cells.push(new Date(cursor.getFullYear(), cursor.getMonth() + 1, cells.length - first.getDay() - count + 1));
        days.innerHTML = cells.map(function (date) {
            var value = iso(date);
            return '<button type="button" class="date-picker-day' + (date.getMonth() !== cursor.getMonth() ? ' is-outside' : '') + (value === hidden.value ? ' is-selected' : '') + '" data-date-value="' + value + '">' + date.getDate() + '</button>';
        }).join('');
    }
    render();
    popup.classList.toggle('d-none');
}, true);
