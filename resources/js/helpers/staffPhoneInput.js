import intlTelInput from 'intl-tel-input';
import 'intl-tel-input/styles';

const formatCambodia = value => {
    let digits = String(value || '').replace(/\D/g,'');
    if (digits.startsWith('00855')) digits = digits.slice(5);
    else if (digits.startsWith('855') && (String(value).trim().startsWith('+') || digits.length > 9)) digits = digits.slice(3);
    if (digits.startsWith('0')) digits = digits.slice(1);
    digits = digits.slice(0,9);
    if (digits.length <= 2) return digits;
    if (digits.length <= 5) return `${digits.slice(0,2)} ${digits.slice(2)}`;
    return `${digits.slice(0,2)} ${digits.slice(2,5)} ${digits.slice(5)}`;
};

export function setupStaffPhoneInputs(form) {
    const inputs = [...form.querySelectorAll('[data-staff-phone]')].map(group => {
        const visible = group.querySelector('[data-staff-phone-number]');
        const hidden = group.querySelector('[data-staff-phone-value]');
        const original = hidden.value;
        const intl = intlTelInput(visible, {
            initialCountry:'kh',
            separateDialCode:true,
            countrySelectorMode:'DROPDOWN',
            dropdownParent:form.closest('.modal'),
            strictMode:false,
            formatAsYouType:true,
            loadUtils:() => import('intl-tel-input/utils'),
        });
        const fallbackNumber = () => {
            const raw = visible.value.trim();
            let digits = raw.replace(/\D/g,'');
            if (!digits) return '';
            if (raw.startsWith('+')) return `+${digits}`;
            if (digits.startsWith('00')) return `+${digits.slice(2)}`;
            const country = intl.getSelectedCountry() || {dialCode:'855'};
            if (country.nationalPrefix && digits.startsWith(country.nationalPrefix)) digits = digits.slice(country.nationalPrefix.length);
            return digits ? `+${country.dialCode || '855'}${digits}` : '';
        };
        const sync = () => {
            if (intl.getSelectedCountry()?.iso2 === 'kh') {
                const value = visible.value;
                const formatted = formatCambodia(value);
                if (value !== formatted) {
                    const start = visible.selectionStart;
                    const end = visible.selectionEnd;
                    visible.value = formatted;
                    // Keep editing in the same place when spaces are inserted or removed.
                    if (document.activeElement === visible && start !== null && end !== null) {
                        visible.setSelectionRange(formatCambodia(value.slice(0,start)).length,formatCambodia(value.slice(0,end)).length);
                    }
                }
            }
            hidden.value = visible.value.trim() ? ((intlTelInput.utils && intl.getNumber()) || fallbackNumber()) : '';
            hidden.dispatchEvent(new Event('input',{bubbles:true}));
            group.closest('.staff-form-field')?.classList.toggle('has-value',Boolean(visible.value.trim()));
        };
        if (original) intl.setNumber(original.startsWith('+') ? original : `+855${formatCambodia(original).replace(/\D/g,'')}`);
        sync();
        intl.promise.then(sync).catch(() => sync());
        visible.addEventListener('input',sync);
        visible.addEventListener('countrychange',sync);
        visible.addEventListener('blur',sync);
        visible.addEventListener('open:countryselector', () => {
            form.closest('.modal')?.querySelectorAll('.iti__search-input').forEach(search => { search.dataset.searchNormalized='true'; });
        });
        form.closest('.modal')?.addEventListener('hide.bs.modal',() => intl.closeCountrySelector());
        return { sync, reset:() => { intl.setSelectedCountry('kh'); intl.setNumber(original.startsWith('+') ? original : original ? `+855${formatCambodia(original).replace(/\D/g,'')}` : ''); sync(); } };
    });
    form.addEventListener('submit',() => inputs.forEach(input => input.sync()));
    form.addEventListener('reset',() => window.setTimeout(() => inputs.forEach(input => input.reset()),0));
}
