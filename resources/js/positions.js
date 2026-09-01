document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('positionModal');
    const name = modal?.querySelector('input[name="name"]');
    const code = modal?.querySelector('input[name="code"]');
    const department = modal?.querySelector('select[name="department_id"]');
    const fields = [name, code, department].map(control => control?.closest('.position-field')).filter(Boolean);

    fields.forEach(field => {
        const control = field.querySelector('input,select');
        const sync = () => field.classList.toggle('has-value', Boolean(control.value));
        control.addEventListener('input', sync);
        control.addEventListener('change', sync);
        sync();
    });

    if (department) {
        const field = department.closest('.position-field');
        const combo = document.createElement('div');
        combo.className = 'position-combobox';
        combo.innerHTML = '<button type="button" class="position-combobox-toggle"><span></span><i class="ti ti-chevron-down"></i></button><div class="position-combobox-menu d-none"><input type="search" class="form-control" placeholder="Search Department"><div class="position-combobox-results"></div></div>';
        department.classList.add('d-none');
        department.after(combo);

        const button = combo.querySelector('button');
        const menu = combo.querySelector('.position-combobox-menu');
        const search = combo.querySelector('input');
        const selected = combo.querySelector('span');
        const results = combo.querySelector('.position-combobox-results');

        const sync = () => {
            selected.textContent = department.value ? department.selectedOptions[0].textContent : '';
            field.classList.toggle('has-value', Boolean(department.value));
        };

        const render = () => {
            const term = search.value.toLowerCase().trim();
            const options = [...department.options].filter(option => option.value && (!term || option.textContent.toLowerCase().includes(term)));
            results.innerHTML = options.length ? options.map(option => `<button type="button" class="position-combobox-option" data-value="${option.value}">${option.textContent}</button>`).join('') : '<div class="text-secondary px-2 py-2">No departments found</div>';
        };

        button.addEventListener('click', () => {
            menu.classList.toggle('d-none');
            if (!menu.classList.contains('d-none')) {
                search.value = '';
                render();
                search.focus();
            }
        });

        search.addEventListener('input', render);
        results.addEventListener('click', event => {
            const option = event.target.closest('[data-value]');
            if (!option) return;
            department.value = option.dataset.value;
            department.dispatchEvent(new Event('change', {
                bubbles: true
            }));
            menu.classList.add('d-none');
            sync();
        });
        document.addEventListener('click', event => {
            if (!combo.contains(event.target)) menu.classList.add('d-none');
        });
        sync();
        render();
    }

    const status = modal?.querySelector('select[name="status"]');
    const toggle = modal?.querySelector('#positionStatusToggle');
    toggle?.addEventListener('click', () => {
        status.value = status.value === '1' ? '0' : '1';
        const active = status.value === '1';
        toggle.classList.toggle('is-active', active);
        toggle.querySelector('.status-toggle-label').textContent = active ? 'ON' : 'OFF';
        toggle.setAttribute('aria-pressed', active ? 'true' : 'false');
    });

    document.getElementById('btnNewPosition')?.addEventListener('click', () => {
        if (modal) bootstrap.Modal.getOrCreateInstance(modal).show();
    });

    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(element => new bootstrap.Tooltip(element));
    document.querySelectorAll('[data-position-delete-trigger]').forEach((button) => {
        button.addEventListener('click', () => {
            button.closest('span')?.querySelector('form')?.requestSubmit();
        });
    });

    if (modal?.dataset.autoOpen === 'true') {
        bootstrap.Modal.getOrCreateInstance(modal).show();
    }
});
