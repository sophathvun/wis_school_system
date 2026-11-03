import Swal from 'sweetalert2';
import {enhanceSubjectTemporal} from './scholarshipTemporalInputs.js';
import {setupStaffLocationSelect} from './helpers/staffBirthplaceSelect.js';
import {initAttendanceSettingsTabs} from './attendanceSettingsTabs.js';

initAttendanceSettingsTabs();

const root=document.querySelector('[data-attendance-calendar]');
if(root){
    const form=root.querySelector('form'),message=root.querySelector('[data-calendar-message]'),save=root.querySelector('[data-calendar-save]');
    let version=0,loading=0,dirty=false,busy=false,lastScope,versions={},campusCount=0;
    let descriptions={};
    const combos=new Map();
    form.querySelectorAll('select').forEach(select=>combos.set(select,setupStaffLocationSelect(form,select,{labelId:select.id+'Label',optionsId:select.id+'Options',locationLabel:select.parentElement.querySelector('label').textContent})));
    const holidayInput=form.elements.holiday_date,label=holidayInput.parentElement.querySelector('label');
    const holidayPicker=enhanceSubjectTemporal(holidayInput);label.htmlFor=holidayPicker.input.id;
    root.addEventListener('attendance-settings-tab-change',()=>holidayPicker.close());
    const holidayDates=()=>[...new Set(form.elements.holidays.value.split(/\r?\n/).map(value=>value.trim()).filter(Boolean))];
    const sync=()=>{
        combos.forEach((combo,select)=>{[...select.options].forEach(option=>option.dataset.en=option.textContent);combo.sync();});
        root.querySelectorAll('.premium-form-field').forEach(field=>{
            const input=field.querySelector('select,input:not([type="hidden"])');field.classList.toggle('has-value',input?.tagName==='SELECT'?Boolean(input.selectedOptions[0]?.textContent):Boolean(input?.value));
        });
    };
    const renderHolidays=()=>{
        const list=root.querySelector('[data-calendar-holiday-list]');list.replaceChildren();
        holidayDates().filter(date=>/^\d{4}-\d{2}-\d{2}$/.test(date)).sort().forEach(date=>{
            const row=document.createElement('div');row.className='att-holiday-row';
            const label=document.createElement('span');label.className='badge bg-blue-lt';label.textContent=date;
            const input=document.createElement('input');input.type='text';input.className='form-control';input.maxLength=200;input.value=descriptions[date]||'';
            input.dataset.holidayDescription=date;input.placeholder='Holiday description';input.setAttribute('aria-label','Holiday description for '+date);
            const button=document.createElement('button');button.type='button';button.className='btn btn-sm btn-outline-danger';button.dataset.removeHoliday=date;
            button.textContent='×';button.setAttribute('aria-label','Remove holiday '+date);row.append(label,input,button);list.append(row);
        });
        if(!list.children.length){const hint=document.createElement('small');hint.className='text-secondary';hint.textContent='No holidays added yet.';list.append(hint);}
    };
    root.querySelector('[data-calendar-add-holiday]').addEventListener('click',()=>{
        if(!holidayPicker.valid()||!holidayInput.value){holidayPicker.input.focus();holidayPicker.input.reportValidity();return;}
        if(!form.elements.holiday_description.value.trim()){form.elements.holiday_description.focus();message.textContent='Enter a holiday description before adding the date.';return;}
        descriptions[holidayInput.value]=form.elements.holiday_description.value.trim();
        form.elements.holidays.value=[...new Set([...holidayDates(),holidayInput.value])].sort().join('\n');
        dirty=true;renderHolidays();holidayPicker.setValue('');form.elements.holiday_description.value='';sync();
    });
    root.querySelector('[data-calendar-holiday-list]').addEventListener('click',event=>{
        const button=event.target.closest('[data-remove-holiday]');if(!button)return;
        delete descriptions[button.dataset.removeHoliday];form.elements.holidays.value=holidayDates().filter(date=>date!==button.dataset.removeHoliday).join('\n');dirty=true;renderHolidays();
    });
    root.querySelector('[data-calendar-holiday-list]').addEventListener('input',event=>{
        if(event.target.dataset.holidayDescription){descriptions[event.target.dataset.holidayDescription]=event.target.value;dirty=true;}
    });
    const api=async(path,body)=>{
        const response=await fetch(root.dataset.base+path,{method:body?'POST':'GET',credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,...(body?{'Content-Type':'application/json'}:{})},body:body?JSON.stringify(body):undefined});
        const data=await response.json().catch(()=>({}));if(!response.ok)throw new Error(Object.values(data.errors||{}).flat().join('\n')||data.message||'Calendar unavailable.');return data;
    };
    async function load(){
        const current=++loading;save.disabled=true;
        const scope={academic_year_id:form.elements.academic_year_id.value,campus_id:form.elements.campus_id.value};
        if(!scope.academic_year_id||!scope.campus_id)return;
        try{
            const data=await api('/calendar?'+new URLSearchParams(scope));if(current!==loading)return;
            version=data.version;versions=data.versions;campusCount=data.campus_count;
            descriptions={...(data.holiday_descriptions||{})};
            form.querySelectorAll('[name="weekdays[]"]').forEach(input=>input.checked=data.weekdays.includes(Number(input.value)));
            form.elements.holidays.value=data.holidays.join('\n');lastScope=scope;dirty=false;save.disabled=false;
            message.textContent=data.mixed?'Campus calendars differ. The first campus is shown as a starting point; saving will apply your choices to all selected campuses.':`Calendar loaded for ${campusCount} campus(es).`;
            renderHolidays();sync();
        }catch(error){message.textContent=error.message;}
    }
    form.addEventListener('input',event=>{if(!event.target.matches('select,.location-combobox-search')&&!event.target.closest('.date-picker'))dirty=true;if(event.target===form.elements.holidays)renderHolidays();sync();});
    form.addEventListener('change',event=>{
        if(!event.target.matches('select')){if(event.target.matches('[name="weekdays[]"]'))dirty=true;sync();return;}
        if(busy||dirty&&!confirm('Discard unsaved calendar changes?')){Object.entries(lastScope||{}).forEach(([key,value])=>form.elements[key].value=value);sync();return;}
        load();
    });
    form.addEventListener('submit',async event=>{
        event.preventDefault();if(busy)return;busy=true;save.disabled=true;
        try{
            if(form.elements.campus_id.value==='all'){
                const confirmation=await Swal.fire({icon:'question',title:'Apply to All Campuses?',text:`Apply these school weekdays and ${holidayDates().length} holiday date(s) to all ${campusCount} campuses you manage for this academic year?`,showCancelButton:true,confirmButtonText:'Apply to All'});
                if(!confirmation.isConfirmed)return;
            }
            const result=await api('/calendar',{academic_year_id:form.elements.academic_year_id.value,campus_id:form.elements.campus_id.value,version,versions,
                weekdays:[...form.querySelectorAll('[name="weekdays[]"]:checked')].map(input=>Number(input.value)),
                holidays:form.elements.holidays.value.split(/\r?\n/).map(value=>value.trim()).filter(Boolean),holiday_descriptions:descriptions});
            dirty=false;await load();message.textContent=`Calendar saved for ${result.campus_count} campus(es).`;
        }catch(error){await Swal.fire({icon:'error',title:'Attendance Calendar',text:error.message});}finally{busy=false;save.disabled=false;}
    });
    api('/meta').then(async meta=>{
        form.elements.academic_year_id.replaceChildren(...meta.years.map(row=>new Option(row.academic_year,row.id)));
        form.elements.campus_id.replaceChildren(new Option('All Campuses','all'),...meta.campuses.filter(row=>meta.manage_campuses.includes(row.id)).map(row=>new Option(row.campus_name_en,row.id)));sync();await load();
    }).catch(error=>message.textContent=error.message);
    window.addEventListener('beforeunload',event=>{if(dirty){event.preventDefault();event.returnValue='';}});
}
