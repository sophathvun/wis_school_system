import Swal from 'sweetalert2';
import { renderPagination } from './helpers/pagination.js';

export function initScanHistory({root,api,run,selected}){
    const find=s=>root.querySelector(s);let requestId=0,controller;
    const render=()=>{
        const list=find('[data-answer-scan-history-list]');if(!list)return;
        const page=JSON.parse(list.dataset.pagination);document.getElementById('answerScansPerPage').value=page.per_page;
        renderPagination(page,'answerScansPagination','answerScansPerPage',refresh);
    };
    const refresh=async(page=1,size=document.getElementById('answerScansPerPage').value)=>{
        const id=++requestId;controller?.abort();controller=new AbortController();
        const list=find('[data-answer-scan-history-list]');list.setAttribute('aria-busy','true');
        const url=new URL(location.href);url.searchParams.set('scan_page',page);url.searchParams.set('scan_per_page',size);
        const endpoint=new URL(url);endpoint.searchParams.set('scan_history','1');
        try{
            const response=await fetch(endpoint,{headers:{Accept:'application/json'},credentials:'same-origin',signal:controller.signal});
            const result=await response.json();if(!response.ok||response.redirected)throw new Error(result.message||'Unable to load Scan History.');
            if(id!==requestId)return;
            const template=document.createElement('template');template.innerHTML=result.html;const next=template.content.querySelector('[data-answer-scan-history-list]');
            if(!next)throw new Error('Unable to display Scan History. Refresh and try again.');
            list.replaceWith(next);const metadata=JSON.parse(next.dataset.pagination);url.searchParams.set('scan_page',metadata.current_page);
            url.searchParams.delete('scan_history');history.replaceState(null,'',url);render();
        }catch(error){if(error.name!=='AbortError'&&id===requestId){render();await Swal.fire({icon:'error',title:'Scan History',text:error.message});}}
        finally{if(id===requestId)find('[data-answer-scan-history-list]')?.setAttribute('aria-busy','false');}
    };
    render();
    find('[data-answer-accept-all]')?.addEventListener('click',()=>run(async()=>{
        const paper=selected();if(!paper)return;
        const filters=new URLSearchParams();for(const key of ['center','room']){const value=new URL(location.href).searchParams.get(key);if(value!==null&&value!=='')filters.set(key,value);}
        const preview=await api(`/papers/${paper.id}/accept-all-preview?${filters}`),content=document.createElement('div');content.className='text-start';
        const paragraph=(value,style='')=>{const p=document.createElement('p');p.textContent=value;p.className=style;content.append(p);};
        paragraph(preview.scope,'fw-semibold');paragraph(`${preview.eligible} pending scan(s) will be accepted across all pages. ${preview.flagged} include flagged answers.`);
        for(const [reason,count]of Object.entries(preview.skipped))paragraph(`${count} skipped: ${reason}.`,'small text-secondary');
        if(!preview.eligible){await Swal.fire({icon:'info',title:'No Scans Ready to Accept',html:content});return;}
        paragraph('Detected answers will be saved as draft scores. Existing scores are kept. Final review still takes place in Scoring.');
        let acknowledgement;
        if(preview.flagged){
            paragraph('Blank, unclear and multiple answers stay unanswered and receive 0 points. Review these sheets individually first if you need to correct their answers.','text-danger');
            const label=document.createElement('label');label.className='d-flex align-items-start gap-2';
            acknowledgement=document.createElement('input');acknowledgement.type='checkbox';acknowledgement.className='form-check-input';acknowledgement.id='answerAcceptFlagged';
            const text=document.createElement('span');text.textContent='I confirm accepting the flagged answers as detected.';label.append(acknowledgement,text);content.append(label);
        }
        const answer=await Swal.fire({icon:'warning',title:'Accept All Pending Scans?',html:content,showCancelButton:true,focusCancel:true,
            confirmButtonText:`Accept ${preview.eligible} Scan(s)`,preConfirm:()=>{
                if(acknowledgement&&!acknowledgement.checked){Swal.showValidationMessage('Confirm accepting the flagged answers.');return false;}return true;
            }});
        if(!answer.isConfirmed)return;
        const result=await api(`/papers/${paper.id}/accept-all`,{token:preview.token,confirmed:true,accept_flagged:!!acknowledgement?.checked});
        await Swal.fire({icon:'success',title:'Scans Accepted',text:result.message});await refresh();
    }));
    return {refresh};
}
