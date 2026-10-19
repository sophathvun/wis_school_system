import { renderPagination } from './helpers/pagination.js';
import { createSeatPicker } from './scholarshipSeatPicker.js';
import { initSeatReportScope } from './scholarshipSeatReportScope.js';

export function initSeatPagination(root) {
    const form=root.querySelector('[data-seat-filters]');
    if(!form)return;
    let state=JSON.parse(root.querySelector('[data-seat-filter-state]')?.textContent||'{}');
    let controller,sequence=0,loading=false,timer;
    const year=form.elements.academic_year_id,center=form.elements.seat_center,room=form.querySelector('[name="seat_room[]"]'),student=form.elements.seat_student,search=form.elements.seat_search;
    const pickers=new Map();form?.querySelectorAll('[data-seat-picker]').forEach(select=>pickers.set(select,createSeatPicker(select)));
    year.addEventListener('change',()=>{
        if(!year.value)return;
        clearTimeout(timer);controller?.abort();++sequence;loading=true;
        const url=new URL(form.action,location.href);
        url.search='';url.searchParams.set('tab','seat-plan');url.searchParams.set('academic_year_id',year.value);
        const size=root.querySelector('#scholarshipSeatPageSize')?.value??new URL(location.href).searchParams.get('seat_per_page');
        if(['all','15','25','50','75','100'].includes(size))url.searchParams.set('seat_per_page',size);
        location.assign(url);
    });
    if(!root.querySelector('[data-scholarship-seat-list]'))return;
    const status=document.createElement('p');status.className='small text-danger mt-2';status.setAttribute('role','status');status.hidden=true;
    root.querySelector('[data-scholarship-seat-list]').after(status);
    const render=()=>{
        const pagination=root.querySelector('[data-seat-pagination]');
        renderPagination(JSON.parse(pagination.dataset.seatPagination),'scholarshipSeatsPagination','scholarshipSeatPageSize',load,['all',15,25,50,75,100]);
    };
    const load=async(page,size)=>{
        clearTimeout(timer);controller?.abort();controller=new AbortController();const current=++sequence;loading=true;
        const panel=root.querySelector('[data-scholarship-seat-list]');panel.setAttribute('aria-busy','true');status.hidden=true;
        const url=new URL(location.href);url.searchParams.set('seat_per_page',String(size));
        [...url.searchParams.keys()].forEach(key=>{if(['seat_center','seat_student','seat_search'].includes(key)||/^seat_room(?:\[.*\])?$/.test(key))url.searchParams.delete(key);});
        if(form)new FormData(form).forEach((value,key)=>{if(key==='seat_room[]')url.searchParams.append(key,value);else if(value!==''&&!['tab','exam_id'].includes(key))url.searchParams.set(key,value);});
        if(page>1&&size!=='all')url.searchParams.set('seat_page',String(page));else url.searchParams.delete('seat_page');
        const endpoint=new URL(url);endpoint.searchParams.set('seat_list','1');
        try{
            const response=await fetch(endpoint,{signal:controller.signal,headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'});
            const data=await response.json();
            if(!response.ok||response.redirected)throw new Error(data.errors?Object.values(data.errors).flat().join(' '):'Unable to load seats. Please try again or refresh the page.');
            if(current!==sequence)return;
            const template=document.createElement('template');template.innerHTML=data.html.trim();
            const replacement=template.content.querySelector('[data-scholarship-seat-list]');if(!replacement)throw new Error('Unable to display seats. Please refresh the page.');
            panel.replaceWith(replacement);render();
            if(data.summary){
                const summaryTemplate=document.createElement('template');summaryTemplate.innerHTML=data.summary.trim();
                const summary=summaryTemplate.content.querySelector('[data-seat-summary]');
                if(summary)root.querySelector('[data-seat-summary]')?.replaceWith(summary);
            }
            if(data.options&&data.filters){
                state={filters:data.filters,options:data.options};
                pickers.get(center)?.setOptions(state.options.centers,state.filters.center??'');
                room.disabled=state.filters.center===null;pickers.get(room)?.setOptions(state.options.rooms,state.filters.rooms);
                student.disabled=!state.filters.rooms.length;
                pickers.get(student)?.setOptions(state.options.students,state.filters.student??'');
            }
            const meta=JSON.parse(replacement.querySelector('[data-seat-pagination]').dataset.seatPagination);
            if(meta.current_page>1&&size!=='all')url.searchParams.set('seat_page',String(meta.current_page));else url.searchParams.delete('seat_page');
            url.searchParams.delete('seat_list');history.replaceState(null,'',url);loading=false;
        }catch(error){
            if(error.name==='AbortError'||current!==sequence)return;
            status.textContent=error.message||'Unable to load seats. Please try again or refresh the page.';status.hidden=false;panel.hidden=true;
            root.querySelector('#scholarshipSeatPageSize').value=panel.dataset.pageSize||'15';render();
        }finally{if(current===sequence)root.querySelector('[data-scholarship-seat-list]')?.setAttribute('aria-busy','false');}
    };
    const refresh=()=>load(1,root.querySelector('#scholarshipSeatPageSize').value);
    form?.addEventListener('submit',event=>{event.preventDefault();refresh();});
    center?.addEventListener('change',()=>{room.disabled=center.value==='';pickers.get(room).setOptions([]);student.disabled=true;pickers.get(student).setOptions([]);refresh();});
    room?.addEventListener('change',()=>{student.disabled=true;pickers.get(student).setOptions([]);refresh();});
    student?.addEventListener('change',refresh);
    search?.addEventListener('input',()=>{loading=true;clearTimeout(timer);timer=setTimeout(refresh,250);});
    root.querySelector('[data-clear-seat-filters]')?.addEventListener('click',()=>{center.value='';room.disabled=true;student.disabled=true;pickers.get(center).refresh();pickers.get(room).setOptions([]);pickers.get(student).setOptions([]);search.value='';refresh();});
    initSeatReportScope(root,()=>({...state,loading}));render();
}
