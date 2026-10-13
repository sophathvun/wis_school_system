export function setupStaffProfileStatus(form) {
    const input = form.querySelector('[data-staff-profile-status-value]');
    const toggle = form.querySelector('[data-staff-profile-status]');
    if (!input || !toggle) return;
    const original = input.value;
    const sync = () => {
        const active = input.value === '1';
        toggle.classList.toggle('is-active',active);
        toggle.querySelector('.status-toggle-label').textContent = active ? 'ON' : 'OFF';
        toggle.setAttribute('aria-pressed',String(active));
        toggle.setAttribute('aria-label',`Profile Status: ${active ? 'Active' : 'Inactive'}`);
    };
    toggle.addEventListener('click',() => {
        input.value = input.value === '1' ? '0' : '1';
        input.dispatchEvent(new Event('change',{bubbles:true}));
    });
    input.addEventListener('change',sync);
    form.addEventListener('reset',() => window.setTimeout(() => { input.value = original;sync(); },0));
    sync();
}
