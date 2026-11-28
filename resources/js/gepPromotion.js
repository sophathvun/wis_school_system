import Swal from 'sweetalert2';
import {createSeatPicker} from './scholarshipSeatPicker.js';

const page=document.querySelector('.gep-promotion');
if(page){
    page.querySelectorAll('select').forEach(select=>createSeatPicker(select));
    const filters=page.querySelector('[data-source-filters]');
    filters.addEventListener('change',event=>{
        if(!event.target.matches('select'))return;
        const chain=['source_term','campus_id','level_id','class_id','group_id'];
        const parent=chain.indexOf(event.target.name);
        if(parent>=0)chain.slice(parent+1).forEach(name=>{filters.elements[name].value='';});
        HTMLFormElement.prototype.submit.call(filters);
    });
    const form=page.querySelector('[data-promotion-form]');
    let checks=[...form.querySelectorAll('[name="enrollments[]"]')];
    const all=form.querySelector('[data-select-all]');
    const selected=()=>checks.filter(check=>check.checked);
    const update=()=>{
        const count=selected().length;
        form.querySelectorAll('[data-selection-count]').forEach(el=>el.textContent=count);
        all.checked=checks.length>0 && count===checks.length;all.indeterminate=count>0 && count<checks.length;
        checks.forEach(check=>check.closest('tr').classList.toggle('is-selected',check.checked));
    };
    all.addEventListener('change',()=>{checks.filter(check=>!check.disabled).forEach(check=>check.checked=all.checked);update();});
    form.addEventListener('change',event=>{if(event.target.matches('[name="enrollments[]"]'))update();});update();
    const search=filters.elements.search;
    const menu=page.querySelector('#promotionSearchResults');
    const status=page.querySelector('[data-search-status]');
    const spinner=page.querySelector('.promotion-search-spinner');
    let timer,request,version=0,composing=false;
    const closeSearch=()=>{menu.hidden=true;search.setAttribute('aria-expanded','false');search.removeAttribute('aria-activedescendant');};
    const showSearch=()=>{menu.hidden=false;search.setAttribute('aria-expanded','true');};
    const message=text=>{menu.replaceChildren();const item=document.createElement('div');item.className='promotion-search-message';item.textContent=text;menu.append(item);showSearch();};
    const loadSearch=async(showSuggestions=true)=>{
        clearTimeout(timer);request?.abort();request=new AbortController();const current=++version;
        const params=new URLSearchParams(new FormData(filters));const url=new URL(filters.action);url.search=params.toString();
        spinner.hidden=false;search.setAttribute('aria-busy','true');status.textContent='Searching students…';
        try{
            const response=await fetch(url,{headers:{Accept:'application/json'},signal:request.signal});
            if(!response.ok || response.redirected)throw new Error('Search could not load. Please refresh or try again.');
            const data=await response.json();if(current!==version)return;
            form.querySelector('tbody').innerHTML=data.rows;
            page.querySelector('[data-promotion-pagination]').innerHTML=data.pagination;
            page.querySelector('[data-match-count]').textContent=Number(data.total).toLocaleString();
            checks=[...form.querySelectorAll('[name="enrollments[]"]')];all.disabled=!checks.length || checks.every(check=>check.disabled);update();
            status.textContent=`${Number(data.total).toLocaleString()} matching students`;
            history.replaceState(null,'',url);
            menu.replaceChildren();
            if(!showSuggestions || !search.value.trim() || document.activeElement!==search){closeSearch();return;}
            if(!data.students.length){message(filters.elements.source_term.value?'No matching students. Try another name or ID.':'Choose a source year / term to search students.');return;}
            data.students.forEach((student,index)=>{
                const option=document.createElement('button');option.type='button';option.className='promotion-search-student';option.id=`promotionStudentOption${index}`;option.setAttribute('role','option');option.setAttribute('aria-selected','false');
                const avatar=document.createElement(student.photo?'img':'span');avatar.className='promotion-search-avatar';
                if(student.photo){avatar.src=student.photo;avatar.alt='';}else{avatar.textContent=student.name.trim().charAt(0);}
                const details=document.createElement('span');const name=document.createElement('strong');name.textContent=student.name;
                const id=document.createElement('small');id.textContent=[student.id,student.name_kh].filter(Boolean).join(' · ');
                const context=document.createElement('small');context.textContent=student.detail;details.append(name,id,context);
                option.append(avatar,details);option.addEventListener('click',()=>{search.value=student.id;closeSearch();search.focus();loadSearch(false);});menu.append(option);
            });showSearch();
        }catch(error){if(error.name!=='AbortError' && current===version){status.textContent=error.message;if(document.activeElement===search)message(error.message);}}
        finally{if(current===version){spinner.hidden=true;search.removeAttribute('aria-busy');}}
    };
    const queueSearch=()=>{request?.abort();version++;closeSearch();clearTimeout(timer);timer=setTimeout(()=>loadSearch(),300);};
    search.addEventListener('input',()=>{if(!composing)queueSearch();});
    search.addEventListener('compositionstart',()=>{composing=true;clearTimeout(timer);request?.abort();version++;});
    search.addEventListener('compositionend',()=>{composing=false;queueSearch();});
    search.addEventListener('focus',()=>{if(search.value.trim() && menu.childElementCount)showSearch();});
    filters.addEventListener('submit',event=>{event.preventDefault();if(!composing)loadSearch(false);});
    search.addEventListener('keydown',event=>{
        if(event.isComposing || composing)return;
        if(event.key==='Escape'){closeSearch();return;}
        const options=[...menu.querySelectorAll('[role="option"]')];
        if(['ArrowDown','ArrowUp'].includes(event.key) && options.length){
            event.preventDefault();showSearch();const previous=options.findIndex(option=>option.id===search.getAttribute('aria-activedescendant'));
            const next=previous<0?(event.key==='ArrowDown'?0:options.length-1):(previous+(event.key==='ArrowDown'?1:-1)+options.length)%options.length;
            options.forEach((option,index)=>option.setAttribute('aria-selected',String(index===next)));search.setAttribute('aria-activedescendant',options[next].id);options[next].scrollIntoView({block:'nearest'});
        }else if(event.key==='Enter' && !menu.hidden){const active=options.find(option=>option.id===search.getAttribute('aria-activedescendant'));if(active){event.preventDefault();active.click();}}
    });
    document.addEventListener('pointerdown',event=>{if(!event.target.closest('.promotion-autocomplete'))closeSearch();});
    search.addEventListener('blur',()=>{setTimeout(()=>{if(!menu.contains(document.activeElement))closeSearch();},150);});
    // Custom searchable selects are hidden; validate them explicitly before opening review.
    form.noValidate=true;
    form.addEventListener('submit',async event=>{
        event.preventDefault();
        if(!selected().length){await Swal.fire({icon:'info',title:'Select students first',text:'Tick the students you want to promote.'});return;}
        const invalid=[...form.querySelectorAll('[required]')].find(field=>!field.checkValidity());
        if(invalid){await Swal.fire({icon:'info',title:'Complete the destination',text:`Please select or enter ${invalid.closest('.scholarship-field').querySelector('label').textContent.replace('*','').trim()}.`});(invalid.hidden?document.getElementById(invalid.id+'Toggle'):invalid)?.focus();return;}
        const content=document.createElement('div');content.className='promotion-review';
        const add=(label,value)=>{const line=document.createElement('p');const strong=document.createElement('strong');strong.textContent=label+': ';line.append(strong,document.createTextNode(value));content.append(line);};
        add('Students',String(selected().length));
        form.querySelectorAll('select').forEach(select=>add(select.closest('.scholarship-field').querySelector('label').textContent.replace('*','').trim(),select.selectedOptions[0].textContent));
        add('Enrollment date',form.elements.enrolled_on.value);
        const active=form.elements.term_assignment_id.selectedOptions[0].dataset.state==='started';
        add('Result',active?'Destination Active · source Active enrollments become Completed.':'Destination Pending · source status stays as saved.');
        const list=document.createElement('p');list.textContent=selected().map(check=>check.dataset.studentName).join(', ');content.append(list);
        const result=await Swal.fire({title:'Review student promotion',html:content,icon:'question',showCancelButton:true,confirmButtonText:`Promote ${selected().length} students`,cancelButtonText:'Back to selection',confirmButtonColor:'#4169cf',width:650});
        if(!result.isConfirmed)return;
        const button=form.querySelector('[data-review]');button.disabled=true;button.textContent='Saving promotion…';form.setAttribute('aria-busy','true');
        Swal.fire({title:'Promoting students',text:'Saving enrollments and activity history. Please wait.',allowOutsideClick:false,allowEscapeKey:false,didOpen:()=>Swal.showLoading()});
        HTMLFormElement.prototype.submit.call(form);
    });
    if(page.dataset.success)Swal.fire({icon:'success',title:'Promotion completed',text:page.dataset.success,confirmButtonText:'Done'});
    window.addEventListener('pageshow',()=>{const button=form.querySelector('[data-review]');if(form.hasAttribute('aria-busy')){form.removeAttribute('aria-busy');button.disabled=false;button.textContent='Review promotion';Swal.close();}});
}
