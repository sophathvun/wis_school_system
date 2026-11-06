import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import {test} from 'node:test';

class Element {
    constructor(){this.events={};this.children=[];this.value='';this.textContent='';this.style={};this.dataset={};this.classes=new Set();this.classList={add:key=>this.classes.add(key),remove:key=>this.classes.delete(key),toggle:(key,on)=>on?this.classes.add(key):this.classes.delete(key)};}
    addEventListener(type,fn){(this.events[type]??=[]).push(fn);}
    async emit(type,event={}){for(const fn of this.events[type]??[])await fn(event);}
    setAttribute(key,value){this[key]=value;}
    removeAttribute(key){delete this[key];}
    append(...items){this.children.push(...items);}
    replaceChildren(...items){this.children=items;}
    insertRow(){const row=new Element();this.append(row);return row;}
    insertCell(){return this.insertRow();}
    contains(target){return this.children.includes(target);}
    getClientRects(){return this.visible===false?[]:[{}];}
    focus(){this.focused=true;}
    click(){this.clicked=true;return this.emit('click');}
}
const photo=(name='001.jpg',size=2048)=>({name,size});
function setup({readonly=false,canReplace=true,pause=false,fail=false,ready=2}={}){
    const root=new Element(),form=new Element(),input=new Element(),document=new Element(),year=new Element(),replace=new Element(),combo=new Element();
    year.value='1';year.options=[{value:'1',textContent:'2024-2025',dataset:{}}];year.selectedOptions=year.options;year.closest=()=>new Element();
    const names=['dropzone','selection','file-list','file-summary','clear','preview','preview-panel','confirm','progress','error','success','summary','rows','missing'];
    const elements=Object.fromEntries(names.map(key=>[key,new Element()]));elements['preview-panel'].hidden=true;elements.confirm.disabled=true;
    const p=Object.fromEntries(['','title','note','percent','track','bar','status','icon'].map(key=>[key,new Element()]));p[''].hidden=true;
    const confirmArea=new Element(),c=Object.fromEntries(['','title','note','percent','track','bar','status','icon'].map(key=>[key,new Element()]));confirmArea.hidden=true;c[''].hidden=true;
    confirmArea.querySelector=selector=>c[selector.match(/data-g9-import-progress(?:-([^\]]+))?\]/)[1]??''];elements['confirm-progress-area']=confirmArea;
    const get=selector=>selector.includes('data-g9-import-progress')?p[selector.match(/data-g9-import-progress(?:-([^\]]+))?\]/)[1]??'']:elements[selector.match(/data-photo-([^\]]+)/)?.[1]];
    form.elements={academic_year_id:year,replace_existing:canReplace?replace:null};form.querySelector=selector=>selector==='input[type=file]'?input:get(selector);
    root.querySelector=selector=>selector==='[data-photo-upload-form]'?form:readonly&&selector==='[data-photo-preview]'?null:get(selector);
    root.dataset={base:'/g9-national-exams/bulk-photos'};
    document.querySelector=selector=>selector==='[data-g9-bulk-photos]'?root:selector==='meta[name="csrf-token"]'?{content:'csrf'}:document.modal?{}:null;
    document.createElement=()=>new Element();
    const requests=[],dialogs=[],xhrs=[];
    const context={document,console,TextDecoder,FormData:class{
        constructor(form){this.values=new Map();if(form){this.set('academic_year_id',year.value);if(canReplace&&replace.checked&&!replace.disabled)this.set('replace_existing','1');}}
        set(key,value){this.values.set(key,[value]);}append(key,value){this.values.set(key,[...(this.values.get(key)??[]),value]);}delete(key){this.values.delete(key);}get(key){return this.values.get(key)?.[0]??null;}getAll(key){return this.values.get(key)??[];}
    },setupStaffLocationSelect:()=>({sync(){},toggle:combo}),
        Swal:{fire:async options=>{dialogs.push(options);if(context.approvalPending)await new Promise(resolve=>context.releaseApproval=resolve);return {isConfirmed:true};}},
        fetch:async(url,options)=>{
            requests.push({url,options});if(context.pause)await new Promise(resolve=>context.release=resolve);
            if(url.endsWith('/confirm')&&context.confirmReader)return {ok:true,headers:{get:()=> 'application/x-ndjson'},body:{getReader:()=>context.confirmReader}};
            const failed=context.fail||(url.endsWith('/confirm')&&context.failConfirm);
            return {ok:!failed,status:context.httpStatus??422,json:async()=>failed?{message:'Photo preview failed.'}:url.endsWith('/confirm')?{summary:{ready}}:{token:'photo-token',summary:{images:2,ready,existing:0,unmatched:2-ready,duplicates:0,invalid:0,missing:0},rows:[{filename:'001.jpg',student_code:'001',name_en:'Candidate',status:ready?'ready':'unmatched',message:'Photo match'}],missing:[]}};
        },pause,fail,
    };
    context.XMLHttpRequest=class{
        constructor(){this.upload={};this.headers={};this.responseText='';xhrs.push(this);}open(method,url){this.method=method;this.url=url;}setRequestHeader(key,value){this.headers[key]=value;}
        getResponseHeader(){return context.previewStream?'application/x-ndjson':'application/json';}abort(){this.aborted=true;this.onabort?.();}
        async send(body){if(context.previewStream){requests.push({url:this.url,options:{headers:this.headers,body}});this.status=200;return;}const result=await context.fetch(this.url,{method:this.method,headers:this.headers,body});this.status=result.ok?200:422;this.responseText=JSON.stringify(await result.json());this.onload();}
    };
    vm.createContext(context);
    for(const name of ['g9PhotoUpload','g9ImportProgress','g9ImportConfirmation'])vm.runInContext(fs.readFileSync(`resources/js/helpers/${name}.js`,'utf8').replace('export function','function'),context);
    vm.runInContext(fs.readFileSync('resources/js/g9BulkPhotos.js','utf8').replace(/^import .*;\r?\n/gm,''),context);
    const choose=async files=>{input.files=files;await input.emit('change');};
    const submit=()=>form.emit('submit',{preventDefault(){}});
    const paste=async(files,target)=>{const event={clipboardData:{files},target,preventDefault(){this.prevented=true;}};await document.emit('paste',event);return event;};
    return {context,form,input,document,year,replace,combo,e:elements,p,c,confirmArea,requests,dialogs,xhrs,choose,submit,paste};
}

test('browsing, dropping and pasting add multiple photos and ZIP files without uploading',async()=>{
    const s=setup();assert.equal(s.e.preview.disabled,true);assert.equal(s.e.selection.hidden,true);
    await s.e.dropzone.click();assert.equal(s.input.clicked,true);await s.choose([photo(),photo('002.PNG')]);
    const transfer={files:[photo('archive.zip')],dropEffect:''};await s.e.dropzone.emit('dragover',{dataTransfer:transfer,preventDefault(){}});
    assert.equal(transfer.dropEffect,'copy');await s.e.dropzone.emit('drop',{dataTransfer:transfer,preventDefault(){}});
    assert.equal(s.e.dropzone.classes.has('is-dragging'),false);assert.equal((await s.paste([photo('003.webp')])).prevented,true);
    assert.equal(s.e['file-list'].children.length,4);assert.match(s.e['file-summary'].textContent,/4 files selected/);assert.equal(s.requests.length,0);
    await s.e['file-list'].children[1].children[2].click();assert.equal(s.e['file-list'].children.length,3);
    assert.equal(s.e['file-list'].children[1].children[1].children[0].textContent,'archive.zip');
    await s.e.clear.click();assert.equal(s.e.selection.hidden,true);assert.equal(s.e.preview.disabled,true);
});

for(const [files,message] of [
    [[photo('invalid.pdf')],'Choose JPG'],[[photo('empty.jpg',0)],'empty'],[[photo('large.jpg',5*1024*1024+1)],'5 MB'],
    [[photo('large.zip',50*1024*1024+1)],'50 MB'],[Array.from({length:201},(_,i)=>photo(i+'.jpg')),'200 files'],
])test(`invalid photo batches are rejected without discarding previously selected files: ${message}`,async()=>{
    const s=setup();await s.choose([photo('kept.jpg')]);await s.choose(files);
    assert.match(s.e.error.textContent,new RegExp(message));assert.equal(s.e.dropzone['aria-invalid'],'true');
    assert.equal(s.e['file-list'].children.length,1);assert.equal(s.e['file-list'].children[0].children[1].children[0].textContent,'kept.jpg');assert.equal(s.requests.length,0);
});

test('read-only permission prevents file selection, paste, browse and preview requests',async()=>{
    const s=setup({readonly:true});assert.equal(s.e.dropzone.disabled,true);assert.equal(s.input.disabled,true);
    await s.choose([photo()]);await s.e.dropzone.click();await s.paste([photo()]);await s.submit();
    assert.equal(s.e['file-list'].children.length,0);assert.equal(s.requests.length,0);assert.equal(s.input.clicked,undefined);
});

test('paste respects text fields and open modals and supports clipboard item files',async()=>{
    const s=setup();assert.equal((await s.paste([photo()],{closest:()=>({})})).prevented,undefined);
    s.document.modal=true;assert.equal((await s.paste([photo()])).prevented,undefined);s.document.modal=false;
    await s.document.emit('paste',{clipboardData:{items:[{kind:'file',getAsFile:()=>photo('<pasted>.jpg')}]},preventDefault(){}});
    assert.equal(s.e['file-list'].children[0].children[1].children[0].textContent,'<pasted>.jpg');
});

test('preview sends all selected photos with academic year and replacement setting using real progress events',async()=>{
    const s=setup({pause:true}),first=photo('001.jpg',5*1024*1024),second=photo('photos.zip',50*1024*1024);
    await s.choose([first]);await s.paste([second]);s.replace.checked=true;const pending=s.submit();
    const request=s.requests[0];assert.equal(request.url,'/g9-national-exams/bulk-photos/preview');
    assert.deepEqual(Array.from(request.options.body.getAll('files[]')),[first,second]);assert.equal(request.options.body.get('academic_year_id'),'1');assert.equal(request.options.body.get('replace_existing'),'1');
    assert.equal(request.options.headers['X-CSRF-TOKEN'],'csrf');assert.equal(s.xhrs[0].withCredentials,true);
    s.xhrs[0].upload.onprogress({lengthComputable:true,loaded:42,total:100});assert.equal(s.p.percent.textContent,'42%');
    assert.equal(s.e.dropzone.disabled,true);assert.equal(s.combo.disabled,true);assert.equal(s.replace.disabled,true);
    await s.choose([photo('blocked.jpg')]);await s.e.clear.click();await s.submit();assert.equal(s.e['file-list'].children.length,2);assert.equal(s.requests.length,1);
    s.xhrs[0].upload.onload();assert.equal(s.p[''].dataset.state,'processing');assert.equal(s.p.title.textContent,'Matching student photos');assert.equal(s.p.percent.textContent,'100% uploaded');assert.equal(s.e.confirm.disabled,true);
    s.context.release();await pending;assert.equal(s.p[''].dataset.state,'complete');assert.equal(s.p.percent.textContent,'100%');assert.match(s.p.status.textContent,/2 images checked/);assert.equal(s.e.confirm.disabled,false);
    assert.equal(s.e.dropzone.disabled,false);assert.equal(s.dialogs.length,0);
});

test('unmatched photos finish in review state and cannot be confirmed without ready matches',async()=>{
    const s=setup({ready:0});await s.choose([photo()]);await s.submit();
    assert.equal(s.e['preview-panel'].hidden,false);assert.equal(s.p[''].dataset.state,'review');assert.equal(s.e.confirm.disabled,true);
});

test('changing academic year or replacement preference invalidates matching results while keeping files',async()=>{
    const s=setup();await s.choose([photo()]);await s.submit();s.year.value='2';await s.form.emit('change');
    assert.equal(s.e['preview-panel'].hidden,true);assert.equal(s.p[''].hidden,true);assert.equal(s.e.confirm.disabled,true);assert.equal(s.e['file-list'].children.length,1);
    await s.e.confirm.click();assert.equal(s.dialogs.length,0);
    await s.submit();s.replace.checked=true;await s.form.emit('change');assert.equal(s.e['preview-panel'].hidden,true);assert.equal(s.e.confirm.disabled,true);
});

test('failed previews retain selections for retry and confirmed uploads retain their completion progress',async()=>{
    const s=setup({fail:true});await s.choose([photo()]);await s.submit();
    assert.equal(s.p[''].dataset.state,'error');assert.equal(s.e.error.textContent,'Photo preview failed.');assert.equal(s.e.preview.disabled,false);assert.equal(s.e['file-list'].children.length,1);
    s.context.fail=false;await s.submit();await s.e.confirm.click();
    assert.equal(s.dialogs.length,1);assert.equal(s.requests[2].options.body.get('token'),'photo-token');
    assert.equal(s.e['preview-panel'].hidden,false);assert.equal(s.c[''].dataset.state,'complete');assert.equal(s.c.percent.textContent,'100%');assert.equal(s.e.confirm.disabled,true);assert.equal(s.e.success.textContent,'2 student photos uploaded successfully.');
});

test('stale confirmation cannot attach a different batch after the file selection changes',async()=>{
    const s=setup();await s.choose([photo()]);await s.submit();s.context.approvalPending=true;const pending=s.e.confirm.click();
    await s.choose([photo('002.jpg')]);s.context.releaseApproval();await pending;assert.equal(s.requests.length,1);assert.equal(s.e['preview-panel'].hidden,true);
});

const nextTick=()=>new Promise(resolve=>setImmediate(resolve));
test('preview shows live per-photo optimization and matching progress until results are ready',async()=>{
    const s=setup();await s.choose([photo('photos.zip')]);s.context.previewStream=true;const pending=s.submit(),xhr=s.xhrs[0];
    assert.match(xhr.headers.Accept,/application\/x-ndjson/);xhr.upload.onload();
    const send=message=>{xhr.responseText+=JSON.stringify(message)+'\n';xhr.onprogress();};
    send({type:'progress',stage:'reading',processed:0,total:0,percent:0});assert.equal(s.p.title.textContent,'Reading photo files');
    send({type:'progress',stage:'optimizing',processed:1,total:2,percent:25});assert.equal(s.p.percent.textContent,'25%');assert.match(s.p.note.textContent,/1 of 2 photos optimized/);
    send({type:'progress',stage:'optimizing',processed:2,total:2,percent:50});assert.equal(s.p.percent.textContent,'50%');
    send({type:'progress',stage:'matching',processed:1,total:2,percent:75});assert.equal(s.p.title.textContent,'Matching student photos');assert.match(s.p.note.textContent,/1 of 2 photos checked/);
    send({type:'progress',stage:'matching',processed:2,total:2,percent:99});assert.equal(s.p.percent.textContent,'99%');assert.equal(s.e['preview-panel'].hidden,true);
    send({type:'complete',token:'stream-photo-token',summary:{images:2,ready:2,existing:0,unmatched:0,duplicates:0,invalid:0,missing:0},rows:[],missing:[]});xhr.onload();await pending;
    assert.equal(s.p.percent.textContent,'100%');assert.equal(s.e.confirm.disabled,false);assert.equal(s.e.dropzone.disabled,false);
});

function confirmStream(s){
    const queue=[];let waiting;
    s.context.confirmReader={read:()=>queue.length?Promise.resolve(queue.shift()):new Promise(resolve=>waiting=resolve),releaseLock(){}};
    const push=value=>{if(waiting){const resolve=waiting;waiting=null;resolve(value);}else queue.push(value);};
    return {send:message=>push({done:false,value:new TextEncoder().encode(JSON.stringify(message)+'\n')}),close:()=>push({done:true})};
}
test('confirm upload reports actual saved-photo counts and waits for commit before showing 100',async()=>{
    const s=setup();await s.choose([photo()]);await s.submit();const stream=confirmStream(s),pending=s.e.confirm.click();await nextTick();
    assert.equal(s.c.percent.textContent,'0%');assert.equal(s.c.title.textContent,'Saving student photos');assert.equal(s.confirmArea.hidden,false);
    stream.send({type:'progress',processed:1,total:2,percent:50});await nextTick();assert.equal(s.c.percent.textContent,'50%');assert.equal(s.c.note.textContent,'1 of 2 photos processed');
    assert.equal(s.e.dropzone.disabled,true);assert.equal(s.e.confirm.disabled,true);assert.equal(s.combo.disabled,true);
    await s.e.confirm.click();await s.choose([photo('blocked.jpg')]);assert.equal(s.requests.length,2);assert.equal(s.e['file-list'].children.length,1);
    stream.send({type:'progress',processed:2,total:2,percent:99,finalizing:true});await nextTick();assert.equal(s.c.percent.textContent,'99%');assert.equal(s.c.title.textContent,'Finalizing photo upload');assert.equal(s.e.success.textContent,'');
    stream.send({type:'complete',summary:{ready:2},percent:100});stream.close();await pending;
    assert.equal(s.c.percent.textContent,'100%');assert.equal(s.c.title.textContent,'Photo upload completed');assert.equal(s.c.note.textContent,'2 student photos saved');assert.equal(s.e.confirm.disabled,true);assert.equal(s.e.dropzone.disabled,false);
    await s.choose([photo('new.jpg')]);assert.equal(s.confirmArea.hidden,true);
});
test('streamed preview failure does not enable confirm or discard selected photos',async()=>{
    const s=setup();await s.choose([photo()]);s.context.previewStream=true;const pending=s.submit(),xhr=s.xhrs[0];
    xhr.responseText=JSON.stringify({type:'progress',stage:'optimizing',processed:1,total:2,percent:25})+'\n';xhr.onprogress();
    xhr.responseText+=JSON.stringify({type:'error',status:422,message:'Invalid ZIP image.'})+'\n';xhr.onprogress();await pending;
    assert.equal(s.p.percent.textContent,'25% checked');assert.equal(s.p[''].dataset.state,'error');assert.equal(s.e.error.textContent,'Invalid ZIP image.');assert.equal(s.e.confirm.disabled,true);assert.equal(s.e.preview.disabled,false);
});
test('confirm upload rollback keeps progress below 100 and permits a fresh preview',async()=>{
    const s=setup();await s.choose([photo()]);await s.submit();const stream=confirmStream(s),pending=s.e.confirm.click();await nextTick();
    stream.send({type:'progress',processed:1,total:2,percent:50});stream.send({type:'error',status:409,message:'Candidate changed after preview.',rolled_back:true});await pending;
    assert.equal(s.c.percent.textContent,'50% processed');assert.equal(s.c[''].dataset.state,'error');assert.match(s.c.status.textContent,/No student photo changes were saved/);
    assert.equal(s.e.success.textContent,'');assert.equal(s.e.confirm.disabled,true);assert.equal(s.e.preview.disabled,false);await s.submit();assert.equal(s.confirmArea.hidden,true);assert.equal(s.e.confirm.disabled,false);
});
test('an interrupted photo confirmation asks users to check student photos rather than claim rollback',async()=>{
    const s=setup();await s.choose([photo()]);await s.submit();const stream=confirmStream(s),pending=s.e.confirm.click();await nextTick();
    stream.send({type:'progress',processed:1,total:2,percent:50});stream.close();await pending;
    assert.match(s.e.error.textContent,/Check the student photos/);assert.equal(s.c.percent.textContent,'50% processed');assert.equal(s.e.success.textContent,'');assert.equal(s.e.dropzone.disabled,false);
});
