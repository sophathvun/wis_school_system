import {showConfirm,showError,showSuccess} from './sweet-alert2.js';
import {setupWmiYearPicker} from './wmiYearPicker.js';

export function setupWmiSchools() {
    const root=document.querySelector('[data-wmi-schools]');if(!root)return;
    const element=document.getElementById('wmiSchoolModal'),form=element.querySelector('form');
    const modal=bootstrap.Modal.getOrCreateInstance(element),save=form.querySelector('[data-wmi-school-save]'),error=form.querySelector('[data-wmi-school-error]');
    const fields=['name_en','name_kh','school_type','province_id','remark'];
    const province=setupWmiYearPicker(form.elements.province_id,'Province / City','ti-map-pin');
    const typePicker=setupWmiYearPicker(form.elements.school_type,'School Type','ti-school');
    const filter=document.getElementById('wmiSchoolProvinceFilter');setupWmiYearPicker(filter,'Province / City','ti-map-pin');
    filter.addEventListener('change',()=>filter.form.requestSubmit());
    const token=document.querySelector('meta[name="csrf-token"]')?.content;
    let editing=null,busy=false;
    const request=async(url,method,payload)=>{
        const response=await fetch(url,{method,headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':token},...(payload?{body:JSON.stringify(payload)}:{})});
        const data=await response.json().catch(()=>({message:'Request failed. Please refresh and retry.'}));
        if(!response.ok)throw Error(Object.values(data.errors||{}).flat()[0]||data.message);return data;
    };
    const sync=()=>{province.sync();typePicker.sync();};
    const open=(school=null)=>{
        if(busy)return;form.reset();editing=school?.id||null;error.hidden=true;
        fields.forEach(key=>{form.elements[key].value=school?.[key]??'';});
        if(!school)form.elements.province_id.value=filter.value;sync();
        element.querySelector('.modal-title').textContent=editing?'Edit School':'Add School';save.querySelector('span').textContent=editing?'Update School':'Save School';modal.show();
    };
    root.querySelector('[data-wmi-school-new]').addEventListener('click',()=>open());
    root.querySelectorAll('[data-wmi-school-edit]').forEach(button=>button.addEventListener('click',()=>open(JSON.parse(button.dataset.school))));
    element.addEventListener('shown.bs.modal',()=>form.elements.name_en.focus());
    element.addEventListener('hide.bs.modal',event=>{if(busy)event.preventDefault();});
    form.addEventListener('submit',async event=>{
        event.preventDefault();if(busy||!form.reportValidity())return;
        const payload=Object.fromEntries(fields.map(key=>[key,form.elements[key].value.trim()]));
        busy=true;error.hidden=true;save.disabled=true;fields.forEach(key=>{form.elements[key].disabled=true;});sync();
        try{const result=await request(root.dataset.saveUrl+(editing?`/${editing}`:''),editing?'PUT':'POST',payload);busy=false;modal.hide();await showSuccess(result.message);window.location.reload();}
        catch(problem){error.textContent=problem.message;error.hidden=false;}
        finally{busy=false;save.disabled=false;fields.forEach(key=>{form.elements[key].disabled=false;});sync();}
    });
    root.querySelectorAll('[data-wmi-school-delete]').forEach(button=>button.addEventListener('click',async()=>{
        if(busy||button.disabled)return;
        const answer=await showConfirm('Delete school?',`Delete ${button.dataset.name}? Schools linked to data cannot be deleted.`,'Delete School');if(!answer.isConfirmed)return;
        busy=true;button.disabled=true;
        try{const result=await request(`${root.dataset.saveUrl}/${button.dataset.id}`,'DELETE');await showSuccess(result.message);window.location.reload();}
        catch(problem){showError(problem.message);button.disabled=false;}finally{busy=false;}
    }));
}
