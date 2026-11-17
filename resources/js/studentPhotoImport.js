import {showConfirm, showSuccess} from './helpers/sweet-alert2.js';

const page=document.querySelector('[data-photo-import]');
if (page) {
    const get=name=>page.querySelector(`[data-photo-${name}]`);
    const base=page.dataset.base.replace(/\/$/,'');
    const token=document.querySelector('meta[name="csrf-token"]')?.content;
    const escape=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    let files=[], data=null, busy=false;
    const selected=new Set();
    const headers={'Accept':'application/json','X-CSRF-TOKEN':token};
    const error=problem=>{get('error').textContent=problem.message||String(problem);get('error').hidden=false;};
    const request=async(url,payload)=>{
        const response=await fetch(url,{method:payload===undefined?'GET':'POST',headers:{...headers,'Content-Type':'application/json'},...(payload===undefined?{}:{body:JSON.stringify(payload)})});
        const result=await response.json().catch(()=>({message:`Request failed (${response.status}). Please retry.`}));
        if (!response.ok) throw Error(Object.values(result.errors||{}).flat()[0]||result.message);
        return result;
    };
    const lock=value=>{
        busy=value; get('preview').disabled=busy||!files.length; get('files').disabled=busy;
        get('replace').disabled=busy; get('drop').disabled=busy; get('confirm').disabled=busy;
        page.querySelectorAll('[data-open-photo-batch]').forEach(button=>button.disabled=busy);
    };
    const progress=(label,percent,note='')=>{
        get('progress').hidden=false; get('progress-label').textContent=label;
        get('percent').textContent=`${percent}%`; get('bar').style.width=`${percent}%`;
        get('bar').parentElement.setAttribute('aria-valuenow',percent); get('progress-note').textContent=note;
    };
    const choose=list=>{
        if (busy) return;
        files=Array.from(list);
        if (files.length>500 || files.some(f=>f.size>(/\.zip$/i.test(f.name)?50:10)*1024*1024)) {
            files=[];error(Error('Choose up to 500 photos, 10 MB per photo or 50 MB per ZIP archive.'));
        } else get('error').hidden=true;
        get('selection').textContent=files.length?`${files.length} file(s) selected · ${files.slice(0,4).map(f=>f.name).join(', ')}${files.length>4?' …':''}`:'No files selected.';
        get('preview').disabled=!files.length;
    };
    get('drop').hidden=false; get('files').hidden=true;
    get('drop').addEventListener('click',()=>get('files').click());
    get('files').addEventListener('change',event=>choose(event.target.files));
    ['dragenter','dragover'].forEach(type=>get('drop').addEventListener(type,event=>{event.preventDefault();if(!busy)get('drop').classList.add('is-dragging');}));
    get('drop').addEventListener('dragleave',()=>get('drop').classList.remove('is-dragging'));
    get('drop').addEventListener('drop',event=>{event.preventDefault();get('drop').classList.remove('is-dragging');choose(event.dataTransfer.files);});
    const render=()=>{
        if(!data)return;
        get('review').hidden=false; get('mode').textContent=data.batch.replace_existing?'Replace existing photos':'Fill missing photos only';
        const stats=[['Files',data.total,'primary'],['Ready',data.summary.ready||0,'primary'],['Updated',data.summary.updated||0,'success'],['Needs review',data.summary.review||0,'warning']];
        get('stats').innerHTML=stats.map(([label,value,color])=>`<div class="photo-import-stat text-${color}"><span class="fw-semibold">${label}</span><strong>${value}</strong></div>`).join('');
        const status=data.batch.status, editable=status==='preview'&&!data.batch.expired;
        get('confirm').textContent=status==='processing'?'Resume import':'Import selected photos';
        get('confirm').hidden=status==='completed'||data.batch.expired;
        get('confirm').disabled=busy||(editable&&!selected.size);
        get('csv').href=`${base}/${data.batch.id}/review`;
        get('state').textContent=data.batch.expired?'This preview expired. Upload a new batch; saved student photos are unchanged.':status==='completed'?`Import complete. ${data.summary.updated||0} updated · ${data.summary.skipped||0} skipped · ${data.summary.review||0} need review.`:status==='processing'?'Import confirmed. You can resume it if the connection is interrupted.':'Compare the current and uploaded photos. Uncheck any ready photo you do not want to import.';
        const labels={ready:'Ready',review:'Needs review',skipped:'Skipped',updated:'Updated'};
        const colors={ready:'primary',review:'warning',skipped:'secondary',updated:'success'};
        get('rows').innerHTML=data.rows.filter(row=>!get('filter').value||row.status===get('filter').value).map(row=>`<tr><td>${row.status==='ready'?`<input class="form-check-input" type="checkbox" data-photo-select="${row.id}" aria-label="Select ${escape(row.filename)}" ${selected.has(row.id)?'checked':''} ${editable?'':'disabled'}>`:''}</td><td>${row.current_url&&!data.batch.expired?`<img src="${escape(row.current_url)}" alt="Current student photo" loading="lazy">`:'<span class="text-secondary">—</span>'}</td><td>${row.source_url&&!data.batch.expired?`<img src="${escape(row.source_url)}" alt="Uploaded photo" loading="lazy">`:'<span class="text-secondary">—</span>'}</td><td class="photo-import-file-cell"><strong>${escape(row.student_code||'No Student ID')}</strong><div class="text-secondary small">${escape(row.filename)}</div></td><td>${escape(row.student_name||'—')}</td><td class="photo-import-file-cell"><span class="badge bg-${colors[row.status]}-lt">${labels[row.status]}</span><div class="text-secondary small mt-1">${escape(row.reason)}</div></td></tr>`).join('')||'<tr><td colspan="6" class="text-center text-secondary py-4">No files in this category.</td></tr>';
        updateSelectionUI();
    };
    const updateSelectionUI=()=>{
        if(!data)return;
        const ready=data.rows.filter(row=>row.status==='ready');
        const editable=data.batch.status==='preview'&&!data.batch.expired;
        get('confirm').disabled=busy||(editable&&!selected.size);
        get('all').disabled=!editable||busy||!ready.length;
        get('all').checked=!!ready.length&&ready.every(row=>selected.has(row.id));
        get('all').indeterminate=selected.size>0&&!get('all').checked;
        page.querySelectorAll('[data-photo-select]').forEach(input=>{input.checked=selected.has(Number(input.dataset.photoSelect));input.disabled=!editable||busy;});
    };
    const open=async(id,initial=true)=>{
        data=await request(`${base}/${id}`);
        if(initial){selected.clear();data.rows.filter(row=>row.status==='ready').forEach(row=>selected.add(row.id));}
        const url=new URL(location.href);url.searchParams.set('batch',id);history.replaceState({},'',url);
        render();
    };
    const upload=(id,file,onProgress)=>new Promise((resolve,reject)=>{
        const xhr=new XMLHttpRequest();xhr.open('POST',`${base}/${id}/upload`);
        Object.entries(headers).forEach(([key,value])=>xhr.setRequestHeader(key,value));
        xhr.upload.onprogress=event=>{if(event.lengthComputable)onProgress(event.loaded/event.total);};
        xhr.onerror=()=>reject(Error('Upload interrupted. Review the saved batch before uploading again.'));
        xhr.onload=()=>{
            let result;try{result=JSON.parse(xhr.responseText);}catch{result={message:`Upload failed (${xhr.status}). Check the server upload size limit.`};}
            if(xhr.status>=200&&xhr.status<300)resolve(result);else reject(Error(Object.values(result.errors||{}).flat()[0]||result.message));
        };
        const body=new FormData();body.append('file',file);xhr.send(body);
    });
    get('preview').addEventListener('click',async()=>{
        if(busy||!files.length)return;lock(true);get('error').hidden=true;
        let id=null;
        try{
            ({id}=await request(base,{replace_existing:get('replace').checked}));
            const total=files.reduce((sum,file)=>sum+file.size,0);let done=0;
            for(let i=0;i<files.length;i++){
                progress('Uploading photos',Math.round(done/total*100),`File ${i+1} of ${files.length}: ${files[i].name}`);
                await upload(id,files[i],fraction=>progress('Uploading photos',Math.min(99,Math.round((done+files[i].size*fraction)/total*100)),`File ${i+1} of ${files.length}; matching and validating photos…`));
                done+=files[i].size;
            }
            progress('Preview ready',100,'Student photos have not been changed. Review the matches below.');
            await open(id);
        }catch(problem){error(problem);if(id)await open(id).catch(()=>{});}
        finally{lock(false);render();}
    });
    get('rows').addEventListener('change',event=>{const id=Number(event.target.dataset.photoSelect);if(!id)return;if(event.target.checked)selected.add(id);else selected.delete(id);updateSelectionUI();});
    get('all').addEventListener('change',event=>{data.rows.filter(row=>row.status==='ready').forEach(row=>event.target.checked?selected.add(row.id):selected.delete(row.id));updateSelectionUI();});
    get('filter').addEventListener('change',render);
    get('confirm').addEventListener('click',async()=>{
        if(busy||!data)return;
        if(data.batch.status==='preview'){
            const answer=await showConfirm('Import selected student photos?',`${selected.size} photos selected. ${data.batch.replace_existing?'Existing student photos may be replaced.':'Existing photos will be kept.'}`,'Import photos');
            if(!answer.isConfirmed)return;
        }
        lock(true);get('error').hidden=true;
        try{
            const total=data.batch.status==='preview'?selected.size:(data.summary.ready||1);
            if(data.batch.status==='preview')await request(`${base}/${data.batch.id}/confirm`,{selected_ids:[...selected]});
            let remaining=total;
            do{
                progress('Converting & saving WebP photos',Math.round((total-remaining)/total*100),`${total-remaining} of ${total} selected photos processed. Keep this page open or resume later.`);
                const result=await request(`${base}/${data.batch.id}/process`,{});
                remaining=Number(result.summary.ready||0);
                if(result.status==='completed')break;
            }while(remaining>0);
            progress('Import complete',100,'Photos saved. Download the review CSV for any skipped or unmatched files.');
            await open(data.batch.id);showSuccess('Student photo import completed.');
        }catch(problem){error(problem);await open(data.batch.id).catch(()=>{});}
        finally{lock(false);render();}
    });
    page.querySelectorAll('[data-open-photo-batch]').forEach(button=>button.addEventListener('click',async()=>{
        if(busy)return;lock(true);get('error').hidden=true;
        try{await open(button.dataset.openPhotoBatch);}catch(problem){error(problem);}finally{lock(false);render();}
    }));
    const initial=new URL(location.href).searchParams.get('batch');
    if(initial){lock(true);open(initial).catch(error).finally(()=>{lock(false);render();});}
}
