// Real Blade/JS, synthetic family records and a stub API; no production writes.
import assert from 'node:assert/strict';
import {createServer} from 'node:http';
import {spawn,execFileSync} from 'node:child_process';
import {readFile,mkdtemp} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join,resolve} from 'node:path';
import {build} from 'vite';

const root=resolve(import.meta.dirname,'../..');
const php='C:/laragon/bin/php/php-8.4.25-Win32-vs17-x64/php.exe';
const chrome='C:/Program Files/Google/Chrome/Application/chrome.exe';
const pages={};
for(const mode of ['campus','central','readonly']) pages[mode]=execFileSync(php,[join(root,'tests/browser/family-management-fixture.php'),mode],{cwd:root,encoding:'utf8'});
const bundle=await build({root,configFile:false,logLevel:'silent',build:{write:false,lib:{entry:join(root,'resources/js/families.js'),formats:['es'],fileName:()=> 'families.js',cssFileName:'families'}}});
const files=new Map();
for(const output of Array.isArray(bundle)?bundle:[bundle]) for(const item of output.output) files.set('/'+item.fileName,item.type==='chunk'?item.code:item.source);
const bootstrap=await readFile(join(root,'node_modules/bootstrap/dist/js/bootstrap.bundle.min.js'));
const bootstrapCss=await readFile(join(root,'node_modules/bootstrap/dist/css/bootstrap.min.css'));
const familyCss=await readFile(join(root,'resources/css/pages/families.css'));
let mode='campus';
const records=()=>[1,2].map(id=>({id,family_number:'F'+id,status:1,students_count:2,can_update:mode==='central'||(mode==='campus'&&id===1),
    can_members_create:mode!=='readonly'&&(mode==='central'||id===1),can_members_update:mode!=='readonly'&&(mode==='central'||id===1),can_members_delete:mode!=='readonly'&&(mode==='central'||id===1),
    can_delete:false,can_status:mode==='central',shared_info:{children:2,campuses:['BCH','BKK']},
    members:[{id:1,relationship_type:'mother',full_name_en:'MOTHER',phone:'+85512888888'},{id:2,relationship_type:'father',full_name_en:'FATHER',phone:'+85512999999'}],
    students:[1,2].map(student=>({id:student,student_id:'STU'+student,full_name_en:'STUDENT '+student,can_link:mode==='central'||(mode==='campus'&&student===1),can_unlink:mode==='central'||(mode==='campus'&&student===1),enrollments:[]}))}));
const pageData=data=>({data,current_page:1,last_page:1,per_page:10,total:data.length,from:1,to:data.length,links:[]});
const server=createServer(async(req,res)=>{
    const path=new URL(req.url,'http://localhost').pathname;
    if(path==='/'){res.setHeader('Content-Type','text/html');res.end(`<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width"><meta name="csrf-token" content="test"><link rel="stylesheet" href="/bootstrap.css"><link rel="stylesheet" href="/families.css"><link rel="stylesheet" href="/family-page.css">${pages[mode]}<script src="/bootstrap.js"></script><script type="module" src="/families.js"></script>`);return;}
    if(path==='/bootstrap.js'){res.setHeader('Content-Type','text/javascript');res.end(bootstrap);return;}
    if(path==='/bootstrap.css'||path==='/family-page.css'){res.setHeader('Content-Type','text/css');res.end(path==='/bootstrap.css'?bootstrapCss:familyCss);return;}
    if(files.has(path)){res.setHeader('Content-Type',path.endsWith('.css')?'text/css':'text/javascript');res.end(files.get(path));return;}
    res.setHeader('Content-Type','application/json');
    if(path==='/families/fetch'){res.end(JSON.stringify(pageData(records())));return;}
    if(path==='/families/link-options'){res.end(JSON.stringify({students:[{id:5,student_id:'STU5',full_name_en:'OWN CAMPUS CHILD'}]}));return;}
    if(path.endsWith('/members')){res.end(JSON.stringify({data:records()[0].members}));return;}
    if(path.endsWith('/history')){res.end(JSON.stringify(pageData([{id:1,family_id:1,action:'family_updated',changed_by_name:'Campus Registrar',created_at:'2026-10-21T00:00:00Z',before_values:{family:{family_number:'F1'},members:[{id:1,relationship_type:'mother',full_name_en:'OLD NAME'}]},after_values:{family:{family_number:'F1'},members:[{id:1,relationship_type:'mother',full_name_en:'NEW NAME'}]}}])));return;}
    res.statusCode=404;res.end('{}');
});
await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
const url=`http://127.0.0.1:${server.address().port}/`;
const profile=await mkdtemp(join(tmpdir(),'family-management-'));
const browser=spawn(chrome,['--headless=new','--disable-gpu','--no-sandbox','--no-first-run','--remote-debugging-port=0',`--user-data-dir=${profile}`,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));let socket;
try{
    let port;for(let i=0;i<100;i++){try{port=Number((await readFile(join(profile,'DevToolsActivePort'),'utf8')).split('\n')[0]);break;}catch{await delay(100);}}
    assert.ok(port,'Chrome starts');
    const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();socket=new WebSocket(tabs.find(p=>p.type==='page').webSocketDebuggerUrl);
    await new Promise(resolve=>socket.addEventListener('open',resolve,{once:true}));let sequence=0;const pending=new Map();
    socket.addEventListener('message',({data})=>{const m=JSON.parse(data);if(pending.has(m.id)){const [yes,no]=pending.get(m.id);pending.delete(m.id);m.error?no(new Error(JSON.stringify(m.error))):yes(m.result);}});
    const command=(method,params={})=>new Promise((yes,no)=>{const id=++sequence;const timeout=setTimeout(()=>{pending.delete(id);no(new Error('Timeout '+method));},15000);pending.set(id,[v=>{clearTimeout(timeout);yes(v);},e=>{clearTimeout(timeout);no(e);}]);socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const r=await command('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw new Error(r.exceptionDetails.exception?.description);return r.result.value;};
    const until=async expression=>{for(let i=0;i<120;i++){if(await evaluate(expression))return;await delay(50);}throw new Error('Timed out '+expression+' '+JSON.stringify(await evaluate('window.fixtureErrors')));};
    const click=selector=>evaluate(`document.querySelector(${JSON.stringify(selector)}).click()`);
    await command('Page.enable');
    await command('Page.addScriptToEvaluateOnNewDocument',{source:'window.fixtureErrors=[];window.addEventListener("error",e=>fixtureErrors.push(e.message));window.addEventListener("unhandledrejection",e=>fixtureErrors.push(String(e.reason)));'});
    for(const width of [1280,390])for(const current of ['campus','central','readonly']){
        mode=current;await command('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<500});
        await command('Page.navigate',{url});await until('document.querySelectorAll("#familiesTable [data-family-members]").length===2');
        assert.equal(await evaluate('document.querySelectorAll("[data-delete]").length'),0,'Linked families have no Delete');
        assert.equal(await evaluate(`!!document.querySelector('[data-edit="2"]')`),mode==='central','Foreign family Edit obeys scope');
        if(mode!=='readonly'){
            await click('[data-edit="1"]');await until('document.getElementById("familyModal").classList.contains("show")');
            assert.match(await evaluate('document.getElementById("familySharedNotice").textContent'),/2 children across BCH, BKK/);
            assert.equal(await evaluate('document.getElementById("familyStatusToggle").disabled'),mode!=='central');
            assert.equal(await evaluate('new FormData(document.getElementById("familyForm")).has("status")'),mode==='central');
            await click('#familyModal [data-bs-dismiss]');await delay(350);
            await click('[data-family-members="1"]');await until('document.querySelectorAll("#membersTable tr").length===2');
            assert.match(await evaluate('document.getElementById("membersSharedNotice").textContent'),/Changes apply to the whole family/);
            await click('#membersModal [data-bs-dismiss]');await delay(350);
            await click('[data-family-link="1"]');await until('document.getElementById("linkFamilyStudentModal").classList.contains("show")');
            assert.match(await evaluate('document.getElementById("linkFamilyStudent").textContent'),/OWN CAMPUS CHILD/);
            await click('#linkFamilyStudentModal [data-bs-dismiss]');await delay(350);
        }else{
            assert.equal(await evaluate('document.querySelectorAll("[data-edit],[data-family-link]").length'),0);
            assert.equal(await evaluate('document.getElementById("newFamily").classList.contains("d-none")'),true);
        }
        await click('[data-family-history="1"]');await until('document.getElementById("familyHistoryBody").textContent.includes("NEW NAME")');
        assert.match(await evaluate('document.getElementById("familyHistoryBody").textContent'),/OLD NAME/);
        assert.deepEqual(await evaluate('window.fixtureErrors'),[],'No browser errors');
        console.log(`${mode} ${width}px: scoped actions, shared notice, links, status and history passed.`);
    }
    await command('Browser.close');
}finally{socket?.close();browser.kill();server.closeAllConnections();await new Promise(resolve=>server.close(resolve));}
