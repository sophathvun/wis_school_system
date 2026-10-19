import { Modal } from 'bootstrap';
import Swal from 'sweetalert2';

export function initInvigilators(root) {
    const panel=root.querySelector('[data-invigilators]');if(!panel)return;
    const staff=JSON.parse(panel.querySelector('[data-invigilator-staff]').textContent),rooms=JSON.parse(panel.querySelector('[data-invigilator-rooms]').textContent);
    const personForm=panel.querySelector('[data-invigilator-person]'),modalElement=panel.querySelector('#scholarshipInvigilatorModal');
    const syncSelect=select=>{const button=panel.querySelector(`#${select.id}Toggle`);if(button){button.textContent=select.selectedOptions[0]?.textContent||'Select…';button.disabled=select.disabled;}};
    let editing=false;
    if(personForm){
        const modal=Modal.getOrCreateInstance(modalElement),source=personForm.elements.source,staffSelect=personForm.elements.staff_id;
        const clearErrors=form=>{form.querySelectorAll('.is-invalid').forEach(n=>n.classList.remove('is-invalid'));form.querySelectorAll('[data-invigilator-error],.invalid-feedback').forEach(n=>n.remove());};
        const setSource=()=>{
            const hrm=!editing&&source.value==='hrm';panel.querySelector('[data-invigilator-hrm]').hidden=!hrm;staffSelect.required=hrm;
            personForm.elements.staff_code.readOnly=hrm;
            if(!hrm&&!editing&&source.value==='external'&&!personForm.elements.campus.value)personForm.elements.campus.value='External';
        };
        source.addEventListener('change',()=>{
            for(const name of ['staff_code','name_en','gender','campus','position','phone','email','organization','notes'])personForm.elements[name].value='';
            staffSelect.value='';syncSelect(staffSelect);setSource();
        });
        staffSelect.addEventListener('change',()=>{
            const selected=staff.find(s=>String(s.id)===staffSelect.value);if(!selected)return;
            for(const name of ['staff_code','name_en','gender','campus','position','phone','email'])personForm.elements[name].value=selected[name]||'';
            personForm.elements.organization.value='WIS';syncSelect(staffSelect);
        });
        panel.querySelector('[data-invigilator-add]').addEventListener('click',()=>{
            editing=false;personForm.reset();clearErrors(personForm);personForm.action=panel.dataset.personCreate;
            panel.querySelector('[data-invigilator-source]').hidden=false;panel.querySelector('#invigilatorModalTitle').textContent='Add Invigilator';setSource();syncSelect(staffSelect);modal.show();
        });
        panel.querySelectorAll('[data-invigilator-edit]').forEach(button=>button.addEventListener('click',()=>{
            editing=true;personForm.reset();clearErrors(personForm);const values=JSON.parse(button.dataset.invigilatorEdit);
            for(const [key,value]of Object.entries(values))if(personForm.elements[key])personForm.elements[key].value=value??'';
            personForm.action=panel.dataset.personUpdate.replace('PERSON',String(values.id));panel.querySelector('[data-invigilator-source]').hidden=true;
            panel.querySelector('#invigilatorModalTitle').textContent=`Edit Invigilator · ${values.code}`;setSource();modal.show();
        }));
    }
    const assignment=panel.querySelector('[data-invigilator-assignment]');
    if(assignment){
        const center=assignment.elements.exam_center_id,session=assignment.elements.session_id,room=assignment.elements.room_number;
        const update=()=>{
            const number=Number(session.selectedOptions[0]?.dataset.number),previous=room.value;
            const options=rooms.filter(r=>String(r.center_id)===center.value&&r.subjects[number-1]);
            room.replaceChildren(new Option('Select room',''),...options.map(r=>new Option(`Room ${r.room} · Grade ${r.grade} · ${r.subjects[number-1]}${r.schedules?.[number-1]?` · ${r.schedules[number-1].exam_date} ${r.schedules[number-1].starts_at}–${r.schedules[number-1].ends_at}`:' · Not scheduled'}`,r.room)));
            if([...room.options].some(o=>o.value===previous))room.value=previous;syncSelect(room);
        };
        center.addEventListener('change',update);session.addEventListener('change',update);update();
    }
    const showErrors=(form,errors)=>{
        form.querySelectorAll('.is-invalid').forEach(n=>{n.classList.remove('is-invalid');n.removeAttribute('aria-invalid');});form.querySelectorAll('[data-invigilator-error],.invalid-feedback').forEach(n=>n.remove());
        const messages=Object.values(errors).flat(),status=document.createElement('div');status.className='alert alert-danger mt-3';status.dataset.invigilatorError='';status.setAttribute('role','alert');status.textContent=messages.join(' ');
        (form.querySelector('.modal-body')||form).append(status);
        for(const [key,value]of Object.entries(errors)){
            const fieldName=key.replace(/\.([^.]+)/g,'[$1]'),input=form.elements.namedItem(fieldName);if(!input?.classList)continue;
            input.classList.add('is-invalid');input.setAttribute('aria-invalid','true');const feedback=document.createElement('div');feedback.className='invalid-feedback';feedback.textContent=[value].flat().join(' ');input.after(feedback);
            const toggle=input.id&&panel.querySelector(`#${input.id}Toggle`);toggle?.classList.add('is-invalid');
        }
        Swal.fire({toast:true,position:'top-end',icon:'error',title:messages[0]||'Please check the form.',showConfirmButton:false,timer:5000});
    };
    const request=async(form)=>{
        const response=await fetch(form.action,{method:'POST',body:new FormData(form),credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
        const data=await response.json().catch(()=>({message:'Unable to read the response. Please try again.'}));
        if(!response.ok||response.redirected)throw data;return data;
    };
    const pending=new WeakSet();
    panel.querySelectorAll('[data-invigilator-form]').forEach(form=>form.addEventListener('submit',async event=>{
        event.preventDefault();if(pending.has(form)||!form.reportValidity())return;pending.add(form);
        try{
            if(form.matches('[data-session-link-form]')){
                const selected=[...form.querySelectorAll('[name="session_ids[]"]:checked')].map(input=>input.value);
                if(selected.length<2 || !selected.includes(form.elements.source_session_id.value)){
                    showErrors(form,{session_ids:['Select at least two sessions, including the source session.']});return;
                }
            }
            if(form.dataset.invigilatorConfirm){const result=await Swal.fire({icon:'question',title:'Confirm action',text:form.dataset.invigilatorConfirm,showCancelButton:true,confirmButtonText:'Confirm',cancelButtonText:'Cancel'});if(!result.isConfirmed)return;}
            const buttons=[...form.querySelectorAll('button[type=submit],button:not([type])')];buttons.forEach(b=>b.disabled=true);
            try{const data=await request(form);if(data.redirect)window.location.assign(data.redirect);else Swal.fire({toast:true,position:'top-end',icon:'success',title:data.message,showConfirmButton:false,timer:3500});}
            finally{buttons.forEach(b=>b.disabled=false);}
        }catch(error){showErrors(form,error.errors||{form:[error.message||'Unable to save. Please try again.']});}
        finally{pending.delete(form);}
    }));
    const upload=panel.querySelector('[data-invigilator-upload]'),commit=panel.querySelector('[data-invigilator-import]'),preview=panel.querySelector('[data-invigilator-preview]');
    if(upload){
        const reset=()=>{commit.hidden=true;commit.elements.token.value='';commit.elements.acknowledge.checked=false;preview.hidden=true;preview.replaceChildren();};
        upload.addEventListener('change',reset);
        upload.addEventListener('submit',async event=>{
            event.preventDefault();if(pending.has(upload)||!upload.reportValidity())return;pending.add(upload);reset();const button=upload.querySelector('button[type=submit]');button.disabled=true;
            try{
                const data=await request(upload);preview.hidden=false;
                const summary=document.createElement('p');summary.className='fw-semibold';summary.textContent=`New: ${data.created} | Existing: ${data.updated} | Room assignments: ${data.assignments} | Errors: ${data.error_count}`;preview.append(summary);
                if(data.errors.length){const list=document.createElement('ul');list.className='text-danger';data.errors.forEach(message=>{const item=document.createElement('li');item.textContent=message;list.append(item);});preview.append(list);}
                const table=(headers,rows)=>{
                    if(!rows.length)return;const wrapper=document.createElement('div');wrapper.className='table-responsive';const table=document.createElement('table');table.className='table table-sm';
                    const head=table.createTHead().insertRow();headers.forEach(label=>{const th=document.createElement('th');th.textContent=label;head.append(th);});const body=table.createTBody();
                    rows.forEach(values=>{const row=body.insertRow();values.forEach(value=>{row.insertCell().textContent=String(value);});});wrapper.append(table);preview.append(wrapper);
                };
                table(['Code','Name','Phone'],data.people.map(p=>[p.code,p.name,p.phone]));
                table(['Code','Invigilator','Exam Center','Session','Room'],data.duties.map(d=>[d.code,d.name,d.center,d.session,d.room]));
                const note=document.createElement('p');note.className='small text-secondary';note.textContent='Preview shows up to 100 people and 100 assignments. Import applies all validated rows.';preview.append(note);
                if(data.token&&!data.error_count){commit.elements.token.value=data.token;commit.hidden=false;}
            }catch(error){showErrors(upload,error.errors||{file:[error.message||'Unable to preview the workbook.']});}
            finally{button.disabled=false;pending.delete(upload);}
        });
    }
}
