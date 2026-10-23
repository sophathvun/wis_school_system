import { findSheetCorners, alignSheet } from './helpers/answerSheetAlignment.js';

const LIMIT=25;
const TYPES=['image/jpeg','image/png','image/webp'];
const sizeLabel=bytes=>bytes<1024*1024?`${Math.max(1,Math.round(bytes/1024))} KB`:`${(bytes/(1024*1024)).toFixed(2)} MB`;
const nextFrame=()=>new Promise(resolve=>setTimeout(resolve,0));

/** Keep only one full-size decoded photo and one alignment canvas in memory. */
export function initStudentAnswerUploads({root,data,api,run,isBusy,reload,success}){
    const get=selector=>root.querySelector(selector),input=get('#answerScanFile');
    if(!input)return;
    const dropzone=get('[data-answer-dropzone]'),canvas=get('#answerAlignment'),preview=get('[data-answer-aligned]');
    const status=get('#answerScanStatus'),errorBox=get('#answerScanError'),batch=get('[data-answer-batch]');
    const disabled=input.disabled,queue=[];
    let active=null,source=null,aligned=null,running=false,preparing=false,pauseRequested=false,paused=false,nextId=0;
    const canChoose=()=>!disabled&&!input.disabled&&!isBusy()&&!running&&!preparing;
    const action=callback=>run(callback).finally(render);
    const clearError=()=>{errorBox.textContent='';errorBox.hidden=true;dropzone.classList.remove('is-invalid');dropzone.removeAttribute('aria-invalid');};
    const showError=message=>{errorBox.textContent=message;errorBox.hidden=false;dropzone.classList.add('is-invalid');dropzone.setAttribute('aria-invalid','true');};
    const releaseSource=()=>{if(source)source.width=source.height=0;source=null;aligned=null;canvas.width=canvas.height=0;preview.hidden=true;preview.removeAttribute('src');};
    const clearPreview=()=>{
        releaseSource();active=null;get('[data-answer-alignment]').hidden=true;get('[data-answer-file]').hidden=true;
        get('[data-answer-file-name]').textContent='';get('[data-answer-file-size]').textContent='';
    };
    const statusLabel=entry=>({queued:'Queued',preparing:'Preparing',ready:'Ready',alignment:'Needs alignment',uploading:'Uploading',uploaded:'Uploaded',failed:'Failed'})[entry.state];
    const button=(label,icon,callback)=>{
        const element=document.createElement('button');element.type='button';element.className='btn btn-sm btn-outline-primary';
        const glyph=document.createElement('i');glyph.className=`ti ${icon} me-1`;glyph.setAttribute('aria-hidden','true');
        element.append(glyph,document.createTextNode(label));element.disabled=running||preparing||isBusy();element.addEventListener('click',callback);return element;
    };
    function render(){
        batch.hidden=!queue.length;
        const uploaded=queue.filter(entry=>entry.state==='uploaded').length,failed=queue.filter(entry=>entry.state==='failed').length;
        const attention=queue.filter(entry=>entry.state==='alignment'||entry.result?.flagged_count>0).length;
        const ready=queue.filter(entry=>entry.state==='ready'||entry.state==='queued').length;
        get('[data-answer-batch-summary]').textContent=`${queue.length} / ${LIMIT} photos · Uploaded: ${uploaded} · Needs attention: ${attention} · Failed: ${failed} · Ready: ${ready}${running?' · Processing one sheet at a time':paused?' · Paused':''}`;
        const progress=get('[data-answer-batch-progress]');progress.max=queue.length||LIMIT;progress.value=uploaded;
        const start=get('[data-answer-batch-start]');start.disabled=disabled||isBusy()||!ready;
        get('[data-answer-batch-start-label]').textContent=paused?'Resume Batch':'Upload Batch';
        const pause=get('[data-answer-batch-pause]');pause.hidden=!running;pause.disabled=pauseRequested;
        get('[data-answer-batch-pause-label]').textContent=pauseRequested?'Finishing current sheet…':'Pause';
        get('[data-answer-batch-retry]').disabled=disabled||isBusy()||!failed;
        get('[data-answer-batch-clear]').disabled=isBusy();get('[data-answer-batch-history]').disabled=isBusy()||!uploaded;
        const list=get('[data-answer-batch-list]');list.replaceChildren();
        for(const entry of queue){
            const row=document.createElement('div');row.className='answer-batch-item';row.dataset.answerQueueId=entry.id;row.dataset.state=entry.state;
            row.classList.toggle('is-selected',active===entry);
            const thumbnail=document.createElement(entry.thumbnail?'img':'span');thumbnail.className='answer-batch-thumbnail';
            if(entry.thumbnail){thumbnail.src=entry.thumbnail;thumbnail.alt=`Sheet preview: ${entry.name}`;}
            else{thumbnail.classList.add('ti','ti-photo');thumbnail.setAttribute('aria-hidden','true');}
            const details=document.createElement('div');details.className='answer-batch-details';
            const name=document.createElement('div');name.className='fw-semibold text-break';name.textContent=entry.name;
            const meta=document.createElement('div');meta.className='small text-secondary';meta.textContent=sizeLabel(entry.file.size);
            const badge=document.createElement('span');badge.className=`badge ${entry.state==='uploaded'?'bg-green-lt':entry.state==='failed'?'bg-red-lt':entry.state==='alignment'?'bg-yellow-lt':'bg-blue-lt'}`;badge.textContent=statusLabel(entry);
            const note=document.createElement('div');note.className=`small mt-1 ${entry.state==='failed'?'text-danger':'text-secondary'}`;
            note.textContent=entry.message||'Ready for automatic alignment and upload.';
            const step=document.createElement('progress');step.className='answer-batch-progress';step.max=100;step.value=entry.state==='uploaded'?100:entry.state==='uploading'?75:entry.state==='ready'?30:entry.state==='preparing'?10:0;
            step.setAttribute('aria-label',`${entry.name}: ${statusLabel(entry)}`);
            details.append(name,meta,badge,note,step);
            const controls=document.createElement('div');controls.className='answer-batch-actions';
            if(entry.state==='uploaded'){
                if(data.permissions['answer-verify']){const review=button('Review','ti-checklist',()=>{});review.dataset.answerReview=entry.result.id;controls.append(review);}
            }else{
                if(entry.prepared)controls.append(button('Align','ti-focus-2',()=>action(()=>openEntry(entry))));
                if(entry.state==='failed')controls.append(button('Retry','ti-refresh',()=>process([entry])));
                const remove=button('Remove','ti-x',()=>{if(isBusy())return;queue.splice(queue.indexOf(entry),1);if(active===entry)clearPreview();clearError();render();});
                remove.classList.replace('btn-outline-primary','btn-outline-danger');controls.append(remove);
            }
            row.append(thumbnail,details,controls);list.append(row);
        }
        const blocked=disabled||running||preparing;
        input.disabled=blocked;get('#answerCameraFile').disabled=blocked;get('[data-answer-camera]').disabled=blocked;
        dropzone.setAttribute('aria-disabled',String(blocked));dropzone.tabIndex=blocked?-1:0;
        const editable=active&&active.state!=='uploaded'&&!running&&!preparing&&!isBusy();
        get('[data-answer-upload]').disabled=!editable||!aligned;
        get('[data-answer-auto]').disabled=!editable||!source;get('[data-answer-corners]').disabled=!editable||!source;
        get('[data-answer-file-remove]').disabled=!editable;
    }
    async function loadPhoto(file){
        if(file.size>10*1024*1024||!TYPES.includes(file.type))throw new Error('Choose a JPG, PNG or WebP up to 10 MB.');
        let image;
        try{
            try{image=await createImageBitmap(file);}catch{throw new Error('This photo could not be opened. Choose a valid JPG, PNG or WebP image.');}
            if(image.width*image.height>24000000||image.width>6000||image.height>6000)throw new Error('Choose a photo up to 24 megapixels and 6000 pixels on either side.');
            const factor=Math.min(1,1600/Math.max(image.width,image.height)),photo=document.createElement('canvas');
            photo.width=Math.max(1,Math.round(image.width*factor));photo.height=Math.max(1,Math.round(image.height*factor));photo.getContext('2d').drawImage(image,0,0,photo.width,photo.height);return photo;
        }finally{image?.close();}
    }
    async function prepareEntry(entry){
        entry.state='preparing';entry.message='Checking photo and corner squares…';render();await nextFrame();
        const photo=await loadPhoto(entry.file);
        try{
            const thumbnail=document.createElement('canvas'),scale=Math.min(64/photo.width,96/photo.height);thumbnail.width=Math.max(1,Math.round(photo.width*scale));thumbnail.height=Math.max(1,Math.round(photo.height*scale));
            thumbnail.getContext('2d').drawImage(photo,0,0,thumbnail.width,thumbnail.height);entry.thumbnail=thumbnail.toDataURL('image/jpeg',.7);thumbnail.width=thumbnail.height=0;
            entry.points=findSheetCorners(photo.getContext('2d').getImageData(0,0,photo.width,photo.height).data,photo.width,photo.height);
            entry.prepared=true;entry.state=entry.points.length===4?'ready':'alignment';
            entry.message=entry.state==='ready'?'Corners found. Ready to upload.':'Open Align and select the four corner square centers.';
        }finally{photo.width=photo.height=0;}
    }
    function paint(){
        if(!source||!active)return;
        const ctx=canvas.getContext('2d');ctx.drawImage(source,0,0);
        active.points.forEach(([x,y],i)=>{ctx.beginPath();ctx.arc(x,y,12,0,Math.PI*2);ctx.strokeStyle='#dc2626';ctx.lineWidth=3;ctx.stroke();ctx.fillStyle='#dc2626';ctx.font='bold 22px Arial';ctx.fillText(i+1,x+14,y);});
        if(active.points.length===4){
            aligned=alignSheet(source,active.points);preview.src=aligned.toDataURL();preview.hidden=false;
            get('[data-answer-align-status]').textContent='Check the aligned preview, then upload for staff verification.';
            if(!['uploading','uploaded'].includes(active.state)){active.state='ready';active.message='Alignment ready. Upload this sheet or resume the batch.';}
        }else{
            aligned=null;preview.hidden=true;preview.removeAttribute('src');
            get('[data-answer-align-status]').textContent=`Tap ${['top left','top right','bottom right','bottom left'][active.points.length]} corner square center (${active.points.length}/4).`;
            active.state='alignment';active.message='Select all four corner square centers before uploading.';
        }
    }
    async function openEntry(entry){
        clearPreview();active=entry;clearError();
        if(!entry.prepared)await prepareEntry(entry);
        source=await loadPhoto(entry.file);canvas.width=source.width;canvas.height=source.height;
        get('[data-answer-file-name]').textContent=entry.name;get('[data-answer-file-size]').textContent=sizeLabel(entry.file.size);
        get('[data-answer-alignment-heading]').textContent=`Selected sheet: ${entry.name}`;
        get('[data-answer-file]').hidden=false;get('[data-answer-alignment]').hidden=false;paint();render();
    }
    async function addFiles(files){
        files=Array.from(files||[]);if(!files.length||!canChoose())return;
        return action(async()=>{
            clearError();
            if(queue.length+files.length>LIMIT){const message=`Add up to ${LIMIT} photos per queue. ${queue.length} already selected; remove or clear photos before adding ${files.length} more.`;showError(message);throw new Error(message);}
            const entries=files.map(file=>({id:++nextId,file,name:file.name||'Pasted photo',state:'queued',points:[],prepared:false}));queue.push(...entries);
            for(const field of [input,get('#answerCameraFile')])field.value='';
            preparing=true;clearPreview();const unlock=lockControls();
            try{
                for(const entry of entries){
                    status.textContent=`Preparing photo ${entries.indexOf(entry)+1} of ${entries.length}: ${entry.name}`;
                    try{await prepareEntry(entry);}catch(error){entry.state='failed';entry.message=error.message;}render();
                }
                const first=entries.find(entry=>entry.prepared);if(first)await openEntry(first);
                status.textContent='Photos ready. Upload the batch, or select Align to check an individual sheet.';
                if(entries.length===1&&entries[0].state==='failed'){showError(entries[0].message);throw new Error(entries[0].message);}
            }finally{preparing=false;unlock();}
        });
    }
    async function uploadEntry(entry){
        if(!entry.prepared)await prepareEntry(entry);
        await openEntry(entry);
        if(!aligned){entry.state='alignment';return;}
        entry.state='uploading';entry.message='Uploading and checking sheet identity…';render();await nextFrame();
        const blob=await new Promise(resolve=>aligned.toBlob(resolve,'image/png'));if(!blob)throw new Error('The aligned photo could not be prepared.');
        const payload=new FormData();payload.append('paper_id',document.getElementById('answerPaper').value);payload.append('image',blob,'aligned-sheet.png');payload.append('original',entry.file);
        entry.result=await api(`/exams/${data.exam_id}/scans`,payload);entry.state='uploaded';
        entry.message=`${entry.result.student_name||entry.result.candidate_code||'Student identified'}${entry.result.student_name&&entry.result.candidate_code?' · '+entry.result.candidate_code:''} · ${entry.result.flagged_count||0} flagged question(s) · Pending staff verification.`;
    }
    function lockControls(){
        const controls=[...root.querySelectorAll('input,select,textarea,button')].filter(control=>!control.hasAttribute('data-answer-batch-pause'));
        const states=controls.map(control=>[control,control.disabled]);controls.forEach(control=>control.disabled=true);
        return ()=>states.forEach(([control,wasDisabled])=>control.disabled=wasDisabled);
    }
    async function process(entries){
        if(disabled||isBusy()||running)return;
        return action(async()=>{
            running=true;pauseRequested=false;paused=false;clearError();const unlock=lockControls();
            try{
                for(const entry of entries){
                    if(pauseRequested){paused=true;break;}
                    if(entry.state==='uploaded')continue;
                    try{await uploadEntry(entry);}catch(error){entry.state='failed';entry.message=error.message;}
                    render();await nextFrame();
                }
                status.textContent=paused?'Batch paused. The current sheet finished; click Resume Batch to continue.':'Batch finished. Check the queue for sheets needing alignment or retry. Uploaded sheets await staff verification.';
            }finally{running=false;unlock();}
        });
    }
    get('[data-answer-batch-start]').addEventListener('click',()=>process(queue.filter(entry=>['queued','ready'].includes(entry.state))));
    get('[data-answer-batch-retry]').addEventListener('click',()=>process(queue.filter(entry=>entry.state==='failed')));
    get('[data-answer-batch-pause]').addEventListener('click',()=>{pauseRequested=true;render();});
    get('[data-answer-batch-clear]').addEventListener('click',()=>{if(isBusy())return;queue.length=0;clearPreview();clearError();status.textContent='Queue cleared. Uploaded sheets remain in Scan History.';render();});
    get('[data-answer-batch-history]').addEventListener('click',()=>{if(!isBusy())reload();});
    get('[data-answer-camera]').addEventListener('click',()=>{if(canChoose())get('#answerCameraFile').click();});
    for(const field of [input,get('#answerCameraFile')])field.addEventListener('change',()=>addFiles(field.files));
    dropzone.addEventListener('click',()=>{if(canChoose()){dropzone.focus();input.click();}});
    dropzone.addEventListener('keydown',event=>{if(event.key==='Enter'||event.key===' '){event.preventDefault();if(canChoose())input.click();}});
    for(const name of ['dragenter','dragover'])dropzone.addEventListener(name,event=>{event.preventDefault();if(canChoose()){dropzone.classList.add('is-dragging');if(event.dataTransfer)event.dataTransfer.dropEffect='copy';}});
    dropzone.addEventListener('dragleave',event=>{if(!dropzone.contains(event.relatedTarget))dropzone.classList.remove('is-dragging');});
    dropzone.addEventListener('drop',event=>{event.preventDefault();dropzone.classList.remove('is-dragging');addFiles(event.dataTransfer?.files);});
    document.addEventListener('paste',event=>{
        if(event.defaultPrevented||!canChoose()||!dropzone.getClientRects().length||document.querySelector('.modal.show,.swal2-container')||event.target?.closest?.('input,textarea,select,[contenteditable]:not([contenteditable="false"])'))return;
        const direct=Array.from(event.clipboardData?.files||[]),files=direct.length?direct:Array.from(event.clipboardData?.items||[]).filter(item=>item.kind==='file').map(item=>item.getAsFile()).filter(Boolean);
        if(files.length){event.preventDefault();addFiles(files);}
    });
    get('[data-answer-file-remove]').addEventListener('click',()=>{if(isBusy()||!active||active.state==='uploaded')return;queue.splice(queue.indexOf(active),1);clearPreview();clearError();status.textContent='Photo removed. Choose, drop or paste another photo.';render();dropzone.focus();});
    get('[data-answer-auto]').addEventListener('click',()=>action(async()=>{if(!source||active.state==='uploaded')return;active.points=findSheetCorners(source.getContext('2d').getImageData(0,0,source.width,source.height).data,source.width,source.height);paint();}));
    get('[data-answer-corners]').addEventListener('click',()=>{if(!isBusy()&&source&&active.state!=='uploaded'){active.points=[];paint();render();}});
    canvas.addEventListener('pointerdown',event=>action(async()=>{
        if(!source||active.state==='uploaded'||running)return;event.preventDefault();if(active.points.length===4)active.points=[];
        const rect=canvas.getBoundingClientRect();active.points.push([(event.clientX-rect.left)*canvas.width/rect.width,(event.clientY-rect.top)*canvas.height/rect.height]);paint();
    }));
    get('[data-answer-upload]').addEventListener('click',()=>{
        if(isBusy()||!active||active.state==='uploaded')return;
        action(async()=>{
            if(!aligned)throw new Error('Select a photo and align all four marker centers first.');
            const entry=active;
            try{await uploadEntry(entry);}catch(error){entry.state='failed';entry.message=error.message;showError(error.message);throw error;}
            if(queue.length===1&&entry.state==='uploaded'){await success(entry.result.message);reload();}
            else status.textContent='Sheet uploaded. Staff verification is required before scores are saved.';
        });
    });
    render();
}
