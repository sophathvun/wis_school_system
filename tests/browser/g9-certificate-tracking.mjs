import assert from 'node:assert/strict';
import {build} from 'vite';
import {createServer} from 'node:http';
import {spawn,execFileSync} from 'node:child_process';
import {readFile,mkdtemp,writeFile,mkdir} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join,resolve} from 'node:path';
const root=resolve(import.meta.dirname,'../..'),php='C:/laragon/bin/php/php-8.4.25-Win32-vs17-x64/php.exe';
const temp=await mkdtemp(join(tmpdir(),'wis-g9-tracking-')),db=join(temp,'test.sqlite');await writeFile(db,'');
const bundle=await build({root,configFile:false,logLevel:'silent',build:{write:false,lib:{entry:{tracking:join(root,'resources/js/g9CertificateTracking.js'),forms:join(root,'resources/js/premiumForms.js')},formats:['es'],fileName:(_format,name)=>name+'.js',cssFileName:'g9-tracking-browser'}}});
const files=new Map();for(const output of Array.isArray(bundle)?bundle:[bundle])for(const f of output.output)files.set('/'+f.fileName,f.type==='chunk'?f.code:f.source);
const server=createServer(async(req,res)=>{try{
    const url=new URL(req.url,'http://localhost');
    if(files.has(url.pathname)){res.setHeader('Content-Type','text/javascript');res.end(files.get(url.pathname));return;}
    if(url.pathname==='/system.css'){res.setHeader('Content-Type','text/css');res.end((await readFile(join(root,'resources/css/app.css'),'utf8')).replace(/^@import.*$/gm,''));return;}
    if(url.pathname.startsWith('/node_modules/')||url.pathname.startsWith('/resources/')){res.setHeader('Content-Type',url.pathname.endsWith('.css')?'text/css':'font/woff2');res.end(await readFile(join(root,url.pathname)));return;}
    const payload={method:req.method,data:Object.fromEntries(url.searchParams),files:{}};
    if(req.method==='POST'){
        const chunks=[];for await(const chunk of req)chunks.push(chunk);
        if(req.headers['content-type']?.includes('application/json'))Object.assign(payload.data,JSON.parse(Buffer.concat(chunks).toString()));
        else{
            const data=await new Request('http://localhost',{method:'POST',headers:req.headers,body:Buffer.concat(chunks)}).formData();
            for(const [key,value] of data){if(typeof value==='string')payload.data[key]=value;else payload.files[key]={name:value.name,mime:value.type,bytes:Buffer.from(await value.arrayBuffer()).toString('base64')};}
        }
    }
    const fixture=JSON.parse(execFileSync(php,[join(root,'tests/browser/g9-certificate-tracking-fixture.php'),db,url.pathname.replace('/g9-national-exams/certificates','')||'/'],{cwd:root,input:JSON.stringify(payload),encoding:'utf8',maxBuffer:15*1024*1024}));
    res.statusCode=fixture.status;res.setHeader('Content-Type',fixture.type);let content=Buffer.from(fixture.content,'base64');
    if(fixture.type.includes('json'))content=JSON.stringify(JSON.parse(content.toString())).replaceAll('http://localhost','');
    if(fixture.type==='text/html')content=`<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="browser-fixture"><link rel="stylesheet" href="/node_modules/@tabler/core/dist/css/tabler.min.css"><link rel="stylesheet" href="/node_modules/@tabler/icons-webfont/dist/tabler-icons.min.css"><link rel="stylesheet" href="/system.css">${['g9-national-exams','g9-results','g12-national-exams','g9-certificate-tracking'].map(name=>`<link rel="stylesheet" href="/resources/css/pages/${name}.css">`).join('')}</head><body><div class="container-fluid p-3">${content.toString().replaceAll('http://localhost','')}</div><script>window.errors=[];addEventListener('error',e=>errors.push(e.message));addEventListener('unhandledrejection',e=>errors.push(String(e.reason)));</script><script type="module" src="/forms.js"></script><script type="module" src="/tracking.js"></script></body></html>`;
    res.end(content);
}catch(error){res.statusCode=500;res.end(JSON.stringify({message:error.stack}));}});
await new Promise(r=>server.listen(0,'127.0.0.1',r));const origin=`http://127.0.0.1:${server.address().port}`;
const browser=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--no-sandbox','--disable-gpu','--no-first-run','--remote-debugging-port=0',`--user-data-dir=${join(temp,'chrome')}`,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(r=>setTimeout(r,ms));let socket;
try{
    let port;for(let i=0;i<100;i++){try{port=Number((await readFile(join(temp,'chrome/DevToolsActivePort'),'utf8')).split('\n')[0]);break;}catch{await delay(100);}}
    const pages=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();socket=new WebSocket(pages.find(p=>p.type==='page').webSocketDebuggerUrl);await new Promise(r=>socket.addEventListener('open',r,{once:true}));
    let id=0;const pending=new Map();socket.addEventListener('message',({data})=>{const m=JSON.parse(data),handler=pending.get(m.id);if(handler){pending.delete(m.id);m.error?handler[1](new Error(JSON.stringify(m.error))):handler[0](m.result);}});
    const command=(method,params={})=>new Promise((resolve,reject)=>{const n=++id;pending.set(n,[resolve,reject]);socket.send(JSON.stringify({id:n,method,params}));});
    const evaluate=async expression=>{const r=await command('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw new Error(r.exceptionDetails.exception?.description||JSON.stringify(r.exceptionDetails));return r.result.value;};
    const until=async expression=>{for(let i=0;i<120;i++){if(await evaluate(expression))return;await delay(100);}throw new Error('Timeout: '+expression+' '+await evaluate('JSON.stringify({errors:window.errors,error:document.querySelector("[data-certificate-error]")?.textContent,modal:document.querySelector("[data-certificate-modal-error]")?.textContent,numbers:document.querySelector("[data-number-error]")?.textContent})'));};
    const click=selector=>evaluate(`document.querySelector(${JSON.stringify(selector)}).click()`);
    const idle=()=>until('document.querySelector("[data-g9-certificate-tracking]")?.getAttribute("aria-busy")==="false"');
    const choose=async(name,index)=>{await click('#g9Certificate-'+name+'-toggle');await click(`#g9Certificate-${name}Options [data-index="${index}"]`);await idle();};
    const open=async id=>{await click(`[data-certificate-manage="${id}"]`);await until(`document.querySelector('#g9CertificateModal.show [name=candidate_id]')?.value==='${id}'`);await delay(250);};
    const set=async(name,value)=>evaluate(`(()=>{const el=document.querySelector(${JSON.stringify(name==='received_date'?'#g9CertificateReceivedDate-direct':`[data-certificate-form] [name="${name}"]`)});el.value=${JSON.stringify(value)};el.dispatchEvent(new Event('change',{bubbles:true}));})()`);
    const close=async()=>{await click('#g9CertificateModal [data-bs-dismiss=modal]');await until('!document.querySelector("#g9CertificateModal.show")');await delay(250);};
    await command('Emulation.setDeviceMetricsOverride',{width:1500,height:1100,deviceScaleFactor:1,mobile:false});
    await command('Page.navigate',{url:origin+'/g9-national-exams/certificates'});await idle();
    assert.equal(await evaluate('document.querySelectorAll("[data-certificate-rows] tr").length'),1);
    await choose('academic_year',1);
    assert.equal(await evaluate('document.querySelectorAll("[data-certificate-rows] tr").length'),25);
    assert.equal(await evaluate('document.querySelector("[data-certificate-count=total]").textContent'),'32');
    assert.equal(await evaluate('document.querySelector("[data-certificate-count=passed]").textContent'),'29');
    assert.equal(await evaluate('document.querySelector("[data-certificate-count=failed]").textContent'),'1');
    assert.equal(await evaluate('document.querySelector("[data-certificate-count=absent]").textContent'),'1');
    assert.equal(await evaluate('document.querySelector(".g12-certificate-table").innerText.includes("Diploma")'),false);
    assert.ok(await evaluate(`document.querySelector('[data-certificate-manage="2"]').closest('tr').textContent.includes('០១ ធ្នូ ២០២៦')`));
    assert.ok(await evaluate(`document.querySelector('[data-certificate-manage="3"]').closest('tr').textContent.includes('03-Oct-2026')`));
    await open(1);await set('status','printed_signed');await set('printed_signed_date','2026-11-01');await set('number','G9-BROWSER-001');
    await click('[data-certificate-save]');await until('!document.querySelector("#g9CertificateModal.show")');await idle();
    await open(1);assert.equal(await evaluate('document.querySelector("#g9CertificateNumber").value'),'G9-BROWSER-001');
    await set('status','received');await set('received_date','2026-11-02');await set('recipient','Browser Student');
    await click('[data-certificate-save]');await until('!document.querySelector("[data-certificate-modal-error]").hidden');
    assert.ok(await evaluate('document.querySelector("[data-certificate-modal-error]").textContent.includes("student-signed")'));
    await evaluate(`(()=>{const transfer=new DataTransfer();transfer.items.add(new File([${JSON.stringify('%PDF-1.4\n% browser receipt\n%%EOF\n')}],'student-signed.pdf',{type:'application/pdf'}));const input=document.querySelector('[name=receipt_file]');input.files=transfer.files;input.dispatchEvent(new Event('change',{bubbles:true}));})()`);
    await click('[data-certificate-save]');await until('!document.querySelector("#g9CertificateModal.show")');await idle();
    await open(1);assert.equal(await evaluate('document.querySelector("#g9CertificateStatus").value'),'received');
    assert.equal(await evaluate('document.querySelector("[data-certificate-reason-field]").hidden'),false);
    assert.equal(await evaluate('document.querySelector("#g9CertificateReason").required'),true);await close();
    await open(1);await set('received_date','13-Jan-2027');
    assert.equal(await evaluate('document.querySelector("#g9CertificateReceivedDate-direct").value'),'13-Jan-2027');
    assert.equal(await evaluate('document.querySelector("#g9CertificateReceivedDate").value'),'2027-01-13');await close();
    await open(4);assert.equal(await evaluate('document.querySelector("[data-certificate-save]").hidden'),true);await close();
    await click('#g9-certificate-pagination [data-page="2"]');await idle();assert.equal(await evaluate('document.querySelectorAll("[data-certificate-rows] tr").length'),7);
    await choose('status',3);assert.equal(await evaluate('document.querySelector("[data-certificate-count=total]").textContent'),'3');
    await choose('status',0);
    await mkdir(join(root,'storage/app/testing/g9-certificate-tracking'),{recursive:true});
    await click('[data-certificate-number-settings]');
    await until('!document.querySelector("[data-number-preview-button]").disabled');
    assert.equal(await evaluate('document.querySelector("[data-number-year-code]").textContent'),'២៦');
    await click('[data-number-preview-button]');
    await until('!document.querySelector("[data-number-preview]").hidden');
    assert.equal(await evaluate('document.querySelectorAll("[data-number-preview-rows] tr").length'),29);
    assert.ok(await evaluate('document.querySelector("[data-number-preview-rows]").textContent.includes("៩២៦០០០១")'));
    assert.equal(await evaluate('document.querySelector("[data-number-preview-rows]").textContent.includes("00004")'),false);
    // Editing a setting invalidates the reviewed numbers until saved and previewed again.
    await evaluate(`(()=>{const input=document.querySelector('#g9NumberStart');input.value=10;input.dispatchEvent(new Event('input',{bubbles:true}));})()`);
    assert.equal(await evaluate('document.querySelector("[data-number-generate]").disabled'),true);
    await click('[data-number-save]');await until('!document.querySelector("[data-number-preview-button]").disabled');
    await click('[data-number-preview-button]');await until('!document.querySelector("[data-number-generate]").disabled');
    assert.ok(await evaluate('document.querySelector("[data-number-preview-rows]").textContent.includes("៩២៦០០១០")'));
    for(const width of [1500,390])for(const theme of ['light','dark']){
        await command('Emulation.setDeviceMetricsOverride',{width,height:1100,deviceScaleFactor:1,mobile:width<500});await evaluate(`document.documentElement.dataset.bsTheme=${JSON.stringify(theme)}`);await delay(150);
        assert.equal(await evaluate('document.documentElement.scrollWidth<=document.documentElement.clientWidth'),true);
        await evaluate('document.querySelector("#g9CertificateNumberModal .modal-body").scrollTop=0');await delay(200);
        await writeFile(join(root,`storage/app/testing/g9-certificate-tracking/number-settings-${width}-${theme}.png`),Buffer.from((await command('Page.captureScreenshot',{format:'png'})).data,'base64'));
        await evaluate('document.querySelector("[data-number-preview]").scrollIntoView({block:"start",behavior:"instant"})');await delay(200);
        await writeFile(join(root,`storage/app/testing/g9-certificate-tracking/numbers-${width}-${theme}.png`),Buffer.from((await command('Page.captureScreenshot',{format:'png'})).data,'base64'));
    }
    await click('[data-number-generate]');await until('!document.querySelector("[data-number-success]").hidden');await idle();
    await click('[data-number-preview-button]');await until('!document.querySelector("[data-number-preview]").hidden');
    assert.equal(await evaluate('document.querySelector("[data-number-generate]").disabled'),true);
    assert.equal(await evaluate('document.querySelectorAll("[data-number-preview-rows] .badge.bg-blue-lt").length'),0);
    await click('#g9CertificateNumberModal [data-bs-dismiss=modal]');await until('!document.querySelector("#g9CertificateNumberModal.show")');await delay(250);
    for(const width of [1500,390])for(const theme of ['light','dark']){
        await command('Emulation.setDeviceMetricsOverride',{width,height:1100,deviceScaleFactor:1,mobile:width<500});await evaluate(`document.documentElement.dataset.bsTheme=${JSON.stringify(theme)}`);await delay(200);
        assert.equal(await evaluate('document.documentElement.scrollWidth<=document.documentElement.clientWidth'),true);
        await writeFile(join(root,`storage/app/testing/g9-certificate-tracking/table-${width}-${theme}.png`),Buffer.from((await command('Page.captureScreenshot',{format:'png'})).data,'base64'));
        await open(2);assert.equal(await evaluate('document.documentElement.scrollWidth<=document.documentElement.clientWidth'),true);
        await until('Array.from(document.querySelectorAll("#g9CertificateModal img")).every(img=>img.complete&&img.naturalWidth>0)');
        await writeFile(join(root,`storage/app/testing/g9-certificate-tracking/modal-${width}-${theme}.png`),Buffer.from((await command('Page.captureScreenshot',{format:'png'})).data,'base64'));await close();
    }
    assert.deepEqual(await evaluate('window.errors'),[]);
    console.log('G9 certificate tracking: provisional and receipt columns, saved printing, receipt upload validation, received status, correction reason, failed restrictions, filters, pagination, desktop/mobile and light/dark layouts passed.');
}finally{socket?.close();browser.kill();await new Promise(r=>server.close(r));}
