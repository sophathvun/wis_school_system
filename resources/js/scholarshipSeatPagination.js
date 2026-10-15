import { renderPagination } from './helpers/pagination.js';

export function initSeatPagination(root) {
    if(!root.querySelector('[data-scholarship-seat-list]'))return;
    let controller,sequence=0;
    const status=document.createElement('p');status.className='small text-danger mt-2';status.setAttribute('role','status');status.hidden=true;
    root.querySelector('[data-scholarship-seat-list]').after(status);
    const render=()=>{
        const pagination=root.querySelector('[data-seat-pagination]');
        renderPagination(JSON.parse(pagination.dataset.seatPagination),'scholarshipSeatsPagination','scholarshipSeatPageSize',load,['all',15,25,50,75,100]);
    };
    const load=async(page,size)=>{
        controller?.abort();controller=new AbortController();const current=++sequence;
        const panel=root.querySelector('[data-scholarship-seat-list]');panel.setAttribute('aria-busy','true');status.hidden=true;
        const url=new URL(location.href);url.searchParams.set('seat_per_page',String(size));
        if(page>1&&size!=='all')url.searchParams.set('seat_page',String(page));else url.searchParams.delete('seat_page');
        const endpoint=new URL(url);endpoint.searchParams.set('seat_list','1');
        try{
            const response=await fetch(endpoint,{signal:controller.signal,headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'});
            if(!response.ok||response.redirected)throw new Error('load');
            const data=await response.json();if(current!==sequence)return;
            const template=document.createElement('template');template.innerHTML=data.html.trim();
            const replacement=template.content.querySelector('[data-scholarship-seat-list]');
            if(!replacement)throw new Error('markup');
            panel.replaceWith(replacement);render();
            const meta=JSON.parse(replacement.querySelector('[data-seat-pagination]').dataset.seatPagination);
            if(meta.current_page>1&&size!=='all')url.searchParams.set('seat_page',String(meta.current_page));else url.searchParams.delete('seat_page');
            url.searchParams.delete('seat_list');history.replaceState(null,'',url);
        }catch(error){
            if(error.name==='AbortError'||current!==sequence)return;
            status.textContent='Unable to load seats. Please try again or refresh the page.';status.hidden=false;
            root.querySelector('#scholarshipSeatPageSize').value=root.querySelector('[data-scholarship-seat-list]').dataset.pageSize||'15';
            render();
        }finally{if(current===sequence)root.querySelector('[data-scholarship-seat-list]')?.setAttribute('aria-busy','false');}
    };
    render();
}
