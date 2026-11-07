import {Modal} from 'bootstrap';

export function setupG9CertificateNumbers(root,{api,onGenerated}){
    const trigger=root.querySelector('[data-certificate-number-settings]');if(!trigger)return;
    const get=selector=>root.querySelector(selector),element=get('#g9CertificateNumberModal'),form=get('[data-number-form]');
    const error=get('[data-number-error]'),success=get('[data-number-success]'),status=get('[data-number-status]');
    const save=get('[data-number-save]'),previewButton=get('[data-number-preview-button]'),generate=get('[data-number-generate]');
    let settings=null,order=[],preview=null,dirty=false,busy=false,sequence=0;
    const escape=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const digits=value=>String(value).replace(/[0-9]/g,c=>'០១២៣៤៥៦៧៨៩'[Number(c)]);
    const sync=()=>{
        form.elements.academic_year.disabled=busy;form.elements.starting_sequence.disabled=busy;
        form.querySelectorAll('[data-number-center-priority],[data-number-center-region]').forEach(control=>control.disabled=busy);
        form.querySelectorAll('[data-number-move]').forEach(button=>{const index=Number(button.dataset.numberMove),direction=Number(button.dataset.direction);button.disabled=busy||index+direction<0||index+direction>=order.length;});
        save.disabled=busy||!settings;previewButton.disabled=busy||!settings||dirty;
        generate.disabled=busy||dirty||!preview?.summary.new;
        generate.querySelector('span').textContent=preview?.summary.new?`Generate ${preview.summary.new} Numbers`:'Generate Numbers';
        form.setAttribute('aria-busy',String(busy));
    };
    const invalidate=()=>{preview=null;get('[data-number-preview]').hidden=true;generate.disabled=true;};
    const changed=()=>{dirty=true;invalidate();success.hidden=true;status.textContent='Save settings before previewing the numbers.';sync();};
    const renderOrder=()=>{
        get('[data-number-group-order]').innerHTML=order.map((key,i)=>`<div class="g9-number-order-item"><span class="g9-number-step">${i+1}</span><strong>${escape(settings.group_labels[key])}</strong><div class="g9-number-order-controls"><button type="button" class="btn btn-sm btn-icon" data-number-move="${i}" data-direction="-1" aria-label="Move ${escape(settings.group_labels[key])} earlier" ${i===0?'disabled':''}><i class="ti ti-arrow-up" aria-hidden="true"></i></button><button type="button" class="btn btn-sm btn-icon" data-number-move="${i}" data-direction="1" aria-label="Move ${escape(settings.group_labels[key])} later" ${i===order.length-1?'disabled':''}><i class="ti ti-arrow-down" aria-hidden="true"></i></button></div></div>`).join('');
    };
    const render=data=>{
        settings=data;order=[...data.group_order];dirty=false;form.elements.starting_sequence.value=data.starting_sequence;
        get('[data-number-year-code]').textContent=data.prefix.slice(1);get('[data-number-example]').textContent=digits(String(data.starting_sequence).padStart(4,'0'));
        renderOrder();get('[data-number-config]').hidden=false;
        get('[data-number-centers]').innerHTML=data.centers.map((center,i)=>`<tr><td><input type="number" class="form-control g9-number-priority" min="1" max="9999" step="1" value="${center.priority}" data-number-center-priority="${i}" aria-label="Priority for ${escape(center.exam_center)}" required></td><td><strong class="school-profile-khmer">${escape(center.exam_center)}</strong>${center.location?`<small>${escape(center.location)}</small>`:''}</td><td><select class="form-select" data-number-center-region="${i}" aria-label="Location for ${escape(center.exam_center)}"><option value="">Select location</option><option value="pp" ${center.region==='pp'?'selected':''}>Phnom Penh</option><option value="province" ${center.region==='province'?'selected':''}>Province</option></select></td></tr>`).join('')||'<tr><td colspan="3" class="text-secondary text-center py-3">No exam centers assigned in this year.</td></tr>';
        status.textContent='Settings loaded. Preview passed students to review the proposed numbers.';sync();
    };
    const failure=problem=>{error.textContent=problem.message;error.hidden=false;error.scrollIntoView({block:'nearest'});};
    async function load(){
        const token=++sequence;settings=null;dirty=false;invalidate();get('[data-number-config]').hidden=true;error.hidden=true;success.hidden=true;
        if(!form.elements.academic_year.value){status.textContent='Select an academic year to get started.';busy=false;sync();return;}
        busy=true;status.textContent='Loading certificate number settings…';sync();
        try{const data=await api('/numbers/settings?academic_year='+encodeURIComponent(form.elements.academic_year.value));if(token===sequence)render(data);}
        catch(problem){if(token===sequence)failure(problem);}
        finally{if(token===sequence){busy=false;sync();}}
    }
    trigger.addEventListener('click',()=>{
        form.elements.academic_year.value=get('#g9CertificateFilters').elements.academic_year.value;
        Modal.getOrCreateInstance(element).show();load();
    });
    form.elements.academic_year.addEventListener('change',load);
    form.addEventListener('input',event=>{
        if(event.target.name==='starting_sequence')get('[data-number-example]').textContent=digits(String(Math.max(1,Number(event.target.value)||1)).padStart(4,'0'));
        if(event.target.name!=='academic_year'&&settings)changed();
    });
    form.addEventListener('change',event=>{if(event.target.name!=='academic_year'&&settings)changed();});
    form.addEventListener('click',event=>{
        const button=event.target.closest('[data-number-move]');if(!button||busy)return;
        const from=Number(button.dataset.numberMove),to=from+Number(button.dataset.direction);[order[from],order[to]]=[order[to],order[from]];renderOrder();changed();
        get(`[data-number-move="${to}"][data-direction="${button.dataset.direction}"]`)?.focus();
    });
    save.addEventListener('click',async()=>{
        if(busy||!settings||!form.reportValidity())return;
        busy=true;error.hidden=true;success.hidden=true;invalidate();sync();
        try{
            const centers=settings.centers.map((center,i)=>({exam_center:center.exam_center,region:get(`[data-number-center-region="${i}"]`).value,priority:Number(get(`[data-number-center-priority="${i}"]`).value)}));
            const data=await api('/numbers/settings',{body:{academic_year:Number(form.elements.academic_year.value),version:settings.version,starting_sequence:Number(form.elements.starting_sequence.value),group_order:order,centers}});
            render(data);success.textContent='Certificate number settings saved.';success.hidden=false;
        }catch(problem){failure(problem);}finally{busy=false;sync();}
    });
    previewButton.addEventListener('click',async()=>{
        if(busy||!settings||dirty)return;
        busy=true;error.hidden=true;success.hidden=true;invalidate();status.textContent='Preparing the certificate number preview…';sync();
        try{
            preview=await api('/numbers/preview',{body:{academic_year:Number(form.elements.academic_year.value)}});
            const totals=preview.summary;
            get('[data-number-preview-totals]').innerHTML=[['Passed students',totals.passed,'ti-circle-check'],['Existing numbers kept',totals.existing,'ti-lock'],['New numbers to assign',totals.new,'ti-certificate']].map(([label,count,icon])=>`<div><i class="ti ${icon}" aria-hidden="true"></i><span>${label}</span><strong>${count.toLocaleString()}</strong></div>`).join('');
            get('[data-number-preview-rows]').innerHTML=preview.rows.map((row,i)=>`<tr><td data-label="No.">${i+1}</td><td data-label="Student"><strong class="school-profile-khmer">${escape(row.name_kh)}</strong><small>${escape(row.student_code)} · ${escape(row.campus)}</small></td><td data-label="Exam Center"><span class="school-profile-khmer">${escape(row.exam_center)}</span><small>${escape(row.group_label)}</small></td><td data-label="Certificate Number"><strong class="g9-number-preview-value">${escape(row.number)}</strong></td><td data-label="Assignment"><span class="badge bg-${row.is_new?'blue':'green'}-lt">${row.is_new?'New':'Existing'}</span></td></tr>`).join('')||'<tr><td colspan="5" class="text-center text-secondary py-4">No passed students in this academic year.</td></tr>';
            get('[data-number-preview]').hidden=false;status.textContent=totals.new?`Review ${totals.new} new numbers. Failed, absent and pending students are excluded.`:'No new numbers to generate. Existing assignments are kept.';
            get('[data-number-preview]').scrollIntoView({block:'start',behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'});
        }catch(problem){failure(problem);status.textContent='Check the settings and preview again.';}finally{busy=false;sync();}
    });
    generate.addEventListener('click',async()=>{
        if(busy||dirty||!preview?.summary.new)return;
        busy=true;error.hidden=true;success.hidden=true;sync();
        try{
            const result=await api('/numbers/generate',{body:{academic_year:Number(form.elements.academic_year.value),preview_token:preview.preview_token}});
            invalidate();success.textContent=result.message;success.hidden=false;status.textContent='Numbers generated. Preview again to see the fixed assignments.';
            await onGenerated();success.scrollIntoView({block:'nearest'});
        }catch(problem){invalidate();failure(problem);status.textContent='Review a fresh preview before generating.';}finally{busy=false;sync();}
    });
    element.addEventListener('hide.bs.modal',event=>{if(busy)event.preventDefault();});
    form.addEventListener('submit',event=>{event.preventDefault();if(!save.disabled)save.click();});
    sync();
}
