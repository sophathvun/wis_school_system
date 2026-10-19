import { createSeatPicker } from './scholarshipSeatPicker.js';

export function initResultFilters(root) {
    const card=root.querySelector('[data-result-filters-card]');
    if(!card)return;
    const form=card.querySelector('[data-result-filters]');
    const body=card.querySelector('#scholarshipResultFilters');
    const toggle=card.querySelector('[data-result-filters-toggle]');
    const phone=matchMedia('(max-width: 767.98px)');
    const pickers=new Map([...form.querySelectorAll('[data-result-picker]')].map(select=>[select,createSeatPicker(select)]));
    document.addEventListener('scholarship:refresh-result-filters',()=>pickers.forEach(picker=>picker.refresh()));
    const expand=expanded=>{
        if(!expanded){
            pickers.forEach(picker=>picker.close());
            if(body.contains(document.activeElement))toggle.focus();
        }
        body.hidden=!expanded;
        toggle.setAttribute('aria-expanded',String(expanded));
        toggle.setAttribute('aria-label',expanded?'Collapse result filters':'Expand result filters');
        toggle.querySelector('i').className=expanded?'ti ti-chevron-up':'ti ti-chevron-down';
        card.dataset.resultFiltersReady='true';
    };
    expand(!phone.matches);
    toggle.addEventListener('click',()=>expand(body.hidden));
    phone.addEventListener('change',()=>expand(!phone.matches));
    const {academic_year_id:year,result_center:center,result_grade:grade,result_student:student}=form.elements;
    const clear=select=>{select.value='';select.disabled=true;pickers.get(select)?.refresh();};
    year.addEventListener('change',()=>{clear(center);clear(grade);clear(student);});
    center.addEventListener('change',()=>{clear(grade);clear(student);});
    grade.addEventListener('change',()=>clear(student));
    form.addEventListener('change',()=>form.requestSubmit());
}
