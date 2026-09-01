document.addEventListener('DOMContentLoaded', () => {
    const workspace = document.querySelector('[data-report-type]');
    if (!workspace) return;

    const reportType = workspace.dataset.reportType || '';
    const reportDate = workspace.dataset.reportDate || '';
    const form = workspace.querySelector('form');

    const closeComboboxes = () => document.querySelectorAll('.report-filter-combobox.is-open, .report-class-picker.is-open')
        .forEach((box) => box.classList.remove('is-open'));

    const bindFilterCombobox = (box) => {
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
            });
        });

        syncLabel();
    };

    document.querySelectorAll('.report-filter-combobox').forEach(bindFilterCombobox);

    document.addEventListener('click', () => closeComboboxes());

    document.querySelectorAll('.report-filter-combobox').forEach((box) => {
        const target = document.getElementById(box.dataset.target);
        if (!target || !['reportAcademicYearValue', 'reportCampusValue', 'reportGradeClassValue'].includes(target.id)) return;
        box.querySelectorAll('.report-filter-options button').forEach((option) => {
            option.addEventListener('click', () => setTimeout(() => form?.requestSubmit(), 0));
        });
    });

    const reportPrintLink = document.querySelector('.report-print-link');
    if (reportPrintLink) {
        reportPrintLink.addEventListener('click', () => {
            const url = new URL(reportPrintLink.href);
            new FormData(form).forEach((value, key) => url.searchParams.append(key, value));
            reportPrintLink.href = url.toString();
        });
    }

    if (reportType !== 'student-list') return;

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
