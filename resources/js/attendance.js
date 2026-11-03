import Swal from 'sweetalert2';
import {initDailyAttendance} from './dailyAttendance.js';
import {initPremiumDatePicker} from './premiumDatePicker.js';
import {setupStaffLocationSelect} from './helpers/staffBirthplaceSelect.js';
import {initAttendanceDateNavigation} from './attendanceDateNavigation.js';

const root=document.querySelector('[data-attendance]');
if(root){
    const get=s=>root.querySelector(s),form=get('[data-att-filters]');
    const attendanceCalendar=initPremiumDatePicker(get('[data-att-date-picker]'));
    initAttendanceDateNavigation(root,attendanceCalendar);
    get('[data-premium-date-input]').addEventListener('change',event=>event.stopPropagation());
    // Reuse the system floating labels, including options loaded asynchronously.
    const floatingFields=[...root.querySelectorAll('[data-att-filters] > div, [data-att-history-filters] > div')];
    const syncFilterLabels=()=>floatingFields.forEach(field=>{
        field.classList.add('premium-form-field');
        const input=field.querySelector('[data-premium-date-input], select, input:not([type="hidden"])');
        // Blank-valued options still display text (for example "All students").
        // Keep the label floated whenever that visible text occupies the control.
        const hasText=input?.tagName==='SELECT'
            ? Boolean(input.selectedOptions[0]?.textContent.trim())
            : Boolean(input?.value);
        field.classList.toggle('has-value',hasText);
    });
    syncFilterLabels();
    root.addEventListener('input',syncFilterLabels);
    root.addEventListener('change',()=>queueMicrotask(syncFilterLabels),true);
    floatingFields.forEach(field=>new MutationObserver(syncFilterLabels).observe(field,{childList:true,subtree:true}));
    form.querySelectorAll('select').forEach(select=>{
        const field=select.parentElement;
        field.classList.add('staff-form-field');
        const label=field.querySelector('.form-label').textContent.trim();
        const prepareOptions=()=>[...select.options].forEach(option=>{
            option.dataset.en=option.textContent.trim();
        });
        prepareOptions();
        const combo=setupStaffLocationSelect(form,select,{
            labelId:`${select.id}Label`,optionsId:`${select.id}Options`,locationLabel:label,
        });
        const sync=()=>{prepareOptions();combo.sync();syncFilterLabels();};
        new MutationObserver(sync).observe(select,{childList:true,subtree:true});
        form.addEventListener('change',()=>queueMicrotask(sync),true);
        const search=field.querySelector('.location-combobox-search');
        search.addEventListener('change',event=>event.stopPropagation());
    });
    const escape=value=>String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
    const statuses={present:'Present',absent:'Absent',late:'Late',excused:'Excused',sick:'Sick'};
    let meta,sessions=[],selected=null,register=null,dirty=false,saving=false,loadVersion=0,filterSnapshot,daily;
    const api=async(path,body)=>{
        const response=await fetch(root.dataset.base+path,{method:body?'POST':'GET',credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,...(body?{'Content-Type':'application/json'}:{})},body:body?JSON.stringify(body):undefined});
        const result=await response.json().catch(()=>({}));
        if(!response.ok)throw new Error(Object.values(result.errors||{}).flat().join('\n')||result.message||'Unable to complete attendance request. Refresh and try again.');
        return result;
    };
    const run=async(callback)=>{if(saving)return;saving=true;try{await callback();}catch(error){await Swal.fire({icon:'error',title:'Attendance',text:error.message});}finally{saving=false;}};
    const fill=(select,rows,label,blank)=>{const value=select.value;select.replaceChildren(...(blank?[new Option(blank,'')]:[]),...rows.map(row=>new Option(row[label],row.id)));if([...select.options].some(option=>option.value===value))select.value=value;};
    const discard=()=>!dirty||confirm('Discard unsaved attendance changes?');
    async function load(){
        if(daily){await daily.load();if(daily.active())return;}
        const version=++loadVersion;
        if(!form.elements.academic_year_id.value)return;
        get('[data-att-sessions]').innerHTML='<div class="text-secondary">Loading sessions…</div>';
        const result=await api('/sessions?'+new URLSearchParams(new FormData(form)));if(version!==loadVersion)return;
        sessions=result.sessions;
        filterSnapshot=Object.fromEntries(new FormData(form));
        get('[data-att-summary]').innerHTML=[['Scheduled Sessions',sessions.length],['Submitted',sessions.filter(s=>s.session_status==='submitted').length],['To Complete',sessions.filter(s=>s.session_status!=='submitted').length]].map(([label,total])=>`<article class="att-stat"><span>${label}</span><strong>${total}</strong></article>`).join('');
        get('[data-att-sessions]').innerHTML=sessions.length?sessions.map(session=>`<button class="att-session" type="button" data-att-open="${session.id}"><div class="att-session-top"><span>${escape(session.starts_at.slice(0,5))} – ${escape(session.ends_at.slice(0,5))}</span><span class="badge ${session.session_status==='submitted'?'bg-green-lt':'bg-yellow-lt'}">${session.session_status==='submitted'?'Submitted':session.session_status==='draft'?'Draft':'Not Taken'}</span></div><h3>${escape(session.subject)}</h3><p><i class="ti ti-school"></i> ${escape(session.class_name)} · ${escape(session.campus_name_en)}</p><p><i class="ti ti-user"></i> ${escape(session.teacher_name)}</p></button>`).join(''):'<div class="text-secondary py-4">No subject sessions scheduled for this date and workspace. Published teacher schedules appear here automatically.</div>';
        if(get('[data-att-history-panel]').open)await loadHistory();
    }
    function counts(){
        const rows=[...get('[data-att-students]').querySelectorAll('tr')];
        const totals={unmarked:0,present:0,absent:0,late:0,excused:0,sick:0};rows.forEach(row=>totals[row.querySelector('[name="status"]').value||'unmarked']++);
        get('[data-att-counts]').innerHTML=Object.entries(totals).map(([key,count])=>`<span class="badge ${key==='absent'?'bg-red-lt':key==='present'?'bg-green-lt':'bg-blue-lt'}">${statuses[key]||'Unmarked'}: ${count}</span>`).join('');
    }
    async function open(session){
        register=await api(`/sessions/${session.id}?date=${encodeURIComponent(form.elements.date.value)}`);selected={...session,date:form.elements.date.value};dirty=false;
        const today=new Date(),todayIso=`${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}-${String(today.getDate()).padStart(2,'0')}`;
        const locked=register.session?.status==='submitted',future=selected.date>todayIso;
        get('[data-att-register]').hidden=false;
        get('[data-att-register-title]').textContent=`${session.subject} · ${session.class_name}`;
        get('[data-att-register-subtitle]').textContent=`${session.campus_name_en} · ${selected.date} · ${session.starts_at.slice(0,5)}–${session.ends_at.slice(0,5)}`;
        get('[data-att-register-status]').textContent=locked?'Submitted · Locked':future?'Future session · Read only':'Draft';
        get('[data-att-students]').innerHTML=register.students.map((student,index)=>`<tr data-student-id="${student.student_id}" data-att-name="${escape([student.name,student.name_kh,student.code].join(' ').toLowerCase())}"><td>${index+1}</td><td><strong class="att-name school-profile-khmer">${escape(student.name_kh)}</strong><span class="att-name">${escape(student.name)}</span><small>${escape(student.code)}</small></td><td><select name="status" class="form-select" aria-label="Attendance for ${escape(student.name)}" ${locked||future?'disabled':''}><option value="">Unmarked</option>${Object.entries(statuses).map(([value,label])=>`<option value="${value}" ${student.mark?.status===value?'selected':''}>${label}</option>`).join('')}</select></td><td><input type="number" name="late_minutes" class="form-control" min="0" max="480" value="${student.mark?.late_minutes||0}" aria-label="Late minutes for ${escape(student.name)}" ${locked||future?'disabled':''}></td><td><input type="text" name="note" class="form-control" maxlength="500" value="${escape(student.mark?.note||'')}" placeholder="Optional note" aria-label="Note for ${escape(student.name)}" ${locked||future?'disabled':''}></td></tr>`).join('');
        if(!register.students.length)get('[data-att-students]').innerHTML='<tr><td colspan="5">No eligible enrollments on this date.</td></tr>';
        for(const selector of ['[data-att-draft]','[data-att-submit]','[data-att-all]'])get(selector).disabled=locked||future||!register.students.length;
        get('[data-att-reopen]').hidden=!(locked&&register.can_reopen);
        get('[data-att-save-message]').textContent=locked?'Submitted attendance is protected from changes.':'Changes are not saved automatically.';
        get('[data-att-history]').innerHTML=register.history.length?register.history.map(event=>{let details={};try{details=JSON.parse(event.details);}catch{}return `<div class="border-bottom py-2"><strong>${escape(event.name)}</strong> · ${escape(event.action.replaceAll('_',' '))}<br><small>${escape(event.created_at)}</small>${details.reason?`<div>${escape(details.reason)}</div>`:''}</div>`;}).join(''):'No changes recorded yet.';
        counts();get('[data-att-search]').value='';
    }
    form.addEventListener('change',event=>{if(event.target.matches('[data-daily-student-filter], [data-daily-search]'))return;if(saving){Object.entries(filterSnapshot||{}).forEach(([name,value])=>form.elements[name].value=value);return;}run(async()=>{
        if(!discard()){Object.entries(filterSnapshot||{}).forEach(([name,value])=>form.elements[name].value=value);return;}
        dirty=false;get('[data-att-register]').hidden=true;selected=null;await load();
    });});
    form.addEventListener('submit',event=>event.preventDefault());
    get('[data-att-sessions]').addEventListener('click',event=>{const button=event.target.closest('[data-att-open]');if(button&&discard())run(()=>open(sessions.find(s=>String(s.id)===button.dataset.attOpen)));});
    get('[data-att-close]').addEventListener('click',()=>{if(discard()){dirty=false;get('[data-att-register]').hidden=true;}});
    get('[data-att-search]').addEventListener('input',event=>{const term=event.target.value.toLowerCase();get('[data-att-students]').querySelectorAll('[data-att-name]').forEach(row=>row.hidden=!row.dataset.attName.includes(term));});
    get('[data-att-mark-form]').addEventListener('input',()=>{dirty=true;get('[data-att-save-message]').textContent='Unsaved changes';counts();});
    get('[data-att-all]').addEventListener('click',()=>{get('[data-att-students]').querySelectorAll('[name="status"]').forEach(input=>{if(!input.value)input.value='present';});dirty=true;counts();});
    async function save(submit){
        if(!selected)return;
        const marks=[...get('[data-att-students]').querySelectorAll('[data-student-id]')].map(row=>({student_id:Number(row.dataset.studentId),status:row.querySelector('[name="status"]').value||null,late_minutes:Number(row.querySelector('[name="late_minutes"]').value||0),note:row.querySelector('[name="note"]').value}));
        if(submit){if(marks.some(mark=>!mark.status))throw new Error('Mark every student before submitting.');const confirmation=await Swal.fire({icon:'question',title:'Submit Attendance?',text:'This locks the register. A campus admin can reopen it with a correction reason.',showCancelButton:true,confirmButtonText:'Submit'});if(!confirmation.isConfirmed)return;}
        await api(`/sessions/${selected.id}`,{date:selected.date,version:register.session?.version||0,marks,submit});dirty=false;await open(selected);await load();
    }
    get('[data-att-draft]').addEventListener('click',()=>run(()=>save(false)));
    get('[data-att-mark-form]').addEventListener('submit',event=>{event.preventDefault();run(()=>save(true));});
    get('[data-att-reopen]').addEventListener('click',()=>run(async()=>{const result=await Swal.fire({title:'Correction Reason',input:'textarea',inputLabel:'Why does this attendance need correction?',showCancelButton:true,inputValidator:value=>value.trim().length<5?'Enter a reason of at least 5 characters.':undefined});if(!result.isConfirmed)return;await api(`/sessions/${selected.id}/reopen`,{date:selected.date,reason:result.value});await open(selected);await load();}));
    const historyQuery=()=>new URLSearchParams([...new FormData(form),...new FormData(get('[data-att-history-filters]'))]);
    async function loadHistory(){
        if(!form.elements.academic_year_id.value)return;
        const result=await api('/history?'+historyQuery());
        get('[data-att-history-results]').innerHTML=result.history.length?`<div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>Campus / Class</th><th>Subject</th><th>Teacher</th><th>Register</th>${Object.values(statuses).map(s=>`<th>${s}</th>`).join('')}</tr></thead><tbody>${result.history.map(row=>`<tr><td>${escape(row.attendance_date)}</td><td>${escape(row.campus_name_en)} · ${escape(row.class_name)}</td><td>${escape(row.subject)}</td><td>${escape(row.teacher_name)}</td><td>${escape(row.status)}</td>${Object.keys(statuses).map(key=>`<td>${Number(row[key]||0)}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`:'<p class="text-secondary">No saved attendance in this date range.</p>';
    }
    get('[data-att-history-panel]').addEventListener('toggle',event=>{if(event.target.open)run(loadHistory);});
    get('[data-att-history-filters]').addEventListener('submit',event=>{event.preventDefault();run(loadHistory);});
    get('[data-att-csv]').addEventListener('click',()=>{if(!get('[data-att-history-filters]').reportValidity())return;const query=historyQuery();query.set('format','csv');window.location.assign(root.dataset.base+'/history?'+query);});
    window.addEventListener('beforeunload',event=>{if(dirty){event.preventDefault();event.returnValue='';}});
    run(async()=>{meta=await api('/meta');fill(form.elements.academic_year_id,meta.years,'academic_year');fill(form.elements.campus_id,meta.campuses,'campus_name_en','All accessible campuses');if(meta.manage_campuses.length){form.elements.mode.append(new Option('Campus Subject Sessions','campus'));if(meta.manage_campuses.length===1)form.elements.campus_id.value=String(meta.manage_campuses[0]);}if(root.dataset.workspace!=='settings')daily=initDailyAttendance(root,meta);await load();});
}
