import {Modal} from 'bootstrap';
import Swal from 'sweetalert2';
import {setupStaffLocationSelect} from './helpers/staffBirthplaceSelect.js';
import {enhanceSubjectTemporal} from './scholarshipTemporalInputs.js';

export function initAttendanceLeave(root,meta,context,reload){
    const modal=document.querySelector('[data-attendance-leave]'),form=modal.querySelector('[data-leave-form]');
    const get=selector=>root.querySelector(selector);
    const escape=value=>String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
    let busy=false,leaves=[],loadVersion=0;
    const select=form.elements.student_id;
    select.required=false;
    const combo=setupStaffLocationSelect(form,select,{labelId:'leaveStudentLabel',optionsId:'leaveStudentOptions',locationLabel:'Student'});
    const dates=new Map();
    for(const name of ['starts_on','ends_on']){
        const input=form.elements[name],label=input.parentElement.querySelector('label');input.setAttribute('aria-label',label.textContent);
        const control=enhanceSubjectTemporal(input);label.htmlFor=control.input.id;
        control.element.querySelector('.date-picker-popup').classList.add('att-leave-calendar-popup');
        dates.set(name,control);
    }
    const sync=()=>{
        modal.querySelectorAll('.premium-form-field').forEach(field=>{
            const input=field.querySelector('select,input:not([type="hidden"])');field.classList.toggle('has-value',Boolean(input?.value));
        });combo.sync();
    };
    const periods=()=>{
        const student=context().students.find(student=>String(student.id)===select.value);
        const available=meta.periods.filter(period=>student?.allowed_period_ids.includes(Number(period.id)));
        modal.querySelector('[data-leave-periods]').hidden=form.elements.mode.value!=='partial';
        modal.querySelector('[data-leave-period-options]').innerHTML=available.map(period=>`<label class="form-check"><input type="checkbox" class="form-check-input" name="period_ids[]" value="${period.id}"><span class="form-check-label">${escape(period.name)} · ${escape(period.starts_at.slice(0,5))}–${escape(period.ends_at.slice(0,5))}</span></label>`).join('')||'<span class="text-warning">Assign a study group and grade with applicable periods first.</span>';
    };
    const api=async(path,body)=>{
        const multipart=body instanceof FormData;
        const response=await fetch(root.dataset.base+path,{method:body?'POST':'GET',credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,...(body&&!multipart?{'Content-Type':'application/json'}:{})},body:body?(multipart?body:JSON.stringify(body)):undefined});
        const data=await response.json().catch(()=>({}));
        if(!response.ok)throw new Error(Object.values(data.errors||{}).flat().join('\n')||data.message||'Unable to update approved leave.');return data;
    };
    async function load(){
        const version=++loadVersion,{scope}=context();get('[data-leave-open]').disabled=false;
        if(!scope){get('[data-leave-list]').textContent='Select a class to view leave.';return;}
        try{
            const data=await api('/leaves?'+new URLSearchParams({academic_year_id:scope.academic_year_id,campus_id:scope.campus_id}));if(version!==loadVersion)return;
            leaves=data.leaves;
            get('[data-leave-list]').innerHTML=leaves.length?leaves.map(leave=>`<article class="att-leave-item"><div><strong>${escape(leave.full_name_kh||leave.full_name_en)}</strong><div>${escape(leave.starts_on)} — ${escape(leave.ends_on)} · <span class="badge bg-blue-lt">${escape(leave.status)}</span></div><small>${escape(leave.reason)} · Approved by ${escape(leave.approved_by)}</small><details class="mt-2"><summary>History</summary>${(leave.history||[]).map(event=>`<div class="small py-1">${escape(event.action)} · ${escape(event.name)} · ${escape(event.created_at)}<br>${escape(JSON.parse(event.details||'{}').reason||'')}</div>`).join('')}</details><div class="att-leave-days">${leave.days.map(day=>`<span class="badge ${day.status==='applied'?'bg-green-lt':day.status==='conflict'?'bg-red-lt':'bg-secondary-lt'}">${escape(day.attendance_date)} · ${escape(day.status)}</span>`).join('')}</div></div><div class="d-flex gap-2 flex-wrap">${leave.attachment_name?`<a class="btn btn-sm" href="${root.dataset.base}/leaves/${leave.id}/attachment">Letter</a>`:''}${leave.status==='approved'&&leave.days.some(day=>day.status==='scheduled')?`<button class="btn btn-sm" type="button" data-leave-shorten="${leave.id}">Shorten</button><button class="btn btn-sm btn-outline-danger" type="button" data-leave-cancel="${leave.id}">Cancel remaining</button>`:''}</div></article>`).join(''):'No approved leave recorded for this campus and academic year.';
        }catch(error){get('[data-leave-list]').textContent=error.message;}
    }
    get('[data-leave-open]').addEventListener('click',()=>{
        const current=context();if(current.busy||current.dirty){Swal.fire({icon:'info',text:'Wait until attendance changes are saved before recording leave.'});return;}
        if(!current.scope||!current.students.length){Swal.fire({icon:'info',title:'Select a Class',text:'Select Academic Year, Campus, and Grade with enrolled students before recording leave.'});return;}
        form.reset();select.replaceChildren(new Option('Select student',''),...current.students.map(student=>{const option=new Option([student.name_kh,student.name].filter(Boolean).join(' · '),student.id);option.dataset.en=option.textContent;return option;}));
        select.value=get('[data-daily-student-filter]').value||'';
        const date=current.scope.date>=modal.dataset.today?current.scope.date:modal.dataset.today;
        dates.forEach(control=>control.setValue(date));periods();sync();Modal.getOrCreateInstance(modal).show();
    });
    form.addEventListener('change',event=>{if(['student_id','mode'].includes(event.target.name))periods();sync();});form.addEventListener('input',sync);
    form.addEventListener('submit',async event=>{
        event.preventDefault();if(busy||![...dates.values()].every(control=>control.valid()))return;
        if(!select.value){await Swal.fire({icon:'info',text:'Select a student before recording leave.'});return;}
        const {scope}=context();if(!scope)return;busy=true;modal.querySelector('[data-leave-submit]').disabled=true;
        try{
            const data=new FormData(form);Object.entries(scope).forEach(([key,value])=>data.set(key,value));data.set('preview','1');
            const preview=await api('/leaves',data);
            if(preview.conflicts.length)throw new Error('Attendance or leave already exists on '+preview.conflicts.join(', ')+'. No records will be replaced.');
            const confirmation=await Swal.fire({icon:'question',title:'Record approved leave?',text:`${preview.dates.length} school day(s): ${preview.dates.join(', ')}. ${preview.skipped.length} non-school/holiday date(s) skipped. ${preview.periods.length} period(s) per day.`,showCancelButton:true,confirmButtonText:'Record Leave'});
            if(!confirmation.isConfirmed)return;
            data.set('preview','0');await api('/leaves',data);Modal.getOrCreateInstance(modal).hide();await reload();await load();
        }catch(error){await Swal.fire({icon:'error',title:'Approved Leave',text:error.message});}
        finally{busy=false;modal.querySelector('[data-leave-submit]').disabled=false;}
    });
    get('[data-leave-list]').addEventListener('click',async event=>{
        const button=event.target.closest('[data-leave-cancel], [data-leave-shorten]');if(!button||busy)return;
        const shorten=button.hasAttribute('data-leave-shorten'),id=Number(button.dataset.leaveShorten||button.dataset.leaveCancel),leave=leaves.find(row=>row.id===id);
        busy=true;
        try{
            let end;
            if(shorten){const result=await Swal.fire({title:'New leave end date',input:'date',inputValue:leave.ends_on,inputAttributes:{min:modal.dataset.today,max:leave.ends_on},showCancelButton:true});if(!result.isConfirmed)return;end=result.value;}
            const result=await Swal.fire({title:shorten?'Reason for shortening':'Cancel remaining leave?',text:'Existing attendance will be retained. Correct it through the daily register if needed.',input:'textarea',inputLabel:'Reason',showCancelButton:true,inputValidator:value=>value.trim()?undefined:'Enter a reason.'});if(!result.isConfirmed)return;
            await api(`/leaves/${id}/${shorten?'shorten':'cancel'}`,{version:leave.version,reason:result.value,...(shorten?{ends_on:end}:{})});await reload();await load();
        }catch(error){await Swal.fire({icon:'error',title:'Approved Leave',text:error.message});}finally{busy=false;}
    });
    return {load};
}
