export function setupG9PhotoUpload(form,{error,previewButton,onChange}) {
    const input=form.querySelector('input[type=file]'),zone=form.querySelector('[data-photo-dropzone]');
    const selection=form.querySelector('[data-photo-selection]'),list=form.querySelector('[data-photo-file-list]');
    const summary=form.querySelector('[data-photo-file-summary]'),clear=form.querySelector('[data-photo-clear]');
    const enabled=!!previewButton;
    let selected=[],busy=false;
    const size=bytes=>bytes<1024*1024?Math.max(1,Math.round(bytes/1024))+' KB':(bytes/(1024*1024)).toFixed(2)+' MB';
    const feedback=message=>{error.textContent=message;zone.classList.toggle('is-invalid',!!message);zone.setAttribute('aria-invalid',String(!!message));};
    const render=()=>{
        selection.hidden=!selected.length;zone.disabled=busy||!enabled;input.disabled=busy||!enabled;clear.disabled=busy||!enabled;
        zone.setAttribute('aria-busy',String(busy));if(previewButton)previewButton.disabled=busy||!selected.length;
        summary.textContent=`${selected.length.toLocaleString()} file${selected.length===1?'':'s'} selected · ${size(selected.reduce((sum,file)=>sum+file.size,0))}`;
        list.replaceChildren();
        selected.forEach((file,index)=>{
            const row=document.createElement('li'),icon=document.createElement('span'),glyph=document.createElement('i');
            row.className='g9-import-file';icon.className='document-file-icon';glyph.className=/\.zip$/i.test(file.name)?'ti ti-file-zip':'ti ti-photo';glyph.setAttribute('aria-hidden','true');icon.append(glyph);
            const details=document.createElement('div'),name=document.createElement('div'),meta=document.createElement('div');
            details.className='g9-import-file-details';name.className='document-file-name';name.textContent=file.name;meta.className='document-file-meta';meta.textContent=size(file.size);details.append(name,meta);
            const remove=document.createElement('button'),removeIcon=document.createElement('i');
            remove.type='button';remove.className='document-file-remove';remove.disabled=busy||!enabled;remove.setAttribute('aria-label','Remove '+file.name);removeIcon.className='ti ti-x';removeIcon.setAttribute('aria-hidden','true');remove.append(removeIcon);
            remove.addEventListener('click',()=>{if(busy||!enabled)return;selected.splice(index,1);input.value='';onChange();feedback('');render();zone.focus();});
            row.append(icon,details,remove);list.append(row);
        });
    };
    const choose=files=>{
        if(busy||!enabled)return;
        const incoming=Array.from(files??[]);if(!incoming.length)return;
        input.value='';onChange();feedback('');
        const invalid=incoming.find(file=>!/\.(jpe?g|png|webp|zip)$/i.test(file.name)||!file.size||file.size>(/\.zip$/i.test(file.name)?50:5)*1024*1024);
        if(selected.length+incoming.length>200)feedback('Select up to 200 files at a time. Use ZIP files for larger photo collections. No files were added.');
        else if(invalid){
            const message=!/\.(jpe?g|png|webp|zip)$/i.test(invalid.name)?'Choose JPG, PNG, WebP or ZIP files.':!invalid.size?'This file is empty.':`This file exceeds the ${/\.zip$/i.test(invalid.name)?50:5} MB limit.`;
            feedback(`${invalid.name}: ${message} No files were added.`);
        }else selected.push(...incoming);
        render();
    };
    const reset=()=>{selected=[];input.value='';onChange();feedback('');render();};
    zone.addEventListener('click',()=>{if(enabled&&!busy)input.click();});
    input.addEventListener('change',()=>choose(input.files));
    for(const type of ['dragenter','dragover'])zone.addEventListener(type,event=>{event.preventDefault();if(!enabled||busy)return;zone.classList.add('is-dragging');if(event.dataTransfer)event.dataTransfer.dropEffect='copy';});
    zone.addEventListener('dragleave',event=>{if(!zone.contains(event.relatedTarget))zone.classList.remove('is-dragging');});
    zone.addEventListener('drop',event=>{event.preventDefault();zone.classList.remove('is-dragging');choose(event.dataTransfer?.files);});
    document.addEventListener('paste',event=>{
        if(!enabled||busy||event.defaultPrevented||!zone.getClientRects().length||document.querySelector('.modal.show,.swal2-container')||event.target?.closest?.('input,textarea,select,[contenteditable]:not([contenteditable="false"])'))return;
        const direct=Array.from(event.clipboardData?.files??[]),files=direct.length?direct:Array.from(event.clipboardData?.items??[]).filter(item=>item.kind==='file').map(item=>item.getAsFile()).filter(Boolean);
        if(files.length){event.preventDefault();choose(files);}
    });
    clear.addEventListener('click',()=>{if(enabled&&!busy){reset();zone.focus();}});
    form.addEventListener('reset',()=>{if(enabled&&!busy)reset();});
    render();
    return {files:()=>[...selected],clear:reset,setBusy(value){busy=value;if(busy)zone.classList.remove('is-dragging');render();}};
}
