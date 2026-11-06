import {setupStaffLocationSelect} from './helpers/staffBirthplaceSelect.js';
import {renderPagination} from './helpers/pagination.js';
import {setupG9PhotoViewer} from './helpers/g9PhotoViewer.js';
import {showSuccess,showWarning} from './helpers/sweet-alert2.js';
import {createG9CandidateAttendance} from './helpers/g9CandidateAttendance.js';

const root=document.querySelector('[data-g9-scoring]');
if(root){
    const subjects=Object.entries(JSON.parse(root.dataset.subjects)),order=['academic_year','exam_center','campus','room','grade','student'];
    const subjectMaxima=JSON.parse(root.dataset.subjectMaxima||'{}');
    const form=root.querySelector('form'),rows=root.querySelector('[data-scoring-rows]'),error=root.querySelector('[data-scoring-error]'),status=root.querySelector('[data-scoring-status]');
    const edit=root.querySelector('[data-scoring-edit]'),label=edit.querySelector('[data-scoring-edit-label]'),mode=root.querySelector('[data-scoring-mode]');
    const toggle=root.querySelector('[data-scoring-filter-toggle]'),mobile=matchMedia('(max-width:767.98px)'),viewPhoto=setupG9PhotoViewer();
    const perPage=document.getElementById('g9-scoring-per-page'),pagination=document.getElementById('g9-scoring-pagination');
    const entry=root.querySelector('[data-scoring-entry]'),subjectSelect=root.querySelector('[data-scoring-entry-subject]');
    const combos=new Map(),editors=new Set(),dirty=new Set();
    let editing=false,loading=false,finishing=false,saving=0,sequence=0,pageNumber=1,acceptedSize=perPage.value,accepted=Object.fromEntries(new FormData(form));
    let selectedSubject=subjectSelect.value;
    let attendanceSaving=0;
    const attendance=createG9CandidateAttendance(root.dataset.base,{
        canEditNow:()=>!editing&&!loading&&!finishing&&!saving&&!attendanceSaving&&!dirty.size,
        onBusyChange:busy=>{attendanceSaving+=busy?1:-1;editors.forEach(editor=>editor.refresh());syncEdit();},
        onDirtyChange:()=>syncEdit(),
    });
    edit.dataset.actionNormalized='true';
    const syncEdit=()=>{
        edit.hidden=![...editors].some(editor=>editor.allowed);edit.disabled=loading||finishing||saving>0||attendanceSaving>0||attendance.hasUnsaved();
        label.textContent=editing?'Lock':'Edit';edit.querySelector('i').className=finishing?'ti ti-loader-2':editing?'ti ti-lock':'ti ti-pencil';
        edit.setAttribute('aria-pressed',String(editing));edit.setAttribute('aria-label',editing?'Lock scores':'Edit scores');
        mode.textContent=editing?'Editing '+subjects.find(([field])=>field===selectedSubject)?.[1].replace('Exam Score ','')+' · Autosave enabled':'Scores locked';
    };
    const api=async(path,body)=>{
        const response=await fetch(root.dataset.base+path,{method:body?'POST':'GET',credentials:'same-origin',headers:{Accept:'application/json',...(body?{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}:{})},body:body?JSON.stringify(body):undefined});
        const result=await response.json().catch(()=>({message:'Unable to load scores. Refresh the page and sign in again if needed.'}));
        if(!response.ok){const failure=new Error(Object.values(result.errors||{}).flat().join('\n')||result.message||'Scores unavailable.');failure.status=response.status;throw failure;}
        return result;
    };
    const sync=()=>combos.forEach(combo=>combo.sync());
    [...form.querySelectorAll('select'),subjectSelect].forEach(select=>{
        [...select.options].forEach(option=>option.dataset.en=option.textContent);
        const combo=setupStaffLocationSelect(select===subjectSelect?entry:form,select,{labelId:select.id+'Label',optionsId:select.id+'Options',locationLabel:select.getAttribute('aria-label')});combos.set(select,combo);
        const menu=select.nextElementSibling.querySelector('.location-combobox-menu'),search=menu.querySelector('input');menu.classList.add('g9-center-menu');
        const heading=document.createElement('div');heading.className='g9-center-menu-heading';heading.textContent=select.getAttribute('aria-label');menu.prepend(heading);
        const box=document.createElement('div'),icon=document.createElement('i');box.className='g9-center-search';icon.className='ti ti-search';icon.setAttribute('aria-hidden','true');search.before(box);box.append(icon,search);
        const position=()=>{const rect=select.nextElementSibling.getBoundingClientRect(),width=Math.min(520,document.documentElement.clientWidth-24);menu.style.width=width+'px';menu.style.left=(Math.max(12,Math.min(rect.left,document.documentElement.clientWidth-width-12))-rect.left)+'px';};
        combo.toggle.addEventListener('click',position);window.addEventListener('resize',position);
    });
    const expand=value=>{form.hidden=!value;toggle.setAttribute('aria-expanded',String(value));toggle.setAttribute('aria-label',value?'Collapse filters':'Expand filters');toggle.querySelector('i').className='ti ti-chevron-'+(value?'up':'down');if(!value)combos.forEach(combo=>combo.close());};
    expand(!mobile.matches);mobile.addEventListener('change',()=>expand(!mobile.matches));toggle.addEventListener('click',()=>expand(toggle.getAttribute('aria-expanded')!=='true'));
    form.addEventListener('submit',event=>event.preventDefault());sync();
    entry.addEventListener('submit',event=>event.preventDefault());
    const highlightSubject=()=>root.querySelectorAll('[data-scoring-subject]').forEach(heading=>heading.classList.toggle('g9-scoring-active-subject',heading.dataset.scoringSubject===selectedSubject));
    subjectSelect.addEventListener('change',async()=>{
        const next=subjectSelect.value;
        if(saving||finishing||loading||attendanceSaving||attendance.hasUnsaved()){subjectSelect.value=selectedSubject;sync();return;}
        finishing=true;syncEdit();
        try{
            const results=await Promise.allSettled([...editors].map(editor=>editor.save()));
            if(results.some(result=>result.status==='rejected'||result.value===false)||dirty.size){subjectSelect.value=selectedSubject;error.textContent='Save or correct the current scores before changing subjects.';sync();return;}
            selectedSubject=next;if(error.textContent==='Save or correct the current scores before changing subjects.')error.textContent='';highlightSubject();editors.forEach(editor=>editor.refresh());
            if(!selectedSubject)editing=false;
        }finally{finishing=false;editors.forEach(editor=>editor.refresh());syncEdit();}
    });
    const fill=(name,values)=>{
        const select=form.elements[name],value=select.value;
        select.replaceChildren(new Option('All '+select.getAttribute('aria-label').toLowerCase(),''),...values.map(item=>new Option(typeof item==='object'?item.label:item,typeof item==='object'?item.id:item)));
        if([...select.options].some(option=>option.value===value))select.value=value;
        [...select.options].forEach(option=>option.dataset.en=option.textContent);
    };
    const params=()=>new URLSearchParams([...new FormData(form)].filter(([,value])=>value!==''));
    root.querySelectorAll('[data-scoring-report]').forEach(button=>button.addEventListener('click',async()=>{
        if(!form.elements.academic_year.value){showWarning('Select an academic year','Choose an academic year before creating a report.');return;}
        if(loading||saving||finishing||attendanceSaving||attendance.hasUnsaved()||dirty.size){showWarning('Scores are not ready','Wait for autosave or correct unsaved rows before creating a report.');return;}
        const format=button.dataset.scoringReport,type=button.dataset.scoringReportType||'verify',url=root.dataset.base+'/reports/'+type+'/'+format+'?'+params();
        if(format==='print'){window.open(url,'_blank','noopener');return;}
        button.disabled=true;error.textContent='';
        try{
            const response=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json'}});
            if(!response.ok){const result=await response.json();throw new Error(Object.values(result.errors||{}).flat().join('\n')||result.message||'Report export failed.');}
            const href=URL.createObjectURL(await response.blob()),link=document.createElement('a');link.href=href;link.download=type==='scores'?'g9-score-collection.pdf':'g9-scoring-verification.pdf';document.body.append(link);link.click();link.remove();setTimeout(()=>URL.revokeObjectURL(href),1000);
        }catch(failure){error.textContent=failure.message;}
        finally{button.disabled=false;}
    }));
    const canLeave=()=>!saving&&!finishing&&!attendanceSaving&&(!(dirty.size||attendance.hasUnsaved())||confirm('Discard unsaved score or absence reason changes?'));
    const line=(cell,text,classes='')=>{const element=document.createElement('div');element.textContent=text??'—';element.className=classes;cell.append(element);return element;};
    const empty=text=>{rows.replaceChildren();const cell=rows.insertRow().insertCell();cell.colSpan=subjects.length+8;cell.className='text-center text-secondary py-5';cell.textContent=text;};
    const canonical=value=>value===''||value===null||value===undefined?'':String(Number(value));
    const render=(record,index)=>{
        const row=rows.insertRow(),cells=Array.from({length:subjects.length+8},()=>row.insertCell());
        line(cells[0],String(index),'badge bg-blue-lt g9-number-badge');
        if(record.photo_url){
            const button=document.createElement('button'),image=document.createElement('img');button.type='button';button.className='g9-photo-trigger';button.setAttribute('aria-label','View photo of '+record.name_en);
            image.className='g9-student-photo';image.src=record.photo_url;image.alt=record.name_en;image.loading='lazy';button.append(image);button.addEventListener('click',()=>viewPhoto(record));cells[1].append(button);
        }else line(cells[1],'—','text-secondary');
        line(cells[2],record.name_kh||record.name_en,'fw-semibold');if(record.name_kh)line(cells[2],record.name_en);
        line(cells[2],record.student_code,'small text-secondary');line(cells[2],[record.campus,record.grade].filter(Boolean).join('-'),'small text-secondary');
        line(cells[3],record.exam_center);
        line(cells[3],record.exam_language||'Exam language not set','small text-secondary');
        const badges=document.createElement('div');badges.className='d-flex gap-2 mt-1 flex-wrap';
        [['Room',record.room,'bg-blue-lt'],['Seat',record.seat,'bg-purple-lt']].forEach(([title,value,color])=>{const item=document.createElement('span'),badge=document.createElement('span');item.append(document.createTextNode(title+': '));badge.className='badge '+color;badge.textContent=value||'—';item.append(badge);badges.append(item);});cells[3].append(badges);
        const savedEoy=canonical(record.eoy_average);
        const eoy=savedEoy!==''&&Number.isFinite(Number(savedEoy))?Math.round(Number(savedEoy)):null;
        line(cells[4],eoy===null?'—':String(eoy),'g9-scoring-summary');
        const inputs=subjects.map(([field,title],i)=>{
            const input=document.createElement('input');input.type='number';input.min='0';input.max='9999.999';input.step='.001';input.inputMode='decimal';input.className='form-control g9-scoring-score';
            input.value=canonical(record.scores[field]);input.readOnly=true;input.disabled=!record.can_edit;input.setAttribute('aria-label',title.replace('Exam Score ','')+' score for '+record.name_en);cells[5+i].append(input);return input;
        });
        const totalCell=cells[subjects.length+5];totalCell.classList.add('g9-scoring-total');
        const totalValue=line(totalCell,'—','g9-scoring-summary'),totalNote=line(totalCell,'','small text-secondary text-center');
        const outcomes=line(totalCell,'','g9-scoring-outcomes');
        const resultValue=line(outcomes,'','badge g9-scoring-outcome');
        const gradeValue=line(outcomes,'','badge g9-scoring-outcome');
        const language=String(record.exam_language||'').trim().toLowerCase();
        const englishExam=['english','en','អង់គ្លេស'].includes(language),khmerExam=['khmer','kh','km','ខ្មែរ'].includes(language);
        totalCell.title=englishExam?'EOY-Avg + all subjects except English.':khmerExam?'EOY-Avg + other subjects + max(English score − 25, 0).':'Set the exam language to calculate Total Score.';
        const last=cells[cells.length-1],rowStatus=line(last,'','small'),count=line(last,'','small text-secondary mt-1'),retry=document.createElement('button');
        rowStatus.setAttribute('role','status');retry.type='button';retry.className='btn btn-sm btn-outline-danger mt-1';retry.textContent='Retry';retry.hidden=true;last.append(retry);
        if(!record.can_edit)line(last,'View only','small text-secondary');
        let baseline=inputs.map(input=>canonical(input.value)),timer=null,rowSaving=false,failed=false,conflicted=false;
        const changed=()=>inputs.some((input,i)=>input.validity?.badInput||canonical(input.value)!==baseline[i]);
        const cancel=()=>{clearTimeout(timer);timer=null;};
        const message=(text,color='secondary')=>{rowStatus.textContent=text;rowStatus.className='small text-'+color;};
        const attendanceEditor=attendance.render(cells[subjects.length+6],record);
        const refresh=()=>{
            changed()?dirty.add(record.id):dirty.delete(record.id);
            attendanceEditor?.refresh();
            inputs.forEach((input,i)=>{
                const selected=subjects[i][0]===selectedSubject;
                const maximum=Number(subjectMaxima[subjects[i][0]]),score=Number(input.value);
                input.classList.toggle('is-below-half',input.value!==''&&input.checkValidity()&&Number.isFinite(score)&&score>=0&&maximum>0&&score<maximum/2);
                input.readOnly=!editing||!selected||rowSaving||conflicted;input.disabled=!record.can_edit;input.tabIndex=!input.readOnly&&!input.disabled?0:-1;
                cells[5+i].classList.toggle('g9-scoring-active-subject',selected);
            });
            const counted=inputs.map((input,i)=>({input,field:subjects[i][0]})).filter(({field})=>!englishExam||field!=='score_english');
            count.textContent=counted.filter(({input})=>input.value!=='').length+' / '+counted.length+' recorded';retry.hidden=!failed;retry.disabled=rowSaving;
            const values=[{field:'eoy_average',value:eoy},...counted.map(({input,field})=>({field,value:input.value}))];
            const entered=values.filter(({value})=>value!==null&&value!==undefined&&value!=='');
            const valid=counted.every(({input})=>input.checkValidity())&&entered.every(({value})=>Number.isFinite(Number(value))&&Number(value)>=0);
            const total=entered.reduce((sum,{field,value})=>{
                const score=Math.round(Number(value)*1000);
                return sum+(khmerExam&&field==='score_english'?Math.max(0,score-25000):score);
            },0)/1000;
            totalValue.textContent=(englishExam||khmerExam)&&valid&&entered.length?String(total):'—';
            totalValue.classList.toggle('is-below-pass',totalValue.textContent!=='—'&&total<260);
            totalNote.textContent=!(englishExam||khmerExam)?'Set exam language':!valid?'Check scores':entered.length&&entered.length<values.length?'Partial':'';
            const complete=(englishExam||khmerExam)&&valid&&entered.length===values.length;
            outcomes.hidden=!complete;
            resultValue.textContent=complete?(total<260?'ធ្លាក់':'ជាប់'):'';
            gradeValue.textContent=complete?(total<260?'ធ្លាក់':total<338?'មធ្យម':total<416?'ល្អបង្គួរ':'ល្អ'):'';
            const outcome=complete?(total<260?'is-failed':total<338?'is-average':total<416?'is-good':'is-very-good'):'';
            resultValue.className='badge g9-scoring-outcome '+(complete?(total<260?'is-failed':'is-passed'):'');
            gradeValue.className='badge g9-scoring-outcome '+outcome;
            resultValue.setAttribute('aria-label','Result: '+resultValue.textContent);
            gradeValue.setAttribute('aria-label','Letter Grade: '+gradeValue.textContent);
        };
        const save=async()=>{
            cancel();
            if(!record.can_edit||!editing||rowSaving||conflicted||!row.isConnected||!changed())return !changed();
            let valid=true;inputs.forEach(input=>{const invalid=!input.checkValidity();input.classList.toggle('is-invalid',invalid);if(invalid)valid=false;});
            if(!valid){message('Enter a non-negative score with up to 3 decimal places.','danger');return false;}
            const scores={};inputs.forEach((input,i)=>{if(canonical(input.value)!==baseline[i])scores[subjects[i][0]]=input.value===''?null:input.value;});
            rowSaving=true;saving++;failed=false;combos.forEach(combo=>combo.toggle.disabled=true);message('Saving…');refresh();syncEdit();
            try{
                const result=await api('/'+record.id,{version:record.version,scores});record.version=result.version;
                inputs.forEach((input,i)=>input.value=canonical(result.scores[subjects[i][0]]));baseline=inputs.map(input=>canonical(input.value));dirty.delete(record.id);
                message('Saved automatically','success');status.textContent='Saved scores for '+record.name_en+'.';showSuccess('Scores saved');return true;
            }catch(failure){failed=true;conflicted=failure.status===409;retry.textContent=conflicted?'Reload':'Retry';message(failure.message+' Your changes are not saved.','danger');return false;}
            finally{rowSaving=false;saving--;combos.forEach(combo=>combo.toggle.disabled=saving>0);refresh();syncEdit();}
        };
        inputs.forEach((input,i)=>{
            input.addEventListener('input',()=>{if(!editing||conflicted)return;cancel();failed=false;input.classList.remove('is-invalid');refresh();if(changed()){message('Unsaved changes…');timer=setTimeout(save,700);}else message('');});
            input.addEventListener('blur',()=>{if(editing&&!rowSaving)save();});
            input.addEventListener('keydown',event=>{
                if(event.key!=='Tab'||event.altKey||event.ctrlKey||event.metaKey||input.readOnly||input.disabled)return;
                if(!input.checkValidity()){event.preventDefault();input.classList.add('is-invalid');message('Correct this score before moving to the next student.','danger');return;}
                const column=[...editors].map(editor=>editor.inputFor(subjects[i][0]));
                const index=column.indexOf(input),direction=event.shiftKey?-1:1;
                for(let next=index+direction;next>=0&&next<column.length;next+=direction){
                    const target=column[next];
                    if(!target||target.disabled||target.readOnly)continue;
                    event.preventDefault();target.focus();target.select();save();return;
                }
                // At the first/last editable student, preserve normal keyboard exit.
            });
        });
        retry.addEventListener('click',()=>{if(conflicted){if(canLeave())load(pageNumber,true);}else save();});
        editors.add({allowed:record.can_edit,save,refresh,cancel,inputFor:field=>inputs[subjects.findIndex(([key])=>key===field)]});refresh();
    };
    const load=async(page=1,options=false)=>{
        const current=++sequence;error.textContent='';status.textContent='';editors.forEach(editor=>editor.cancel());editors.clear();dirty.clear();attendance.discard();editing=false;loading=true;syncEdit();
        try{
            if(!form.elements.academic_year.value){order.slice(1).forEach(name=>fill(name,[]));sync();empty('Select an academic year to view candidate scores.');pagination.replaceChildren();accepted=Object.fromEntries(new FormData(form));return;}
            empty('Loading candidate scores…');
            if(options){const choices=await api('/options?'+params());if(current!==sequence)return;order.slice(1).forEach(name=>fill(name,choices[name]));sync();}
            accepted=Object.fromEntries(new FormData(form));
            const query=params();query.set('page',page);query.set('per_page',perPage.value);
            const result=await api('/list?'+query);if(current!==sequence)return;
            pageNumber=result.current_page;acceptedSize=perPage.value;rows.replaceChildren();result.data.forEach((record,i)=>render(record,result.from+i));if(!result.data.length)empty('No candidates match the selected filters.');
            renderPagination(result,'g9-scoring-pagination','g9-scoring-per-page',next=>{if(!canLeave()){perPage.value=acceptedSize;return;}load(next);},[25,50,75,100]);
        }catch(failure){if(current===sequence){empty('Unable to load candidate scores.');error.textContent=failure.message;pagination.replaceChildren();}}
        finally{if(current===sequence){loading=false;editors.forEach(editor=>editor.refresh());syncEdit();}}
    };
    edit.addEventListener('pointerdown',event=>event.preventDefault());
    edit.addEventListener('click',async()=>{
        if(loading||finishing||saving||attendanceSaving||attendance.hasUnsaved())return;
        if(!editing){
            if(!selectedSubject){showWarning('Select a subject','Choose the subject you want to enter scores for.');combos.get(subjectSelect).toggle.focus();return;}
            editing=true;editors.forEach(editor=>editor.refresh());syncEdit();showSuccess('Editing enabled','Tab moves down the selected subject. Changes save automatically. Click Lock when finished.');return;
        }
        finishing=true;syncEdit();
        try{
            const results=await Promise.allSettled([...editors].map(editor=>editor.save()));
            if(results.some(result=>result.status==='rejected'||result.value===false)||dirty.size){error.textContent='Some scores are not saved. Check the highlighted rows before locking.';return;}
            editing=false;error.textContent='';editors.forEach(editor=>{editor.cancel();editor.refresh();});showSuccess('Scores locked');
        }finally{finishing=false;editors.forEach(editor=>editor.refresh());syncEdit();}
    });
    form.addEventListener('change',event=>{
        const index=order.indexOf(event.target.name);if(index<0)return;
        if(!canLeave()){Object.entries(accepted).forEach(([name,value])=>form.elements[name].value=value);sync();return;}
        order.slice(index+1).forEach(name=>form.elements[name].value='');load(1,true);
    });
    window.addEventListener('beforeunload',event=>{if(dirty.size||saving){event.preventDefault();event.returnValue='';}});
    load();
}
