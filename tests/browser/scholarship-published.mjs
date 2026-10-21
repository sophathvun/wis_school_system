// Render actual Blade/JS against synthetic in-memory scholarship records.
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
const bundle=await build({root,configFile:false,logLevel:'silent',build:{write:false,lib:{entry:{scholarship:join(root,'resources/js/westernScholarship.js'),access:join(root,'resources/js/accessManagement.js')},formats:['es'],fileName:(_,name)=>name+'.js'}}});
const files=new Map();for(const output of Array.isArray(bundle)?bundle:[bundle])for(const item of output.output)files.set('/'+item.fileName,item.type==='chunk'?item.code:item.source);
const appCss=await readFile(join(root,'resources/css/app.css'),'utf8');
const toggleCss=appCss.slice(appCss.indexOf('.status-toggle {'),appCss.indexOf('.status-toggle {')+4500);
let mode='campus';const htmlCache=new Map();
const server=createServer(async(req,res)=>{
    const url=new URL(req.url,'http://localhost');
    if(files.has(url.pathname)){res.setHeader('Content-Type',url.pathname.endsWith('.css')?'text/css':'text/javascript');res.end(files.get(url.pathname));return;}
    if(url.pathname.startsWith('/resources/')||url.pathname.startsWith('/node_modules/')){res.setHeader('Content-Type',url.pathname.endsWith('.css')?'text/css':'font/woff2');res.end(await readFile(join(root,url.pathname)));return;}
    const key=mode+url.search;
    if(!htmlCache.has(key))htmlCache.set(key,execFileSync(php,[join(root,'tests/browser/scholarship-published-fixture.php'),mode,url.searchParams.toString()],{cwd:root,encoding:'utf8'}).replaceAll('http://localhost/students/western-scholarship','/students/western-scholarship'));
    const permissions=mode==='permissions';res.setHeader('Content-Type','text/html; charset=utf-8');
    res.end(`<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/node_modules/@tabler/core/dist/css/tabler.min.css"><link rel="stylesheet" href="/node_modules/@tabler/icons-webfont/dist/tabler-icons.min.css"><link rel="stylesheet" href="/resources/css/pages/${permissions?'partials-permission-tree':'western-scholarship'}.css"><style>body{padding:16px}${permissions?toggleCss:''}</style>${htmlCache.get(key)}<script type="module" src="/${permissions?'access':'scholarship'}.js"></script>`);
});
await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
const origin='http://127.0.0.1:'+server.address().port;
const profile=await mkdtemp(join(tmpdir(),'scholarship-published-'));
const browser=spawn(chrome,['--headless=new','--no-sandbox','--disable-gpu','--no-first-run','--remote-debugging-port=0',`--user-data-dir=${profile}`,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));let socket;
try{
    let port;for(let i=0;i<100;i++){try{port=Number((await readFile(join(profile,'DevToolsActivePort'),'utf8')).split('\n')[0]);break;}catch{await delay(100);}}
    const pages=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();socket=new WebSocket(pages.find(page=>page.type==='page').webSocketDebuggerUrl);
    await new Promise((resolve,reject)=>{socket.addEventListener('open',resolve,{once:true});socket.addEventListener('error',reject,{once:true});});
    let id=0;const pending=new Map(),errors=[];
    socket.addEventListener('message',({data})=>{const msg=JSON.parse(data);if(msg.method==='Runtime.exceptionThrown')errors.push(msg.params.exceptionDetails.exception?.description||msg.params.exceptionDetails.text);const handler=pending.get(msg.id);if(!handler)return;pending.delete(msg.id);msg.error?handler[1](new Error(JSON.stringify(msg.error))):handler[0](msg.result);});
    const command=(method,params={})=>new Promise((resolve,reject)=>{const request=++id;pending.set(request,[resolve,reject]);socket.send(JSON.stringify({id:request,method,params}));});
    const evaluate=async expression=>{const result=await command('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(result.exceptionDetails)throw new Error(JSON.stringify(result.exceptionDetails));return result.result.value;};
    const until=async expression=>{for(let i=0;i<160;i++){if(await evaluate(expression))return;await delay(50);}throw new Error('Timed out: '+expression+'; browser errors: '+errors.join('; '));};
    const click=selector=>evaluate(`document.querySelector(${JSON.stringify(selector)}).click()`);
    await command('Page.enable');await command('Runtime.enable');await command('Emulation.setFocusEmulationEnabled',{enabled:true});
    for(const mobile of [false,true]){
        await command('Emulation.setDeviceMetricsOverride',{width:mobile?390:1440,height:mobile?844:980,deviceScaleFactor:1,mobile});
        for(mode of ['campus','central','readonly']){
            await command('Page.navigate',{url:origin});await until('document.querySelector("[data-result-filters-ready]")!==null');
            assert.equal(await evaluate('document.querySelectorAll("[data-published-winner]").length'),mode==='central'?24:12);
            assert.equal(await evaluate('document.querySelector("#scholarshipResultFilters").hidden'),mobile);
            assert.equal(await evaluate('document.querySelector("[data-result-filters] [name=tab]").value'),'scholarship-result');
            assert.equal(await evaluate('document.querySelector("[data-result-award-form],[data-result-grade-metrics],[data-result-print-settings]")===null'),true);
            assert.equal(await evaluate('/CONFIDENTIAL_EOY|PRIVATE_SNAPSHOT|EOY Avg|Total Score|Exam Scores|No Award/.test(document.body.innerHTML)'),false);
            assert.equal(await evaluate('document.querySelectorAll("[data-result-report-link]").length'),mode==='readonly'?0:3);
            if(mode==='campus')assert.equal(await evaluate('document.querySelector("a[data-scholarship-tab-label=Result]")===null'),true);
            assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'),true);
            if(mode!=='readonly'){
                await evaluate('window.open=url=>window.exportUrl=String(url)');await click('[data-result-report-format=print]');
                await until('document.querySelector("#scholarshipResultReportScope").classList.contains("show")');
                assert.equal(await evaluate('document.querySelector("[data-result-report-scope] [name=report_students]").type'),'hidden');
                assert.equal(await evaluate('document.querySelector("[data-result-report-scope] [name=report_students]").value'),'winners');
                await click('[data-result-report-open]');await until('Boolean(window.exportUrl)');
                assert.match(await evaluate('window.exportUrl'),/scholarship-results\/print\?/);assert.match(await evaluate('window.exportUrl'),/report_students=winners/);
                await until('!document.querySelector(".modal.show,.modal-backdrop")');
            }
            await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:false}).then(result=>writeFile(join(root,`storage/app/testing/scholarship-published-${mode}-${mobile?'mobile':'desktop'}.png`),Buffer.from(result.data,'base64')));
        }
        mode='permissions';await command('Page.navigate',{url:origin});await until('document.querySelector("[data-permission-tab=scoring]")!==null');await delay(100);
        assert.equal(await evaluate('document.querySelectorAll("[data-permission-tab]").length'),9);
        if(mobile)await click('[data-permission-module-toggle]');
        const tab='[data-permission-code="western-scholarship.tab-scoring"]',action='[data-permission-code="western-scholarship.scoring"]';
        assert.equal(await evaluate(`document.querySelector(${JSON.stringify(action)}).dataset.status`),'1');
        await click(tab);assert.equal(await evaluate(`document.querySelector(${JSON.stringify(action)}).disabled`),true);
        assert.equal(await evaluate(`document.querySelector(${JSON.stringify(action)}).querySelector('input').checked`),false);
        await click(tab);assert.equal(await evaluate(`document.querySelector(${JSON.stringify(action)}).disabled`),false);await click(action);
        await click('[data-permission-code="western-scholarship.view"]');
        assert.equal(await evaluate('[...document.querySelectorAll("[data-permission-tab] button")].every(button=>button.disabled&&button.dataset.status==="0")'),true);
        await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:false}).then(result=>writeFile(join(root,`storage/app/testing/scholarship-permission-tabs-${mobile?'mobile':'desktop'}.png`),Buffer.from(result.data,'base64')));
    }
    assert.deepEqual(errors,[]);console.log('Published winners, score-free exports and tab permission dependencies passed for Campus/Central/read-only users on desktop and mobile.');
}finally{socket?.close();browser.kill();server.closeAllConnections();await new Promise(resolve=>server.close(resolve));}
