import {showConfirm,showError,showSuccess} from './sweet-alert2.js';

export function setupWmiYears() {
    const root=document.querySelector('[data-wmi-years]');
    if(!root)return;
    const modalElement=document.getElementById('wmiYearModal');
    const modal=bootstrap.Modal.getOrCreateInstance(modalElement);
    const form=modalElement.querySelector('form');
    const input=form.elements.year;
    const problem=form.querySelector('[data-wmi-year-error]');
    const save=form.querySelector('[data-wmi-year-save]');
    const token=document.querySelector('meta[name="csrf-token"]')?.content;
    let editing=null,busy=false;
    const request=async(url,method,body)=>{
        const response=await fetch(url,{method,headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':token},...(body?{body:JSON.stringify(body)}:{})});
        const data=await response.json().catch(()=>({message:`Request failed (${response.status}). Refresh the page and try again.`}));
        if(!response.ok)throw Error(Object.values(data.errors||{}).flat()[0]||data.message);
        return data;
    };
    const open=(id=null,year='')=>{
        if(busy)return;
        editing=id;form.reset();input.value=year;problem.hidden=true;
        document.getElementById('wmiYearModalTitle').textContent=id?'Edit WMI Year':'Add WMI Year';
        save.querySelector('span').textContent=id?'Update Year':'Save Year';modal.show();
    };
    root.querySelector('[data-wmi-year-new]').addEventListener('click',()=>open());
    root.querySelectorAll('[data-wmi-year-edit]').forEach(button=>button.addEventListener('click',()=>open(button.dataset.id,button.dataset.year)));
    modalElement.addEventListener('shown.bs.modal',()=>input.focus());
    modalElement.addEventListener('hide.bs.modal',event=>{if(busy)event.preventDefault();});
    form.addEventListener('submit',async event=>{
        event.preventDefault();if(busy||!form.reportValidity())return;
        busy=true;save.disabled=true;input.disabled=true;problem.hidden=true;
        try{
            const data=await request(root.dataset.saveUrl+(editing?`/${editing}`:''),editing?'PUT':'POST',{year:input.value.trim()});
            busy=false;modal.hide();await showSuccess(data.message);window.location.reload();
        }catch(error){problem.textContent=error.message;problem.hidden=false;}
        finally{busy=false;save.disabled=false;input.disabled=false;}
    });
    root.querySelectorAll('[data-wmi-year-delete]').forEach(button=>button.addEventListener('click',async()=>{
        if(busy||button.disabled)return;
        const answer=await showConfirm('Delete WMI year?',`Delete WMI ${button.dataset.year}? Years linked to data cannot be deleted.`,'Delete Year');
        if(!answer.isConfirmed)return;
        busy=true;button.disabled=true;
        try{const data=await request(`${root.dataset.saveUrl}/${button.dataset.id}`,'DELETE');await showSuccess(data.message);window.location.reload();}
        catch(error){showError(error.message);button.disabled=false;}
        finally{busy=false;}
    }));
}
