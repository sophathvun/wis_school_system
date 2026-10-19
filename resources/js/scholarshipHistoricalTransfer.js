import Swal from 'sweetalert2';

export function initHistoricalTransfer(root) {
    const panel=root.querySelector('[data-scholarship-transfer]');if(!panel)return;
    panel.querySelector('[data-transfer-year]')?.addEventListener('change',event=>event.target.form.submit());
    const upload=panel.querySelector('[data-transfer-upload]'),confirm=panel.querySelector('[data-transfer-confirm]');if(!upload)return;
    const preview=panel.querySelector('[data-transfer-preview]'),file=upload.elements.file,error=panel.querySelector('[data-transfer-file-error]');
    let busy=false;
    const invalidate=()=>{preview.hidden=true;confirm.hidden=true;confirm.elements.token.value='';confirm.elements.acknowledge.checked=false;file.classList.remove('is-invalid');error.textContent='';};
    upload.addEventListener('change',invalidate);
    const reportError=message=>{file.classList.add('is-invalid');error.textContent=message;Swal.fire({icon:'error',title:'Check the import',text:message});};
    const request=async(url,form)=>{
        const response=await fetch(url,{method:'POST',body:new FormData(form),credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
        const data=await response.json();if(!response.ok||response.redirected)throw new Error(data.errors?Object.values(data.errors).flat().join(' '):data.message||'Unable to process this import. Please try again.');return data;
    };
    const lock=value=>{busy=value;upload.querySelector('button').disabled=value;file.disabled=value;upload.elements.replace.disabled=value;confirm.querySelector('button').disabled=value;};
    upload.addEventListener('submit',async event=>{
        event.preventDefault();if(busy)return;invalidate();
        if(!file.files.length){reportError('Choose a CSV file first.');file.focus();return;}
        if(file.files[0].size>5*1024*1024){reportError('Choose a CSV file no larger than 5 MB.');return;}
        // Capture enabled inputs before locking them; disabled file inputs are not included in FormData.
        const payload=new FormData(upload);lock(true);
        try{
            const response=await fetch(panel.dataset.previewUrl,{method:'POST',body:payload,credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
            const data=await response.json();if(!response.ok||response.redirected)throw new Error(data.errors?Object.values(data.errors).flat().join(' '):data.message||'Unable to preview.');
            panel.querySelector('[data-transfer-summary]').textContent=`${data.created} new · ${data.updated} replacement(s) · ${data.error_count} error(s)`;
            const errors=panel.querySelector('[data-transfer-errors]');errors.replaceChildren();errors.hidden=!data.errors.length;
            data.errors.forEach(item=>{const line=document.createElement('div');line.textContent=`Line ${item.line} (${item.student_id||'Unknown student'}): ${item.message}`;errors.append(line);});
            const settings=panel.querySelector('[data-transfer-settings-preview]'),settingsRows=panel.querySelector('[data-transfer-settings-rows]');
            settingsRows.replaceChildren();settings.hidden=!data.settings_changes?.length;
            (data.settings_changes||[]).forEach(item=>{const row=document.createElement('tr');[item.setting,item.before,item.after].forEach(value=>{const cell=document.createElement('td');cell.textContent=String(value);row.append(cell);});settingsRows.append(row);});
            const body=panel.querySelector('[data-transfer-rows]');body.replaceChildren();
            data.rows.forEach(item=>{const row=document.createElement('tr');[item.line,`${item.student_code} · ${item.student_name}`,`${item.grade} / ${item.class_rank}`,item.total_score??'—',item.award_percent??'—',item.candidate_id?'Replace':'Add'].forEach(value=>{const cell=document.createElement('td');cell.textContent=String(value);row.append(cell);});body.append(row);});
            confirm.elements.token.value=data.token||'';confirm.hidden=!data.token;preview.hidden=false;
            if(data.error_count)Swal.fire({icon:'warning',title:'Correct the CSV',text:`${data.error_count} error(s) found. Nothing has been imported.`});
            else Swal.fire({toast:true,position:'top-end',icon:'success',title:'Validation passed. Review and confirm the import.',showConfirmButton:false,timer:3500});
        }catch(e){reportError(e.message||'Unable to preview. Please try again.');}finally{lock(false);}
    });
    confirm.addEventListener('submit',async event=>{
        event.preventDefault();if(busy)return;
        if(!confirm.reportValidity())return;
        const answer=await Swal.fire({icon:'question',title:'Import historical records?',text:'This academic year will become a protected historical archive. Review any replacements before continuing.',showCancelButton:true,confirmButtonText:'Confirm Import',cancelButtonText:'Cancel'});
        if(!answer.isConfirmed||busy)return;lock(true);
        try{const data=await request(panel.dataset.importUrl,confirm);confirm.hidden=true;upload.reset();preview.hidden=true;await Swal.fire({toast:true,position:'top-end',icon:'success',title:data.message,showConfirmButton:false,timer:3500,timerProgressBar:true});location.assign(data.redirect);}
        catch(e){reportError(e.message||'Unable to import. Please preview again.');}finally{lock(false);}
    });
    panel.dataset.transferReady='true';
}
