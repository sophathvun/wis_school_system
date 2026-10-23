import assert from 'node:assert/strict';
import { build } from 'vite';
import { createServer } from 'node:http';
import { spawn,execFileSync } from 'node:child_process';
import { readFile,mkdtemp,writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join,resolve } from 'node:path';

const root=resolve(import.meta.dirname,'../..'),php='C:/laragon/bin/php/php-8.4.25-Win32-vs17-x64/php.exe',chrome='C:/Program Files/Google/Chrome/Application/chrome.exe';
const temp=await mkdtemp(join(tmpdir(),'wis-answer-sheets-')),db=join(temp,'test.sqlite');await writeFile(db,'');
const bundle=await build({root,configFile:false,logLevel:'silent',build:{write:false,lib:{entry:{answers:join(root,'resources/js/scholarshipAnswerSheets.js'),scholarship:join(root,'resources/js/westernScholarship.js')},formats:['es'],fileName:(_,name)=>name+'.js'}}});
const files=new Map();for(const output of Array.isArray(bundle)?bundle:[bundle])for(const f of output.output)files.set('/'+f.fileName,f.type==='chunk'?f.code:f.source);
const fixture=(path,payload={})=>JSON.parse(execFileSync(php,[join(root,'tests/browser/scholarship-answer-sheets-fixture.php'),db,path],{cwd:root,input:JSON.stringify(payload),encoding:'utf8',maxBuffer:25*1024*1024}));
const server=createServer(async(req,res)=>{
    try{
        const url=new URL(req.url,'http://localhost');
        if(files.has(url.pathname)){res.setHeader('Content-Type',url.pathname.endsWith('.js')?'text/javascript':'text/css');res.end(files.get(url.pathname));return;}
        if(url.pathname.startsWith('/node_modules/')||url.pathname.startsWith('/resources/')){res.setHeader('Content-Type',url.pathname.endsWith('.css')?'text/css':'font/woff2');res.end(await readFile(join(root,url.pathname)));return;}
        const chunks=[];for await(const chunk of req)chunks.push(chunk);const body=Buffer.concat(chunks);let payload=Object.fromEntries(url.searchParams);
        if(req.headers['content-type']?.includes('multipart/form-data')){
            const form=await new Response(body,{headers:{'Content-Type':req.headers['content-type']}}).formData();
            for(const [k,v]of form)payload[k]=typeof v==='string'?v:Buffer.from(await v.arrayBuffer()).toString('base64');
        }else if(body.length)payload=JSON.parse(body.toString());
        const result=fixture(url.pathname,payload);res.statusCode=result.status;res.setHeader('Content-Type',result.type);
        let content=Buffer.from(result.content,'base64');
        if(result.type==='text/html'){
            let html=content.toString().replaceAll('http://localhost','').replaceAll('http:\\/\\/localhost','');
            if(!html.includes('answer-print-page'))html=`<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="fixture"><link rel="stylesheet" href="/node_modules/@tabler/core/dist/css/tabler.min.css"><link rel="stylesheet" href="/node_modules/@tabler/icons-webfont/dist/tabler-icons.min.css"><link rel="stylesheet" href="/resources/css/pages/western-scholarship.css"><link rel="stylesheet" href="/resources/css/pages/scholarship-answer-sheets.css"></head><body><div class="container-fluid p-3">${html}</div><script>window.errors=[];addEventListener('error',e=>errors.push(e.message));addEventListener('unhandledrejection',e=>errors.push(String(e.reason)));window.open=(url)=>{window.printUrl=url;return{location:{assign:u=>window.printUrl=u},close:()=>{}};};</script><script type="module" src="/answers.js"></script><script type="module" src="/scholarship.js"></script></body></html>`;
            content=html;
        }else if(result.type.includes('json'))content=content.toString().replaceAll('http:\\/\\/localhost','').replaceAll('http://localhost','');
        res.end(content);
    }catch(e){res.statusCode=500;res.end(JSON.stringify({message:e.stack}));}
});
await new Promise(r=>server.listen(0,'127.0.0.1',r));const origin=`http://127.0.0.1:${server.address().port}`;
const browser=spawn(chrome,['--headless=new','--no-sandbox','--disable-gpu','--no-first-run','--remote-debugging-port=0',`--user-data-dir=${join(temp,'chrome')}`,'about:blank'],{windowsHide:true,stdio:'ignore'});
let socket;const delay=ms=>new Promise(r=>setTimeout(r,ms));
try{
    let port;for(let i=0;i<100;i++){try{port=Number((await readFile(join(temp,'chrome/DevToolsActivePort'),'utf8')).split('\n')[0]);break;}catch{await delay(100);}}
    const pages=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();socket=new WebSocket(pages.find(p=>p.type==='page').webSocketDebuggerUrl);await new Promise(r=>socket.addEventListener('open',r,{once:true}));
    let id=0;const pending=new Map();socket.addEventListener('message',({data})=>{const msg=JSON.parse(data),h=pending.get(msg.id);if(h){pending.delete(msg.id);msg.error?h[1](new Error(JSON.stringify(msg.error))):h[0](msg.result);}});
    const command=(method,params={})=>new Promise((resolve,reject)=>{const n=++id;pending.set(n,[resolve,reject]);socket.send(JSON.stringify({id:n,method,params}));});
    const evaluate=async expression=>{const r=await command('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw new Error(r.exceptionDetails.exception?.description||JSON.stringify(r.exceptionDetails));return r.result.value;};
    const until=async expr=>{for(let i=0;i<120;i++){if(await evaluate(expr))return;await delay(100);}throw new Error('Timeout: '+expr+' '+await evaluate('JSON.stringify({error:document.querySelector(".swal2-popup")?.textContent,errors:window.errors})'));};
    const click=selector=>evaluate(`document.querySelector(${JSON.stringify(selector)}).click()`);
    const settled=()=>until('document.querySelector("[data-answer-sheets]").getAttribute("aria-busy")==="false"');
    await command('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    await command('Page.navigate',{url:origin});await until('document.querySelector("[data-answer-filter-card]")?.dataset.resultFiltersReady==="true"');
    const response=fixture('/batch-samples');assert.equal(response.status,200,Buffer.from(response.content,'base64').toString().slice(0,500));
    const samples=JSON.parse(Buffer.from(response.content,'base64').toString());assert.equal(samples.sheets.length,25);
    await evaluate(`window.batchSamples=${JSON.stringify(samples)};window.batchFiles=[];window.activeUploads=0;window.maxActiveUploads=0;window.activeDecodes=0;window.maxActiveDecodes=0;window.scanRequests=0;
        const realBitmap=window.createImageBitmap;window.createImageBitmap=async(...args)=>{activeDecodes++;maxActiveDecodes=Math.max(maxActiveDecodes,activeDecodes);try{const image=await realBitmap(...args),close=image.close.bind(image);image.close=()=>{activeDecodes--;close();};return image;}catch(error){activeDecodes--;throw error;}};
        const realFetch=window.fetch;window.fetch=async(url,options)=>{if(!String(url).match(/\\/exams\\/\\d+\\/scans$/))return realFetch(url,options);activeUploads++;scanRequests++;maxActiveUploads=Math.max(maxActiveUploads,activeUploads);try{await new Promise(r=>setTimeout(r,350));if(options.body.get('original').name==='retry-me.png'&&!window.failedOnce){window.failedOnce=true;throw new Error('Temporary connection failure. Please retry this sheet.');}return await realFetch(url,options);}finally{activeUploads--;}};`);
    await evaluate(`(async()=>{
        for(let i=0;i<25;i++){
            const svg=i===23?batchSamples.wrong_svg:batchSamples.sheets[i].svg,doc=new DOMParser().parseFromString(svg,'image/svg+xml');
            if(i===22)for(const marker of [...doc.querySelectorAll('rect')].filter(r=>r.getAttribute('width')==='20'&&r.getAttribute('height')==='20'))marker.setAttribute('fill','#777');
            const image=new Image(),url=URL.createObjectURL(new Blob([new XMLSerializer().serializeToString(doc.documentElement)],{type:'image/svg+xml'}));image.src=url;await image.decode();
            const c=document.createElement('canvas');c.width=1000;c.height=1414;const ctx=c.getContext('2d');ctx.drawImage(image,0,0,1000,1414);URL.revokeObjectURL(url);
            const dots=[...doc.querySelectorAll('circle')];ctx.fillStyle='black';if(i!==22)for(let question=0;question<100;question++){const dot=dots[question*5];ctx.beginPath();ctx.arc(Number(dot.getAttribute('cx')),Number(dot.getAttribute('cy')),Number(dot.getAttribute('r'))-.6,0,Math.PI*2);ctx.fill();}
            batchFiles.push(new File([await new Promise(r=>c.toBlob(r))],i===24?'retry-me.png':('student-'+(i+1)+'.png'),{type:'image/png'}));c.width=c.height=0;
        }
        const d=new DataTransfer();batchFiles.forEach(file=>d.items.add(file));document.querySelector('[data-answer-dropzone]').dispatchEvent(new DragEvent('drop',{bubbles:true,cancelable:true,dataTransfer:d}));
    })()`);
    await until('document.querySelectorAll("[data-answer-queue-id]").length===25&&document.querySelector("[data-answer-sheets]").getAttribute("aria-busy")==="false"');
    assert.equal(await evaluate('document.querySelectorAll("[data-answer-queue-id][data-state=ready]").length'),24);
    assert.equal(await evaluate('document.querySelectorAll("[data-answer-queue-id][data-state=alignment]").length'),1);
    // A second selection cannot silently exceed the total 25-photo limit.
    await evaluate(`(()=>{const d=new DataTransfer();d.items.add(batchFiles[0]);document.querySelector('[data-answer-dropzone]').dispatchEvent(new ClipboardEvent('paste',{bubbles:true,cancelable:true,clipboardData:d}));})()`);
    await until('document.querySelector(".swal2-icon-error")!==null');assert.ok(await evaluate('document.querySelector(".swal2-popup").textContent.includes("25")'));await click('.swal2-confirm');await settled();
    assert.equal(await evaluate('document.querySelectorAll("[data-answer-queue-id]").length'),25);
    await click('[data-answer-batch-start]');await until('activeUploads===1');
    assert.equal(await evaluate('document.getElementById("answerPaper").disabled'),true,'Paper filters are locked while processing');
    await click('[data-answer-batch-pause]');await settled();
    assert.equal(await evaluate('document.querySelectorAll("[data-answer-queue-id][data-state=uploaded]").length'),1,'Pause finishes the current request');
    assert.equal(await evaluate('document.querySelector("[data-answer-batch-start-label]").textContent'),'Resume Batch');
    await click('[data-answer-batch-start]');
    await until('document.querySelectorAll("[data-answer-queue-id][data-state=uploaded]").length===22&&document.querySelector("[data-answer-sheets]").getAttribute("aria-busy")==="false"');
    assert.equal(await evaluate('document.querySelectorAll("[data-answer-queue-id][data-state=failed]").length'),2,'Wrong paper and transient failure are isolated');
    assert.equal(await evaluate('maxActiveUploads'),1,'Only one upload request is in flight');
    assert.equal(await evaluate('maxActiveDecodes'),1,'Only one full photo is decoded at a time');
    assert.equal(await evaluate('activeDecodes'),0,'Image bitmaps are released');
    assert.ok(await evaluate('document.querySelector("[data-answer-batch-list]").textContent.includes("does not match")'));
    const requestsBeforeRetry=await evaluate('scanRequests');await click('[data-answer-batch-retry]');await settled();
    assert.equal(await evaluate('scanRequests'),requestsBeforeRetry+2,'Retry Failed sends only failed entries');
    assert.equal(await evaluate('document.querySelectorAll("[data-answer-queue-id][data-state=uploaded]").length'),23);
    assert.equal(await evaluate('document.querySelectorAll("[data-answer-queue-id][data-state=failed]").length'),1);
    // Pale but readable markers need manual alignment; other sheets already uploaded.
    await evaluate(`[...document.querySelector('[data-answer-queue-id][data-state=alignment]').querySelectorAll('button')].find(b=>b.textContent.includes('Align')).click()`);await settled();
    await click('[data-answer-corners]');
    for(const [x,y]of [[35,35],[965,35],[965,1379],[35,1379]]){
        await evaluate(`(()=>{const c=document.getElementById('answerAlignment'),r=c.getBoundingClientRect();c.dispatchEvent(new PointerEvent('pointerdown',{bubbles:true,cancelable:true,clientX:r.left+${x}*r.width/1000,clientY:r.top+${y}*r.height/1414}));})()`);await settled();
    }
    assert.equal(await evaluate('document.querySelectorAll("[data-answer-queue-id][data-state=alignment]").length'),0);
    await click('[data-answer-batch-start]');await settled();
    assert.equal(await evaluate('document.querySelectorAll("[data-answer-queue-id][data-state=uploaded]").length'),24);
    await evaluate(`[...document.querySelector('[data-answer-queue-id][data-state=failed]').querySelectorAll('button')].find(b=>b.textContent.includes('Remove')).click()`);
    await evaluate(`(async()=>{const image=new Image(),url=URL.createObjectURL(new Blob([batchSamples.sheets[23].svg],{type:'image/svg+xml'}));image.src=url;await image.decode();const c=document.createElement('canvas');c.width=1000;c.height=1414;c.getContext('2d').drawImage(image,0,0,1000,1414);URL.revokeObjectURL(url);const d=new DataTransfer();d.items.add(new File([await new Promise(r=>c.toBlob(r))],'correct-paper.png',{type:'image/png'}));document.querySelector('[data-answer-dropzone]').dispatchEvent(new ClipboardEvent('paste',{bubbles:true,cancelable:true,clipboardData:d}));})()`);await settled();
    await click('[data-answer-batch-start]');await settled();
    assert.equal(await evaluate('document.querySelectorAll("[data-answer-queue-id][data-state=uploaded]").length'),25);
    assert.equal(await evaluate('document.querySelector("[data-answer-batch-progress]").value'),25);
    let state=JSON.parse(Buffer.from(fixture('/state').content,'base64').toString());
    assert.equal(state.scans.length,25);assert.ok(state.scans.every(scan=>scan.status==='pending'));assert.equal(state.scores.length,0,'Batch upload never saves scores');
    // Live review actions work without discarding the completed queue.
    await click('[data-answer-queue-id] [data-answer-review]');await until('document.querySelector("#answerReviewModal.show")!==null');
    await click('#answerReviewModal [data-bs-dismiss]');await until('getComputedStyle(document.querySelector("#answerReviewModal")).display==="none"');
    for(const width of [1440,390]){
        await command('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<500});
        await evaluate(`document.querySelector('[data-answer-batch]').scrollIntoView({block:'start',behavior:'instant'})`);await delay(150);
        assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'),true,'Queue fits viewport');
        await writeFile(join(root,`storage/app/testing/answer-batch-${width}.png`),Buffer.from((await command('Page.captureScreenshot',{format:'png'})).data,'base64'));
    }
    await click('[data-answer-batch-clear]');assert.equal(await evaluate('document.querySelector("[data-answer-batch]").hidden'),true);
    state=JSON.parse(Buffer.from(fixture('/state').content,'base64').toString());assert.equal(state.scans.length,25,'Clearing only removes the local queue');
    // Previously uploaded images and corrupt images report per-file errors.
    await evaluate(`(()=>{const d=new DataTransfer();d.items.add(batchFiles[0]);d.items.add(new File(['bad'],'corrupt.png',{type:'image/png'}));document.querySelector('[data-answer-dropzone]').dispatchEvent(new DragEvent('drop',{bubbles:true,cancelable:true,dataTransfer:d}));})()`);await settled();
    await click('[data-answer-batch-start]');await settled();
    assert.equal(await evaluate('document.querySelectorAll("[data-answer-queue-id][data-state=failed]").length'),2);
    assert.ok(await evaluate('document.querySelector("[data-answer-batch-list]").textContent.includes("already uploaded")'));
    state=JSON.parse(Buffer.from(fixture('/state').content,'base64').toString());assert.equal(state.scans.length,25);assert.equal(state.scores.length,0);
    assert.deepEqual(await evaluate('window.errors'),[]);
    console.log('25-sheet queue: sequential upload/decoding, pause/resume, limit, isolated failures, retry, manual alignment, student identity, duplicate rejection, pending verification, live review and desktop/mobile layouts passed.');
    await command('Browser.close');
}finally{socket?.close();browser.kill();server.closeAllConnections();await new Promise(r=>server.close(r));}
