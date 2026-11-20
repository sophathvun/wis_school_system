import Swal from 'sweetalert2';
import { renderPagination } from './helpers/pagination.js';
import { setupWmiYearPicker } from './helpers/wmiYearPicker.js';

document.addEventListener('DOMContentLoaded',()=>{
    const root=document.getElementById('gep-term-assignments');if(!root)return;
    const get=id=>document.getElementById(id),base=root.dataset.url,permissions=JSON.parse(root.dataset.permissions);
    const form=get('assignment-form'),modal=bootstrap.Modal.getOrCreateInstance(get('assignment-modal'));
    const yearPicker=setupWmiYearPicker(get('assignment-year-filter'),'GEP Academic Year','ti-calendar');
    const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const message=error=>Object.values(error.errors??{}).flat().join(' ')||error.message;
    const date=value=>value?new Date(`${value}T00:00:00`).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}).replaceAll(' ','-'):'—';
    const listError=text=>{get('assignment-list-error').textContent=text;get('assignment-list-error').classList.toggle('d-none',!text);};
    let rows=[],options={years:[],terms:[]},editing=null,page=1,summary={},timer,loading;
    const request=async(path='',method='GET',data,signal)=>{
        const response=await fetch(base+path,{method,signal,headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:data?JSON.stringify(data):undefined});
        const result=await response.json().catch(()=>({}));if(!response.ok)throw Object.assign(new Error(result.message||'Unable to complete the request.'),{errors:result.errors});return result;
    };
    const notify=title=>Swal.fire({toast:true,position:'top-end',icon:'success',title,timer:2500,timerProgressBar:true,showConfirmButton:false});
    const load=async(target=1)=>{
        loading?.abort();loading=new AbortController();
        try{
            const result=await request(`/data?${new URLSearchParams({academic_year_id:get('assignment-year-filter').value,search:get('assignment-search').value.trim(),perPage:get('assignment-per-page').value,page:target})}`,'GET',undefined,loading.signal);
            if(result.current_page>result.last_page)return load(result.last_page);
            rows=result.data;page=result.current_page;summary=result;listError('');
            get('assignment-rows').innerHTML=rows.length?rows.map((row,index)=>{
                const status=row.lifecycle_status,label=status[0].toUpperCase()+status.slice(1);
                const canStart=permissions.start&&status!=='started'&&row.academic_year.status&&row.term.status&&(!row.current_term||permissions.finish);
                return `<tr><td><span class="badge bg-primary-lt px-3 py-2">${result.from+index}</span></td><td>${esc(row.academic_year.academic_year)}</td><td class="fw-semibold">${esc(row.term.term_name)}</td><td>${row.sort_order}</td><td class="text-nowrap">${date(row.start_date)}</td><td class="text-nowrap">${date(row.end_date)}</td><td><span class="badge bg-${({pending:'orange',started:'green',finished:'red'})[status]}-lt">${label}</span></td><td>${status==='started'?'<span class="badge bg-green-lt">Started</span>':canStart?`<button class="btn btn-success btn-sm text-nowrap" data-action="start" data-id="${row.id}"><i class="ti ti-check me-1" aria-hidden="true"></i>Set Started</button>`:'<span class="text-secondary">—</span>'}</td><td><span class="text-${row.schedule_notice==='Upcoming'?'secondary':'warning'}">${esc(row.schedule_notice||'—')}</span></td><td><div class="btn-list justify-content-end flex-nowrap">${status==='started'&&permissions.finish?`<button class="btn btn-outline-warning btn-sm" data-action="finish" data-id="${row.id}">Finish</button>`:''}${permissions.edit?`<button class="btn btn-outline-primary btn-sm" data-action="edit" data-id="${row.id}">Edit</button>`:''}${permissions.delete&&status==='pending'?`<button class="btn btn-outline-danger btn-sm" data-action="delete" data-id="${row.id}">Delete</button>`:''}</div></td></tr>`;
            }).join(''):'<tr><td colspan="10" class="text-center text-secondary py-4">No term assignments found.</td></tr>';
            renderPagination(result,'assignment-pagination','assignment-per-page',load);
        }catch(error){if(error.name!=='AbortError')listError(message(error));}
    };
    const open=row=>{
        editing=row?.id??null;form.reset();form.querySelectorAll('.is-invalid').forEach(el=>el.classList.remove('is-invalid'));get('assignment-error').classList.add('d-none');
        get('assignment-year').innerHTML='<option value="">Select Academic Year</option>'+options.years.map(year=>`<option value="${year.id}">${esc(year.academic_year)}</option>`).join('');
        get('assignment-term').innerHTML='<option value="">Select Term</option>'+options.terms.filter(term=>term.status||term.id===row?.term_id).map(term=>`<option value="${term.id}">${esc(term.term_name)}${!term.status?' (Off)':''}</option>`).join('');
        get('assignment-year').value=row?.academic_year_id??get('assignment-year-filter').value;
        get('assignment-term').value=row?.term_id??'';get('assignment-order').value=row?.sort_order??'';
        get('assignment-start').value=row?.start_date??'';get('assignment-end').value=row?.end_date??'';
        for(const id of ['assignment-year','assignment-term','assignment-order'])get(id).disabled=Boolean(row&&row.lifecycle_status!=='pending');
        get('assignment-title').textContent=editing?'Edit Term Assignment':'Assign GEP Term';get('assignment-save').textContent=editing?'Update':'Save';modal.show();
    };
    get('assignment-add')?.addEventListener('click',()=>open());
    form.addEventListener('submit',async event=>{
        event.preventDefault();get('assignment-save').disabled=true;form.querySelectorAll('.is-invalid').forEach(el=>el.classList.remove('is-invalid'));
        const data=Object.fromEntries(['academic_year_id','term_id','sort_order','start_date','end_date'].map(name=>[name,form.elements.namedItem(name).value]));
        try{
            const result=await request(editing?`/${editing}`:'',editing?'PUT':'POST',data);
            get('assignment-year-filter').value=String(result.data.academic_year_id);
            yearPicker.sync();
            get('assignment-search').value='';clearTimeout(timer);
            modal.hide();notify(result.message);
            await load(1);
        }
        catch(error){get('assignment-error').textContent=message(error);get('assignment-error').classList.remove('d-none');for(const [key,messages] of Object.entries(error.errors??{})){form.elements.namedItem(key)?.classList.add('is-invalid');const hint=form.querySelector(`[data-error="${key}"]`);if(hint)hint.textContent=messages[0];}}
        finally{get('assignment-save').disabled=false;}
    });
    get('assignment-rows').addEventListener('click',async event=>{
        const button=event.target.closest('[data-action]');if(!button)return;
        const row=rows.find(row=>String(row.id)===button.dataset.id);if(!row)return;
        const action=button.dataset.action;if(action==='edit')return open(row);
        const current=row.current_term;
        const text=action==='start'?`Set GEP ${row.academic_year.academic_year} ${row.term.term_name} Started? Earlier academic years and earlier terms will become Finished. Later academic years and later terms will become Pending. ${row.schedule_notice?`Schedule reminder: ${row.schedule_notice}. `:''}Student enrollments will stay unchanged.`:action==='finish'?'This term will be marked Finished. The next term will remain Pending until staff start it.':'Delete this Pending term assignment?';
        const confirm=await Swal.fire({title:`${action==='start'?'Set Started:':action==='finish'?'Finish':'Delete'} ${row.term.term_name}?`,text,icon:action==='delete'?'warning':'question',showCancelButton:true,confirmButtonText:action==='start'?'Set Started':action==='finish'?'Finish Term':'Delete'});if(!confirm.isConfirmed)return;
        button.disabled=true;
        try{const result=await request(`/${row.id}${action==='delete'?'':`/${action}`}`,action==='delete'?'DELETE':'POST',action==='start'?{confirm_finish_current:Boolean(current)}:undefined);notify(result.message);await load(page);}
        catch(error){listError(message(error));button.disabled=false;}
    });
    get('assignment-year-filter').addEventListener('change',()=>load(1));
    get('assignment-search').addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(()=>load(1),250);});
    const initialize=async()=>{
        try{options=await request('/options');get('assignment-year-filter').innerHTML='<option value="">All Academic Years</option>'+options.years.map(year=>`<option value="${year.id}">${esc(year.academic_year)}</option>`).join('');get('assignment-year-filter').value='';yearPicker.sync();await load();}
        catch(error){listError(message(error));}
    };initialize();
});
