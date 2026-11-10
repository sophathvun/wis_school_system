import assert from 'node:assert/strict';
import {createServer} from 'node:http';
import {spawn} from 'node:child_process';
import {readFile,writeFile,mkdtemp} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join,resolve} from 'node:path';
const root=resolve(import.meta.dirname,'../..'),qa=join(root,'storage/app/testing/help-center');
const temp=await mkdtemp(join(tmpdir(),'school-help-'));
const server=createServer(async(req,res)=>{try{
    const url=new URL(req.url,'http://localhost');
    if(url.pathname.startsWith('/node_modules/')||url.pathname.startsWith('/resources/')||url.pathname.startsWith('/public/fonts/')){
        res.setHeader('Content-Type',url.pathname.endsWith('.css')?'text/css':url.pathname.endsWith('.js')?'text/javascript':url.pathname.endsWith('.ttf')?'font/ttf':'font/woff2');
        res.end(await readFile(join(root,url.pathname)));return;
    }
    const slug=url.pathname==='/help'?'index':url.pathname.split('/').pop();
    let html=await readFile(join(qa,slug+'.html'),'utf8');
    html=html.replace(/https?:\/\/[^/"\s]+\/(help[^"\s]*)/g,'/$1');
    html=html.replace('</head>','<link rel="stylesheet" href="/node_modules/@tabler/core/dist/css/tabler.min.css"><link rel="stylesheet" href="/node_modules/@tabler/icons-webfont/dist/tabler-icons.min.css"><link rel="stylesheet" href="/resources/css/pages/help-center.css"></head>');
    html=html.replace('</body>','<script>window.errors=[];addEventListener("error",e=>errors.push(e.message));</script><script type="module" src="/resources/js/helpCenter.js"></script></body>');
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
    const until=async expression=>{for(let i=0;i<100;i++){if(await evaluate(expression))return;await delay(100);}throw new Error('Timeout '+expression);};
    await command('Page.enable');
    for(const width of [1440,390]){
        await command('Emulation.setDeviceMetricsOverride',{width,height:1100,deviceScaleFactor:1,mobile:width<500});
        for(const page of ['index','g9-settings','g12-certificates','attendance-daily','admin-role-permissions','students-enrollment','students-workflow']){
            await command('Page.navigate',{url:origin+(page==='index'?'/help':'/help/'+page)});
            await until(`document.readyState==='complete'&&document.querySelector('.help-center')!==null`);
            await evaluate('document.fonts.ready');
            if(page==='index')assert.equal(await evaluate('document.querySelectorAll(".help-article-card").length'),60);
            else{
                assert.ok(await evaluate('document.querySelectorAll("[data-help-toc-links] a").length>=5'));
                assert.equal(await evaluate('document.querySelector("[data-help-print]").hidden'),false);
                assert.equal(await evaluate(`(async()=>{await document.fonts.load('16px "Help Khmer Siemreap"','កាលបរិច្ឆេទ');return document.fonts.check('16px "Help Khmer Siemreap"','កាលបរិច្ឆេទ')})()`),true);
            }
            for(const theme of ['light','dark']){
                await evaluate(`document.documentElement.dataset.bsTheme='${theme}'`);
                assert.equal(await evaluate('document.documentElement.scrollWidth<=document.documentElement.clientWidth'),true,`${page} overflow at ${width}`);
                await writeFile(join(qa,`${page}-${width}-${theme}.png`),Buffer.from((await command('Page.captureScreenshot',{format:'png'})).data,'base64'));
            }
            assert.deepEqual(await evaluate('window.errors'),[]);
        }
    }
    console.log('Help Center: 60 guides, G9/G12/Attendance/Administrator/Students articles, table of contents, print control, internal Khmer font, desktop/mobile, and light/dark layouts passed.');
}finally{socket?.close();browser.kill();await new Promise(r=>server.close(r));}
