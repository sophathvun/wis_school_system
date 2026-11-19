import Swal from 'sweetalert2';
import { renderPagination } from './helpers/pagination.js';

document.addEventListener('DOMContentLoaded',()=>{
    const root=document.getElementById('gep-class-types');if(!root)return;
    const get=id=>document.getElementById(id),base=root.dataset.url,permissions=JSON.parse(root.dataset.permissions);
    const form=get('gep-class-type-form'),modal=bootstrap.Modal.getOrCreateInstance(get('gep-class-type-modal'));
    const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    let rows=[],editing=null,page=1,timer,loading;
    const errorText=error=>Object.values(error.errors??{}).flat().join(' ')||error.message;
    const listError=message=>{get('gep-class-type-list-error').textContent=message;get('gep-class-type-list-error').classList.toggle('d-none',!message);};
    const request=async(url,method,data)=>{
        const response=await fetch(url,{method,headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:data?JSON.stringify(data):undefined});
        const result=await response.json().catch(()=>({}));if(!response.ok)throw Object.assign(new Error(result.message||'Unable to complete the request.'),{errors:result.errors});return result;
    };
    const load=async(target=1)=>{
        loading?.abort();loading=new AbortController();
        try{
            const response=await fetch(`${base}/data?${new URLSearchParams({page:target,perPage:get('gep-class-type-per-page').value,search:get('gep-class-type-search').value.trim()})}`,{headers:{Accept:'application/json'},signal:loading.signal});
            if(!response.ok)throw new Error('Unable to load GEP class types.');const result=await response.json();
            if(result.current_page>result.last_page)return load(result.last_page);
            rows=result.data;page=result.current_page;listError('');
            get('gep-class-type-rows').innerHTML=rows.length?rows.map((row,index)=>`<tr><td class="text-center"><span class="badge bg-primary-lt px-3 py-2">${result.from+index}</span></td><td class="fw-semibold">${esc(row.type_name)}</td><td>${esc(row.sort_order)}</td><td class="text-center"><button type="button" class="status-toggle ${row.status?'is-active':''}" data-class-type-status="${row.id}" aria-pressed="${row.status}" aria-label="${row.status?'Turn off':'Turn on'} ${esc(row.type_name)}" ${permissions.status?'':'disabled'}><span class="status-toggle-label">${row.status?'ON':'OFF'}</span><span class="status-toggle-knob"></span></button></td><td class="text-end"><div class="btn-list justify-content-end">${permissions.edit?`<button class="btn btn-outline-primary btn-sm" data-class-type-edit="${row.id}"><i class="ti ti-edit me-1"></i>Edit</button>`:''}${permissions.delete?`<button class="btn btn-outline-danger btn-sm" data-class-type-delete="${row.id}"><i class="ti ti-trash me-1"></i>Delete</button>`:''}${!permissions.edit&&!permissions.delete?'<span class="text-secondary">—</span>':''}</div></td></tr>`).join(''):'<tr><td colspan="5" class="text-center text-secondary py-5">No GEP class types found.</td></tr>';
            renderPagination(result,'gep-class-type-pagination','gep-class-type-per-page',load);
        }catch(error){if(error.name!=='AbortError')listError(error.message);}
    };
    const open=row=>{
        form.reset();get('gep-class-type-order').value=row?.sort_order??0;editing=row?.id??null;get('gep-class-type-error').classList.add('d-none');form.querySelectorAll('.is-invalid').forEach(el=>el.classList.remove('is-invalid'));
        get('gep-class-type-name').value=row?.type_name??'';
        get('gep-class-type-title').textContent=editing?'Edit GEP Class Type':'Add GEP Class Type';get('gep-class-type-save').textContent=editing?'Update':'Save';modal.show();
    };
    get('gep-class-type-add')?.addEventListener('click',()=>open());
    get('gep-class-type-modal').addEventListener('shown.bs.modal',()=>get('gep-class-type-name').focus());
    form.addEventListener('submit',async event=>{
        event.preventDefault();get('gep-class-type-save').disabled=true;form.querySelectorAll('.is-invalid').forEach(el=>el.classList.remove('is-invalid'));
        try{await request(editing?`${base}/${editing}`:base,editing?'PUT':'POST',{sort_order:get('gep-class-type-order').value,type_name:get('gep-class-type-name').value.trim()});modal.hide();Swal.fire({icon:'success',title:editing?'GEP class type updated successfully.':'GEP class type added successfully.',toast:true,position:'top-end',timer:2500,timerProgressBar:true,showConfirmButton:false});await load(editing?page:1);}
        catch(error){get('gep-class-type-error').textContent=errorText(error);get('gep-class-type-error').classList.remove('d-none');for(const [key,messages] of Object.entries(error.errors??{})){form.elements.namedItem(key)?.classList.add('is-invalid');const hint=form.querySelector(`[data-error="${key}"]`);if(hint)hint.textContent=messages[0];}}
        finally{get('gep-class-type-save').disabled=false;}
    });
    get('gep-class-type-rows').addEventListener('click',async event=>{
        const button=event.target.closest('[data-class-type-edit],[data-class-type-delete],[data-class-type-status]');if(!button)return;
        const id=button.dataset.classTypeEdit??button.dataset.classTypeDelete??button.dataset.classTypeStatus,row=rows.find(item=>String(item.id)===id);if(!row)return;
        if(button.hasAttribute('data-class-type-edit'))return open(row);
        const status=button.hasAttribute('data-class-type-status');
        if(!status&&!window.confirm(`Delete GEP class type ${row.type_name}?`))return;
        button.disabled=true;
        try{await request(`${base}/${id}${status?'/status':''}`,status?'PATCH':'DELETE',status?{status:!row.status}:undefined);await load(page);}
        catch(error){listError(errorText(error));button.disabled=false;}
    });
    get('gep-class-type-search').addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(()=>load(1),250);});load();
});
