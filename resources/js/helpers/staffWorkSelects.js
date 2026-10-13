import { setupStaffLocationSelect } from './staffBirthplaceSelect';

export function setupStaffWorkSelects(form) {
    form.querySelectorAll('[data-staff-work-select]').forEach(select => {
        for (const option of select.options) {
            if (option.value) option.dataset.en = option.textContent.trim();
        }
        const label = select.closest('.staff-form-field').querySelector('.form-label').textContent.trim();
        const ui = setupStaffLocationSelect(form,select,{
            labelId:select.id+'-label',optionsId:select.id+'-options',locationLabel:label,
        });
        ui.toggle.closest('.location-combobox').classList.add('staff-work-select-combobox');
    });
    const position = form.elements.namedItem('position_id');
    const department = form.elements.namedItem('department_id');
    position?.addEventListener('change',() => {
        const selected = position.selectedOptions[0];
        // Preserve saved departments on load/reset and standalone department
        // choices when Position is cleared. Autofill on a position selection.
        if (!department || !selected?.value || !selected.hasAttribute('data-department')) return;
        const departmentId = selected.dataset.department;
        if (![...department.options].some(option => option.value === departmentId)) return;
        department.value = departmentId;
        department.dispatchEvent(new Event('input',{bubbles:true}));
        department.dispatchEvent(new Event('change',{bubbles:true}));
    });
}
