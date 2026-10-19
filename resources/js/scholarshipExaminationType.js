import Swal from 'sweetalert2';

export function initExaminationType(root) {
    const form=root.querySelector('[data-examination-type]');if(!form)return;
    const input=form.elements.examination_type,edit=form.querySelector('[data-edit-examination-type]'),actions=form.querySelector('[data-examination-type-actions]'),error=form.querySelector('[data-examination-type-error]');
    let saved=input.value,saving=false;
    const mode=editing=>{input.readOnly=!editing;edit.hidden=editing;actions.hidden=!editing;input.classList.remove('is-invalid');error.textContent='';};
    edit.addEventListener('click',()=>{mode(true);input.focus();});
    form.querySelector('[data-cancel-examination-type]').addEventListener('click',()=>{if(!saving){input.value=saved;mode(false);}});
    form.addEventListener('submit',async event=>{
        event.preventDefault();if(saving||input.readOnly)return;
        if(!input.value.trim()){input.classList.add('is-invalid');error.textContent='Enter the examination type.';input.focus();return;}
        saving=true;form.querySelectorAll('button').forEach(button=>button.disabled=true);
        try{
            const response=await fetch(form.action,{method:'POST',body:new FormData(form),credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
            const data=await response.json();if(!response.ok||response.redirected)throw new Error(data.errors?Object.values(data.errors).flat().join(' '):data.message||'Unable to save the examination type.');
            saved=data.examination_type;input.value=saved;mode(false);form.elements.version.value=data.version;
            const settings=root.querySelector('[data-exam-settings] [name=version]');if(settings)settings.value=data.version;
            const locations=root.querySelector('[data-center-locations-form] [name=version]');if(locations)locations.value=data.version;
            root.dispatchEvent(new CustomEvent('scholarship-exam-version',{detail:data.version}));
            Swal.fire({toast:true,position:'top-end',icon:'success',title:data.message,showConfirmButton:false,timer:3500,timerProgressBar:true});
        }catch(reason){input.classList.add('is-invalid');error.textContent=reason.message||'Unable to save the examination type.';}
        finally{saving=false;form.querySelectorAll('button').forEach(button=>button.disabled=false);}
    });
    form.dataset.examinationTypeReady='true';
}
