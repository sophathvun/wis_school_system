document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('departmentModal');
    if (!modal) return;

    modal.querySelectorAll('.department-field').forEach((field) => {
        const control = field.querySelector('input');
        if (!control) return;
        const sync = () => field.classList.toggle('has-value', Boolean(control.value));
        control.addEventListener('input', sync);
        sync();
    });

    const status = modal.querySelector('select[name="status"]');
    const toggle = modal.querySelector('#departmentStatusToggle');
    toggle?.addEventListener('click', () => {
        status.value = status.value === '1' ? '0' : '1';
        const active = status.value === '1';
        toggle.classList.toggle('is-active', active);
        toggle.querySelector('.status-toggle-label').textContent = active ? 'ON' : 'OFF';
        toggle.setAttribute('aria-pressed', active ? 'true' : 'false');
    });

    document.getElementById('btnNewDepartment')?.addEventListener('click', () => {
        bootstrap.Modal.getOrCreateInstance(modal).show();
    });
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => {
        bootstrap.Tooltip.getOrCreateInstance(element);
    });
    document.querySelectorAll('[data-department-delete-trigger]').forEach((button) => {
        button.addEventListener('click', () => {
            button.closest('span')?.querySelector('form')?.requestSubmit();
        });
    });
    if (modal.dataset.autoOpen === 'true') bootstrap.Modal.getOrCreateInstance(modal).show();
});
