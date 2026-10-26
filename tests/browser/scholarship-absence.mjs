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
    const until=async expr=>{for(let i=0;i<120;i++){if(await evaluate(expr))return;await delay(100);}throw new Error('Timeout: '+expr+' '+await evaluate('JSON.stringify({error:document.querySelector(".swal2-popup")?.textContent,errors:window.errors,url:location.href,body:document.body.textContent.slice(0,500)})'));};
    const click=selector=>evaluate(`document.querySelector(${JSON.stringify(selector)}).click()`);

    await command('Page.navigate',{url:origin});await until('document.querySelector("[data-answer-filter-card]")?.dataset.resultFiltersReady==="true"');
    const pixels=async(mark,shade=0)=>evaluate('(async()=>{const svg=await(await fetch("/sample.svg")).text(),url=URL.createObjectURL(new Blob([svg],{type:"image/svg+xml"})),image=new Image();image.src=url;await image.decode();const c=document.createElement("canvas");c.width=1000;c.height=1414;const ctx=c.getContext("2d");ctx.drawImage(image,0,0,1000,1414);URL.revokeObjectURL(url);ctx.translate(628,220);ctx.scale(1.7,1.7);ctx.translate(-545,-263);ctx.strokeStyle=ctx.fillStyle="rgb('+shade+','+shade+','+shade+')";ctx.lineWidth=3;'+(mark==='hand-A'?'ctx.beginPath();ctx.moveTo(553,299);ctx.lineTo(568,270);ctx.lineTo(585,299);ctx.moveTo(559,289);ctx.lineTo(579,289);ctx.stroke();':mark==='blank'?'':'ctx.font="32px Arial";ctx.fillText('+JSON.stringify(mark)+',553,299);')+'return c.toDataURL().split(",")[1];})()');
    for(const [mark,shade,state]of [['blank',0,'blank'],['hand-A',0,'absent'],['A',0,'absent'],['A',210,'absent'],['X',0,'unclear'],['H',0,'unclear'],['V',0,'unclear'],['4',0,'unclear'],['?',0,'unclear']]){
        const image=await pixels(mark,shade);
        await writeFile(join(root,'storage/app/testing/absence-'+mark.replace('?','question')+'-'+shade+'.png'),Buffer.from(image,'base64'));
        const result=fixture('/read-layout',{image,configuration:{question_count:100,choices:['1','2','3','4','5'],layout_version:8,student_sheet:true}});
        assert.equal(result.status,200);const detection=JSON.parse(Buffer.from(result.content,'base64').toString());assert.equal(detection.absence.state,state,'Absence classifier: '+mark+'/'+shade+' '+JSON.stringify(detection.absence));
    }
    console.log('Real printed absence box: handwritten and font A, light-grey A, blank, X/H/V/4/question-mark rejection passed.');
    for(const width of [1440,390]){
        fixture('/absence-reset');await command('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<500});
        await command('Page.navigate',{url:origin});await until('document.querySelector("[data-answer-filter-card]")?.dataset.resultFiltersReady==="true"');
        const photo=await pixels('hand-A');
        await evaluate('(async()=>{const raw=Uint8Array.from(atob('+JSON.stringify(photo)+'),c=>c.charCodeAt(0)),blob=new Blob([raw],{type:"image/png"}),d=new DataTransfer();d.items.add(new File([blob],"absence-sheet.png",{type:"image/png"}));document.querySelector("[data-answer-dropzone]").dispatchEvent(new DragEvent("drop",{bubbles:true,cancelable:true,dataTransfer:d}));})()');
        const documentStart=await evaluate('performance.timeOrigin');
        await until('document.querySelector("[data-answer-aligned]").getAttribute("src")?.startsWith("data:")');await click('[data-answer-upload]');await until('document.querySelector(".swal2-icon-success")!==null');await click('.swal2-confirm');
        await until('performance.timeOrigin!=='+documentStart+'&&document.querySelector("[data-answer-filter-card]")?.dataset.resultFiltersReady==="true"&&!document.querySelector(".swal2-popup")');
        assert.ok(await evaluate('document.querySelector("[data-answer-scan-row]").textContent.includes("Auto Absent")'));
        await click('[data-answer-review]');await until('document.querySelector("#answerReviewModal.show")!==null');await delay(450);
        assert.equal(await evaluate('document.querySelector("[data-answer-review-form] input[name=absent]").checked'),true);
        assert.equal(await evaluate('[...document.querySelectorAll("[data-answer-response-grid] select")].every(s=>s.disabled)'),true);
        await writeFile(join(root,'storage/app/testing/absence-review-'+width+'.png'),Buffer.from((await command('Page.captureScreenshot',{format:'png'})).data,'base64'));
        if(width===1440){
            const beforeVerification=await evaluate('performance.timeOrigin');
            await evaluate('document.querySelector("[data-answer-review-form] input[name=confirmed]").checked=true');
            await click('[data-answer-verify]');await until('document.querySelector(".swal2-icon-success")!==null');await click('.swal2-confirm');
            await until('performance.timeOrigin!=='+beforeVerification+'&&document.querySelector("[data-answer-filter-card]")?.dataset.resultFiltersReady==="true"');
        }else{
            await click('#answerReviewModal [data-bs-dismiss]');await until('getComputedStyle(document.querySelector("#answerReviewModal")).display==="none"');
            await click('[data-answer-accept-all]');await until('document.querySelector(".swal2-title")?.textContent==="Accept All Pending Scans?"');assert.ok(await evaluate('document.querySelector(".swal2-html-container").textContent.includes("1 Auto Absent")'));
            await click('.swal2-confirm');await until('document.querySelector(".swal2-title")?.textContent==="Scans Accepted"');await click('.swal2-confirm');
        }
        await until('document.querySelector("[data-answer-scan-row]").textContent.includes("Accepted")');
        const state=JSON.parse(Buffer.from(fixture('/state').content,'base64').toString()),scan=state.scans[0],subject=state.scores.find(s=>s.source_reference===String(scan.id));
        assert.equal(scan.score,0);assert.equal(scan.absence.verified_absent,true);assert.equal(subject.is_absent,true);
        assert.equal(state.scores.filter(s=>s.subject_id!==subject.subject_id).every(s=>s.is_absent===false),true);
        await click('[data-answer-marked]');await until('document.querySelector("#answerMarkedModal.show")!==null');assert.ok(await evaluate('document.querySelector("[data-answer-marked-summary]").textContent.includes("Auto Absent")'));assert.equal(await evaluate('document.querySelectorAll("[data-answer-marked-overlay] circle").length'),0);
        await click('#answerMarkedModal [data-bs-dismiss]');await until('getComputedStyle(document.querySelector("#answerMarkedModal")).display==="none"');
        assert.deepEqual(await evaluate('window.errors'),[]);console.log(width+'px: printed A recognition, upload alignment, Auto Absent review, '+(width===1440?'individual verification':'bulk acceptance')+', subject-only zero/absence and marked view passed.');
    }
    await command('Page.navigate',{url:origin+'/name-fit-report'});
    await until('document.querySelectorAll("[data-answer-student-name]").length===3');
    const names=await evaluate('(async()=>{await document.fonts.ready;return [...document.querySelectorAll("[data-answer-student-name]")].map(n=>({size:Number(n.getAttribute("font-size")),width:n.getComputedTextLength(),limit:Number(n.dataset.fitWidth)}));})()');
    assert.equal(names[0].size,30);assert.equal(names[1].size,30);assert.ok(names[2].size<30);
    for(const name of names)assert.ok(name.width<=name.limit+.1,JSON.stringify(name));
    await command('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    await writeFile(join(root,'storage/app/testing/answer-large-absence-name-fit.png'),Buffer.from((await command('Page.captureScreenshot',{format:'png'})).data,'base64'));
    console.log('A4 report: short and medium names enlarge to 30px; long names fit within 380px without entering the absence box.');
    await command('Browser.close');
}finally{socket?.close();browser.kill();server.closeAllConnections();await new Promise(r=>server.close(r));}
