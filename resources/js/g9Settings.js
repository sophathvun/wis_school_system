import './khmer-calendar.js';
import {setupStaffLocationSelect} from './helpers/staffBirthplaceSelect.js';
import {enhanceSubjectTemporal} from './scholarshipTemporalInputs.js';
import {setupG9EoyCampusSettings} from './helpers/g9EoyCampusSettings.js';
import {showSuccess} from './helpers/sweet-alert2.js';
import {setupG9SettingsNavigation} from './helpers/g9SettingsNavigation.js';

const root=document.querySelector('[data-g9-settings]');
if(root){
    const year=root.querySelector('[data-setting-year]'),center=root.querySelector('[data-setting-center]'),location=root.querySelector('[data-setting-location]');
    const form=root.querySelector('[data-date-settings]'),save=root.querySelector('[data-setting-save]'),message=root.querySelector('[data-setting-message]');
    const preview=root.querySelector('[data-signature-preview]'),dropzone=root.querySelector('[data-signature-dropzone]'),file=root.querySelector('[data-signature-file]');
    const remove=root.querySelector('[data-signature-remove]'),uploadTitle=root.querySelector('[data-signature-title]');
    const centerFields=[...root.querySelectorAll('[data-center-field]')],sections=new Map(),combos=new Map();
    const eoySettings=setupG9EoyCampusSettings(root,year);
    const navigation=setupG9SettingsNavigation(root,()=>sections.forEach(({picker})=>picker.close()));
    let version=0,dirty=false,busy=false,loading=true,ready=false,canManage=false,reading=false,signatureChanged=false,signatureValue=null,uploadVersion=0;
    let lastYear=year.value,lastCenter=center.value,defaults={};
    [year,center].forEach(select=>{
        [...select.options].forEach(option=>option.dataset.en=option.textContent);
        const combo=setupStaffLocationSelect(root,select,{labelId:select.id+'Label',optionsId:select.id+'Options',locationLabel:select===year?'Academic Year':'Exam Center'});combos.set(select,combo);
        const menu=select.nextElementSibling.querySelector('.location-combobox-menu');menu.classList.add('g9-setting-center-menu');
        const position=()=>{
            const rect=select.nextElementSibling.getBoundingClientRect(),width=Math.min(520,document.documentElement.clientWidth-24);
            menu.style.width=width+'px';menu.style.left=(Math.max(12,Math.min(rect.left,document.documentElement.clientWidth-width-12))-rect.left)+'px';
        };
        combo.toggle.addEventListener('click',position);window.addEventListener('resize',position);
    });
    const sync=()=>{
        combos.forEach(combo=>combo.sync());
        root.querySelectorAll('.premium-form-field').forEach(field=>{const input=field.querySelector('select,input:not([type=hidden])');if(input)field.classList.toggle('has-value',input.tagName==='SELECT'?Boolean(input.selectedOptions[0]?.textContent):Boolean(input.value));});
    };
    const controls=()=>{
        const locked=busy||reading||!ready||!canManage;
        if(save)save.disabled=locked;
        combos.forEach(combo=>combo.toggle.disabled=busy||reading);
        location.disabled=locked;centerFields.forEach(input=>input.disabled=locked||!center.value);
        [dropzone,file,remove].forEach(input=>input.disabled=locked||!center.value);
        sections.forEach(({section,input,picker,inherit,edit,state})=>{
            inherit.disabled=locked||!center.value;
            const disabled=locked||Boolean(center.value&&inherit.checked);
            edit.hidden=!ready||!canManage||!center.value||!inherit.checked;edit.disabled=locked;
            state.textContent=busy?'Loading settings…':reading?'Please wait for the signature upload to finish.':!ready?'Dates are unavailable. Check the status message below.':!canManage?'Read only. A central user with G9 settings management permission can edit dates.':center.value&&inherit.checked?'Using the academic-year default. Click Edit Date to set a date for this exam center.':center.value?'Custom date for this exam center. Switch on the default above to inherit again.':'Editable academic-year default. Applies to centers using this default.';
            section.classList.toggle('is-inherited',Boolean(center.value&&inherit.checked));
            input.disabled=disabled;picker.input.disabled=disabled;picker.element.querySelector('[data-premium-date-toggle]').disabled=disabled;
            section.querySelector('[data-setting-solar]').disabled=disabled;section.querySelector('[data-setting-lunar]').disabled=disabled;
        });
    };
    const changed=()=>{if(!loading&&ready){dirty=true;message.textContent='Unsaved settings.';}sync();};
    const setDate=(key,value)=>{
        const {section,picker}=sections.get(key);picker.setValue(value?.date||'');
        section.querySelector('[data-setting-solar]').value=value?.solar||'';section.querySelector('[data-setting-lunar]').value=value?.lunar||'';
    };
    root.querySelectorAll('[data-date-section]').forEach(section=>{
        const input=section.querySelector('[data-setting-date]'),inherit=section.querySelector('[data-setting-inherit]'),edit=section.querySelector('[data-setting-edit-date]'),state=section.querySelector('[data-setting-date-state]');let picker;
        picker=enhanceSubjectTemporal(input,{onChange:()=>{
            if(!loading&&ready&&picker&&picker.input.validity.valid&&!(center.value&&inherit.checked)){
                section.querySelector('[data-setting-solar]').value=input.value?window.formatKhmerSolarDate(input.value,location.value.trim()):'';
                section.querySelector('[data-setting-lunar]').value=input.value?window.formatKhmerLunarDate(input.value):'';changed();
            }
        }});
        section.querySelector('.premium-form-field>label').htmlFor=picker.input.id;
        picker.input.setAttribute('aria-describedby',state.id);edit.setAttribute('aria-controls',picker.input.id);
        sections.set(section.dataset.dateSection,{section,input,picker,inherit,edit,state});
        edit.addEventListener('click',()=>{
            if(busy||reading||!ready||!canManage||!center.value||!inherit.checked)return;
            inherit.checked=false;picker.close();controls();changed();picker.input.focus();
        });
        inherit.addEventListener('change',()=>{
            if(inherit.checked){loading=true;setDate(section.dataset.dateSection,defaults[section.dataset.dateSection]);loading=false;}
            picker.close();controls();changed();
        });
    });
    const api=async(path,body)=>{
        const params=new URLSearchParams({academic_year_id:year.value});if(path==='/center')params.set('exam_center',center.value);
        const response=await fetch(root.dataset.base+path+(body?'':'?'+params),{method:body?'POST':'GET',credentials:'same-origin',headers:{Accept:'application/json',...(body?{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}:{})},body:body?JSON.stringify(body):undefined});
        const result=await response.json();if(!response.ok)throw new Error(Object.values(result.errors||{}).flat().join('\n')||result.message||'Settings unavailable.');return result;
    };
    const apply=data=>{
        loading=true;version=data.version;canManage=data.can_manage;location.value=data.location||'';defaults=data.defaults_dates||data.dates;
        centerFields.forEach(input=>input.value=data[input.dataset.centerField]||'');
        root.querySelectorAll('[data-center-only]').forEach(panel=>panel.hidden=!center.value);
        root.querySelector('[data-center-settings-title]').textContent=center.value?'Exam Center Settings':'Academic-Year Defaults';
        root.querySelector('[data-setting-scope]').textContent=center.value?'Changes apply only to '+center.value+' in the selected academic year. The exam date remains on each candidate record.':'Default dates apply to centers that have no date override. Select an exam center above to configure its province and signature.';
        sections.forEach(({section,inherit},key)=>{section.querySelector('[data-inherit-control]').hidden=!center.value;inherit.checked=Boolean(center.value&&!(data.date_overrides||[]).includes(key));setDate(key,data.dates[key]);});
        signatureValue=data.photo_signature||null;signatureChanged=false;uploadVersion++;reading=false;
        preview.hidden=!signatureValue;if(signatureValue)preview.src=signatureValue;else preview.removeAttribute('src');file.value='';uploadTitle.textContent='Drop signature here';
        dirty=false;ready=true;lastYear=year.value;lastCenter=center.value;loading=false;sync();
    };
    const closeCalendars=()=>sections.forEach(({picker})=>picker.close());
    const load=async(refreshCenters=false)=>{
        busy=true;ready=false;closeCalendars();controls();message.textContent='Loading settings…';
        try{
            if(!year.value){message.textContent='No academic years available.';return;}
            let data;
            if(refreshCenters){
                data=await api('/dates');const old=center.value;
                center.replaceChildren(new Option('Academic-year defaults',''),...(data.report_centers||[]).map(item=>new Option(item.exam_center,item.exam_center)));
                [...center.options].forEach(option=>option.dataset.en=option.textContent);
                center.value=[...center.options].some(option=>option.value===old)&&old?old:(data.report_centers?.[0]?.exam_center||'');
            }
            apply(center.value?await api('/center'):(data||await api('/dates')));
            message.textContent=center.value?'Settings loaded for '+center.value+'.':'Academic-year defaults loaded.';
        }catch(failure){message.textContent=failure.message;}
        finally{busy=false;controls();sync();}
    };
    const canChange=()=>!busy&&!reading&&(!dirty||confirm('Discard unsaved exam-center settings?'));
    year.addEventListener('change',()=>{
        if(!canChange()||!eoySettings.canChange()){year.value=lastYear;sync();return;}
        dirty=false;load(true);eoySettings.load();
    });
    center.addEventListener('change',()=>{if(!canChange()){center.value=lastCenter;sync();return;}dirty=false;load();});
    form.addEventListener('input',event=>{if(!event.target.closest('.date-picker')&&event.target!==file)changed();});
    location.addEventListener('input',()=>{
        sections.forEach(({section,input,picker,inherit})=>{if(!(center.value&&inherit.checked)&&input.value&&picker.valid())section.querySelector('[data-setting-solar]').value=window.formatKhmerSolarDate(input.value,location.value.trim());});changed();
    });
    const readSignature=selected=>{
        if(!selected||busy||reading||!canManage||!ready)return;
        if(!['image/png','image/jpeg'].includes(selected.type)||selected.size>2*1024*1024){message.textContent='Choose a PNG or JPG signature up to 2 MB.';file.value='';return;}
        const selectedYear=year.value,selectedCenter=center.value,current=++uploadVersion,reader=new FileReader();reading=true;controls();
        reader.onload=()=>{if(current!==uploadVersion||year.value!==selectedYear||center.value!==selectedCenter)return;signatureValue=reader.result;signatureChanged=true;preview.src=reader.result;preview.hidden=false;uploadTitle.textContent=selected.name;reading=false;controls();changed();};
        reader.onerror=()=>{if(current!==uploadVersion)return;reading=false;controls();message.textContent='Signature image could not be read.';};reader.readAsDataURL(selected);
    };
    file.addEventListener('change',()=>readSignature(file.files[0]));dropzone.addEventListener('click',()=>file.click());
    dropzone.addEventListener('dragover',event=>{event.preventDefault();if(!dropzone.disabled){event.dataTransfer.dropEffect='copy';dropzone.classList.add('is-dragging');}});
    dropzone.addEventListener('dragleave',event=>{if(!dropzone.contains(event.relatedTarget))dropzone.classList.remove('is-dragging');});
    dropzone.addEventListener('drop',event=>{event.preventDefault();dropzone.classList.remove('is-dragging');if(dropzone.disabled)return;if(event.dataTransfer.files.length!==1){message.textContent='Drop one signature image.';return;}readSignature(event.dataTransfer.files[0]);});
    remove.addEventListener('click',()=>{if(remove.disabled)return;uploadVersion++;signatureValue=null;signatureChanged=true;preview.removeAttribute('src');preview.hidden=true;file.value='';uploadTitle.textContent='Drop signature here';changed();});
    form.addEventListener('submit',async event=>{
        event.preventDefault();if(busy||reading||!ready||!canManage||!save)return;
        const invalid=[...sections.values()].find(({picker,inherit})=>!(center.value&&inherit.checked)&&!picker.valid());
        if(invalid){navigation.activate('dates');invalid.picker.input.focus();invalid.picker.input.reportValidity();return;}
        const dates={};sections.forEach(({section,input,inherit},key)=>dates[key]={date:input.value,solar:section.querySelector('[data-setting-solar]').value,lunar:section.querySelector('[data-setting-lunar]').value,...(center.value?{inherit:inherit.checked}:{})});
        const payload={academic_year_id:year.value,version,location:location.value.trim(),dates};
        if(center.value)Object.assign(payload,{exam_center:center.value,...Object.fromEntries(centerFields.map(input=>[input.dataset.centerField,input.value.trim()])),...(signatureChanged?{photo_signature:signatureValue}:{})});
        busy=true;closeCalendars();controls();message.textContent='Saving settings…';
        try{apply(await api(center.value?'/center':'/dates',payload));message.textContent=center.value?'Exam-center settings saved.':'Academic-year defaults saved.';showSuccess('Settings saved');}
        catch(failure){message.textContent=failure.message;}
        finally{busy=false;controls();}
    });
    window.addEventListener('beforeunload',event=>{if(dirty||busy||reading){event.preventDefault();event.returnValue='';}});
    load(true);
}
