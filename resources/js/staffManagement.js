import { Modal, Tab } from 'bootstrap';
import { toKhmerDigits, dateKhmer, nationalityNote } from './helpers/staffProfileFields';
import { showConfirm, showSuccess, showError } from './helpers/sweet-alert2';
import { setupStaffPhotoUpload } from './helpers/staffPhotoUpload';
import { setupStaffDirectoryPhotoViewer } from './helpers/staffDirectoryPhotoViewer';
import { setupStaffGenderSelect } from './helpers/staffGenderSelect';
import { setupStaffBirthplaceSelect, setupStaffNationalitySelect, setupStaffMaritalStatusSelect } from './helpers/staffBirthplaceSelect';
import { initPremiumDatePicker } from './premiumDatePicker';
import { setupStaffPhoneInputs } from './helpers/staffPhoneInput';
import { setupStaffAddress } from './helpers/staffAddress';
import { setupStaffAddressSizing } from './helpers/staffAddressSizing';
import { setupStaffIdentification } from './helpers/staffIdentification';
import { setupStaffWorkingCampuses } from './helpers/staffWorkingCampuses';
import { setupStaffProfileStatus } from './helpers/staffProfileStatus';
import { setupStaffEmploymentEndDetails } from './helpers/staffEmploymentEndDetails';
import { setupStaffWorkSelects } from './helpers/staffWorkSelects';

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('staffDirectoryPhotoModal')) setupStaffDirectoryPhotoViewer();
    const successMessage = document.querySelector('[data-staff-success]')?.dataset.staffSuccess;
    if (successMessage) showSuccess('Success', successMessage);
    const actionError = document.querySelector('[data-staff-action-error]')?.dataset.staffActionError;
    if (actionError) showError('Unable to delete staff', actionError);
    document.querySelectorAll('[data-staff-edit]').forEach(link => {
        let pending = false;
        link.addEventListener('click', async event => {
            if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            if (pending) return;
            pending = true;
            try {
                const result = await showConfirm('Edit staff?', `Open ${link.dataset.staffEdit}'s profile for editing?`, 'Edit');
                if (result.isConfirmed) window.location.assign(link.href);
            } finally { pending = false; }
        });
    });
    document.querySelectorAll('[data-staff-delete]').forEach(deleteForm => {
        let pending = false;
        deleteForm.addEventListener('submit', async event => {
            event.preventDefault();
            if (pending) return;
            pending = true;
            try {
                const result = await showConfirm('Delete staff?', `Delete ${deleteForm.dataset.staffDelete}'s staff profile?`, 'Delete');
                if (result.isConfirmed) HTMLFormElement.prototype.submit.call(deleteForm);
            } finally { pending = false; }
        });
    });
    const staffWrapper = document.querySelector('.page-wrapper:has(#staffDirectoryCard)');
    const navbars = [...document.querySelectorAll('.page > header.navbar,.page > aside.navbar')];
    const syncNavbarHeight = () => {
        const navbar = navbars.find(element => window.innerWidth >= 992 ? element.tagName==='HEADER' : element.tagName==='ASIDE');
        if (staffWrapper && navbar) staffWrapper.style.setProperty('--staff-navbar-height',`${navbar.getBoundingClientRect().height}px`);
    };
    syncNavbarHeight();
    if (staffWrapper && typeof ResizeObserver !== 'undefined') {
        const observer = new ResizeObserver(syncNavbarHeight);
        navbars.forEach(navbar => observer.observe(navbar));
    }
    window.addEventListener('resize',syncNavbarHeight);
    const pageBody = staffWrapper?.querySelector('.page-body');
    const backToTop = document.querySelector('[data-staff-back-to-top]');
    if (pageBody && backToTop) {
        const syncBackToTop = () => { backToTop.hidden = pageBody.scrollTop < 250; };
        pageBody.addEventListener('scroll', syncBackToTop, { passive: true });
        backToTop.addEventListener('click', () => {
            pageBody.scrollTo({ top: 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
        });
        syncBackToTop();
    }
    const filters = document.querySelector('[data-staff-filters]');
    if (filters) {
        const filterCard = document.getElementById('staffDataFilters');
        const filterToggle = filterCard?.querySelector('[data-staff-filters-toggle]');
        if (filterToggle) {
            const desktop = window.matchMedia('(min-width: 768px)');
            let expanded = desktop.matches;
            let manuallyToggled = false;
            const syncFilterPanel = () => {
                filterCard.classList.toggle('staff-filters-expanded', expanded);
                filters.hidden = !expanded;
                filterToggle.setAttribute('aria-expanded', String(expanded));
                filterToggle.setAttribute('aria-label', `${expanded ? 'Collapse' : 'Expand'} data filters`);
                filterToggle.querySelector('i').className = `ti ti-chevron-${expanded ? 'up' : 'down'}`;
            };
            syncFilterPanel();
            filterToggle.addEventListener('click', () => {
                manuallyToggled = true;
                expanded = !expanded;
                syncFilterPanel();
            });
            desktop.addEventListener('change', () => {
                if (!manuallyToggled) {
                    expanded = desktop.matches;
                    syncFilterPanel();
                }
            });
        }
        let timer;
        const submitFilters = () => { clearTimeout(timer); filters.requestSubmit(); };
        filters.querySelectorAll('select').forEach(select => select.addEventListener('change', submitFilters));
        const search = filters.querySelector('input[type="search"]');
        let composing = false;
        const scheduleSearch = () => { clearTimeout(timer); if (!composing) timer = setTimeout(submitFilters,650); };
        search?.addEventListener('input',scheduleSearch);
        search?.addEventListener('compositionstart',() => { composing = true; clearTimeout(timer); });
        search?.addEventListener('compositionend',() => { composing = false; scheduleSearch(); });
        filters.addEventListener('submit',() => clearTimeout(timer));
    }
    ['staffModal','staffViewModal'].forEach(id => {
        const modal = document.getElementById(id);
        if (modal?.dataset.openOnLoad === '1') Modal.getOrCreateInstance(modal).show();
    });
    const form = document.querySelector('[data-staff-profile-form]');
    if (!form) return;
    const field = name => form.elements.namedItem(name);
    ['name_en','emergency_contact_name'].forEach(name => {
        const input = field(name);
        let composing = false;
        const uppercase = () => {
            if (!input || composing) return;
            const value = input.value;
            const formatted = value.toUpperCase();
            if (value === formatted) return;
            const start = input.selectionStart;
            const end = input.selectionEnd;
            input.value = formatted;
            if (start !== null && end !== null) {
                input.setSelectionRange(value.slice(0,start).toUpperCase().length,value.slice(0,end).toUpperCase().length);
            }
        };
        input?.addEventListener('input',uppercase);
        input?.addEventListener('compositionstart',() => { composing = true; });
        input?.addEventListener('compositionend',() => { composing = false; uppercase(); });
        form.addEventListener('submit',uppercase);
    });
    const setValue = (name,value) => {
        const input = field(name);
        if (input && input.value !== value) {
            input.value = value;
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
    };
    const syncBilingual = () => {
        const birthDateKhmer = form.querySelector('[data-staff-birth-date-kh]');
        if (birthDateKhmer) birthDateKhmer.textContent = dateKhmer(field('date_of_birth')?.value);
        setValue('nationality_note',nationalityNote(field('nationality')?.value,field('nationality_kh')?.value));
        ['house_number','street'].forEach(name => setValue(name+'_kh',toKhmerDigits(field(name+'_en')?.value)));
    };
    ['gender','date_of_birth','marital_status','nationality','nationality_kh','house_number_en','street_en'].forEach(name => {
        field(name)?.addEventListener('input',syncBilingual);
        field(name)?.addEventListener('change',syncBilingual);
    });
    syncBilingual();
    form.addEventListener('reset', () => window.setTimeout(() => {
        form.querySelectorAll('[data-premium-date-picker]').forEach(picker => {
            picker.querySelector('[data-premium-date-input]')?.dispatchEvent(new Event('change',{bubbles:true}));
            if (picker.classList.contains('is-open')) picker.querySelector('[data-premium-date-toggle]')?.click();
        });
        syncBilingual();
    },0));
    setupStaffGenderSelect(form);
    setupStaffBirthplaceSelect(form);
    setupStaffNationalitySelect(form);
    setupStaffMaritalStatusSelect(form);
    setupStaffPhoneInputs(form);
    setupStaffAddress(form);
    setupStaffAddressSizing(form);
    form.querySelectorAll('[data-premium-date-picker]').forEach(picker => {
        initPremiumDatePicker(picker);
        picker.querySelector('[data-premium-date-input]')?.addEventListener('input',syncBilingual);
        picker.closest('.staff-form-field')?.classList.toggle('has-value',Boolean(picker.querySelector('[data-premium-date-value]')?.value));
    });
    setupStaffIdentification(form);
    setupStaffWorkSelects(form);
    setupStaffWorkingCampuses(form);
    setupStaffProfileStatus(form);
    setupStaffEmploymentEndDetails(form);
    const syncSchedule = () => {
        form.querySelectorAll('[data-staff-day]').forEach(row => {
            const period = row.querySelector('select').value;
            row.querySelectorAll('input[type="time"]').forEach(input => {
                const session = input.name.includes('session_1_') ? 1 : 2;
                input.disabled = period === 'off' || (period==='morning' && session===2) || (period==='afternoon' && session===1);
                input.placeholder = field(`session_${session}_${input.name.includes('_in]')?'in':'out'}`)?.value || '';
                const hint = input.parentElement.querySelector('[data-staff-time-hint]');
                if (hint) hint.textContent = !input.disabled && !input.value && input.placeholder ? `Default: ${input.placeholder}` : '';
            });
        });
    };
    form.querySelectorAll('[data-staff-day] select').forEach(select => select.addEventListener('change',syncSchedule));
    form.querySelectorAll('[data-staff-day] input').forEach(input => input.addEventListener('input',syncSchedule));
    ['session_1_in','session_1_out','session_2_in','session_2_out'].forEach(name => field(name)?.addEventListener('input',syncSchedule));
    syncSchedule();
    form.querySelectorAll('[data-staff-schedule-preset]').forEach(button => button.addEventListener('click',() => {
        const days = button.dataset.staffSchedulePreset.split(',');
        form.querySelectorAll('[data-staff-day]').forEach(row => {
            const select = row.querySelector('select');
            select.value = days.includes(row.dataset.staffDay) ? 'full_day' : 'off';
        });
        syncSchedule();
    }));
    setupStaffPhotoUpload(form);
    const revealField = input => {
        if (input?.matches('[data-premium-date-value]')) input=input.closest('[data-premium-date-picker]').querySelector('[data-premium-date-input]');
        if (input?.matches('[data-staff-profile-status-value]')) input=form.querySelector('[data-staff-profile-status]');
        if (input?.matches('[data-staff-phone-value]')) input=input.closest('[data-staff-phone]').querySelector('[data-staff-phone-number]');
        if (input?.matches('[data-staff-birth-province],[data-staff-birth-country],[data-staff-nationality],[data-staff-marital-status],[data-staff-address-level],[data-staff-working-campuses],[data-staff-work-select]')) input=input.parentElement.querySelector('.location-combobox-toggle');
        const panel = input?.closest('.tab-pane');
        const toggle = panel && form.querySelector(`[data-bs-target="#${panel.id}"]`);
        if (toggle) Tab.getOrCreateInstance(toggle).show();
        input?.focus();
    };
    form.querySelectorAll('[data-staff-error-name]').forEach(button => button.addEventListener('click',() => {
        const parts = button.dataset.staffErrorName.split('.');
        const name = parts[0] + parts.slice(1).map(part => `[${part}]`).join('');
        revealField(field(name) || (parts[0] === 'campuses' ? form.querySelector('[data-staff-working-campuses]') : null));
    }));
    const firstError = form.querySelector('[aria-invalid="true"]');
    if (firstError) revealField(firstError);
    const nextIndex = {};
    ['educations','experiences'].forEach(kind => {
        const container = form.querySelector(`[data-staff-repeat="${kind}"]`);
        const indexes = [...container.querySelectorAll('input[name]')].map(input => Number(input.name.match(/\[(\d+)\]/)?.[1] || 0));
        nextIndex[kind] = indexes.length ? Math.max(...indexes)+1 : 0;
        form.querySelector(`[data-staff-add="${kind}"]`)?.addEventListener('click', () => {
            if (container.querySelectorAll('[data-repeat-row]').length >= 50) return;
            const template = form.querySelector(`[data-staff-template="${kind}"]`);
            container.insertAdjacentHTML('beforeend',template.innerHTML.replaceAll('__INDEX__',String(nextIndex[kind]++)));
            container.lastElementChild.querySelector('input')?.focus();
            container.lastElementChild.querySelector('input')?.dispatchEvent(new Event('input',{bubbles:true}));
        });
    });
    form.addEventListener('click', event => event.target.closest('[data-staff-remove]')?.closest('[data-repeat-row]')?.remove());
    // Reveal the correct tab when native validation finds a required field in a hidden panel.
    form.addEventListener('invalid', event => {
        revealField(event.target);
    }, true);
});
