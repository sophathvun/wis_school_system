import Swal from 'sweetalert2';
import { renderPagination } from './helpers/pagination.js';

document.addEventListener('DOMContentLoaded',()=>{
    const root=document.getElementById('gep-groups');if(!root)return;
    const get=id=>document.getElementById(id),base=root.dataset.url,permissions=JSON.parse(root.dataset.permissions);
    const form=get('gep-group-form'),modal=bootstrap.Modal.getOrCreateInstance(get('gep-group-modal'));
    const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    let rows=[],editing=null,page=1,timer,loading;
    const errorText=error=>Object.values(error.errors??{}).flat().join(' ')||error.message;
    const listError=message=>{get('gep-group-list-error').textContent=message;get('gep-group-list-error').classList.toggle('d-none',!message);};
    const request=async(url,method,data)=>{
        const response=await fetch(url,{method,headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:data?JSON.stringify(data):undefined});
        const result=await response.json().catch(()=>({}));if(!response.ok)throw Object.assign(new Error(result.message||'Unable to complete the request.'),{errors:result.errors});return result;
    };
    const load=async(target=1)=>{
        loading?.abort();loading=new AbortController();
        try{
            const response=await fetch(`${base}/data?${new URLSearchParams({page:target,perPage:get('gep-group-per-page').value,search:get('gep-group-search').value.trim()})}`,{headers:{Accept:'application/json'},signal:loading.signal});
            if(!response.ok)throw new Error('Unable to load GEP groups.');const result=await response.json();
            if(result.current_page>result.last_page)return load(result.last_page);
            rows=result.data;page=result.current_page;listError('');
            get('gep-group-rows').innerHTML=rows.length?rows.map((row,index)=>`<tr><td class="text-center"><span class="badge bg-primary-lt px-3 py-2">${result.from+index}</span></td><td class="fw-semibold">${esc(row.session_name)}</td><td><span class="badge bg-primary-lt">${esc(row.group_code)}</span></td><td>${esc(row.sort_order)}</td><td class="text-center"><button type="button" class="status-toggle ${row.status?'is-active':''}" data-group-status="${row.id}" aria-pressed="${row.status}" aria-label="${row.status?'Turn off':'Turn on'} ${esc(row.session_name)}" ${permissions.status?'':'disabled'}><span class="status-toggle-label">${row.status?'ON':'OFF'}</span><span class="status-toggle-knob"></span></button></td><td class="text-end"><div class="btn-list justify-content-end">${permissions.edit?`<button class="btn btn-outline-primary btn-sm" data-group-edit="${row.id}"><i class="ti ti-edit me-1"></i>Edit</button>`:''}${permissions.delete?`<button class="btn btn-outline-danger btn-sm" data-group-delete="${row.id}"><i class="ti ti-trash me-1"></i>Delete</button>`:''}${!permissions.edit&&!permissions.delete?'<span class="text-secondary">—</span>':''}</div></td></tr>`).join(''):'<tr><td colspan="6" class="text-center text-secondary py-5">No GEP groups found.</td></tr>';
            renderPagination(result,'gep-group-pagination','gep-group-per-page',load);
        }catch(error){if(error.name!=='AbortError')listError(error.message);}
    };
    const updateGroup=()=>{get('gep-group-code').value=({Morning:'M',Afternoon:'A',Evening:'E'})[get('gep-group-name').value]??'';};
    get('gep-group-name').addEventListener('change',updateGroup);
    const open=row=>{
        form.reset();get('gep-group-order').value=row?.sort_order??0;editing=row?.id??null;get('gep-group-error').classList.add('d-none');form.querySelectorAll('.is-invalid').forEach(el=>el.classList.remove('is-invalid'));
        get('gep-group-name').value=row?.session_name??'';updateGroup();
        get('gep-group-title').textContent=editing?'Edit GEP Group':'Add GEP Group';get('gep-group-save').textContent=editing?'Update':'Save';modal.show();
    };
    get('gep-group-add')?.addEventListener('click',()=>open());
    get('gep-group-modal').addEventListener('shown.bs.modal',()=>get('gep-group-name').focus());
    form.addEventListener('submit',async event=>{
        event.preventDefault();get('gep-group-save').disabled=true;form.querySelectorAll('.is-invalid').forEach(el=>el.classList.remove('is-invalid'));
        try{await request(editing?`${base}/${editing}`:base,editing?'PUT':'POST',{sort_order:get('gep-group-order').value,session_name:get('gep-group-name').value.trim()});modal.hide();Swal.fire({icon:'success',title:editing?'GEP group updated successfully.':'GEP group added successfully.',toast:true,position:'top-end',timer:2500,timerProgressBar:true,showConfirmButton:false});await load(editing?page:1);}
        catch(error){get('gep-group-error').textContent=errorText(error);get('gep-group-error').classList.remove('d-none');for(const [key,messages] of Object.entries(error.errors??{})){form.elements.namedItem(key)?.classList.add('is-invalid');const hint=form.querySelector(`[data-error="${key}"]`);if(hint)hint.textContent=messages[0];}}
        finally{get('gep-group-save').disabled=false;}
    });
    get('gep-group-rows').addEventListener('click',async event=>{
        const button=event.target.closest('[data-group-edit],[data-group-delete],[data-group-status]');if(!button)return;
        const id=button.dataset.groupEdit??button.dataset.groupDelete??button.dataset.groupStatus,row=rows.find(item=>String(item.id)===id);if(!row)return;
        if(button.hasAttribute('data-group-edit'))return open(row);
        const status=button.hasAttribute('data-group-status');
        if(!status&&!window.confirm(`Delete GEP group ${row.session_name}?`))return;
        button.disabled=true;
        try{await request(`${base}/${id}${status?'/status':''}`,status?'PATCH':'DELETE',status?{status:!row.status}:undefined);await load(page);}
        catch(error){listError(errorText(error));button.disabled=false;}
    });
    get('gep-group-search').addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(()=>load(1),250);});load();
});
