import {Modal} from 'bootstrap';
import {setupStaffLocationSelect} from './helpers/staffBirthplaceSelect.js';
import {renderPagination} from './helpers/pagination.js';
import {setupG9PhotoViewer} from './helpers/g9PhotoViewer.js';
import {setupG9CertificateNumbers} from './helpers/g9CertificateNumbers.js';
import {initPremiumDatePicker} from './premiumDatePicker.js';

const root=document.querySelector('[data-g9-certificate-tracking]');
if(root){
    const get=selector=>root.querySelector(selector),filters=get('#g9CertificateFilters'),rows=get('[data-certificate-rows]');
    const error=get('[data-certificate-error]'),success=get('[data-certificate-success]'),summary=get('[data-certificate-summary]');
    const search=get('#g9CertificateSearch'),perPage=get('#g9-certificate-per-page'),pagination=get('#g9-certificate-pagination');
    const modalElement=get('#g9CertificateModal'),form=get('[data-certificate-form]'),modalError=get('[data-certificate-modal-error]');
    const save=get('[data-certificate-save]'),viewPhoto=setupG9PhotoViewer(),combos=new Map(),order=['academic_year','exam_center','campus','room','grade','student'];
    const fields=['status','number','printed_signed_date','received_date','recipient','reason'];
    const mobile=matchMedia('(max-width:767.98px)');
    let sequence=0,controller=null,timer=null,currentPage=1,opening=0,saving=false,editable=false;
    const escape=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const date=value=>value&&/^\d{4}-\d{2}-\d{2}$/.test(value)?value.split('-').reverse().join('-'):value||'—';
    const englishMonths=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const khmerMonths=['មករា','កុម្ភៈ','មីនា','មេសា','ឧសភា','មិថុនា','កក្កដា','សីហា','កញ្ញា','តុលា','វិច្ឆិកា','ធ្នូ'];
    const certificateDate=value=>{
        if(!/^\d{4}-\d{2}-\d{2}$/.test(value??''))return value||'—';
        const [year,month,day]=value.split('-'),khmer=text=>text.replace(/\d/g,digit=>'០១២៣៤៥៦៧៨៩'[Number(digit)]);
        return `${khmer(day)} ${khmerMonths[Number(month)-1]} ${khmer(year)}`;
    };
    const receivedDate=value=>{
        if(!/^\d{4}-\d{2}-\d{2}$/.test(value??''))return value||'—';
        const [year,month,day]=value.split('-');return `${day}-${englishMonths[Number(month)-1]}-${year}`;
    };
    const receivedDateElement=get('[data-certificate-received-picker]'),receivedDatePicker=initPremiumDatePicker(receivedDateElement);
    const receivedDateInput=receivedDateElement.querySelector('[data-premium-date-input]');
    const params=()=>{const query=new URLSearchParams(new FormData(filters));query.set('search',search.value.trim());return query;};
    const badge=(label,color)=>`<span class="badge bg-${color}-lt">${escape(label)}</span>`;
    const canEdit=r=>r.can_manage&&(r.tracking.status!=='received'||r.can_correct);
    const api=async(path,{signal,body}={})=>{
        const json=body&&!(body instanceof FormData);
        const response=await fetch(root.dataset.base+path,{method:body?'POST':'GET',credentials:'same-origin',signal,
            headers:{Accept:'application/json',...(body?{'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content??''}:{}),...(json?{'Content-Type':'application/json'}:{})},body:json?JSON.stringify(body):body});
        if([401,419].includes(response.status)||response.redirected)throw new Error('Your session expired. Refresh the page and sign in again.');
        const data=await response.json();
        if(!response.ok)throw new Error(Object.values(data.errors??{}).flat().join('\n')||data.message||'Unable to load certificate tracking.');
        return data;
    };
    filters.querySelectorAll('select').forEach(select=>{
        [...select.options].forEach(option=>option.dataset.en=option.textContent);
        const combo=setupStaffLocationSelect(filters,select,{labelId:select.id+'Label',optionsId:select.id+'Options',locationLabel:select.getAttribute('aria-label')});combos.set(select,combo);
        const menu=select.nextElementSibling.querySelector('.location-combobox-menu');menu.classList.add('g9-center-menu');
        const heading=document.createElement('div');heading.className='g9-center-menu-heading';heading.textContent=select.getAttribute('aria-label');menu.prepend(heading);
        const position=()=>{
            const rect=select.nextElementSibling.getBoundingClientRect();let left=12,right=document.documentElement.clientWidth-12;
            for(let parent=select.parentElement;parent;parent=parent.parentElement){
                if(!['auto','scroll','hidden','clip'].includes(getComputedStyle(parent).overflowX))continue;
                const bounds=parent.getBoundingClientRect();left=Math.max(left,bounds.left+parent.clientLeft+8);right=Math.min(right,bounds.left+parent.clientLeft+parent.clientWidth-8);
            }
            const width=Math.min(520,Math.max(0,right-left));menu.style.width=width+'px';menu.style.left=Math.max(left,Math.min(rect.left,right-width))-rect.left+'px';
        };
        combo.toggle.addEventListener('click',position,{capture:true});select.nextElementSibling.addEventListener('keydown',position,{capture:true});window.addEventListener('resize',position);
    });
    const sync=()=>combos.forEach(combo=>combo.sync());
    const fill=(name,values)=>{
        const select=filters.elements[name],previous=select.value,empty=select.options[0].cloneNode(true);select.replaceChildren(empty);
        for(const value of values??[]){const option=new Option(typeof value==='object'?value.label:value,typeof value==='object'?value.id:value);option.dataset.en=option.textContent;select.append(option);}
        select.value=[...select.options].some(option=>option.value===previous)?previous:'';
    };
    const expand=expanded=>{
        filters.hidden=!expanded;const toggle=get('[data-certificate-filter-toggle]');toggle.setAttribute('aria-expanded',String(expanded));
        toggle.setAttribute('aria-label',(expanded?'Collapse':'Expand')+' filters');toggle.querySelector('i').className='ti ti-chevron-'+(expanded?'up':'down');
        if(!expanded)combos.forEach(combo=>combo.close());
    };
    expand(!mobile.matches);mobile.addEventListener('change',()=>expand(!mobile.matches));get('[data-certificate-filter-toggle]').addEventListener('click',()=>expand(filters.hidden));
    const empty=text=>rows.innerHTML=`<tr><td colspan="8" class="text-center text-secondary py-5">${escape(text)}</td></tr>`;
    const documentCard=(file,label,canView,legacy='')=>{
        if(!file)return `<div class="g12-certificate-document is-empty"><span class="g12-certificate-document-icon"><i class="ti ti-file-off" aria-hidden="true"></i></span><div class="g12-certificate-document-info"><strong>${escape(label)}</strong><small>${canView?(legacy?'Imported reference: '+escape(legacy):'Not uploaded'):'Document access restricted'}</small></div></div>`;
        const size=file.size>=1048576?(file.size/1048576).toFixed(1)+' MB':Math.max(1,Math.round(file.size/1024))+' KB';
        return `<div class="g12-certificate-document"><a class="g12-certificate-preview ${file.is_image?'':'is-pdf'}" href="${escape(file.url)}" target="_blank" rel="noopener" aria-label="Open ${escape(label)}">${file.is_image?`<img src="${escape(file.thumbnail_url)}" alt="${escape(label)} thumbnail" loading="lazy">`:'<i class="ti ti-file-type-pdf" aria-hidden="true"></i>'}</a><div class="g12-certificate-document-info"><strong>${escape(label)}</strong><small title="${escape(file.original_name)}">${escape(file.original_name)}</small><span>${size}</span></div><a class="g12-certificate-download" href="${escape(file.is_image?file.url+'?jpg=1':file.download)}" aria-label="Download ${escape(label)}"><i class="ti ti-download" aria-hidden="true"></i></a></div>`;
    };
    const certificateCell=(r,receipt=false)=>{
        const label=receipt?'Certificate Received by Student':'Provisional Certificate';
        if(!r.eligible)return `<td class="g12-certificate-cell" data-label="${label}"><div class="g12-certificate-group is-not-issued"><div>${badge(r.outcome==='pending'?'Pending Result':'Not Issued',r.outcome==='pending'?'orange':'red')}</div><p class="g12-certificate-remark">${r.outcome==='absent'?'Candidate absent from the examination.':r.outcome==='pending'?'Certificate issuance is available after a passed result.':'Certificate not issued — Failed National Examination.'}</p></div></td>`;
        const t=r.tracking,done=receipt?t.status==='received':['printed_signed','received'].includes(t.status);
        const status=receipt?(done?'Received by Student':'Not Yet Received'):(done?'Printed & Signed':'Awaiting Printing');
        let details=receipt?`<div>Received Date: <strong>${escape(receivedDate(t.received_date))}</strong></div><div>Received By: <strong>${escape(t.recipient||'—')}</strong></div>`:`<div>Printed & Signed: <strong>${escape(date(t.printed_signed_date))}</strong></div><div>Certificate No.: <strong>${escape(t.number||'—')}</strong></div>`;
        if(!receipt&&t.certificate_date)details+=`<div>Certificate Date: <strong class="school-profile-khmer g9-certificate-date-value">${escape(certificateDate(t.certificate_date))}</strong></div>`;
        if(t.legacy)details+=`<div>${badge(t.needs_review?'Imported · Needs Review':'Imported History','orange')}</div>`;
        return `<td class="g12-certificate-cell" data-label="${label}"><div class="g12-certificate-group"><div class="g12-certificate-group-header">${badge(status,done?'green':'orange')}</div>${documentCard(r.documents[receipt?'receipt':'provisional'],receipt?'Student-Signed Copy':'Printed & Signed Copy',r.can_view_documents,receipt?r.legacy_receipt_name:r.legacy_source_name)}<div class="g9-certificate-details">${details}</div></div></td>`;
    };
    const render=data=>{
        summary.hidden=false;for(const key of ['total','passed','failed','absent','printed_signed','received','awaiting'])get(`[data-certificate-count="${key}"]`).textContent=data.summary[key].toLocaleString();
        const result=data.records;currentPage=result.current_page;
        rows.innerHTML=result.data.map((r,index)=>`<tr>
            <td class="text-center" data-label="No.">${result.from+index}</td>
            <td data-label="Photo">${r.photo_url?`<button type="button" class="g12-photo-trigger" data-certificate-photo="${r.id}" aria-label="View photo of ${escape(r.name_en)}"><img src="${escape(r.photo_url)}" class="g12-photo" loading="lazy" alt="Photo of ${escape(r.name_en)}"></button>`:'—'}</td>
            <td data-label="Student"><div><strong class="school-profile-khmer">${escape(r.name_kh||'—')}</strong><div class="g12-student-name-en">${escape(r.name_en)}</div><div class="text-secondary">${escape(r.student_code)}</div></div></td>
            <td class="g12-tracking-grade" data-label="Grade"><div><strong>${escape(r.year)}</strong><div>${escape(r.campus)} / ${escape(r.grade)}</div></div></td>
            <td class="g12-tracking-examination" data-label="Examination"><div class="g9-certificate-exam"><strong>${escape(r.exam_center||'Not assigned')}</strong><div class="g12-tracking-exam-date">Exam Date: ${escape(date(r.exam_date))}</div><div class="g12-tracking-room-seat"><span>Room: ${escape(r.room||'—')}</span><b aria-hidden="true">|</b><span>Seat: ${escape(r.seat||'—')}</span></div><div class="g12-tracking-result"><span>Letter Grade: <strong>${escape(r.letter_grade||'—')}</strong></span>${badge({passed:'Passed',failed:'Failed',pending:'Pending',absent:'Absent'}[r.outcome],{passed:'green',failed:'red',pending:'orange',absent:'orange'}[r.outcome])}</div></div></td>
            ${certificateCell(r)}${certificateCell(r,true)}
            <td data-label="Actions"><button type="button" class="btn btn-sm btn-outline-primary" data-certificate-manage="${r.id}"><i class="ti ${canEdit(r)?'ti-folder-open':'ti-eye'} me-1" aria-hidden="true"></i>${canEdit(r)?'Manage':'View'}</button></td>
        </tr>`).join('');
        if(!result.data.length)empty('No candidates match the selected filters.');
        rows.querySelectorAll('[data-certificate-photo]').forEach(button=>button.addEventListener('click',()=>viewPhoto(result.data.find(r=>r.id===Number(button.dataset.certificatePhoto)))));
        renderPagination(result,'g9-certificate-pagination','g9-certificate-per-page',page=>load(page),[15,25,50,100]);
    };
    async function load(page=1,refreshOptions=false){
        clearTimeout(timer);const token=++sequence;controller?.abort();controller=new AbortController();const signal=controller.signal;
        root.setAttribute('aria-busy','true');error.hidden=true;summary.hidden=true;pagination.replaceChildren();
        try{
            if(!filters.elements.academic_year.value){order.slice(1).forEach(name=>fill(name,[]));sync();empty('Select an academic year to display certificate tracking.');return;}
            empty('Loading certificate tracking…');
            if(refreshOptions){const options=await api('/options?'+params(),{signal});if(token!==sequence)return;order.slice(1).forEach(name=>fill(name,options[name]));sync();}
            const query=params();query.set('page',page);query.set('per_page',perPage.value);const data=await api('/data?'+query,{signal});if(token===sequence)render(data);
        }catch(failure){if(failure.name!=='AbortError'&&token===sequence){error.textContent=failure.message;error.hidden=false;empty('Unable to load certificate tracking.');}}
        finally{if(token===sequence)root.setAttribute('aria-busy','false');}
    }
    const syncStatus=()=>{
        const status=form.elements.status.value;
        form.elements.received_date.required=form.elements.recipient.required=editable&&status==='received';
        receivedDateInput.required=editable&&status==='received';
        receivedDateElement.dataset.premiumDateMin=form.elements.printed_signed_date.value;
    };
    async function open(id){
        const token=++opening;error.hidden=true;
        try{
            const record=await api('/'+id);if(token!==opening)return;
            form.reset();modalError.hidden=true;editable=canEdit(record);const correction=record.tracking.status==='received';
            form.elements.candidate_id.value=record.id;form.elements.version.value=record.tracking.version;
            for(const name of fields){form.elements[name].value=record.tracking[name]??'';form.elements[name].disabled=!editable;}
            receivedDateInput.disabled=receivedDateElement.querySelector('[data-premium-date-toggle]').disabled=!editable;
            syncStatus();receivedDatePicker.setValue(record.tracking.received_date??'');
            form.elements.number.readOnly=Boolean(record.tracking.number);
            get('[data-certificate-student]').textContent=[record.name_kh,record.name_en,record.student_code].filter(Boolean).join(' · ');
            for(const kind of ['provisional','receipt']){
                const label=get(`[data-certificate-file-label="${kind}"]`);label.hidden=!editable;label.classList.remove('has-file');
                label.querySelector('strong').textContent=kind==='receipt'?'Choose student-signed copy image or PDF':'Choose certificate image or PDF';form.elements[kind+'_file'].disabled=!editable;
                get(`[data-certificate-current="${kind}"]`).innerHTML=documentCard(record.documents[kind],kind==='receipt'?'Student-Signed Copy':'Provisional Certificate',record.can_view_documents,kind==='receipt'?record.legacy_receipt_name:record.legacy_source_name);
            }
            const note=get('[data-certificate-modal-note]');note.hidden=true;
            if(!record.eligible){note.textContent=record.outcome==='absent'?'This candidate was absent. A certificate cannot be issued.':record.outcome==='pending'?'The result is pending. Certificates and uploads become available after a passed result.':'This candidate failed the examination. A certificate cannot be issued.';note.hidden=false;}
            else if(record.tracking.legacy){note.textContent='Imported certificate history is preserved. '+(record.tracking.review_notes?.join(' ')||'Check the dates, recipient and signed copy before saving a correction.');note.hidden=false;}
            else if(correction&&!editable){note.textContent='This certificate has already been received. Correction permission is required to change it.';note.hidden=false;}
            get('[data-certificate-reason-field]').hidden=!correction||!editable;form.elements.reason.required=correction&&editable;form.elements.reason.disabled=!correction||!editable;
            const latest=new Set(Object.values(record.documents).map(file=>file.id));const previous=(record.document_history??[]).filter(file=>!latest.has(file.id));
            get('[data-certificate-history]').hidden=!previous.length;get('[data-certificate-history]').open=false;
            get('[data-certificate-history-files]').innerHTML=previous.map(file=>documentCard(file,file.kind==='receipt'?'Previous Signed Copy':'Previous Provisional Certificate',true)).join('');
            save.hidden=!editable;save.disabled=false;syncStatus();Modal.getOrCreateInstance(modalElement).show();
        }catch(failure){error.textContent=failure.message;error.hidden=false;}
    }
    root.addEventListener('click',event=>{const button=event.target.closest('[data-certificate-manage]');if(button)open(button.dataset.certificateManage);});
    filters.addEventListener('submit',event=>event.preventDefault());
    filters.addEventListener('change',event=>{if(event.target===search)return;const index=order.indexOf(event.target.name);if(index>=0)order.slice(index+1).forEach(name=>filters.elements[name].value='');sync();success.hidden=true;load(1,index>=0);});
    search.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(()=>load(1),300);});
    form.addEventListener('change',event=>{
        if(event.target.type==='file'){
            const label=event.target.closest('[data-certificate-file-label]'),file=event.target.files[0];label.classList.toggle('has-file',Boolean(file));
            label.querySelector('strong').textContent=file?.name||(event.target.name==='receipt_file'?'Choose student-signed copy image or PDF':'Choose certificate image or PDF');
        }
        syncStatus();
        if(event.target.name==='printed_signed_date')receivedDateElement.dispatchEvent(new Event('premium-date-range-change'));
    });
    modalElement.addEventListener('hide.bs.modal',event=>{if(saving)event.preventDefault();});
    form.addEventListener('submit',async event=>{
        event.preventDefault();if(saving||!editable||!form.reportValidity())return;
        saving=true;save.disabled=true;modalError.hidden=true;
        try{
            const data=new FormData(form);for(const name of ['provisional_file','receipt_file'])if(!form.elements[name].files.length)data.delete(name);
            await api('/'+form.elements.candidate_id.value,{body:data});saving=false;Modal.getOrCreateInstance(modalElement).hide();
            success.textContent='Certificate documents and tracking saved.';success.hidden=false;await load(currentPage);
        }catch(failure){modalError.textContent=failure.message;modalError.hidden=false;modalError.scrollIntoView({block:'nearest'});}
        finally{saving=false;save.disabled=false;}
    });
    setupG9CertificateNumbers(root,{api,onGenerated:()=>load(currentPage)});
    sync();load();
}
