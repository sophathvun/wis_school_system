import {Modal} from 'bootstrap';
import Swal from 'sweetalert2';
import {renderPagination} from './helpers/pagination.js';
import {createSeatPicker} from './scholarshipSeatPicker.js';

const root=document.querySelector('[data-g12-exams]');
if(root){
    const get=s=>document.querySelector(s),base=root.dataset.base,tab=root.dataset.tab,permissions=JSON.parse(get('#g12Permissions').textContent);
    const escape=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const statusLabels={awaiting:'Awaiting certificate',available:'Available at school',collected:'Collected',not_applicable:'Not applicable',review:'Needs review',pending:'Pending',passed:'Passed',failed:'Failed'};
    const badge=value=>`<span class="badge ${value==='collected'||value==='passed'?'bg-green-lt':value==='failed'?'bg-red-lt':'bg-blue-lt'}">${escape(statusLabels[value]??value)}</span>`;
    const api=async(path,body)=>{
        const result=await fetch(base+path,{method:body?'POST':'GET',credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':get('meta[name=csrf-token]')?.content,...(body&&!(body instanceof FormData)?{'Content-Type':'application/json'}:{})},body:body?(body instanceof FormData?body:JSON.stringify(body)):undefined});
        const fallback=result.status===413?'This upload exceeds the server request limit. Try a smaller ZIP or ask your server administrator to increase the upload limit.':result.status===419||result.status===401?'Your session expired. Refresh the page and sign in again.':result.redirected?'The server redirected this request instead of returning the preview. Refresh and sign in again; if the upload is still rejected, try a smaller ZIP.':'The server did not return a valid response. Please retry; if this continues, ask your administrator to check the upload error.';
        if(result.redirected||!result.headers.get('Content-Type')?.toLowerCase().includes('json'))throw new Error(fallback);
        let data;try{data=await result.json();}catch{throw new Error(fallback);}
        if(!data||typeof data!=='object')throw new Error(fallback);
        if(!result.ok){const error=new Error(result.status===413?fallback:Object.values(data.errors??{}).flat().join('\n')||data.message||fallback);error.fields=data.errors;throw error;}return data;
    };
    let busy=false,meta,editing,centerEditing,detail,importBatch,importPreview,photoBatch,photoEntries=[],photoYear=null;
    const pickers=new Map();
    const run=async action=>{if(busy)return;busy=true;root.setAttribute('aria-busy','true');try{await action();}catch(error){await Swal.fire({icon:'error',title:'G12 National Exams',text:error.message});}finally{busy=false;root.setAttribute('aria-busy','false');}};
    const saved=message=>Swal.fire({icon:'success',title:'Saved',text:message});
    const modal=id=>Modal.getOrCreateInstance(get(id));
    const optionList=(rows,label,id='id')=>rows.map(r=>`<option value="${escape(r[id])}">${escape(typeof label==='function'?label(r):r[label])}</option>`).join('');
    const options=(select,rows,label,id='id')=>{if(!select)return;const value=select.value;select.innerHTML=`<option value="">${escape(select.dataset.placeholder??'Select')}</option>`+optionList(rows,label,id);select.value=value;pickers.get(select)?.refresh();};
    const field=(name,label,value='',attrs='')=>`<div><label class="form-label" for="edit-${escape(name)}">${escape(label)}</label><input class="form-control" id="edit-${escape(name)}" name="${escape(name)}" value="${escape(value)}" ${attrs}></div>`;
    function refreshPickers(scope){scope.querySelectorAll('.scholarship-field select').forEach(select=>{if(!pickers.has(select))pickers.set(select,createSeatPicker(select));else pickers.get(select).refresh();});}
    const loadMeta=async()=>{
        meta=await api('/meta?tab='+tab);
        for(const [name,rows,label,id]of [['academic_year',meta.years.map(v=>({id:v,label:v})),'label'],['campus_id',meta.campuses,'campus_name_en'],['center_id',meta.centers,'name'],['grade',meta.grades.map(v=>({id:v,label:v})),'label'],['result_status',['pending','passed','failed'].map(v=>({id:v,label:statusLabels[v]})),'label'],['certificate_type',[{id:'soft',label:'Soft Certificate'},{id:'hard',label:'Hard Certificate'}],'label'],['certificate_status',Object.entries(statusLabels).filter(([v])=>['awaiting','available','collected','not_applicable','review'].includes(v)).map(([id,label])=>({id,label})),'label']])options(get(`[data-g12-filters] [name="${name}"]`),rows,label,id);
        const photoYearSelect=get('#photoYear');
        if(photoYearSelect){
            options(photoYearSelect,meta.years.map(year=>({id:year,label:year})),'label');
            photoYearSelect.disabled=!meta.years.length;
            get('[data-g12-photo-year-empty]').hidden=!!meta.years.length;
            get('[data-g12-photo-preview-button]').disabled=!meta.years.length;
        }
        refreshPickers(root);
    };
    const filters=()=>new URLSearchParams(get('[data-g12-filters]')?new FormData(get('[data-g12-filters]')):[]);
    const load=async(page=1,perPage=get('#g12PerPage')?.value??15)=>{
        if(!get('[data-g12-records]'))return;const query=filters();query.set('tab',tab);query.set('page',page);query.set('per_page',perPage);
        const res=await api('/list?'+query);get('[data-g12-records]').innerHTML=res.data.map((r,i)=>`<tr><td>${res.from+i}</td><td>${r.photo_url?`<img src="${escape(r.photo_url)}" class="g12-photo" loading="lazy" alt="Student photo">`:'—'}</td><td><strong>${escape(r.name_en)}</strong><div class="school-profile-khmer">${escape(r.name_kh)}</div><div class="text-secondary">${escape(r.student_code)}</div></td><td>${escape(r.academic_year)}<div>${escape(r.campus)} / ${escape(r.grade)}</div></td><td>${escape(r.center??'Not assigned')}<div>Room ${escape(r.room||'—')} / Seat ${escape(r.seat||'—')}</div></td><td>${escape(r.overall_grade)} ${badge(r.result_status)}</td>${['soft','hard'].map(t=>`<td>${badge(r.certificates.find(c=>c.type===t)?.status??'awaiting')}</td>`).join('')}<td><div class="d-flex gap-2"><button class="btn btn-sm btn-outline-primary" data-g12-view="${r.id}"><i class="ti ti-eye me-1"></i>View</button>${r.can_edit&&tab==='records'?`<button class="btn btn-sm btn-outline-primary" data-g12-edit="${r.id}" aria-label="Edit student"><i class="ti ti-edit"></i></button>`:''}</div></td></tr>`).join('')||'<tr><td colspan="9" class="text-center py-4">No student exam records match these filters.</td></tr>';
        renderPagination(res,'g12Pagination','g12PerPage',(p,size)=>run(()=>load(p,size)),['all',15,25,50,100]);
    };
    get('[data-g12-filters]')?.addEventListener('submit',e=>{e.preventDefault();run(()=>load());});
    async function openEdit(id){
        editing=id?await api('/records/'+id):null;const values=editing?.data??{};if(editing){values.photo_filename=editing.photo_filename;values.student_id=editing.student_code;values.academic_year=editing.academic_year;}
        let html='';for(const [group,fields]of Object.entries(meta.fields)){
            html+=`<h4 class="mt-3">${escape(group)}</h4><div class="g12-fields mb-3">`;
            for(const [name,label]of Object.entries(fields)){
                if(name==='campus'||name==='g12_exam_center'){
                    const campus=name==='campus',key=campus?'campus_id':'center_id',rows=campus?meta.campuses:meta.centers.filter(c=>c.active||c.id===editing?.center_id);
                    html+=`<div class="scholarship-field"><label for="edit-${key}">${escape(label)}</label><select class="form-select" name="${key}" id="edit-${key}" data-placeholder="Select ${escape(label)}" ${campus?'required':''}><option value="">Select ${escape(label)}</option>${optionList(rows,campus?'campus_name_en':'name')}</select></div>`;
                }else html+=field(name,label,values[name]??'',(['student_id','academic_year','student_name_en'].includes(name)?'required maxlength="255" ':'')+(editing&&['student_id','academic_year'].includes(name)?'readonly':''));
            }html+='</div>';
        }
        html+='<h4 class="mt-3">Expected document filenames from MoEYS</h4><div class="g12-fields">';for(const [kind,name]of Object.entries(meta.document_fields))html+=field(name,kind.replaceAll('_',' '),values[name]??'');html+='</div>';
        get('[data-g12-edit-fields]').innerHTML=html;const form=get('[data-g12-edit-form]');form.elements.campus_id.value=editing?.campus_id??'';form.elements.center_id.value=editing?.center_id??'';refreshPickers(form);
        get('#g12EditTitle').textContent=editing?'Edit Student Exam Record':'Add Student Exam Record';modal('#g12EditModal').show();
    }
    get('[data-g12-new]')?.addEventListener('click',()=>run(()=>openEdit()));
    get('[data-g12-edit-form]').addEventListener('submit',e=>{e.preventDefault();run(async()=>{
        const values=Object.fromEntries(new FormData(e.target)),campus=Number(values.campus_id),center=values.center_id?Number(values.center_id):null;delete values.campus_id;delete values.center_id;
        values.campus=meta.campuses.find(c=>c.id===campus)?.campus_name_en??'';values.g12_exam_center=meta.centers.find(c=>c.id===center)?.name??'';
        try{const res=await api('/records'+(editing?'/'+editing.id:''),{data:values,campus_id:campus,center_id:center,version:editing?.version});modal('#g12EditModal').hide();await saved(res.message);await loadMeta();await load();}catch(error){for(const key of Object.keys(error.fields??{})){const input=e.target.elements[key.replace(/^data\./,'')];input?.classList.add('is-invalid');}throw error;}
    });});
    function centers(){const search=get('[data-g12-center-search]')?.value.toLowerCase()??'';const list=meta.centers.filter(c=>c.name.toLowerCase().includes(search));get('[data-g12-centers]').innerHTML=list.map(c=>`<tr><td>${escape(c.source_id??'')}</td><td>${escape(c.name)}</td><td>${c.active?'Active':'Inactive'}</td><td>${permissions['centers-manage']?`<button class="btn btn-sm btn-outline-primary" data-g12-center-edit="${c.id}"><i class="ti ti-edit me-1"></i>Edit</button>`:''}</td></tr>`).join('')||'<tr><td colspan="4">No exam centers. Import Sheet 2 or add a center.</td></tr>';}
    const centerForm=get('[data-g12-center-form]');
    const syncCenterLabels=()=>centerForm.querySelectorAll('.premium-form-field').forEach(wrapper=>wrapper.classList.toggle('has-value',Boolean(wrapper.querySelector('input,select')?.value.trim())));
    for(const name of ['input','change'])centerForm.addEventListener(name,syncCenterLabels);
    const openCenter=id=>{
        centerEditing=meta.centers.find(c=>c.id===Number(id));centerForm.reset();
        centerForm.elements.name.value=centerEditing?.name??'';
        get('#g12CenterCode').value=centerEditing?.source_id||'Assigned automatically';
        centerForm.elements.active.value=centerEditing?.active===false?'0':'1';
        get('#g12CenterTitle').textContent=centerEditing?'Edit Exam Center':'Add Exam Center';
        get('#g12CenterCodeHint').textContent=centerEditing?.source_id?'The center ID is kept when you edit this record.':'The system assigns an ID when the center is saved.';
        syncCenterLabels();modal('#g12CenterModal').show();
    };
    get('[data-g12-center-new]')?.addEventListener('click',()=>openCenter());get('[data-g12-center-search]')?.addEventListener('input',centers);
    get('[data-g12-center-form]').addEventListener('submit',e=>{e.preventDefault();run(async()=>{const data=Object.fromEntries(new FormData(e.target));data.active=data.active==='1';const res=await api('/centers'+(centerEditing?'/'+centerEditing.id:''),data);modal('#g12CenterModal').hide();await saved(res.message);await loadMeta();centers();});});
    const docNames={soft_certificate:'Soft certificate',soft_certificate_received:'Signed soft-certificate receipt',hard_certificate:'Hard certificate',hard_certificate_received:'Signed hard-certificate receipt',other_documents:'Other documents',portrait:'Student portrait'};
    async function openView(id){
        detail=await api('/records/'+id);let html=`<div class="d-flex gap-3 align-items-center mb-3">${detail.photo_url?`<img class="g12-detail-photo" src="${escape(detail.photo_url)}" alt="Student photo">`:''}<div><h2>${escape(detail.name_en)}</h2><div>${escape(detail.name_kh)}</div><strong>${escape(detail.student_code)}</strong> · ${escape(detail.academic_year)} · ${escape(detail.campus)}</div></div>`;
        html+='<div class="g12-certificate-grid mb-4">';for(const type of ['soft','hard']){
            const cert=detail.certificates.find(c=>c.type===type),editable=detail.permissions.collection;html+=`<section class="g12-certificate"><h3>${type==='soft'?'Soft':'Hard'} Certificate ${badge(cert.status)}</h3>`;
            if(cert.imported_status)html+=`<p class="small text-secondary">Imported status: ${escape(cert.imported_status)}</p>`;
            if(cert.status==='collected'&&!detail.files.some(f=>f.kind===type+'_certificate_received'))html+='<p class="small text-warning">The collection was imported; no signed receipt has been uploaded yet.</p>';
            if(editable){
                html+=`<form data-g12-collection="${type}"><input type="hidden" name="version" value="${cert.version}"><div class="g12-fields"><div><label class="form-label">Status</label><select name="status" class="form-select">${['awaiting','available','collected','not_applicable','review'].map(s=>`<option value="${s}" ${s===cert.status?'selected':''}>${statusLabels[s]}</option>`).join('')}</select></div>${field('number','Certificate Number',cert.number??'')}${field('available_date','Date Received at School',cert.available_date??'','type="date"')}${field('collected_date','Date Collected',cert.collected_date??'','type="date"')}${field('recipient','Collected By',cert.recipient??'')}</div><label class="form-label mt-3">Signed certificate-copy photo / PDF</label><input type="file" class="form-control" name="file" accept=".jpg,.jpeg,.png,.webp,.pdf" ${detail.permissions['documents-upload']?'':'disabled'}><label class="form-label mt-3">Reason for correcting an existing collection</label><textarea class="form-control" name="reason" maxlength="1000" rows="2"></textarea><button class="btn btn-primary mt-3">Save Certificate Tracking</button></form>`;
            }else html+=`<dl><dt>Certificate Number</dt><dd>${escape(cert.number??'—')}</dd><dt>Collected Date / Recipient</dt><dd>${escape(cert.collected_date??'—')} / ${escape(cert.recipient??'—')}</dd></dl>`;
            html+='</section>';
        }html+='</div>';
        html+='<h3>Documents and versions</h3>';
        if(detail.permissions['documents-upload'])html+=`<form data-g12-document-form class="g12-fields mb-3"><div><label class="form-label">Document Type</label><select class="form-select" name="kind">${Object.entries(docNames).filter(([kind])=>kind!=='portrait').map(([kind,label])=>`<option value="${kind}">${label}</option>`).join('')}</select></div><div><label class="form-label">File (up to 20 MB)</label><input type="file" name="file" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf" required></div><div class="d-flex align-items-end"><button class="btn btn-outline-primary">Upload Document</button></div></form>`;
        html+=detail.files.length?'<div class="table-responsive"><table class="table"><thead><tr><th>Preview</th><th>Document / Original Filename</th><th>Uploaded</th><th>Actions</th></tr></thead><tbody>'+detail.files.map(f=>`<tr><td>${f.mime.startsWith('image/')?`<img class="g12-document-image" loading="lazy" src="${escape(f.url)}" alt="Document preview">`:'PDF'}</td><td>${escape(docNames[f.kind]??f.kind)}<div class="small">${escape(f.original_name)}</div></td><td>${escape(new Date(f.created_at).toLocaleString())}</td><td><a class="btn btn-sm btn-outline-primary" href="${escape(f.url)}" target="_blank" rel="noopener">View</a> <a class="btn btn-sm btn-outline-secondary" href="${escape(f.download)}">Download</a></td></tr>`).join('')+'</tbody></table></div>':'<p class="text-secondary">No documents available to view.</p>';
        for(const [group,fields]of Object.entries(meta.fields)){html+=`<details class="border rounded p-3 my-3"><summary class="fw-semibold">${escape(group)}</summary><dl class="g12-detail-grid mt-3">`;for(const [key,label]of Object.entries(fields))html+=`<div><dt>${escape(label)}</dt><dd>${escape(detail.data[key]??'—')}</dd></div>`;html+='</dl></details>';}
        html+='<details class="border rounded p-3"><summary>Activity history</summary><ul>'+detail.history.map(h=>`<li>${escape(h.action)} · Staff #${h.user_id} · ${escape(h.created_at)}</li>`).join('')+'</ul></details>';
        get('[data-g12-detail]').innerHTML=html;modal('#g12ViewModal').show();
    }
    root.addEventListener('click',e=>{const view=e.target.closest('[data-g12-view]'),edit=e.target.closest('[data-g12-edit]'),center=e.target.closest('[data-g12-center-edit]'),report=e.target.closest('[data-g12-export]');if(view)run(()=>openView(view.dataset.g12View));if(edit)run(()=>openEdit(edit.dataset.g12Edit));if(center)openCenter(center.dataset.g12CenterEdit);if(report){const query=filters();query.set('format',report.dataset.g12Export);window.open(base+'/report?'+query,'_blank','noopener');}});
    root.addEventListener('submit',e=>{
        if(e.target.matches('[data-g12-document-form]')){e.preventDefault();run(async()=>{const res=await api(`/records/${detail.id}/files`,new FormData(e.target));await saved(res.message);await openView(detail.id);});}
        if(e.target.matches('[data-g12-collection]')){e.preventDefault();run(async()=>{const res=await api(`/records/${detail.id}/certificates/${e.target.dataset.g12Collection}`,new FormData(e.target));await saved(res.message);await openView(detail.id);await load();});}
    });
    root.querySelectorAll('[data-g12-drop]').forEach(zone=>{
        for(const type of ['dragenter','dragover'])zone.addEventListener(type,e=>{e.preventDefault();zone.classList.add('dragover');});
        for(const type of ['dragleave','drop'])zone.addEventListener(type,e=>{e.preventDefault();zone.classList.remove('dragover');});
        zone.addEventListener('drop',e=>{const input=zone.querySelector('input');if(input.multiple)input.files=e.dataTransfer.files;else{const d=new DataTransfer();if(e.dataTransfer.files[0])d.items.add(e.dataTransfer.files[0]);input.files=d.files;}input.dispatchEvent(new Event('change',{bubbles:true}));});
    });
    const excelForm=get('[data-g12-excel-form]'),excelZone=get('[data-g12-excel-drop]');
    if(excelForm&&excelZone){
        const input=excelForm.querySelector('[name=file]'),list=get('[data-g12-excel-files]'),errorBox=get('[data-g12-excel-error]'),previewButton=get('[data-g12-excel-preview]');
        let selectedFile=null;
        const clearError=()=>{errorBox.textContent='';errorBox.hidden=true;excelZone.classList.remove('is-invalid');excelZone.removeAttribute('aria-invalid');};
        const showError=message=>{errorBox.textContent=message;errorBox.hidden=false;excelZone.classList.add('is-invalid');excelZone.setAttribute('aria-invalid','true');};
        const clearPreview=()=>{importBatch=null;importPreview=null;get('[data-g12-import-preview]').hidden=true;};
        const render=()=>{
            previewButton.disabled=!selectedFile;
            if(!selectedFile){list.innerHTML='';return;}
            const size=selectedFile.size<1024*1024?`${Math.max(1,Math.round(selectedFile.size/1024))} KB`:`${(selectedFile.size/(1024*1024)).toFixed(2)} MB`;
            list.innerHTML=`<div class="g12-excel-file"><span class="document-file-icon"><i class="ti ti-file-spreadsheet" aria-hidden="true"></i></span><div class="min-w-0"><div class="document-file-name">${escape(selectedFile.name)}</div><div class="document-file-meta">${size} · Ready to upload</div></div><button type="button" class="document-file-remove" data-g12-excel-remove aria-label="Remove ${escape(selectedFile.name)}"><i class="ti ti-x" aria-hidden="true"></i></button></div>`;
        };
        const chooseFiles=files=>{
            if(busy)return;
            const candidates=Array.from(files||[]);if(!candidates.length)return;
            clearError();clearPreview();selectedFile=null;input.value='';
            const file=candidates[0];
            if(candidates.length!==1)showError('Choose one Excel workbook at a time.');
            else if(!/\.xlsx$/i.test(file.name))showError('Choose an Excel workbook in .xlsx format.');
            else if(!file.size)showError('This Excel file is empty. Choose a workbook containing your data.');
            else if(file.size>20*1024*1024)showError('The Excel file exceeds 20 MB. Choose a smaller workbook.');
            else{
                const transfer=new DataTransfer();transfer.items.add(file);input.files=transfer.files;selectedFile=file;
            }
            render();
        };
        excelZone.addEventListener('click',event=>{if(event.target!==input&&!busy){excelZone.focus({preventScroll:true});input.click();}});
        excelZone.addEventListener('keydown',event=>{if(event.key==='Enter'||event.key===' '){event.preventDefault();if(!busy)input.click();}});
        for(const name of ['dragenter','dragover'])excelZone.addEventListener(name,event=>{event.preventDefault();if(!busy){excelZone.classList.add('is-dragging');if(event.dataTransfer)event.dataTransfer.dropEffect='copy';}});
        excelZone.addEventListener('dragleave',event=>{if(!excelZone.contains(event.relatedTarget))excelZone.classList.remove('is-dragging');});
        excelZone.addEventListener('drop',event=>{event.preventDefault();excelZone.classList.remove('is-dragging');chooseFiles(event.dataTransfer?.files);});
        document.addEventListener('paste',event=>{
            if(event.defaultPrevented||busy||!excelZone.getClientRects().length||document.querySelector('.modal.show,.swal2-container')||event.target?.closest?.('input,textarea,select,[contenteditable]:not([contenteditable="false"])'))return;
            const direct=Array.from(event.clipboardData?.files||[]);
            const files=direct.length?direct:Array.from(event.clipboardData?.items||[]).filter(item=>item.kind==='file').map(item=>item.getAsFile()).filter(Boolean);
            if(!files.length)return;event.preventDefault();chooseFiles(files);
        });
        input.addEventListener('change',()=>chooseFiles(input.files));
        input.addEventListener('invalid',event=>{event.preventDefault();showError('Choose, drop or paste an Excel workbook before previewing.');excelZone.focus();});
        list.addEventListener('click',event=>{if(busy||!event.target.closest('[data-g12-excel-remove]'))return;selectedFile=null;input.value='';clearError();clearPreview();render();excelZone.focus();});
        excelForm.addEventListener('reset',()=>{selectedFile=null;clearError();clearPreview();render();});
    }
    const maps=()=>{const result={campuses:{},centers:{}};get('[data-g12-mappings]')?.querySelectorAll('select').forEach(s=>{if(s.value)result[s.dataset.mapType][s.dataset.source]=s.value;});return result;};
    function importTable(preview,keepMappings=false){
        importPreview=preview;get('[data-g12-import-preview]').hidden=false;get('[data-g12-import-summary]').textContent=`${preview.total} records: ${preview.new} new, ${preview.updates} updates, ${preview.errors} with issues`;
        get('[data-g12-import-rows]').innerHTML=preview.rows.map(r=>`<tr><td>${r.row}</td><td>${escape(r.name)}<div>${escape(r.student_id)}</div></td><td>${escape(r.year)}</td><td>${escape(r.campus)}</td><td>${escape(r.center)}</td><td>${r.action}</td><td class="text-danger">${escape(r.issues.join(', '))}</td></tr>`).join('');
        if(!keepMappings){get('[data-g12-mappings]').innerHTML=preview.missing_campuses.map(name=>`<div><label class="form-label">Map campus: ${escape(name||'(blank)')}</label><select class="form-select" data-map-type="campuses" data-source="${escape(name)}"><option value="">Select campus</option>${optionList(meta.campuses,'campus_name_en')}</select></div>`).join('')+preview.missing_centers.map(name=>`<div><label class="form-label">Map center: ${escape(name)}</label><select class="form-select" data-map-type="centers" data-source="${escape(name)}"><option value="">Select center</option><option value="none">No center — preserve source text for review</option>${permissions['centers-manage']?'<option value="new">Create this exam center</option>':''}${optionList(meta.centers,'name')}${preview.settings_centers.map(c=>`<option value="@${escape(c.exam_center)}">${escape(c.exam_center)} (Sheet 2)</option>`).join('')}</select></div>`).join('');}
        get('[data-g12-confirm]').disabled=preview.errors>0;
    }
    get('[data-g12-excel-form]')?.addEventListener('submit',e=>{e.preventDefault();run(async()=>{const result=await api('/imports/preview',new FormData(e.target));importBatch=result.id;importTable(result.preview);});});
    get('[data-g12-reinspect]')?.addEventListener('click',()=>run(async()=>{const res=await api(`/imports/${importBatch}/inspect`,{maps:maps()});importTable(res.preview,true);}));
    get('[data-g12-confirm]')?.addEventListener('click',()=>run(async()=>{
        const choice=await Swal.fire({icon:'question',title:'Import MoEYS records?',text:`Import ${importPreview.new} new records and update ${importPreview.updates} existing records. Documents and staff-recorded collections are retained.`,showCancelButton:true,confirmButtonText:'Confirm Import'});if(!choice.isConfirmed)return;
        const versions=Object.fromEntries(importPreview.rows.map(r=>[r.row,r.version]));const res=await api(`/imports/${importBatch}/confirm`,{confirmed:true,maps:maps(),versions});await saved(`${res.message} ${res.summary.new} new, ${res.summary.updates} updated.`);get('[data-g12-import-preview]').hidden=true;await loadMeta();await history();
    }));
    const history=async()=>{if(!get('[data-g12-history]'))return;const rows=await api('/imports');get('[data-g12-history]').innerHTML=rows.map(b=>`<tr><td>${escape(new Date(b.created_at).toLocaleString())}</td><td>${escape(b.filename)}</td><td>${escape(b.kind)}</td><td>${escape(b.status)}</td><td>${escape(b.summary?JSON.stringify(b.summary):'Preview only')}</td></tr>`).join('')||'<tr><td colspan="5">No imports yet.</td></tr>';};
    const studentLabel=r=>`${r.student_code} — ${r.name_en}`;
    const photoPanel=get('[data-g12-photo-preview]'),photoRows=get('[data-g12-photo-rows]'),photoConfirm=get('[data-g12-photo-confirm]');
    let photoPreviewVersion=0;
    const pendingPhotoRows=()=>[...(photoRows?.querySelectorAll('tr[data-photo-index]')??[])].filter(row=>!row.querySelector('[data-photo-selected]').disabled);
    const selectedPhotoRows=()=>pendingPhotoRows().filter(row=>row.querySelector('[data-photo-selected]').checked);
    const updatePhotoSelection=()=>{
        if(!photoConfirm)return;
        const pending=pendingPhotoRows(),count=selectedPhotoRows().length,all=get('[data-g12-photo-select-all]');
        photoConfirm.disabled=!photoBatch||!count||photoYear!==get('#photoYear').value;
        photoConfirm.textContent=count?`Upload Selected Photos (${count})`:'Upload Selected Photos';
        all.disabled=!pending.length;all.checked=!!pending.length&&count===pending.length;all.indeterminate=count>0&&count<pending.length;
    };
    const resetPhotoPreview=()=>{
        if(!photoPanel)return;
        photoPreviewVersion++;
        photoBatch=null;photoEntries=[];photoYear=null;photoPanel.hidden=true;photoPanel.classList.add('d-none');photoRows.innerHTML='';
        get('[data-g12-photo-bar]').value=0;get('[data-g12-photo-progress]').textContent='';
        get('[data-g12-photo-help]').textContent='Click Preview Photo Matches to load these photos before selecting students and uploading.';
        updatePhotoSelection();
    };
    get('[data-g12-photos-form] input[type=file]')?.addEventListener('change',resetPhotoPreview);
    get('#photoYear')?.addEventListener('input',resetPhotoPreview);
    get('#photoYear')?.addEventListener('change',resetPhotoPreview);
    get('#photoYear')?.addEventListener('invalid',event=>{
        event.preventDefault();get('#g12PhotoYearError').hidden=false;
        const button=pickers.get(event.target)?.button;button?.classList.add('is-invalid');button?.setAttribute('aria-invalid','true');button?.focus();
    });
    get('#photoYear')?.addEventListener('change',event=>{
        get('#g12PhotoYearError').hidden=true;
        const button=pickers.get(event.target)?.button;button?.classList.remove('is-invalid');button?.removeAttribute('aria-invalid');
    });
    photoRows?.addEventListener('change',updatePhotoSelection);
    get('[data-g12-photo-select-all]')?.addEventListener('change',event=>{for(const row of pendingPhotoRows())row.querySelector('[data-photo-selected]').checked=event.target.checked;updatePhotoSelection();});
    get('[data-g12-photos-form]')?.addEventListener('submit',e=>{e.preventDefault();run(async()=>{
        resetPhotoPreview();
        if(!meta.years.includes(get('#photoYear').value))throw new Error('Select an academic year from your student exam records.');
        const files=Array.from(e.target.querySelector('input[type=file]').files);
        if(!files.length)throw new Error('Choose photos or a ZIP before previewing.');
        if(files.length>100)throw new Error('Choose up to 100 files per preview, or put the photos in ZIP files no larger than 200 MB each.');
        for(const file of files){
            const zip=/\.zip$/i.test(file.name),limit=(zip?200:20)*1024*1024;
            if(!/\.(zip|jpe?g|png|webp)$/i.test(file.name))throw new Error(`${file.name}: choose JPG, PNG or WebP photos, or a ZIP containing those photos.`);
            if(file.size>limit)throw new Error(zip?`${file.name} is ${(file.size/(1024*1024)).toFixed(1)} MB. The limit is 200 MB per ZIP. Split the archive into smaller ZIPs and import them one at a time. Each ZIP must also be no larger than 500 MB after extraction.`:`${file.name} exceeds the 20 MB limit per photo. Choose a smaller image.`);
        }
        const revision=photoPreviewVersion,res=await api('/photos/preview',new FormData(e.target));
        if(revision!==photoPreviewVersion)return;
        if(!res.id||!Array.isArray(res.entries)||!res.entries.length)throw new Error('No photos were returned for preview. Choose JPG, PNG or WebP photos, or a ZIP containing those photos, and preview again.');
        const students=meta.students.filter(r=>r.academic_year===res.year);let datalist=get('#g12PhotoStudents');if(!datalist){datalist=document.createElement('datalist');datalist.id='g12PhotoStudents';root.append(datalist);}datalist.innerHTML=students.map(r=>`<option value="${escape(studentLabel(r))}"></option>`).join('');
        get('[data-g12-photo-rows]').innerHTML=res.entries.map(row=>{const student=students.find(r=>r.id===row.record_id);return `<tr data-photo-index="${row.index}"><td><input type="checkbox" class="form-check-input" data-photo-selected ${row.status==='matched'?'checked':''} aria-label="Select ${escape(row.name)}"></td><td>${escape(row.name)}</td><td>${badge(row.status)}</td><td><input class="form-control" data-photo-student list="g12PhotoStudents" value="${escape(student?studentLabel(student):'')}" placeholder="Search Student ID / name"></td><td><input type="checkbox" class="form-check-input" data-photo-replace ${permissions['photos-replace']?'':'disabled'} aria-label="Replace existing photo"></td><td data-photo-status></td></tr>`;}).join('');
        photoBatch=res.id;photoEntries=res.entries;photoYear=res.year;
        photoPanel.hidden=false;photoPanel.classList.remove('d-none');updatePhotoSelection();
        const matched=res.entries.filter(r=>r.status==='matched').length;
        get('[data-g12-photo-progress]').textContent=`${res.entries.length} photos found · ${matched} matched and selected · ${res.entries.length-matched} need review.`;
        get('[data-g12-photo-help]').textContent=students.length?'Matched photos are selected automatically. For unmatched photos, choose a student and tick Select. Existing photos also require Replace permission and the Replace checkbox.':'No accessible student exam records were found for this academic year. Import the students or choose the correct year, then preview the photos again.';
    });});
    get('[data-g12-photo-confirm]')?.addEventListener('click',()=>run(async()=>{
        if(!photoBatch||!photoEntries.length||photoYear!==get('#photoYear').value)throw new Error('Click Preview Photo Matches before uploading.');
        const selected=selectedPhotoRows();if(!selected.length){updatePhotoSelection();return;}
        const confirmation=await Swal.fire({icon:'question',title:'Upload selected photos?',text:`Upload ${selected.length} photos to the selected students. Any checked replacements retain previous versions.`,showCancelButton:true,confirmButtonText:'Upload Photos'});if(!confirmation.isConfirmed)return;
        const students=meta.students.filter(r=>r.academic_year===photoYear);let uploaded=0,failed=0;const bar=get('[data-g12-photo-bar]');bar.max=selected.length;bar.value=0;
        for(const row of selected){const status=row.querySelector('[data-photo-status]');status.textContent='Uploading…';
            try{const name=row.querySelector('[data-photo-student]').value,student=students.find(s=>studentLabel(s)===name);if(!student)throw new Error('Select an exact Student ID / name from the list.');
                await api(`/photos/${photoBatch}/${row.dataset.photoIndex}`,{record_id:student.id,replace:row.querySelector('[data-photo-replace]').checked});status.textContent='Uploaded';status.className='text-success';row.querySelector('[data-photo-selected]').disabled=true;uploaded++;
            }catch(error){status.textContent=error.message;status.className='text-danger';failed++;}
            bar.value++;get('[data-g12-photo-progress]').textContent=`${bar.value} / ${selected.length} processed · ${uploaded} uploaded · ${failed} need attention`;
        }
        updatePhotoSelection();
        await Swal.fire({icon:failed?'warning':'success',title:'Photo Batch Complete',text:`${uploaded} uploaded. ${failed} need attention; correct those rows and retry.`});
    }));
    run(async()=>{await loadMeta();if(get('[data-g12-records]'))await load();if(tab==='centers')centers();if(tab==='imports')await history();});
}
