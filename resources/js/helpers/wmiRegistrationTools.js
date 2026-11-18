import {showConfirm,showSuccess} from './sweet-alert2.js';
import {setupWmiYearPicker} from './wmiYearPicker.js';

export function setupWmiRegistrationTools() {
    const root=document.querySelector('[data-wmi-registration-tools]');if(!root)return;
    const token=document.querySelector('meta[name="csrf-token"]')?.content;
    document.querySelectorAll('[data-tool-picker],.wmi-registration-modal [data-registration-picker]').forEach(select=>setupWmiYearPicker(select,select.dataset.toolPicker||select.dataset.registrationPicker));
    const exportForm=root.querySelector('[data-tools-export]');
    if(exportForm){
        const year=exportForm.elements.wmi_year_id,center=exportForm.elements.wmi_exam_center_id;
        year.addEventListener('change',()=>{center.value='';for(const option of center.options)option.disabled=!!option.value&&!!year.value&&option.dataset.year!==year.value;center.dispatchEvent(new Event('change',{bubbles:true}));});
    }
    const request=async(url,method,payload)=>{
        const multipart=payload instanceof FormData;
        const response=await fetch(url,{method,headers:{Accept:'application/json','X-CSRF-TOKEN':token,...(multipart?{}:{'Content-Type':'application/json'})},body:multipart?payload:JSON.stringify(payload)});
        const data=await response.json().catch(()=>({message:'Request failed. Refresh the page and try again.'}));
        if(!response.ok)throw Error(Object.values(data.errors||{}).flat().join('\n')||data.message);return data;
    };
    const task=(selector,url,multipart,confirmation)=>{
        const taskForm=document.querySelector(selector);if(!taskForm)return;const taskModal=taskForm.closest('.modal');let running=false;
        taskModal.addEventListener('hide.bs.modal',event=>{if(running)event.preventDefault();});
        taskForm.addEventListener('submit',async event=>{
            event.preventDefault();if(running||!taskForm.reportValidity())return;
            if(confirmation&&!(await showConfirm('Assign rooms and seats?',confirmation,'Assign')).isConfirmed)return;
            const error=taskForm.querySelector('[data-task-error]'),button=taskForm.querySelector('[type="submit"]');
            const data=new FormData(taskForm);running=true;button.disabled=true;error.hidden=true;const caption=button.textContent;button.textContent='Processing…';
            try{const result=await request(url,'POST',multipart?data:Object.fromEntries(data));running=false;bootstrap.Modal.getOrCreateInstance(taskModal).hide();await showSuccess(result.message);window.location.reload();}
            catch(problem){error.textContent=problem.message;error.hidden=false;error.scrollIntoView({block:'nearest'});}finally{running=false;button.disabled=false;button.textContent=caption;}
        });
    };
    task('[data-id-import-form]',root.dataset.importUrl,true);
    task('[data-seat-form]',root.dataset.allocateUrl,false,'Assign all unseated candidates at this center? Existing seats will be preserved.');
    const dropzone=document.querySelector('.wmi-import-dropzone');if(!dropzone)return;const fileInput=dropzone.querySelector('input');
    ['dragenter','dragover'].forEach(name=>dropzone.addEventListener(name,event=>{event.preventDefault();dropzone.classList.add('is-dragging');}));
    dropzone.addEventListener('dragleave',()=>dropzone.classList.remove('is-dragging'));
    dropzone.addEventListener('drop',event=>{
        event.preventDefault();dropzone.classList.remove('is-dragging');
        if(event.dataTransfer.files.length!==1)return;
        fileInput.files=event.dataTransfer.files;fileInput.dispatchEvent(new Event('change',{bubbles:true}));
    });
}
