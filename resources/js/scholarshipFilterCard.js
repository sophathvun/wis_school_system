import { createSeatPicker } from './scholarshipSeatPicker.js';

/** Shared filter appearance, searchable pickers and responsive collapse behavior. */
export function initScholarshipFilterCard({card,body,toggle,selects,label,ready='resultFiltersReady'}) {
    const phone=matchMedia('(max-width: 767.98px)');
    const pickers=new Map([...selects].map(select=>[select,createSeatPicker(select)]));
    const expand=expanded=>{
        if(!expanded){
            pickers.forEach(picker=>picker.close());
            if(body.contains(document.activeElement))toggle.focus();
        }
        body.hidden=!expanded;
        toggle.setAttribute('aria-expanded',String(expanded));
        toggle.setAttribute('aria-label',`${expanded?'Collapse':'Expand'} ${label}`);
        toggle.querySelector('i').className=expanded?'ti ti-chevron-up':'ti ti-chevron-down';
        card.dataset[ready]='true';
    };
    expand(!phone.matches);
    toggle.addEventListener('click',()=>expand(body.hidden));
    phone.addEventListener('change',()=>expand(!phone.matches));
    return pickers;
}
