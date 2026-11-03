import {renderDailyAttendanceRow} from './dailyAttendanceRow.js';
import {initAttendancePeriodPopup} from './attendancePeriodPopup.js';
import {initAttendanceLeave} from './attendanceLeave.js';

export function initDailyAttendance(root,meta){
    const get=s=>root.querySelector(s),filters=get('[data-att-filters]');
    const periodPopup=initAttendancePeriodPopup(root);
    const escape=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    let scope,register,students=[],dirty=false,loading=0,busy=false,lastFilters;
    let autosaveTimer,revision=0;
    let editPast=false,blankOnly=false,locked=true;
    const leave=initAttendanceLeave(root,meta,()=>({scope,students,busy,dirty}),load);
    const queueSave=()=>{clearTimeout(autosaveTimer);autosaveTimer=setTimeout(saveChanges,700);};
    const changed=()=>{if(locked)return;blankOnly=false;dirty=true;revision++;counts();get('[data-daily-message]').textContent='Changes will save automatically…';queueSave();};
    const statuses={P:'Present',EX:'Excused',UX:'Unexcused',L:'Late'};
    const studentFilter=get('[data-daily-student-filter]');
    const filterStudents=()=>{
        const term=get('[data-daily-search]').value.trim().toLowerCase();
        get('[data-daily-students]').querySelectorAll('[data-daily-name]').forEach(row=>{
            row.hidden=Boolean(studentFilter.value&&row.dataset.dailyStudent!==studentFilter.value)||!row.dataset.dailyName.includes(term);
        });
    };
    const syncStudentFilter=(previous=studentFilter.value)=>{
        studentFilter.replaceChildren(new Option('All students',''),...students.map(student=>new Option([student.name_kh,student.name].filter(Boolean).join(' · '),student.id)));
        if([...studentFilter.options].some(option=>option.value===previous))studentFilter.value=previous;
        filterStudents();
    };
    const sessionSections=[get('[data-att-summary]'),get('[data-att-sessions]').closest('section'),get('[data-att-history-panel]')];
    const active=()=>filters.elements.mode.value==='daily';
    if(meta.manage_campuses.length){filters.elements.mode.append(new Option('Admin — Daily Attendance','daily'));if(root.dataset.workspace==='daily')filters.elements.mode.value='daily';}
    const call=async(body,query)=>{
        const response=await fetch(root.dataset.base+'/daily'+(body?'':'?'+query),{method:body?'POST':'GET',credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,...(body?{'Content-Type':'application/json'}:{})},body:body?JSON.stringify(body):undefined});
        const data=await response.json();if(!response.ok)throw new Error(Object.values(data.errors||{}).flat().join('\n')||data.message||'Unable to load attendance.');return data;
    };
    function counts(){
        get('[data-daily-students]').querySelectorAll('[data-daily-student]').forEach(row=>{
            const status=row.querySelector('[data-daily-status]');
            status.dataset.attStatus=status.value;
            const exception=['EX','UX','L'].includes(status.value);
            row.querySelectorAll('[data-daily-exception]').forEach(field=>field.hidden=!exception);
            row.querySelectorAll('[data-status-choice]').forEach(button=>{
                button.setAttribute('aria-pressed',String(button.dataset.statusChoice===status.value));
                button.disabled=status.disabled;
            });
            row.querySelectorAll('[data-daily-missed], [data-daily-remarks]').forEach(input=>input.disabled=status.disabled||!status.value);
            row.querySelectorAll('[data-daily-period]').forEach(input=>{
                input.disabled=status.disabled;
                if(status.value==='P')input.checked=false;
            });
            const selected=row.querySelectorAll('[data-daily-period]:checked').length;
            const total=row.querySelectorAll('[data-daily-period]').length;
            const selectAll=row.querySelector('[data-daily-period-all]');
            selectAll.disabled=status.disabled||!total;
            selectAll.checked=total>0&&selected===total;
            selectAll.indeterminate=selected>0&&selected<total;
            row.querySelector('[data-daily-period-count]').textContent=`${selected} of ${total} selected`;
            const missed=row.querySelector('[data-daily-missed]');
            missed.readOnly=Boolean(selected)||status.value==='P';
            if(selected||status.value==='P')missed.value=selected;
            row.querySelector('[data-daily-period-summary]').textContent=selected?`${selected} periods selected`:'Select session';
            row.querySelector('[data-daily-missed-note]').textContent=`Missed periods: ${missed.value||0}`;
        });
        const result={P:0,EX:0,UX:0,L:0,unmarked:0};
        get('[data-daily-students]').querySelectorAll('[data-daily-status]').forEach(input=>result[input.value||'unmarked']++);
        get('[data-daily-counts]').innerHTML=Object.entries(result).map(([key,value])=>`<span class="badge ${key==='P'?'bg-green-lt':key==='UX'?'bg-red-lt':'bg-blue-lt'}">${key==='unmarked'?'Unmarked':`${key} · ${statuses[key]}`}: ${value}</span>`).join('');
    }
    function syncClasses(){
        const value=filters.elements.class_id.value;
        const pairs=meta.class_scopes.filter(row=>String(row.academic_year_id)===filters.elements.academic_year_id.value&&String(row.campus_id)===filters.elements.campus_id.value);
        const allowed=new Set(pairs.map(row=>`${row.class_id}:${row.grade_id||0}`));
        filters.elements.class_id.replaceChildren(new Option('Select grade',''),...pairs.map(row=>new Option(row.label,`${row.class_id}:${row.grade_id||0}`)));
        if(allowed.has(value))filters.elements.class_id.value=value;
    }
    async function load(){
        periodPopup.close();
        clearTimeout(autosaveTimer);
        editPast=false;blankOnly=false;locked=true;get('[data-daily-edit]').hidden=true;
        const version=++loading;
        const daily=active();get('[data-att-daily]').hidden=!daily;get('[data-att-daily-class]').hidden=!daily;
        root.querySelectorAll('[data-att-daily-filter]').forEach(field=>field.hidden=!daily);
        get('[data-att-filter-title]').textContent=daily?'Daily Attendance Filters':'Subject Session Filters';
        get('[data-att-date-label]').textContent=daily?'Attendance Date':'Session Date';
        sessionSections.forEach(section=>section.hidden=daily);
        if(!daily)return;
        const selectedStudent=studentFilter.value;
        get('[data-att-register]').hidden=true;
        syncClasses();
        lastFilters=Object.fromEntries(new FormData(filters));
        const {academic_year_id,campus_id,date}=lastFilters;
        const [class_id,grade_id]=String(lastFilters.class_id||'').split(':');
        get('[data-daily-save]').disabled=true;get('[data-daily-save]').hidden=true;get('[data-daily-present]').disabled=true;
        scope=null;students=[];get('[data-daily-counts]').innerHTML='';get('[data-daily-history]').textContent='';
        leave.load();
        syncStudentFilter();
        if(!academic_year_id||!campus_id||!class_id||!date){get('[data-daily-students]').innerHTML='<tr><td colspan="7">Select Academic Year, Campus, and Grade/Class to display students.</td></tr>';return;}
        if(!meta.manage_campuses.includes(Number(campus_id))){get('[data-daily-students]').innerHTML='<tr><td colspan="7">Choose a campus where you can administer attendance.</td></tr>';return;}
        get('[data-daily-students]').innerHTML='<tr><td colspan="7">Loading class register…</td></tr>';
        try{
            const data=await call(null,new URLSearchParams({academic_year_id,campus_id,class_id,...(Number(grade_id)?{grade_id}:{}),date}));if(version!==loading)return;
            scope={academic_year_id,campus_id,class_id,...(Number(grade_id)?{grade_id}:{}),date};register=data.register;students=data.students;dirty=false;
            const today=data.today;
            const future=date>today,past=date<today,conflict=students.some(s=>s.other_class),disabled=future||past||conflict;
            locked=disabled;
            get('[data-daily-edit]').hidden=!past||conflict||!students.length;
            get('[data-daily-students]').innerHTML=students.length?students.map((student,index)=>renderDailyAttendanceRow(student,index,disabled,statuses,escape,meta.periods)).join(''):'<tr><td colspan="7">No students enrolled in this class on the selected date.</td></tr>';
            get('[data-daily-save]').disabled=disabled||!students.length;get('[data-daily-present]').disabled=disabled||!students.length;
            get('[data-daily-message]').textContent=future?'Future date — read only.':conflict?'Update the original class register to avoid duplicate daily attendance.':past?'Past attendance — read only. Click Edit to make corrections.':register?'Saved register loaded. Changes save automatically.':'Changes save automatically when you mark attendance.';
            get('[data-daily-history]').innerHTML=data.history.length?data.history.map(event=>`<div class="border-bottom py-2">${escape(event.name)} · ${escape(event.created_at)}</div>`).join(''):'No saved changes yet.';
            counts();
            syncStudentFilter(selectedStudent);
            leave.load();
        }catch(error){if(version===loading)get('[data-daily-students]').innerHTML=`<tr><td colspan="7" class="text-danger">${escape(error.message)}</td></tr>`;}
    }
    filters.addEventListener('change',event=>{
        if(event.target.matches('[data-premium-date-input], .location-combobox-search, [data-daily-student-filter], [data-daily-search]'))return;
        if(busy){event.stopImmediatePropagation();Object.entries(lastFilters||{}).forEach(([key,value])=>filters.elements[key].value=value);return;}
        if(dirty&&!confirm('Discard unsaved daily attendance changes?')){
            event.stopImmediatePropagation();Object.entries(lastFilters||{}).forEach(([key,value])=>filters.elements[key].value=value);return;
        }
        dirty=false;
        if(['academic_year_id','campus_id'].includes(event.target.name))syncClasses();
    },true);
    get('[data-daily-edit]').addEventListener('click',()=>{
        if(busy||!scope)return;
        editPast=true;locked=false;
        get('[data-daily-students]').querySelectorAll('[data-daily-status]').forEach(input=>input.disabled=false);
        get('[data-daily-edit]').hidden=true;get('[data-daily-present]').disabled=false;get('[data-daily-save]').disabled=false;
        counts();get('[data-daily-message]').textContent='Editing past attendance. Corrections save automatically.';
    });
    get('[data-daily-present]').addEventListener('click',()=>{
        if(locked||busy)return;
        let filled=0;
        get('[data-daily-students]').querySelectorAll('[data-daily-status]').forEach(input=>{
            if(input.value||input.disabled)return;
            input.value='P';input.closest('[data-daily-student]').querySelector('[data-daily-missed]').value=0;filled++;
        });
        if(!filled){get('[data-daily-message]').textContent='No blank attendance to fill. Existing statuses are unchanged.';return;}
        const wasDirty=dirty;changed();blankOnly=!wasDirty;
    });
    get('[data-daily-form]').addEventListener('click',event=>{
        const button=event.target.closest('[data-status-choice]');
        if(!button||button.disabled)return;
        const status=button.closest('[data-daily-student]').querySelector('[data-daily-status]');
        if(status.value===button.dataset.statusChoice)return;
        status.value=button.dataset.statusChoice;
        changed();
    });
    get('[data-daily-form]').addEventListener('input',event=>{
        if(event.target.matches('[data-daily-period], [data-daily-period-all]')){
            const row=event.target.closest('[data-daily-student]');
            const status=row.querySelector('[data-daily-status]');
            if(event.target.matches('[data-daily-period-all]')){
                row.querySelectorAll('[data-daily-period]:not(:disabled)').forEach(input=>input.checked=event.target.checked);
            }
            if(event.target.checked&&(!status.value||status.value==='P'))status.value='UX';
            row.querySelector('[data-daily-missed]').value=row.querySelectorAll('[data-daily-period]:checked').length;
        }
        changed();
    });
    get('[data-daily-form]').addEventListener('change',changed);
    get('[data-daily-search]').addEventListener('input',filterStudents);
    studentFilter.addEventListener('change',filterStudents);
    async function saveChanges(){
        clearTimeout(autosaveTimer);
        if(busy||locked||!scope||!dirty)return;
        const marks=[...get('[data-daily-students]').querySelectorAll('[data-daily-student]')].map(row=>({student_id:Number(row.dataset.dailyStudent),status:row.querySelector('[data-daily-status]').value,missed_sessions:Number(row.querySelector('[data-daily-missed]').value||0),absent_period_ids:[...row.querySelectorAll('[data-daily-period]:checked')].map(input=>Number(input.value)),remarks:row.querySelector('[data-daily-remarks]').value}));
        const marked=marks.filter(mark=>mark.status);
        if(!marked.length){get('[data-daily-message]').textContent='Choose an attendance status to save. Unmarked students remain blank.';return;}
        if(!get('[data-daily-form]').checkValidity()){get('[data-daily-message]').textContent='Not saved — correct the highlighted field.';get('[data-daily-form]').reportValidity();return;}
        const savedRevision=revision;
        busy=true;get('[data-daily-save]').disabled=true;
        get('[data-daily-message]').textContent='Saving…';
        try{
            const result=await call({...scope,version:register?.version||0,partial:true,edit_past:editPast,only_blank:blankOnly,marks:marked});
            register={...register,version:result.version};dirty=revision!==savedRevision;
            // Refresh attribution without replacing controls or interrupting typing.
            const savedScope={...scope};
            call(null,new URLSearchParams(savedScope)).then(data=>{
                if(Object.entries(savedScope).some(([key,value])=>scope?.[key]!==value)||data.register?.version!==register?.version)return;
                data.students.forEach(student=>{
                    const cell=get(`[data-daily-author="${student.id}"]`),author=student.added_by;
                    if(!cell||!author)return;
                    cell.innerHTML=`<strong class="att-name">${escape(author.name||'—')}</strong><small class="att-name">${escape(author.position||'—')}</small><small class="att-name">${escape(author.date||'—')}</small>`;
                });
            }).catch(()=>{});
            get('[data-daily-message]').textContent=dirty?'Saving latest changes…':`All changes saved · ${new Date().toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'})}`;
            get('[data-daily-save]').hidden=true;
            if(dirty)queueSave();
        }
        catch(error){clearTimeout(autosaveTimer);get('[data-daily-message]').textContent=`Not saved — ${error.message} Use Retry Save to try again.`;get('[data-daily-save]').hidden=false;}
        finally{busy=false;if(scope)get('[data-daily-save]').disabled=false;}
    }
    get('[data-daily-form]').addEventListener('submit',event=>{event.preventDefault();saveChanges();});
    window.addEventListener('beforeunload',event=>{if(dirty){event.preventDefault();event.returnValue='';}});
    return {active,load};
}
