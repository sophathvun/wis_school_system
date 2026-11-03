import {Modal} from 'bootstrap';
import Swal from 'sweetalert2';
import {setupStaffLocationSelect} from './helpers/staffBirthplaceSelect.js';
import {enhanceSubjectTemporal} from './scholarshipTemporalInputs.js';

const root=document.querySelector('[data-teaching-schedules]');
if(root){
    const get=selector=>root.querySelector(selector),filters=get('[data-schedule-filters]'),editor=get('[data-schedule-editor]');
    const escape=value=>String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
    const days=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    let meta,rows=[],busy=false,loadVersion=0;
    const combos=new Map(),temporals=new Map();
    const api=async(path,body)=>{
        const response=await fetch(root.dataset.base+path,{method:body?'POST':'GET',credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,...(body?{'Content-Type':'application/json'}:{})},body:body?JSON.stringify(body):undefined});
        const data=await response.json().catch(()=>({}));
        if(!response.ok)throw new Error(Object.values(data.errors||{}).flat().join('\n')||data.message||'Unable to load schedules.');
        return data;
    };
    const run=async callback=>{
        if(busy)return;busy=true;get('[data-schedule-save]').disabled=true;
        try{await callback();}catch(error){await Swal.fire({icon:'error',title:'Schedules',text:error.message});}
        finally{busy=false;get('[data-schedule-save]').disabled=false;}
    };
    const sync=()=>{
        combos.forEach((combo,select)=>{[...select.options].forEach(option=>option.dataset.en=option.textContent);combo.sync();});
        root.querySelectorAll('.premium-form-field').forEach(field=>{
            const input=field.querySelector('select,input:not([type="hidden"])');
            field.classList.toggle('has-value',input?.tagName==='SELECT'?Boolean(input.selectedOptions[0]?.textContent):Boolean(input?.value));
        });
    };
    const fill=(select,items,label,blank)=>{
        const previous=select.value;
        select.replaceChildren(...(blank?[new Option(blank,'')]:[]),...items.map(item=>new Option(item[label],item.id)));
        if([...select.options].some(option=>option.value===previous))select.value=previous;
    };
    const classKey=row=>`${row.class_id}:${row.grade_id||0}`;
    const periodTime=period=>{
        const format=raw=>{const [hour,minute]=raw.split(':');return `${Number(hour)%12||12}:${minute} ${Number(hour)<12?'AM':'PM'}`;};
        return `${format(period.starts_at)} – ${format(period.ends_at)}`;
    };
    const syncPeriod=()=>{
        const period=meta.periods.find(item=>String(item.id)===editor.elements.period_id.value);
        get('[data-schedule-period-time]').textContent=period?periodTime(period):'Select an active session period';
    };
    function syncClasses(form){
        const available=meta.classes.filter(row=>String(row.academic_year_id)===form.elements.academic_year_id.value&&(!form.elements.campus_id.value||String(row.campus_id)===form.elements.campus_id.value));
        const unique=[...new Map(available.map(row=>[classKey(row),{id:classKey(row),label:row.label}])).values()];
        fill(form.elements.class_key,unique,'label',form===filters?'All grades':'Select grade');sync();
    }
    const classLabel=row=>{
        const grade=row.grade_short_name||'';
        return grade&&!row.class_name.startsWith(grade)?grade+row.class_name:row.class_name;
    };
    function render(){
        const term=filters.elements.search.value.trim().toLowerCase();
        const visible=rows.filter(row=>(!filters.elements.class_key.value||classKey(row)===filters.elements.class_key.value)&&(!filters.elements.teacher_id.value||String(row.teacher_id)===filters.elements.teacher_id.value)&&[row.subject,row.teacher_name,row.campus_name_en,classLabel(row)].join(' ').toLowerCase().includes(term));
        get('[data-schedule-summary]').innerHTML=`<span class="badge bg-blue-lt">${visible.length} sessions</span><span class="badge bg-green-lt">${visible.filter(row=>row.status==='published').length} published</span><span class="badge bg-yellow-lt">${visible.filter(row=>row.status==='draft').length} drafts</span>`;
        get('[data-schedule-list]').innerHTML=visible.length?visible.map(row=>{
            const manageable=meta.manage_campuses.includes(Number(row.campus_id));
            return `<tr><td><strong>${days[row.weekday]}</strong><small>${escape(row.starts_at.slice(0,5))} – ${escape(row.ends_at.slice(0,5))}</small></td><td>${escape(classLabel(row))}<small>${escape(row.campus_name_en)}</small></td><td><strong>${escape(row.subject)}</strong><small>${escape(row.teacher_name)}</small></td><td>${escape(row.effective_from)}<small>Until ${escape(row.effective_until||'end of schedule')}</small></td><td><span class="badge ${row.status==='published'?'bg-green-lt':'bg-yellow-lt'}">${escape(row.status)}</span></td><td>${manageable?(row.status==='draft'?`<button type="button" class="btn btn-sm me-1" data-schedule-edit="${row.id}">Edit</button><button type="button" class="btn btn-sm btn-primary" data-schedule-publish="${row.id}">Publish</button>`:`<button type="button" class="btn btn-sm" data-schedule-revise="${row.id}">Create Revision</button>`):'—'}</td></tr>`;
        }).join(''):'<tr><td colspan="6">No sessions match these filters.</td></tr>';
    }
    async function load(){
        const version=++loadVersion;
        if(!filters.elements.academic_year_id.value)return;
        const data=await api('/sessions?'+new URLSearchParams({academic_year_id:filters.elements.academic_year_id.value,campus_id:filters.elements.campus_id.value}));
        if(version!==loadVersion)return;rows=data.schedules;render();
    }
    function open(row=null,revision=false){
        editor.reset();
        editor.elements.id.value=revision?'':row?.id||'';
        editor.elements.version.value=revision?0:row?.version||0;
        editor.elements.replaces_id.value=revision?row.id:row?.replaces_id||'';
        for(const name of ['academic_year_id','campus_id'])editor.elements[name].value=row?.[name]??filters.elements[name].value;
        if(!editor.elements.campus_id.value)editor.elements.campus_id.value=String(meta.manage_campuses[0]);
        syncClasses(editor);
        if(row)editor.elements.class_key.value=classKey(row);
        editor.elements.teacher_id.value=row?.teacher_id||'';
        editor.elements.subject.value=row?.subject||'';
        editor.elements.weekday.value=row?.weekday??1;
        editor.elements.period_id.value=row?.period_id||'';syncPeriod();
        const today=new Date(),iso=`${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}-${String(today.getDate()).padStart(2,'0')}`;
        for(const name of ['effective_from','effective_until'])temporals.get(name).setValue(row?.[name]?.slice(0,10)||(name==='effective_from'?iso:''));
        get('[data-schedule-revision]').hidden=!revision&&!row?.replaces_id;
        get('#teachingScheduleTitle').textContent=revision?'Create Schedule Revision':row?'Edit Draft':'Add Teaching Session';
        sync();Modal.getOrCreateInstance(get('#teachingScheduleModal')).show();
    }
    root.querySelectorAll('select').forEach(select=>{
        combos.set(select,setupStaffLocationSelect(select.form,select,{labelId:select.id+'Label',optionsId:select.id+'Options',locationLabel:select.parentElement.querySelector('label').textContent.trim()}));
    });
    for(const name of ['effective_from','effective_until']){
        const input=editor.elements[name];input.setAttribute('aria-label',input.parentElement.querySelector('label').textContent);
        const control=enhanceSubjectTemporal(input);input.parentElement.parentElement.querySelector('label').htmlFor=control.input.id;temporals.set(name,control);
    }
    root.addEventListener('input',()=>{sync();render();});
    filters.addEventListener('submit',event=>event.preventDefault());
    filters.addEventListener('change',event=>{
        if(!event.target.matches('select'))return;
        if(['academic_year_id','campus_id'].includes(event.target.name)){syncClasses(filters);load().catch(error=>Swal.fire({icon:'error',text:error.message}));}else render();
        sync();
    });
    editor.addEventListener('change',event=>{if(['academic_year_id','campus_id'].includes(event.target.name))syncClasses(editor);if(event.target.name==='period_id')syncPeriod();sync();});
    get('[data-schedule-add]').addEventListener('click',()=>open());
    get('[data-schedule-list]').addEventListener('click',event=>{
        const edit=event.target.closest('[data-schedule-edit]'),revise=event.target.closest('[data-schedule-revise]'),publish=event.target.closest('[data-schedule-publish]');
        if(edit||revise){const id=edit?.dataset.scheduleEdit||revise.dataset.scheduleRevise;open(rows.find(row=>row.id===Number(id)),Boolean(revise));}
        if(publish)run(async()=>{const row=rows.find(row=>row.id===Number(publish.dataset.schedulePublish));const confirmation=await Swal.fire({icon:'question',title:'Publish this session?',text:'This makes attendance available to the assigned teacher. Published sessions require dated revisions to change.',showCancelButton:true,confirmButtonText:'Publish'});if(confirmation.isConfirmed){await api(`/sessions/${row.id}/publish`,{version:row.version});await load();}});
    });
    editor.addEventListener('submit',event=>{event.preventDefault();run(async()=>{
        if(![...temporals.values()].every(control=>control.valid()))return;
        const body=Object.fromEntries(new FormData(editor));
        const [class_id,grade_id]=body.class_key.split(':');delete body.class_key;
        body.class_id=class_id;body.grade_id=Number(grade_id)||null;
        if(!body.id)delete body.id;if(!body.replaces_id)delete body.replaces_id;
        await api('/sessions',body);Modal.getOrCreateInstance(get('#teachingScheduleModal')).hide();await load();
    });});
    run(async()=>{
        meta=await api('/meta');
        fill(editor.elements.period_id,meta.periods.map(period=>({...period,label:`${period.session==='morning'?'Morning':'Afternoon'} · ${period.name} · ${periodTime(period)}`})),'label','Select a session period');
        for(const form of [filters,editor]){
            fill(form.elements.academic_year_id,meta.years,'academic_year');
            fill(form.elements.campus_id,form===editor?meta.campuses.filter(row=>meta.manage_campuses.includes(row.id)):meta.campuses,'campus_name_en',form===filters?'All campuses':undefined);
            fill(form.elements.teacher_id,meta.teachers,'name',form===filters?'All teachers':'Select teacher');syncClasses(form);
        }
        get('[data-schedule-add]').hidden=!meta.manage_campuses.length;sync();await load();
    });
}
