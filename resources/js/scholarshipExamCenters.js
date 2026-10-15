export function initExamCenters(form) {
    const section=form.querySelector('[data-exam-centers]');
    if(!section)return;
    const rows=section.querySelector('[data-center-rows]');
    const campusRows=section.querySelector('[data-center-campus-rows]');
    const error=section.querySelector('[data-center-load-error]');
    const year=form.querySelector('#examYear');
    const save=form.querySelector('button[type="submit"]');
    let index=Math.max(-1,...[...rows.querySelectorAll('[data-center-name]')].map(input=>Number(/\[(\d+)\]/.exec(input.name)?.[1]??-1)))+1;
    let request,sequence=0,loading=false,failed=false;
    const sync=()=>{
        const centers=[...rows.querySelectorAll('[data-exam-center-row]')].map(row=>({key:row.querySelector('[data-center-key]').value,name:row.querySelector('[data-center-name]').value.trim()||'Unnamed center'}));
        campusRows.querySelectorAll('[data-campus-center]').forEach(select=>{
            const selected=select.value;
            select.replaceChildren(new Option('Select exam center',''),...centers.map(center=>new Option(center.name,center.key)));
            select.value=centers.some(center=>center.key===selected)?selected:'';
        });
        rows.querySelectorAll('[data-remove-center]').forEach(button=>button.disabled=centers.length<=1);
        section.querySelector('[data-add-center]').disabled=centers.length>=50;
    };
    section.querySelector('[data-add-center]').addEventListener('click',()=>{
        if(rows.children.length>=50)return;
        const template=form.querySelector('[data-center-template]').content.cloneNode(true);
        const next=index++,key=`new-${crypto.randomUUID()}`;
        template.querySelectorAll('*').forEach(element=>{
            for(const attr of ['id','for','name','value'])if(element.hasAttribute(attr))element.setAttribute(attr,element.getAttribute(attr).replaceAll('__INDEX__',String(next)).replaceAll('__KEY__',key));
        });
        rows.append(template);sync();rows.lastElementChild.querySelector('[data-center-name]').focus();
    });
    rows.addEventListener('input',sync);
    rows.addEventListener('click',event=>{
        const button=event.target.closest('[data-remove-center]');
        if(!button||rows.children.length<=1)return;
        button.closest('[data-exam-center-row]').remove();sync();
    });
    const renderCampuses=campuses=>{
        const previous=new Map([...campusRows.querySelectorAll('[data-campus-center]')].map(select=>[select.name,select.value]));
        campusRows.replaceChildren();
        for(const campus of campuses){
            const row=document.createElement('div');row.className='scholarship-center-campus-row';row.dataset.centerCampus=String(campus.id);
            const details=document.createElement('div'),label=document.createElement('label'),count=document.createElement('div'),field=document.createElement('div'),select=document.createElement('select');
            select.id=`centerCampus${campus.id}`;select.name=`center_assignments[${campus.id}]`;select.className='form-select';select.required=true;select.dataset.campusCenter='';
            label.htmlFor=select.id;label.className='fw-semibold mb-0';label.textContent=campus.label;
            count.className='small text-secondary';count.textContent=`${campus.count} ranked student(s)`;
            details.append(label,count);field.append(select);row.append(details,field);campusRows.append(row);
            sync();select.value=previous.get(select.name)||'';
        }
        if(!campuses.length){const empty=document.createElement('p');empty.className='text-secondary small mb-0';empty.textContent='No ranked students in this academic year. Campuses will appear after student ranks are assigned.';campusRows.append(empty);}
    };
    year.addEventListener('change',async()=>{
        request?.abort();request=new AbortController();const current=++sequence;
        error.hidden=true;failed=false;
        if(!year.value){renderCampuses([]);loading=false;save.disabled=false;campusRows.removeAttribute('aria-busy');return;}
        loading=true;save.disabled=true;campusRows.setAttribute('aria-busy','true');
        try{
            const url=new URL(form.dataset.centerCampusesUrl,location.href);url.searchParams.set('academic_year_id',year.value);
            const response=await fetch(url,{signal:request.signal,headers:{Accept:'application/json'}});
            if(!response.ok)throw new Error('load');
            const data=await response.json();if(current!==sequence)return;
            renderCampuses(data.campuses);
        }catch(e){
            if(e.name==='AbortError'||current!==sequence)return;
            failed=true;error.textContent='Unable to load participating campuses. Check your Settings permission for all participating campuses, then select the academic year again or reload the page.';error.hidden=false;
        }finally{
            if(current===sequence){loading=false;save.disabled=failed;campusRows.removeAttribute('aria-busy');}
        }
    });
    form.addEventListener('submit',event=>{if(loading||failed)event.preventDefault();});
    sync();
    if(!year.value)renderCampuses([]);
}
