import { renderPagination } from './helpers/pagination.js';

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('gep-years');
    if (!root) return;
    const get = id => document.getElementById(id);
    const permissions = JSON.parse(root.dataset.permissions);
    const base = root.dataset.url;
    const modal = bootstrap.Modal.getOrCreateInstance(get('gep-year-modal'));
    const form = get('gep-year-form');
    const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
    let rows = [], editing = null, currentPage = 1, searchTimer, loadRequest;
    const request = async (url, method, data) => {
        const response = await fetch(url, {method, headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:data?JSON.stringify(data):undefined});
        const result = await response.json().catch(() => ({}));
        if (!response.ok) throw Object.assign(new Error(result.message || 'Unable to complete the request.'), {errors:result.errors});
        return result;
    };
    const showListError = message => {get('gep-year-list-error').textContent=message;get('gep-year-list-error').classList.toggle('d-none',!message);};
    const load = async (page = 1) => {
        loadRequest?.abort();loadRequest=new AbortController();
        try {
            const response=await fetch(`${base}/data?${new URLSearchParams({page,perPage:get('gep-year-per-page').value,search:get('gep-year-search').value.trim()})}`,{headers:{Accept:'application/json'},signal:loadRequest.signal});
            if(!response.ok)throw new Error('Unable to load GEP academic years.');
            const result=await response.json();
            if(result.current_page>result.last_page)return load(result.last_page);
            rows=result.data;currentPage=result.current_page;showListError('');
            get('gep-year-rows').innerHTML=rows.length?rows.map((row,index)=>`<tr><td class="text-center"><span class="badge bg-primary-lt px-3 py-2">${result.from+index}</span></td><td class="fw-semibold">${esc(row.academic_year)}</td><td><span class="badge bg-${({pending:'orange',started:'green',finished:'secondary'})[row.lifecycle_status]}-lt">${esc(row.lifecycle_status[0].toUpperCase()+row.lifecycle_status.slice(1))}</span></td><td class="text-center">${row.lifecycle_status==='started'?'<span class="badge bg-green-lt"><i class="ti ti-check me-1"></i>Started</span>':permissions.start?`<button class="btn btn-outline-success btn-sm" data-action="start" data-id="${row.id}">Set Started</button>`:'<span class="text-secondary">—</span>'}</td><td class="text-end"><div class="btn-list justify-content-end">${permissions.edit?`<button class="btn btn-outline-primary btn-sm" data-action="edit" data-id="${row.id}" aria-label="Edit ${esc(row.academic_year)}"><i class="ti ti-edit me-1"></i>Edit</button>`:''}${permissions.delete?`<button class="btn btn-outline-danger btn-sm" data-action="delete" data-id="${row.id}" ${row.lifecycle_status==='started'?'disabled title="Finish this year before deleting"':''}><i class="ti ti-trash me-1"></i>Delete</button>`:''}${!permissions.edit&&!permissions.delete?'<span class="text-secondary">—</span>':''}</div></td></tr>`).join(''):'<tr><td colspan="5" class="text-center text-secondary py-5">No GEP academic years found.</td></tr>';
            renderPagination(result,'gep-year-pagination','gep-year-per-page',load);
        } catch(error) {if(error.name!=='AbortError')showListError(error.message);}
    };
    const open = row => {
        form.reset();editing=row?.id??null;
        form.querySelectorAll('.is-invalid').forEach(el=>el.classList.remove('is-invalid'));
        get('gep-year-error').classList.add('d-none');
        get('gep-year-name').value=row?.academic_year??'';
        get('gep-year-status').value=row?.lifecycle_status??'pending';
        get('gep-year-title').textContent=editing?'Edit GEP Academic Year':'Add GEP Academic Year';
        get('gep-year-save').textContent=editing?'Update':'Save';modal.show();
    };
    get('gep-year-add')?.addEventListener('click',()=>open());
    get('gep-year-modal').addEventListener('shown.bs.modal',()=>get('gep-year-name').focus());
    form.addEventListener('submit',async event=>{
        event.preventDefault();
        const data={academic_year:get('gep-year-name').value.trim(),lifecycle_status:get('gep-year-status').value};
        const previous=rows.find(row=>row.id===editing);
        if(data.lifecycle_status==='started'&&previous?.lifecycle_status!=='started'&&!window.confirm('Start this GEP academic year? Any other started GEP year will be marked Finished. WIS academic years will not change.'))return;
        get('gep-year-save').disabled=true;
        form.querySelectorAll('.is-invalid').forEach(el=>el.classList.remove('is-invalid'));
        try {await request(editing?`${base}/${editing}`:base,editing?'PUT':'POST',data);modal.hide();await load(editing?currentPage:1);}
        catch(error){get('gep-year-error').textContent=Object.values(error.errors??{}).flat().join(' ')||error.message;get('gep-year-error').classList.remove('d-none');for(const [key,messages] of Object.entries(error.errors??{})){const control=form.elements.namedItem(key);control?.classList.add('is-invalid');const hint=form.querySelector(`[data-error="${key}"]`);if(hint)hint.textContent=messages[0];}}
        finally {get('gep-year-save').disabled=false;}
    });
    get('gep-year-rows').addEventListener('click',async event=>{
        const button=event.target.closest('[data-action]');if(!button)return;
        const row=rows.find(row=>String(row.id)===button.dataset.id);if(!row)return;
        if(button.dataset.action==='edit')return open(row);
        const starting=button.dataset.action==='start';
        if(!window.confirm(starting?`Start GEP ${row.academic_year}? Any other started GEP year will be marked Finished.`:`Delete GEP academic year ${row.academic_year}?`))return;
        button.disabled=true;
        try{await request(`${base}/${row.id}${starting?'/start':''}`,starting?'POST':'DELETE');await load(currentPage);}
        catch(error){showListError(Object.values(error.errors??{}).flat().join(' ')||error.message);button.disabled=false;}
    });
    get('gep-year-search').addEventListener('input',()=>{clearTimeout(searchTimer);searchTimer=setTimeout(()=>load(1),250);});
    load();
});
