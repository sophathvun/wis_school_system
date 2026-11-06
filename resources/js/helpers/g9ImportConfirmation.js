export function setupG9ImportConfirmation(container,options={}) {
    const copy={title:'Importing candidate records',finalizing:'Finalizing import',unit:'records',label:'Candidate import progress',
        running:'Keep this page open until the import finishes. Changes are saved together when the import succeeds.',finalStatus:'All rows processed. Finalizing the database transaction…',
        unavailable:'This import is unavailable. Preview the file again.',connectionError:'Import completion could not be confirmed. Check Your Import History before retrying.',
        complete:'Import completed',completion:summary=>`${summary.new.toLocaleString()} added · ${summary.updates.toLocaleString()} updated`,saved:'All imported records have been saved successfully.',
        failed:'Import not completed',rollback:'No candidate changes were saved. Preview the file again before retrying.',...options};
    const get=key=>container.querySelector(`[data-g9-import-progress${key?'-'+key:''}]`);
    const panel=get(''),title=get('title'),note=get('note'),percent=get('percent'),track=get('track'),bar=get('bar'),status=get('status'),icon=get('icon');
    let current=0;
    const update=message=>{
        current=Math.max(current,Math.min(99,Number(message.percent)||0));
        title.textContent=message.finalizing?copy.finalizing:copy.title;
        note.textContent=`${Number(message.processed).toLocaleString()} of ${Number(message.total).toLocaleString()} ${copy.unit} processed`;
        percent.textContent=current+'%';bar.style.width=current+'%';track.setAttribute('aria-valuenow',String(current));
        track.setAttribute('aria-valuetext',current+'% processed. '+note.textContent);
        status.textContent=message.finalizing?copy.finalStatus:copy.running;
    };
    const connectionError=()=>new Error(copy.connectionError);
    return {
        reset(){container.hidden=true;panel.hidden=true;container.setAttribute('aria-busy','false');},
        async run(url,data,total){
            current=0;container.hidden=false;panel.hidden=false;panel.dataset.state='processing';container.setAttribute('aria-busy','true');
            icon.className='ti ti-loader-2';track.setAttribute('aria-label',copy.label);
            update({processed:0,total,percent:0});container.scrollIntoView?.({block:'nearest'});
            try{
                const response=await fetch(url,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json, application/x-ndjson','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:data});
                let result;
                if(!response.ok){
                    const error=await response.json().catch(()=>({}));
                    const failure=new Error(Object.values(error.errors??{}).flat().join('\n')||error.message||copy.unavailable);
                    failure.rolledBack=response.status>=400&&response.status<500;throw failure;
                }
                if(response.headers?.get('Content-Type')?.includes('application/x-ndjson')&&response.body?.getReader){
                    const reader=response.body.getReader(),decoder=new TextDecoder();let buffer='';
                    const accept=line=>{
                        if(!line.trim())return;
                        const message=JSON.parse(line);
                        if(message.type==='progress')update(message);
                        if(message.type==='complete')result=message;
                        if(message.type==='error'){const failure=new Error(message.message);failure.rolledBack=message.rolled_back===true;throw failure;}
                    };
                    try{
                        while(true){
                            const chunk=await reader.read();
                            buffer+=decoder.decode(chunk.value,{stream:!chunk.done});
                            let end;while((end=buffer.indexOf('\n'))!==-1){accept(buffer.slice(0,end));buffer=buffer.slice(end+1);}
                            if(buffer.length>1024*1024)throw connectionError();
                            if(chunk.done)break;
                        }
                        accept(buffer);
                    }finally{reader.releaseLock();}
                    if(!result)throw connectionError();
                }else result=await response.json();
                if(!result?.summary)throw connectionError();
                panel.dataset.state='complete';title.textContent=copy.complete;percent.textContent='100%';bar.style.width='100%';icon.className='ti ti-circle-check';
                note.textContent=copy.completion(result.summary);
                track.setAttribute('aria-valuenow','100');track.setAttribute('aria-valuetext','Import committed successfully');
                status.textContent=copy.saved;
                return result;
            }catch(error){
                const failure=error.rolledBack===true?error:connectionError();
                panel.dataset.state='error';title.textContent=copy.failed;percent.textContent=current+'% processed';icon.className='ti ti-alert-circle';
                track.removeAttribute('aria-valuenow');track.setAttribute('aria-valuetext','Import did not complete');
                status.textContent=failure.rolledBack?copy.rollback:failure.message;
                throw failure;
            }finally{container.setAttribute('aria-busy','false');}
        },
    };
}
