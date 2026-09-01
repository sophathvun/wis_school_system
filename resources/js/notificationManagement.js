document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-auto-submit]').forEach(select => {
        select.addEventListener('change', () => {
            select.form?.submit();
        });
    });

    const modal = document.getElementById('notificationEditModal');
    if (modal?.dataset.autoOpen === 'true') {
        bootstrap.Modal.getOrCreateInstance(modal).show();
    }
});
