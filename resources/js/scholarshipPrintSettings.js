import { Modal } from 'bootstrap';
import Swal from 'sweetalert2';
import { initPremiumDatePicker } from './premiumDatePicker.js';

export function initRankPrintSettings(root) {
    const element=root.querySelector('#scholarshipPrintSettingsModal');
    if(!element)return;
    const modal=Modal.getOrCreateInstance(element),form=element.querySelector('form');
    const campus=form.elements.campus_id,year=form.elements.academic_year_id;
    const save=form.querySelector('[data-save-print-settings]'),status=form.querySelector('[data-print-settings-error]');
    const centralShortcut=form.querySelector('[data-print-central-shortcut]');
    let centralCampus=null,requiresVerifier=false;
    let staff=[];
    const roles=['prepared','checked','approved'],pickers=new Map();
    const dates=new Map(roles.map(role=>{
        const picker=form.querySelector(`[name="${role}_date"]`).closest('[data-premium-date-picker]');
        return [role,{picker,input:picker.querySelector('[data-premium-date-input]'),toggle:picker.querySelector('[data-premium-date-toggle]'),calendar:initPremiumDatePicker(picker)}];
    }));
    const fields=['prepared_by_name','prepared_date','checked_by_name','checked_date','approved_by_name','approved_date'];
    const labels={prepared_by_name:'Prepared by',prepared_date:'Preparation Date',checked_by_name:'Checked and Verified by',checked_date:'Verification Date',approved_by_name:'Approved by',approved_date:'Approval Date'};
    let loading=false,saving=false,ready=false,sequence=0,controller;
    const closePickers=()=>pickers.forEach(picker=>picker.close());
    const closeDates=()=>dates.forEach(date=>date.calendar.close());
    form.addEventListener('focusin',event=>{
        dates.forEach(date=>{if(!date.picker.contains(event.target))date.calendar.close();});
        if(event.target.closest('[data-premium-date-picker]'))closePickers();
    });
    roles.forEach(role=>{
        const picker=form.querySelector(`[data-signature-role="${role}"]`),input=picker.querySelector('[data-staff-input]'),results=picker.querySelector('[data-staff-results]');
        const id=form.elements[`${role}_staff_id`],position=form.querySelector(`[data-staff-position="${role}"]`);
        const close=()=>{results.hidden=true;input.setAttribute('aria-expanded','false');};
        const show=(term='')=>{
            if(loading||saving||!ready)return;
            closePickers();results.replaceChildren();
            const matches=staff.filter(person=>[person.name,person.name_kh,person.code,person.position].join(' ').toLowerCase().includes(term.trim().toLowerCase()));
            matches.slice(0,50).forEach(person=>{
                const option=document.createElement('button');option.type='button';option.tabIndex=-1;option.dataset.staffId=person.id;option.setAttribute('role','option');option.setAttribute('aria-selected',String(id.value)===String(person.id)?'true':'false');
                option.textContent=person.name;const detail=document.createElement('small');detail.textContent=[person.code,person.position||'No position assigned in HRM'].filter(Boolean).join(' · ');option.append(detail);
                option.addEventListener('click',()=>{id.value=person.id;input.value=person.name;position.textContent=`Position: ${person.position||'Not assigned in HRM'}`;input.classList.remove('is-invalid');input.removeAttribute('aria-invalid');input.removeAttribute('aria-describedby');picker.parentElement.querySelector('.invalid-feedback')?.remove();input.focus();close();});
                results.append(option);
            });
            if(!matches.length){const empty=document.createElement('div');empty.className='small text-secondary p-2';empty.textContent='No matching HRM staff.';results.append(empty);}
            results.hidden=false;input.setAttribute('aria-expanded','true');
            const body=form.querySelector('.modal-body'),overflow=results.getBoundingClientRect().bottom-body.getBoundingClientRect().bottom;
            if(overflow>0)body.scrollTop+=overflow+4;
        };
        input.addEventListener('focus',()=>show());
        input.addEventListener('click',()=>show());
        picker.addEventListener('focusout',()=>setTimeout(()=>{if(!picker.contains(document.activeElement))close();},0));
        input.addEventListener('input',()=>{id.value='';position.textContent='Position: —';show(input.value);});
        input.addEventListener('keydown',event=>{
            if(event.key==='Escape'&&!results.hidden){event.preventDefault();event.stopPropagation();close();}
            if(event.key==='ArrowDown'){event.preventDefault();if(results.hidden)show(input.value);results.querySelector('button')?.focus();}
            if(event.key==='Enter'&&!results.hidden){event.preventDefault();results.querySelector('button')?.click();}
        });
        results.addEventListener('keydown',event=>{
            const options=[...results.querySelectorAll('button')],index=options.indexOf(document.activeElement);
            if(['ArrowDown','ArrowUp'].includes(event.key)){event.preventDefault();options[(index+(event.key==='ArrowDown'?1:-1)+options.length)%options.length]?.focus();}
            if(event.key==='Escape'){event.preventDefault();event.stopPropagation();input.focus();close();}
        });
        pickers.set(role,{close,input,id,position});
    });
    element.addEventListener('pointerdown',event=>{if(!event.target.closest('.signature-staff-picker'))closePickers();});
    const busy=()=>{
        [campus,...fields.map(name=>form.elements[name])].forEach(input=>input.disabled=loading||saving);
        roles.forEach(role=>{
            const active=role!=='checked'||requiresVerifier,disabled=loading||saving||!active,date=dates.get(role),picker=pickers.get(role);
            form.querySelector(`[data-print-signature-role="${role}"]`).hidden=!active;
            picker.input.disabled=disabled;picker.id.disabled=disabled;picker.input.required=active;
            form.elements[`${role}_date`].disabled=disabled;
            date.input.disabled=disabled;date.toggle.disabled=disabled;date.input.required=active;
        });
        save.disabled=loading||saving||!ready;
        centralShortcut.disabled=loading||saving;
        form.setAttribute('aria-busy',String(loading||saving));
    };
    const clearErrors=()=>{
        status.hidden=true;status.textContent='';
        form.querySelectorAll('.invalid-feedback').forEach(node=>node.remove());
        form.querySelectorAll('.is-invalid').forEach(input=>{input.classList.remove('is-invalid');input.removeAttribute('aria-invalid');input.removeAttribute('aria-describedby');});
    };
    const errors=messages=>{
        clearErrors();
        status.textContent=Object.values(messages).flat().join(' ');status.hidden=false;
        Object.entries(messages).forEach(([name,text])=>{
            const input=name.endsWith('_date')?dates.get(name.replace('_date',''))?.input:form.elements[name.endsWith('_staff_id')?name.replace('_staff_id','_by_name'):name];if(!input)return;
            const feedback=document.createElement('div');feedback.className='invalid-feedback';feedback.id=`rank-print-error-${name}`;feedback.textContent=[text].flat().join(' ');feedback.setAttribute('role','alert');
            input.classList.add('is-invalid');input.setAttribute('aria-invalid','true');input.setAttribute('aria-describedby',feedback.id);input.after(feedback);
        });
        form.querySelector('.is-invalid')?.focus();
    };
    const load=async(yearId,campusId)=>{
        controller?.abort();controller=new AbortController();const current=++sequence;
        loading=true;ready=false;closePickers();closeDates();clearErrors();busy();
        const url=new URL(form.action,location.href);url.searchParams.set('academic_year_id',yearId);
        if(campusId)url.searchParams.set('campus_id',campusId);
        try{
            const response=await fetch(url,{headers:{Accept:'application/json'},signal:controller.signal});
            const data=await response.json();if(current!==sequence)return;
            if(!response.ok)throw new Error(data.message||'Unable to load campus print settings.');
            year.value=data.academic_year.id;form.querySelector('[data-print-year-label]').value=data.academic_year.label;
            campus.replaceChildren(...data.campuses.map(item=>new Option(item.label,item.id,false,String(item.id)===String(data.campus_id))));
            centralCampus=data.campuses.find(item=>item.is_central)||null;
            const isCentral=String(centralCampus?.id)===String(data.campus_id);
            requiresVerifier=data.requires_verifier===true;
            element.querySelector('#scholarshipPrintSettingsTitle').textContent=isCentral?'Central Print Settings':'Campus Print Settings';
            form.querySelector('[data-print-scope-label]').textContent=centralCampus?'Report Scope':'Campus';
            form.querySelector('[data-print-scope-description]').textContent=isCentral?'Choose HRM staff for the all-campus report; positions fill automatically.':'Choose HRM staff for this campus; positions fill automatically.';
            centralShortcut.hidden=!centralCampus||isCentral;
            fields.forEach(name=>form.elements[name].value=data.values[name]??'');
            dates.forEach((date,role)=>date.calendar.setValue(data.values[`${role}_date`]));
            staff=data.staff||[];
            roles.forEach(role=>{
                const picker=pickers.get(role),selected=staff.find(person=>String(person.id)===String(data.values[`${role}_staff_id`]));
                picker.id.value=selected?.id??'';picker.input.value=selected?.name??data.values[`${role}_by_name`]??'';
                picker.position.textContent=`Position: ${selected?(selected.position||'Not assigned in HRM'):(data.values[`${role}_position`]||'—')}`;
            });ready=true;
        }catch(error){if(error.name!=='AbortError'&&current===sequence)errors({form:[error.message||'Unable to load campus print settings.']});}
        finally{if(current===sequence){loading=false;busy();}}
    };
    root.addEventListener('click',event=>{
        const button=event.target.closest('[data-rank-print-settings]');if(!button||saving)return;
        const yearId=root.querySelector('#scholarshipYear')?.value;
        if(!yearId){Swal.fire({icon:'info',title:'Select an academic year',text:'Choose an academic year before opening Print Settings.'});return;}
        if(Swal.getPopup()?.classList.contains('swal2-toast'))Swal.close();
        requiresVerifier=false;fields.forEach(name=>form.elements[name].value='');form.querySelector('[data-print-year-label]').value='Loading…';
        element.querySelector('#scholarshipPrintSettingsTitle').textContent='Print Settings';centralShortcut.hidden=true;form.querySelector('.modal-body').scrollTop=0;
        modal.show();load(yearId,root.querySelector('#scholarshipCampus')?.value);
    });
    campus.addEventListener('change',()=>load(year.value,campus.value));
    centralShortcut.addEventListener('click',()=>{if(centralCampus&&!loading&&!saving)load(year.value,centralCampus.id);});
    element.addEventListener('hidden.bs.modal',()=>{
        controller?.abort();sequence++;loading=false;ready=false;closePickers();closeDates();busy();root.querySelector('[data-rank-print-settings]')?.focus();
    });
    form.addEventListener('submit',async event=>{
        event.preventDefault();if(loading||saving||!ready)return;
        const invalid={};
        roles.forEach(role=>{
            if(role==='checked'&&!requiresVerifier)return;
            const picker=pickers.get(role),selected=staff.find(person=>String(person.id)===picker.id.value);
            if(!selected||picker.input.value.trim()!==String(selected.name).trim())invalid[`${role}_staff_id`]=[`Please select ${labels[`${role}_by_name`]} from the HRM Staff List.`];
            const date=dates.get(role);date.calendar.sync();
            if(!form.elements[`${role}_date`].value)invalid[`${role}_date`]=[date.input.value.trim()?`Please enter a valid ${labels[`${role}_date`]} as DD-MMM-YYYY.`:`Please fill in ${labels[`${role}_date`]}.`];
        });
        if(Object.keys(invalid).length){errors(invalid);return;}
        const body=new FormData(form);saving=true;closePickers();closeDates();clearErrors();busy();
        try{
            const response=await fetch(form.action,{method:'POST',body,headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
            const data=await response.json();
            if(!response.ok){errors(data.errors||{form:[data.message||'Unable to save print settings.']});return;}
            modal.hide();Swal.fire({toast:true,position:'top-end',icon:'success',title:data.message||'Campus print settings saved.',showConfirmButton:false,showCloseButton:true,timer:3500});
        }catch(error){errors({form:['Unable to save print settings. Please try again.']});}
        finally{saving=false;busy();if(element.classList.contains('show'))form.querySelector('.is-invalid')?.focus();}
    });
}
