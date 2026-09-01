document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('documentTypeModal')?.addEventListener('show.bs.modal', event => {
        const data = event.relatedTarget?.dataset.type ? JSON.parse(event.relatedTarget.dataset.type) : null;
        const active = data ? Boolean(data.status) : true;

        document.getElementById('document_type_id').value = data?.id || '';
        document.getElementById('document_type_key').value = data?.type_key || '';
        document.getElementById('document_type_en').value = data?.name_en || '';
        document.getElementById('document_type_kh').value = data?.name_kh || '';
        document.getElementById('document_type_order').value = data?.sort_order || 0;
        document.getElementById('document_type_status').value = active ? 1 : 0;

        const toggle = document.getElementById('document_type_status_toggle');
        const label = toggle?.querySelector('.status-toggle-label');

        toggle?.classList.toggle('is-active', active);
        toggle?.setAttribute('aria-pressed', active ? 'true' : 'false');
        if (label) {
            label.textContent = active ? 'ON' : 'OFF';
        }
    });

    document.getElementById('document_type_status_toggle')?.addEventListener('click', event => {
        const toggle = event.currentTarget;
        const input = document.getElementById('document_type_status');
        const active = !toggle.classList.contains('is-active');

        toggle.classList.toggle('is-active', active);
        toggle.setAttribute('aria-pressed', active ? 'true' : 'false');
        toggle.querySelector('.status-toggle-label').textContent = active ? 'ON' : 'OFF';
        input.value = active ? 1 : 0;
    });
});
