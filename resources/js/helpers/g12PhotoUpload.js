export function setupG12PhotoUpload(form,{isBusy,onChange}) {
    const input=form.querySelector('input[type=file]'),zone=form.querySelector('[data-g12-photo-drop]');
    const list=form.querySelector('[data-g12-photo-files]'),selection=form.querySelector('[data-g12-photo-file-selection]');
    const summary=form.querySelector('[data-g12-photo-file-summary]'),clear=form.querySelector('[data-g12-photo-files-clear]');
    const error=form.querySelector('[data-g12-photo-upload-error]'),preview=form.querySelector('[data-g12-photo-preview-button]');
    const year=form.querySelector('#photoYear');
    let files=[];
    const size=bytes=>bytes<1024*1024?Math.max(1,Math.round(bytes/1024))+' KB':(bytes/(1024*1024)).toFixed(2)+' MB';
    const feedback=message=>{error.textContent=message;error.hidden=!message;zone.classList.toggle('is-invalid',!!message);zone.setAttribute('aria-invalid',String(!!message));};
    const sync=()=>{
        const busy=isBusy();zone.disabled=busy;input.disabled=busy;clear.disabled=busy;zone.setAttribute('aria-busy',String(busy));
        preview.disabled=busy||year.disabled||!files.length;selection.hidden=!files.length;
        summary.textContent=`${files.length.toLocaleString()} file${files.length===1?'':'s'} selected · ${size(files.reduce((total,file)=>total+file.size,0))}`;
        list.replaceChildren();
        files.forEach((file,index)=>{
            const row=document.createElement('li'),icon=document.createElement('span'),glyph=document.createElement('i'),details=document.createElement('div'),name=document.createElement('div'),meta=document.createElement('div'),remove=document.createElement('button'),removeIcon=document.createElement('i');
            row.className='g12-excel-file';icon.className='document-file-icon';glyph.className=/\.zip$/i.test(file.name)?'ti ti-file-zip':'ti ti-photo';glyph.setAttribute('aria-hidden','true');icon.append(glyph);
            details.className='g12-photo-file-details';name.className='document-file-name';name.textContent=file.name;meta.className='document-file-meta';meta.textContent=size(file.size);details.append(name,meta);
            remove.type='button';remove.className='document-file-remove';remove.disabled=busy;remove.setAttribute('aria-label','Remove '+file.name);removeIcon.className='ti ti-x';removeIcon.setAttribute('aria-hidden','true');remove.append(removeIcon);
            remove.addEventListener('click',()=>{if(isBusy())return;files.splice(index,1);input.value='';feedback('');onChange();sync();zone.focus();});
            row.append(icon,details,remove);list.append(row);
        });
    };
    const choose=incoming=>{
        if(isBusy())return;
        const added=Array.from(incoming??[]);if(!added.length)return;
        input.value='';feedback('');
        const invalid=added.find(file=>!/\.(jpe?g|png|webp|zip)$/i.test(file.name)||!file.size||file.size>(/\.zip$/i.test(file.name)?200:20)*1024*1024);
        if(files.length+added.length>100)feedback('Select up to 100 files per preview. Use ZIP files for larger collections. No files were added.');
        else if(invalid){const message=!/\.(jpe?g|png|webp|zip)$/i.test(invalid.name)?'Choose JPG, PNG, WebP or ZIP files.':!invalid.size?'This file is empty.':`This file exceeds the ${/\.zip$/i.test(invalid.name)?200:20} MB limit.`;feedback(`${invalid.name}: ${message} No files were added.`);}
        else{files.push(...added);onChange();}
        sync();
    };
    zone.addEventListener('click',()=>{if(!isBusy())input.click();});
    input.addEventListener('change',()=>choose(input.files));
    for(const type of ['dragenter','dragover'])zone.addEventListener(type,event=>{event.preventDefault();if(isBusy())return;zone.classList.add('is-dragging');if(event.dataTransfer)event.dataTransfer.dropEffect='copy';});
    zone.addEventListener('dragleave',event=>{if(!zone.contains(event.relatedTarget))zone.classList.remove('is-dragging');});
    zone.addEventListener('drop',event=>{event.preventDefault();zone.classList.remove('is-dragging');choose(event.dataTransfer?.files);});
    document.addEventListener('paste',event=>{
        if(isBusy()||event.defaultPrevented||!zone.getClientRects().length||document.querySelector('.modal.show,.swal2-container')||event.target?.closest?.('input,textarea,select,[contenteditable]:not([contenteditable="false"])'))return;
        const direct=Array.from(event.clipboardData?.files??[]),pasted=direct.length?direct:Array.from(event.clipboardData?.items??[]).filter(item=>item.kind==='file').map(item=>item.getAsFile()).filter(Boolean);
        if(pasted.length){event.preventDefault();choose(pasted);}
    });
    const reset=()=>{files=[];input.value='';feedback('');onChange();sync();};
    clear.addEventListener('click',()=>{if(!isBusy()){reset();zone.focus();}});
    form.addEventListener('reset',()=>{if(!isBusy())reset();});
    sync();
    return {files:()=>[...files],sync};
}
