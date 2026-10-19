import { createSeatPicker } from './scholarshipSeatPicker.js';
import Swal from 'sweetalert2';

export function initScoring(root) {
    const fullscreenButton=root.querySelector('[data-scoring-fullscreen]');
    if(fullscreenButton){
        let fallback=false,busy=false;
        const active=()=>fallback||document.fullscreenElement===root;
        const sync=()=>{
            const expanded=active();
            root.classList.toggle('is-scoring-fullscreen',expanded);
            document.body.classList.toggle('scholarship-scoring-fullscreen-open',expanded);
            fullscreenButton.setAttribute('aria-pressed',String(expanded));
            fullscreenButton.querySelector('span').textContent=expanded?'Exit Full Screen':'Full Screen';
            fullscreenButton.querySelector('i').className=expanded?'ti ti-minimize me-2':'ti ti-maximize me-2';
        };
        const exit=async()=>{
            fallback=false;
            if(document.fullscreenElement===root)await document.exitFullscreen();
            sync();fullscreenButton.focus({preventScroll:true});
        };
        fullscreenButton.addEventListener('click',async()=>{
            if(busy)return;
            busy=true;
            try{
                if(active())await exit();
                else{
                    try{
                        if(!root.requestFullscreen||!document.fullscreenEnabled)throw new Error('Use expanded view');
                        await root.requestFullscreen();
                    }catch{fallback=true;}
                    sync();
                }
            }finally{busy=false;}
        });
        document.addEventListener('fullscreenchange',sync);
        document.addEventListener('keydown',event=>{
            if(event.key==='Escape'&&fallback){event.preventDefault();void exit();}
        });
        sync();
    }
    const card=root.querySelector('[data-scoring-filters-card]');
    if(!card)return;
    const form=card.querySelector('[data-scoring-filters]');
    const body=card.querySelector('#scholarshipScoringFilters');
    const toggle=card.querySelector('[data-scoring-filters-toggle]');
    const phone=matchMedia('(max-width: 767.98px)');
    const pickers=new Map([...form.querySelectorAll('[data-score-picker]')].map(select=>[select,createSeatPicker(select)]));
    const draftForm=root.querySelector('#scholarshipScoreDrafts');
    const hasUnsavedScores=()=>draftForm?.dataset.unsavedRestored==='true'||[...(draftForm?.elements||[])].some(input=>!input.disabled && (
        input.type==='checkbox'?input.checked!==input.defaultChecked:input.type==='number'&&(input.validity.badInput||input.value!==input.defaultValue)
    ));
    let leaveWarningOpen=false,savingDraft=false;
    const warnBeforeLeaving=async()=>{
        if(leaveWarningOpen || (Swal.isVisible()&&!Swal.getPopup()?.classList.contains('swal2-toast')))return;
        leaveWarningOpen=true;
        pickers.forEach(picker=>picker.close());
        try{
            const result=await Swal.fire({
                icon:'warning',title:'Unsaved Scores',text:'Save your score and attendance changes before leaving this list.',
                showCancelButton:true,confirmButtonText:'Save Draft',cancelButtonText:'Continue Editing',
                target:root.classList.contains('is-scoring-fullscreen')?root:document.body,
            });
            if(result.isConfirmed)draftForm.requestSubmit();
        }finally{leaveWarningOpen=false;}
    };
    const originalFilters=new Map([...form.querySelectorAll('select')].map(select=>[select,{value:select.value,disabled:select.disabled}]));
    form.addEventListener('change',event=>{
        if(!hasUnsavedScores())return;
        event.preventDefault();event.stopImmediatePropagation();
        originalFilters.forEach((state,select)=>{select.value=state.value;select.disabled=state.disabled;pickers.get(select)?.refresh();});
        void warnBeforeLeaving();
    },true);
    document.addEventListener('click',event=>{
        const link=event.target.closest('a[href]');
        if(!link||!hasUnsavedScores()||link.hasAttribute('download')||(link.target&&link.target!=='_self')||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey)return;
        const target=new URL(link.href,location.href);
        if(target.origin===location.origin&&target.pathname===location.pathname&&target.search===location.search&&target.hash)return;
        event.preventDefault();event.stopImmediatePropagation();void warnBeforeLeaving();
    },true);
    document.addEventListener('submit',event=>{
        if(event.target===draftForm||!hasUnsavedScores())return;
        event.preventDefault();event.stopImmediatePropagation();void warnBeforeLeaving();
    },true);
    window.addEventListener('beforeunload',event=>{
        if(!savingDraft&&hasUnsavedScores()){event.preventDefault();event.returnValue='';}
    });
    window.addEventListener('pageshow',()=>{savingDraft=false;});
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
    root.querySelectorAll('.scholarship-scoring-table').forEach(table=>{
        const tbody=table.tBodies[0];
        const originalOrder=new Map([...tbody.rows].map((row,index)=>[row,index]));
        table.querySelectorAll('[data-score-sort]').forEach(button=>button.addEventListener('click',()=>{
            const header=button.closest('th');
            const direction=header.getAttribute('aria-sort')==='ascending'?'descending':'ascending';
            const key=button.dataset.scoreSort;
            const value=row=>{
                const raw=key==='total'?row.querySelector('[data-score-total]')?.dataset.scoreTotal:[...row.querySelectorAll('[data-score-input]')].find(input=>input.dataset.scoreInput===key)?.value;
                return raw===undefined||raw.trim()===''||!Number.isFinite(Number(raw))?null:Number(raw);
            };
            const rows=[...tbody.rows].sort((a,b)=>{
                const first=value(a),second=value(b);
                if(first===null&&second!==null)return 1;
                if(second===null&&first!==null)return -1;
                return (first===null?0:(first-second)*(direction==='ascending'?1:-1))||originalOrder.get(a)-originalOrder.get(b);
            });
            rows.forEach(row=>tbody.append(row));
            root.querySelectorAll('tr[data-scoring-candidate]').forEach((row,index)=>{row.cells[0].textContent=String(index+1);});
            table.querySelectorAll('[data-score-sort]').forEach(control=>{
                const selected=control===button;
                control.closest('th').setAttribute('aria-sort',selected?direction:'none');
                control.querySelector('i').className=selected?(direction==='ascending'?'ti ti-arrow-up':'ti ti-arrow-down'):'ti ti-arrows-sort';
                control.setAttribute('aria-label',`Sort ${control.dataset.sortLabel} ${selected&&direction==='ascending'?'descending':'ascending'}`);
            });
        }));
    });
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
        let scoreAlertOpen=false;
        const validateScore=input=>{
            let message='';
            const value=input.value.trim(),number=Number(value);
            if(!input.disabled){
                if(input.validity.badInput || (value!==''&&!Number.isFinite(number)))message='Enter a valid score.';
                else if(value!==''&&number>Number(input.max))message=`Score cannot exceed the maximum of ${input.max}.`;
                else if(value!==''&&number<Number(input.min))message=`Score must be at least ${input.min}.`;
                else if(value!==''&&Math.abs(number*100-Math.round(number*100))>0.000001)message='Use no more than two decimal places.';
            }
            const feedback=message || (!input.disabled && input.dataset.blankScore==='true' && input.dataset.scoreInput===subject.value && value===''?'Score not entered. Please fill in this score.':'');
            let error=document.getElementById(input.id+'Error');
            if(feedback&&!error){
                error=document.createElement('div');error.id=input.id+'Error';error.className='invalid-feedback scholarship-score-error';error.setAttribute('aria-live','polite');input.after(error);
                input.setAttribute('aria-describedby',error.id);
            }
            if(error){error.textContent=feedback;error.hidden=!feedback;}
            input.classList.toggle('is-invalid',!!feedback);input.setAttribute('aria-invalid',String(!!feedback));input.setCustomValidity(message);
            return message;
        };
        const savedSubject=root.querySelector('[data-draft-save-confirmed]')?.dataset.savedSubject;
        if(savedSubject)inputs.forEach(input=>{
            if(!input.disabled && input.dataset.scoreInput===savedSubject && input.value.trim()==='')input.dataset.blankScore='true';
            validateScore(input);
        });
        const showScoreError=async input=>{
            const message=validateScore(input);
            if(!message||scoreAlertOpen)return;
            scoreAlertOpen=true;
            try{
                await Swal.fire({icon:'error',title:'Check Score',text:message,confirmButtonText:'OK',returnFocus:false,target:root.classList.contains('is-scoring-fullscreen')?root:document.body});
                if(input.isConnected){
                    if(subject.value!==input.dataset.scoreInput){subject.value=input.dataset.scoreInput;picker.refresh();activate();}
                    focusInput(input);
                }
            }finally{scoreAlertOpen=false;}
        };
        const activate=()=>{
            const subjectField=root.querySelector('#scholarshipScoreDrafts [name="score_subject"]');
            if(subjectField)subjectField.value=subject.value;
            inputs.forEach(input=>{
                const editable=input.dataset.canScore==='true'&&!input.disabled&&subject.value!==''&&input.dataset.scoreInput===subject.value;
                input.readOnly=!editable;input.tabIndex=editable?0:-1;
                input.setAttribute('aria-readonly',String(!editable));
                input.classList.toggle('is-scoring',editable);
                validateScore(input);
            });
            root.querySelectorAll('[data-score-subject-heading]').forEach(heading=>heading.classList.toggle('is-scoring',heading.dataset.scoreSubjectHeading===subject.value));
            lock.disabled=subject.value==='';
        };
        subject.addEventListener('change',()=>{
            activate();
            requestAnimationFrame(()=>{const first=[...root.querySelectorAll('[data-score-input]')].find(input=>!input.readOnly&&!input.disabled);if(first)focusInput(first);});
        });
        lock.addEventListener('click',()=>{subject.value='';picker.refresh();activate();picker.button.focus();});
        root.addEventListener('input',event=>{if(event.target.matches('[data-score-input]'))validateScore(event.target);});
        root.addEventListener('focusout',event=>{
            const input=event.target.closest('[data-score-input]');
            if(input&&!input.readOnly&&!input.disabled&&validateScore(input))void showScoreError(input);
        });
        const drafts=root.querySelector('#scholarshipScoreDrafts');
        if(drafts){
            drafts.noValidate=true;
            drafts.addEventListener('submit',event=>{
                const invalid=inputs.filter(input=>validateScore(input));
                if(invalid.length){event.preventDefault();void showScoreError(invalid[0]);}
            });
        }
        root.addEventListener('keydown',event=>{
            const input=event.target.closest('[data-score-input]');
            if(!input||input.readOnly||input.disabled||!['Tab','Enter'].includes(event.key)||event.ctrlKey||event.metaKey||event.altKey)return;
            const active=[...root.querySelectorAll('[data-score-input]')].filter(field=>!field.readOnly&&!field.disabled);
            const next=active[active.indexOf(input)+(event.shiftKey?-1:1)];
            event.preventDefault();
            if(validateScore(input)){void showScoreError(input);return;}
            if(next)focusInput(next);
            else if(event.shiftKey)picker.button.focus();
            else root.querySelector('[data-save-score-drafts]')?.focus();
        });
        activate();
    }
    const reviewForm=root.querySelector('[data-score-review-form]');
    if(reviewForm){
        const button=reviewForm.querySelector('[data-review-scores]');
        const hint=root.querySelector('[data-unsaved-score-review]');
        const ready=!button.disabled;
        let submitting=false;
        const dirty=hasUnsavedScores;
        const sync=()=>{const changed=dirty();button.disabled=!ready||changed||submitting;hint.hidden=!changed;};
        root.addEventListener('input',sync);root.addEventListener('change',sync);sync();
        reviewForm.addEventListener('submit',event=>{
            if(!ready||dirty()||submitting||!reviewForm.reportValidity()){event.preventDefault();return;}
            submitting=true;sync();
        });
    }
    draftForm?.addEventListener('submit',event=>{
        queueMicrotask(()=>{savingDraft=!event.defaultPrevented;});
    });
}
