import Swal from 'sweetalert2';

export function initExamReseating(root){
    const button=root.querySelector('[data-exam-reseating-reset]');if(!button)return;
    let busy=false;
    const request=async(url,body)=>{
        const response=await fetch(url,{method:body?'POST':'GET',credentials:'same-origin',headers:{Accept:'application/json',
            ...(body?{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]')?.content||''}:{})},body:body?JSON.stringify(body):undefined});
        const data=await response.json();
        if(!response.ok||response.redirected)throw new Error(Object.values(data.errors||{}).flat().join('\n')||data.message||'The reset could not be completed.');
        return data;
    };
    button.addEventListener('click',async()=>{
        if(busy||button.disabled||root.getAttribute('aria-busy')==='true')return;
        busy=true;button.disabled=true;
        try{
            const preview=await request(button.dataset.previewUrl),content=document.createElement('div');content.className='text-start';
            const paragraph=(text,className='')=>{const p=document.createElement('p');p.textContent=text;p.className=className;content.append(p);};
            paragraph(preview.scope,'fw-bold');
            paragraph('Entire exam: all campuses, grades, subjects and rooms. Page filters do not limit this reset.');
            const list=document.createElement('ul');
            for(const text of [`${preview.scores} score record(s), including ${preview.manual_scores} manual score(s) and any zero scores`,
                `${preview.scans} student scan(s) and their stored images`,`${preview.reviews} review(s) and ${preview.awards} award selection(s)`,
                `${preview.links} answer-sheet link(s), ${preview.seats} seat assignment(s), and ${preview.room_duties} room duty assignment(s)`]){
                const li=document.createElement('li');li.textContent=text;list.append(li);
            }
            content.append(list);paragraph(`Keep ${preview.students} student candidate(s), their class ranks, ${preview.papers_kept} paper setup(s), and all approved answer keys.`);
            paragraph('This cannot be undone. Old printed student sheets will be invalid. After adding students and confirming new seats, link students and print fresh sheets.','text-danger');
            const reasonLabel=document.createElement('label');reasonLabel.className='form-label';reasonLabel.htmlFor='reseatingResetReason';reasonLabel.textContent='Reason for resetting';
            const reason=document.createElement('textarea');reason.id='reseatingResetReason';reason.className='form-control mb-3';reason.rows=2;reason.minLength=5;reason.maxLength=1000;
            const confirmLabel=document.createElement('label');confirmLabel.className='form-label';confirmLabel.htmlFor='reseatingResetConfirm';confirmLabel.textContent='Type RESET to confirm';
            const confirmation=document.createElement('input');confirmation.id='reseatingResetConfirm';confirmation.className='form-control';confirmation.autocomplete='off';
            content.append(reasonLabel,reason,confirmLabel,confirmation);
            const answer=await Swal.fire({icon:'warning',title:'Reset Exam for Reseating?',html:content,width:640,
                showCancelButton:true,focusCancel:true,confirmButtonText:'Reset Exam',confirmButtonColor:'#d63939',
                preConfirm:()=>{
                    if(reason.value.trim().length<5){Swal.showValidationMessage('Enter a reason of at least five characters.');reason.focus();return false;}
                    if(confirmation.value.trim()!=='RESET'){Swal.showValidationMessage('Type RESET to confirm the full exam reset.');confirmation.focus();return false;}
                    return reason.value.trim();
                }});
            if(!answer.isConfirmed)return;
            const result=await request(button.dataset.resetUrl,{token:preview.token,confirmed:true,confirmation:'RESET',reason:answer.value});
            await Swal.fire({icon:result.image_cleanup_pending?'warning':'success',title:'Ready for New Seating',text:result.message});
            location.assign(result.url);
        }catch(error){await Swal.fire({icon:'error',title:'Exam Reset',text:error.message});}
        finally{busy=false;button.disabled=false;}
    });
}
