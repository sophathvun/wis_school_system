export function setupG9ImportProgress(form,options={}) {
    const copy={
        uploadTitle:'Uploading file',uploadLabel:'Import file upload',
        uploadStatus:'Uploading your file securely. Validation begins after the upload finishes.',
        processingTitle:'Validating candidate data',processingLabel:'Preparing import preview',
        processingValue:'File uploaded. Candidate validation is in progress.',
        processingStatus:'Checking rows, matching candidates and preparing your preview. Please keep this page open.',
        previewLabel:'Import preview',unavailable:'Import unavailable.',
        liveRows:false,
        checking:message=>({title:message.stage==='reading'?'Reading import file':message.stage==='finalizing'?'Preparing import preview':'Validating candidate data',count:message.total?`${Number(message.processed).toLocaleString()} of ${Number(message.total).toLocaleString()} rows checked`:'',status:message.stage==='reading'?'Reading your file before checking candidate records.':message.stage==='finalizing'?'All rows checked. Preparing the preview results…':'Checking candidate details and matching existing records. No candidate changes are saved during preview.'}),
        failureStatus:'Your file is still selected. Check the error below and click Preview Import to retry.',
        fileLabel:data=>data.get('file')?.name??'',
        completion:summary=>({review:summary.errors.length>0,status:summary.errors.length?`${summary.total.toLocaleString()} rows checked. Resolve the errors below before confirming.`:`${summary.total.toLocaleString()} rows checked. Review the results below before confirming the import.`}),
        ...options,
    };
    const get=key=>form.querySelector(`[data-g9-import-progress${key?'-'+key:''}]`);
    const panel=get(''),title=get('title'),note=get('note'),percent=get('percent');
    const track=get('track'),bar=get('bar'),status=get('status'),icon=get('icon');
    let uploaded=0,checked=null,filename='';
    const processing=()=>{
        if(checked!==null)return;
        uploaded=100;panel.dataset.state='processing';title.textContent=copy.processingTitle;
        percent.textContent='100% uploaded';bar.style.width='100%';
        track.removeAttribute('aria-valuenow');track.setAttribute('aria-label',copy.processingLabel);
        track.setAttribute('aria-valuetext',copy.processingValue);
        icon.className='ti ti-loader-2';status.textContent=copy.processingStatus;
    };
    const checking=message=>{
        const text=copy.checking(message);
        checked=Math.max(checked??0,Math.min(99,Number(message.percent)||0));
        panel.dataset.state='processing';icon.className='ti ti-loader-2';
        title.textContent=text.title;
        note.textContent=text.count?`${text.count} · ${filename}`:filename;
        percent.textContent=checked+'%';bar.style.width=checked+'%';track.setAttribute('aria-label',copy.processingLabel);
        track.setAttribute('aria-valuenow',String(checked));track.setAttribute('aria-valuetext',checked+'% checked. '+note.textContent);
        status.textContent=text.status;
    };
    return {
        reset(){panel.hidden=true;form.setAttribute('aria-busy','false');},
        preview(url,data){
            uploaded=0;checked=null;filename=copy.fileLabel(data);panel.hidden=false;panel.dataset.state='uploading';form.setAttribute('aria-busy','true');
            title.textContent=copy.uploadTitle;note.textContent=filename;
            percent.textContent='0%';bar.style.width='0%';icon.className='ti ti-cloud-upload';
            track.setAttribute('aria-label',copy.uploadLabel);track.setAttribute('aria-valuenow','0');track.setAttribute('aria-valuetext','0% uploaded');
            status.textContent=copy.uploadStatus;
            return new Promise((resolve,reject)=>{
                const xhr=new XMLHttpRequest();
                xhr.open('POST',url);xhr.withCredentials=true;
                xhr.setRequestHeader('Accept',copy.liveRows?'application/json, application/x-ndjson':'application/json');
                xhr.setRequestHeader('X-CSRF-TOKEN',document.querySelector('meta[name="csrf-token"]').content);
                let offset=0,buffer='',result=null,failed=false;
                const streamed=()=>xhr.getResponseHeader?.('Content-Type')?.includes('application/x-ndjson');
                const accept=line=>{
                    if(!line.trim())return;
                    const message=JSON.parse(line);
                    if(message.type==='progress')checking(message);
                    if(message.type==='complete')result=message;
                    if(message.type==='error')throw new Error(message.message||copy.unavailable);
                };
                const read=final=>{
                    buffer+=xhr.responseText.slice(offset);offset=xhr.responseText.length;
                    let end;while((end=buffer.indexOf('\n'))!==-1){accept(buffer.slice(0,end));buffer=buffer.slice(end+1);}
                    if(final){accept(buffer);buffer='';}
                };
                const streamFailure=error=>{if(failed)return;failed=true;reject(error instanceof SyntaxError?new Error('The preview response was interrupted. Please try again.'):error);xhr.abort?.();};
                xhr.upload.onprogress=event=>{
                    if(!event.lengthComputable||event.total<=0||panel.dataset.state!=='uploading')return;
                    uploaded=Math.max(uploaded,Math.min(100,Math.floor(event.loaded/event.total*100)));
                    percent.textContent=uploaded+'%';bar.style.width=uploaded+'%';
                    track.setAttribute('aria-valuenow',String(uploaded));track.setAttribute('aria-valuetext',uploaded+'% uploaded');
                    if(uploaded===100)processing();
                };
                xhr.upload.onload=processing;
                xhr.onprogress=()=>{if(!failed&&streamed())try{read(false);}catch(error){streamFailure(error);}};
                xhr.onload=()=>{
                    if(failed)return;
                    if(streamed()){
                        try{read(true);if(!result?.summary)throw new Error('The preview response was interrupted. Please try again.');resolve(result);}catch(error){streamFailure(error);}
                        return;
                    }
                    let response;
                    try{response=JSON.parse(xhr.responseText);}catch{reject(new Error('The server could not prepare the preview. Please try again.'));return;}
                    if(xhr.status<200||xhr.status>=300){reject(new Error(Object.values(response.errors??{}).flat().join('\n')||response.message||copy.unavailable));return;}
                    processing();resolve(response);
                };
                xhr.onerror=()=>reject(new Error('The upload connection was interrupted. Check your connection and preview the file again.'));
                xhr.onabort=()=>reject(new Error('Preview upload was cancelled. Select your file and try again.'));
                xhr.ontimeout=()=>reject(new Error('The preview request timed out. Please try again.'));
                xhr.send(data);
            });
        },
        complete(summary){
            const completed=copy.completion(summary);
            panel.dataset.state=completed.review?'review':'complete';form.setAttribute('aria-busy','false');
            title.textContent=completed.review?'Preview ready · review required':'Preview ready';percent.textContent='100%';bar.style.width='100%';icon.className=completed.review?'ti ti-alert-triangle':'ti ti-circle-check';
            track.setAttribute('aria-label',copy.previewLabel);track.setAttribute('aria-valuenow','100');track.setAttribute('aria-valuetext','Preview complete');
            status.textContent=completed.status;
        },
        fail(){
            panel.dataset.state='error';form.setAttribute('aria-busy','false');title.textContent='Preview could not be prepared';icon.className='ti ti-alert-circle';
            percent.textContent=checked!==null?checked+'% checked':uploaded===100?'100% uploaded':uploaded+'% uploaded';
            track.removeAttribute('aria-valuenow');track.setAttribute('aria-valuetext','Preview failed');
            status.textContent=copy.failureStatus;
        },
    };
}
