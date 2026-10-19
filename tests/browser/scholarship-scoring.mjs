// Uses synthetic in-memory scholarship data and a local headless browser.
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { spawn,execFileSync } from 'node:child_process';
import { readFile,mkdtemp,writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join,resolve } from 'node:path';

const root=resolve(import.meta.dirname,'../..');
const php=process.env.CHAT_TEST_PHP||'C:/laragon/bin/php/php-8.4.25-Win32-vs17-x64/php.exe';
const executable=process.env.CHAT_TEST_BROWSER||'C:/Program Files/Google/Chrome/Application/chrome.exe';
const requests=[],reviewPosts=[];
const server=createServer(async(req,res)=>{
    const url=new URL(req.url,'http://localhost');
    if(req.method==='POST'){
        let body='';for await(const chunk of req)body+=chunk;
        reviewPosts.push(Object.fromEntries(new URLSearchParams(body)));
        res.writeHead(303,{Location:'/'+url.search});res.end();return;
    }
    if(url.pathname.startsWith('/resources/')||url.pathname.startsWith('/node_modules/')){
        res.setHeader('Content-Type',url.pathname.endsWith('.js')?'text/javascript':url.pathname.endsWith('.css')?'text/css':'font/woff2');
        res.end(await readFile(join(root,url.pathname)));return;
    }
    if(url.pathname.startsWith('/storage/')){
        res.setHeader('Content-Type','image/svg+xml');res.end('<svg xmlns="http://www.w3.org/2000/svg" width="600" height="800"><rect width="600" height="800" fill="#dbeafe"/></svg>');return;
    }
    requests.push(Object.fromEntries(url.searchParams));
    const html=execFileSync(php,[join(root,'tests/browser/scholarship-fixture.php'),'scoring',url.searchParams.has('scoring_many')?'pagination':'',url.searchParams.toString()],{cwd:root,encoding:'utf8'})
        .replaceAll('http://localhost/students/western-scholarship','/students/western-scholarship')
        .replace(/https?:\/\/[^"\s]*\/storage\/testing\/scholarship-photo\.svg/g,'/storage/testing/scholarship-photo.svg');
    res.setHeader('Content-Type','text/html; charset=utf-8');
    res.end(`<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/node_modules/@tabler/core/dist/css/tabler.min.css"><link rel="stylesheet" href="/node_modules/@tabler/icons-webfont/dist/tabler-icons.min.css"><link rel="stylesheet" href="/resources/css/pages/western-scholarship.css"><style>body{padding:20px}</style>${html}<script type="importmap">{"imports":{"bootstrap":"/node_modules/bootstrap/dist/js/bootstrap.esm.js","@popperjs/core":"/node_modules/@popperjs/core/lib/index.js","sweetalert2":"/node_modules/sweetalert2/dist/sweetalert2.esm.all.js"}}</script><script type="module" src="/resources/js/westernScholarship.js"></script>`);
});
await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
const profile=await mkdtemp(join(tmpdir(),'wis-scoring-'));
const browser=spawn(executable,['--headless=new','--disable-gpu','--no-sandbox','--no-first-run','--remote-debugging-port=0',`--user-data-dir=${profile}`,'about:blank'],{windowsHide:true,stdio:'ignore'});
let socket;
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
try{
    let port;
    for(let attempt=0;attempt<100;attempt++){
        try{port=Number((await readFile(join(profile,'DevToolsActivePort'),'utf8')).split('\n')[0]);break;}catch{await delay(100);}
    }
    assert.ok(port);
    const pages=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket=new WebSocket(pages.find(page=>page.type==='page').webSocketDebuggerUrl);
    await new Promise((resolve,reject)=>{socket.addEventListener('open',resolve,{once:true});socket.addEventListener('error',reject,{once:true});});
    let id=0;const pending=new Map(),errors=[];
    socket.addEventListener('message',({data})=>{
        const message=JSON.parse(data);
        if(message.method==='Runtime.exceptionThrown')errors.push(message.params.exceptionDetails.exception?.description||message.params.exceptionDetails.text);
        if(!pending.has(message.id))return;
        const [resolve,reject]=pending.get(message.id);pending.delete(message.id);
        message.error?reject(new Error(JSON.stringify(message.error))):resolve(message.result);
    });
    const command=(method,params={})=>new Promise((resolve,reject)=>{
        const requestId=++id,timeout=setTimeout(()=>{pending.delete(requestId);reject(new Error('CDP timeout: '+method));},10000);
        pending.set(requestId,[value=>{clearTimeout(timeout);resolve(value);},error=>{clearTimeout(timeout);reject(error);}]);
        socket.send(JSON.stringify({id:requestId,method,params}));
    });
    const evaluate=async expression=>{
        const result=await command('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});
        if(result.exceptionDetails)throw new Error(result.exceptionDetails.exception?.description||JSON.stringify(result.exceptionDetails));
        return result.result.value;
    };
    const until=async expression=>{
        for(let attempt=0;attempt<100;attempt++){if(await evaluate(expression))return;await delay(50);}
        assert.fail('Timed out: '+expression);
    };
    const click=selector=>evaluate(`document.querySelector(${JSON.stringify(selector)}).click()`);
    const ready=()=>until('document.querySelector("[data-scoring-filters-ready]")!==null');
    const origin='http://127.0.0.1:'+server.address().port;
    await command('Page.enable');await command('Runtime.enable');
    await command('Emulation.setFocusEmulationEnabled',{enabled:true});
    for(const mobile of [false,true]){
        await command('Emulation.setDeviceMetricsOverride',{width:mobile?390:1440,height:mobile?844:980,deviceScaleFactor:1,mobile});
        await command('Page.navigate',{url:origin});await ready();
        assert.equal(await evaluate('document.querySelector("#scholarshipScoringFilters").hidden'),mobile);
        assert.equal(await evaluate('document.querySelector("[data-exam-selector]")===null'),true);
        assert.equal(await evaluate('document.querySelector("#scoreFilterRoomToggle").disabled'),true);
        assert.equal(await evaluate('document.querySelector("#scoreFilterStudentToggle").disabled'),true);
        assert.equal(await evaluate('document.querySelectorAll(".scholarship-scoring-table").length'),0);
        if(mobile)await click('[data-scoring-filters-toggle]');
        await click('#scoreFilterCenterToggle');
        await evaluate('document.querySelector("#scoreFilterCenter").closest(".scholarship-field").querySelector("input[type=search]").value="Main";document.querySelector("#scoreFilterCenter").closest(".scholarship-field").querySelector("input[type=search]").dispatchEvent(new Event("input"))');
        await click('#scoreFilterCenterOptions [data-seat-option="0"]');
        await until('location.search.includes("score_center=0")');await ready();
        assert.equal(await evaluate('document.querySelector("#scoreFilterRoomToggle").disabled'),false);
        assert.equal(await evaluate('document.querySelectorAll(".scholarship-scoring-table").length'),0);
        if(mobile)await click('[data-scoring-filters-toggle]');
        await click('#scoreFilterRoomToggle');await click('#scoreFilterRoomOptions [data-seat-option="1"]');
        await until('location.search.includes("score_room=1")');await ready();
        assert.equal(await evaluate('document.querySelector("#scoreFilterStudentToggle").disabled'),false);
        if(mobile)await click('[data-scoring-filters-toggle]');
        await click('#scoreFilterStudentToggle');
        assert.ok(await evaluate('document.querySelector("#scoreFilterStudentOptions").textContent.includes("1 - CHEAN CHAMNUNY")'));
        await click('#scoreFilterStudentOptions [data-seat-option="1"]');
        await until('location.search.includes("score_student=1")');await ready();
        assert.equal(await evaluate('document.querySelectorAll("[data-review-scores]").length'),1);
        assert.equal(await evaluate('document.querySelector("[data-review-scores]").disabled'),false);
        assert.equal(await evaluate('document.querySelectorAll("[data-score-review-form] input[type=checkbox]").length'),0);
        assert.equal(await evaluate('[...document.querySelectorAll("[data-score-input]")].every(input=>input.readOnly)'),true);
        assert.equal(await evaluate('document.querySelector("[data-scoring-filters-card]").contains(document.querySelector("[data-scoring-subject]"))'),true);
        if(mobile)await click('[data-scoring-filters-toggle]');
        await click('#scholarshipScoringSubjectToggle');await click('#scholarshipScoringSubjectOptions [data-seat-option="1"]');
        await until('document.activeElement?.id==="score1_1" && !document.querySelector("#score1_1").readOnly');
        assert.equal(await evaluate('document.querySelector("#score1_2").readOnly'),true);
        const formValues=await evaluate(`(()=>{const input=document.querySelector('[name*="[scores]["]');input.value='88.5';const form=input.form;return {values:Object.fromEntries(new FormData(form)),count:form.querySelectorAll('input[name*="[scores]["]').length,associated:[...document.querySelectorAll('[name*="[scores]["]')].every(i=>i.form===form)};})()`);
        assert.equal(await evaluate('document.querySelectorAll("[data-save-score-drafts]").length'),1);assert.equal(formValues.associated,true);assert.equal(formValues.count,0);
        assert.equal(Object.keys(formValues.values).filter(key=>key.includes('[scores][')).length,4);
        assert.ok(Object.values(formValues.values).includes('88.5'));
        await evaluate('document.querySelector(".scholarship-score-input-cell input").dispatchEvent(new Event("input",{bubbles:true}))');
        assert.equal(await evaluate('document.querySelector("[data-review-scores]").disabled'),true);
        assert.equal(await evaluate('document.querySelector("[data-unsaved-score-review]").hidden'),false);
        await evaluate('const score=document.querySelector(".scholarship-score-input-cell input");score.value=score.defaultValue;score.dispatchEvent(new Event("input",{bubbles:true}))');
        assert.equal(await evaluate('document.querySelector("[data-review-scores]").disabled'),false);
        assert.equal(await evaluate(`(()=>{const absent=document.querySelector('.scholarship-scoring-attendance input[name$="[absent]"]');absent.checked=true;return new FormData(absent.form).get(absent.name);})()`),'1');
        await evaluate('document.querySelector(".scholarship-scoring-attendance input").dispatchEvent(new Event("change",{bubbles:true}))');
        assert.equal(await evaluate('document.querySelector("[data-review-scores]").disabled'),true);
        await evaluate('const absent=document.querySelector(".scholarship-scoring-attendance input");absent.checked=absent.defaultChecked;absent.dispatchEvent(new Event("change",{bubbles:true}))');
        const previousReviews=reviewPosts.length;
        await click('[data-review-scores]');
        for(let attempt=0;attempt<100&&reviewPosts.length===previousReviews;attempt++)await delay(50);
        assert.equal(reviewPosts.length,previousReviews+1);
        await until('document.querySelector("[data-scoring-filters-ready]") && document.querySelector("#score1_1")?.readOnly && !document.querySelector("[data-review-scores]").disabled');
        assert.equal(reviewPosts.at(-1).confirm,undefined);
        assert.equal(await evaluate('document.querySelector(".swal2-popup")===null'),true);
        assert.deepEqual(await evaluate('[...document.querySelectorAll(".scholarship-scoring-table thead tr:first-child th")].slice(-3).map(th=>th.textContent.trim())'),['Total Score','Attendance','Action']);
        assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true);
        assert.equal(await evaluate('document.querySelectorAll(".scholarship-scoring-table thead tr").length'),2);
        await evaluate('window.scrollTo(0,0)');
        const shot=await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
        await writeFile(join(root,'storage/app/testing/scoring-'+(mobile?'mobile':'desktop')+'.png'),Buffer.from(shot.data,'base64'));
        if(mobile)await click('[data-scoring-filters-toggle]');
        await click('#scoreFilterCenterToggle');await click('#scoreFilterCenterOptions [data-seat-option=""]');
        await until('new URL(location.href).searchParams.get("score_center")===""');await ready();
        assert.equal(await evaluate('document.querySelector("#scoreFilterStudentToggle").disabled'),true);
        assert.equal(await evaluate('new URL(location.href).searchParams.has("score_student")'),false);
        assert.equal(await evaluate('new URL(location.href).searchParams.has("score_room")'),false);
        assert.equal(await evaluate('document.querySelectorAll(".scholarship-scoring-table").length'),0);
    }
    await command('Emulation.setDeviceMetricsOverride',{width:1440,height:980,deviceScaleFactor:1,mobile:false});
    await command('Page.navigate',{url:origin+'/?scoring_many=1&score_center=0&score_room=1&score_page=2'});await ready();
    assert.equal(await evaluate('document.querySelectorAll("[data-scoring-candidate]").length'),25);
    assert.equal(await evaluate('document.querySelector(".pagination")===null'),true);
    const beforeScroll=await evaluate('document.querySelector(".scholarship-scoring-table tbody tr").getBoundingClientRect().top');
    await evaluate('document.querySelector(".scholarship-scoring-overflow").scrollTop=250');
    const sticky=await evaluate(`(()=>{const box=document.querySelector('.scholarship-scoring-overflow'),head=box.querySelector('thead');return {top:head.getBoundingClientRect().top-box.getBoundingClientRect().top,rowTop:box.querySelector('tbody tr').getBoundingClientRect().top,scroll:box.scrollTop,rows:head.querySelectorAll('tr').length};})()`);
    assert.ok(Math.abs(sticky.top)<2);assert.equal(sticky.rows,2);assert.equal(sticky.scroll,250);assert.ok(beforeScroll-sticky.rowTop>240);
    await evaluate('document.querySelector(".scholarship-scoring-overflow").scrollTop=0');
    await click('#scholarshipScoringSubjectToggle');await click('#scholarshipScoringSubjectOptions [data-seat-option="1"]');
    await until('document.activeElement?.id==="score1_1"');
    const tab=async(shift=false)=>{
        await command('Input.dispatchKeyEvent',{type:'keyDown',key:'Tab',code:'Tab',windowsVirtualKeyCode:9,modifiers:shift?8:0});
        await command('Input.dispatchKeyEvent',{type:'keyUp',key:'Tab',code:'Tab',windowsVirtualKeyCode:9,modifiers:shift?8:0});
    };
    await tab();assert.equal(await evaluate('document.activeElement.id'),'score2_1');
    await tab(true);assert.equal(await evaluate('document.activeElement.id'),'score1_1');
    await evaluate('document.querySelectorAll("[data-score-input=\\"1\\"]")[24].focus()');
    await tab();assert.equal(await evaluate('document.activeElement.hasAttribute("data-save-score-drafts")'),true);
    await click('#scholarshipScoringSubjectToggle');await click('#scholarshipScoringSubjectOptions [data-seat-option="2"]');
    await until('document.activeElement?.id==="score1_2"');
    await tab();assert.equal(await evaluate('document.activeElement.id'),'score2_2');
    assert.equal(await evaluate('[...document.querySelectorAll("[data-score-input=\\"1\\"]")].every(input=>input.readOnly)'),true);
    await click('[data-lock-score-inputs]');
    assert.equal(await evaluate('[...document.querySelectorAll("[data-score-input]")].every(input=>input.readOnly)'),true);
    assert.equal(await evaluate('[...new FormData(document.querySelector("#scholarshipScoreDrafts")).keys()].filter(key=>key.includes("[scores][")).length'),100);
    const packed=await evaluate(`(()=>{const data=new FormData();data.set('_token','testing');for(let id=1;id<=200;id++){data.set('rows['+id+'][version]','1');for(let subject=1;subject<=4;subject++)data.set('rows['+id+'][scores]['+subject+']','80');}document.querySelector('#scholarshipScoreDrafts').dispatchEvent(new FormDataEvent('formdata',{formData:data}));return {fields:[...data.keys()].length,rows:Object.keys(JSON.parse(data.get('score_drafts'))).length};})()`);
    assert.deepEqual(packed,{fields:2,rows:200});
    assert.deepEqual(errors,[]);
    assert.ok(requests.some(request=>request.score_center==='0'&&request.score_room==='1'&&request.score_student==='1'));
    console.log('Scoring desktop/mobile filters, collapse defaults, parent reset, row form data and responsive table passed.');
}finally{socket?.close();browser.kill();server.closeAllConnections();await new Promise(resolve=>server.close(resolve));}
