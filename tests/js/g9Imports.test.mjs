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
const file=(name='candidates.xlsx',size=2048)=>({name,size});
function setup({pause=false,fail=false,approval=true}={}){
    const root=new Element(),form=new Element(),input=new Element(),document=new Element();
    const elements=Object.fromEntries(['dropzone','file','filename','filemeta','remove','progress','progress-title','progress-note','progress-percent','progress-track','progress-bar','progress-status','progress-icon'].map(key=>[key,new Element()]));
    elements.progress.hidden=true;
    const preview=new Element(),confirm=new Element(),button=new Element(),error=new Element(),success=new Element();
    const confirmArea=new Element(),confirmProgress=Object.fromEntries(['','title','note','percent','track','bar','status','icon'].map(key=>[key,new Element()]));
    confirmArea.hidden=true;confirmProgress[''].hidden=true;confirmArea.querySelector=selector=>confirmProgress[selector.match(/data-g9-import-progress(?:-([^\]]+))?\]/)[1]??''];
    const summary=new Element(),issues=new Element(),rows=new Element();
    preview.hidden=true;confirm.disabled=true;form.elements={file:input};
    form.querySelector=selector=>elements[selector.match(/data-g9-import-([^\]]+)/)[1]];
    const map={'form':form,'preview-panel':preview,'import-form':form,'import-error':error,'import-success':success,'confirm':confirm,'preview':button,'summary':summary,'preview-errors':issues,'preview-rows':rows,'confirm-progress-area':confirmArea};
    root.dataset={base:'/g9-national-exams/imports-exports'};
    root.querySelector=selector=>map[selector.match(/data-g9-(.+)\]/)?.[1]]??null;
    document.querySelector=selector=>selector==='[data-g9-imports]'?root:selector==='meta[name="csrf-token"]'?{content:'csrf'}:document.modal?{}:null;
    document.createElement=()=>new Element();
    const requests=[],dialogs=[],xhrs=[];
    const context={document,console,TextDecoder,FormData:class{constructor(){this.values=new Map();}set(key,value){this.values.set(key,value);}get(key){return this.values.get(key);}},
        Swal:{fire:async options=>{dialogs.push(options);return {isConfirmed:approval};}},
        fetch:async(url,options)=>{
            requests.push({url,options});
            if(context.pause)await new Promise(resolve=>context.release=resolve);
            if(url.endsWith('/confirm')&&context.confirmReader)return {ok:true,headers:{get:()=> 'application/x-ndjson'},body:{getReader:()=>context.confirmReader}};
            return {ok:!context.fail,status:context.httpStatus??422,json:async()=>context.fail?{message:'File cannot be read.'}:context.reply??(url.endsWith('/confirm')?{summary:{new:1,updates:0}}:{token:'preview-token',summary:{total:1,new:1,updates:0,errors:[],warnings:[]},rows:[{row:2,action:'Add',student_code:'001',name_kh:'Candidate',name_en:'Candidate'}]})};
        },pause,fail,
    };
    context.XMLHttpRequest=class {
        constructor(){this.upload={};this.headers={};this.responseText='';xhrs.push(this);}
        open(method,url){this.method=method;this.url=url;}
        setRequestHeader(key,value){this.headers[key]=value;}
        getResponseHeader(){return context.previewStream?'application/x-ndjson':'application/json';}
        abort(){this.aborted=true;this.onabort?.();}
        async send(body){
            if(context.previewStream){requests.push({url:this.url,options:{method:this.method,headers:this.headers,body}});this.status=200;return;}
            const response=await context.fetch(this.url,{method:this.method,headers:this.headers,body});
            this.status=response.ok?200:422;this.responseText=JSON.stringify(await response.json());this.onload();
        }
    };
    vm.createContext(context);
    vm.runInContext(fs.readFileSync('resources/js/helpers/g9ImportUpload.js','utf8').replace('export function','function'),context);
    vm.runInContext(fs.readFileSync('resources/js/helpers/g9ImportProgress.js','utf8').replace('export function','function'),context);
    vm.runInContext(fs.readFileSync('resources/js/helpers/g9ImportConfirmation.js','utf8').replace('export function','function'),context);
    vm.runInContext(fs.readFileSync('resources/js/g9Imports.js','utf8').replace(/^import .*;\r?\n/gm,''),context);
    const choose=async files=>{input.files=files;await input.emit('change');};
    const submit=()=>form.emit('submit',{preventDefault(){}});
    const paste=async(files,{items,target,prevented=false}={})=>{
        const event={defaultPrevented:prevented,clipboardData:{files,items},target,preventDefault(){this.prevented=true;}};
        await document.emit('paste',event);return event;
    };
    return {context,document,form,input,zone:elements.dropzone,card:elements.file,name:elements.filename,meta:elements.filemeta,remove:elements.remove,preview,confirm,button,error,success,requests,dialogs,choose,submit,paste,xhrs,progress:elements,confirmArea,confirmProgress};
}

test('browse and remove select a file without uploading and expose its name and size safely',async()=>{
    const s=setup();assert.equal(s.card.hidden,true);assert.equal(s.button.disabled,true);
    await s.zone.click();assert.equal(s.input.clicked,true);
    await s.choose([file('<candidate>.CSV')]);
    assert.equal(s.name.textContent,'<candidate>.CSV');assert.equal(s.meta.textContent,'2 KB · Ready to preview');
    assert.equal(s.card.hidden,false);assert.equal(s.button.disabled,false);assert.equal(s.requests.length,0);
    await s.remove.click();assert.equal(s.card.hidden,true);assert.equal(s.button.disabled,true);assert.equal(s.zone.focused,true);
});

test('drag and drop selects XLSX and clears the drag highlight',async()=>{
    const s=setup(),dataTransfer={files:[file()],dropEffect:''};let prevented=0;
    await s.zone.emit('dragover',{dataTransfer,preventDefault(){prevented++;}});
    assert.equal(dataTransfer.dropEffect,'copy');assert.equal(s.zone.classes.has('is-dragging'),true);
    await s.zone.emit('drop',{dataTransfer,preventDefault(){prevented++;}});
    assert.equal(prevented,2);assert.equal(s.zone.classes.has('is-dragging'),false);assert.equal(s.name.textContent,'candidates.xlsx');
});

test('file paste supports clipboard files and item-only file lists without uploading',async()=>{
    const s=setup();const event=await s.paste([file('pasted.csv')]);
    assert.equal(event.prevented,true);assert.equal(s.name.textContent,'pasted.csv');
    await s.paste([],{items:[{kind:'string'},{kind:'file',getAsFile:()=>file('items.xlsx')}]});
    assert.equal(s.name.textContent,'items.xlsx');assert.equal(s.requests.length,0);
});

test('paste leaves editable fields, open modals, hidden upload areas and plain text alone',async()=>{
    const s=setup();
    assert.equal((await s.paste([file()],{target:{closest:()=>({})}})).prevented,undefined);
    s.document.modal=true;assert.equal((await s.paste([file()])).prevented,undefined);s.document.modal=false;
    s.zone.visible=false;assert.equal((await s.paste([file()])).prevented,undefined);s.zone.visible=true;
    assert.equal((await s.paste([],{items:[{kind:'string'}]})).prevented,undefined);
    assert.equal((await s.paste([file()],{prevented:true})).prevented,undefined);
    assert.equal(s.card.hidden,true);
});

for(const [files,message] of [
    [[file('photo.jpg')],'Choose an Excel'],[[file('empty.csv',0)],'empty'],
    [[file('large.xlsx',10*1024*1024+1)],'exceeds 10 MB'],[[file(),file('other.csv')],'one Excel'],
])test(`invalid file selection is rejected: ${message}`,async()=>{
    const s=setup();await s.choose([file()]);await s.choose(files);
    assert.match(s.error.textContent,new RegExp(message));assert.equal(s.zone['aria-invalid'],'true');
    assert.equal(s.card.hidden,true);assert.equal(s.button.disabled,true);assert.equal(s.requests.length,0);
});

test('preview sends the selected pasted file and replacement invalidates the previous token',async()=>{
    const s=setup(),selected=file('pasted.csv',10*1024*1024);await s.paste([selected]);await s.submit();
    assert.equal(s.requests[0].url,'/g9-national-exams/imports-exports/preview');
    assert.equal(s.requests[0].options.body.get('file'),selected);assert.equal(s.requests[0].options.headers['X-CSRF-TOKEN'],'csrf');
    assert.equal(s.preview.hidden,false);assert.equal(s.confirm.disabled,false);
    await s.choose([file('replacement.xlsx')]);assert.equal(s.preview.hidden,true);assert.equal(s.confirm.disabled,true);
    await s.confirm.click();assert.equal(s.dialogs.length,0);assert.equal(s.requests.length,1);
});

test('pending uploads prevent replacement, paste, removal and duplicate previews',async()=>{
    const s=setup({pause:true});await s.choose([file()]);const pending=s.submit();
    assert.equal(s.zone.disabled,true);assert.equal(s.button.disabled,true);assert.equal(s.remove.disabled,true);
    await s.paste([file('pasted.csv')]);await s.choose([file('replacement.csv')]);await s.remove.click();await s.submit();
    assert.equal(s.requests.length,1);assert.equal(s.name.textContent,'candidates.xlsx');
    s.context.release();await pending;assert.equal(s.zone.disabled,false);assert.equal(s.button.disabled,false);
});

test('a failed preview keeps the file available for retry and confirmation remains explicit',async()=>{
    const s=setup({fail:true});await s.choose([file()]);await s.submit();
    assert.equal(s.error.textContent,'File cannot be read.');assert.equal(s.button.disabled,false);assert.equal(s.preview.hidden,true);
    s.context.fail=false;await s.submit();assert.equal(s.requests.length,2);assert.equal(s.dialogs.length,0);
    await s.confirm.click();assert.equal(s.dialogs.length,1);assert.equal(s.requests[2].options.body.get('token'),'preview-token');
    assert.equal(s.preview.hidden,false);assert.equal(s.success.textContent,'Imported successfully: 1 added, 0 updated.');
    assert.equal(s.confirmProgress.percent.textContent,'100%');assert.equal(s.confirmProgress[''].dataset.state,'complete');
});

test('missing files cannot be previewed and reset clears the selected file and preview',async()=>{
    const s=setup();await s.submit();assert.match(s.error.textContent,/Choose, drop or paste/);assert.equal(s.requests.length,0);
    await s.choose([file()]);await s.submit();await s.form.emit('reset');
    assert.equal(s.card.hidden,true);assert.equal(s.preview.hidden,true);assert.equal(s.confirm.disabled,true);assert.equal(s.button.disabled,true);
});

test('preview displays actual upload percentages then validation until the response is ready',async()=>{
    const s=setup({pause:true});await s.choose([file()]);const pending=s.submit(),p=s.progress;
    assert.equal(p.progress.hidden,false);assert.equal(p.progress.dataset.state,'uploading');assert.equal(p['progress-percent'].textContent,'0%');
    assert.equal(s.form['aria-busy'],'true');assert.equal(s.xhrs[0].withCredentials,true);
    s.xhrs[0].upload.onprogress({lengthComputable:true,loaded:45,total:100});
    assert.equal(p['progress-percent'].textContent,'45%');assert.equal(p['progress-bar'].style.width,'45%');assert.equal(p['progress-track']['aria-valuenow'],'45');
    s.xhrs[0].upload.onprogress({lengthComputable:true,loaded:30,total:100});assert.equal(p['progress-percent'].textContent,'45%');
    s.xhrs[0].upload.onprogress({lengthComputable:false});assert.equal(p['progress-percent'].textContent,'45%');
    s.xhrs[0].upload.onprogress({lengthComputable:true,loaded:0,total:0});assert.equal(p['progress-percent'].textContent,'45%');
    s.xhrs[0].upload.onload();
    assert.equal(p.progress.dataset.state,'processing');assert.equal(p['progress-percent'].textContent,'100% uploaded');
    assert.equal(p['progress-track']['aria-valuenow'],undefined);assert.equal(s.preview.hidden,true);assert.equal(s.confirm.disabled,true);
    s.context.release();await pending;
    assert.equal(p.progress.dataset.state,'complete');assert.equal(p['progress-percent'].textContent,'100%');assert.equal(p['progress-title'].textContent,'Preview ready');
    assert.equal(s.form['aria-busy'],'false');assert.equal(s.preview.hidden,false);assert.equal(s.confirm.disabled,false);
    await s.choose([file('new.csv')]);assert.equal(p.progress.hidden,true);
});

test('100 percent upload starts validation without claiming the preview is ready',async()=>{
    const s=setup({pause:true});await s.choose([file()]);const pending=s.submit();
    s.xhrs[0].upload.onprogress({lengthComputable:true,loaded:100,total:100});
    assert.equal(s.progress.progress.dataset.state,'processing');assert.equal(s.progress['progress-title'].textContent,'Validating candidate data');
    assert.equal(s.button.disabled,true);assert.equal(s.confirm.disabled,true);
    s.context.release();await pending;
});

test('failed previews show the error state and retry resets progress without a false completion',async()=>{
    const s=setup({fail:true});await s.choose([file()]);await s.submit();
    assert.equal(s.progress.progress.dataset.state,'error');assert.equal(s.progress['progress-title'].textContent,'Preview could not be prepared');
    assert.equal(s.progress['progress-percent'].textContent,'0% uploaded');assert.equal(s.form['aria-busy'],'false');
    assert.equal(s.confirm.disabled,true);assert.equal(s.button.disabled,false);
    s.context.fail=false;await s.submit();assert.equal(s.progress.progress.dataset.state,'complete');
});

test('completed validation with row errors shows review status and keeps confirmation disabled',async()=>{
    const s=setup();s.context.reply={token:'preview-token',summary:{total:1,new:0,updates:0,errors:[{row:2,messages:['Unknown campus']}],warnings:[]},rows:[]};
    await s.choose([file()]);await s.submit();
    assert.equal(s.progress.progress.dataset.state,'review');assert.equal(s.progress['progress-percent'].textContent,'100%');
    assert.match(s.progress['progress-status'].textContent,/Resolve the errors/);assert.equal(s.confirm.disabled,true);
});

function confirmationStream(state){
    const queued=[];let waiting=null,released=false;
    state.context.confirmReader={read:()=>queued.length?Promise.resolve(queued.shift()):new Promise(resolve=>waiting=resolve),releaseLock(){released=true;}};
    const push=chunk=>{if(waiting){const resolve=waiting;waiting=null;resolve(chunk);}else queued.push(chunk);};
    return {send:message=>push({done:false,value:new TextEncoder().encode(JSON.stringify(message)+'\n')}),raw:value=>push({done:false,value}),close:()=>push({done:true}),released:()=>released};
}
const nextTick=()=>new Promise(resolve=>setImmediate(resolve));

test('confirmation shows real processed-row progress and reaches 100 only after the committed completion',async()=>{
    const s=setup();await s.choose([file()]);await s.submit();const stream=confirmationStream(s),pending=s.confirm.click();await nextTick();
    assert.equal(s.confirmArea.hidden,false);assert.equal(s.confirmProgress.percent.textContent,'0%');assert.equal(s.confirm.disabled,true);assert.equal(s.zone.disabled,true);
    assert.match(s.requests.at(-1).options.headers.Accept,/application\/x-ndjson/);
    stream.send({type:'progress',processed:1,total:2,percent:50,finalizing:false});await nextTick();
    assert.equal(s.confirmProgress.percent.textContent,'50%');assert.equal(s.confirmProgress.note.textContent,'1 of 2 records processed');
    await s.choose([file('blocked.csv')]);await s.confirm.click();assert.equal(s.requests.length,2);assert.equal(s.name.textContent,'candidates.xlsx');
    stream.send({type:'progress',processed:2,total:2,percent:99,finalizing:true});await nextTick();
    assert.equal(s.confirmProgress.percent.textContent,'99%');assert.equal(s.confirmProgress.title.textContent,'Finalizing import');assert.equal(s.success.textContent,'');
    stream.send({type:'complete',summary:{new:1,updates:1},percent:100});stream.close();await pending;
    assert.equal(s.confirmProgress.percent.textContent,'100%');assert.equal(s.confirmProgress.title.textContent,'Import completed');assert.equal(s.confirmProgress[''].dataset.state,'complete');
    assert.equal(s.confirmArea['aria-busy'],'false');assert.equal(s.zone.disabled,false);assert.equal(s.confirm.disabled,true);assert.equal(s.preview.hidden,false);assert.equal(stream.released(),true);
    await s.confirm.click();assert.equal(s.requests.length,2);
    await s.choose([file('replacement.csv')]);assert.equal(s.confirmArea.hidden,true);
});

test('streamed confirmation errors report rollback and never display successful completion',async()=>{
    const s=setup();await s.choose([file()]);await s.submit();const stream=confirmationStream(s),pending=s.confirm.click();await nextTick();
    stream.send({type:'progress',processed:1,total:2,percent:50});await nextTick();
    const bytes=new TextEncoder().encode(JSON.stringify({type:'error',status:409,message:'ទិន្នន័យ changed. Preview again.',rolled_back:true})+'\n');
    stream.raw(bytes.slice(0,32));stream.raw(bytes.slice(32));await pending;
    assert.match(s.error.textContent,/ទិន្នន័យ changed/);assert.match(s.confirmProgress.status.textContent,/No candidate changes were saved/);
    assert.equal(s.confirmProgress.percent.textContent,'50% processed');assert.equal(s.confirmProgress[''].dataset.state,'error');assert.equal(s.success.textContent,'');
    assert.equal(s.zone.disabled,false);assert.equal(s.button.disabled,false);assert.equal(s.confirm.disabled,true);
    await s.submit();assert.equal(s.confirmArea.hidden,true);assert.equal(s.confirm.disabled,false);
});

test('an interrupted confirmation asks users to check history instead of assuming rollback or success',async()=>{
    const s=setup();await s.choose([file()]);await s.submit();const stream=confirmationStream(s),pending=s.confirm.click();await nextTick();
    stream.send({type:'progress',processed:1,total:4,percent:25});stream.close();await pending;
    assert.equal(s.confirmProgress[''].dataset.state,'error');assert.match(s.error.textContent,/Check Your Import History/);
    assert.equal(s.confirmProgress.percent.textContent,'25% processed');assert.equal(s.success.textContent,'');assert.equal(s.zone.disabled,false);
});

test('cancelling the confirmation dialog leaves the import progress hidden and records untouched',async()=>{
    const s=setup({approval:false});await s.choose([file()]);await s.submit();await s.confirm.click();
    assert.equal(s.confirmArea.hidden,true);assert.equal(s.requests.length,1);assert.equal(s.confirm.disabled,false);
});

test('gateway failures do not claim rollback when the server may still be processing',async()=>{
    const s=setup();await s.choose([file()]);await s.submit();s.context.fail=true;s.context.httpStatus=504;await s.confirm.click();
    assert.match(s.error.textContent,/Check Your Import History/);assert.match(s.confirmProgress.status.textContent,/Check Your Import History/);
    assert.equal(s.confirmProgress[''].dataset.state,'error');assert.equal(s.success.textContent,'');
});

function previewStream(state){
    state.context.previewStream=true;
    return {
        send(message){const xhr=state.xhrs.at(-1);xhr.responseText+=JSON.stringify(message)+'\n';xhr.onprogress();},
        raw(text){const xhr=state.xhrs.at(-1);xhr.responseText+=text;xhr.onprogress();},
        finish(){state.xhrs.at(-1).onload();},
    };
}
test('preview validates rows live after upload and waits for the final preview before showing 100',async()=>{
    const s=setup();await s.choose([file()]);const stream=previewStream(s),pending=s.submit();
    assert.match(s.requests[0].options.headers.Accept,/application\/x-ndjson/);
    s.xhrs[0].upload.onload();assert.equal(s.progress['progress-percent'].textContent,'100% uploaded');
    stream.send({type:'progress',stage:'reading',processed:0,total:0,percent:0});
    assert.equal(s.progress['progress-title'].textContent,'Reading import file');assert.equal(s.progress['progress-percent'].textContent,'0%');
    stream.send({type:'progress',stage:'validating',processed:25,total:100,percent:25});
    assert.equal(s.progress['progress-percent'].textContent,'25%');assert.match(s.progress['progress-note'].textContent,/25 of 100 rows checked/);
    assert.equal(s.progress['progress-track']['aria-valuenow'],'25');assert.equal(s.preview.hidden,true);assert.equal(s.confirm.disabled,true);
    stream.send({type:'progress',stage:'validating',processed:50,total:100,percent:50});
    assert.equal(s.progress['progress-percent'].textContent,'50%');
    stream.send({type:'progress',stage:'finalizing',processed:100,total:100,percent:99});
    assert.equal(s.progress['progress-title'].textContent,'Preparing import preview');assert.equal(s.progress['progress-percent'].textContent,'99%');
    stream.send({type:'complete',token:'stream-preview',summary:{total:100,new:100,updates:0,errors:[],warnings:[]},rows:[]});
    assert.equal(s.progress['progress-percent'].textContent,'99%');stream.finish();await pending;
    assert.equal(s.progress['progress-percent'].textContent,'100%');assert.equal(s.preview.hidden,false);assert.equal(s.confirm.disabled,false);assert.equal(s.zone.disabled,false);
});

test('live preview counts invalid rows and shows review results rather than enabling confirmation',async()=>{
    const s=setup();await s.choose([file()]);const stream=previewStream(s),pending=s.submit();
    stream.send({type:'progress',stage:'validating',processed:1,total:2,percent:50});
    const message=JSON.stringify({type:'complete',token:'preview-with-errors',summary:{total:2,new:1,updates:0,errors:[{row:3,messages:['Unknown campus']}],warnings:[]},rows:[]})+'\n';
    stream.raw(message.slice(0,37));assert.equal(s.progress['progress-percent'].textContent,'50%');
    stream.raw(message.slice(37));stream.finish();await pending;
    assert.equal(s.progress.progress.dataset.state,'review');assert.equal(s.progress['progress-percent'].textContent,'100%');assert.equal(s.confirm.disabled,true);
});

test('streamed preview errors retain the checked percentage and selected file for retry',async()=>{
    const s=setup();await s.choose([file()]);const stream=previewStream(s),pending=s.submit();
    stream.send({type:'progress',stage:'validating',processed:1,total:2,percent:50});
    stream.send({type:'error',status:422,message:'Workbook cannot be read.'});await pending;
    assert.equal(s.error.textContent,'Workbook cannot be read.');assert.equal(s.progress['progress-percent'].textContent,'50% checked');
    assert.equal(s.progress.progress.dataset.state,'error');assert.equal(s.xhrs[0].aborted,true);assert.equal(s.button.disabled,false);assert.equal(s.confirm.disabled,true);assert.equal(s.name.textContent,'candidates.xlsx');
});

test('an incomplete preview stream fails without claiming 100 percent completion',async()=>{
    const s=setup();await s.choose([file()]);const stream=previewStream(s),pending=s.submit();
    stream.send({type:'progress',stage:'validating',processed:3,total:4,percent:75});stream.finish();await pending;
    assert.match(s.error.textContent,/interrupted/);assert.equal(s.progress['progress-percent'].textContent,'75% checked');assert.equal(s.preview.hidden,true);
});
