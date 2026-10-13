import { setupStaffLocationSelect } from './staffBirthplaceSelect';

export function setupStaffWorkingCampuses(form) {
    const select = form.querySelector('[data-staff-working-campuses]');
    const primary = form.elements.namedItem('primary_campus_id');
    if (!select || !primary) return;
    const ui = setupStaffLocationSelect(form,select,{
        labelId:'staffWorkingCampusesLabel',optionsId:'staffWorkingCampusesOptions',locationLabel:'Working Campuses',
    });
    ui.toggle.closest('.location-combobox').classList.add('staff-working-campuses-combobox');
    const syncPrimary = () => {
        for (const option of select.options) {
            const isPrimary = Boolean(primary.value) && option.value === primary.value;
            option.dataset.locked = String(isPrimary);
            if (isPrimary) option.selected = true;
        }
        select.dispatchEvent(new Event('change',{bubbles:true}));
    };
    primary.addEventListener('change',syncPrimary);
    form.addEventListener('reset',() => window.setTimeout(() => { syncPrimary();ui.close(); },0));
    syncPrimary();
}
