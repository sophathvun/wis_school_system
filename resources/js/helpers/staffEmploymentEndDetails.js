export function setupStaffEmploymentEndDetails(form) {
    const panel = form.querySelector('#staff-work-tab');
    const status = form.elements.namedItem('employment_status');
    const hire = form.elements.namedItem('joined_on');
    const end = form.elements.namedItem('ended_on');
    const picker = end?.closest('[data-premium-date-picker]');
    if (!panel || !status || !picker) return;
    const sync = () => {
        const visible = status.value !== 'active';
        panel.dataset.employmentStatus = status.value;
        panel.querySelectorAll('.staff-employment-end-date-field,.staff-employment-end-reason-field').forEach(wrapper => {
            if (!visible) wrapper.querySelector('[data-premium-date-picker].is-open [data-premium-date-toggle]')?.click();
            wrapper.hidden = !visible;
            wrapper.querySelectorAll('input,textarea,[data-premium-date-toggle]').forEach(input => { input.disabled = !visible; });
        });
        const minimum = hire?.value || '';
        if (picker.dataset.premiumDateMin !== minimum) {
            picker.dataset.premiumDateMin = minimum;
            picker.dispatchEvent(new Event('premium-date-range-change'));
        }
    };
    status.addEventListener('change',sync);
    hire?.addEventListener('change',sync);
    hire?.closest('[data-premium-date-picker]')?.querySelector('[data-premium-date-input]')?.addEventListener('input',sync);
    form.addEventListener('reset',() => window.setTimeout(sync,0));
    sync();
}
