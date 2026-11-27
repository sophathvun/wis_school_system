import {createSeatPicker} from './scholarshipSeatPicker.js';
const form=document.querySelector('[data-gep-dashboard-filters]');
if(form){
    form.querySelectorAll('select').forEach(select=>createSeatPicker(select));
    form.addEventListener('change',event=>{
        if(!event.target.matches('select[name]'))return;
        if(event.target.name==='academic_year_id')form.elements.term_assignment_id.value='';
        form.requestSubmit();
    });
    form.addEventListener('submit',()=>{
        form.setAttribute('aria-busy','true');
    });
    window.addEventListener('pageshow',()=>form.removeAttribute('aria-busy'));
}
