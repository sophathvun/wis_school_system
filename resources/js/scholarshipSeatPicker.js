import { createPopper } from '@popperjs/core';

/** Searchable single/multiple select; touch selections never depend on input blur. */
export function createSeatPicker(select) {
    const field=select.closest('.scholarship-field'),label=field.querySelector('label');
    field.classList.add('scholarship-seat-picker');
    const button=document.createElement('button');button.type='button';button.id=select.id+'Toggle';button.className='form-select scholarship-select-toggle';
    button.setAttribute('aria-haspopup','listbox');button.setAttribute('aria-expanded','false');label.htmlFor=button.id;
    const menu=document.createElement('div');menu.className='scholarship-select-menu';menu.hidden=true;
    const search=document.createElement('input');search.type='search';search.className='form-control';search.placeholder='Search...';search.dataset.searchNormalized='true';search.setAttribute('aria-label','Search '+label.textContent.trim());
    const results=document.createElement('div');results.className='scholarship-seat-options';results.id=select.id+'Options';results.setAttribute('role','listbox');
    if(select.multiple)results.setAttribute('aria-multiselectable','true');
    button.setAttribute('aria-controls',results.id);menu.append(search,results);select.hidden=true;select.after(button,menu);
    let popper;
    const close=()=>{menu.hidden=true;field.classList.remove('is-open');button.setAttribute('aria-expanded','false');popper?.destroy();popper=null;};
    const render=()=>{
        const focused=document.activeElement?.dataset.seatOption;
        const selected=[...select.selectedOptions].filter(option=>option.value!=='');
        button.textContent=selected.length?selected.map(option=>option.textContent).join(', '):select.dataset.placeholder;
        button.disabled=select.disabled;button.classList.toggle('is-invalid',select.classList.contains('is-invalid'));
        results.replaceChildren();
        const options=[...select.options].filter(option=>!option.disabled&&option.textContent.toLowerCase().includes(search.value.trim().toLowerCase()));
        options.forEach(option=>{
            const item=document.createElement('button');item.type='button';item.className='scholarship-select-option';item.dataset.seatOption=option.value;item.setAttribute('role','option');item.setAttribute('aria-selected',String(option.selected));
            if(select.multiple){const check=document.createElement('i');check.className='ti me-2 '+(option.selected?'ti-square-check':'ti-square');check.setAttribute('aria-hidden','true');item.append(check);}
            item.append(document.createTextNode(option.textContent));
            item.addEventListener('click',()=>{
                if(select.multiple)option.selected=!option.selected;else select.value=option.value;
                render();select.dispatchEvent(new Event('change',{bubbles:true}));
                if(!select.multiple){close();button.focus();}
            });results.append(item);
        });
        if(!options.length){const empty=document.createElement('div');empty.className='small text-secondary p-2';empty.textContent='No matches found.';results.append(empty);}
        if(focused!==undefined)[...results.querySelectorAll('button')].find(option=>option.dataset.seatOption===focused)?.focus();
        popper?.update();
    };
    const open=()=>{
        if(button.disabled)return;
        document.dispatchEvent(new CustomEvent('scholarship:close-seat-pickers',{detail:select}));
        search.value='';menu.hidden=false;field.classList.add('is-open');button.setAttribute('aria-expanded','true');
        menu.style.width=Math.min(Math.max(button.offsetWidth,260),innerWidth-24)+'px';
        render();popper=createPopper(button,menu,{strategy:'fixed',placement:'bottom-start',modifiers:[{name:'offset',options:{offset:[0,6]}},{name:'preventOverflow',options:{padding:12}},{name:'flip',options:{padding:12}}]});
        search.focus({preventScroll:true});
    };
    button.addEventListener('click',()=>menu.hidden?open():close());search.addEventListener('input',render);
    field.addEventListener('keydown',event=>{
        if(event.key==='Enter'&&event.target===search){event.preventDefault();results.querySelector('button')?.click();}
        if(event.key==='Escape'&&!menu.hidden){event.preventDefault();event.stopPropagation();close();button.focus();}
        if(['ArrowDown','ArrowUp'].includes(event.key)){
            event.preventDefault();if(menu.hidden){open();return;}
            const options=[...results.querySelectorAll('button')],index=options.indexOf(document.activeElement);
            options[(index+(event.key==='ArrowDown'?1:-1)+options.length)%options.length]?.focus();
        }
        if(event.key==='Tab')close();
    });
    document.addEventListener('pointerdown',event=>{if(!field.contains(event.target))close();});
    document.addEventListener('scholarship:close-seat-pickers',event=>{if(event.detail!==select)close();});
    select.closest('.modal')?.addEventListener('hidden.bs.modal',close);
    render();
    return {refresh:render,close,button,setOptions:(items,values=[])=>{
        const chosen=new Set([values].flat().map(String));select.replaceChildren();
        if(!select.multiple)select.add(new Option(select.dataset.placeholder,''));
        items.forEach(item=>select.add(new Option(item.label,String(item.id),false,chosen.has(String(item.id)))));
        render();
    }};
}
