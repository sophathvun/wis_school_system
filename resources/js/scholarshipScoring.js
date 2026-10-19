import { createSeatPicker } from './scholarshipSeatPicker.js';

export function initScoring(root) {
    const card=root.querySelector('[data-scoring-filters-card]');
    if(!card)return;
    const form=card.querySelector('[data-scoring-filters]');
    const body=card.querySelector('#scholarshipScoringFilters');
    const toggle=card.querySelector('[data-scoring-filters-toggle]');
    const phone=matchMedia('(max-width: 767.98px)');
    const pickers=new Map([...form.querySelectorAll('[data-score-picker]')].map(select=>[select,createSeatPicker(select)]));
    const expand=expanded=>{
        if(!expanded){
            pickers.forEach(picker=>picker.close());
            if(body.contains(document.activeElement))toggle.focus();
        }
        body.hidden=!expanded;
        toggle.setAttribute('aria-expanded',String(expanded));
        toggle.setAttribute('aria-label',expanded?'Collapse scoring filters':'Expand scoring filters');
        toggle.querySelector('i').className=expanded?'ti ti-chevron-up':'ti ti-chevron-down';
        card.dataset.scoringFiltersReady='true';
    };
    expand(!phone.matches);
    toggle.addEventListener('click',()=>expand(body.hidden));
    phone.addEventListener('change',()=>expand(!phone.matches));
    const {academic_year_id:year,score_center:center,score_room:room,score_student:student}=form.elements;
    const clear=select=>{select.value='';select.disabled=true;pickers.get(select)?.refresh();};
    year.addEventListener('change',()=>{clear(center);clear(room);clear(student);});
    center.addEventListener('change',()=>{clear(room);clear(student);});
    room.addEventListener('change',()=>clear(student));
    form.addEventListener('change',()=>form.requestSubmit());
    root.querySelectorAll('.scholarship-scoring-table thead').forEach(heading=>{
        const viewport=heading.closest('.scholarship-scoring-overflow');
        const measure=()=>viewport.style.setProperty('--scoring-header-height',`${heading.getBoundingClientRect().height}px`);
        new ResizeObserver(measure).observe(heading);measure();
    });
    root.querySelector('#scholarshipScoreDrafts')?.addEventListener('formdata',event=>{
        const entries=[...event.formData.entries()];
        if(entries.length<900)return;
        // Large rooms can exceed PHP's input-variable limit; keep the same rows in one JSON field.
        const rows={};
        entries.forEach(([name,value])=>{
            const match=/^rows\[(\d+)\]\[(version|absent|scores)\](?:\[(\d+)\])?$/.exec(name);
            if(!match)return;
            const [,id,field,subjectId]=match;rows[id]??={};
            if(field==='scores'){rows[id].scores??={};rows[id].scores[subjectId]=value;}
            else rows[id][field]=value;
            event.formData.delete(name);
        });
        event.formData.set('score_drafts',JSON.stringify(rows));
    });
    const subject=root.querySelector('[data-scoring-subject]');
    if(subject){
        const picker=createSeatPicker(subject);
        pickers.set(subject,picker);
        const lock=root.querySelector('[data-lock-score-inputs]');
        const inputs=[...root.querySelectorAll('[data-score-input]')];
        const focusInput=input=>{input.focus();input.select();input.scrollIntoView({block:'nearest',inline:'nearest'});};
        const activate=()=>{
            inputs.forEach(input=>{
                const editable=input.dataset.canScore==='true'&&!input.disabled&&subject.value!==''&&input.dataset.scoreInput===subject.value;
                input.readOnly=!editable;input.tabIndex=editable?0:-1;
                input.setAttribute('aria-readonly',String(!editable));
                input.classList.toggle('is-scoring',editable);
            });
            root.querySelectorAll('[data-score-subject-heading]').forEach(heading=>heading.classList.toggle('is-scoring',heading.dataset.scoreSubjectHeading===subject.value));
            lock.disabled=subject.value==='';
        };
        subject.addEventListener('change',()=>{
            activate();
            requestAnimationFrame(()=>{const first=inputs.find(input=>!input.readOnly&&!input.disabled);if(first)focusInput(first);});
        });
        lock.addEventListener('click',()=>{subject.value='';picker.refresh();activate();picker.button.focus();});
        root.addEventListener('keydown',event=>{
            const input=event.target.closest('[data-score-input]');
            if(!input||input.readOnly||input.disabled||!['Tab','Enter'].includes(event.key)||event.ctrlKey||event.metaKey||event.altKey)return;
            const active=inputs.filter(field=>!field.readOnly&&!field.disabled);
            const next=active[active.indexOf(input)+(event.shiftKey?-1:1)];
            event.preventDefault();
            if(next)focusInput(next);
            else if(event.shiftKey)picker.button.focus();
            else root.querySelector('[data-save-score-drafts]')?.focus();
        });
        activate();
    }
    const reviewForm=root.querySelector('[data-score-review-form]');
    if(reviewForm){
        const draftForm=root.querySelector('#scholarshipScoreDrafts');
        const button=reviewForm.querySelector('[data-review-scores]');
        const hint=root.querySelector('[data-unsaved-score-review]');
        const ready=!button.disabled;
        let submitting=false;
        const dirty=()=>draftForm?.dataset.unsavedRestored==='true'||[...(draftForm?.elements||[])].some(input=>!input.disabled && (input.type==='checkbox'?input.checked!==input.defaultChecked:input.type==='number'&&input.value!==input.defaultValue));
        const sync=()=>{const changed=dirty();button.disabled=!ready||changed||submitting;hint.hidden=!changed;};
        root.addEventListener('input',sync);root.addEventListener('change',sync);sync();
        reviewForm.addEventListener('submit',event=>{
            if(!ready||dirty()||submitting||!reviewForm.reportValidity()){event.preventDefault();return;}
            submitting=true;sync();
        });
    }
}
