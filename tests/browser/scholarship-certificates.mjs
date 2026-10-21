// Exercise the real certificate editor and report module against an isolated SQLite fixture.
import assert from 'node:assert/strict';
import {build} from 'vite';
import {createServer} from 'node:http';
import {spawn,execFileSync} from 'node:child_process';
import {readFile,mkdtemp,writeFile} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join,resolve} from 'node:path';
const root=resolve(import.meta.dirname,'../..'),php=process.env.CHAT_TEST_PHP||'C:/laragon/bin/php/php-8.4.25-Win32-vs17-x64/php.exe',chrome=process.env.CHAT_TEST_BROWSER||'C:/Program Files/Google/Chrome/Application/chrome.exe';
const temp=await mkdtemp(join(tmpdir(),'scholarship-certificates-')),db=join(temp,'fixture.sqlite');await writeFile(db,'');
const bundle=await build({root,configFile:false,logLevel:'silent',build:{write:false,lib:{entry:{certificates:join(root,'resources/js/scholarshipCertificates.js'),scholarship:join(root,'resources/js/westernScholarship.js'),forms:join(root,'resources/js/premiumForms.js')},formats:['es'],fileName:(_,name)=>name+'.js',cssFileName:'fixtures'}}});
const files=new Map();for(const output of Array.isArray(bundle)?bundle:[bundle])for(const item of output.output)files.set('/'+item.fileName,item.type==='chunk'?item.code:item.source);
let mode='central',contentOnlyReport;const reportFiles=new Map();
const script='<script type="module" src="/certificates.js"></script>';
const css='<link rel="stylesheet" href="/resources/css/pages/scholarship-certificates.css">';
const appCss=await readFile(join(root,'resources/css/app.css'),'utf8');
const formsCss=appCss.slice(appCss.indexOf('.premium-form-field {'),appCss.indexOf('[data-bs-theme="dark"] .user-searchable-combobox'));
const paginationCss=appCss.slice(appCss.indexOf('.premium-pagination {'),appCss.indexOf('.logo-dropzone {'))+appCss.slice(appCss.indexOf('.premium-pagination-list {'),appCss.indexOf('/* Responsive form and modal baseline */'));
const server=createServer(async(req,res)=>{
    try{
        const url=new URL(req.url,'http://localhost');
        if(files.has(url.pathname)){res.setHeader('Content-Type',url.pathname.endsWith('.css')?'text/css':'text/javascript');res.end(files.get(url.pathname));return;}
        if(reportFiles.has(url.pathname)){res.setHeader('Content-Type','text/html; charset=utf-8');res.end(reportFiles.get(url.pathname));return;}
        if(url.pathname.startsWith('/fonts/')||url.pathname.startsWith('/resources/')||url.pathname.startsWith('/node_modules/')){
            const path=join(root,url.pathname.startsWith('/fonts/')?'public':'',url.pathname);res.setHeader('Content-Type',url.pathname.endsWith('.css')?'text/css':url.pathname.endsWith('.ttf')?'font/ttf':'font/woff2');res.end(await readFile(path));return;
        }
        const chunks=[];for await(const chunk of req)chunks.push(chunk);const body=Buffer.concat(chunks).toString();let payload=body?JSON.parse(body):Object.fromEntries(url.searchParams);
        const result=JSON.parse(execFileSync(php,[join(root,'tests/browser/scholarship-certificates-fixture.php'),db,url.pathname,JSON.stringify(payload),mode],{cwd:root,encoding:'utf8',maxBuffer:30*1024*1024}));
        let content=Buffer.from(result.content,'base64');res.statusCode=result.status;res.setHeader('Content-Type',result.type);
        if(result.type.startsWith('text/html')){
            let html=content.toString().replaceAll('http://localhost/students/western-scholarship','/students/western-scholarship').replaceAll('http:\\/\\/localhost','');
            if(html.includes('data-cert-report')){html=html.replace('</head>',css+'</head>').replace('</body>',script+'</body>');reportFiles.set('/last-report',html);}
            else html=`<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="fixture"><link rel="stylesheet" href="/node_modules/@tabler/core/dist/css/tabler.min.css"><link rel="stylesheet" href="/node_modules/@tabler/icons-webfont/dist/tabler-icons.min.css"><link rel="stylesheet" href="/resources/css/pages/western-scholarship.css">${css}<style>body{padding:16px}${formsCss}${paginationCss}</style>${html}<script type="module" src="/scholarship.js"></script><script type="module" src="/forms.js"></script>${script}`;
            content=html;
        }
        if(result.type.startsWith('application/json'))content=content.toString().replaceAll('http:\\/\\/localhost','').replaceAll('http://localhost','');
        res.end(content);
    }catch(error){res.statusCode=500;res.setHeader('Content-Type','application/json');res.end(JSON.stringify({message:error.message}));}
});
await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));const origin=`http://127.0.0.1:${server.address().port}`;
const profile=join(temp,'chrome');const browser=spawn(chrome,['--headless=new','--no-sandbox','--disable-gpu','--disable-popup-blocking','--no-first-run','--remote-debugging-port=0',`--user-data-dir=${profile}`,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));let socket;
try{
    let port;for(let i=0;i<100;i++){try{port=Number((await readFile(join(profile,'DevToolsActivePort'),'utf8')).split('\n')[0]);break;}catch{await delay(100);}}
    const pages=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();socket=new WebSocket(pages.find(page=>page.type==='page').webSocketDebuggerUrl);await new Promise(resolve=>socket.addEventListener('open',resolve,{once:true}));
    let sequence=0;const pending=new Map(),errors=[];socket.addEventListener('message',({data})=>{const msg=JSON.parse(data);if(msg.method==='Runtime.exceptionThrown')errors.push(msg.params.exceptionDetails.exception?.description||msg.params.exceptionDetails.text);const handler=pending.get(msg.id);if(handler){pending.delete(msg.id);msg.error?handler[1](new Error(JSON.stringify(msg.error))):handler[0](msg.result);}});
    const command=(method,params={})=>new Promise((resolve,reject)=>{const id=++sequence;pending.set(id,[resolve,reject]);socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const result=await command('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(result.exceptionDetails)throw new Error(JSON.stringify(result.exceptionDetails));return result.result.value;};
    const until=async expression=>{for(let i=0;i<160;i++){if(await evaluate(expression))return;await delay(70);}throw new Error('Timed out: '+expression+'; '+await evaluate('JSON.stringify({alert:document.querySelector(".swal2-popup")?.textContent.slice(0,1000),popup:window.certPopup?{url:window.certPopup.location.href,ready:window.certPopup.document.readyState,base:window.certPopup.document.baseURI,script:[...window.certPopup.document.scripts].map(s=>s.src),body:window.certPopup.document.body?.textContent.slice(0,350)}:null})')+'; '+errors.join('; '));};
    const click=selector=>evaluate(`document.querySelector(${JSON.stringify(selector)}).click()`);
    const change=(selector,value,event='input')=>evaluate(`{const el=document.querySelector(${JSON.stringify(selector)});el.value=${JSON.stringify(value)};el.dispatchEvent(new Event(${JSON.stringify(event)},{bubbles:true}));}`);
    const printPreview=async(frame=false)=>{await click('[data-cert-output=print]');await until('document.querySelector(".swal2-title")?.textContent==="Certificate Print Preview"');await click(frame?'.swal2-deny':'.swal2-confirm');};
    await command('Page.enable');await command('Runtime.enable');await command('Emulation.setFocusEmulationEnabled',{enabled:true});
    for(const mobile of [false,true]){
        mode='central';await command('Emulation.setDeviceMetricsOverride',{width:mobile?390:1440,height:mobile?844:1000,deviceScaleFactor:1,mobile});await command('Page.navigate',{url:origin});await until('document.querySelector("[data-cert-ready=true]")!==null');
        assert.equal(await evaluate('document.querySelectorAll("[data-cert-row]").length'),15);
        assert.deepEqual(await evaluate('[...document.querySelector("#certPagination select").options].map(o=>o.value)'),['all','15','25','50','100']);
        assert.equal(await evaluate('document.querySelector("#certPagination select").value'),'15');
        await click('#certPagination [data-page="2"]');await until('document.querySelector("#certPagination .active a")?.dataset.page==="2"');
        assert.equal(await evaluate('document.querySelector("[data-cert-row] td:first-child").textContent'),'16');
        assert.equal(await evaluate('new URL(location.href).searchParams.get("page")'),'2');
        await change('#certPagination .premium-pagination-goto input','1','change');await until('document.querySelector("#certPagination .active a")?.dataset.page==="1"');
        await change('#certPagination select','25','change');await until('document.querySelectorAll("[data-cert-row]").length===25');
        await change('#certPagination select','all','change');await until('document.querySelectorAll("[data-cert-row]").length===30');
        await change('#certPagination select','15','change');await until('document.querySelectorAll("[data-cert-row]").length===15');
        await evaluate('window.scrollTo({top:document.documentElement.scrollHeight,behavior:"instant"})');await delay(150);
        await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:false}).then(result=>writeFile(join(root,`storage/app/testing/certificates-pagination-${mobile?'mobile':'desktop'}.png`),Buffer.from(result.data,'base64')));
        assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'),true);
        await click('[data-cert-open-settings]');await until('document.querySelector("[data-cert-settings-ready=true]")!==null');
        assert.equal(await evaluate('document.querySelector("#certSettings").getAttribute("aria-modal")'),'true');
        assert.equal(await evaluate('[...document.querySelectorAll("[data-cert-staff]")].every(select=>select.closest(".premium-form-field").classList.contains("has-value"))'),true);
        await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:false}).then(result=>writeFile(join(root,`storage/app/testing/certificates-settings-${mobile?'mobile':'desktop'}.png`),Buffer.from(result.data,'base64')));
        await click('#certSettings [data-bs-dismiss=modal]');await until('!document.querySelector(".modal.show,.modal-backdrop")');
        assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'),true);assert.equal(await evaluate('document.querySelector("#certFilters").hidden'),mobile);
        assert.equal(await evaluate('document.querySelector("[data-cert-filters-toggle]").getAttribute("aria-expanded")'),String(!mobile));
        if(mobile)await click('[data-cert-filters-toggle]');
        assert.equal(await evaluate('document.querySelectorAll("[data-cert-filters] .scholarship-select-toggle").length'),5);
        assert.equal(await evaluate('[...document.querySelectorAll("[data-cert-filters] .scholarship-select-toggle")].every(button=>Math.round(button.getBoundingClientRect().height)===52)'),true);
        await click('#certCampusToggle');assert.equal(await evaluate('document.querySelector("#certCampusToggle").getAttribute("aria-expanded")'),'true');
        await click('[data-cert-filters-toggle]');assert.equal(await evaluate('[...document.querySelectorAll("[data-cert-filters] .scholarship-select-menu")].every(menu=>menu.hidden)'),true);
        assert.equal(await evaluate('document.querySelector("[data-cert-filters-toggle] i").className'),'ti ti-chevron-down');await click('[data-cert-filters-toggle]');
        await evaluate(`{window.filterSubmissions=[];window.captureCertificateFilter=event=>{event.preventDefault();window.filterSubmissions.push(Object.fromEntries(new FormData(event.target)));};document.querySelector('[data-cert-filters]').addEventListener('submit',window.captureCertificateFilter);}`);
        await click('#certGradeToggle');await click('#certGradeOptions [data-seat-option="1"]');
        assert.equal(await evaluate('window.filterSubmissions.at(-1).grade'),'1');
        await click('#certSearchToggle');await change('#certSearchToggle + .scholarship-select-menu input','GRADE 1 STUDENT 01');
        await click('#certSearchOptions [data-seat-option="GRADE 1 STUDENT 01"]');assert.equal(await evaluate('window.filterSubmissions.at(-1).q'),'GRADE 1 STUDENT 01');
        await click('#certCampusToggle');await click('#certCampusOptions [data-seat-option="2"]');assert.equal(await evaluate('window.filterSubmissions.at(-1).q'),'');
        await click('#certYearToggle');await click('#certYearOptions [data-seat-option="2"]');assert.deepEqual(await evaluate('(()=>{const f=window.filterSubmissions.at(-1);return [f.campus,f.grade,f.type,f.q]})()'),['','','','']);
        await click('#certYearToggle');await click('#certYearOptions [data-seat-option="1"]');
        await evaluate(`document.querySelector('[data-cert-filters]').removeEventListener('submit',window.captureCertificateFilter)`);
        await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:false}).then(result=>writeFile(join(root,`storage/app/testing/certificates-filters-${mobile?'mobile':'desktop'}.png`),Buffer.from(result.data,'base64')));
        await click('[data-cert-toggle=certEditor]');await delay(100);
        await change('#certField','name','change');const y=await evaluate('Number(document.querySelector("[data-cert-property=y]").value)');await click('[data-cert-nudge=down]');assert.equal(await evaluate('Number(document.querySelector("[data-cert-property=y]").value)'),y+1);
        await change('#certColor','#8425a5');assert.equal(await evaluate('document.querySelector("[data-field=name]").style.color'),'rgb(132, 37, 165)');
        await change('#certSize','25');await click('[data-cert-save]');await until('document.querySelector(".swal2-success")!==null');await until('!document.querySelector(".swal2-container")');
        await change('#certTemplate','subject','change');await change('#certField','title','change');
        const rect=await evaluate('(()=>{const r=document.querySelector("[data-field=title]").getBoundingClientRect();return{x:r.left+r.width/2,y:r.top+r.height/2}})()');
        await evaluate(`{const canvas=document.querySelector('[data-cert-canvas]'),field=document.querySelector('[data-field=title]');canvas.setPointerCapture=()=>{};field.dispatchEvent(new PointerEvent('pointerdown',{clientX:${rect.x},clientY:${rect.y},pointerId:1,bubbles:true}));canvas.dispatchEvent(new PointerEvent('pointermove',{clientX:${rect.x},clientY:${rect.y+8},pointerId:1,bubbles:true}));canvas.dispatchEvent(new PointerEvent('pointerup',{pointerId:1,bubbles:true}));}`);
        assert.ok(await evaluate('Number(document.querySelector("[data-cert-property=y]").value)>96'));
        await click('[data-cert-save]');await until('document.querySelector(".swal2-success")!==null');await until('!document.querySelector(".swal2-container")');
        await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:false}).then(result=>writeFile(join(root,`storage/app/testing/certificates-editor-${mobile?'mobile':'desktop'}.png`),Buffer.from(result.data,'base64')));
        assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'),true);
        if(!mobile){
            await click('[data-cert-open-settings]');await until('document.querySelector("[data-cert-settings-ready=true]")!==null');await change('[data-signer-name="0"]','SAMPLE VICE PRESIDENT');await change('[data-signer-name="1"]','SAMPLE REGISTRAR DIRECTOR');await evaluate('document.querySelector("[data-cert-settings]").requestSubmit()');await until('document.querySelector(".swal2-success")!==null');await until('!document.querySelector(".swal2-container,.modal.show,.modal-backdrop")');
            await click('[data-cert-plan]');await until('document.querySelector(".swal2-confirm")?.textContent.includes("Assign These")');assert.equal(await evaluate('document.querySelectorAll(".swal2-html-container tbody tr").length'),30);await click('.swal2-confirm');await until('document.querySelector("[data-cert-ready=true]")!==null&&JSON.parse(document.querySelector("[data-cert-list-data]").textContent).printable_ids.length===30');
            const ids=await evaluate('JSON.parse(document.querySelector("[data-cert-list-data]").textContent).printable_ids.slice(0,3)');
            assert.equal(await evaluate('document.querySelector("#certBatch,#certOutput")===null'),true);
            await evaluate(`{const open=window.open;window.open=(...args)=>(window.certPopup=open.apply(window,args));}`);
            await printPreview();await until('window.certPopup?.document.querySelectorAll("[data-cert-page]").length===30');await evaluate('window.certPopup.close()');
            await click('#certPagination [data-page="2"]');await until('document.querySelector("#certPagination .active a")?.dataset.page==="2"');
            await click('#certPagination [data-page="1"]');await until('document.querySelector("#certPagination .active a")?.dataset.page==="1"');
            assert.equal(await evaluate('JSON.parse(document.querySelector("[data-cert-list-data]").textContent).printable_ids.length'),30);
            await command('Page.navigate',{url:origin+'/?campus=1&grade=1&type=subject'});await until('document.querySelector("[data-cert-ready=true]")!==null');
            await evaluate(`{const open=window.open;window.open=(...args)=>(window.certPopup=open.apply(window,args));}`);
            await printPreview();await until('window.certPopup?.document.querySelectorAll("[data-cert-page]").length===3');
            assert.equal(await evaluate('JSON.parse(window.certPopup.document.querySelector("#certReportData").textContent).pages.every(page=>page.values.campus==="BCH"&&page.values.grade===1&&page.values.subject)'),true);
            await evaluate('window.certPopup.close()');
            await command('Page.navigate',{url:origin+'/?campus=1&grade=1&type=subject&q=GRADE%201%20STUDENT%2005'});await until('document.querySelector("[data-cert-ready=true]")!==null');
            await evaluate(`{const open=window.open;window.open=(...args)=>(window.certPopup=open.apply(window,args));}`);
            await printPreview();await until('window.certPopup?.document.querySelectorAll("[data-cert-page]").length===1');
            assert.equal(await evaluate('JSON.parse(window.certPopup.document.querySelector("#certReportData").textContent).pages[0].values.student_name'),'GRADE 1 STUDENT 05');
            await evaluate('window.certPopup.close()');
            const response=await fetch(origin+'/students/western-scholarship/certificates/1/report',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({ids,format:'print',frame:false})});assert.equal(response.status,200);contentOnlyReport=reportFiles.get('/last-report');
        }
        mode='campus';await command('Page.navigate',{url:origin});await until('document.querySelector("[data-cert-ready=true]")!==null');assert.equal(await evaluate('document.querySelector("[data-cert-save],[data-cert-plan],[data-cert-settings],[data-cert-cancel]")===null'),true);
        assert.equal(await evaluate('JSON.parse(document.querySelector("[data-cert-list-data]").textContent).printable_ids.length'),15);
        await evaluate(`{window.openedCertificateWindows=0;const open=window.open;window.open=(...args)=>{window.openedCertificateWindows++;return window.certPopup=open.apply(window,args);};}`);
        await click('[data-cert-output=print]');await until('document.querySelector(".swal2-title")?.textContent==="Certificate Print Preview"');
        assert.equal(await evaluate('document.querySelector(".swal2-confirm").textContent'),'Content Only');
        assert.equal(await evaluate('document.querySelector(".swal2-deny").textContent'),'Include Certificate Frame');
        await delay(400);
        await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:false}).then(result=>writeFile(join(root,`storage/app/testing/certificates-print-options-${mobile?'mobile':'desktop'}.png`),Buffer.from(result.data,'base64')));
        await click('.swal2-cancel');await until('!document.querySelector(".swal2-container")');assert.equal(await evaluate('window.openedCertificateWindows'),0);
        await printPreview(true);await until('window.certPopup?.document.querySelectorAll("[data-cert-page]").length===15');
        assert.equal(await evaluate('window.certPopup.document.body.classList.contains("cert-content-only")'),false);await evaluate('window.certPopup.close()');
        await printPreview();await until('window.certPopup?.document.querySelectorAll("[data-cert-page]").length===15');
        assert.equal(await evaluate('window.certPopup.document.body.classList.contains("cert-content-only")'),true);await evaluate('window.certPopup.close()');
    }
    reportFiles.set('/last-report',contentOnlyReport);
    await command('Emulation.setDeviceMetricsOverride',{width:1000,height:1100,deviceScaleFactor:1,mobile:false});await command('Page.navigate',{url:origin+'/last-report'});await until('document.querySelectorAll("[data-cert-page] .cert-field").length>10');await evaluate('document.fonts.ready');await delay(250);
    assert.equal(await evaluate('document.querySelectorAll("[data-cert-page]").length'),3);
    await command('Emulation.setEmulatedMedia',{media:'print'});assert.equal(await evaluate('getComputedStyle(document.querySelector(".cert-frame")).display'),'none');
    await command('Page.printToPDF',{printBackground:true,preferCSSPageSize:true,displayHeaderFooter:false}).then(result=>writeFile(join(root,'storage/app/testing/certificates/browser-content.pdf'),Buffer.from(result.data,'base64')));
    await command('Emulation.setEmulatedMedia',{media:''});await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:false}).then(result=>writeFile(join(root,'storage/app/testing/certificates/browser-preview.png'),Buffer.from(result.data,'base64')));
    const generated=JSON.parse(execFileSync(php,[join(root,'tests/browser/scholarship-certificates-fixture.php'),db,'/generate','{}','central'],{cwd:root,encoding:'utf8'}));assert.equal(generated.status,200);
    assert.deepEqual(errors,[]);console.log('Certificate editor, saved styling, drag/nudge, numbering, campus access and A4 content-only print passed on desktop and mobile.');
}finally{socket?.close();browser.kill();server.closeAllConnections();await new Promise(resolve=>server.close(resolve));}
