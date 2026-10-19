import { initPremiumDatePicker } from './premiumDatePicker.js';

let sequence=0;
const pad=value=>String(value).padStart(2,'0');

/** Keep API/form values ISO and HH:mm while giving subjects editable premium controls. */
export function enhanceSubjectTemporal(value,{onChange=()=>{}}={}) {
    if(value._scholarshipTemporal)return value._scholarshipTemporal;
    const date=value.type==='date',initial=value.value,label=value.getAttribute('aria-label')||'Exam time';
    const wrapper=document.createElement('div');wrapper.className='date-picker scholarship-exam-date scholarship-temporal';
    value.replaceWith(wrapper);value.type='hidden';value.dataset.temporalReady='true';
    const input=document.createElement('input');input.type='text';input.className='form-control form-control-sm';input.autocomplete='off';input.disabled=value.disabled;
    input.id=`scholarshipTemporal${++sequence}`;input.setAttribute('aria-label',label);input.required=value.required;value.required=false;
    const toggle=document.createElement('button');toggle.type='button';toggle.className='date-picker-trigger date-picker-calendar-button';toggle.disabled=value.disabled;
    toggle.setAttribute('aria-label',date?`Open ${label} calendar`:`Choose ${label}`);toggle.setAttribute('aria-expanded','false');
    toggle.innerHTML=`<i class="ti ti-${date?'calendar':'clock'}" aria-hidden="true"></i>`;
    const popup=document.createElement('div');popup.className='date-picker-popup scholarship-temporal-popup d-none';popup.id=`${input.id}Popup`;popup.setAttribute('role','dialog');popup.setAttribute('aria-label',date?'Choose Exam Date':'Choose exam time');toggle.setAttribute('aria-controls',popup.id);
    wrapper.append(input,value,toggle,popup);
    let api;
    if(date){
        wrapper.dataset.premiumDateFormat='DD-MMM-YYYY';wrapper.dataset.premiumDatePlacement='auto';wrapper.dataset.premiumDatePortal='true';
        input.dataset.premiumDateInput='';value.dataset.premiumDateValue='';toggle.dataset.premiumDateToggle='';input.placeholder='DD-MMM-YYYY';
        popup.innerHTML='<div class="date-picker-header"><button type="button" class="date-picker-nav" data-premium-date-prev aria-label="Previous month"><i class="ti ti-chevron-left" aria-hidden="true"></i></button><button type="button" class="date-picker-year-toggle" aria-label="Choose year"></button><button type="button" class="date-picker-nav" data-premium-date-next aria-label="Next month"><i class="ti ti-chevron-right" aria-hidden="true"></i></button></div><div class="date-picker-year-popup d-none"><div class="date-picker-years"></div></div><div class="date-picker-grid"><div class="date-picker-weekdays"><span>SU</span><span>MO</span><span>TU</span><span>WE</span><span>TH</span><span>FR</span><span>SA</span></div><div class="date-picker-days"></div></div><div class="scholarship-calendar-footer"><button type="button" class="btn btn-sm btn-primary" data-premium-date-today>Today</button></div>';
        const calendar=initPremiumDatePicker(wrapper);
        const changed=()=>{input.classList.toggle('is-invalid',!input.validity.valid);onChange(value.value);};
        input.addEventListener('input',changed);value.addEventListener('change',changed);
        api={close:calendar.close,destroy:calendar.destroy,sync:()=>calendar.sync(),setValue:calendar.setValue};
    }else{
        const controller=new AbortController(),signal=controller.signal;
        input.placeholder='HH:mm';input.inputMode='text';input.maxLength=12;
        popup.classList.add('scholarship-time-popup');
        popup.innerHTML='<div class="date-picker-header"><i class="ti ti-clock" aria-hidden="true"></i><span>Choose time</span><span class="small">24-hour</span></div><div class="scholarship-time-columns"><label>Hour<select class="form-select" data-time-hour aria-label="Hour"></select></label><span>:</span><label>Minute<select class="form-select" data-time-minute aria-label="Minute"></select></label></div><div class="scholarship-calendar-footer"><button type="button" class="btn btn-sm btn-outline-secondary" data-time-clear>Clear</button><button type="button" class="btn btn-sm btn-primary" data-time-done>Done</button></div>';
        const hour=popup.querySelector('[data-time-hour]'),minute=popup.querySelector('[data-time-minute]');
        hour.replaceChildren(...Array.from({length:24},(_,i)=>new Option(pad(i),pad(i))));minute.replaceChildren(...Array.from({length:60},(_,i)=>new Option(pad(i),pad(i))));
        const parse=raw=>{
            let text=raw.trim().toLowerCase(),match=/^(\d{1,2})(?::([0-5]\d))?\s*(am|pm)$/.exec(text);
            if(match){let h=Number(match[1]);if(h<1||h>12)return null;return `${pad(h%12+(match[3]==='pm'?12:0))}:${match[2]||'00'}`;}
            if(/^\d{3,4}$/.test(text))text=text.slice(0,-2)+':'+text.slice(-2);
            match=/^(\d{1,2}):([0-5]\d)$/.exec(text);return match&&Number(match[1])<24?`${pad(Number(match[1]))}:${match[2]}`:null;
        };
        const sync=(format=true)=>{
            const raw=input.value.trim(),parsed=parse(raw);value.value=parsed||'';
            input.setCustomValidity(raw&&!parsed?'Enter a valid time, for example 08:30 or 2:30 PM.':'');
            input.classList.toggle('is-invalid',Boolean(raw&&!parsed));
            if(parsed){if(format)input.value=parsed;[hour.value,minute.value]=parsed.split(':');}
            onChange(value.value);
        };
        const close=()=>{popup.classList.add('d-none');wrapper.classList.remove('is-open');toggle.setAttribute('aria-expanded','false');if(popup.parentElement!==wrapper)wrapper.append(popup);};
        const position=()=>{
            const rect=wrapper.getBoundingClientRect(),viewport=window.visualViewport,width=Math.min(290,innerWidth-24),top=(viewport?.offsetTop||0)+12,bottom=(viewport?.offsetTop||0)+(viewport?.height||innerHeight)-12;
            Object.assign(popup.style,{position:'fixed',width:`${width}px`,maxWidth:`${width}px`,maxHeight:`${Math.max(0,bottom-top)}px`,overflowY:'auto',left:`${Math.max(12,Math.min(rect.left,innerWidth-width-12))}px`,top:'0px'});
            const height=popup.getBoundingClientRect().height;popup.style.top=`${Math.max(top,Math.min(rect.bottom+8+height<=bottom?rect.bottom+8:rect.top-height-8,bottom-height))}px`;
        };
        const open=()=>{if(input.matches(':disabled'))return;const parsed=parse(input.value)||'08:00';[hour.value,minute.value]=parsed.split(':');document.body.append(popup);popup.classList.remove('d-none');wrapper.classList.add('is-open');toggle.setAttribute('aria-expanded','true');position();};
        toggle.addEventListener('click',()=>popup.classList.contains('d-none')?open():close());
        input.addEventListener('input',()=>sync(false));input.addEventListener('blur',()=>sync());input.addEventListener('change',()=>sync());
        input.addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();sync();close();}if(event.key==='ArrowDown'){event.preventDefault();open();hour.focus();}if(event.key==='Escape'){close();toggle.focus();}});
        popup.querySelector('[data-time-done]').addEventListener('click',()=>{input.value=`${hour.value}:${minute.value}`;sync();close();toggle.focus();});
        popup.querySelector('[data-time-clear]').addEventListener('click',()=>{input.value='';sync();close();toggle.focus();});
        popup.addEventListener('keydown',event=>{if(event.key==='Escape'){event.stopPropagation();close();toggle.focus();}});
        document.addEventListener('click',event=>{if(!event.composedPath().includes(wrapper)&&!event.composedPath().includes(popup))close();},{signal});
        window.addEventListener('resize',()=>{if(!popup.classList.contains('d-none'))position();},{signal});
        window.addEventListener('scroll',()=>{if(!popup.classList.contains('d-none'))position();},{capture:true,passive:true,signal});
        window.visualViewport?.addEventListener('resize',()=>{if(!popup.classList.contains('d-none'))position();},{signal});
        input.form?.addEventListener('submit',event=>{sync();if(!input.checkValidity()){event.preventDefault();input.reportValidity();}},{signal});
        api={close,sync,setValue:raw=>{input.value=raw||'';sync();},destroy:()=>{close();controller.abort();}};
        input.value=initial?initial.slice(0,5):'';value.value=input.value;
    }
    popup.addEventListener('keydown',event=>{if(event.key==='Escape'){event.stopPropagation();api.close();toggle.focus();}});
    const control={...api,input,value,element:wrapper,valid:()=>{if(input.matches(':disabled'))return true;api.sync();return input.checkValidity();}};
    value._scholarshipTemporal=control;return control;
}
