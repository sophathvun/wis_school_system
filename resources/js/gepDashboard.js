import {createSeatPicker} from './scholarshipSeatPicker.js';
const form=document.querySelector('[data-gep-dashboard-filters]');
if(form){
    form.querySelectorAll('select').forEach(select=>createSeatPicker(select));
    form.elements.academic_year_id.addEventListener('change',()=>{
        form.elements.term_assignment_id.value='';
        form.requestSubmit();
    });
    form.addEventListener('submit',()=>{
        form.setAttribute('aria-busy','true');
        const button=form.querySelector('button[type="submit"]');button.disabled=true;button.textContent='Updating overview…';
    });
    window.addEventListener('pageshow',()=>{form.removeAttribute('aria-busy');const button=form.querySelector('button[type="submit"]');button.disabled=false;button.innerHTML='<i class="ti ti-refresh me-2"></i>Update overview';});
}
