import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';
import { initScholarshipFilterCard } from './scholarshipFilterCard.js';
import { renderPagination } from './helpers/pagination.js';

const clone=value=>structuredClone(value);
function loadFonts(fonts){
    const style=document.createElement('style');
    style.textContent=Object.entries(fonts).map(([key,font])=>`@font-face{font-family:${key};src:url('/fonts/${font.directory}/${font.file}');font-weight:400;font-display:block}${font.bold?`@font-face{font-family:${key};src:url('/fonts/${font.directory}/${font.bold}');font-weight:700;font-display:block}`:''}`).join('');
    document.head.append(style);
}
function replaceValues(text,values){return String(text??'').replace(/\{\{\s*([^}]+)\s*\}\}/g,(_,key)=>String(values[key.trim()]??''));}
function fitText(sheet){
    sheet.querySelectorAll('[data-font-size]').forEach(el=>{
        const original=Number(el.dataset.fontSize);el.style.fontSize=`${original}pt`;
        // Measure in layout pixels, so zooming the preview does not alter the font size.
        const ratio=Math.min(1,(el.clientWidth-3)/Math.max(1,el.scrollWidth),(el.clientHeight-3)/Math.max(1,el.scrollHeight));
        if(ratio<1)el.style.fontSize=`${Math.max(6,original*ratio)}pt`;
    });
}
function draw(sheet,layout,values,assets,selected=null){
    const elements=[];const frame=document.createElement('img');frame.className='cert-frame';frame.alt='Certificate frame';frame.src=assets.frame;elements.push(frame);
    if(layout.fields.logo.visible&&assets[layout.logo_source]){const cover=document.createElement('div');cover.className='cert-logo-cover';elements.push(cover);}
    Object.entries(layout.fields).forEach(([key,field])=>{
        if(!field.visible)return;
        const el=document.createElement('div');el.className=`cert-field${selected===key?' is-selected':''}`;el.dataset.field=key;
        Object.assign(el.style,{left:`${Number(field.x)+Number(layout.offset_x)}mm`,top:`${Number(field.y)+Number(layout.offset_y)}mm`,width:`${field.width}mm`,height:`${field.height}mm`,fontFamily:field.font,fontSize:`${field.size}pt`,fontWeight:field.bold?'700':'400',color:field.color,textAlign:field.align});
        if(key==='logo'){
            if(assets[layout.logo_source]){const image=document.createElement('img');image.src=assets[layout.logo_source];image.alt='School logo';el.append(image);}
            else{el.textContent='Choose or upload a logo';el.style.fontSize='10pt';}
        }else{el.textContent=replaceValues(field.text,values);el.dataset.fontSize=field.size;}
        elements.push(el);
    });
    sheet.replaceChildren(...elements);fitText(sheet);
}
const report=document.querySelector('[data-cert-report]');
if(report){
    const data=JSON.parse(document.getElementById('certReportData').textContent);loadFonts(data.fonts);
    report.querySelectorAll('[data-cert-page]').forEach(sheet=>{const page=data.pages[Number(sheet.dataset.certPage)];draw(sheet,page.layout,page.values,data.assets);});
    const ready=()=>Promise.all([document.fonts.ready,...[...report.querySelectorAll('img')].map(img=>img.decode?.().catch(()=>{}))]);
    ready().then(()=>report.querySelectorAll('[data-cert-page]').forEach(fitText));
    report.querySelector('[data-cert-print]').addEventListener('click',async()=>{await ready();report.querySelectorAll('[data-cert-page]').forEach(fitText);window.print();});
    window.addEventListener('beforeprint',()=>report.querySelectorAll('[data-cert-page]').forEach(fitText));
}
const root=document.querySelector('[data-certificates]');
if(root){
    const card=root.querySelector('[data-cert-filters-card]'),form=card.querySelector('[data-cert-filters]');
    const pickers=initScholarshipFilterCard({card,body:card.querySelector('#certFilters'),toggle:card.querySelector('[data-cert-filters-toggle]'),selects:form.querySelectorAll('[data-cert-picker]'),label:'certificate filters'});
    form.addEventListener('change',event=>{
        if(!event.target.matches('[data-cert-picker]'))return;
        const {academic_year_id:year,campus,grade,type,q}=form.elements;
        const clear=select=>{select.value='';pickers.get(select)?.refresh();};
        if(event.target===year)[campus,grade,type,q].forEach(clear);
        else if(event.target!==q)clear(q);
        form.requestSubmit();
    });
}
const raw=document.getElementById('certData');
if(root&&raw){
    const data=JSON.parse(raw.textContent);loadFonts(data.fonts);
    const get=selector=>root.querySelector(selector);const all=selector=>[...root.querySelectorAll(selector)];
    let settings=data.settings,assets=data.assets,layouts=clone(settings.layouts),type='class',key='title',settingsDirty=false,busy=false;
    const modalElement=get('#certSettings'),settingsModal=modalElement?Modal.getOrCreateInstance(modalElement):null;
    const alertTarget=()=>modalElement?.classList.contains('show')?modalElement:document.body;
    get('[data-cert-open-settings]')?.addEventListener('click',event=>settingsModal.show(event.currentTarget));
    modalElement?.addEventListener('shown.bs.modal',()=>modalElement.dataset.certSettingsReady='true');
    modalElement?.addEventListener('hidden.bs.modal',()=>{delete modalElement.dataset.certSettingsReady;get('[data-cert-open-settings]')?.focus();});
    const closeSettings=()=>new Promise(resolve=>{
        if(!modalElement?.classList.contains('show')){resolve();return;}
        modalElement.addEventListener('hidden.bs.modal',resolve,{once:true});
        if(modalElement.dataset.certSettingsReady==='true')settingsModal.hide();
        else modalElement.addEventListener('shown.bs.modal',()=>settingsModal.hide(),{once:true});
    });
    const list=get('[data-cert-list]'),pageSize=get('#certPageSize');
    let pageData=JSON.parse(list.querySelector('[data-cert-list-data]').textContent);
    const sheet=get('[data-cert-canvas]');const templateDirty=()=>JSON.stringify(layouts)!==JSON.stringify(settings.layouts);
    const dirty=()=>templateDirty()||settingsDirty;
    const changed=()=>{const badge=get('[data-cert-unsaved]');if(badge)badge.hidden=!templateDirty();};
    const values=()=>({...data.samples[type],location:settings.location,issue_date:new Intl.DateTimeFormat('en-US',{year:'numeric',month:'long',day:'numeric',timeZone:'UTC'}).format(new Date(`${settings.issue_date}T12:00:00Z`)),scholarship_year:settings.scholarship_year,
        ...Object.fromEntries(settings.signers.flatMap((s,i)=>[[`signer_${i+1}_name`,s.name],[`signer_${i+1}_position`,s.position]]))});
    const drawEditor=()=>{if(sheet)draw(sheet,layouts[type],values(),assets,key);changed();};
    function resize(){if(!sheet)return;const shell=sheet.parentElement;if(shell.clientWidth)sheet.style.transform=`scale(${shell.clientWidth/sheet.offsetWidth})`;}
    if(sheet){sheet.dataset.editable='true';new ResizeObserver(resize).observe(sheet.parentElement);document.fonts.ready.then(drawEditor);document.fonts.addEventListener('loadingdone',drawEditor);}
    function syncControls(){
        if(!sheet)return;const field=layouts[type].fields[key];get('#certField').value=key;
        all('[data-cert-property]').forEach(input=>{const value=field[input.dataset.certProperty];if(input.type==='checkbox')input.checked=!!value;else input.value=value??'';});
        get('[data-cert-text-controls]').hidden=key==='logo';get('[data-cert-logo-controls]').hidden=key!=='logo';get('#certLogoSource').value=layouts[type].logo_source;
        all('[data-cert-offset]').forEach(el=>el.value=layouts[type][`offset_${el.dataset.certOffset}`]);drawEditor();resize();
    }
    async function request(url,body=null){
        const response=await fetch(url,{method:body?'POST':'GET',credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content??'',...(body instanceof FormData?{}:{'Content-Type':'application/json'})},...(body?{body:body instanceof FormData?body:JSON.stringify(body)}:{})});
        const json=await response.json().catch(()=>({message:'The request could not be completed. Please reload and try again.'}));
        if(!response.ok)throw new Error(json.errors?Object.values(json.errors).flat().join('\n'):json.message);return json;
    }
    async function run(action){
        if(busy)return;busy=true;root.setAttribute('aria-busy','true');const buttons=all('button[data-cert-save],button[data-cert-plan],[data-cert-save-settings],button[data-cert-output],button[data-cert-cancel]');const disabled=buttons.map(b=>b.disabled);buttons.forEach(b=>b.disabled=true);
        try{await action();}catch(error){await Swal.fire({icon:'error',title:'Please check',text:error.message,target:alertTarget()});}
        finally{busy=false;root.removeAttribute('aria-busy');buttons.forEach((b,i)=>b.disabled=disabled[i]);}
    }
    const success=title=>Swal.fire({icon:'success',title,timer:1800,showConfirmButton:false,target:alertTarget()});
    async function allowLeave(){if(!dirty())return true;const result=await Swal.fire({icon:'warning',title:'Unsaved certificate changes',text:'Save your settings and templates before continuing, or discard the changes.',showCancelButton:true,confirmButtonText:'Keep editing',cancelButtonText:'Discard changes'});return result.dismiss===Swal.DismissReason.cancel;}
    window.addEventListener('beforeunload',event=>{if(dirty()||busy){event.preventDefault();event.returnValue='';}});
    document.addEventListener('click',async event=>{const link=event.target.closest('a[href]');if(!link||!dirty()||link.target==='_blank'||link.getAttribute('href').startsWith('#'))return;event.preventDefault();event.stopImmediatePropagation();if(await allowLeave()){settingsDirty=false;layouts=clone(settings.layouts);location.assign(link.href);}},true);
    get('[data-cert-filters]').addEventListener('submit',async event=>{if(!dirty())return;event.preventDefault();if(await allowLeave()){settingsDirty=false;layouts=clone(settings.layouts);event.target.submit();}});
    all('[data-cert-toggle]').forEach(button=>button.addEventListener('click',()=>{const target=get(`#${button.dataset.certToggle}`);target.hidden=!target.hidden;all(`[data-cert-toggle="${button.dataset.certToggle}"]`).forEach(b=>b.setAttribute('aria-expanded',String(!target.hidden)));resize();if(!target.hidden&&target.id!=='certFilters')target.scrollIntoView({behavior:'smooth',block:'start'});}));
    function initPagination(){pageSize.value=pageData.per_page;renderPagination(pageData,'certPagination','certPageSize',loadPage,['all',15,25,50,100]);}
    async function loadPage(page,size){
        if(busy){initPagination();return;}
        if(dirty()){initPagination();await Swal.fire({icon:'info',title:'Save changes first',text:'Save your certificate settings and templates before changing pages.'});return;}
        await run(async()=>{
            list.setAttribute('aria-busy','true');
            try{
                const url=new URL(get('[data-cert-filters]').action,location.href);new FormData(get('[data-cert-filters]')).forEach((v,k)=>url.searchParams.set(k,v));
                url.searchParams.set('page',page);url.searchParams.set('per_page',size);url.searchParams.set('certificate_list','1');
                const response=await fetch(url,{headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'});
                const result=await response.json();if(!response.ok)throw new Error(result.errors?Object.values(result.errors).flat().join('\n'):result.message);
                pageData=result.pagination;list.innerHTML=result.html;
                history.replaceState(null,'',result.url);initPagination();
            }catch(error){initPagination();throw error;}finally{list.removeAttribute('aria-busy');}
        });
    }
    initPagination();
    get('[data-cert-settings]')?.addEventListener('input',()=>settingsDirty=true);
    all('[data-cert-staff]').forEach(select=>select.addEventListener('change',()=>{const option=select.selectedOptions[0];if(!option.dataset.name)return;get(`[data-signer-name="${select.dataset.certStaff}"]`).value=option.dataset.name;get(`[data-signer-position="${select.dataset.certStaff}"]`).value=option.dataset.position;settingsDirty=true;}));
    get('[data-cert-settings]')?.addEventListener('submit',event=>{event.preventDefault();if(!event.target.reportValidity())return;run(async()=>{
        const form=event.target;const body={version:settings.version,issue_date:form.elements.issue_date.value,location:form.elements.location.value,scholarship_year:form.elements.scholarship_year.value,
            signers:[0,1].map(i=>({name:get(`[data-signer-name="${i}"]`).value,position:get(`[data-signer-position="${i}"]`).value}))};
        const result=await request(data.urls.settings,body);settings=result.settings;settingsDirty=false;drawEditor();await closeSettings();await success('Certificate settings saved');
    });});
    all('[data-cert-upload]').forEach(input=>input.addEventListener('change',()=>run(async()=>{
        const file=input.files[0];if(!file)return;if(file.size>10*1024*1024)throw new Error('Choose an image smaller than 10 MB.');
        const body=new FormData();body.append('version',settings.version);body.append('kind',input.dataset.certUpload);body.append('image',file);
        const result=await request(data.urls.image,body);settings=result.settings;assets=result.assets;drawEditor();input.value='';await success('Certificate image saved');
    })));
    get('#certTemplate')?.addEventListener('change',event=>{type=event.target.value;syncControls();});
    get('#certField')?.addEventListener('change',event=>{key=event.target.value;syncControls();});
    all('[data-cert-property]').forEach(input=>input.addEventListener('input',()=>{
        let value=input.type==='checkbox'?input.checked:(input.type==='number'?Number(input.value):input.value);
        layouts[type].fields[key][input.dataset.certProperty]=value;drawEditor();
    }));
    get('#certToken')?.addEventListener('change',event=>{
        if(!event.target.value)return;const input=get('#certText');input.setRangeText(`{{${event.target.value}}}`,input.selectionStart,input.selectionEnd,'end');layouts[type].fields[key].text=input.value;event.target.value='';drawEditor();input.focus();
    });
    get('#certLogoSource')?.addEventListener('change',event=>{layouts[type].logo_source=event.target.value;drawEditor();});
    all('[data-cert-offset]').forEach(input=>input.addEventListener('input',()=>{layouts[type][`offset_${input.dataset.certOffset}`]=Number(input.value);drawEditor();}));
    const move=(x,y)=>{const field=layouts[type].fields[key];field.x=Math.round(Math.max(0,Math.min(210-field.width,x))*10)/10;field.y=Math.round(Math.max(0,Math.min(297-field.height,y))*10)/10;syncControls();};
    all('[data-cert-nudge]').forEach(button=>button.addEventListener('click',()=>{const dir=button.dataset.certNudge,f=layouts[type].fields[key];move(f.x+(dir==='left'?-1:dir==='right'?1:0),f.y+(dir==='up'?-1:dir==='down'?1:0));}));
    let drag=null;
    sheet?.addEventListener('pointerdown',event=>{const target=event.target.closest('[data-field]');if(!target)return;key=target.dataset.field;const field=layouts[type].fields[key];drag={x:event.clientX,y:event.clientY,left:field.x,top:field.y,scale:210/sheet.getBoundingClientRect().width};sheet.setPointerCapture(event.pointerId);syncControls();event.preventDefault();});
    sheet?.addEventListener('pointermove',event=>{if(drag)move(drag.left+(event.clientX-drag.x)*drag.scale,drag.top+(event.clientY-drag.y)*drag.scale);});
    sheet?.addEventListener('pointerup',()=>drag=null);sheet?.addEventListener('pointercancel',()=>drag=null);
    get('[data-cert-reset]')?.addEventListener('click',async()=>{if((await Swal.fire({icon:'question',title:'Reset this template?',text:'The default layout will replace your local changes. Save Template to apply it.',showCancelButton:true,confirmButtonText:'Reset Template'})).isConfirmed){layouts[type]=clone(data.defaults[type]);syncControls();}});
    get('[data-cert-save]')?.addEventListener('click',()=>run(async()=>{
        const savedType=type,submitted=clone(layouts[type]);
        const result=await request(data.urls.layout,{version:settings.version,type:savedType,layout:submitted});
        const unsaved=clone(layouts);settings=result.settings;layouts=clone(settings.layouts);
        Object.keys(layouts).filter(t=>t!==savedType||JSON.stringify(unsaved[t])!==JSON.stringify(submitted)).forEach(t=>layouts[t]=unsaved[t]);
        syncControls();await success('Certificate template saved');
    }));
    get('[data-cert-plan]')?.addEventListener('click',()=>run(async()=>{
        if(dirty()){await Swal.fire({icon:'info',title:'Save changes first',text:'Save your certificate settings and templates before previewing numbers.'});return;}
        const plan=await request(data.urls.plan);if(!plan.rows.length){await Swal.fire({icon:'info',title:'All eligible students already have numbers'});return;}
        const box=document.createElement('div');box.className='table-responsive';const note=document.createElement('p');note.textContent=`${plan.rows.length} certificates will be assigned across ALL campuses, regardless of the list filters.`;box.append(note);
        const table=document.createElement('table');table.className='table table-sm text-start';const head=table.createTHead().insertRow();['No.','Student','Campus','Grade','Certificate'].forEach(label=>{const cell=document.createElement('th');cell.textContent=label;head.append(cell);});
        const tbody=table.createTBody();plan.rows.forEach(row=>{const tr=tbody.insertRow();[row.certificate_number,row.student_name,row.campus,row.class,row.type==='subject'?`Best in ${row.subject}`:row.place].forEach(value=>tr.insertCell().textContent=value);});box.append(table);
        const result=await Swal.fire({title:'Confirm Certificate Numbers',html:box,width:1000,showCancelButton:true,confirmButtonText:'Assign These Numbers',allowOutsideClick:false});
        if(result.isConfirmed){const saved=await request(data.urls.assign,{hash:plan.hash});await success(`${saved.count} certificate numbers assigned`);busy=false;location.reload();}
    }));
    root.addEventListener('click',event=>{const button=event.target.closest('[data-cert-cancel]');if(!button)return;run(async()=>{
        if(dirty()){await Swal.fire({icon:'info',title:'Save changes first'});return;}
        const answer=await Swal.fire({icon:'warning',title:'Cancel this certificate?',text:'Its number will be retained and cannot be reused. The student will not receive another number automatically.',input:'textarea',inputLabel:'Reason for cancellation',inputValidator:value=>value.trim().length<5?'Enter a reason of at least 5 characters.':undefined,showCancelButton:true,confirmButtonText:'Cancel Certificate'});
        if(answer.isConfirmed){await request(button.dataset.certCancel,{reason:answer.value});await success('Certificate cancelled');busy=false;location.reload();}
    });});
    all('[data-cert-output]').forEach(button=>button.addEventListener('click',()=>run(async()=>{
        if(dirty()){await Swal.fire({icon:'info',title:'Save changes first',text:'Save your certificate changes before printing or exporting.'});return;}
        const ids=pageData.printable_ids;
        if(!ids.length)throw new Error('No issued certificates match the current filters. Assign certificate numbers before printing.');
        let popup=null;
        const chooseLayout=frame=>{
            if(button.dataset.certOutput==='print'){
                popup=window.open('about:blank','_blank');
                if(!popup){Swal.showValidationMessage('Allow pop-ups to open the A4 print preview.');return false;}
            }
            return {frame};
        };
        const choice=await Swal.fire({
            title:button.dataset.certOutput==='print'?'Certificate Print Preview':'Certificate PDF',
            text:`${ids.length} certificate${ids.length===1?'':'s'} match the current filters. Choose the layout.`,
            icon:'question',showDenyButton:true,showCancelButton:true,
            confirmButtonText:'Content Only',denyButtonText:'Include Certificate Frame',cancelButtonText:'Cancel',
            confirmButtonColor:'#3c70c7',denyButtonColor:'#3c70c7',
            preConfirm:()=>chooseLayout(false),preDeny:()=>chooseLayout(true),
        });
        if(!choice.isConfirmed&&!choice.isDenied)return;
        const url=new URL(data.urls.report,location.href);const payload={ids:ids.map(Number),format:button.dataset.certOutput,frame:choice.value.frame};
        try{
            const response=await fetch(url,{method:'POST',body:JSON.stringify(payload),headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content??''},credentials:'same-origin'});
            if(!response.ok){const error=await response.json();throw new Error(error.errors?Object.values(error.errors).flat().join('\n'):error.message);}
            if(popup){popup.document.open();popup.document.write(await response.text());popup.document.close();}
            else{const blob=await response.blob(),a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download='wis-scholarship-certificates.pdf';a.click();setTimeout(()=>URL.revokeObjectURL(a.href),10000);}
        }catch(error){popup?.close();throw error;}
    })));
    syncControls();root.dataset.certReady='true';
}
