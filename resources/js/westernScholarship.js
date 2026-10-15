import { Modal, Tooltip } from 'bootstrap';
import Swal from 'sweetalert2';
import { renderPagination } from './helpers/pagination.js';
import { initRankPrintSettings } from './scholarshipPrintSettings.js';
import { initExamCenters } from './scholarshipExamCenters.js';
import { initSeatPagination } from './scholarshipSeatPagination.js';
import { initPremiumDatePicker } from './premiumDatePicker.js';

const root = document.querySelector('[data-scholarship-page]');
if (root) {
    if(root.dataset.settingsSuccess)Swal.fire({toast:true,position:'top-end',icon:'success',title:root.dataset.settingsSuccess,showConfirmButton:false,showCloseButton:true,timer:3500,timerProgressBar:true});
    initRankPrintSettings(root);
    initSeatPagination(root);
    const photoModalElement=root.querySelector('#scholarshipPhotoModal');
    if(photoModalElement){
        const modal=Modal.getOrCreateInstance(photoModalElement);
        const image=photoModalElement.querySelector('[data-photo-image]');
        const viewport=photoModalElement.querySelector('.scholarship-photo-viewport');
        const status=photoModalElement.querySelector('[data-photo-status]');
        const download=photoModalElement.querySelector('[data-photo-download]');
        const zoomIn=photoModalElement.querySelector('[data-photo-zoom-in]');
        const zoomOut=photoModalElement.querySelector('[data-photo-zoom-out]');
        const reset=photoModalElement.querySelector('[data-photo-reset]');
        let zoom=1,loaded=false,trigger;
        const renderPhoto=()=>{
            zoomIn.disabled=!loaded || zoom>=3;
            zoomOut.disabled=!loaded || zoom<=.5;
            reset.disabled=!loaded;
            photoModalElement.querySelector('[data-photo-zoom]').textContent=`${Math.round(zoom*100)}%`;
            if(!loaded || !viewport.clientWidth)return;
            // Fit against the screen rather than the dialog's changing width, so zoom remains stable.
            // Reserve space for the stage padding, borders, and scrollbars when the image is enlarged.
            const maxWidth=photoModalElement.clientWidth;
            const fit=Math.min(1,Math.max(1,maxWidth-40)/image.naturalWidth,Math.max(1,viewport.offsetHeight-40)/image.naturalHeight);
            const photoWidth=image.naturalWidth*fit*zoom;
            photoModalElement.style.setProperty('--scholarship-photo-width',`${Math.min(maxWidth,Math.max(Math.min(320,maxWidth),photoWidth+26))}px`);
            image.style.width=`${photoWidth}px`;
            image.style.height=`${image.naturalHeight*fit*zoom}px`;
        };
        const setZoom=value=>{zoom=Math.max(.5,Math.min(3,value));renderPhoto();};
        zoomIn.addEventListener('click',()=>setZoom(zoom+.25));
        zoomOut.addEventListener('click',()=>setZoom(zoom-.25));
        reset.addEventListener('click',()=>{setZoom(1);viewport.scrollTo(0,0);});
        image.addEventListener('load',()=>{
            loaded=true;image.hidden=false;status.hidden=true;
            download.href=image.src;download.classList.remove('disabled');download.removeAttribute('aria-disabled');
            renderPhoto();
        });
        image.addEventListener('error',()=>{
            loaded=false;image.hidden=true;status.hidden=false;status.textContent='Unable to load this photo. Close and try again.';
            download.removeAttribute('href');download.classList.add('disabled');download.setAttribute('aria-disabled','true');renderPhoto();
        });
        root.addEventListener('click',event=>{
            const button=event.target.closest('[data-scholarship-photo]');
            if(!button)return;
            trigger=button;zoom=1;loaded=false;image.hidden=true;
            status.hidden=false;status.textContent='Loading photo…';
            download.removeAttribute('href');download.classList.add('disabled');download.setAttribute('aria-disabled','true');
            const name=button.dataset.photoName || 'Student Photo';
            const extension=new URL(button.dataset.scholarshipPhoto,location.href).pathname.match(/\.(jpe?g|png|webp|gif|avif|svg)$/i)?.[0] || '';
            download.download=`${name.replace(/[^\p{L}\p{N}_-]+/gu,'_')}${extension}`;
            photoModalElement.querySelector('#scholarshipPhotoTitle').textContent=name;
            image.alt=`Photo of ${name}`;renderPhoto();image.src=button.dataset.scholarshipPhoto;
            viewport.scrollTo(0,0);modal.show();
        });
        photoModalElement.addEventListener('shown.bs.modal',renderPhoto);
        photoModalElement.addEventListener('hidden.bs.modal',()=>{
            loaded=false;image.removeAttribute('src');image.hidden=true;download.removeAttribute('href');
            const returnTarget=trigger?.isConnected?trigger:[...root.querySelectorAll('[data-scholarship-photo]')].find(button=>button.dataset.scholarshipPhoto===trigger?.dataset.scholarshipPhoto);
            returnTarget?.focus();
        });
        new ResizeObserver(renderPhoto).observe(viewport);
        window.addEventListener('resize',renderPhoto);
    }
    const tabsCard=root.querySelector('.scholarship-navigation');
    const tabsToggle=tabsCard?.querySelector('.scholarship-tabs-toggle');
    if(tabsToggle) {
        const isMobile=()=>window.innerWidth<992;
        const tooltips=[...tabsCard.querySelectorAll('[data-bs-toggle="tooltip"]')].map(element=>{
            const tooltip=Tooltip.getOrCreateInstance(element,{container:'body',placement:'right',customClass:'scholarship-tabs-tooltip',delay:{show:200,hide:0},
                title:()=>element===tabsToggle?element.getAttribute('aria-label'):element.dataset.scholarshipTabLabel});
            element.addEventListener('click',()=>tooltip.hide());return tooltip;
        });
        const syncTabs=()=>{
            const collapsed=isMobile()?!tabsCard.classList.contains('is-open'):root.classList.contains('scholarship-tabs-collapsed');
            tooltips.forEach(tooltip=>{tooltip.hide();collapsed?tooltip.enable():tooltip.disable();});
            tabsToggle.setAttribute('aria-expanded',String(!collapsed));
            const label=collapsed?'Expand scholarship tabs':'Collapse scholarship tabs';
            tabsToggle.setAttribute('aria-label',label);tabsToggle.setAttribute('data-bs-title',label);
            tabsToggle.querySelector('i').className=collapsed?'ti ti-layout-sidebar-left-expand':'ti ti-layout-sidebar-left-collapse';
        };
        const closeMobileTabs=()=>{tabsCard.classList.remove('is-open');syncTabs();};
        // Each page opens collapsed, including after following another scholarship tab.
        syncTabs();
        tabsToggle.addEventListener('click',event=>{
            event.stopPropagation();
            if(isMobile())tabsCard.classList.toggle('is-open');
            else root.classList.toggle('scholarship-tabs-collapsed');
            syncTabs();
        });
        tabsCard.querySelector('nav')?.addEventListener('click',event=>event.stopPropagation());
        document.addEventListener('click',()=>{if(isMobile())closeMobileTabs();});
        window.addEventListener('resize',closeMobileTabs);
    }
    root.querySelector('[data-exam-selector] select')?.addEventListener('change', event => event.target.form.submit());
    root.addEventListener('submit',event=>{
        const confirmedForm=event.target.closest('[data-scholarship-confirm]');
        if(confirmedForm && !window.confirm(confirmedForm.dataset.scholarshipConfirm))event.preventDefault();
    });
    const settings=root.querySelector('[data-exam-settings]');
    if (settings) {
        settings.querySelectorAll('[data-premium-date-picker]').forEach(initPremiumDatePicker);
        initExamCenters(settings);
        const nextIndex=type=>Math.max(-1,...[...settings.querySelectorAll(`[data-${type}-rows] input`)].map(input=>Number(/\[(\d+)\]/.exec(input.name)?.[1]??-1)))+1;
        let nextSubject=nextIndex('subject');
        settings.querySelector('[data-add-subject]')?.addEventListener('click',()=>settings.querySelector('[data-subject-rows]').insertAdjacentHTML('beforeend',settings.querySelector('[data-subject-template]').innerHTML.replaceAll('__INDEX__',String(nextSubject++))));
        settings.addEventListener('click',event=>event.target.closest('[data-remove-config-row]')?.closest('tr').remove());
    }
    const committeeForm=root.querySelector('[data-committee-form]');
    if(committeeForm){
        const center=committeeForm.querySelector('#committeeCenter'),grade=committeeForm.querySelector('#committeeGrade'),room=committeeForm.querySelector('#committeeRoom'),role=committeeForm.querySelector('#committeeRole');
        const rooms=JSON.parse(committeeForm.dataset.committeeRooms);
        const refreshRooms=()=>{
            const final=role.value==='final-approver';
            center.disabled=final||center.options.length===1;grade.disabled=final;
            if(final){center.value='';grade.value='';}
            room.disabled=final||(center.options.length>1&&!center.value);
            room.replaceChildren(new Option(room.disabled&&!final?'Select a center first':'All rooms',''));
            if(!room.disabled)rooms.filter(item=>(center.value?String(item.center)===center.value:item.center===null)&&(!grade.value||String(item.grade)===grade.value)).sort((a,b)=>a.room-b.room).forEach(item=>room.add(new Option(String(item.room),String(item.room))));
        };
        [center,grade,role].forEach(select=>select.addEventListener('change',refreshRooms));refreshRooms();
    }
    const form = root.querySelector('[data-scholarship-rank-form]');
    const controls = new Map();
    const closeAll = () => controls.forEach(control => control.close());
    root.querySelectorAll('[data-scholarship-select]').forEach((select, index) => {
        const field = select.closest('.scholarship-field');
        const label = field.querySelector('label');
        const button = document.createElement('button');
        button.type = 'button'; button.id = `${select.id}Toggle`;
        button.className = 'form-select scholarship-select-toggle';
        button.setAttribute('aria-haspopup', 'listbox'); button.setAttribute('aria-expanded', 'false');
        label.htmlFor = button.id;
        const menu = document.createElement('div');
        menu.className = 'scholarship-select-menu'; menu.hidden = true;
        const search = document.createElement('input');
        search.type = 'search'; search.className = 'form-control'; search.placeholder = 'Search…';
        search.setAttribute('aria-label', `Search ${label.textContent.replace('*', '').trim()}`);
        search.dataset.searchNormalized = 'true';
        const results = document.createElement('div');
        results.id = `scholarshipOptions${index}`; results.className = 'scholarship-select-results';
        results.setAttribute('role', 'listbox'); results.setAttribute('aria-label', label.textContent.replace('*', '').trim());
        button.setAttribute('aria-controls', results.id);
        menu.append(search, results); field.append(button, menu);
        select.hidden = true;
        // Keep native validation on the real control while directing focus to its visible button.
        select.addEventListener('invalid', event => { event.preventDefault(); button.focus(); button.classList.add('is-invalid'); });
        const close = () => { menu.hidden = true; field.classList.remove('is-open'); button.setAttribute('aria-expanded', 'false'); };
        const refresh = () => {
            button.textContent = select.selectedOptions[0]?.textContent || 'Select…';
            button.disabled = select.disabled;
            results.replaceChildren();
            const term = search.value.trim().toLowerCase();
            [...select.options].filter(option => !option.disabled && (select.hasAttribute('data-remote-students') || option.text.toLowerCase().includes(term))).forEach(option => {
                const item = document.createElement('button'); item.type = 'button';
                item.className = 'scholarship-select-option'; item.textContent = option.text;
                item.setAttribute('role', 'option'); item.setAttribute('aria-selected', String(option.selected));
                item.addEventListener('click', () => { select.value = option.value; button.classList.remove('is-invalid'); close(); refresh(); select.dispatchEvent(new Event('change', { bubbles: true })); button.focus(); });
                results.append(item);
            });
            if (!results.children.length) { const empty = document.createElement('p'); empty.className = 'text-secondary p-2 mb-0'; empty.textContent = 'No matching options.'; results.append(empty); }
        };
        button.addEventListener('click', () => {
            const opening = menu.hidden; closeAll();
            if (!opening) return;
            search.value = ''; refresh(); menu.hidden = false; field.classList.add('is-open'); button.setAttribute('aria-expanded', 'true');
            if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) search.focus();
            if (select.hasAttribute('data-remote-students')) search.dispatchEvent(new Event('input'));
        });
        search.addEventListener('input', () => { if (!select.hasAttribute('data-remote-students')) refresh(); });
        button.addEventListener('keydown', event => {
            if (event.key === 'ArrowDown') { event.preventDefault(); if (menu.hidden) button.click(); results.querySelector('button')?.focus(); }
        });
        menu.addEventListener('keydown', event => {
            if (event.key === 'Escape') { event.preventDefault(); close(); button.focus(); }
            if (['ArrowDown', 'ArrowUp'].includes(event.key)) {
                event.preventDefault(); const options = [...results.querySelectorAll('button')];
                const current = options.indexOf(document.activeElement); options[(current + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length]?.focus();
            }
        });
        controls.set(select, { refresh, close, search, button }); refresh();
    });
    root.querySelectorAll('[data-award-form]').forEach(awardForm => {
        const selects=[...awardForm.querySelectorAll('[data-award-winner]')];
        const update=()=>{
            const used=new Set();
            selects.forEach(select=>{
                [...select.options].forEach(option=>option.disabled=Boolean(option.value && used.has(option.value)));
                const available=[...select.options].filter(option=>option.value && !used.has(option.value));
                const highest=available.length?Math.max(...available.map(option=>Number(option.dataset.score))):null;
                const ties=available.filter(option=>Number(option.dataset.score)===highest).length;
                const guidance=select.closest('[data-award-slot]').querySelector('[data-award-guidance]');
                guidance.textContent=highest===null?'No eligible students remain.':`Highest remaining score: ${highest}${ties>1?` — ${ties} students tied; choose one.`:'.'}`;
                if(select.value && Number(select.selectedOptions[0]?.dataset.score)<highest)guidance.textContent+=' Enter an exception reason for this lower score.';
                if(select.value && used.has(select.value)) guidance.textContent='This student already has an earlier award. Choose another student.';
                controls.get(select)?.refresh();
                if(select.value)used.add(select.value);
            });
        };
        awardForm.addEventListener('change',update); update();
    });
    document.addEventListener('click', event => { if (!event.target.closest('.scholarship-field')) closeAll(); });
    if (form) {
        form.noValidate=true;
        const filterCard=form.closest('.scholarship-rank-card');
        const filterToggle=filterCard.querySelector('[data-rank-filters-toggle]');
        const filterBody=filterCard.querySelector('#scholarshipRankFilters');
        const phoneLayout=window.matchMedia('(max-width: 767.98px)');
        const setFiltersExpanded=expanded=>{
            if(!expanded){closeAll();if(filterBody.contains(document.activeElement))filterToggle.focus();}
            filterBody.hidden=!expanded;
            filterToggle.setAttribute('aria-expanded',String(expanded));
            filterToggle.setAttribute('aria-label',expanded?'Collapse rank filters':'Expand rank filters');
            filterToggle.querySelector('i').className=expanded?'ti ti-chevron-up':'ti ti-chevron-down';
            filterCard.dataset.rankFiltersReady='true';
        };
        setFiltersExpanded(!phoneLayout.matches);
        filterToggle.addEventListener('click',()=>setFiltersExpanded(filterBody.hidden));
        phoneLayout.addEventListener('change',()=>setFiltersExpanded(!phoneLayout.matches));
        const year=form.elements.academic_year_id, campus=form.elements.campus_id, grade=form.elements.grade_class;
        const student=form.elements.enrollment_id, rank=form.elements.rank, average=form.elements.eoy_average, studentControl=controls.get(student);
        const status=root.querySelector('[data-scholarship-search-status]'), listStatus=root.querySelector('[data-scholarship-list-status]');
        let studentAbort, filterAbort, timer, studentSequence=0, filterSequence=0, loading=false, filterFailed=false, saving=false;
        let locked=form.dataset.ranksLocked==='true';
        const setOptions=(select,items,placeholder,value='')=>{
            if(!select)return;
            select.replaceChildren(new Option(placeholder,''));
            items.forEach(item=>{
                const option=new Option(item.label,String(item.id));
                if('can_assign' in item)option.dataset.canAssign=String(item.can_assign);
                select.append(option);
            });
            select.value=String(value??'');if(select.selectedIndex<0)select.value='';
            controls.get(select)?.refresh();
        };
        const updateState=()=>{
            year.disabled=saving;
            campus.disabled=loading || saving || !year.value;
            grade.disabled=loading || saving || !campus.value;
            student.disabled=loading || saving || !campus.value || !grade.value;
            const canAssign=campus.selectedOptions[0]?.dataset.canAssign==='true';
            rank.disabled=loading || saving || filterFailed || locked || Boolean(campus.value && !canAssign);
            average.disabled=rank.disabled;
            form.querySelector('[type="submit"]').disabled=rank.disabled;
            root.querySelector('[data-scholarship-ranks-locked]').hidden=loading || !locked;
            root.querySelector('[data-scholarship-rank-permission]').hidden=loading || !campus.value || canAssign;
            [year,campus,grade,student].forEach(select=>controls.get(select)?.refresh());
        };
        const filterUrl=(page=null)=>{
            const url=new URL(window.location.href);
            ['academic_year_id','campus_id','grade_class','grade_id','class_id','enrollment_id','exam_id','page','new_exam','per_page'].forEach(key=>url.searchParams.delete(key));
            ['academic_year_id','campus_id','grade_class','enrollment_id','per_page'].forEach(key=>{if(form.elements[key]?.value)url.searchParams.set(key,form.elements[key].value);});
            url.searchParams.set('tab','assign-rank');
            if(page>1 && form.elements.per_page.value!=='all')url.searchParams.set('page',String(page));
            return url;
        };
        const syncLinks=url=>{
            history.replaceState(null,'',url);
            root.querySelectorAll('.scholarship-navigation a').forEach(link=>{
                const target=new URL(url);target.searchParams.set('tab',new URL(link.href).searchParams.get('tab'));link.href=target.href;
            });
        };
        const cancelStudents=()=>{clearTimeout(timer);studentAbort?.abort();studentSequence++;};
        const clearStudent=()=>{
            cancelStudents();setOptions(student,[],'Select student');rank.value='';average.value='';studentControl.search.value='';form.elements.rank_id.value='';
            ['enrollment_id','rank','eoy_average'].forEach(clearFieldError);
        };
        const fields={academic_year_id:'Academic Year',campus_id:'Campus',grade_class:'Grade',enrollment_id:'Student',rank:'Class Rank',eoy_average:'EOY Avg.'};
        const clearFieldError=field=>{
            const input=form.elements[field];if(!input)return;
            [input,controls.get(input)?.button].filter(Boolean).forEach(element=>{element.classList.remove('is-invalid');element.removeAttribute('aria-invalid');element.removeAttribute('aria-describedby');});
            input.closest('.scholarship-field')?.querySelector('[data-rank-error]')?.remove();
        };
        const showErrors=async errors=>{
            Object.keys(fields).forEach(clearFieldError);
            setFiltersExpanded(true);closeAll();
            let first;
            const messages=[];
            for(const [field,values]of Object.entries(errors)){
                const text=[].concat(values).join(' ');messages.push(text);
                const input=form.elements[field];if(!input)continue;
                const visible=controls.get(input)?.button||input;
                const feedback=document.createElement('div');feedback.id=`${input.id}Error`;feedback.className='invalid-feedback d-block';feedback.dataset.rankError='';feedback.setAttribute('role','alert');feedback.textContent=text;
                input.closest('.scholarship-field')?.append(feedback);
                [input,visible].forEach(element=>{element.classList.add('is-invalid');element.setAttribute('aria-invalid','true');element.setAttribute('aria-describedby',feedback.id);});
                if(!first && !visible.disabled)first=visible;
            }
            await Swal.fire({icon:'warning',title:'Please check the information',text:[...new Set(messages)].join('\n'),confirmButtonText:'OK'});
            first?.focus();first?.scrollIntoView({behavior:'smooth',block:'center'});
        };
        Object.keys(fields).forEach(field=>{
            form.elements[field]?.addEventListener('input',()=>clearFieldError(field));
            form.elements[field]?.addEventListener('change',()=>clearFieldError(field));
        });
        form.addEventListener('submit',async event=>{
            event.preventDefault();if(saving || loading || filterFailed || locked)return;
            const errors={};
            for(const [field,label]of Object.entries(fields))if(!String(form.elements[field].value).trim())errors[field]=[`Please ${field==='eoy_average'?'enter':'select'} ${label}.`];
            if(!errors.eoy_average && (!average.validity.valid || Number(average.value)<0 || Number(average.value)>100))errors.eoy_average=['Enter EOY Avg. from 0 to 100 with at most two decimal places.'];
            if(!errors.rank && !['1','2','3'].includes(rank.value))errors.rank=['Select Class Rank 1, 2, or 3.'];
            if(Object.keys(errors).length){await showErrors(errors);return;}
            Object.keys(fields).forEach(clearFieldError);
            const body=new FormData(form),button=form.querySelector('[type="submit"]'),original=button.innerHTML;
            saving=true;updateState();button.textContent='Saving…';
            try{
                const response=await fetch(form.action,{method:'POST',body,headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'});
                const data=await response.json();
                if(!response.ok || response.redirected){await showErrors(data.errors||{form:[data.message||'Unable to save. Please try again.']});return;}
                clearStudent();
                await refreshRanks();
                Swal.fire({toast:true,position:'top-end',icon:'success',title:data.message||'Class rank saved successfully.',showConfirmButton:false,showCloseButton:true,timer:3500,timerProgressBar:true});
            }catch(error){await showErrors({form:['Unable to save. Check your connection and try again.']});}
            finally{saving=false;button.innerHTML=original;updateState();form.querySelector('.is-invalid:not([hidden]):not(:disabled)')?.focus();}
        });
        const loadStudents=async()=>{
            const requestId=++studentSequence;studentAbort?.abort();
            if(!year.value || !campus.value || !grade.value){status.textContent='Select a campus and grade to find students.';return;}
            studentAbort=new AbortController();status.textContent='Searching students…';
            const url=new URL(root.dataset.studentsUrl,window.location.origin);
            ['academic_year_id','campus_id','grade_class'].forEach(key=>{if(form.elements[key]?.value)url.searchParams.set(key,form.elements[key].value);});
            url.searchParams.set('q',studentControl.search.value);
            try{
                const response=await fetch(url,{headers:{Accept:'application/json'},credentials:'same-origin',signal:studentAbort.signal});
                const data=await response.json();
                if(!response.ok || response.redirected)throw new Error(data.message||'Unable to load students. Refresh the page and try again.');
                if(requestId!==studentSequence)return;
                const previous=student.selectedOptions[0]?.cloneNode(true),value=student.value;
                student.replaceChildren(new Option('Select student',''));
                data.data.forEach(item=>{const option=new Option(item.label,String(item.id));option.dataset.rank=item.rank??'';option.dataset.eoyAverage=item.eoy_average??'';student.append(option);});
                if(value && ![...student.options].some(option=>option.value===value) && previous)student.append(previous);
                student.value=value;studentControl.refresh();
                status.textContent=data.has_more?'More than 30 students found. Type an ID or name to narrow the search.':data.data.length?`${data.data.length} student${data.data.length===1?'':'s'} found.`:'No students match these filters.';
            }catch(error){if(error.name!=='AbortError' && requestId===studentSequence)status.textContent=error.message||'Unable to load students. Please try again.';}
        };
        const renderRankPagination=()=>{
            const container=root.querySelector('[data-rank-pagination]');
            if(!container)return;
            renderPagination(JSON.parse(container.dataset.rankPagination),'scholarshipRanksPagination','scholarshipRankPageSize',(page,size)=>{
                form.elements.per_page.value=String(size);
                refreshRanks({page,reloadStudents:false});
            },['all',10,25,50,75,100]);
        };
        const refreshRanks=async({page=null,reloadStudents=true}={})=>{
            const requestId=++filterSequence;filterAbort?.abort();cancelStudents();
            const url=filterUrl(page);syncLinks(url);loading=true;filterFailed=false;updateState();
            root.querySelector('[data-scholarship-ranks-panel]')?.setAttribute('aria-busy','true');
            listStatus.textContent='Loading assigned student ranks…';filterAbort=new AbortController();
            const endpoint=new URL(root.dataset.rankFiltersUrl,window.location.origin);endpoint.search=url.search;
            try{
                const response=await fetch(endpoint,{headers:{Accept:'application/json'},credentials:'same-origin',signal:filterAbort.signal});
                const data=await response.json();
                if(!response.ok || response.redirected)throw new Error(data.message||'Unable to load ranks. Change a filter to try again.');
                if(requestId!==filterSequence)return;
                setOptions(campus,data.campuses,'All campuses',data.filters.campus_id);
                setOptions(grade,data.grades,'All grades',data.filters.grade_class);
                if(student.value && String(data.filters.enrollment_id??'')!==student.value)clearStudent();
                locked=Boolean(data.ranks_locked);form.dataset.ranksLocked=String(locked);form.dataset.examYear=year.value;
                const template=document.createElement('template');template.innerHTML=data.html.trim();
                const panel=template.content.querySelector('[data-scholarship-ranks-panel]');
                if(!panel)throw new Error('Unable to display ranks. Please refresh the page.');
                root.querySelector('[data-scholarship-ranks-panel]').replaceWith(panel);
                form.elements.per_page.value=String(data.filters.per_page??form.elements.per_page.value);
                renderRankPagination();
                loading=false;updateState();syncLinks(filterUrl(page));listStatus.textContent='';
                if(reloadStudents)loadStudents();
            }catch(error){
                if(error.name!=='AbortError' && requestId===filterSequence){
                    root.querySelector('[data-scholarship-ranks-panel]').hidden=true;
                    listStatus.textContent=error.message||'Unable to load ranks. Change a filter to try again.';
                    loading=false;filterFailed=true;updateState();
                }
            }
        };
        studentControl.search.addEventListener('input',()=>{cancelStudents();timer=setTimeout(loadStudents,250);});
        student.addEventListener('change',()=>{
            if(String(student.value)!==String(form.dataset.editingEnrollment||''))form.elements.rank_id.value='';
            rank.value=student.selectedOptions[0]?.dataset.rank||'';if(rank.selectedIndex<0)rank.value='';
            average.value=student.selectedOptions[0]?.dataset.eoyAverage??'';
            refreshRanks({reloadStudents:false});
        });
        [year,campus,grade].forEach(select=>select.addEventListener('change',()=>{
            closeAll();clearStudent();
            if(select===year)setOptions(campus,[],'All campuses');
            if(select===year || select===campus)setOptions(grade,[],'All grades');
            refreshRanks();
        }));
        root.addEventListener('click',event=>{
            const link=event.target.closest('[data-scholarship-ranks-panel] .pagination a');
            if(!link || link.dataset.page!==undefined)return;event.preventDefault();
            refreshRanks({page:Number(new URL(link.href).searchParams.get('page')||1),reloadStudents:false});
        });
        form.dataset.editingEnrollment=student.value;
        renderRankPagination();updateState();loadStudents();
        if(root.dataset.rankSuccess)Swal.fire({toast:true,position:'top-end',icon:'success',title:root.dataset.rankSuccess,showConfirmButton:false,showCloseButton:true,timer:3500,timerProgressBar:true});
        const initialErrors=JSON.parse(root.dataset.rankErrors||'{}');
        if(Object.keys(initialErrors).length)showErrors(initialErrors);
    }
}
