import {setupStaffLocationSelect} from './staffBirthplaceSelect.js';
import {showSuccess} from './sweet-alert2.js';

export function setupG9EoyCampusSettings(root,year) {
    const panel=root.querySelector('[data-eoy-campus-settings]');
    if(!panel)return {canChange:()=>true,load(){}};
    const form=panel.querySelector('form'),campus=form.elements.campus_id,save=panel.querySelector('[data-eoy-campus-save]'),message=panel.querySelector('[data-eoy-campus-message]');
    const names=[form.elements.compiler_name,form.elements.director_name];
    [...campus.options].forEach(option=>option.dataset.en=option.textContent);
    const combo=setupStaffLocationSelect(form,campus,{labelId:'g9EoyCampusLabel',optionsId:'g9EoyCampusOptions',locationLabel:'Campus'});
    let version=0,dirty=false,busy=false,canManage=false,requestId=0,lastCampus=campus.value;
    const fields=()=>names.forEach(input=>{input.disabled=busy||!canManage;input.closest('.premium-form-field').classList.toggle('has-value',input.value!=='');});
    const request=async body=>{
        const response=await fetch(panel.dataset.url+(body?'':'?'+new URLSearchParams({academic_year_id:year.value,campus_id:campus.value})),{
            method:body?'POST':'GET',credentials:'same-origin',headers:{Accept:'application/json',...(body?{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}:{})},body:body?JSON.stringify(body):undefined,
        });
        const result=await response.json();if(!response.ok)throw Error(Object.values(result.errors||{}).flat().join('\n')||result.message||'Campus names unavailable.');return result;
    };
    const apply=data=>{version=data.version;canManage=data.can_manage;names.forEach(input=>input.value=data[input.name]||'');dirty=false;save.hidden=!canManage;};
    const load=async()=>{
        const id=++requestId;lastCampus=campus.value;dirty=false;busy=true;canManage=false;save.disabled=true;combo.toggle.disabled=true;names.forEach(input=>input.value='');fields();
        if(!year.value||!campus.value){busy=false;message.textContent='Select an academic year and campus.';return;}
        message.textContent='Loading campus names...';
        try{const data=await request();if(id!==requestId)return;apply(data);message.textContent=version?'Campus names loaded.':'No names saved for this campus and academic year yet.';}
        catch(error){if(id===requestId)message.textContent=error.message;}
        finally{if(id===requestId){busy=false;combo.toggle.disabled=false;save.disabled=!canManage;fields();}}
    };
    const canChange=()=>!busy&&(!dirty||confirm('Discard unsaved annual score card campus names?'));
    campus.addEventListener('change',()=>{if(!canChange()){campus.value=lastCampus;combo.sync();return;}load();});
    names.forEach(input=>input.addEventListener('input',()=>{dirty=true;message.textContent='Unsaved campus names.';fields();}));
    form.addEventListener('submit',async event=>{
        event.preventDefault();if(busy||!canManage||!form.reportValidity())return;
        busy=true;save.disabled=true;combo.toggle.disabled=true;fields();message.textContent='Saving campus names...';
        try{apply(await request({academic_year_id:year.value,campus_id:campus.value,version,...Object.fromEntries(names.map(input=>[input.name,input.value.trim()]))}));message.textContent='Campus names saved.';showSuccess('Campus names saved');}
        catch(error){message.textContent=error.message;}
        finally{busy=false;save.disabled=false;combo.toggle.disabled=false;fields();}
    });
    window.addEventListener('beforeunload',event=>{if(dirty||busy){event.preventDefault();event.returnValue='';}});
    load();return {load,canChange};
}
