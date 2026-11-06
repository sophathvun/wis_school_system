export function setupG9ImportUpload(form,{error,previewButton,onChange}) {
    const input=form.elements.file,zone=form.querySelector('[data-g9-import-dropzone]');
    const card=form.querySelector('[data-g9-import-file]'),name=form.querySelector('[data-g9-import-filename]');
    const meta=form.querySelector('[data-g9-import-filemeta]'),remove=form.querySelector('[data-g9-import-remove]');
    let selected=null,busy=false;
    const feedback=message=>{
        error.textContent=message;
        zone.classList.toggle('is-invalid',!!message);
        zone.setAttribute('aria-invalid',String(!!message));
    };
    const render=()=>{
        card.hidden=!selected;zone.disabled=busy;input.disabled=busy;remove.disabled=busy;
        zone.setAttribute('aria-busy',String(busy));previewButton.disabled=busy||!selected;
        name.textContent=selected?.name??'';
        meta.textContent=selected?`${selected.size<1024*1024?Math.max(1,Math.round(selected.size/1024))+' KB':(selected.size/(1024*1024)).toFixed(2)+' MB'} · Ready to preview`:'';
    };
    const choose=files=>{
        if(busy)return;
        const candidates=Array.from(files??[]);if(!candidates.length)return;
        selected=null;input.value='';onChange();feedback('');
        const file=candidates[0];
        if(candidates.length!==1)feedback('Choose one Excel or CSV file at a time.');
        else if(!/\.(xlsx|csv)$/i.test(file.name))feedback('Choose an Excel (.xlsx) or CSV (.csv) file.');
        else if(!file.size)feedback('This file is empty. Choose a file containing candidate data.');
        else if(file.size>10*1024*1024)feedback('This file exceeds 10 MB. Choose a smaller file.');
        else selected=file;
        render();
    };
    zone.addEventListener('click',()=>{if(!busy)input.click();});
    input.addEventListener('change',()=>choose(input.files));
    for(const type of ['dragenter','dragover'])zone.addEventListener(type,event=>{
        event.preventDefault();if(busy)return;
        zone.classList.add('is-dragging');if(event.dataTransfer)event.dataTransfer.dropEffect='copy';
    });
    zone.addEventListener('dragleave',event=>{if(!zone.contains(event.relatedTarget))zone.classList.remove('is-dragging');});
    zone.addEventListener('drop',event=>{event.preventDefault();zone.classList.remove('is-dragging');choose(event.dataTransfer?.files);});
    document.addEventListener('paste',event=>{
        if(event.defaultPrevented||busy||!zone.getClientRects().length||document.querySelector('.modal.show,.swal2-container')||event.target?.closest?.('input,textarea,select,[contenteditable]:not([contenteditable="false"])'))return;
        const direct=Array.from(event.clipboardData?.files??[]);
        const files=direct.length?direct:Array.from(event.clipboardData?.items??[]).filter(item=>item.kind==='file').map(item=>item.getAsFile()).filter(Boolean);
        if(!files.length)return;
        event.preventDefault();choose(files);
    });
    remove.addEventListener('click',()=>{
        if(busy)return;
        selected=null;input.value='';onChange();feedback('');render();zone.focus();
    });
    form.addEventListener('reset',()=>{if(busy)return;selected=null;onChange();feedback('');render();});
    render();
    return {
        file:()=>selected,
        requireFile(){if(selected)return true;feedback('Choose, drop or paste a file before previewing.');zone.focus();return false;},
        setBusy(value){busy=value;if(busy)zone.classList.remove('is-dragging');render();},
    };
}
