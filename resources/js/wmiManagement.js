import {setupWmiNavigation} from './helpers/wmiNavigation.js';
import {setupWmiYears} from './helpers/wmiYears.js';
import {setupWmiExamCenters} from './helpers/wmiExamCenters.js';
import {setupWmiSchools} from './helpers/wmiSchools.js';
import {setupWmiRegistrations} from './helpers/wmiRegistrations.js';
import {setupWmiRegistrationTools} from './helpers/wmiRegistrationTools.js';
const workspace=document.querySelector('[data-wmi-workspace]');
if(workspace)setupWmiNavigation(workspace,window.matchMedia('(min-width: 768px)'));
setupWmiYears();
setupWmiExamCenters();
setupWmiSchools();
setupWmiRegistrations();
setupWmiRegistrationTools();

// Synchronize programmatically filled edit forms as well as typing and resets.
document.querySelectorAll('.wmi-year-modal').forEach(form=>{
    const sync=()=>form.querySelectorAll('.wmi-modal-field').forEach(field=>{
        const control=field.querySelector('input,textarea,select');
        field.classList.toggle('wmi-has-value',Boolean(control?.value?.trim()));
    });
    form.addEventListener('input',sync);
    form.addEventListener('change',sync);
    form.addEventListener('reset',()=>queueMicrotask(sync));
    form.closest('.modal').addEventListener('show.bs.modal',sync);
    sync();
});
