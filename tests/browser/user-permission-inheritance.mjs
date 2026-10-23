import assert from 'node:assert/strict';
import {build} from 'vite';
import {createServer} from 'node:http';
import {spawn,execFileSync} from 'node:child_process';
import {readFile,mkdtemp,writeFile} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join,resolve} from 'node:path';

const root=resolve(import.meta.dirname,'../..');
const php=process.env.CHAT_TEST_PHP||'C:/laragon/bin/php/php-8.4.25-Win32-vs17-x64/php.exe';
const chrome=process.env.CHAT_TEST_BROWSER||'C:/Program Files/Google/Chrome/Application/chrome.exe';
const bundle=await build({root,configFile:false,logLevel:'silent',build:{write:false,lib:{entry:join(root,'resources/js/accessManagement.js'),formats:['es'],fileName:()=> 'access.js'}}});
const assets=new Map();for(const output of Array.isArray(bundle)?bundle:[bundle])for(const file of output.output)assets.set('/'+file.fileName,file.type==='chunk'?file.code:file.source);
const css=await readFile(join(root,'resources/css/app.css'),'utf8');const toggleCss=css.slice(css.indexOf('.status-toggle {'),css.indexOf('.status-toggle {')+4500);
const markup=new Map(['user','full','role'].map(mode=>[mode,execFileSync(php,[join(root,'tests/browser/user-permission-inheritance-fixture.php'),mode],{cwd:root,encoding:'utf8'})]));
const server=createServer(async(req,res)=>{
    const url=new URL(req.url,'http://localhost');
    if(assets.has(url.pathname)){res.setHeader('Content-Type',url.pathname.endsWith('.js')?'text/javascript':'text/css');res.end(assets.get(url.pathname));return;}
    if(url.pathname.startsWith('/node_modules/')||url.pathname.startsWith('/resources/')){res.setHeader('Content-Type',url.pathname.endsWith('.css')?'text/css':'font/woff2');res.end(await readFile(join(root,url.pathname)));return;}
    res.setHeader('Content-Type','text/html; charset=utf-8');res.end(`<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/node_modules/@tabler/core/dist/css/tabler.min.css"><link rel="stylesheet" href="/node_modules/@tabler/icons-webfont/dist/tabler-icons.min.css"><link rel="stylesheet" href="/resources/css/pages/partials-permission-tree.css"><style>body{padding:16px}${toggleCss}</style></head><body>${markup.get(url.searchParams.get('mode')||'user')}<script>window.fixtureErrors=[];addEventListener('error',e=>fixtureErrors.push(e.message));addEventListener('unhandledrejection',e=>fixtureErrors.push(String(e.reason)));</script><script type="module" src="/access.js"></script></body></html>`);
});
await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
const origin='http://127.0.0.1:'+server.address().port;
const profile=await mkdtemp(join(tmpdir(),'wis-inherited-permissions-'));
const browser=spawn(chrome,['--headless=new','--no-sandbox','--disable-gpu','--no-first-run','--remote-debugging-port=0',`--user-data-dir=${profile}`,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));let socket;
try{
    let port;for(let i=0;i<100;i++){try{port=Number((await readFile(join(profile,'DevToolsActivePort'),'utf8')).split('\n')[0]);break;}catch{await delay(100);}}
    assert.ok(port);const pages=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();socket=new WebSocket(pages.find(p=>p.type==='page').webSocketDebuggerUrl);
    await new Promise(resolve=>socket.addEventListener('open',resolve,{once:true}));
    let id=0;const pending=new Map();socket.addEventListener('message',({data})=>{const m=JSON.parse(data),handler=pending.get(m.id);if(!handler)return;pending.delete(m.id);m.error?handler[1](new Error(JSON.stringify(m.error))):handler[0](m.result);});
    const command=(method,params={})=>new Promise((resolve,reject)=>{const key=++id,timer=setTimeout(()=>{pending.delete(key);reject(new Error('Timeout: '+method));},15000);pending.set(key,[value=>{clearTimeout(timer);resolve(value);},error=>{clearTimeout(timer);reject(error);}]);socket.send(JSON.stringify({id:key,method,params}));});
    const evaluate=async expression=>{const r=await command('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw new Error(r.exceptionDetails.exception?.description||JSON.stringify(r.exceptionDetails));return r.result.value;};
    const until=async expression=>{for(let i=0;i<150;i++){if(await evaluate(expression))return;await delay(50);}throw new Error('Timed out: '+expression);};
    const state=code=>evaluate(`(()=>{const b=document.querySelector('[data-permission-code="${code}"]');return {on:b.dataset.status==='1',disabled:b.disabled,inherited:b.dataset.permissionInherited==='true'};})()`);
    const toggle=code=>evaluate(`document.querySelector('[data-permission-code="${code}"]').click()`);
    const posted=()=>evaluate(`new FormData(document.querySelector('form')).getAll('permissions[]').map(Number)`);
    for(const width of [1440,390]){
        await command('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<500});
        await command('Page.navigate',{url:origin});await until('document.querySelector("[data-permission-module-toggle]")?.dataset.mobileReady==="1"');
        for(const code of ['photo-editor.view','students.view','students.enrollment.view','students.enrollment.profile','western-scholarship.tab-scoring','western-scholarship.scoring','dashboard.customize'])
            assert.deepEqual(await state(code),{on:true,disabled:true,inherited:true},`${code} inherits its configured grant`);
        assert.equal((await state('dashboard.reset')).on,true,'Direct user permissions remain ON');
        const sources=await evaluate(`[...document.querySelectorAll('.permission-inheritance-badge')].map(e=>e.getAttribute('data-bs-original-title')||e.title)`);
        assert.ok(sources.includes('Role: Registrar Officer'));assert.ok(sources.includes('Department: Academics'));
        const ids=await evaluate('permissionFixtureIds');assert.deepEqual(await posted(),[ids['dashboard.reset']],'Inherited grants are excluded from user-only save payload');
        for(const code of ['students.enrollment.update','western-scholarship.review-scores','hrm-staff-data.import']){
            assert.equal((await state(code)).disabled,false,`Inherited dependencies allow additional ${code}`);await toggle(code);assert.equal((await state(code)).on,true);
        }
        assert.deepEqual((await posted()).sort((a,b)=>a-b),[ids['dashboard.reset'],ids['students.enrollment.update'],ids['western-scholarship.review-scores'],ids['hrm-staff-data.import']].sort((a,b)=>a-b));
        await evaluate('document.querySelector("[data-full-access]").click();document.querySelector("[data-full-access]").click()');
        assert.equal((await state('photo-editor.view')).on,true,'Clearing extra user grants retains role access');
        assert.equal((await state('dashboard.customize')).on,true,'Clearing extra user grants retains department access');
        assert.equal((await state('dashboard.reset')).on,false);
        await toggle('students.enrollment.update');assert.equal((await state('students.enrollment.update')).on,true,'Additional grants remain editable after Full Access changes');
        const beforeSearch=await posted();
        await evaluate(`(()=>{const module=document.querySelector('[data-permission-code="western-scholarship.answer-marked-view"]').closest('[data-permission-module]');module.classList.add('is-collapsed');const s=document.querySelector('[data-permission-module-search]');s.value='marked';s.dispatchEvent(new Event('input'));})()`);
        assert.equal(await evaluate(`document.querySelector('[data-permission-code="western-scholarship.answer-marked-view"]').closest('[data-permission-module]').classList.contains('is-collapsed')`),false,'Searching reveals matching actions inside collapsed modules');
        assert.equal(await evaluate(`document.querySelector('[data-permission-code="western-scholarship.answer-marked-view"]').getBoundingClientRect().height>0`),true,'Marked-sheet permission is visible after search');
        assert.equal(await evaluate(`document.querySelector('[data-permission-code="western-scholarship.answer-verify"]').getBoundingClientRect().height`),0,'Unrelated actions do not bury the matching permission');
        assert.deepEqual(await posted(),beforeSearch,'Searching does not enable or disable permissions');
        await writeFile(join(root,`storage/app/testing/marked-permission-search-${width}.png`),Buffer.from((await command('Page.captureScreenshot',{format:'png'})).data,'base64'));
        await evaluate(`(()=>{const s=document.querySelector('[data-permission-module-search]');s.value='';s.dispatchEvent(new Event('input'));})()`);
        assert.equal(await evaluate(`document.querySelector('[data-permission-code="western-scholarship.answer-marked-view"]').closest('[data-permission-module]').classList.contains('is-collapsed')`),true,'Clearing search restores the previous collapsed state');
        assert.equal(await evaluate(`document.querySelector('[data-permission-code="western-scholarship.answer-verify"]').closest('.permission-action').classList.contains('d-none')`),false,'Clearing search restores other actions');
        await evaluate(`document.querySelector('[data-permission-module-search]').value='photo editor';document.querySelector('[data-permission-module-search]').dispatchEvent(new Event('input'));`);
        assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true,'Permission cards fit screen width');
        await writeFile(join(root,`storage/app/testing/user-permission-inheritance-${width}.png`),Buffer.from((await command('Page.captureScreenshot',{format:'png'})).data,'base64'));
        assert.deepEqual(await evaluate('fixtureErrors'),[]);
        await command('Page.navigate',{url:origin+'/?mode=full'});await until('document.querySelector("[data-permission-module-toggle]")?.dataset.mobileReady==="1"');
        assert.equal(await evaluate('document.querySelector("[data-full-access]").disabled'),true,'Fully inherited access is locked at its source');
        assert.deepEqual(await posted(),[ids['dashboard.reset']],'Preexisting duplicate user grants survive without copying inherited grants');
        await command('Page.navigate',{url:origin+'/?mode=role'});await until('document.querySelector("[data-permission-module-toggle]")?.dataset.mobileReady==="1"');
        assert.deepEqual(await state('photo-editor.view'),{on:true,disabled:false,inherited:false},'The role itself remains editable');
        await toggle('photo-editor.view');assert.equal((await state('photo-editor.view')).on,false);
        console.log(`${width}px: role/department inheritance, nested tabs, direct grants, dependencies, save payload, Full Access and role editing passed.`);
    }
    await command('Browser.close');
}finally{socket?.close();browser.kill();server.closeAllConnections();await new Promise(resolve=>server.close(resolve));}
