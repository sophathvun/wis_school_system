import {showConfirm,showError,showSuccess} from './sweet-alert2.js';
import {setupWmiYearPicker} from './wmiYearPicker.js';

export function setupWmiExamCenters() {
    const root=document.querySelector('[data-wmi-centers]');if(!root)return;
    const element=document.getElementById('wmiCenterModal');
    const modal=bootstrap.Modal.getOrCreateInstance(element), form=element.querySelector('form');
    const error=form.querySelector('[data-wmi-center-error]'),save=form.querySelector('[data-wmi-center-save]');
    const year=form.elements.wmi_year_id, name=form.elements.name, location=form.elements.location;
    const yearPicker=setupWmiYearPicker(year);
    const filter=document.getElementById('wmiCenterYearFilter');
    if(filter){setupWmiYearPicker(filter);filter.addEventListener('change',()=>filter.form.requestSubmit());}
    const token=document.querySelector('meta[name="csrf-token"]')?.content;
    let editing=null,busy=false,linked=false;
    const request=async(url,method,payload)=>{
        const response=await fetch(url,{method,headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':token},...(payload?{body:JSON.stringify(payload)}:{})});
        const data=await response.json().catch(()=>({message:`Request failed (${response.status}). Please refresh and retry.`}));
        if(!response.ok)throw Error(Object.values(data.errors||{}).flat()[0]||data.message);return data;
    };
    const open=(center=null)=>{
        if(busy)return;form.reset();editing=center?.id||null;linked=!!center?.linked;error.hidden=true;
        year.value=center?.wmi_year_id||document.getElementById('wmiCenterYearFilter')?.value||'';
        name.value=center?.name||'';location.value=center?.location||'';
        year.disabled=linked;yearPicker.sync();form.querySelector('[data-wmi-center-year-hint]').hidden=!linked;
        document.getElementById('wmiCenterModalTitle').textContent=editing?'Edit Exam Center':'Add Exam Center';
        save.querySelector('span').textContent=editing?'Update Exam Center':'Save Exam Center';modal.show();
    };
    root.querySelector('[data-wmi-center-new]').addEventListener('click',()=>open());
    root.querySelectorAll('[data-wmi-center-edit]').forEach(button=>button.addEventListener('click',()=>open(JSON.parse(button.dataset.center))));
    element.addEventListener('shown.bs.modal',()=>{if(year.value)name.focus();else yearPicker.focus();});
    element.addEventListener('hide.bs.modal',event=>{if(busy)event.preventDefault();});
    form.addEventListener('submit',async event=>{
        event.preventDefault();if(busy||!form.reportValidity())return;
        const payload={wmi_year_id:year.value,name:name.value.trim(),location:location.value.trim()};
        busy=true;save.disabled=true;year.disabled=true;name.disabled=true;location.disabled=true;error.hidden=true;
        yearPicker.sync();
        try{const result=await request(root.dataset.saveUrl+(editing?`/${editing}`:''),editing?'PUT':'POST',payload);busy=false;modal.hide();await showSuccess(result.message);window.location.reload();}
        catch(problem){error.textContent=problem.message;error.hidden=false;}
        finally{busy=false;save.disabled=false;year.disabled=linked;yearPicker.sync();name.disabled=false;location.disabled=false;}
    });
    root.querySelectorAll('[data-wmi-center-delete]').forEach(button=>button.addEventListener('click',async()=>{
        if(busy||button.disabled)return;
        const answer=await showConfirm('Delete exam center?',`Delete ${button.dataset.name}? Centers linked to data cannot be deleted.`,'Delete Center');if(!answer.isConfirmed)return;
        busy=true;button.disabled=true;
        try{const result=await request(`${root.dataset.saveUrl}/${button.dataset.id}`,'DELETE');await showSuccess(result.message);window.location.reload();}
        catch(problem){showError(problem.message);button.disabled=false;}finally{busy=false;}
    }));
}
