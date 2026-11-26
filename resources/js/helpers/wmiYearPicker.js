import {bindDropdownSearch, dropdownOptionMatches} from './dropdownSearch.js';

// Keep the native select as the source of truth for validation and form submission.
export function setupWmiYearPicker(select, searchLabel='WMI Year', optionIcon='ti-calendar') {
    const wrapper=document.createElement('div');wrapper.className='wmi-year-picker';
    select.before(wrapper);wrapper.append(select);select.classList.add('wmi-picker-native');select.tabIndex=-1;
    const trigger=document.createElement('button');trigger.type='button';trigger.className='wmi-picker-trigger';
    const label=document.createElement('span'), chevron=document.createElement('i');
    chevron.className='ti ti-chevron-down';chevron.setAttribute('aria-hidden','true');trigger.append(label,chevron);
    trigger.setAttribute('aria-expanded','false');trigger.setAttribute('aria-controls',`${select.id}Choices`);
    const fieldLabel=document.querySelector(`label[for="${select.id}"]`);
    if(fieldLabel){fieldLabel.id=`${select.id}Label`;trigger.id=`${select.id}Trigger`;fieldLabel.htmlFor=trigger.id;trigger.setAttribute('aria-labelledby',`${fieldLabel.id} ${select.id}Value`);}
    label.id=`${select.id}Value`;
    const panel=document.createElement('div');panel.className='wmi-picker-panel';panel.id=`${select.id}Choices`;panel.hidden=true;
    const search=document.createElement('input');search.type='search';search.className='form-control wmi-picker-search';
    search.placeholder=`Search ${searchLabel}…`;search.setAttribute('aria-label',`Search ${searchLabel}`);search.autocomplete='off';
    const list=document.createElement('div');list.className='wmi-picker-options';
    const status=document.createElement('div');status.className='wmi-picker-status';status.setAttribute('role','status');
    panel.append(search,list,status);wrapper.append(trigger,panel);
    const close=()=>{panel.hidden=true;trigger.setAttribute('aria-expanded','false');};
    const decorate=(container,option)=>{
        const path=option?.dataset.flag;
        if(!path&&!option?.dataset.primaryLabel)return;
        const text=document.createElement('span');text.textContent=option.dataset.primaryLabel||container.textContent;
        if(option.dataset.secondaryLabel){
            text.className='wmi-picker-bilingual';
            const secondary=document.createElement('small');secondary.textContent=option.dataset.secondaryLabel;text.append(secondary);
        }
        if(!path){container.replaceChildren(text);return;}
        const image=document.createElement('img');image.className='wmi-picker-flag';image.alt='';
        image.src=/^(?:https?:\/\/|\/)/i.test(path)?path:`/${path}`;
        image.addEventListener('error',()=>image.remove(),{once:true});
        container.replaceChildren(image,text);
    };
    const sync=()=>{
        label.textContent=select.selectedOptions[0]?.textContent||`Select ${searchLabel}`;
        decorate(label,select.selectedOptions[0]);
        const floatingField=select.closest('.wmi-modal-field,.wmi-filter-field');
        if(floatingField){
            label.textContent=select.value?label.textContent:'';
            floatingField.classList.toggle('wmi-has-value',Boolean(select.value));
        }
        trigger.disabled=select.disabled;if(select.disabled)close();
    };
    const render=()=>{
        list.replaceChildren();
        const query=search.value.trim().toLocaleLowerCase();
        const options=Array.from(select.options).filter(option=>!option.disabled&&!option.hidden&&(!select.required||option.value!=='')&&dropdownOptionMatches(option,query));
        options.forEach(option=>{
            const item=document.createElement('button');item.type='button';item.className='wmi-picker-option';
            item.classList.toggle('is-selected',option.value===select.value);item.setAttribute('aria-pressed',String(option.value===select.value));
            const icon=document.createElement('i');icon.className=`ti ${optionIcon}`;icon.setAttribute('aria-hidden','true');
            const text=document.createElement('span');text.textContent=option.textContent;
            decorate(text,option);
            const check=document.createElement('i');check.className='ti ti-check wmi-picker-check';check.setAttribute('aria-hidden','true');item.append(icon,text,check);
            item.addEventListener('click',()=>{select.value=option.value;sync();close();trigger.focus();select.dispatchEvent(new Event('change',{bubbles:true}));});
            list.append(item);
        });
        status.textContent=options.length?'':`No matching ${searchLabel.toLowerCase()}`;status.hidden=options.length>0;
    };
    trigger.addEventListener('click',()=>{if(!panel.hidden){close();return;}search.value='';render();panel.hidden=false;trigger.setAttribute('aria-expanded','true');search.focus();});
    bindDropdownSearch(search,render);
    document.addEventListener('pointerdown',event=>{if(!wrapper.contains(event.target))close();});
    wrapper.addEventListener('focusout',event=>{if(!wrapper.contains(event.relatedTarget))close();});
    select.addEventListener('invalid',event=>{event.preventDefault();trigger.focus();trigger.setAttribute('aria-invalid','true');});
    select.addEventListener('change',()=>{sync();trigger.removeAttribute('aria-invalid');});
    panel.addEventListener('keydown',event=>{
        if(event.isComposing||event.keyCode===229)return;
        if(event.key==='Escape'){event.preventDefault();event.stopPropagation();close();trigger.focus();return;}
        if(event.key==='Enter'&&event.target===search){event.preventDefault();list.querySelector('button')?.click();return;}
        if(!['ArrowDown','ArrowUp'].includes(event.key))return;
        event.preventDefault();const items=Array.from(list.querySelectorAll('button'));if(!items.length)return;
        const index=items.indexOf(document.activeElement);items[(index+(event.key==='ArrowDown'?1:-1)+items.length)%items.length].focus();
    });
    wrapper.addEventListener('focusout',event=>{if(!wrapper.contains(event.relatedTarget))close();});
    document.addEventListener('click',event=>{if(!wrapper.contains(event.target))close();});
    select.addEventListener('change',sync);
    select.addEventListener('invalid',event=>{event.preventDefault();trigger.focus();trigger.setAttribute('aria-invalid','true');});
    select.addEventListener('change',()=>trigger.removeAttribute('aria-invalid'));
    select.addEventListener('focus',()=>trigger.focus());
    select.closest('.modal')?.addEventListener('hide.bs.modal',close);
    sync();return {sync,focus:()=>trigger.focus()};
}
