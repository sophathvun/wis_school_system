const months=['January','February','March','April','May','June','July','August','September','October','November','December'];

export function reportMonthLabel(value) {
    const match=/^(\d{4})-(0[1-9]|1[0-2])$/.exec(value||'');
    return match?`${months[Number(match[2])-1]} ${match[1]}`:'';
}

/** A browser-independent month chooser; the original input retains the submitted ISO value. */
export function initReportMonthPicker(input) {
    if(!input||input.dataset.monthPickerBound)return;
    input.dataset.monthPickerBound='true';
    const field=input.parentElement,wrapper=document.createElement('div');wrapper.className='report-month-picker';
    const trigger=document.createElement('button');trigger.type='button';trigger.className='form-control report-month-trigger';
    trigger.innerHTML='<span></span><i class="ti ti-calendar" aria-hidden="true"></i>';
    trigger.setAttribute('aria-haspopup','dialog');trigger.setAttribute('aria-expanded','false');
    const popup=document.createElement('div');popup.className='report-month-popup';popup.hidden=true;popup.id='attendanceReportMonthPopup';popup.setAttribute('role','dialog');popup.setAttribute('aria-label','Choose report month');
    trigger.setAttribute('aria-controls',popup.id);
    popup.innerHTML='<div class="report-month-heading"><button type="button" class="btn btn-sm" data-month-prev aria-label="Previous year"><i class="ti ti-chevron-left"></i></button><input type="number" min="1" max="9999" step="1" aria-label="Year"><button type="button" class="btn btn-sm" data-month-next aria-label="Next year"><i class="ti ti-chevron-right"></i></button></div><div class="report-month-grid"></div>';
    const year=popup.querySelector('input'),grid=popup.querySelector('.report-month-grid');
    input.before(wrapper);wrapper.append(trigger,popup,input);input.type='hidden';
    const label=field.querySelector('.form-label');trigger.id='attendanceReportMonthToggle';if(label)label.htmlFor=trigger.id;
    const close=()=>{popup.hidden=true;trigger.setAttribute('aria-expanded','false');};
    const sync=()=>{trigger.querySelector('span').textContent=reportMonthLabel(input.value)||'Select month';};
    const render=()=>{
        grid.replaceChildren();
        months.forEach((name,index)=>{
            const button=document.createElement('button');button.type='button';button.className='report-month-option';button.textContent=name.slice(0,3);
            const value=`${String(Number(year.value)).padStart(4,'0')}-${String(index+1).padStart(2,'0')}`;
            button.setAttribute('aria-label',`${name} ${year.value}`);button.setAttribute('aria-pressed',String(input.value===value));
            button.addEventListener('click',()=>{
                if(!year.checkValidity()||!year.value){year.reportValidity();return;}
                const changed=input.value!==value;input.value=value;sync();close();trigger.focus();
                if(changed)input.dispatchEvent(new Event('change',{bubbles:true}));
            });grid.append(button);
        });
    };
    trigger.addEventListener('click',()=>{
        if(!popup.hidden){close();return;}
        year.value=reportMonthLabel(input.value)?input.value.slice(0,4):new Date().getFullYear();render();popup.hidden=false;trigger.setAttribute('aria-expanded','true');
        (grid.querySelector('[aria-pressed="true"]')||grid.querySelector('button')).focus();
    });
    year.addEventListener('input',render);
    year.addEventListener('change',event=>event.stopPropagation());
    for(const [selector,offset] of [['[data-month-prev]',-1],['[data-month-next]',1]])popup.querySelector(selector).addEventListener('click',()=>{year.value=Math.min(9999,Math.max(1,Number(year.value)+offset));render();});
    popup.addEventListener('keydown',event=>{
        if(event.key==='Escape'){event.preventDefault();event.stopPropagation();close();trigger.focus();}
        if(event.key==='Enter'&&event.target===year){event.preventDefault();if(year.checkValidity())grid.querySelector('button')?.focus();}
    });
    document.addEventListener('click',event=>{if(!wrapper.contains(event.target))close();});
    wrapper.addEventListener('focusout',event=>{if(event.relatedTarget&&!wrapper.contains(event.relatedTarget))close();});
    input.addEventListener('change',sync);input.form?.addEventListener('reset',()=>setTimeout(()=>{sync();close();},0));
    sync();return {close};
}
