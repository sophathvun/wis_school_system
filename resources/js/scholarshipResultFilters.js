import { initScholarshipFilterCard } from './scholarshipFilterCard.js';

export function initResultFilters(root) {
    const card=root.querySelector('[data-result-filters-card]');
    if(!card)return;
    const form=card.querySelector('[data-result-filters]');
    const body=card.querySelector('#scholarshipResultFilters');
    const toggle=card.querySelector('[data-result-filters-toggle]');
    const pickers=initScholarshipFilterCard({card,body,toggle,selects:form.querySelectorAll('[data-result-picker]'),label:'result filters'});
    document.addEventListener('scholarship:refresh-result-filters',()=>pickers.forEach(picker=>picker.refresh()));
    const {academic_year_id:year,result_center:center,result_grade:grade,result_student:student}=form.elements;
    const clear=select=>{select.value='';select.disabled=true;pickers.get(select)?.refresh();};
    year.addEventListener('change',()=>{clear(center);clear(grade);clear(student);});
    center.addEventListener('change',()=>{clear(grade);clear(student);});
    grade.addEventListener('change',()=>clear(student));
    form.addEventListener('change',()=>form.requestSubmit());
}
