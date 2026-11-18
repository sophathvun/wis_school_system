export function setupWmiNavigation(workspace, desktop) {
    const toggle=workspace.querySelector('[data-wmi-toggle]');
    if(!toggle)return;
    // Fresh visits use the device default; resizing across the breakpoint resets it.
    let expanded=desktop.matches;
    const apply=()=>{
        workspace.classList.toggle('is-expanded',expanded);
        workspace.classList.toggle('is-collapsed',!expanded);
        toggle.setAttribute('aria-expanded',String(expanded));
        const label=`${expanded?'Collapse':'Expand'} section navigation`;
        toggle.setAttribute('aria-label',label);toggle.title=label;
        toggle.querySelector('i').className=`ti ti-layout-sidebar-left-${expanded?'collapse':'expand'}`;
    };
    toggle.addEventListener('click',()=>{expanded=!expanded;apply();});
    desktop.addEventListener('change',()=>{expanded=desktop.matches;apply();});
    apply();
}
