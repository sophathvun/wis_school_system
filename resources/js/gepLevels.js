import { renderPagination } from './helpers/pagination.js';
import Swal from 'sweetalert2';

document.addEventListener('DOMContentLoaded',()=>{
    const root=document.getElementById('gep-levels');if(!root)return;
    const get=id=>document.getElementById(id),base=root.dataset.url,permissions=JSON.parse(root.dataset.permissions);
    const form=get('gep-level-form'),modal=bootstrap.Modal.getOrCreateInstance(get('gep-level-modal'));
    const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    let rows=[],editing=null,page=1,timer,loading;
    const errorText=error=>Object.values(error.errors??{}).flat().join(' ')||error.message;
    const listError=message=>{get('gep-level-list-error').textContent=message;get('gep-level-list-error').classList.toggle('d-none',!message);};
    const request=async(url,method,data)=>{
        const response=await fetch(url,{method,headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:data?JSON.stringify(data):undefined});
        const result=await response.json().catch(()=>({}));if(!response.ok)throw Object.assign(new Error(result.message||'Unable to complete the request.'),{errors:result.errors});return result;
    };
    const load=async(target=1)=>{
        loading?.abort();loading=new AbortController();
        try{
            const response=await fetch(`${base}/data?${new URLSearchParams({page:target,perPage:get('gep-level-per-page').value,search:get('gep-level-search').value.trim()})}`,{headers:{Accept:'application/json'},signal:loading.signal});
            if(!response.ok)throw new Error('Unable to load GEP levels.');const result=await response.json();
            if(result.current_page>result.last_page)return load(result.last_page);
            rows=result.data;page=result.current_page;listError('');
            get('gep-level-rows').innerHTML=rows.length?rows.map((row,index)=>`<tr><td class="text-center"><span class="badge bg-primary-lt px-3 py-2">${result.from+index}</span></td><td>${esc(row.type)}</td><td class="fw-semibold">${esc(row.level_name)}</td><td>${esc(row.short_name)}</td><td>${esc(row.program)}</td><td>${esc(row.sort_order)}</td><td class="text-center"><button type="button" class="status-toggle ${row.status?'is-active':''}" data-level-status="${row.id}" aria-pressed="${row.status}" aria-label="${row.status?'Turn off':'Turn on'} ${esc(row.level_name)}" ${permissions.status?'':'disabled'}><span class="status-toggle-label">${row.status?'ON':'OFF'}</span><span class="status-toggle-knob"></span></button></td><td class="text-end"><div class="btn-list justify-content-end">${permissions.edit?`<button class="btn btn-outline-primary btn-sm" data-level-edit="${row.id}"><i class="ti ti-edit me-1"></i>Edit</button>`:''}${permissions.delete?`<button class="btn btn-outline-danger btn-sm" data-level-delete="${row.id}"><i class="ti ti-trash me-1"></i>Delete</button>`:''}${!permissions.edit&&!permissions.delete?'<span class="text-secondary">—</span>':''}</div></td></tr>`).join(''):'<tr><td colspan="8" class="text-center text-secondary py-5">No GEP levels found.</td></tr>';
            renderPagination(result,'gep-level-pagination','gep-level-per-page',load);
        }catch(error){if(error.name!=='AbortError')listError(error.message);}
    };
    const open=row=>{
        form.reset();get('gep-level-order').value=row?.sort_order??0;editing=row?.id??null;get('gep-level-error').classList.add('d-none');form.querySelectorAll('.is-invalid').forEach(el=>el.classList.remove('is-invalid'));
        get('gep-level-type').value=row?.type??'';get('gep-level-program').value=row?.program??'';get('gep-level-name').value=row?.level_name??'';get('gep-level-short').value=row?.short_name??'';
        get('gep-level-title').textContent=editing?'Edit GEP Level':'Add GEP Level';get('gep-level-save').textContent=editing?'Update':'Save';modal.show();
    };
    get('gep-level-add')?.addEventListener('click',()=>open());
    get('gep-level-modal').addEventListener('shown.bs.modal',()=>get('gep-level-type').focus());
    form.addEventListener('submit',async event=>{
        event.preventDefault();get('gep-level-save').disabled=true;form.querySelectorAll('.is-invalid').forEach(el=>el.classList.remove('is-invalid'));
        try{
            await request(editing?`${base}/${editing}`:base,editing?'PUT':'POST',{sort_order:get('gep-level-order').value,type:get('gep-level-type').value.trim(),program:get('gep-level-program').value.trim(),level_name:get('gep-level-name').value.trim(),short_name:get('gep-level-short').value.trim()});
            modal.hide();
            Swal.fire({icon:'success',title:editing?'GEP level updated successfully.':'GEP level added successfully.',toast:true,position:'top-end',timer:2500,timerProgressBar:true,showConfirmButton:false});
            await load(editing?page:1);
        }
        catch(error){get('gep-level-error').textContent=errorText(error);get('gep-level-error').classList.remove('d-none');for(const [key,messages] of Object.entries(error.errors??{})){form.elements.namedItem(key)?.classList.add('is-invalid');const hint=form.querySelector(`[data-error="${key}"]`);if(hint)hint.textContent=messages[0];}}
        finally{get('gep-level-save').disabled=false;}
    });
    get('gep-level-rows').addEventListener('click',async event=>{
        const button=event.target.closest('[data-level-edit],[data-level-delete],[data-level-status]');if(!button)return;
        const id=button.dataset.levelEdit??button.dataset.levelDelete??button.dataset.levelStatus,row=rows.find(item=>String(item.id)===id);if(!row)return;
        if(button.hasAttribute('data-level-edit'))return open(row);
        const status=button.hasAttribute('data-level-status');
        if(!status&&!window.confirm(`Delete GEP level ${row.level_name}?`))return;
        button.disabled=true;
        try{await request(`${base}/${id}${status?'/status':''}`,status?'PATCH':'DELETE',status?{status:!row.status}:undefined);await load(page);}
        catch(error){listError(errorText(error));button.disabled=false;}
    });
    get('gep-level-search').addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(()=>load(1),250);});load();
});
