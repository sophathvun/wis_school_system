import { Modal } from 'bootstrap';
import Swal from 'sweetalert2';
import { findSheetCorners, alignSheet } from './helpers/answerSheetAlignment.js';

export function initTeacherKeys({root,api,run,selected,keyPaper,onDetected}){
    const get=s=>document.querySelector(s),keyElement=get('#answerKeyModal'),scanElement=get('#teacherKeyScanModal');
    if(!scanElement)return;
    const scanModal=Modal.getOrCreateInstance(scanElement),keyModal=Modal.getOrCreateInstance(keyElement);
    let context,source,points=[],aligned,detected;
    const canvas=get('#teacherKeyAlignment'),status=get('[data-teacher-align-status]'),useButton=get('[data-teacher-key-use]');
    const input=get('#teacherKeyFile'),camera=get('#teacherKeyCamera'),dropzone=get('[data-teacher-key-dropzone]');
    const photoStatus=get('#teacherKeyPhotoStatus'),photoError=get('#teacherKeyPhotoError'),preview=get('[data-teacher-aligned]');
    const busy=()=>root.getAttribute('aria-busy')==='true';
    const canChoose=()=>scanElement.classList.contains('show')&&!busy()&&!input.disabled;
    const resetDetection=()=>{detected=null;useButton.disabled=true;get('[data-teacher-detected-status]').hidden=true;};
    const resetPhoto=()=>{
        if(source)source.width=source.height=0;source=null;points=[];aligned=null;resetDetection();
        input.value='';camera.value='';canvas.width=canvas.height=0;preview.hidden=true;preview.removeAttribute('src');
        get('[data-teacher-alignment]').hidden=true;get('[data-teacher-key-file]').hidden=true;get('[data-teacher-key-upload]').disabled=true;
        get('[data-teacher-key-file-name]').textContent='';get('[data-teacher-key-file-size]').textContent='';
        photoStatus.textContent='';photoError.textContent='';photoError.hidden=true;dropzone.classList.remove('is-invalid','is-dragging');dropzone.removeAttribute('aria-invalid');
    };
    const loading=value=>{
        input.disabled=value;camera.disabled=value;get('[data-teacher-key-camera]').disabled=value;get('[data-teacher-key-file-remove]').disabled=value;
        dropzone.setAttribute('aria-disabled',String(value));dropzone.tabIndex=value?-1:0;
    };
    const paint=()=>{
        const ctx=canvas.getContext('2d');ctx.drawImage(source,0,0);
        points.forEach(([x,y],i)=>{ctx.beginPath();ctx.arc(x,y,12,0,Math.PI*2);ctx.strokeStyle='#dc2626';ctx.lineWidth=3;ctx.stroke();ctx.fillStyle='#dc2626';ctx.font='bold 22px Arial';ctx.fillText(i+1,x+14,y);});
        if(points.length===4){aligned=alignSheet(source,points);preview.src=aligned.toDataURL();preview.hidden=false;status.textContent='Check the aligned preview, then upload to detect the correct answers.';}
        else{aligned=null;preview.hidden=true;preview.removeAttribute('src');status.textContent=`Tap ${['top left','top right','bottom right','bottom left'][points.length]} corner square center (${points.length}/4).`;}
        get('[data-teacher-key-upload]').disabled=!aligned;resetDetection();
    };
    document.querySelectorAll('[data-teacher-key-print]').forEach(button=>button.addEventListener('click',()=>{
        if(root.getAttribute('aria-busy')==='true')return;
        const paper=button.closest('#answerKeyModal')?keyPaper():selected();if(!paper)return;
        const popup=window.open('about:blank','_blank');run(async()=>{
            try{
                const result=await api(`/papers/${paper.id}/teacher-key`,{version:paper.version});
                if(popup){popup.opener=null;popup.location.assign(result.url);}
                else{const link=document.createElement('a');link.href=result.url;link.target='_blank';link.rel='noopener';link.textContent='Open Teacher Answer Key';await Swal.fire({title:'Teacher answer key ready',html:link});}
            }catch(error){popup?.close();throw error;}
        });
    }));
    get('[data-teacher-key-open]')?.addEventListener('click',()=>run(async()=>{
        context=keyPaper();resetPhoto();loading(false);
        get('[data-teacher-key-heading]').textContent=`${context.subject} · Grade ${context.grade} · Paper ${context.paper_version} · ${context.question_count} questions`;
        keyElement.addEventListener('hidden.bs.modal',()=>scanModal.show(),{once:true});keyModal.hide();
    }));
    scanElement.addEventListener('hidden.bs.modal',()=>{resetPhoto();keyModal.show();});
    const choosePhoto=files=>{
        files=Array.from(files||[]);if(!files.length||!canChoose())return;
        return run(async()=>{
            resetPhoto();loading(true);let image;
            try{
                if(files.length!==1)throw new Error('Choose one teacher answer key photo at a time.');
                const file=files[0];
                if(file.size>10*1024*1024||!['image/jpeg','image/png','image/webp'].includes(file.type))throw new Error('Choose a JPG, PNG or WebP up to 10 MB.');
                photoStatus.textContent='Preparing photo for alignment…';
                try{image=await createImageBitmap(file);}catch{throw new Error('This photo could not be opened. Choose a valid JPG, PNG or WebP image.');}
                if(!scanElement.classList.contains('show'))return;
                if(image.width*image.height>24000000)throw new Error('Choose a photo up to 24 megapixels.');
                const factor=Math.min(1,1600/Math.max(image.width,image.height));source=document.createElement('canvas');source.width=Math.max(1,Math.round(image.width*factor));source.height=Math.max(1,Math.round(image.height*factor));
                source.getContext('2d').drawImage(image,0,0,source.width,source.height);canvas.width=source.width;canvas.height=source.height;
                get('[data-teacher-key-file-name]').textContent=file.name||'Pasted teacher key photo';
                get('[data-teacher-key-file-size]').textContent=file.size<1024*1024?`${Math.max(1,Math.round(file.size/1024))} KB`:`${(file.size/(1024*1024)).toFixed(2)} MB`;
                get('[data-teacher-key-file]').hidden=false;
                points=findSheetCorners(source.getContext('2d').getImageData(0,0,source.width,source.height).data,source.width,source.height);get('[data-teacher-alignment]').hidden=false;paint();
                photoStatus.textContent='Photo ready. Check the corner alignment below before uploading.';
            }catch(error){
                resetPhoto();photoError.textContent=error.message;photoError.hidden=false;dropzone.classList.add('is-invalid');dropzone.setAttribute('aria-invalid','true');throw error;
            }finally{image?.close();loading(false);}
        });
    };
    get('[data-teacher-key-camera]').addEventListener('click',()=>{if(canChoose())camera.click();});
    for(const field of [input,camera])field.addEventListener('change',()=>choosePhoto(field.files));
    dropzone.addEventListener('click',()=>{if(canChoose()){dropzone.focus();input.click();}});
    dropzone.addEventListener('keydown',event=>{if(event.key==='Enter'||event.key===' '){event.preventDefault();if(canChoose())input.click();}});
    for(const name of ['dragenter','dragover'])dropzone.addEventListener(name,event=>{event.preventDefault();if(canChoose()){dropzone.classList.add('is-dragging');if(event.dataTransfer)event.dataTransfer.dropEffect='copy';}});
    dropzone.addEventListener('dragleave',event=>{if(!dropzone.contains(event.relatedTarget))dropzone.classList.remove('is-dragging');});
    dropzone.addEventListener('drop',event=>{event.preventDefault();dropzone.classList.remove('is-dragging');choosePhoto(event.dataTransfer?.files);});
    document.addEventListener('paste',event=>{
        if(event.defaultPrevented||!canChoose()||document.querySelector('.swal2-container')||event.target?.closest?.('input,textarea,select,[contenteditable]:not([contenteditable="false"])'))return;
        const direct=Array.from(event.clipboardData?.files||[]),files=direct.length?direct:Array.from(event.clipboardData?.items||[]).filter(item=>item.kind==='file').map(item=>item.getAsFile()).filter(Boolean);
        if(files.length){event.preventDefault();choosePhoto(files);}
    });
    get('[data-teacher-key-file-remove]').addEventListener('click',()=>{if(canChoose()){resetPhoto();photoStatus.textContent='Photo removed. Choose, drop or paste another photo.';dropzone.focus();}});
    get('[data-teacher-key-auto]').addEventListener('click',()=>run(async()=>{if(!source)return;points=findSheetCorners(source.getContext('2d').getImageData(0,0,source.width,source.height).data,source.width,source.height);paint();}));
    get('[data-teacher-key-corners]').addEventListener('click',()=>{if(!busy()&&source){points=[];paint();}});
    canvas.addEventListener('pointerdown',event=>run(async()=>{
        if(!source)return;event.preventDefault();if(points.length===4)points=[];const rect=canvas.getBoundingClientRect();points.push([(event.clientX-rect.left)*canvas.width/rect.width,(event.clientY-rect.top)*canvas.height/rect.height]);paint();
    }));
    get('[data-teacher-key-upload]').addEventListener('click',()=>run(async()=>{
        if(!aligned)throw new Error('Select a teacher key photo and align all four marker centers first.');
        resetDetection();const blob=await new Promise(resolve=>aligned.toBlob(resolve,'image/png'));if(!blob)throw new Error('The aligned photo could not be prepared.');
        const payload=new FormData();payload.append('image',blob,'teacher-answer-key.png');payload.append('version',context.version);
        detected=await api(`/papers/${context.id}/teacher-key-scan`,payload);
        const flags=Object.keys(detected.flags).length,notice=get('[data-teacher-detected-status]');
        notice.textContent=`Detected ${detected.answers.length} questions. ${flags} need manual review. Check the sheet preview, then use the detected answers. Nothing has been saved or approved.`;notice.hidden=false;useButton.disabled=false;
    }));
    useButton.addEventListener('click',()=>run(async()=>{
        if(!detected)return;
        if(!(await Swal.fire({icon:'question',title:'Use detected answers?',text:'This replaces the answers currently in the editor. Question scores stay unchanged. Review all flagged questions, save the draft, then approve it.',showCancelButton:true,confirmButtonText:'Use Detected Answers'})).isConfirmed)return;
        onDetected(context,detected,aligned.toDataURL());scanModal.hide();
    }));
}
