import Swal from 'sweetalert2';
import { initResultReportScope } from './scholarshipResultReportScope.js';

export function initResults(root) {
    const fullscreenButton=root.querySelector('[data-result-fullscreen]');
    if(fullscreenButton){
        let fallback=false,busy=false;
        const active=()=>fallback||document.fullscreenElement===root;
        const sync=()=>{
            const expanded=active();
            root.classList.toggle('is-result-fullscreen',expanded);
            document.body.classList.toggle('scholarship-result-fullscreen-open',expanded);
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
            if(event.key==='Escape'&&fallback&&!root.querySelector('.modal.show,.swal2-popup:not(.swal2-toast)')){event.preventDefault();void exit();}
        });
        sync();
    }
    const dialog=options=>Swal.fire({target:root.classList.contains('is-result-fullscreen')?root:document.body,...options});
    const grades=[...root.querySelectorAll('[data-result-grade]')];
    initResultReportScope(root);
    if(!grades.length)return;
    const dirty=new Set();
    const warning=()=>dialog({icon:'warning',title:'Unsaved Winners',text:'Save the changed grade winners before leaving this list or publishing results.',confirmButtonText:'Continue Editing'});
    root.querySelector('[data-result-filters]')?.addEventListener('change',event=>{
        if(!dirty.size)return;
        event.preventDefault();event.stopImmediatePropagation();void warning();
        // Restore the displayed filters; the current results have not changed.
        event.target.value=event.target.dataset.resultOriginalValue;
        document.dispatchEvent(new CustomEvent('scholarship:refresh-result-filters'));
    },true);
    root.querySelectorAll('[data-result-filters] select').forEach(select=>select.dataset.resultOriginalValue=select.value);
    document.addEventListener('click',event=>{
        if(dirty.size&&event.target.closest('[data-result-report-link],[data-result-print-settings]')){event.preventDefault();event.stopImmediatePropagation();void warning();return;}
        const link=event.target.closest('a[href]');
        if(!dirty.size||!link||link.hasAttribute('download')||link.target==='_blank'||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey)return;
        event.preventDefault();void warning();
    },true);
    document.addEventListener('submit',event=>{
        if(!dirty.size||event.target.matches('[data-result-award-form]'))return;
        event.preventDefault();event.stopImmediatePropagation();void warning();
    },true);
    window.addEventListener('beforeunload',event=>{if(dirty.size){event.preventDefault();event.returnValue='';}});
    grades.forEach(section=>{
        const table=section.querySelector('table'),body=table.tBodies[0];
        const originalOrder=new Map([...body.rows].map((row,index)=>[row,index]));
        const start=Number(body.rows[0]?.querySelector('[data-result-number]').textContent)||1;
        const sortRows=(key,ascending)=>{
            const value=row=>{
                if(key==='award'){
                    const select=row.querySelector('[data-result-award-select]');
                    if(select)return select.value?select.selectedIndex-1:null;
                    const order=row.querySelector('[data-result-award-order]')?.dataset.resultAwardOrder;
                    return order==null||order===''?null:Number(order);
                }
                const raw=row.querySelector(`[data-result-score="${key}"]`)?.dataset.sortValue;
                return raw==null||raw===''?null:Number(raw);
            };
            const rows=[...body.rows].sort((a,b)=>{
                const left=value(a),right=value(b);
                if(left===null||right===null)return left===right?originalOrder.get(a)-originalOrder.get(b):left===null?1:-1;
                return (ascending?left-right:right-left)||originalOrder.get(a)-originalOrder.get(b);
            });
            rows.forEach((row,index)=>{row.querySelector('[data-result-number]').textContent=start+index;body.append(row);});
        };
        table.querySelectorAll('[data-result-sort]').forEach(button=>button.addEventListener('click',()=>{
            const header=button.closest('th'),ascending=header.getAttribute('aria-sort')!=='ascending',key=button.dataset.resultSort;
            table.querySelectorAll('[data-result-sort]').forEach(sort=>{
                const active=sort===button;sort.closest('th').setAttribute('aria-sort',active?(ascending?'ascending':'descending'):'none');
                sort.querySelector('i').className=active?(ascending?'ti ti-arrow-up':'ti ti-arrow-down'):'ti ti-arrows-sort';
                sort.setAttribute('aria-label',`Sort ${sort.dataset.sortLabel} ${active&&ascending?'descending':'ascending'}`);
            });
            sortRows(key,ascending);
        }));
        const form=section.querySelector('[data-result-award-form]');
        if(!form)return;
        const slots=[...form.querySelectorAll('[data-result-award-slot]')].map(element=>({
            key:element.dataset.resultAwardSlot,label:element.dataset.slotLabel,subject:element.dataset.slotSubject,percent:element.dataset.slotPercent,
            candidate:element.querySelector('[data-slot-candidate]'),award:element.querySelector('[data-slot-award]'),reason:element.querySelector('[data-slot-reason]'),
        }));
        const metrics=JSON.parse(form.querySelector('[data-result-grade-metrics]').textContent);
        const signature=()=>JSON.stringify(slots.map(slot=>[slot.candidate.value,slot.award.value,slot.reason.value]));
        let savedSignature=signature();
        const markDirty=()=>{
            const changed=signature()!==savedSignature;
            if(changed)dirty.add(form);else dirty.delete(form);
            section.querySelector('[data-result-unsaved]').hidden=!changed;
        };
        const clearErrors=()=>section.querySelectorAll('.is-invalid').forEach(input=>input.classList.remove('is-invalid'));
        const error=async(message,candidate)=>{
            const row=section.querySelector(`[data-result-candidate="${candidate}"]`);
            const select=row?.querySelector('[data-result-award-select]');
            if(select){select.classList.add('is-invalid');row.querySelector('[data-result-rank-error]').textContent=message;}
            await dialog({icon:'warning',title:'Check Scholarship Winners',text:message,confirmButtonText:'OK'});
            select?.focus();
        };
        section.querySelectorAll('[data-result-award-select]:not(:disabled)').forEach(select=>{
            const row=select.closest('[data-result-candidate]'),id=row.dataset.resultCandidate;
            const percent=row.querySelector('[data-result-award-percent]'),reason=row.querySelector('[data-result-award-reason]');
            select.addEventListener('change',()=>{
                clearErrors();
                const selected=slots.find(slot=>slot.key===select.value);
                const candidate=metrics.find(candidate=>String(candidate.id)===id);
                if(selected && (selected.subject?candidate.absent_subjects.includes(Number(selected.subject)):!candidate.overall_eligible)){
                    select.value=select.dataset.savedSlot;void error('This student was absent for the award subject. Overall awards require attendance in all subjects.',id);return;
                }
                if(selected?.candidate.value && selected.candidate.value!==id){
                    select.value=select.dataset.savedSlot;
                    void error(`${selected.label} already has a winner in Grade ${section.dataset.resultGrade}. Clear that assignment before choosing another student.`,id);return;
                }
                const previous=slots.find(slot=>slot.key===select.dataset.savedSlot);
                if(previous){previous.candidate.value='';previous.award.value=previous.percent;previous.reason.value='';}
                if(selected){selected.candidate.value=id;selected.award.value=selected.percent;selected.reason.value='';}
                percent.value=selected?.percent??'';reason.value='';select.dataset.savedSlot=select.value;
                select.classList.toggle('is-award-assigned',Boolean(selected));
                row.querySelector('[data-result-award-editor]').hidden=!selected;row.querySelector('[data-result-no-award]').hidden=Boolean(selected);
                row.querySelector('[data-result-exception]').hidden=!selected;
                const awardSort=table.querySelector('[data-result-sort="award"]').closest('th').getAttribute('aria-sort');
                if(awardSort!=='none')sortRows('award',awardSort==='ascending');
                markDirty();
            });
            percent.addEventListener('input',()=>{const slot=slots.find(slot=>slot.key===select.value);if(slot){slot.award.value=percent.value;markDirty();}});
            reason.addEventListener('input',()=>{const slot=slots.find(slot=>slot.key===select.value);if(slot){slot.reason.value=reason.value;markDirty();}});
        });
        let saving=false;
        form.addEventListener('submit',async event=>{
            event.preventDefault();if(saving)return;clearErrors();
            const remaining=new Map(metrics.filter(row=>row.eligible).map(row=>[String(row.id),row]));
            for(const slot of slots){
                const id=slot.candidate.value;
                const eligible=new Map([...remaining].filter(([,row])=>slot.subject?!row.absent_subjects.includes(Number(slot.subject)):row.overall_eligible));
                if(!id){if(eligible.size){await error(`Select a student for ${slot.label} before saving Grade ${section.dataset.resultGrade} winners.`);return;}continue;}
                const candidate=eligible.get(id);
                if(!candidate){await error('Each winner must be a reviewed, attending student and can receive only one award.',id);return;}
                const score=row=>Number(slot.subject?row.scores[slot.subject]:row.total);
                const highest=Math.max(...[...eligible.values()].map(score));
                if(score(candidate)<highest&&slot.reason.value.trim().length<5){await error(`${slot.label}: select a student with the highest remaining score, or enter an exception reason of at least 5 characters.`,id);return;}
                if(!slot.award.value.trim()||!Number.isFinite(Number(slot.award.value))||Number(slot.award.value)<0||Number(slot.award.value)>100||!/^\d+(?:\.\d{1,2})?$/.test(slot.award.value)){await error('Enter a scholarship percentage from 0 to 100 with at most two decimal places.',id);return;}
                remaining.delete(id);
            }
            const button=section.querySelector('[data-result-save]');saving=true;button.disabled=true;form.setAttribute('aria-busy','true');
            // Freeze this grade while its exact choices are being saved.
            const controls=[...section.querySelectorAll('select:not(:disabled),textarea:not(:disabled),input[type=number]:not(:disabled)')];
            const data=new FormData(form);controls.forEach(control=>control.disabled=true);
            try{
                const response=await fetch(form.action,{method:'POST',body:data,credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
                const result=await response.json();
                if(!response.ok||response.redirected||!result.version)throw new Error(result.errors?Object.values(result.errors).flat().join(' '):result.message||'Unable to save scholarship winners.');
                root.querySelectorAll('input[name=version]').forEach(input=>input.value=String(result.version));
                savedSignature=signature();dirty.delete(form);section.querySelector('[data-result-unsaved]').hidden=true;
                dialog({toast:true,position:'top-end',icon:'success',title:result.message,showConfirmButton:false,showCloseButton:true,timer:3500,timerProgressBar:true});
            }catch(problem){await dialog({icon:'error',title:'Unable to Save Winners',text:problem.message||'Please check your connection and try again.'});}
            finally{saving=false;button.disabled=false;controls.forEach(control=>control.disabled=false);form.removeAttribute('aria-busy');}
        });
    });
}
