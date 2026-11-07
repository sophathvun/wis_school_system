import assert from 'node:assert/strict';
import {build} from 'vite';
import {createServer} from 'node:http';
import {spawn,execFileSync} from 'node:child_process';
import {readFile,mkdtemp,writeFile,mkdir} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join,resolve} from 'node:path';
const root=resolve(import.meta.dirname,'../..'),php='C:/laragon/bin/php/php-8.4.25-Win32-vs17-x64/php.exe';
const temp=await mkdtemp(join(tmpdir(),'wis-g9-results-')),db=join(temp,'test.sqlite');await writeFile(db,'');
const bundle=await build({root,configFile:false,logLevel:'silent',build:{write:false,lib:{entry:{results:join(root,'resources/js/g9Results.js'),forms:join(root,'resources/js/premiumForms.js')},formats:['es'],fileName:(_format,name)=>name+'.js',cssFileName:'g9-browser'}}});
const files=new Map();for(const output of Array.isArray(bundle)?bundle:[bundle])for(const f of output.output)files.set('/'+f.fileName,f.type==='chunk'?f.code:f.source);
const server=createServer(async(req,res)=>{try{
    const url=new URL(req.url,'http://localhost');
    if(files.has(url.pathname)){res.setHeader('Content-Type',url.pathname.endsWith('.css')?'text/css':'text/javascript');res.end(files.get(url.pathname));return;}
    if(url.pathname==='/system.css'){res.setHeader('Content-Type','text/css');res.end((await readFile(join(root,'resources/css/app.css'),'utf8')).replace(/^@import.*$/gm,''));return;}
    if(url.pathname.startsWith('/node_modules/')||url.pathname.startsWith('/resources/')||url.pathname.startsWith('/fonts/')){res.setHeader('Content-Type',url.pathname.endsWith('.css')?'text/css':'font/woff2');res.end(await readFile(join(root,url.pathname.startsWith('/fonts/')?'public'+url.pathname:url.pathname)));return;}
    const fixture=JSON.parse(execFileSync(php,[join(root,'tests/browser/g9-results-fixture.php'),db,url.pathname.replace('/g9-national-exams/results','')||'/'],{cwd:root,input:JSON.stringify(Object.fromEntries(url.searchParams)),encoding:'utf8',maxBuffer:15*1024*1024}));
    res.statusCode=fixture.status;res.setHeader('Content-Type',fixture.type);let content=Buffer.from(fixture.content,'base64');
    if(!url.pathname.includes('/reports/'))content=content.toString().replaceAll('http://localhost','');
    if(fixture.type==='text/html'&&!url.pathname.includes('/reports/'))content=`<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/node_modules/@tabler/core/dist/css/tabler.min.css"><link rel="stylesheet" href="/node_modules/@tabler/icons-webfont/dist/tabler-icons.min.css"><link rel="stylesheet" href="/system.css"><link rel="stylesheet" href="/resources/css/pages/g9-national-exams.css"><link rel="stylesheet" href="/resources/css/pages/g9-results.css"></head><body><div class="container-fluid p-3">${content}</div><script>window.errors=[];addEventListener('error',e=>errors.push(e.message));addEventListener('unhandledrejection',e=>errors.push(String(e.reason)));</script><script type="module" src="/forms.js"></script><script type="module" src="/results.js"></script></body></html>`;
    res.end(content);
}catch(error){res.statusCode=500;res.end(JSON.stringify({message:error.stack}));}});
await new Promise(r=>server.listen(0,'127.0.0.1',r));const origin=`http://127.0.0.1:${server.address().port}`;
const browser=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--no-sandbox','--disable-gpu','--no-first-run','--remote-debugging-port=0',`--user-data-dir=${join(temp,'chrome')}`,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(r=>setTimeout(r,ms));let socket;
try{
    let port;for(let i=0;i<100;i++){try{port=Number((await readFile(join(temp,'chrome/DevToolsActivePort'),'utf8')).split('\n')[0]);break;}catch{await delay(100);}}
    const pages=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();socket=new WebSocket(pages.find(page=>page.type==='page').webSocketDebuggerUrl);await new Promise(r=>socket.addEventListener('open',r,{once:true}));
    let id=0;const pending=new Map();socket.addEventListener('message',({data})=>{const m=JSON.parse(data),handler=pending.get(m.id);if(handler){pending.delete(m.id);m.error?handler[1](new Error(JSON.stringify(m.error))):handler[0](m.result);}});
    const command=(method,params={})=>new Promise((resolve,reject)=>{const n=++id;pending.set(n,[resolve,reject]);socket.send(JSON.stringify({id:n,method,params}));});
    const evaluate=async expression=>{const r=await command('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw new Error(r.exceptionDetails.exception?.description||JSON.stringify(r.exceptionDetails));return r.result.value;};
    const until=async expression=>{for(let i=0;i<120;i++){if(await evaluate(expression))return;await delay(100);}throw new Error('Timeout: '+expression+' '+await evaluate('JSON.stringify({errors:window.errors,error:document.querySelector("[data-results-error]")?.textContent,url:location.href,body:document.body?.innerText.slice(0,300)})'));};
    const click=selector=>evaluate(`document.querySelector(${JSON.stringify(selector)}).click()`);
    const choose=async(name,value)=>{await click('#g9Result-'+name+'-toggle');await until(`document.querySelector('#g9Result-${name}Options [data-index="${value}"]')!==null`);await click(`#g9Result-${name}Options [data-index="${value}"]`);await until('document.querySelector("[data-g9-results]").getAttribute("aria-busy")==="false"');};
    const idle=()=>until('document.querySelector("[data-g9-results]")?.getAttribute("aria-busy")==="false"');
    await command('Emulation.setDeviceMetricsOverride',{width:1500,height:1100,deviceScaleFactor:1,mobile:false});
    await command('Page.navigate',{url:origin+'/g9-national-exams/results'});await idle();
    assert.equal(await evaluate('Array.from(document.querySelectorAll("[data-results-export-toggle]")).every(b=>b.disabled)'),true);
    assert.equal(await evaluate('document.querySelector("#g9ResultFilters").hidden'),false);
    await choose('academic_year',1);
    assert.equal(await evaluate('document.querySelectorAll("[data-results-export]").length'),18);
    assert.equal(await evaluate('Array.from(document.querySelectorAll("[data-results-export-toggle]")).every(b=>!b.disabled)'),true);
    await click('[data-results-export-toggle]');
    assert.equal(await evaluate('document.querySelector(".g9-result-exports .dropdown-menu").classList.contains("show")'),true);
    await click('[data-results-export-toggle]');
    await evaluate(`window.exportDownloads=[];window.exportFetches=[];window.originalClick=HTMLAnchorElement.prototype.click;HTMLAnchorElement.prototype.click=function(){if(this.download){window.exportDownloads.push(this.download);}else window.originalClick.call(this);};window.originalFetch=window.fetch;window.fetch=(url,options)=>{if(String(url).includes('/reports/'))window.exportFetches.push(String(url));return window.originalFetch(url,options);};`);
    for(const format of ['excel','pdf']){
        await click(`[data-results-export="list"][data-format="${format}"]`);
        await until(`window.exportDownloads.includes('g9-result-list.${format==='pdf'?'pdf':'xlsx'}')`);
    }
    // Exercise the print popup and HTML response without opening a system dialog.
    await evaluate(`window.originalOpen=window.open;window.printHref=null;window.open=()=>({opener:null,document:{title:'',body:{textContent:''}},location:{replace:url=>window.printHref=url},close(){window.printClosed=true;}});`);
    await click('[data-results-export="list"][data-format="print"]');
    await until('window.printHref!==null');
    assert.equal(await evaluate(`(async()=>{const html=await(await fetch(window.printHref)).text();return html.includes('បញ្ជីលទ្ធផលប្រឡង')&&html.includes('document.fonts.ready')})()`),true);
    await evaluate('window.open=window.originalOpen');
    // The app frame clips horizontal overflow. Focusing an unpositioned wide
    // filter menu must not scroll this frame and move the whole form left.
    await evaluate(`(()=>{const root=document.querySelector('[data-g9-results]'),frame=document.createElement('div');frame.id='result-test-frame';frame.style.cssText='width:100%;overflow-x:hidden;overflow-y:auto;max-height:95vh';root.before(frame);frame.append(root);})()`);
    const filterGeometry=()=>evaluate(`(()=>{const root=document.querySelector('[data-g9-results]'),form=document.querySelector('#g9ResultFilters');return {rootLeft:root.getBoundingClientRect().left,formLeft:form.getBoundingClientRect().left,scroll:[document.scrollingElement.scrollLeft,...Array.from((function*(el){for(;el;el=el.parentElement)yield el;})(form)).map(el=>el.scrollLeft)]};})()`);
    for(const name of ['academic_year','exam_center','campus','room','grade','student','result']){
        for(const keyboard of [false,true]){
            const before=await filterGeometry();
            if(keyboard)await evaluate(`document.querySelector('#g9Result-${name}-toggle').dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowDown',bubbles:true,cancelable:true}))`);
            else await click('#g9Result-'+name+'-toggle');
            const after=await filterGeometry();assert.deepEqual(after,before,`${name} ${keyboard?'keyboard':'click'} moved the form`);
            const bounds=await evaluate(`(()=>{const r=document.querySelector('#g9Result-${name}Options').closest('.location-combobox-menu').getBoundingClientRect();return {left:r.left,right:r.right,viewport:document.documentElement.clientWidth};})()`);
            assert.ok(bounds.left>=0&&bounds.right<=bounds.viewport,`${name} menu exceeds the viewport`);
            await evaluate(`document.querySelector('#g9Result-${name}-toggle').dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true,cancelable:true}))`);
        }
    }
    assert.equal(await evaluate('document.querySelector("[data-result-metric=total]").textContent'),'32');
    assert.deepEqual(await evaluate(`['english','khmer'].map(language=>document.querySelector('[data-result-absent-language="'+language+'"]').textContent)`),['0','1']);
    assert.deepEqual(await evaluate(`['english','khmer'].map(language=>[document.querySelector('[data-result-candidate-language="'+language+'"]').textContent,document.querySelector('[data-result-candidate-language-percentage="'+language+'"]').textContent])`),[['16','(50%)'],['16','(50%)']]);
    assert.deepEqual(await evaluate(`['english','khmer'].map(language=>[document.querySelector('[data-result-passed-language="'+language+'"]').textContent,document.querySelector('[data-result-language-percentage="'+language+'"]').textContent])`),[['10','(62.5%)'],['10','(66.67%)']]);
    assert.equal(await evaluate('document.querySelector("[data-result-passed-percentage]").textContent'),'(64.52%)');
    assert.equal(await evaluate('document.querySelector("[data-result-failed-percentage]").textContent'),'(32.26%)');
    assert.deepEqual(await evaluate(`['english','khmer'].map(language=>[document.querySelector('[data-result-failed-language="'+language+'"]').textContent,document.querySelector('[data-result-failed-language-percentage="'+language+'"]').textContent])`),[['5','(31.25%)'],['5','(33.33%)']]);
    assert.equal(await evaluate('document.querySelectorAll("[data-results-rows] tr").length'),25);
    await click('#g9-results-pagination [data-page="2"]');await idle();assert.equal(await evaluate('document.querySelectorAll("[data-results-rows] tr").length'),7);
    await click('[data-results-tab=graph]');await idle();assert.equal(await evaluate('document.querySelectorAll("[data-results-bars] .g9-result-bar-track").length'),2);
    assert.equal(await evaluate('document.querySelectorAll("[data-results-legend]>div").length'),4);
    assert.ok(await evaluate('document.querySelector("[data-results-donut]").style.background.includes("conic-gradient")'));
    await mkdir(join(root,'storage/app/testing'),{recursive:true});
    for(const width of [1500,390]){
        await command('Emulation.setDeviceMetricsOverride',{width,height:1100,deviceScaleFactor:1,mobile:width<500});await delay(200);
        assert.equal(await evaluate('document.querySelector("#g9ResultFilters").hidden'),width<500);
        assert.equal(await evaluate('document.querySelector("[data-g9-results]").classList.contains("is-collapsed")'),width<500);
        for(const theme of ['light','dark']){
            await evaluate(`document.documentElement.dataset.bsTheme=${JSON.stringify(theme)}`);await delay(200);
            assert.equal(await evaluate('document.documentElement.scrollWidth<=document.documentElement.clientWidth'),true);
            await writeFile(join(root,`storage/app/testing/g9-results-graph-${width}-${theme}.png`),Buffer.from((await command('Page.captureScreenshot',{format:'png'})).data,'base64'));
        }
    }
    await click('[data-results-nav-toggle]');await click('[data-results-tab=statistics]');await idle();assert.equal(await evaluate('document.querySelectorAll("[data-results-statistics] tr").length'),2);
    assert.equal(await evaluate('document.querySelector("[data-results-stat-total]").textContent.includes("32")'),true);
    await click('[data-results-filter-toggle]');await choose('campus',1);assert.equal(await evaluate('document.querySelector("[data-result-metric=total]").textContent'),'16');
    await choose('result',2);assert.equal(await evaluate('document.querySelector("[data-result-metric=passed]").textContent'),'0');
    await click('[data-results-export="branch"][data-format="excel"]');await until(`window.exportDownloads.includes('g9-result-branch.xlsx')`);
    assert.equal(await evaluate(`(()=>{const url=new URL(window.exportFetches.at(-1),location.href);return url.searchParams.get('campus')==='1'&&url.searchParams.get('result')==='failed'&&!url.searchParams.has('view');})()`),true);
    await click('[data-results-tab=results]');await idle();
    await click('[data-results-export-toggle]');
    assert.equal(await evaluate(`(()=>{const r=document.querySelector('.g9-result-exports .dropdown-menu.show').getBoundingClientRect();return r.left>=0&&r.right<=innerWidth;})()`),true);
    await writeFile(join(root,'storage/app/testing/g9-result-reports/mobile-export-menu.png'),Buffer.from((await command('Page.captureScreenshot',{format:'png'})).data,'base64'));
    await click('[data-results-export-toggle]');
    await choose('academic_year',0);assert.equal(await evaluate('document.querySelector("[data-results-summary]").hidden'),true);assert.equal(await evaluate('document.querySelectorAll("[data-results-stat-total] tr").length'),0);
    assert.equal(await evaluate('Array.from(document.querySelectorAll("[data-results-export-toggle]")).every(b=>b.disabled)'),true);
    assert.deepEqual(await evaluate('window.errors'),[]);
    await command('Page.enable');
    await command('Page.addScriptToEvaluateOnNewDocument',{source:'window.errors=[];addEventListener("error",e=>window.errors.push(e.message));addEventListener("unhandledrejection",e=>window.errors.push(String(e.reason)));window.print=()=>window.printRequested=true;'});
    await command('Emulation.setDeviceMetricsOverride',{width:1100,height:1400,deviceScaleFactor:1,mobile:false});
    for(const type of ['list','room','subject']){
        await command('Page.navigate',{url:origin+`/g9-national-exams/results/reports/${type}/print?academic_year=1`});
        await until('window.printRequested===true');
        const sheets=await evaluate('document.querySelectorAll(".result-sheet").length');
        const pdf=Buffer.from((await command('Page.printToPDF',{preferCSSPageSize:true,printBackground:true,displayHeaderFooter:false})).data,'base64');
        assert.equal((pdf.toString('latin1').match(/\/Type\s*\/Page\b/g)||[]).length,sheets,`${type} browser print added an overflow page`);
        await writeFile(join(root,`storage/app/testing/g9-result-reports/browser-${type}.pdf`),pdf);
    }
    console.log('G9 results: Print/Excel/PDF menus, downloads, print content, filtered exports, searchable filters, pagination, all three tabs, graphs, statistics, blank reset, desktop/mobile and light/dark layouts passed.');
}finally{socket?.close();browser.kill();await new Promise(r=>server.close(r));}
