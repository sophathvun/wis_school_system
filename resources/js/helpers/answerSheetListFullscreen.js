/** Expand only the list, returning to the page before opening external dialogs. */
export function initAnswerSheetListFullscreen(panel){
    const button=panel?.querySelector('[data-answer-list-fullscreen]');
    if(!button)return async()=>true;
    let fallback=false,changing=false,wasActive=false;
    const active=()=>fallback||document.fullscreenElement===panel;
    const sync=()=>{
        const expanded=active();panel.classList.toggle('is-answer-list-fullscreen',expanded);
        document.body.classList.toggle('answer-list-fullscreen-open',expanded);
        button.setAttribute('aria-pressed',String(expanded));
        button.querySelector('span').textContent=expanded?'Exit Full Screen':'Full Screen';
        button.querySelector('i').className=expanded?'ti ti-minimize me-2':'ti ti-maximize me-2';
        if(wasActive&&!expanded)button.focus({preventScroll:true});wasActive=expanded;
    };
    const exit=async()=>{
        fallback=false;
        try{if(document.fullscreenElement===panel)await document.exitFullscreen();}catch{/* Keep the restore button available if the browser declines. */}
        sync();return !active();
    };
    button.addEventListener('click',async()=>{
        if(changing)return;changing=true;
        try{
            if(active())await exit();
            else{
                try{if(!panel.requestFullscreen||!document.fullscreenEnabled)throw new Error('Use expanded view');await panel.requestFullscreen();}
                catch{fallback=true;}
                sync();
            }
        }finally{changing=false;}
    });
    document.addEventListener('fullscreenchange',sync);
    document.addEventListener('keydown',event=>{
        if(event.key==='Escape'&&fallback&&!document.querySelector('.modal.show,.swal2-popup:not(.swal2-toast)')){event.preventDefault();void exit();}
    });
    sync();return async()=>changing?false:exit();
}
