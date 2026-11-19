import { renderPagination } from './helpers/pagination.js';

document.addEventListener('DOMContentLoaded',()=>{
    const root=document.getElementById('gep-terms');if(!root)return;
    const get=id=>document.getElementById(id),base=root.dataset.url,permissions=JSON.parse(root.dataset.permissions);
    const form=get('gep-term-form'),modal=bootstrap.Modal.getOrCreateInstance(get('gep-term-modal'));
    const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    let rows=[],editing=null,page=1,timer,loading;
    const errorText=error=>Object.values(error.errors??{}).flat().join(' ')||error.message;
    const listError=message=>{get('gep-term-list-error').textContent=message;get('gep-term-list-error').classList.toggle('d-none',!message);};
    const request=async(url,method,data)=>{
        const response=await fetch(url,{method,headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:data?JSON.stringify(data):undefined});
        const result=await response.json().catch(()=>({}));if(!response.ok)throw Object.assign(new Error(result.message||'Unable to complete the request.'),{errors:result.errors});return result;
    };
    const load=async(target=1)=>{
        loading?.abort();loading=new AbortController();
        try{
            const response=await fetch(`${base}/data?${new URLSearchParams({page:target,perPage:get('gep-term-per-page').value,search:get('gep-term-search').value.trim()})}`,{headers:{Accept:'application/json'},signal:loading.signal});
            if(!response.ok)throw new Error('Unable to load GEP terms.');const result=await response.json();
            if(result.current_page>result.last_page)return load(result.last_page);
            rows=result.data;page=result.current_page;listError('');
            get('gep-term-rows').innerHTML=rows.length?rows.map((row,index)=>`<tr><td class="text-center"><span class="badge bg-primary-lt px-3 py-2">${result.from+index}</span></td><td class="fw-semibold">${esc(row.term_name)}</td><td>${esc(row.short_name)}</td><td class="text-center"><button type="button" class="status-toggle ${row.status?'is-active':''}" data-term-status="${row.id}" aria-pressed="${row.status}" aria-label="${row.status?'Turn off':'Turn on'} ${esc(row.term_name)}" ${permissions.status?'':'disabled'}><span class="status-toggle-label">${row.status?'ON':'OFF'}</span><span class="status-toggle-knob"></span></button></td><td class="text-end"><div class="btn-list justify-content-end">${permissions.edit?`<button class="btn btn-outline-primary btn-sm" data-term-edit="${row.id}"><i class="ti ti-edit me-1"></i>Edit</button>`:''}${permissions.delete?`<button class="btn btn-outline-danger btn-sm" data-term-delete="${row.id}"><i class="ti ti-trash me-1"></i>Delete</button>`:''}${!permissions.edit&&!permissions.delete?'<span class="text-secondary">—</span>':''}</div></td></tr>`).join(''):'<tr><td colspan="5" class="text-center text-secondary py-5">No GEP terms found.</td></tr>';
            renderPagination(result,'gep-term-pagination','gep-term-per-page',load);
        }catch(error){if(error.name!=='AbortError')listError(error.message);}
    };
    const open=row=>{
        form.reset();editing=row?.id??null;get('gep-term-error').classList.add('d-none');form.querySelectorAll('.is-invalid').forEach(el=>el.classList.remove('is-invalid'));
        get('gep-term-name').value=row?.term_name??'';get('gep-term-short').value=row?.short_name??'';
        get('gep-term-title').textContent=editing?'Edit GEP Term':'Add GEP Term';get('gep-term-save').textContent=editing?'Update':'Save';modal.show();
    };
    get('gep-term-add')?.addEventListener('click',()=>open());
    get('gep-term-modal').addEventListener('shown.bs.modal',()=>get('gep-term-name').focus());
    form.addEventListener('submit',async event=>{
        event.preventDefault();get('gep-term-save').disabled=true;form.querySelectorAll('.is-invalid').forEach(el=>el.classList.remove('is-invalid'));
        try{await request(editing?`${base}/${editing}`:base,editing?'PUT':'POST',{term_name:get('gep-term-name').value.trim(),short_name:get('gep-term-short').value.trim()});modal.hide();await load(editing?page:1);}
        catch(error){get('gep-term-error').textContent=errorText(error);get('gep-term-error').classList.remove('d-none');for(const [key,messages] of Object.entries(error.errors??{})){form.elements.namedItem(key)?.classList.add('is-invalid');const hint=form.querySelector(`[data-error="${key}"]`);if(hint)hint.textContent=messages[0];}}
        finally{get('gep-term-save').disabled=false;}
    });
    get('gep-term-rows').addEventListener('click',async event=>{
        const button=event.target.closest('[data-term-edit],[data-term-delete],[data-term-status]');if(!button)return;
        const id=button.dataset.termEdit??button.dataset.termDelete??button.dataset.termStatus,row=rows.find(item=>String(item.id)===id);if(!row)return;
        if(button.hasAttribute('data-term-edit'))return open(row);
        const status=button.hasAttribute('data-term-status');
        if(!status&&!window.confirm(`Delete GEP term ${row.term_name}?`))return;
        button.disabled=true;
        try{await request(`${base}/${id}${status?'/status':''}`,status?'PATCH':'DELETE',status?{status:!row.status}:undefined);await load(page);}
        catch(error){listError(errorText(error));button.disabled=false;}
    });
    get('gep-term-search').addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(()=>load(1),250);});load();
});
