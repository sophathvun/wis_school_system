export function initAttendanceSettingsTabs(){
    const root=document.querySelector('[data-att-settings]');
    if(!root)return;
    const mobile=matchMedia('(max-width:767.98px)');
    const toggle=root.querySelector('[data-att-settings-toggle]');
    const tabs=[...root.querySelectorAll('[data-att-settings-tab]')];
    const setExpanded=expanded=>{
        root.classList.toggle('is-collapsed',!expanded);
        toggle.setAttribute('aria-expanded',String(expanded));
        toggle.setAttribute('aria-label',`${expanded?'Collapse':'Expand'} settings tabs`);
        toggle.querySelector('i').className=`ti ti-layout-sidebar-left-${expanded?'collapse':'expand'}`;
    };
    setExpanded(!mobile.matches);
    mobile.addEventListener('change',()=>setExpanded(!mobile.matches));
    toggle.addEventListener('click',()=>setExpanded(toggle.getAttribute('aria-expanded')!=='true'));
    const activate=(tab,collapseOnMobile=true)=>{
        const selected=tab.dataset.attSettingsTab;
        tabs.forEach(button=>{const active=button===tab;button.setAttribute('aria-selected',String(active));button.tabIndex=active?0:-1;});
        root.querySelectorAll('[data-att-settings-panel]').forEach(panel=>{
            panel.hidden=panel.dataset.attSettingsPanel!==selected;
            if(panel.tagName==='FIELDSET')panel.disabled=panel.hidden;
        });
        const calendar=root.querySelector('[data-attendance-calendar]');
        calendar.hidden=selected==='schedules';
        calendar.classList.toggle('att-holidays-active',selected==='holidays');
        root.querySelector('[data-att-settings-title]').textContent=tab.textContent.trim();
        calendar.dispatchEvent(new Event('attendance-settings-tab-change'));
        if(mobile.matches&&collapseOnMobile){setExpanded(false);toggle.focus();}
    };
    tabs.forEach(tab=>{
        tab.addEventListener('click',()=>activate(tab));
        tab.addEventListener('keydown',event=>{
            const index=tabs.indexOf(tab);
            const next=event.key==='Home'?0:event.key==='End'?tabs.length-1:['ArrowDown','ArrowRight'].includes(event.key)?(index+1)%tabs.length:['ArrowUp','ArrowLeft'].includes(event.key)?(index-1+tabs.length)%tabs.length:null;
            if(next===null)return;event.preventDefault();activate(tabs[next],false);tabs[next].focus();
        });
    });
}
