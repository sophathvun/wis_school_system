export function setupStaffIdentification(form) {
    const card = form.querySelector('[data-staff-identification]');
    if (!card) return;
    const type = form.elements.namedItem('identity_document_type');
    const number = form.elements.namedItem('identity_document_number');
    const issue = form.elements.namedItem('identity_document_issued_on');
    const expiry = form.elements.namedItem('identity_document_expires_on');
    const expiryPicker = expiry.closest('[data-premium-date-picker]');
    const sync = () => {
        card.dataset.documentType = type.value;
        card.querySelector(`label[for="${number.id}"]`).textContent = {national_id:'ID Number',passport:'Passport Number'}[type.value] || 'Document Number';
        card.querySelectorAll('.staff-identity-name,.staff-identity-details').forEach(wrapper => {
            const visible = wrapper.classList.contains('staff-identity-name') ? type.value === 'other' : Boolean(type.value);
            wrapper.hidden = !visible;
            if (!visible) wrapper.querySelector('[data-premium-date-picker].is-open [data-premium-date-toggle]')?.click();
            wrapper.querySelectorAll('input,[data-premium-date-toggle]').forEach(input => { input.disabled = !visible; });
        });
        if (expiryPicker.dataset.premiumDateMin !== issue.value) {
            expiryPicker.dataset.premiumDateMin = issue.value;
            expiryPicker.dispatchEvent(new Event('premium-date-range-change'));
        }
    };
    type.addEventListener('change',sync);
    issue.addEventListener('input',sync);
    issue.addEventListener('change',sync);
    issue.closest('[data-premium-date-picker]').querySelector('[data-premium-date-input]').addEventListener('input',sync);
    form.addEventListener('reset',() => window.setTimeout(sync,0));
    sync();
}
