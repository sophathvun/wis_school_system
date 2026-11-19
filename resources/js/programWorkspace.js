const dirtyProgramForms = new Set();

for (const eventName of ['input', 'change']) {
    document.addEventListener(eventName, event => {
        const form = event.target.closest?.('form');
        if (form && !form.matches('[data-program-switch-form]')) dirtyProgramForms.add(form);
    });
}
document.addEventListener('reset', event => dirtyProgramForms.delete(event.target));
document.addEventListener('hidden.bs.modal', event => {
    event.target.querySelectorAll('form').forEach(form => dirtyProgramForms.delete(form));
});
document.addEventListener('submit', event => {
    if (!event.target.matches('[data-program-switch-form]')) return;
    const hasUnsavedChanges = [...dirtyProgramForms].some(form => form.isConnected);
    if (hasUnsavedChanges && !window.confirm('You have unsaved changes. Cancel to save your work, or choose OK to discard the changes and switch programs.')) {
        event.preventDefault();
    }
});
