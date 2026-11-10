import assert from 'node:assert/strict';
import {createServer} from 'node:http';
import {spawn} from 'node:child_process';
import {readFile,writeFile,mkdtemp} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join,resolve} from 'node:path';
const root=resolve(import.meta.dirname,'../..'),qa=join(root,'storage/app/testing/student-data-transfer');
const temp=await mkdtemp(join(tmpdir(),'school-csv-'));
let state={processed:0,total:100,imported:0,failed:0,percent:0,phase:'preparing'},posts=0,rejectNext=false;
const server=createServer(async(req,res)=>{try{
 const url=new URL(req.url,'http://localhost');
 if(['/node_modules/','/resources/','/public/fonts/'].some(prefix=>url.pathname.startsWith(prefix))){res.setHeader('Content-Type',url.pathname.endsWith('.css')?'text/css':url.pathname.endsWith('.js')?'text/javascript':'application/octet-stream');res.end(await readFile(join(root,url.pathname)));return;}
 if(url.pathname.startsWith('/student-import-progress/')){res.setHeader('Content-Type','application/json');res.end(JSON.stringify(state));return;}
 if(req.method==='POST'){
  posts++;for await(const chunk of req){};
  res.setHeader('Content-Type','application/json');
  if(rejectNext){rejectNext=false;res.statusCode=422;res.end(JSON.stringify({message:'Missing required columns: academic_year_id'}));return;}
  state={processed:0,total:100,imported:0,failed:0,percent:0,phase:'processing'};
  const timer=setInterval(()=>{state.processed=Math.min(100,state.processed+25);state.percent=state.processed;state.failed=state.processed/5;state.imported=state.processed-state.failed;if(state.processed===100)clearInterval(timer);},350);
  setTimeout(()=>res.end(JSON.stringify({redirect:`/students/data-transfer?finished=${url.pathname.split('/')[3]}`,total:100,imported:80,failed:20})),2400);return;
 }
 let html=await readFile(join(qa,'page.html'),'utf8');
 html=html.replace(/https?:\/\/[^/"\s]+\/(students\/data-transfer[^"\s]*|student-import-progress[^"\s]*)/g,'/$1');
 res.setHeader('Content-Type','text/html');res.end(html);
}catch(e){res.statusCode=500;res.end(e.message);}});
await new Promise(r=>server.listen(0,'127.0.0.1',r));const origin=`http://127.0.0.1:${server.address().port}`;
const browser=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--no-sandbox','--disable-gpu','--no-first-run','--remote-debugging-port=0',`--user-data-dir=${join(temp,'chrome')}`,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(r=>setTimeout(r,ms));let socket;
try{
 let port;for(let i=0;i<100;i++){try{port=Number((await readFile(join(temp,'chrome/DevToolsActivePort'),'utf8')).split('\n')[0]);break;}catch{await delay(100);}}
 const pages=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();socket=new WebSocket(pages.find(p=>p.type==='page').webSocketDebuggerUrl);await new Promise(r=>socket.addEventListener('open',r,{once:true}));
 let id=0;const pending=new Map();socket.addEventListener('message',({data})=>{const m=JSON.parse(data),p=pending.get(m.id);if(p){pending.delete(m.id);m.error?p[1](new Error(JSON.stringify(m.error))):p[0](m.result);}});
 const command=(method,params={})=>new Promise((resolve,reject)=>{const n=++id;pending.set(n,[resolve,reject]);socket.send(JSON.stringify({id:n,method,params}));});
 const evaluate=async expression=>{const r=await command('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(r.exceptionDetails)throw new Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
 const until=async expression=>{for(let i=0;i<100;i++){if(await evaluate(expression))return;await delay(80);}throw new Error('Timeout '+expression);};
 await command('Page.enable');await command('Emulation.setDeviceMetricsOverride',{width:1440,height:1100,deviceScaleFactor:1,mobile:false});
 for(const [index,type] of ['students','enrollments','families'].entries()){
  await command('Page.navigate',{url:origin+'/students/data-transfer'});await until('document.querySelectorAll("[data-csv-dropzone]:not([hidden])").length===3');
  await evaluate(`window.form=document.querySelectorAll('[data-csv-import]')[${index}];window.zone=form.querySelector('[data-csv-dropzone]');window.transfer=new DataTransfer();transfer.items.add(new File(['student_id,academic_year_id\\n00123,2025-2026'],'sample.csv',{type:'text/csv'}));`);
  if(type==='students'){
   await evaluate(`zone.dispatchEvent(new DragEvent('drop',{bubbles:true,cancelable:true,dataTransfer:transfer}));form.querySelector('.document-file-remove').click();`);
   assert.equal(await evaluate(`form.querySelector('[data-csv-file-list]').children.length`),0);
   await evaluate(`const invalid=new DataTransfer();invalid.items.add(new File(['x'],'bad.pdf'));zone.dispatchEvent(new DragEvent('drop',{bubbles:true,cancelable:true,dataTransfer:invalid}));`);
   assert.match(await evaluate(`form.querySelector('[data-csv-error]').textContent`),/CSV or TXT/);
   await evaluate(`zone.dispatchEvent(new DragEvent('drop',{bubbles:true,cancelable:true,dataTransfer:transfer}));`);
  }else if(type==='enrollments')await evaluate(`zone.dispatchEvent(new ClipboardEvent('paste',{bubbles:true,cancelable:true,clipboardData:transfer}));`);
  else{
   await evaluate(`window.browsed=false;form.querySelector('input[type=file]').addEventListener('click',()=>window.browsed=true);zone.dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true}));`);assert.equal(await evaluate('window.browsed'),true);
   const doc=await command('DOM.getDocument');const node=await command('DOM.querySelector',{nodeId:doc.root.nodeId,selector:'#csv-file-families'});await command('DOM.setFileInputFiles',{nodeId:node.nodeId,files:[join(qa,'sample.csv')]});
  }
  assert.match(await evaluate(`form.querySelector('.document-file-name').textContent`),/sample.csv/);
  if(index===0){await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:true}).then(r=>writeFile(join(qa,'desktop.png'),Buffer.from(r.data,'base64')));}
  if(index===1){await command('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});await evaluate(`document.documentElement.dataset.bsTheme='dark'`);}
  await evaluate(`form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));`);
  await until(`document.querySelector('[data-import-modal] [role=progressbar]')?.getAttribute('aria-valuenow')>0 && document.querySelector('[data-import-modal] [role=progressbar]')?.getAttribute('aria-valuenow')<100 && document.querySelector('[data-live-stage]')?.textContent==='Importing records'`);
  const progress=await evaluate(`({value:Number(form.querySelector('[role=progressbar]').getAttribute('aria-valuenow')),imported:Number(form.querySelector('[data-csv-imported]').textContent),failed:Number(form.querySelector('[data-csv-failed]').textContent),disabled:[...document.querySelectorAll('[data-csv-import] button[type=submit]')].every(b=>b.disabled)})`);
  assert.equal(progress.imported+progress.failed,progress.value);assert.equal(progress.disabled,true);
  if(index===1){await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:true}).then(r=>writeFile(join(qa,'mobile-dark-progress.png'),Buffer.from(r.data,'base64')));}
  await until(`location.search.includes('finished=${type}') && Array.isArray(window.errors) && document.querySelectorAll('[data-csv-dropzone]:not([hidden])').length===3`);assert.equal(posts,index+1);assert.deepEqual(await evaluate('window.errors'),[]);
 }
 await command('Page.navigate',{url:origin+'/students/data-transfer'});await until('document.querySelectorAll("[data-csv-dropzone]:not([hidden])").length===3');rejectNext=true;
 await evaluate(`const f=document.querySelector('[data-csv-import]'),d=new DataTransfer();d.items.add(new File(['x'],'sample.csv'));f.querySelector('[data-csv-dropzone]').dispatchEvent(new DragEvent('drop',{bubbles:true,cancelable:true,dataTransfer:d}));f.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));`);
 await until(`document.querySelector('.swal2-title')?.textContent==='Import Needs Attention'`);
 assert.match(await evaluate(`document.querySelector('[data-csv-error]').textContent`),/Missing required columns/);
 assert.equal(await evaluate(`[...document.querySelectorAll('[data-csv-import] button[type=submit]')].every(b=>!b.disabled)`),true);
 assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true);
 console.log('PASS: all three imports, drag/drop, paste, keyboard browse, removal, validation, real processing percentages, duplicate-click protection, errors, desktop and mobile dark layouts.');
}finally{socket?.close();browser.kill();server.close();}
