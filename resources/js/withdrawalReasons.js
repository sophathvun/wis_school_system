document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('reasonModal');

    modal?.addEventListener('show.bs.modal', event => {
        const data = event.relatedTarget?.dataset.reason ? JSON.parse(event.relatedTarget.dataset.reason) : null;

        document.getElementById('reason_id').value = data?.id || '';
        document.getElementById('reason_key').value = data?.reason_key || '';
        document.getElementById('reason_name_en').value = data?.name_en || '';
        document.getElementById('reason_name_kh').value = data?.name_kh || '';
        document.getElementById('reason_sort_order').value = data?.sort_order || 0;
        document.getElementById('reason_status').value = data?.status ? 1 : 0;
    });

    const paginationControls = document.querySelector('.premium-pagination-controls');
    if (paginationControls && !document.getElementById('withdrawal-reason-per-page')) {
        const perPage = new URL(window.location.href).searchParams.get('perPage') || '10';
        paginationControls.insertAdjacentHTML('afterbegin', `
            <label class="premium-pagination-select">
                <select class="form-select form-select-sm" aria-label="Entries per page" id="withdrawal-reason-per-page">
                    <option value="10"${perPage === '10' ? ' selected' : ''}>10 / page</option>
                    <option value="25"${perPage === '25' ? ' selected' : ''}>25 / page</option>
                    <option value="50"${perPage === '50' ? ' selected' : ''}>50 / page</option>
                    <option value="100"${perPage === '100' ? ' selected' : ''}>100 / page</option>
                </select>
            </label>
        `);
    }

    document.getElementById('withdrawal-reason-per-page')?.addEventListener('change', event => {
        const url = new URL(window.location.href);
        url.searchParams.set('page', '1');
        url.searchParams.set('perPage', event.currentTarget.value);
        window.location.href = url.toString();
    });

    document.getElementById('withdrawalReasonPageInput')?.addEventListener('change', event => {
        const url = new URL(window.location.href);
        url.searchParams.set('page', event.currentTarget.value || '1');
        window.location.href = url.toString();
    });

    document.querySelectorAll('[data-withdrawal-reason-delete-form]').forEach(form => {
        form.addEventListener('submit', async event => {
            if (form.dataset.confirmed === 'true') {
                return;
            }

            event.preventDefault();

            const confirmAction = window.schoolShowConfirm
                ? window.schoolShowConfirm(
                    'Deactivate Withdrawal Reason',
                    'Are you sure you want to deactivate this withdrawal reason?',
                    'Deactivate',
                    'Cancel',
                )
                : window.Swal?.fire({
                    title: 'Deactivate Withdrawal Reason',
                    text: 'Are you sure you want to deactivate this withdrawal reason?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Deactivate',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#d63939',
                });

            const result = confirmAction ? await confirmAction : {
                isConfirmed: confirm('Deactivate this withdrawal reason?'),
            };

            if (!result.isConfirmed) {
                return;
            }

            form.dataset.confirmed = 'true';
            form.submit();
        });
    });
});
