import Swal from 'sweetalert2';
import { renderPagination } from './helpers/pagination.js';
import { setupWmiYearPicker } from './helpers/wmiYearPicker.js';
import { setupBackToTop } from './helpers/backToTop.js';
import { createStudentPhotoEditor } from './helpers/studentPhotoEditor.js';
import { initPremiumDatePicker } from './premiumDatePicker.js';
import { dateKhmer } from './helpers/staffProfileFields.js';
import intlTelInput from 'intl-tel-input';
import 'intl-tel-input/styles';
import { formatGepCambodiaPhone, gepPhoneValue } from './helpers/gepEnrollmentPhone.js';
import '../css/pages/gep-enrollment.css';

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('gep-enrollments'); if (!root) return;
    const get = id => document.getElementById(id), options = JSON.parse(root.dataset.options), permissions = JSON.parse(root.dataset.permissions), base = root.dataset.url;
    const form = get('gep-enrollment-form'), modal = bootstrap.Modal.getOrCreateInstance(get('gep-enrollment-modal'));
    const historyModal = bootstrap.Modal.getOrCreateInstance(get('gep-history-modal'));
    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const date = value => { if (!value) return '—'; const [y,m,d] = value.slice(0,10).split('-'); return `${d}-${['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][Number(m)-1]}-${y}`; };
    const fields = ['academic_year_id','term_assignment_id','campus_id','level_id','class_type_id','class_id','group_id','enrollment_status'];
    const pickers = new Map(); let rows = [], editing = null, source = 'existing', page = 1, timer, searchTimer, loading, searching;
    const sync = element => {
        if (!element) return;
        const wrapper=element.closest('.gep-field');
        wrapper?.classList.toggle('has-value', Boolean(element.value || element.dataset.importedName) || element.hasAttribute('data-filter') || wrapper.hasAttribute('data-gep-always-float'));
        pickers.get(element)?.sync();
        if(!element.value && element.dataset.importedName){
            const label=get(`${element.id}Value`);
            if(label){label.textContent=element.dataset.importedName;label.title='Imported name: no unique match in the location/settings list. Select a listed name to replace it.';}
        }else if(element.tagName==='SELECT')get(`${element.id}Value`)?.removeAttribute('title');
    };
    const setOptions = (select, items, label, value = '') => {
        select.innerHTML = `<option value="">${select.hasAttribute('data-filter') ? esc(`All ${label}`) : ''}</option>` + items.map(item => `<option value="${esc(item.value)}"${item.flag?` data-flag="${esc(item.flag)}"`:''}${item.primary?` data-primary-label="${esc(item.primary)}" data-secondary-label="${esc(item.secondary)}"`:''}>${esc(item.label)}</option>`).join('');
        select.value = String(value ?? ''); sync(select);
    };
    const assignments = (year, forForm) => options.assignments.filter(a => (!year || String(a.academic_year_id) === String(year)) && (!forForm || (a.lifecycle_status !== 'finished' && a.academic_year?.status && a.term?.status) || a.id === editing?.term_assignment_id));
    const courseOptions = {
        level_id: ['levels','level_name'], class_type_id: ['classTypes','type_name'], class_id: ['classes','class_name'], group_id: ['groups','session_name'],
    };
    const termOptions = list => list.map(a => ({value:a.id,label:`${a.academic_year.academic_year} · ${a.term.term_name} (${a.lifecycle_status})`}));
    const statusOptions = list => list.map(s => ({value:s,label:s[0].toUpperCase()+s.slice(1)}));
    const fill = (prefix, forForm = false) => {
        setOptions(get(`${prefix}academic_year_id`), options.years.filter(y => !forForm || y.status || y.id === editing?.term_assignment?.academic_year_id).map(y => ({value:y.id,label:y.academic_year})), 'Academic Years');
        setOptions(get(`${prefix}term_assignment_id`),termOptions(assignments('',forForm)),'Terms');
        const campuses = forForm ? (editing ? options.editCampuses : options.createCampuses) : options.campuses;
        setOptions(get(`${prefix}campus_id`),campuses.map(c => ({value:c.id,label:c.campus_name_en})),'Campuses');
        for (const [field,[list,label]] of Object.entries(courseOptions)) setOptions(get(`${prefix}${field}`), options[list].filter(v => !forForm || v.status || v.id === editing?.[field]).map(v => ({value:v.id,label:field==='group_id'?`${v.session_name} · ${v.group_code}`:v[label]})), field==='group_id'?'Groups':field==='class_type_id'?'Class Types':field==='level_id'?'Levels':'Classes');
        setOptions(get(`${prefix}enrollment_status`),statusOptions(forForm?['active','pending']:['active','pending','completed','withdrawn','graduated']),'Statuses');
    };
    fill('gep-filter-'); fill('gep-form-',true);
    setOptions(get('gep-lookup-academic_year_id'),options.lookupYears.map(y=>({value:y.id,label:y.academic_year})),'Academic Years');
    setOptions(get('gep-lookup-campus_id'),options.campuses.map(c=>({value:c.id,label:c.campus_name_en})),'Campuses');
    const fillLookupClasses=()=>{
        const year=get('gep-lookup-academic_year_id').value,campus=get('gep-lookup-campus_id').value;
        const matches=options.lookupClasses.filter(c=>(!year||String(c.academic_year_id)===year)&&(!campus||String(c.campus_id)===campus));
        const choices=new Map(matches.map(c=>{
            const raw=String(c.grade_short_name||c.grade).trim();
            const kindergarten=raw.match(/^(?:kindergarten|k)[\s-]*([1-3])/i);
            const regular=raw.match(/^(?:grade\s*)?(\d+)/i);
            const grade=/^(?:nursery|n)(?:\b|-|$)/i.test(raw)?'N':kindergarten?`K${kindergarten[1]}`:regular?String(Number(regular[1])):raw.replace(/[-\s]+$/,'');
            const suffix=String(c.class_name).trim().replace(/^(?:grade\s*)?(?:K[1-3]|N|\d+)[\s-]*/i,'');
            const gradeOrder=grade==='N'?0:kindergarten?Number(kindergarten[1]):regular?Number(regular[1])+3:1000+Number(c.grade_order||0);
            return [`${c.grade_id}:${c.id}`,{value:`${c.grade_id}:${c.id}`,gradeOrder,label:`${grade}${grade==='N'||kindergarten?'-':''}${suffix}`}];
        }));
        setOptions(get('gep-lookup-class_id'),[...choices.values()].sort((a,b)=>a.gradeOrder-b.gradeOrder||a.label.localeCompare(b.label,'en',{numeric:true,sensitivity:'base'})),'Grades');
    };
    fillLookupClasses();
    const cambodia=options.countries.find(c=>String(c.country_name_en).trim().toLowerCase()==='cambodia');
    root.querySelectorAll('[data-country-select]').forEach(select=>setOptions(select,options.countries.map(c=>({value:c.id,flag:c.flag_path||(c.id===cambodia?.id?'/flags/cambodia.svg':''),primary:select.dataset.countrySelect==='nationality'?(c.nationality_name_en||c.country_name_en):'',secondary:c.nationality_name_kh||'',label:select.dataset.countrySelect==='nationality'?`${c.nationality_name_kh||''} / ${c.nationality_name_en||c.country_name_en}`:`${c.country_name_en} / ${c.country_name_kh||''}`})),'Countries'));
    root.querySelectorAll('[data-occupation-select]').forEach(select=>setOptions(select,options.occupations.map(o=>({value:o.id,label:`${o.occupation_name_kh||''} / ${o.occupation_name_en}`})),'Occupations'));
    setOptions(get('gep-form-existing_family_id'),options.families.map(f=>({value:f.id,label:f.label})),'GEP Families');
    root.querySelectorAll('.gep-field select').forEach(select => { pickers.set(select,setupWmiYearPicker(select,select.getAttribute('aria-label') || select.name || 'options','ti-chevron-right')); sync(select); });
    root.querySelectorAll('.gep-field input,.gep-field textarea,.gep-field select').forEach(element => {
        ['input','change'].forEach(event => element.addEventListener(event,() => {delete element.dataset.importedName;sync(element);})); sync(element);
    });
    get('gep-student-search').closest('.gep-field').classList.add('gep-lookup-search-field');
    const request = async (path, method = 'GET', data, signal) => {
        const multipart=data instanceof FormData;
        if(multipart && method==='PUT'){data.set('_method','PUT');method='POST';}
        const headers={Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content};
        if(!multipart)headers['Content-Type']='application/json';
        const response = await fetch(base+path, {method,signal,headers,body:data?(multipart?data:JSON.stringify(data)):undefined});
        const result = await response.json().catch(() => ({}));
        if (!response.ok) throw Object.assign(new Error(response.status===401?'Your session has expired. Keep this form open and sign in again in another tab.':result.message||'Unable to complete the request.'), {errors:result.errors});
        return result;
    };
    const showError = (id,error) => { const element=get(id); element.textContent=error ? Object.values(error.errors??{}).flat().join(' ') || error.message : ''; element.classList.toggle('d-none',!error); };
    const toast = title => Swal.fire({toast:true,position:'top-end',icon:'success',title,showConfirmButton:false,timer:3000,timerProgressBar:true});
    const photoEditor=createStudentPhotoEditor({bootstrap,csrf:document.querySelector('meta[name="csrf-token"]').content,
        elements:{parent:get('gep-enrollment-modal'),input:get('gep-student-photo'),dropzone:get('gep-photo-dropzone'),preview:get('gep-photo-preview'),previewContainer:get('gep-photo-preview-container'),editButton:get('gep-photo-edit')},
        showError:(title,message)=>Swal.fire({icon:'error',title,text:message}),showSuccess:(title)=>toast(title),saveMessage:'Save the GEP enrollment to apply this photo.'});
    const profileFields=[...form.querySelectorAll('[data-profile-field]')];
    const createPhoneControl=(visibleId,hiddenId)=>{
        const visible=get(visibleId),hidden=get(hiddenId);
        const intl=intlTelInput(visible,{initialCountry:'kh',countryOrder:['kh'],numberDisplayFormat:'NATIONAL',separateDialCode:true,customPlaceholder:()=> 'Enter phone number',dropdownParent:get('gep-enrollment-modal'),loadUtils:()=>import('intl-tel-input/utils')});
        visible.placeholder='Enter phone number';
        const syncPhone=()=>{hidden.value=visible.value.trim()?(intl.getNumber()||gepPhoneValue(visible.value,intl.getSelectedCountry()?.dialCode||'855')):'';sync(hidden);};
        const setValue=value=>{
            const saved=String(value||'').trim();
            intl.setNumber('');intl.setSelectedCountry('kh');
            if(saved)intl.setNumber(saved.startsWith('+')?saved:gepPhoneValue(saved));
            if(intl.getSelectedCountry()?.iso2==='kh')visible.value=formatGepCambodiaPhone(value);
            hidden.value=value||'';sync(hidden);
        };
        visible.addEventListener('input',()=>{if(intl.getSelectedCountry()?.iso2==='kh')visible.value=formatGepCambodiaPhone(visible.value);syncPhone();});
        visible.addEventListener('countrychange',syncPhone);
        return {sync:syncPhone,setValue,setDisabled:disabled=>intl.setDisabled(disabled)};
    };
    const homePhoneControl=createPhoneControl('gep-home-phone-display','gep-form-home_phone');
    const parentPhoneControls=Object.fromEntries(['mother','father','guardian'].map(parent=>[parent,createPhoneControl(`gep-${parent}-phone-display`,`gep-form-${parent}-phone`)]));
    let profileVersion=0,profileRequest;
    const enableProfile=enabled=>{profileFields.forEach(field=>{field.disabled=!enabled;field.required=enabled&&field.hasAttribute('data-profile-required');sync(field);});homePhoneControl.setDisabled(!enabled);get('gep-birth-date-display').disabled=!enabled;get('gep-birth-date-display').closest('.date-picker').querySelector('[data-premium-date-toggle]').disabled=!enabled;};
    const dobKhmer=()=>{get('gep-dob-kh').value=dateKhmer(get('gep-form-date_of_birth').value);sync(get('gep-dob-kh'));sync(get('gep-form-date_of_birth'));};
    const birthDatePicker=initPremiumDatePicker(get('gep-birth-date-display').closest('[data-premium-date-picker]'));
    const enrolledDatePicker=initPremiumDatePicker(get('gep-enrolled-date-display').closest('[data-premium-date-picker]'));
    get('gep-birth-date-display').addEventListener('input',dobKhmer);
    const resetProfile=()=>{
        birthDatePicker.close();
        delete get('gep-student-search').dataset.loadingStudent;
        profileVersion++;profileRequest?.abort();profileFields.forEach(field=>{field.value='';delete field.dataset.importedName;sync(field);});
        get('gep-form-student_identifier').readOnly=false;
        for(const prefix of ['birth','address'])for(const type of ['province','district','commune','village'])setOptions(get(`gep-form-${prefix}_${type}_id`),[],type);
        get('gep-student-number').value='';sync(get('gep-student-number'));birthDatePicker.setValue('');dobKhmer();homePhoneControl.setValue('');
        Object.values(parentPhoneControls).forEach(control=>control.setValue(''));
    };
    const fillParents=parents=>{
        for(const parent of ['mother','father','guardian'])for(const field of ['name_en','name_kh','occupation_id','nationality_country_id','phone','workplace']){
            if(field==='phone'){parentPhoneControls[parent].setValue(parents?.[parent]?.phone||'');continue;}
            const control=form.elements.namedItem(`${parent}_${field}`),raw=parents?.[parent]?.[field==='occupation_id'?'occupation':field==='nationality_country_id'?'nationality':'']||'';
            control.dataset.importedName=raw;
            control.value=parents?.[parent]?.[field]||(field==='nationality_country_id'&&!raw?cambodia?.id||'':'');sync(control);
        }
    };
    const fillProfile=async(profile={},identifier='',number='')=>{
        const version=++profileVersion;profileRequest?.abort();profileRequest=new AbortController();
        const importedName=field=>{const base=field.name==='nationality_country_id'?'nationality':field.name.replace(/_id$/,'');return [profile.transfer_names?.[`${base}_en`],profile.transfer_names?.[`${base}_kh`]].filter(Boolean).join(' / ');};
        for(const field of profileFields){field.dataset.importedName=importedName(field);if(field.dataset.locationPrefix && field.dataset.locationType!=='country')continue;field.value=profile[field.name]??'';sync(field);}
        homePhoneControl.setValue(profile.home_phone||'');
        if(!get('gep-form-nationality_country_id').value&&!get('gep-form-nationality_country_id').dataset.importedName&&cambodia){get('gep-form-nationality_country_id').value=cambodia.id;sync(get('gep-form-nationality_country_id'));}
        get('gep-form-student_identifier').value=identifier||'';get('gep-student-number').value=number||'';sync(get('gep-form-student_identifier'));sync(get('gep-student-number'));birthDatePicker.setValue(get('gep-form-date_of_birth').value);dobKhmer();
        get('gep-form-student_identifier').readOnly=Boolean(identifier);
        for(const prefix of ['birth','address']){
            for(const [type,parent] of [['province','country'],['district','province'],['commune','district'],['village','commune']]){
                const parentId=profile[`${prefix}_${parent}_id`],control=get(`gep-form-${prefix}_${type}_id`);
                const result=parentId?await request(`/locations?${new URLSearchParams({type,parent_id:parentId})}`,'GET',undefined,profileRequest.signal):{data:[]};
                if(version!==profileVersion)return;
                setOptions(control,result.data.map(v=>({value:v.id,label:`${v.name_kh||''} / ${v.name_en}`})),type,profile[`${prefix}_${type}_id`]||'');
            }
        }
    };
    get('gep-form-date_of_birth').addEventListener('change',dobKhmer);
    for(const prefix of ['birth','address']){
        const types=['country','province','district','commune','village'];let locationVersion=0;
        types.slice(0,-1).forEach((type,index)=>get(`gep-form-${prefix}_${type}_id`).addEventListener('change',async()=>{
            const version=++locationVersion,profileAtRequest=profileVersion;const parentId=get(`gep-form-${prefix}_${type}_id`).value;
            types.slice(index+1).forEach(child=>{const control=get(`gep-form-${prefix}_${child}_id`);delete control.dataset.importedName;setOptions(control,[],child);});
            if(!parentId)return;
            try{const result=await request(`/locations?${new URLSearchParams({type:types[index+1],parent_id:parentId})}`);if(version!==locationVersion||profileAtRequest!==profileVersion)return;setOptions(get(`gep-form-${prefix}_${types[index+1]}_id`),result.data.map(v=>({value:v.id,label:`${v.name_kh||''} / ${v.name_en}`})),types[index+1]);}catch(error){showError('gep-form-error',error);}
        }));
    }
    get('gep-form-existing_family_id').addEventListener('change',async()=>{
        const id=get('gep-form-existing_family_id').value;if(!id)return;
        try{const result=await request(`/profile?gep_student_id=${encodeURIComponent(id)}`);if(id!==get('gep-form-existing_family_id').value)return;get('gep-form-family_number').value=result.profile?.family_number||'';sync(get('gep-form-family_number'));fillParents(result.parent_information);}catch(error){showError('gep-form-error',error);}
    });
    const load = async (target = 1) => {
        loading?.abort(); loading=new AbortController();
        const filters=Object.fromEntries(fields.map(field => [field,get(`gep-filter-${field}`).value]));
        try {
            const result=await request(`/data?${new URLSearchParams({...filters,search:get('gep-enrollment-search').value.trim(),page:target,perPage:get('gep-enrollment-per-page').value})}`,'GET',undefined,loading.signal);
            rows=result.records.data; page=result.records.current_page; showError('gep-list-error',null);
            for (const [key,value] of Object.entries(result.summary)) get(`gep-total-${key}`).textContent=Number(value).toLocaleString();
            get('gep-enrollment-rows').innerHTML=rows.length ? rows.map((row,index) => {
                const student=row.gep_student.profile||{}, status=row.enrollment_status, style=({active:'green',pending:'orange',completed:'azure',withdrawn:'red',graduated:'purple'})[status];
                return `<tr><td><span class="badge bg-primary-lt px-3 py-2 text-nowrap">${result.records.from+index}</span></td>
                    <td>${row.gep_student.photo_url?`<img class="gep-list-photo" src="${esc(row.gep_student.photo_url)}" alt="Student photo" loading="lazy">`:''}<div class="text-primary fw-semibold">${esc(row.gep_student.student_identifier||row.gep_student.gep_code)}</div><div class="gep-student-name">${esc(student.full_name_en)}</div>${student.full_name_kh?`<div class="school-profile-khmer">${esc(student.full_name_kh)}</div>`:''}<div class="small text-secondary">DOB: ${date(student.date_of_birth)} · ${esc(student.gender||'—')}</div></td>
                    <td><div><i class="ti ti-phone me-1"></i>${esc(student.home_phone||'—')}</div><div class="small text-secondary text-break"><i class="ti ti-mail me-1"></i>${esc(student.email||'—')}</div>${row.guardian_name?`<div class="small mt-1">Guardian: ${esc(row.guardian_name)}<br>${esc(row.guardian_phone||'')}</div>`:''}</td>
                    <td><strong>${esc(row.term_assignment.academic_year.academic_year)}</strong><div>${esc(row.term_assignment.term.term_name)}</div></td>
                    <td><strong>${esc(row.campus.campus_name_en)}</strong><div>${esc(row.level.level_name)} · ${esc(row.school_class.class_name)}</div><div class="small text-secondary">${esc(row.class_type.type_name)} · ${esc(row.group.session_name)} (${esc(row.group.group_code)})</div></td>
                    <td><span class="badge bg-primary-lt me-1">${row.student_type==='old'?'Old':'New'}</span><span class="badge bg-${style}-lt">${esc(status[0].toUpperCase()+status.slice(1))}</span><div class="small text-secondary mt-1">${date(row.enrolled_on)}</div></td>
                    <td><div class="btn-list justify-content-end flex-nowrap">${row.permissions.history?`<button class="btn btn-sm btn-outline-secondary" data-action="history" data-id="${row.id}" title="Activity history" aria-label="Activity history"><i class="ti ti-history"></i></button>`:''}${row.permissions.edit?`<button class="btn btn-sm btn-outline-primary" data-action="edit" data-id="${row.id}" title="Edit enrollment" aria-label="Edit enrollment"><i class="ti ti-edit"></i></button>`:''}${row.permissions.delete?`<button class="btn btn-sm btn-outline-danger" data-action="delete" data-id="${row.id}" title="Delete enrollment" aria-label="Delete enrollment"><i class="ti ti-trash"></i></button>`:''}</div></td></tr>`;
            }).join('') : '<tr><td colspan="7" class="text-center text-secondary py-5">No GEP enrollments match these filters.</td></tr>';
            renderPagination(result.records,'gep-enrollment-pagination','gep-enrollment-per-page',load);
        } catch(error) { if(error.name!=='AbortError')showError('gep-list-error',error); }
    };
    fields.forEach(field => get(`gep-filter-${field}`).addEventListener('change',() => {
        if(field==='academic_year_id')setOptions(get('gep-filter-term_assignment_id'),termOptions(assignments(get('gep-filter-academic_year_id').value,false)),'Terms');
        load();
    }));
    get('gep-enrollment-search').addEventListener('input',() => {clearTimeout(timer);timer=setTimeout(() => load(),300);});
    get('gep-filter-reset').addEventListener('click',() => {fill('gep-filter-');get('gep-enrollment-search').value='';sync(get('gep-enrollment-search'));load();});
    const filterBody=get('gep-filter-body'), filterToggle=get('gep-filter-toggle');
    const collapse = closed => {filterBody.hidden=closed;filterToggle.setAttribute('aria-expanded',String(!closed));filterToggle.querySelector('span').textContent=closed?'Expand':'Collapse';};
    collapse(window.matchMedia('(max-width: 767px)').matches);
    filterToggle.addEventListener('click',() => collapse(!filterBody.hidden));
    const setSource = value => {
        photoEditor.resetPreview();
        resetProfile();get('gep-selected-gep-student-id').value='';
        clearTimeout(searchTimer);
        source=value; if(!editing){get('gep-form-student_type').value='new';sync(get('gep-form-student_type'));} searching?.abort();get('gep-student-results').replaceChildren();get('gep-student-search').value='';get('gep-selected-student-id').value='';get('gep-selected-student').classList.add('d-none');
        get('gep-student-results').classList.add('d-none');get('gep-student-search').setAttribute('aria-expanded','false');
        get('gep-student-information').classList.toggle('d-none',source==='existing'&&!editing);
        get('gep-existing-panel').classList.toggle('d-none',source!=='existing'||Boolean(editing));
        enableProfile(source==='new'||Boolean(editing));
        if(!editing)fillParents({});
        if(!editing&&cambodia)root.querySelectorAll('[data-country-select="nationality"]').forEach(select=>{if(!select.value){select.value=cambodia.id;sync(select);}});
        if(source==='new'&&!editing&&cambodia)for(const prefix of ['birth','address']){const country=get(`gep-form-${prefix}_country_id`);country.value=cambodia.id;sync(country);country.dispatchEvent(new Event('change',{bubbles:true}));}
        root.querySelectorAll('[data-source]').forEach(button => {const active=button.dataset.source===source;button.classList.toggle('active',active);button.setAttribute('aria-pressed',String(active));});
    };
    const formTerms = (value = '') => {
        const select=get('gep-form-term_assignment_id');setOptions(select,termOptions(assignments(get('gep-form-academic_year_id').value,true)),'Term',value);
    };
    const open = async (row = null) => {
        editing=row;form.reset();form.querySelectorAll('.is-invalid').forEach(e => e.classList.remove('is-invalid'));showError('gep-form-error',null);fill('gep-form-',true);
        get('gep-registration-layout').classList.toggle('is-editing',Boolean(row));
        get('gep-lookup-academic_year_id').value='';get('gep-lookup-campus_id').value='';fillLookupClasses();
        get('gep-source-modes').classList.toggle('d-none',Boolean(row));get('gep-edit-student').classList.toggle('d-none',!row);
        setSource('existing');
        get('gep-form-academic_year_id').disabled=Boolean(row);get('gep-form-term_assignment_id').disabled=Boolean(row);
        if(row){
            get('gep-edit-student').textContent=`${row.gep_student.gep_code} · ${row.gep_student.profile?.full_name_en||''}`;
            try{await fillProfile(row.gep_student.profile,row.gep_student.student_identifier,row.gep_student.gep_code);}catch(error){if(error.name!=='AbortError')showError('gep-form-error',error);}
            get('gep-form-academic_year_id').value=row.term_assignment.academic_year_id;formTerms(row.term_assignment_id);
            ['term_assignment_id','campus_id','level_id','class_type_id','class_id','group_id','student_type','enrollment_status','enrolled_on','notes'].forEach(field => {form.elements.namedItem(field).value=field==='enrolled_on'?row[field]?.slice(0,10):row[field]??'';});
            fillParents(row.parent_information||{guardian:{name_en:row.guardian_name,phone:row.guardian_phone}});
            if(row.gep_student.photo_url)photoEditor.showPreview(row.gep_student.photo_url);
        }else{
            const current=options.assignments.find(a=>a.lifecycle_status==='started'&&a.academic_year.status&&a.term.status);
            get('gep-form-academic_year_id').value=get('gep-filter-academic_year_id').value || current?.academic_year_id || options.years.find(y=>y.status)?.id || '';
            formTerms(current && String(current.academic_year_id)===get('gep-form-academic_year_id').value?current.id:'');
            const campuses=options.createCampuses;get('gep-form-campus_id').value=campuses.find(c=>String(c.id)===get('gep-filter-campus_id').value)?.id || (campuses.length===1?campuses[0].id:'');
            get('gep-form-enrollment_status').value='active';get('gep-form-enrolled_on').value=options.today;
        }
        enrolledDatePicker.setValue(get('gep-form-enrolled_on').value);
        form.querySelectorAll('input,textarea,select').forEach(sync);
        get('gep-enrollment-title').textContent=row?'Edit GEP Enrollment':'Register GEP Student';get('gep-enrollment-save').textContent=row?'Update':'Register Student';modal.show();
    };
    get('gep-enrollment-add')?.addEventListener('click',() => open());
    root.querySelectorAll('[data-source]').forEach(button => button.addEventListener('click',()=>setSource(button.dataset.source)));
    get('gep-form-academic_year_id').addEventListener('change',()=>formTerms());
    let studentMatches=[];
    const lookupStudents=()=>{
        clearTimeout(searchTimer);searching?.abort();get('gep-student-results').replaceChildren();
        if(source!=='existing'||editing)return;
        const q=get('gep-student-search').value.trim(), campus=get('gep-lookup-campus_id').value;
        get('gep-student-results').classList.remove('d-none');get('gep-student-search').setAttribute('aria-expanded','true');get('gep-student-results').textContent='Searching students…';
        searchTimer=setTimeout(async()=>{
            searching=new AbortController();
            const [gradeId='',classId='']=get('gep-lookup-class_id').value.split(':');
            try{const result=await request(`/students?${new URLSearchParams({q,campus_id:campus,academic_year_id:get('gep-lookup-academic_year_id').value,grade_id:gradeId,class_id:classId})}`,'GET',undefined,searching.signal);studentMatches=result.data;
                get('gep-student-results').innerHTML=studentMatches.map(s=>`<button type="button" class="gep-student-result" data-student="${s.id}"><strong>${esc(s.full_name_en)}</strong><div class="small text-secondary">${esc(s.student_id||s.student_no)} · DOB: ${date(s.date_of_birth)}</div></button>`).join('')+`<div class="small text-secondary">${result.has_more?'Showing first 30 matches. Refine your search.':studentMatches.length?'':'No matching students in permitted campuses.'}</div>`;
            }catch(error){if(error.name!=='AbortError')showError('gep-form-error',error);}
        },300);
    };
    get('gep-student-search').addEventListener('input',()=>{
        get('gep-selected-student-id').value='';get('gep-selected-gep-student-id').value='';resetProfile();enableProfile(false);get('gep-selected-student').classList.add('d-none');get('gep-student-information').classList.add('d-none');photoEditor.resetPreview();lookupStudents();
    });
    get('gep-student-search').addEventListener('focus',lookupStudents);
    const closeLookup=()=>{get('gep-student-results').classList.add('d-none');get('gep-student-search').setAttribute('aria-expanded','false');};
    document.addEventListener('pointerdown',event=>{if(!event.target.closest('.gep-student-lookup'))closeLookup();});
    get('gep-student-search').addEventListener('keydown',event=>{
        if(event.key==='Escape'){event.preventDefault();event.stopPropagation();closeLookup();}
        if(event.key==='ArrowDown'){event.preventDefault();get('gep-student-results').querySelector('button')?.focus();}
    });
    get('gep-student-results').addEventListener('keydown',event=>{
        if(event.key==='Escape'){event.preventDefault();event.stopPropagation();get('gep-student-search').focus();closeLookup();}
        if(!['ArrowDown','ArrowUp'].includes(event.key))return;
        event.preventDefault();const buttons=[...get('gep-student-results').querySelectorAll('button')],index=buttons.indexOf(document.activeElement);
        buttons[(index+(event.key==='ArrowDown'?1:-1)+buttons.length)%buttons.length]?.focus();
    });
    ['academic_year_id','campus_id','class_id'].forEach(field=>get(`gep-lookup-${field}`).addEventListener('change',()=>{
        if(field==='academic_year_id'||field==='campus_id')fillLookupClasses();
        get('gep-selected-student-id').value='';get('gep-selected-gep-student-id').value='';resetProfile();enableProfile(false);get('gep-selected-student').classList.add('d-none');get('gep-student-search').value='';get('gep-student-information').classList.add('d-none');photoEditor.resetPreview();
        lookupStudents();
    }));
    get('gep-student-results').addEventListener('click',async event=>{
        const button=event.target.closest('[data-student]');if(!button)return;const student=studentMatches.find(s=>String(s.id)===button.dataset.student);
        const selectedId=String(student.id);get('gep-student-search').dataset.loadingStudent=selectedId;
        try{
        const result=await request(`/profile?${new URLSearchParams(student.gep_student_id?{gep_student_id:student.gep_student_id}:{student_id:student.id})}`);
        if(source!=='existing'||get('gep-student-search').dataset.loadingStudent!==selectedId)return;
        await fillProfile(result.profile,result.student_identifier,result.gep_code);
        if(source!=='existing'||get('gep-student-search').dataset.loadingStudent!==selectedId)return;
        get('gep-form-student_type').value=result.student_type_default||'new';sync(get('gep-form-student_type'));
        enableProfile(true);photoEditor.resetPreview();if(result.photo_url)photoEditor.showPreview(result.photo_url);
        fillParents(result.parent_information||{});
        get('gep-student-information').classList.remove('d-none');get('gep-student-search').value=student.full_name_en;sync(get('gep-student-search'));closeLookup();
        get('gep-selected-student-id').value=student.gep_student_id?'':student.id;get('gep-selected-gep-student-id').value=student.gep_student_id||'';get('gep-selected-student').textContent=`Selected: ${student.full_name_en} · ${student.student_id||student.student_no} · DOB: ${date(student.date_of_birth)}`;get('gep-selected-student').classList.remove('d-none');get('gep-student-results').replaceChildren();
        }catch(error){if(error.name!=='AbortError')showError('gep-form-error',error);}
    });
    form.addEventListener('submit',async event=>{
        if(event.defaultPrevented)return;
        event.preventDefault();showError('gep-form-error',null);form.querySelectorAll('.is-invalid').forEach(e=>e.classList.remove('is-invalid'));
        homePhoneControl.sync();Object.values(parentPhoneControls).forEach(control=>control.sync());
        if(!editing&&source==='existing'&&!get('gep-selected-student-id').value&&!get('gep-selected-gep-student-id').value){showError('gep-form-error',new Error('Search and select an existing student first.'));get('gep-student-search').focus();return;}
        const button=get('gep-enrollment-save');button.disabled=true;
        const data=new FormData(form);data.set('term_assignment_id',get('gep-form-term_assignment_id').value);
        if(!get('gep-student-photo').files.length)data.delete('photo');
        if(!editing)data.set('source',source);
        try{const result=await request(editing?`/${editing.id}`:'',editing?'PUT':'POST',data);
            const member=result.data.gep_student;
            if(member?.profile?.family_number){const family={id:member.id,label:`${member.profile.family_number} · ${member.profile.full_name_en}`,family_number:member.profile.family_number};options.families=options.families.filter(f=>f.id!==member.id);options.families.push(family);setOptions(get('gep-form-existing_family_id'),options.families.map(f=>({value:f.id,label:f.label})),'GEP Families');}
            modal.hide();toast(result.message);await load(page);}
        catch(error){showError('gep-form-error',error);Object.keys(error.errors??{}).forEach(name=>form.elements.namedItem(name)?.classList.add('is-invalid'));}
        finally{button.disabled=false;}
    });
    const historyValue=(field,value)=>{
        if(field==='parent_information')return Object.entries(value||{}).map(([parent,details])=>`${parent}: ${Object.entries(details).filter(([,v])=>v).map(([key,v])=>`${key.replaceAll('_',' ')}: ${v}`).join(', ')}`).join('; ');
        if(field==='student_profile')return Object.entries(value||{}).filter(([,v])=>v).map(([key,v])=>`${key.replaceAll('_',' ')}: ${v}`).join(', ');
        const lists={term_assignment_id:termOptions(options.assignments),campus_id:options.campuses.map(c=>({value:c.id,label:c.campus_name_en}))};
        for(const [key,[list,label]]of Object.entries(courseOptions))lists[key]=options[list].map(v=>({value:v.id,label:v[label]}));
        return lists[field]?.find(v=>String(v.value)===String(value))?.label || (field==='enrolled_on'?date(value):value??'—');
    };
    get('gep-enrollment-rows').addEventListener('click',async event=>{
        const button=event.target.closest('[data-action]');if(!button)return;const row=rows.find(r=>r.id===Number(button.dataset.id));if(!row)return;
        if(button.dataset.action==='edit'){open(row);return;}
        if(button.dataset.action==='history'){
            get('gep-history-body').textContent='Loading activity history…';historyModal.show();
            try{const result=await request(`/${row.id}/history`);get('gep-history-body').innerHTML=result.data.map(item=>{
                const before=JSON.parse(item.before_values||'null'),after=JSON.parse(item.after_values||'null'),values=after||before||{};
                return `<div class="gep-history-event"><strong>${esc(item.action[0].toUpperCase()+item.action.slice(1))}</strong><div class="small text-secondary mb-2">${date(item.created_at)} · ${esc(item.changed_by||'System')}</div><div class="gep-history-values">${Object.entries(values).filter(([field,value])=>!before||!after||JSON.stringify(before[field])!==JSON.stringify(value)).map(([field,value])=>`<div><span class="text-secondary">${esc(field.replace(/_id$/,'').replaceAll('_',' '))}:</span> ${before&&after?`${esc(historyValue(field,before[field]))} → `:''}${esc(historyValue(field,value))}</div>`).join('')}</div></div>`;
            }).join('')||'No activity recorded.';}catch(error){get('gep-history-body').textContent=error.message;}return;
        }
        const confirm=await Swal.fire({title:'Delete this GEP enrollment?',text:`${row.gep_student.profile?.full_name_en||''}. Student information and activity history will be preserved.`,icon:'warning',showCancelButton:true,confirmButtonText:'Delete Enrollment',confirmButtonColor:'#d63939'});
        if(confirm.isConfirmed)try{const result=await request(`/${row.id}`,'DELETE');toast(result.message);load(page);}catch(error){showError('gep-list-error',error);}
    });
    setupBackToTop(get('gep-back-top'));load();
});
