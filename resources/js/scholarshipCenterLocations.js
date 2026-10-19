import { Modal } from 'bootstrap';
import Swal from 'sweetalert2';

export function initCenterLocations(root) {
    const element=root.querySelector('#scholarshipCenterLocationsModal');if(!element)return;
    const modal=Modal.getOrCreateInstance(element),form=element.querySelector('form'),trigger=root.querySelector('[data-edit-center-locations]');
    const inputs=[...form.querySelectorAll('[data-location-center-id]')],status=form.querySelector('[data-location-error]');
    let saved=new Map(inputs.map(input=>[input.name,input.value])),saving=false;
    const clear=()=>{status.hidden=true;status.textContent='';form.querySelectorAll('.invalid-feedback').forEach(node=>node.remove());inputs.forEach(input=>{input.classList.remove('is-invalid');input.removeAttribute('aria-invalid');});};
    const errors=messages=>{
        clear();status.textContent=Object.values(messages).flat().join(' ');status.hidden=false;
        inputs.forEach(input=>{
            const message=messages[`locations.${input.dataset.locationCenterId}`];if(!message)return;
            input.classList.add('is-invalid');input.setAttribute('aria-invalid','true');
            const feedback=document.createElement('div');feedback.className='invalid-feedback';feedback.textContent=[message].flat().join(' ');input.after(feedback);
        });form.querySelector('.is-invalid')?.focus();
    };
    trigger.addEventListener('click',()=>{if(!saving){clear();inputs.forEach(input=>input.value=saved.get(input.name));modal.show();}});
    form.addEventListener('submit',async event=>{
        event.preventDefault();if(saving)return;
        const invalid={};inputs.forEach(input=>{input.value=input.value.trim();if(!input.value||input.value.length>80)invalid[`locations.${input.dataset.locationCenterId}`]=['Enter a location of up to 80 characters.'];});
        if(Object.keys(invalid).length){errors(invalid);return;}
        const body=new FormData(form);clear();saving=true;form.querySelectorAll('button,input').forEach(input=>input.disabled=true);
        try{
            const response=await fetch(form.action,{method:'POST',body,headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'});
            const data=await response.json();if(!response.ok||response.redirected){errors(data.errors||{form:[data.message||'Unable to save exam center locations.']});return;}
            data.centers.forEach(center=>{
                const input=inputs.find(input=>String(input.dataset.locationCenterId)===String(center.id));if(input)input.value=center.location;
                root.querySelectorAll(`[data-center-location-id="${center.id}"]`).forEach(field=>{field.value=center.location;field.setAttribute('value',center.location);});
            });
            saved=new Map(inputs.map(input=>[input.name,input.value]));
            root.querySelectorAll('[data-examination-type] [name=version],[data-exam-settings] [name=version],[data-center-locations-form] [name=version]').forEach(input=>input.value=data.version);
            root.dispatchEvent(new CustomEvent('scholarship-exam-version',{detail:data.version}));
            modal.hide();Swal.fire({toast:true,position:'top-end',icon:'success',title:data.message,showConfirmButton:false,timer:3500,timerProgressBar:true});
        }catch(error){errors({form:['Unable to save exam center locations. Please try again.']});}
        finally{saving=false;form.querySelectorAll('button,input').forEach(input=>input.disabled=false);}
    });
    element.addEventListener('hidden.bs.modal',()=>trigger.focus());
    form.dataset.centerLocationsReady='true';
}
