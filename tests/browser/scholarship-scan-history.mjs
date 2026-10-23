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

    for(const width of [1440,390]){
        const seed=fixture('/history-sample');assert.equal(seed.status,200,Buffer.from(seed.content,'base64').toString());
        await command('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<500});
        await command('Page.navigate',{url:origin});await until('document.querySelector("#answerScansPagination .premium-pagination")!==null');
        await evaluate('(async()=>{const css=await(await fetch("/resources/css/app.css")).text(),style=document.createElement("style");style.textContent=css.slice(css.indexOf(".premium-pagination {"),css.indexOf("/* Branding uploads"))+css.slice(css.indexOf(".premium-pagination-list {"),css.indexOf("/* Responsive form and modal baseline */"));document.head.append(style);window.workspaceMarker="upload queue stays";})()');
        assert.equal(await evaluate('document.querySelectorAll("[data-answer-scan-row]").length'),10);
        assert.deepEqual(await evaluate('[...document.querySelector("#answerScansPagination select").options].map(o=>o.value)'),['10','25','50','100']);
        await click('#answerScansPagination [data-page="2"]');await until('JSON.parse(document.querySelector("[data-answer-scan-history-list]").dataset.pagination).current_page===2');
        assert.equal(await evaluate('document.querySelector("[data-answer-scan-row] td").textContent'),'11');
        await evaluate('(()=>{const input=document.querySelector("#answerScansPagination .premium-pagination-goto input");input.value=3;input.dispatchEvent(new Event("change"));})()');
        await until('JSON.parse(document.querySelector("[data-answer-scan-history-list]").dataset.pagination).current_page===3');
        assert.equal(await evaluate('document.querySelectorAll("[data-answer-scan-row]").length'),7);
        await click('[data-answer-review]');await until('document.querySelector("#answerReviewModal.show")!==null');await delay(350);await click('#answerReviewModal [data-bs-dismiss]');await until('getComputedStyle(document.querySelector("#answerReviewModal")).display==="none"');
        for(const size of [25,50,100,10]){
            await evaluate('(()=>{const input=document.querySelector("#answerScansPagination select");input.value="'+size+'";input.dispatchEvent(new Event("change"));})()');
            await until('JSON.parse(document.querySelector("[data-answer-scan-history-list]").dataset.pagination).per_page==='+size);
            assert.equal(await evaluate('document.querySelectorAll("[data-answer-scan-row]").length'),Math.min(size,27));
        }
        await evaluate('document.getElementById("answerScansPagination").scrollIntoView({block:"end",behavior:"instant"})');await delay(450);
        await writeFile(join(root,'storage/app/testing/scan-history-pagination-'+width+'.png'),Buffer.from((await command('Page.captureScreenshot',{format:'png'})).data,'base64'));
        await click('[data-answer-accept-all]');await until('document.querySelector(".swal2-title")?.textContent==="Accept All Pending Scans?"');
        assert.ok(await evaluate('document.querySelector(".swal2-html-container").textContent.includes("27 pending")&&document.querySelector(".swal2-html-container").textContent.includes("1 include flagged")'));
        await click('.swal2-confirm');await until('document.querySelector(".swal2-validation-message")?.textContent.includes("flagged")');await click('.swal2-cancel');await until('!document.querySelector(".swal2-popup")');
        assert.equal(JSON.parse(Buffer.from(fixture('/state').content,'base64').toString()).scans.filter(s=>s.status==='pending').length,27);
        await click('[data-answer-accept-all]');await until('document.querySelector("#answerAcceptFlagged")!==null');await click('#answerAcceptFlagged');
        await delay(450);
        await writeFile(join(root,'storage/app/testing/scan-history-accept-all-'+width+'.png'),Buffer.from((await command('Page.captureScreenshot',{format:'png'})).data,'base64'));
        await click('.swal2-confirm');await until('document.querySelector(".swal2-title")?.textContent==="Scans Accepted"');await click('.swal2-confirm');
        await until('document.querySelector("[data-answer-scan-history-list]").getAttribute("aria-busy")==="false"&&[...document.querySelectorAll("[data-answer-scan-row] .badge")].every(b=>b.textContent==="Accepted")');
        const state=JSON.parse(Buffer.from(fixture('/state').content,'base64').toString());assert.equal(state.scans.filter(s=>s.status==='accepted').length,27);assert.equal(state.scans.filter(s=>s.score===99).length,1);
        assert.equal(await evaluate('window.workspaceMarker'),'upload queue stays');assert.deepEqual(await evaluate('window.errors'),[]);
        console.log(width+'px: enrollment pagination, AJAX page/size/go-to, Review after paging, cancellation and confirmed flagged acceptance across all 27 scans passed.');
    }
    await command('Browser.close');
}finally{socket?.close();browser.kill();server.closeAllConnections();await new Promise(r=>server.close(r));}
