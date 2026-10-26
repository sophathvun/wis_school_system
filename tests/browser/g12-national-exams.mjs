import assert from 'node:assert/strict';
import {build} from 'vite';
import {createServer} from 'node:http';
import {spawn,execFileSync} from 'node:child_process';
import {readFile,mkdtemp,writeFile} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join,resolve} from 'node:path';
const root=resolve(import.meta.dirname,'../..'),php='C:/laragon/bin/php/php-8.4.25-Win32-vs17-x64/php.exe',chrome='C:/Program Files/Google/Chrome/Application/chrome.exe';
const temp=await mkdtemp(join(tmpdir(),'wis-g12-exams-')),db=join(temp,'test.sqlite');await writeFile(db,'');
const bundle=await build({root,configFile:false,logLevel:'silent',build:{write:false,lib:{entry:{g12:join(root,'resources/js/g12NationalExams.js'),premiumForms:join(root,'resources/js/premiumForms.js')},formats:['es'],fileName:(_format,name)=>name+'.js',cssFileName:'g12-browser'}}});
const files=new Map();for(const output of Array.isArray(bundle)?bundle:[bundle])for(const f of output.output)files.set('/'+f.fileName,f.type==='chunk'?f.code:f.source);
const fixture=(path,payload={})=>JSON.parse(execFileSync(php,[join(root,'tests/browser/g12-national-exams-fixture.php'),db,path],{cwd:root,input:JSON.stringify(payload),encoding:'utf8',maxBuffer:25*1024*1024}));
const decode=result=>JSON.parse(Buffer.from(result.content,'base64').toString());
const server=createServer(async(req,res)=>{try{
    const url=new URL(req.url,'http://localhost');
    if(files.has(url.pathname)){res.setHeader('Content-Type',url.pathname.endsWith('.css')?'text/css':'text/javascript');res.end(files.get(url.pathname));return;}
    if(url.pathname==='/system-forms.css'){res.setHeader('Content-Type','text/css');res.end((await readFile(join(root,'resources/css/app.css'),'utf8')).replace(/^@import.*$/gm,''));return;}
    if(url.pathname.startsWith('/node_modules/')||url.pathname.startsWith('/resources/')){res.setHeader('Content-Type',url.pathname.endsWith('.css')?'text/css':'font/woff2');res.end(await readFile(join(root,url.pathname)));return;}
    const chunks=[];for await(const chunk of req)chunks.push(chunk);const body=Buffer.concat(chunks);let payload=Object.fromEntries(url.searchParams);
    if(req.headers['content-type']?.includes('multipart/form-data')){
        const form=await new Response(body,{headers:{'Content-Type':req.headers['content-type']}}).formData();
        for(const [key,value]of form){if(typeof value==='string')payload[key]=value;else if(value.size){payload._files??={};const data={name:value.name,bytes:Buffer.from(await value.arrayBuffer()).toString('base64')};if(key==='files[]'){payload._files.files??=[];payload._files.files.push(data);}else payload._files[key]=data;}}
    }else if(body.length)payload={...payload,...JSON.parse(body.toString())};
    const path=url.pathname.replace(/^\/g12-national-exams/,'')||'/';
    // Browser exercises the actual preview UI against a synthetic parsed workbook;
    // the real MoEYS XLSX reader is covered separately by backend tests.
    const result=fixture(path==='/imports/preview'?'/sample-workbook':path,payload);res.statusCode=result.status;res.setHeader('Content-Type',result.type);
    let content=Buffer.from(result.content,'base64');
    if(result.type==='text/html'){
        let html=content.toString().replaceAll('http://localhost','').replaceAll('http:\\/\\/localhost','');
        if(!html.includes('<!doctype'))html=`<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="fixture"><link rel="stylesheet" href="/node_modules/@tabler/core/dist/css/tabler.min.css"><link rel="stylesheet" href="/node_modules/@tabler/icons-webfont/dist/tabler-icons.min.css"><link rel="stylesheet" href="/system-forms.css">${[...files.keys()].filter(p=>p.endsWith('.css')).map(p=>`<link rel="stylesheet" href="${p}">`).join('')}<link rel="stylesheet" href="/resources/css/pages/g12-national-exams.css"></head><body><div class="container-fluid p-3">${html}</div><script>window.errors=[];addEventListener('error',e=>errors.push(e.message));addEventListener('unhandledrejection',e=>errors.push(String(e.reason)));window.open=(url)=>{window.reportUrl=url;};</script><script type="module" src="/premiumForms.js"></script><script type="module" src="/g12.js"></script></body></html>`;
        content=html;
    }else if(result.type.includes('json'))content=content.toString().replaceAll('http:\\/\\/localhost','').replaceAll('http://localhost','');
    res.end(content);
}catch(e){res.statusCode=500;res.end(JSON.stringify({message:e.stack}));}});
await new Promise(r=>server.listen(0,'127.0.0.1',r));const origin=`http://127.0.0.1:${server.address().port}`;
const browser=spawn(chrome,['--headless=new','--no-sandbox','--disable-gpu','--no-first-run','--remote-debugging-port=0',`--user-data-dir=${join(temp,'chrome')}`,'about:blank'],{windowsHide:true,stdio:'ignore'});
let socket;const delay=ms=>new Promise(r=>setTimeout(r,ms));
try{
    let port;for(let i=0;i<100;i++){try{port=Number((await readFile(join(temp,'chrome/DevToolsActivePort'),'utf8')).split('\n')[0]);break;}catch{await delay(100);}}
    const pages=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();socket=new WebSocket(pages.find(p=>p.type==='page').webSocketDebuggerUrl);await new Promise(r=>socket.addEventListener('open',r,{once:true}));
    let id=0;const pending=new Map();socket.addEventListener('message',({data})=>{const m=JSON.parse(data),h=pending.get(m.id);if(h){pending.delete(m.id);m.error?h[1](new Error(JSON.stringify(m.error))):h[0](m.result);}});
    const command=(method,params={})=>new Promise((resolve,reject)=>{const n=++id;pending.set(n,[resolve,reject]);socket.send(JSON.stringify({id:n,method,params}));});
    const evaluate=async expression=>{const r=await command('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw new Error(r.exceptionDetails.exception?.description||JSON.stringify(r.exceptionDetails));return r.result.value;};
    const until=async expression=>{for(let i=0;i<160;i++){if(await evaluate(expression))return;await delay(100);}throw new Error('Timeout: '+expression+' '+await evaluate('JSON.stringify({errors:window.errors,alert:document.querySelector(".swal2-popup")?.textContent,body:document.body.textContent.slice(0,700)})'));};
    const click=selector=>evaluate(`document.querySelector(${JSON.stringify(selector)}).click()`);
    const paths={records:'student-records',centers:'exam-centers',certificates:'certificate-tracking',imports:'excel-imports',photos:'student-photos',reports:'reports'};
    const goto=async tab=>{await command('Page.navigate',{url:origin+'/g12-national-exams/'+paths[tab]});await until(`document.querySelector('[data-g12-exams][data-tab="${tab}"]')?.getAttribute('aria-busy')==='false'`);assert.deepEqual(await evaluate('window.errors'),[]);assert.equal(await evaluate('document.querySelectorAll("[data-g12-submenus] .dropdown-item").length'),6);assert.equal(await evaluate(`document.querySelector('[data-g12-submenus] [aria-current="page"]').getAttribute('href').endsWith('/${paths[tab]}')`),true);assert.equal(await evaluate('document.querySelector("[data-g12-exams] .nav-tabs")===null'),true);};
    const set=values=>evaluate(`(()=>{for(const [selector,value]of Object.entries(${JSON.stringify(values)})){document.querySelector(selector).value=value;}})()`);
    const submit=selector=>evaluate(`document.querySelector(${JSON.stringify(selector)}).requestSubmit()`);
    const photoFile=async(selector,name)=>evaluate(`(async()=>{const c=document.createElement('canvas');c.width=600;c.height=800;const x=c.getContext('2d');x.fillStyle='#e8efff';x.fillRect(0,0,600,800);const blob=await new Promise(r=>c.toBlob(r,'image/jpeg'));const dt=new DataTransfer();dt.items.add(new File([blob],${JSON.stringify(name)},{type:'image/jpeg'}));document.querySelector(${JSON.stringify(selector)}).files=dt.files;})()`);
    await command('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    await goto('records');await until('document.querySelectorAll("[data-g12-records] tr").length===15');
    assert.equal(await evaluate('document.querySelector(".premium-pagination-info").textContent.includes("22 entries")'),true);
    await click('#g12Pagination [data-page="2"]');await until('document.querySelectorAll("[data-g12-records] tr").length===7');
    await click('[data-g12-new]');await until('document.querySelector("#g12EditModal.show")!==null');
    await set({'#edit-student_id':'77777','#edit-academic_year':'2025-2026','#edit-student_name_en':'NEW BROWSER STUDENT','#edit-campus_id':'1','#edit-center_id':'1','#edit-grade':'12B','#edit-g12_letter_grade':'B'});await submit('[data-g12-edit-form]');
    await until('document.querySelector(".swal2-icon-success")!==null');await click('.swal2-confirm');await until('document.querySelector("[data-g12-exams]").getAttribute("aria-busy")==="false"');
    await set({'#g12Search':'77777'});await submit('[data-g12-filters]');await until('document.querySelector("[data-g12-records]").textContent.includes("NEW BROWSER STUDENT")');
    await click('[data-g12-edit]');await until('document.querySelector("#g12EditModal.show")!==null');assert.equal(await evaluate('document.querySelector("#edit-student_id").readOnly'),true);
    await set({'#edit-student_name_en':'EDITED BROWSER STUDENT'});await submit('[data-g12-edit-form]');await until('document.querySelector(".swal2-icon-success")!==null');await click('.swal2-confirm');await until('document.querySelector("[data-g12-records]").textContent.includes("EDITED BROWSER STUDENT")');
    await click('[data-g12-view]');await until('document.querySelector("#g12ViewModal.show")!==null');
    await photoFile('[data-g12-document-form] [name=file]','signed-receipt.jpg');await set({'[data-g12-document-form] [name=kind]':'soft_certificate_received'});await submit('[data-g12-document-form]');
    await until('document.querySelector(".swal2-icon-success")!==null');await click('.swal2-confirm');await until('document.querySelector("[data-g12-detail]").textContent.includes("signed-receipt.jpg")');
    await set({'[data-g12-collection=soft] [name=status]':'collected','[data-g12-collection=soft] [name=collected_date]':'2026-10-26','[data-g12-collection=soft] [name=recipient]':'Student'});await submit('[data-g12-collection=soft]');
    await until('document.querySelector(".swal2-icon-success")!==null');await click('.swal2-confirm');await until('document.querySelector("[data-g12-records]").textContent.includes("Collected")');
    await writeFile(join(root,'storage/app/testing/g12-national-record-desktop.png'),Buffer.from((await command('Page.captureScreenshot',{format:'png'})).data,'base64'));
    await goto('centers');await until('document.querySelector("[data-g12-centers]").textContent.includes("MoEYS Test Center")');await click('[data-g12-center-new]');await until('document.querySelector("#g12CenterModal.show")!==null');
    await until('document.querySelector("#g12CenterModal").classList.contains("show")&&!document.querySelector("#g12CenterName").parentElement.classList.contains("has-value")');
    assert.ok(await evaluate('document.querySelector("#g12CenterModal .modal-dialog").getBoundingClientRect().width<=460'));
    assert.equal(await evaluate('document.querySelector("#g12CenterCode").readOnly'),true);
    assert.equal(await evaluate('document.querySelector("#g12CenterModal .modal-body").classList.contains("premium-form-field")'),false);
    await set({'#g12CenterName':'BROWSER EXAM CENTER'});await evaluate('document.querySelector("#g12CenterName").dispatchEvent(new Event("input",{bubbles:true}))');
    await until('document.querySelector("#g12CenterName").parentElement.classList.contains("has-value")');
    await writeFile(join(root,'storage/app/testing/g12-center-modal-1440.png'),Buffer.from((await command('Page.captureScreenshot',{format:'png'})).data,'base64'));
    await submit('[data-g12-center-form]');await until('document.querySelector(".swal2-icon-success")!==null');await click('.swal2-confirm');await until('document.querySelector("[data-g12-centers]").textContent.includes("BROWSER EXAM CENTER")');
    const newCenter=await evaluate(`(async()=>{const r=await fetch('/g12-national-exams/meta?tab=centers');return (await r.json()).centers.find(c=>c.name==='BROWSER EXAM CENTER');})()`);assert.match(newCenter.source_id,/^EC-\d{6,}$/);
    await click(`[data-g12-center-edit="${newCenter.id}"]`);await until('document.querySelector("#g12CenterModal.show")!==null');await delay(400);assert.equal(await evaluate('document.querySelector("#g12CenterCode").value'),newCenter.source_id);await click('#g12CenterModal .btn-close');await until('document.querySelector("#g12CenterModal.show")===null');await delay(400);
    await command('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
    await click('[data-g12-center-new]');await until('document.querySelector("#g12CenterModal.show")!==null');await delay(400);
    assert.ok(await evaluate('document.querySelector("#g12CenterModal .modal-dialog").getBoundingClientRect().width<=358'));
    assert.equal(await evaluate(`(()=>{const modal=document.querySelector('#g12CenterModal');return [...modal.querySelectorAll('.premium-form-field')].every(field=>{const label=field.querySelector('label').getBoundingClientRect(),control=field.querySelector('input,select').getBoundingClientRect();return label.top>=control.top&&label.bottom<=control.bottom;});})()`),true);
    await writeFile(join(root,'storage/app/testing/g12-center-modal-390.png'),Buffer.from((await command('Page.captureScreenshot',{format:'png'})).data,'base64'));
    await click('#g12CenterModal .btn-close');await until('document.querySelector("#g12CenterModal.show")===null');await delay(400);
    await command('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    console.log('Compact center modal, separate floating labels, automatic IDs and edit preservation passed on desktop/mobile.');
    await goto('imports');
    assert.equal(await evaluate('document.querySelector("[data-g12-template]").textContent.includes("Download Excel Template")'),true);
    assert.equal(await evaluate(`(async()=>{const r=await fetch(document.querySelector('[data-g12-template]').href);const b=new Uint8Array(await r.arrayBuffer());return r.ok&&r.headers.get('content-type').includes('spreadsheetml')&&b[0]===80&&b[1]===75;})()`),true);
    console.log('Excel template download button returns a valid workbook.');
    const excelTransfer=async(kind,name='moeys.xlsx',size=20)=>evaluate(`(()=>{
        const zone=document.querySelector('[data-g12-excel-drop]'),input=zone.querySelector('input'),data=new DataTransfer();
        data.items.add(new File(['x'.repeat(${size})],${JSON.stringify(name)},{type:'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'}));
        if(${JSON.stringify(kind)}==='change'){input.files=data.files;input.dispatchEvent(new Event('change',{bubbles:true}));}
        else if(${JSON.stringify(kind)}==='drop')zone.dispatchEvent(new DragEvent('drop',{dataTransfer:data,bubbles:true,cancelable:true}));
        else{zone.focus();const event=new ClipboardEvent('paste',{clipboardData:data,bubbles:true,cancelable:true});
            if(${JSON.stringify(kind)}==='items')Object.defineProperty(event,'clipboardData',{value:{files:[],items:[{kind:'file',getAsFile:()=>data.files[0]}]}});
            zone.dispatchEvent(event);
        }
    })()`);
    assert.equal(await evaluate('document.querySelector("[data-g12-excel-preview]").disabled'),true);
    await excelTransfer('drop','dropped.xlsx');assert.equal(await evaluate('document.querySelector("[data-g12-excel-files]").textContent.includes("dropped.xlsx")'),true);
    await click('[data-g12-excel-remove]');assert.equal(await evaluate('document.querySelector("[data-g12-excel-form] input").files.length'),0);
    await excelTransfer('paste','pasted.xlsx');assert.equal(await evaluate('document.querySelector("[data-g12-excel-form] input").files[0].name'),'pasted.xlsx');
    await excelTransfer('items','items.xlsx');assert.equal(await evaluate('document.querySelector("[data-g12-excel-form] input").files[0].name'),'items.xlsx');
    await excelTransfer('drop','wrong.csv');assert.equal(await evaluate('document.querySelector("[data-g12-excel-drop]").getAttribute("aria-invalid")'),'true');
    assert.equal(await evaluate('document.querySelector("[data-g12-excel-error]").textContent.includes(".xlsx")'),true);
    await excelTransfer('paste','oversized.xlsx',20*1024*1024+1);assert.equal(await evaluate('document.querySelector("[data-g12-excel-error]").textContent.includes("exceeds 20 MB")'),true);
    assert.equal(await evaluate(`(()=>{const data=new DataTransfer();data.setData('text/plain','copied spreadsheet cells');const event=new ClipboardEvent('paste',{clipboardData:data,bubbles:true,cancelable:true});document.querySelector('[data-g12-excel-drop]').dispatchEvent(event);return event.defaultPrevented;})()`),false);
    await excelTransfer('change');assert.equal(await evaluate('document.querySelector("[data-g12-excel-preview]").disabled'),false);
    assert.equal(await evaluate('document.querySelector("[data-g12-excel-error]").hidden'),true);
    await writeFile(join(root,'storage/app/testing/g12-excel-upload-1440.png'),Buffer.from((await command('Page.captureScreenshot',{format:'png'})).data,'base64'));
    await submit('[data-g12-excel-form]');await until('!document.querySelector("[data-g12-import-preview]").hidden');
    await excelTransfer('paste','replacement.xlsx');assert.equal(await evaluate('document.querySelector("[data-g12-import-preview]").hidden'),true);
    await submit('[data-g12-excel-form]');await until('!document.querySelector("[data-g12-import-preview]").hidden');
    console.log('Excel drop, clipboard files/items, browse change, removal, type/size errors and stale preview reset passed.');
    assert.ok(await evaluate('document.querySelector("[data-g12-import-summary]").textContent.includes("0 with issues")'));await click('[data-g12-confirm]');await until('document.querySelector(".swal2-title")?.textContent==="Import MoEYS records?"');await click('.swal2-confirm');await until('document.querySelector(".swal2-icon-success")!==null');await click('.swal2-confirm');await until('document.querySelector("[data-g12-history]").textContent.includes("confirmed")');
    await goto('photos');
    assert.equal(await evaluate('document.querySelector("#photoYear").tagName'),'SELECT');
    assert.deepEqual(await evaluate('[...document.querySelector("#photoYear").options].map(o=>o.value)'),['','2025-2026']);
    await click('#photoYearToggle');
    await evaluate(`(()=>{const search=document.querySelector('#photoYear').parentElement.querySelector('input[type=search]');search.value='2099';search.dispatchEvent(new Event('input',{bubbles:true}));})()`);
    assert.equal(await evaluate('document.querySelector("#photoYearOptions").textContent.includes("No matches found.")'),true);
    await evaluate(`(()=>{const search=document.querySelector('#photoYear').parentElement.querySelector('input[type=search]');search.value='2025';search.dispatchEvent(new Event('input',{bubbles:true}));})()`);
    await click('#photoYearOptions [data-seat-option="2025-2026"]');
    assert.equal(await evaluate('document.querySelector("#photoYear").value'),'2025-2026');
    assert.equal(await evaluate('document.querySelector("#photoYearToggle").textContent'),'2025-2026');
    assert.equal(await evaluate('document.querySelector("[data-g12-photo-confirm]").disabled'),true);
    assert.equal(await evaluate('getComputedStyle(document.querySelector("[data-g12-photo-preview]")).display'),'none');
    await evaluate(`(()=>{const d=new DataTransfer();d.items.add(new File(['test'],'photo_2025-2026.zip',{type:'application/zip'}));const input=document.querySelector('[data-g12-photos-form] input[type=file]');input.files=d.files;Object.defineProperty(input.files[0],'size',{value:598*1024*1024});window.originalFetch=window.fetch;window.photoPreviewRequests=0;window.fetch=(...args)=>{if(String(args[0]).includes('/photos/preview'))window.photoPreviewRequests++;return window.originalFetch(...args);};})()`);
    await submit('[data-g12-photos-form]');await until('document.querySelector(".swal2-html-container")?.textContent.includes("200 MB per ZIP")');
    assert.equal(await evaluate('window.photoPreviewRequests'),0);await click('.swal2-confirm');await until('document.querySelector("[data-g12-exams]").getAttribute("aria-busy")==="false"');
    await evaluate(`(()=>{const d=new DataTransfer();d.items.add(new File(['test'],'small.zip',{type:'application/zip'}));document.querySelector('[data-g12-photos-form] input[type=file]').files=d.files;window.fetch=(...args)=>String(args[0]).includes('/photos/preview')?Promise.resolve(new Response('<html>Upload rejected</html>',{status:200,headers:{'Content-Type':'text/html'}})):window.originalFetch(...args);})()`);
    await submit('[data-g12-photos-form]');await until('document.querySelector(".swal2-html-container")?.textContent.includes("valid response")');
    assert.equal(await evaluate('document.querySelector(".swal2-html-container").textContent.includes("No photos were returned")'),false);
    await click('.swal2-confirm');await until('document.querySelector("[data-g12-exams]").getAttribute("aria-busy")==="false"');await evaluate('window.fetch=window.originalFetch');
    console.log('598 MB ZIP blocked before network upload; HTML rejection no longer reported as empty photo preview.');
    await evaluate(`(()=>{const d=new DataTransfer();d.items.add(new File(['not a zip'],'broken.zip',{type:'application/zip'}));const input=document.querySelector('[data-g12-photos-form] input[type=file]');input.files=d.files;input.dispatchEvent(new Event('change',{bubbles:true}));})()`);
    await submit('[data-g12-photos-form]');await until('document.querySelector(".swal2-icon-error")!==null');
    assert.equal(await evaluate('document.querySelector("[data-g12-photo-confirm]").disabled'),true);await click('.swal2-confirm');await until('document.querySelector("[data-g12-exams]").getAttribute("aria-busy")==="false"');
    await evaluate(`(async()=>{const blob=await(await fetch('/g12-national-exams/sample-photo-zip')).blob(),d=new DataTransfer();d.items.add(new File([blob],'photo_2025.zip',{type:'application/zip'}));const input=document.querySelector('[data-g12-photos-form] input[type=file]');input.files=d.files;input.dispatchEvent(new Event('change',{bubbles:true}));})()`);
    assert.equal(await evaluate('document.querySelector("[data-g12-photo-confirm]").disabled'),true);
    await submit('[data-g12-photos-form]');await until('!document.querySelector("[data-g12-photo-preview]").hidden');
    assert.equal(await evaluate('document.querySelectorAll("[data-photo-index]").length'),2);
    assert.equal(await evaluate('document.querySelectorAll("[data-photo-selected]:checked").length'),1);
    assert.equal(await evaluate('document.querySelector("[data-g12-photo-confirm]").disabled'),false);
    await click('[data-g12-photo-select-all]');assert.equal(await evaluate('document.querySelectorAll("[data-photo-selected]:checked").length'),2);
    await click('[data-g12-photo-select-all]');assert.equal(await evaluate('document.querySelector("[data-g12-photo-confirm]").disabled'),true);
    await click('[data-photo-selected]');
    assert.equal(await evaluate('document.querySelector("[data-photo-student]").value.includes("00123")'),true);await click('[data-g12-photo-confirm]');await until('document.querySelector(".swal2-title")?.textContent==="Upload selected photos?"');await click('.swal2-confirm');await until('document.querySelector(".swal2-title")?.textContent==="Photo Batch Complete"');await click('.swal2-confirm');
    assert.equal(await evaluate('document.querySelector("[data-g12-photo-confirm]").disabled'),true);
    await photoFile('[data-g12-photos-form] input[type=file]','unmatched-moeys-photo.jpg');await evaluate('document.querySelector("[data-g12-photos-form] input[type=file]").dispatchEvent(new Event("change",{bubbles:true}))');
    assert.equal(await evaluate('document.querySelector("[data-g12-photo-preview]").hidden'),true);
    await submit('[data-g12-photos-form]');await until('document.querySelector("[data-g12-photo-rows]").textContent.includes("unmatched-moeys-photo")');
    assert.equal(await evaluate('document.querySelector("[data-g12-photo-confirm]").disabled'),true);
    await evaluate('document.querySelector("[data-photo-student]").value=[...document.querySelector("#g12PhotoStudents").options].find(o=>o.value.startsWith("00124")).value;');await click('[data-photo-selected]');await click('[data-g12-photo-confirm]');await until('document.querySelector(".swal2-title")?.textContent==="Upload selected photos?"');await click('.swal2-confirm');await until('document.querySelector(".swal2-title")?.textContent==="Photo Batch Complete"');await click('.swal2-confirm');
    await click('#photoYearToggle');await click('#photoYearOptions [data-seat-option=""]');
    assert.equal(await evaluate('document.querySelector("[data-g12-photo-preview]").hidden'),true);assert.equal(await evaluate('document.querySelector("[data-g12-photo-confirm]").disabled'),true);
    console.log('ZIP preview rows, matched selection, Select All, invalid ZIP handling and file/year preview resets passed.');
    console.log('Searchable photo academic year dropdown uses student record years and filters options.');
    const state=decode(fixture('/state'));assert.equal(state.records.filter(r=>r.photo_path).length,2);assert.equal(state.records.find(r=>r.student_code==='80001').certificates.find(c=>c.type==='soft').status,'collected');assert.equal(state.records.find(r=>r.student_code==='77777').certificates.find(c=>c.type==='soft').status,'collected');
    await goto('reports');await click('[data-g12-export=print]');assert.ok(await evaluate('window.reportUrl.includes("format=print")'));await click('[data-g12-export=xlsx]');assert.ok(await evaluate('window.reportUrl.includes("format=xlsx")'));
    for(const width of [1440,390]){
        await command('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<500});
        for(const tab of ['records','centers','certificates','imports','photos','reports']){await goto(tab);assert.deepEqual(await evaluate('window.errors'),[]);if(tab==='records')await writeFile(join(root,`storage/app/testing/g12-national-list-${width}.png`),Buffer.from((await command('Page.captureScreenshot',{format:'png'})).data,'base64'));if(tab==='imports'){await excelTransfer('paste','mobile-and-desktop.xlsx');assert.equal(await evaluate('document.querySelector("[data-g12-excel-preview]").disabled'),false);await writeFile(join(root,`storage/app/testing/g12-excel-upload-${width}.png`),Buffer.from((await command('Page.captureScreenshot',{format:'png'})).data,'base64'));}}
        console.log(width+'px: all six sidebar submenus and active links render; forms and screens initialize without browser errors.');
    }
    console.log('Add/Edit, private receipt upload, subject-independent soft/hard collection, center settings, import confirmation, filename/manual photo matching and report links passed.');
    await command('Browser.close');
}finally{socket?.close();browser.kill();server.closeAllConnections();await new Promise(r=>server.close(r));}
