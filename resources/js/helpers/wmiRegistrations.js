import {showConfirm,showError,showSuccess} from './sweet-alert2.js';
import {setupWmiYearPicker} from './wmiYearPicker.js';

export function setupWmiRegistrations() {
    const root=document.querySelector('[data-wmi-registrations]');if(!root)return;
    const element=document.getElementById('wmiRegistrationModal'),form=element.querySelector('form'),modal=bootstrap.Modal.getOrCreateInstance(element);
    const error=form.querySelector('[data-registration-error]'),save=form.querySelector('[data-registration-save]');
    const token=document.querySelector('meta[name="csrf-token"]')?.content;
    const fields=['wmi_year_id','wmi_exam_center_id','student_source','enrollment_id','wmi_school_id','student_name','student_name_kh','date_of_birth','gender','current_grade','grade_contest','campus_study','phone_number','telegram_number','email_address','remark'];
    const pickers=new Map();
    document.querySelectorAll('.wmi-registration-modal [data-registration-picker]').forEach(select=>pickers.set(select,setupWmiYearPicker(select,select.dataset.registrationPicker)));
    const sync=()=>{
        pickers.forEach(picker=>picker.sync());
        document.querySelectorAll('.wmi-registration-modal .wmi-modal-field').forEach(field=>{field.classList.toggle('wmi-has-value',Boolean(field.querySelector('input,select,textarea')?.value));});
    };
    const phone=form.elements.phone_number,telegram=form.elements.telegram_number,samePhone=document.getElementById('wmiRegTelegramSame');
    const syncTelegram=()=>{if(samePhone.checked)telegram.value=phone.value;telegram.readOnly=samePhone.checked;sync();};
    samePhone.addEventListener('change',syncTelegram);
    phone.addEventListener('input',syncTelegram);phone.addEventListener('change',syncTelegram);
    const syncSchoolType=()=>{document.getElementById('wmiRegSchoolType').value=form.elements.wmi_school_id.selectedOptions[0]?.dataset.schoolType||'';sync();};
    form.elements.wmi_school_id.addEventListener('change',syncSchoolType);
    document.querySelectorAll('.wmi-registration-modal').forEach(modal=>{modal.addEventListener('input',sync);modal.addEventListener('change',sync);modal.addEventListener('shown.bs.modal',sync);});
    const request=async(url,method,payload)=>{
        const multipart=payload instanceof FormData;
        const response=await fetch(url,{method,headers:{Accept:'application/json','X-CSRF-TOKEN':token,...(multipart?{}:{'Content-Type':'application/json'})},body:multipart?payload:JSON.stringify(payload)});
        const data=await response.json().catch(()=>({message:'Request failed. Refresh the page and try again.'}));
        if(!response.ok)throw Error(Object.values(data.errors||{}).flat().join('\n')||data.message);return data;
    };
    const filterYear=document.getElementById('wmiRegistrationYearFilter');
    const syncFilterLabels=()=>root.querySelectorAll('.wmi-filter-field').forEach(field=>{
        field.classList.toggle('wmi-has-value',Boolean(field.querySelector('input,select')?.value?.trim()));
    });
    const filters=root.querySelector('[data-registration-filters]');
    filters.addEventListener('input',syncFilterLabels);filters.addEventListener('change',syncFilterLabels);syncFilterLabels();
    root.querySelectorAll('[data-registration-filter]').forEach(select=>{
        setupWmiYearPicker(select,select.dataset.registrationFilter);
        select.addEventListener('change',()=>select.form.requestSubmit());
    });
    root.querySelector('[data-registration-date]').addEventListener('change',event=>event.target.form.requestSubmit());
    const filterCenters=(select,year)=>{for(const option of select.options)option.disabled=!!option.value&&!!year&&option.dataset.year!==String(year);if(select.selectedOptions[0]?.disabled)select.value='';};
    const filterPanel=root.querySelector('[data-wmi-filter-panel]'),filterToggle=filterPanel.querySelector('[data-wmi-filter-toggle]');
    const media=window.matchMedia('(min-width: 768px)');let filtersExpanded=media.matches;
    const applyFilterState=()=>{
        filterPanel.classList.toggle('is-expanded',filtersExpanded);
        filterPanel.querySelector('#wmiDataFilters').hidden=!filtersExpanded;
        filterToggle.setAttribute('aria-expanded',String(filtersExpanded));
        filterToggle.setAttribute('aria-label',filtersExpanded?'Collapse filters':'Expand filters');
        filterToggle.querySelector('i').className=`ti ti-chevron-${filtersExpanded?'up':'down'}`;
    };
    filterToggle.addEventListener('click',()=>{filtersExpanded=!filtersExpanded;applyFilterState();});
    media.addEventListener('change',()=>{filtersExpanded=media.matches;applyFilterState();});applyFilterState();
    let filterTimer;
    root.querySelector('[data-registration-search]').addEventListener('input',()=>{clearTimeout(filterTimer);filterTimer=setTimeout(()=>filterYear.form.requestSubmit(),650);});
    let editing=null,busy=false,searchController=null,searchTimer=null,searchVersion=0;
    const results=form.querySelector('[data-enrollment-results]'),selected=form.querySelector('[data-enrollment-selected]');
    const studentSearch=document.getElementById('wmiRegStudentSearch'),academicYear=document.getElementById('wmiRegAcademicYear');
    const lookupCampus=document.getElementById('wmiRegLookupCampus'),lookupGrade=document.getElementById('wmiRegLookupGrade');
    const resetSearch=()=>{clearTimeout(searchTimer);searchController?.abort();searchVersion++;results.replaceChildren();studentSearch.value='';};
    const source=()=>form.elements.student_source.value;
    const sourceState=()=>{
        const western=source()==='western';
        form.querySelector('[data-western-fields]').hidden=!western;
        form.querySelectorAll('[data-other-fields]').forEach(field=>{field.hidden=western;});
        form.querySelectorAll('[data-western-only]').forEach(field=>{field.hidden=!western;});
        form.querySelectorAll('[data-snapshot]').forEach(input=>{input.readOnly=western||input.name==='campus_study';});
        form.elements.gender.disabled=western;form.elements.wmi_school_id.disabled=western;form.elements.wmi_school_id.required=!western;
        form.elements.student_name.required=!western;form.elements.current_grade.required=!western;form.elements.gender.required=!western;
        if(!western)form.elements.campus_study.value='Other';syncSchoolType();syncTelegram();sync();
    };
    const yearChanged=()=>{filterCenters(form.elements.wmi_exam_center_id,form.elements.wmi_year_id.value);sync();};
    form.elements.wmi_year_id.addEventListener('change',yearChanged);
    const registrar=document.getElementById('wmiRegRegistrar');
    const snapshot=(data)=>{
        ['enrollment_id','student_name','student_name_kh','date_of_birth','gender','current_grade','campus_study','phone_number','email_address'].forEach(key=>{form.elements[key].value=data[key]??'';});
        selected.textContent=`Selected: ${data.student_code||''} · ${data.student_name} · ${data.academic_year||''} · ${data.campus_study}`;results.replaceChildren();syncTelegram();sync();
    };
    const search=async()=>{
        const q=studentSearch.value.trim();searchController?.abort();const version=++searchVersion;
        if(!academicYear.value){results.textContent='Select an academic year to find a Western student.';return;}
        searchController=new AbortController();results.textContent='Searching enrollments…';
        try{
            const url=new URL(root.dataset.searchUrl,window.location.origin);url.searchParams.set('q',q);url.searchParams.set('academic_year_id',academicYear.value);
            if(lookupCampus.value)url.searchParams.set('campus_id',lookupCampus.value);
            if(lookupGrade.value)url.searchParams.set('grade_id',lookupGrade.value);
            const response=await fetch(url,{headers:{Accept:'application/json'},signal:searchController.signal});const data=await response.json();
            if(version!==searchVersion)return;if(!response.ok)throw Error(data.message||'Could not search enrollments.');
            results.replaceChildren();if(!data.data.length)results.textContent='No matching enrollments found.';
            data.data.forEach(record=>{const button=document.createElement('button');button.type='button';button.className='wmi-enrollment-option';button.textContent=`${record.student_code} · ${record.student_name} · ${record.current_grade} · ${record.campus_study}`;button.addEventListener('click',()=>snapshot(record));results.append(button);});
        }catch(problem){if(problem.name!=='AbortError'&&version===searchVersion)results.textContent=problem.message;}
    };
    studentSearch.addEventListener('input',()=>{clearTimeout(searchTimer);searchTimer=setTimeout(search,300);});
    studentSearch.addEventListener('focus',search);
    [lookupCampus,lookupGrade].forEach(select=>select.addEventListener('change',()=>{resetSearch();form.elements.enrollment_id.value='';selected.textContent='';search();}));
    academicYear.addEventListener('change',()=>{resetSearch();form.elements.enrollment_id.value='';selected.textContent='';search();});
    form.querySelectorAll('[name="student_source"]').forEach(radio=>radio.addEventListener('change',()=>{
        resetSearch();selected.textContent='';samePhone.checked=false;['enrollment_id','student_name','student_name_kh','date_of_birth','gender','current_grade','campus_study','phone_number','telegram_number','email_address','wmi_school_id'].forEach(key=>{form.elements[key].value='';});sourceState();
    }));
    const open=(record=null)=>{
        if(busy)return;form.reset();resetSearch();editing=record?.id||null;selected.textContent='';error.hidden=true;
        fields.forEach(key=>{if(key!=='student_source')form.elements[key].value=record?.[key]??'';});
        samePhone.checked=Boolean(phone.value&&phone.value===telegram.value);
        form.querySelector(`[name="student_source"][value="${record?.student_source||'western'}"]`).checked=true;
        if(!record)form.elements.wmi_year_id.value=filterYear.value;
        form.elements.wmi_year_id.disabled=!!record;
        registrar.value=record?(record.registration_channel==='online'?'online':String(record.registrar_campus_id)):'online';
        if(record?.student_source==='western')selected.textContent=`Selected: ${record.student_code||''} · ${record.student_name}`;
        yearChanged();sourceState();sync();element.querySelector('.modal-title').textContent=editing?'Edit WMI Registration':'Register WMI Student';save.querySelector('span').textContent=editing?'Update Registration':'Register Candidate';modal.show();
    };
    root.querySelector('[data-wmi-registration-new]').addEventListener('click',()=>open());
    root.querySelectorAll('[data-wmi-registration-edit]').forEach(button=>button.addEventListener('click',()=>open(JSON.parse(button.dataset.registration))));
    root.querySelectorAll('[data-wmi-registration-delete]').forEach(button=>button.addEventListener('click',async()=>{
        if(busy||button.disabled)return;
        const answer=await showConfirm('Delete registration?',`Delete the WMI registration for ${button.dataset.name}? The registration, committee ID, and seating assignment will be removed. Student and enrollment records will be preserved.`,'Delete Registration');
        if(!answer.isConfirmed||busy)return;
        busy=true;button.disabled=true;
        try {const result=await request(`${root.dataset.saveUrl}/${button.dataset.id}`,'DELETE');await showSuccess(result.message);window.location.reload();}
        catch(problem){await showError(problem.message);button.disabled=false;}
        finally {busy=false;}
    }));
    element.addEventListener('hide.bs.modal',event=>{if(busy)event.preventDefault();else resetSearch();});
    form.addEventListener('submit',async event=>{
        event.preventDefault();if(busy||!form.reportValidity())return;
        if(source()==='western'&&!form.elements.enrollment_id.value){error.textContent='Select a student from Student Enrollments.';error.hidden=false;return;}
        const payload=Object.fromEntries(fields.map(key=>[key,form.elements[key].value||null]));
        payload.registration_channel=registrar.value==='online'?'online':'campus';payload.registrar_campus_id=registrar.value==='online'?null:registrar.value;
        busy=true;save.disabled=true;error.hidden=true;
        try{const result=await request(root.dataset.saveUrl+(editing?`/${editing}`:''),editing?'PUT':'POST',payload);busy=false;modal.hide();await showSuccess(result.message);window.location.reload();}
        catch(problem){error.textContent=problem.message;error.hidden=false;error.scrollIntoView({block:'nearest'});}finally{busy=false;save.disabled=false;}
    });
    sync();
}
