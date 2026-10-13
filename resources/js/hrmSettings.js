import { showConfirm, showSuccess } from './helpers/sweet-alert2';
import { Modal, Tooltip } from 'bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    const settings = document.querySelector('[data-hrm-settings]');
    if (!settings) return;
    settings.querySelector('[data-staff-csv-import]')?.addEventListener('submit',event => {
        const form=event.currentTarget;
        if (!form.checkValidity()) return;
        const button=form.querySelector('[data-staff-import-submit]');
        button.disabled=true;
        button.querySelector('span').textContent='Importing…';
        form.querySelector('[data-staff-import-progress]').hidden=false;
    });
    const tabsCard = settings.querySelector('.hrm-settings-navigation');
    const tabsToggle = tabsCard?.querySelector('[data-hrm-tabs-toggle]');
    if (tabsToggle) {
        const mobile = window.matchMedia('(max-width: 991.98px)');
        let expanded = false;
        const tooltips = [tabsToggle, ...tabsCard.querySelectorAll('[data-hrm-tab-label]')].map(element => {
            const tooltip = Tooltip.getOrCreateInstance(element, {
                container: 'body', placement: 'right', customClass: 'hrm-settings-tabs-tooltip',
                delay: { show: 200, hide: 0 },
                title: () => element === tabsToggle ? element.getAttribute('aria-label') : element.dataset.hrmTabLabel,
            });
            element.addEventListener('click', () => tooltip.hide());
            return tooltip;
        });
        const syncTabs = () => {
            settings.classList.toggle('hrm-settings-tabs-collapsed', !expanded);
            tabsToggle.setAttribute('aria-expanded', String(expanded));
            tabsToggle.setAttribute('aria-label', `${expanded ? 'Collapse' : 'Expand'} HRM settings tabs`);
            tabsToggle.querySelector('i').className = `ti ti-layout-sidebar-left-${expanded ? 'collapse' : 'expand'}`;
            tooltips.forEach(tooltip => {
                tooltip.hide();
                if (!expanded && !mobile.matches) tooltip.enable();
                else tooltip.disable();
            });
        };
        syncTabs();
        tabsToggle.addEventListener('click', () => { expanded = !expanded; syncTabs(); });
        document.addEventListener('click', event => {
            if (mobile.matches && expanded && !tabsCard.contains(event.target)) { expanded = false; syncTabs(); }
        });
        mobile.addEventListener('change', () => { expanded = false; syncTabs(); });
    }
    const settingModal = document.querySelector('.hrm-setting-modal');
    if (settingModal) {
        const form = settingModal.querySelector('[data-hrm-setting-form]');
        const entityLabel = settingModal.dataset.entityLabel;
        settings.querySelector('[data-hrm-setting-new]')?.addEventListener('click', () => {
            form.reset();
            [settingModal.dataset.idField, 'name', 'code', 'parent_id', 'department_id'].forEach(name => {
                const input = form.elements.namedItem(name);
                if (input) input.value = '';
            });
            form.elements.namedItem('status').value = '1';
            const parent = form.elements.namedItem('parent_id');
            if (parent) [...parent.options].forEach(option => { option.disabled = false; });
            form.querySelectorAll('[data-hrm-edit-only]').forEach(option => option.remove());
            form.querySelectorAll('.is-invalid').forEach(input => { input.classList.remove('is-invalid'); input.removeAttribute('aria-invalid'); });
            const errors = form.querySelector('[data-hrm-setting-errors]');
            if (errors) errors.hidden = true;
            form.querySelectorAll('input:not([type="hidden"]),select').forEach(input => input.dispatchEvent(new Event('input', { bubbles: true })));
            settingModal.querySelector('.modal-title').textContent = `Create New ${entityLabel}`;
            form.querySelector('[data-hrm-setting-submit-label]').textContent = `Save ${entityLabel}`;
        });
        settingModal.addEventListener('shown.bs.modal', () => {
            (form.querySelector('.is-invalid') || form.elements.namedItem('name')).focus();
        });
        if (settingModal.dataset.openOnLoad === '1') Modal.getOrCreateInstance(settingModal).show();
    }
    const success = settings.querySelector('[data-hrm-settings-success]')?.dataset.hrmSettingsSuccess;
    if (success) showSuccess('Success', success);
    settings.querySelectorAll('[data-hrm-settings-delete]').forEach(form => {
        let pending = false;
        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (pending) return;
            pending = true;
            try {
                const choice = await showConfirm('Delete setting?', `Delete ${form.dataset.hrmSettingsDelete}?`, 'Delete');
                if (choice.isConfirmed) HTMLFormElement.prototype.submit.call(form);
            } finally { pending = false; }
        });
    });
    const searchForm = settings.querySelector('[data-hrm-settings-search]');
    const search = searchForm?.querySelector('input[type="search"]');
    let timer;
    let composing = false;
    const scheduleSearch = () => {
        clearTimeout(timer);
        if (!composing) timer = setTimeout(() => searchForm.requestSubmit(), 650);
    };
    search?.addEventListener('input', scheduleSearch);
    search?.addEventListener('compositionstart', () => { composing = true; clearTimeout(timer); });
    search?.addEventListener('compositionend', () => { composing = false; scheduleSearch(); });
    searchForm?.addEventListener('submit', () => clearTimeout(timer));
});
