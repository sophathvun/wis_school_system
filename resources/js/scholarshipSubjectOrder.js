import Swal from 'sweetalert2';
import { enhanceSubjectTemporal } from './scholarshipTemporalInputs.js';

export function initSubjectRows(settings) {
    const body=settings.querySelector('[data-subject-rows]');if(!body)return;
    let dragged;
    const temporal=new Set();
    const editable=()=>settings.dataset.settingsMode==='edit';
    const sync=()=>{
        for(const control of temporal)if(!control.element.isConnected){control.destroy();temporal.delete(control);}
        body.querySelectorAll('[data-subject-temporal]:not([data-temporal-ready])').forEach(input=>temporal.add(enhanceSubjectTemporal(input)));
        [...body.children].forEach((row,index)=>{
        row.draggable=editable();
        row.querySelector('[data-move-subject="up"]').disabled=!editable()||index===0;
        row.querySelector('[data-move-subject="down"]').disabled=!editable()||index===body.children.length-1;
        });
    };
    body.addEventListener('click',event=>{
        const button=event.target.closest('[data-move-subject]');if(!button||!editable())return;
        const row=button.closest('tr'),other=button.dataset.moveSubject==='up'?row.previousElementSibling:row.nextElementSibling;
        if(other){if(button.dataset.moveSubject==='up')body.insertBefore(row,other);else other.after(row);sync();}
    });
    body.addEventListener('dragstart',event=>{
        if(!editable()||event.target.closest('input')){event.preventDefault();return;}
        dragged=event.target.closest('tr');event.dataTransfer.effectAllowed='move';event.dataTransfer.setData('text/plain','subject-row');
    });
    body.addEventListener('dragover',event=>{if(dragged&&editable())event.preventDefault();});
    body.addEventListener('drop',event=>{
        if(!dragged||!editable())return;event.preventDefault();const target=event.target.closest('tr');
        if(target&&target!==dragged){const rows=[...body.children];if(rows.indexOf(dragged)<rows.indexOf(target))target.after(dragged);else body.insertBefore(dragged,target);sync();}dragged=null;
    });
    body.addEventListener('dragend',()=>dragged=null);
    new MutationObserver(sync).observe(body,{childList:true});
    new MutationObserver(sync).observe(settings,{attributes:true,attributeFilter:['data-settings-mode']});sync();
}

export function initSubjectOrder(root) {
    const panel=root.querySelector('[data-subject-order]');if(!panel)return;
    const settings=root.querySelector('[data-exam-settings]'),edit=panel.querySelector('[data-edit-subject-order]'),actions=panel.querySelector('[data-order-actions]');
    const fields=panel.querySelector('[data-order-fields]'),error=panel.querySelector('[data-order-error]'),status=panel.querySelector('[data-order-status]');
    const clone=value=>JSON.parse(JSON.stringify(value));
    let state=JSON.parse(panel.querySelector('[data-subject-order-state]').textContent);state.grades={...state.grades};
    state.schedules=state.schedules??{defaults:Object.fromEntries(state.subjects.map(s=>[s.id,{exam_date:'',starts_at:'',ends_at:''}])),grades:{}};
    state.schedules.grades={...state.schedules.grades};
    let saved=clone(state),editing=false,saving=false,dragged;
    let temporal=[];
    const subjects=new Map(state.subjects.map(subject=>[Number(subject.id),subject]));
    const eligible=grade=>state.subjects.filter(subject=>subject.grade_from<=grade&&subject.grade_to>=grade).map(subject=>Number(subject.id));
    const effective=grade=>state.grades[grade]??state.bands[grade<=6?'primary':'secondary'].filter(id=>eligible(grade).includes(Number(id)));
    const clearError=()=>{error.hidden=true;error.textContent='';};
    const sync=()=>{
        const structural=settings.dataset.settingsMode==='edit';
        edit.hidden=editing;edit.disabled=saving||structural;
        panel.querySelector('[data-order-settings-note]').hidden=!structural;
        fields.disabled=!editing||saving;actions.hidden=!editing;
        actions.querySelectorAll('button').forEach(button=>button.disabled=saving);
        const settingsEdit=settings.querySelector('[data-edit-exam-settings]');if(settingsEdit)settingsEdit.disabled=editing||saving;
    };
    const listIds=key=>key.startsWith('band-')?state.bands[key.slice(5)]:state.grades[Number(key.slice(6))];
    const move=(key,from,to)=>{
        if(!editing||saving)return;const ids=listIds(key);if(!ids||to<0||to>=ids.length)return;
        const [id]=ids.splice(from,1);ids.splice(to,0,id);render();
        status.textContent=`${subjects.get(Number(id)).name} moved to Session ${to+1}.`;
        const row=[...panel.querySelector(`[data-order-key="${key}"]`).children].find(row=>Number(row.dataset.orderSubject)===Number(id));
        row?.querySelector('button:not(:disabled)')?.focus();
    };
    const timingControls=(id,key)=>{
        const grade=key.startsWith('grade-')?Number(key.slice(6)):null;
        const overrides=state.schedules.grades,custom=grade!==null&&Object.hasOwn(overrides[grade]??{},id);
        const value=(custom?overrides[grade][id]:state.schedules.defaults[id])??{exam_date:'',starts_at:'',ends_at:''};
        const wrapper=document.createElement('div');wrapper.className='scholarship-order-timing';
        const duration=document.createElement('small');duration.className='text-secondary';
        const showDuration=()=>{
            const minutes=time=>{const [h,m]=String(time||'').split(':').map(Number);return h*60+m;};
            const length=minutes(value.ends_at)-minutes(value.starts_at);duration.textContent=length>0?`${length} minutes`: 'Set exam date and times';
        };
        for(const [field,label,type]of [['exam_date','Exam Date','date'],['starts_at','Start Time','time'],['ends_at','End Time','time']]){
            const container=document.createElement('label');container.className='small';container.textContent=label;
            const input=document.createElement('input');input.type=type;input.className='form-control form-control-sm';input.value=value[field]??'';
            input.setAttribute('aria-label',`${subjects.get(Number(id)).name} ${grade?`Grade ${grade}`:'default'} ${label}`);
            input.dataset.scheduleField=`schedules.${grade?`grades.${grade}`:'defaults'}.${id}.${field}`;
            input.disabled=!editing||saving||state.schedule_readonly||(grade!==null&&!custom);
            const control=enhanceSubjectTemporal(input,{onChange:raw=>{
                value[field]=raw;
                if(grade!==null){overrides[grade]??={};overrides[grade][id]=value;}else state.schedules.defaults[id]=value;
                input.classList.remove('is-invalid');showDuration();
            }});control.input.dataset.scheduleField=input.dataset.scheduleField;temporal.push(control);
            container.append(control.element);wrapper.append(container);
        }
        const footer=document.createElement('div');footer.className='scholarship-timing-footer';showDuration();footer.append(duration);
        if(grade!==null){
            const button=document.createElement('button');button.type='button';button.className='btn btn-sm btn-outline-primary';button.dataset.gradeScheduleAction=custom?'reset':'customize';button.dataset.scheduleSubject=id;
            button.textContent=custom?'Use Default Times':'Customize Date / Times';button.disabled=!editing||saving||state.schedule_readonly;
            button.addEventListener('click',()=>{
                if(custom){delete overrides[grade][id];if(!Object.keys(overrides[grade]).length)delete overrides[grade];}
                else{overrides[grade]??={};overrides[grade][id]=clone(value);}
                render();
            });footer.append(button);
        }
        wrapper.append(footer);return wrapper;
    };
    const drawList=(list,ids,key,custom=true)=>{
        list.replaceChildren();list.dataset.orderKey=key;
        if(!ids.length){const empty=document.createElement('li');empty.className='small text-secondary p-2';empty.textContent='No subjects configured.';list.append(empty);return;}
        ids.forEach((id,index)=>{
            const subject=subjects.get(Number(id));if(!subject)return;
            const row=document.createElement('li');row.className='scholarship-order-row';row.dataset.orderSubject=id;row.draggable=editing&&!saving&&custom;
            const session=document.createElement('span');session.className='scholarship-order-session';session.textContent=`Session ${index+1}`;
            const text=document.createElement('div');text.className='scholarship-order-name';text.textContent=subject.name;
            const range=document.createElement('small');range.textContent=subject.grade_from===subject.grade_to?`Grade ${subject.grade_from}`:`Grades ${subject.grade_from}–${subject.grade_to}`;text.append(range);
            const buttons=document.createElement('div');buttons.className='scholarship-order-buttons';
            for(const [direction,offset] of [['up',-1],['down',1]]){
                const button=document.createElement('button');button.type='button';button.className='btn btn-sm btn-outline-secondary btn-icon';button.dataset.orderMove=direction;
                button.setAttribute('aria-label',`Move ${subject.name} ${direction}`);button.disabled=!editing||saving||!custom||index+offset<0||index+offset>=ids.length;
                const icon=document.createElement('i');icon.className=`ti ti-arrow-${direction}`;icon.setAttribute('aria-hidden','true');button.append(icon);
                button.addEventListener('click',()=>move(key,index,index+offset));buttons.append(button);
            }
            row.append(session,text,buttons,timingControls(id,key));
            row.addEventListener('dragstart',event=>{if(!row.draggable||event.target.closest('input')){event.preventDefault();return;}dragged={key,index};event.dataTransfer.setData('text/plain',key);event.dataTransfer.effectAllowed='move';row.classList.add('is-dragging');});
            row.addEventListener('dragover',event=>{if(dragged?.key===key&&custom&&editing&&!saving)event.preventDefault();});
            row.addEventListener('drop',event=>{if(dragged?.key!==key||!custom)return;event.preventDefault();event.stopPropagation();move(key,dragged.index,index);dragged=null;});
            row.addEventListener('dragend',()=>{dragged=null;row.classList.remove('is-dragging');});
            list.append(row);
        });
    };
    const render=()=>{
        temporal.forEach(control=>control.destroy());temporal=[];
        const open=new Set([...panel.querySelectorAll('[data-order-grade][open]')].map(node=>Number(node.dataset.orderGrade)));
        for(const band of ['primary','secondary'])drawList(panel.querySelector(`[data-order-band="${band}"]`),state.bands[band],`band-${band}`);
        const grades=panel.querySelector('[data-order-grades]');grades.replaceChildren();
        for(let grade=1;grade<=12;grade++){
            const custom=Object.hasOwn(state.grades,grade),details=document.createElement('details');details.className='scholarship-order-grade';details.dataset.orderGrade=grade;details.open=open.has(grade);
            const summary=document.createElement('summary');summary.textContent=`Grade ${grade} `;
            const customizedTiming=Object.keys(state.schedules.grades[grade]??{}).length>0;
            const badge=document.createElement('span');badge.className=`badge ${custom||customizedTiming?'bg-warning-lt':'bg-secondary-lt'}`;badge.textContent=customizedTiming?(custom?'Custom Order & Times':'Custom Times'):(custom?'Custom Order':'Follow Grade Band Defaults');summary.append(badge);
            const body=document.createElement('div');body.className='scholarship-order-grade-body';
            const button=document.createElement('button');button.type='button';button.className='btn btn-sm btn-outline-primary mb-2';button.dataset.gradeOrderAction=custom?'reset':'customize';
            button.textContent=custom?'Reset to Default':'Customize Order';button.disabled=!eligible(grade).length;
            button.addEventListener('click',()=>{if(!editing||saving)return;if(custom)delete state.grades[grade];else state.grades[grade]=[...effective(grade)];render();status.textContent=custom?`Grade ${grade} now follows its grade band.`:`Grade ${grade} uses a custom order.`;});
            const list=document.createElement('ol');list.className='scholarship-order-list';list.setAttribute('aria-label',`Grade ${grade} exam order`);drawList(list,effective(grade),`grade-${grade}`,custom);
            body.append(button,list);details.append(summary,body);grades.append(details);
        }
        sync();
    };
    edit.addEventListener('click',()=>{if(settings.dataset.settingsMode==='edit')return;editing=true;clearError();render();status.textContent='Move subjects with the arrows or drag rows within the same list.';});
    panel.querySelector('[data-cancel-subject-order]').addEventListener('click',()=>{if(!saving){state=clone(saved);editing=false;clearError();render();status.textContent='';}});
    panel.querySelector('[data-save-subject-order]').addEventListener('click',async()=>{
        if(!editing||saving)return;
        const invalid=temporal.find(control=>!control.valid());
        if(invalid){error.textContent=invalid.input.validationMessage;error.hidden=false;invalid.input.classList.add('is-invalid');invalid.input.reportValidity();return;}
        saving=true;clearError();render();
        try{
            const response=await fetch(panel.dataset.url,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':settings.querySelector('[name=_token]').value},body:JSON.stringify({version:Number(panel.dataset.version),bands:state.bands,grades:state.grades,...(!state.schedule_readonly?{schedules:state.schedules}:{})})});
            const data=await response.json();if(!response.ok||response.redirected){const reason=new Error(data.errors?Object.values(data.errors).flat().join(' '):data.message||'Unable to save exam order.');reason.fields=data.errors;throw reason;}
            state={...state,...data.order,grades:{...data.order.grades},schedules:data.schedules??state.schedules};saved=clone(state);editing=false;
            root.querySelectorAll('[data-examination-type] [name=version],[data-exam-settings] [name=version],[data-center-locations-form] [name=version]').forEach(input=>input.value=data.version);
            root.dispatchEvent(new CustomEvent('scholarship-exam-version',{detail:data.version}));
            const rows=settings.querySelector('[data-subject-rows]'),ids=[...new Set([...state.bands.primary,...state.bands.secondary])];
            ids.forEach(id=>{const row=[...rows.children].find(row=>Number(row.querySelector('[data-subject-id]').value)===Number(id));if(row)rows.append(row);});
            for(const row of rows.children){const id=row.querySelector('[data-subject-id]').value,value=state.schedules.defaults[id];if(value)for(const key of ['exam_date','starts_at','ends_at']){const input=row.querySelector(`[name$="[${key}]"]`);if(input?._scholarshipTemporal)input._scholarshipTemporal.setValue(value[key]||'');else if(input)input.value=value[key]||'';}}
            status.textContent='The saved subject order and grade-specific times are now used for exams and invigilator assignments.';
            Swal.fire({toast:true,position:'top-end',icon:'success',title:data.message,showConfirmButton:false,timer:3500,timerProgressBar:true});
        }catch(reason){error.textContent=reason.message||'Unable to save exam order. Please try again.';error.hidden=false;}
        finally{saving=false;render();}
    });
    root.addEventListener('scholarship-exam-version',event=>panel.dataset.version=event.detail);
    new MutationObserver(sync).observe(settings,{attributes:true,attributeFilter:['data-settings-mode']});
    render();panel.dataset.subjectOrderReady='true';
}
