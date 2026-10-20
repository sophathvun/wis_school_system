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
const requests=[],reviewPosts=[],awardPosts=[],scoreReportRequests=[],resultReportRequests=[],resultSettingPosts=[];
const server=createServer(async(req,res)=>{
    const url=new URL(req.url,'http://localhost');
    if(url.pathname.endsWith('/results/print-settings')){
        res.setHeader('Content-Type','application/json');
        if(req.method==='POST'){
            let body='';for await(const chunk of req)body+=chunk;resultSettingPosts.push(body);
            res.end(JSON.stringify({message:'Result print settings saved.'}));return;
        }
        res.end(execFileSync(php,[join(root,'tests/browser/scholarship-result-report-fixture.php'),'settings','8',url.searchParams.toString()],{cwd:root,encoding:'utf8'}));return;
    }
    if(req.method==='POST'){
        let body='';for await(const chunk of req)body+=chunk;
        if(url.pathname.includes('/awards/')){
            const fields={};
            const boundary=/boundary=(.+)$/.exec(req.headers['content-type']||'')?.[1];
            if(boundary)for(const part of body.split('--'+boundary)){
                const name=/name="([^"]+)"/.exec(part)?.[1],start=part.indexOf('\r\n\r\n');
                if(name&&start>=0)fields[name]=part.slice(start+4).replace(/\r\n$/,'');
            }
            else Object.assign(fields,Object.fromEntries(new URLSearchParams(body)));
            awardPosts.push(fields);
            res.writeHead(awardPosts.length===1?422:200,{'Content-Type':'application/json'});
            res.end(JSON.stringify(awardPosts.length===1?{message:'An award was changed. Please check the grade winners.'}:{message:'Scholarship winners saved for Grade 1.',version:Number(fields.version)+1}));return;
        }
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
    if(url.pathname==='/score-list-preview'){
        res.setHeader('Content-Type','text/html; charset=utf-8');
        res.end(await readFile(join(root,'storage/app/testing/score-reports/score-list.html')));return;
    }
    if(url.pathname==='/result-list-preview'){
        res.setHeader('Content-Type','text/html; charset=utf-8');res.end(await readFile(join(root,'storage/app/testing/result-reports/result-list.html')));return;
    }
    if(/\/result-reports\/(xlsx|pdf)$/.test(url.pathname)){
        resultReportRequests.push({format:url.pathname.split('/').at(-1),params:Object.fromEntries(url.searchParams),grades:url.searchParams.getAll('report_grades[]')});
        res.writeHead(204);res.end();return;
    }
    if(/\/score-list\/(xlsx|pdf)$/.test(url.pathname)){
        scoreReportRequests.push({format:url.pathname.split('/').at(-1),params:Object.fromEntries(url.searchParams),rooms:url.searchParams.getAll('report_rooms[]')});
        res.writeHead(204);res.end();return;
    }
    requests.push(Object.fromEntries(url.searchParams));
    const resultSuccess=url.searchParams.get('result_success');
    const isResult=Boolean(resultSuccess)||url.searchParams.get('tab')==='result';
    const html=execFileSync(php,[join(root,'tests/browser/scholarship-fixture.php'),isResult?'result':'scoring',isResult?(resultSuccess==='published'?'':'draft'):url.searchParams.has('scoring_many')?'pagination':'',url.searchParams.toString()],{cwd:root,encoding:'utf8'})
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
        for(let attempt=0;attempt<300;attempt++){if(await evaluate(expression))return;await delay(50);}
        console.error(await evaluate('JSON.stringify({url:location.href,ready:!!document.querySelector("[data-scoring-filters-ready]"),reviewDisabled:document.querySelector("[data-review-scores]")?.disabled,scores:[...document.querySelectorAll("[data-score-input]")].slice(0,4).map(input=>({value:input.value,defaultValue:input.defaultValue,readOnly:input.readOnly})),dialog:document.querySelector(".swal2-title")?.textContent})'));
        assert.fail('Timed out: '+expression);
    };
    const click=selector=>evaluate(`document.querySelector(${JSON.stringify(selector)}).click()`);
    const ready=()=>until('document.querySelector("[data-scoring-filters-ready]")!==null');
    const origin='http://127.0.0.1:'+server.address().port;
    await command('Page.enable');await command('Runtime.enable');
    await command('Emulation.setFocusEmulationEnabled',{enabled:true});
    if(!process.env.CHAT_TEST_RESULTS_ONLY){
    for(const mobile of [false,true]){
        await command('Emulation.setDeviceMetricsOverride',{width:mobile?390:1440,height:mobile?844:980,deviceScaleFactor:1,mobile});
        await command('Page.navigate',{url:origin});await ready();
        assert.equal(await evaluate('document.querySelector("#scholarshipScoringFilters").hidden'),mobile);
        assert.equal(await evaluate('document.querySelector("[data-exam-selector]")===null'),true);
        assert.equal(await evaluate('document.querySelector("#scoreFilterRoomToggle").disabled'),true);
        assert.equal(await evaluate('document.querySelector("#scoreFilterStudentToggle").disabled'),true);
        assert.equal(await evaluate('document.querySelectorAll(".scholarship-scoring-table").length'),0);
        assert.equal(await evaluate('document.querySelectorAll("[data-score-report-link].disabled").length'),0);
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
        assert.deepEqual(await evaluate('[...document.querySelectorAll("[data-score-report-link]")].map(link=>link.textContent.trim())'),['Print','Excel','PDF']);
        assert.equal(await evaluate('[...document.querySelectorAll("[data-score-report-link]")].every(link=>!link.classList.contains("disabled")&&link.href.includes("score_center=0")&&link.href.includes("score_room=1")&&(link.compareDocumentPosition(document.querySelector("[data-scoring-fullscreen]"))&Node.DOCUMENT_POSITION_FOLLOWING))'),true);
        assert.equal(await evaluate('document.querySelector("#scoreFilterStudentToggle").disabled'),false);
        if(mobile)await click('[data-scoring-filters-toggle]');
        await click('#scoreFilterStudentToggle');
        assert.ok(await evaluate('document.querySelector("#scoreFilterStudentOptions").textContent.includes("1 - CHEAN CHAMNUNY")'));
        await click('#scoreFilterStudentOptions [data-seat-option="1"]');
        await until('location.search.includes("score_student=1")');await ready();
        const fullscreenValues=await evaluate('[...document.querySelectorAll("[data-score-input]")].map(input=>input.value)');
        if(!mobile)assert.equal(await evaluate('(()=>{const box=document.querySelector(".scholarship-scoring-overflow");return box.scrollWidth<=box.clientWidth+1;})()'),true,'Desktop columns fit the available table width');
        await click('[data-scoring-fullscreen]');
        await until('document.querySelector("[data-scoring-fullscreen]").getAttribute("aria-pressed")==="true"');
        assert.equal(await evaluate('getComputedStyle(document.querySelector(".scholarship-navigation")).display'),'none');
        assert.equal(await evaluate('(()=>{const r=document.querySelector("[data-scholarship-page]").getBoundingClientRect();return Math.abs(r.width-innerWidth)<2&&Math.abs(r.height-innerHeight)<2;})()'),true);
        assert.equal(await evaluate('document.querySelector("[data-scoring-fullscreen]").textContent.trim()'),'Exit Full Screen');
        assert.equal(await evaluate('(()=>{const footer=document.querySelector("[data-scoring-footer]").getBoundingClientRect(),table=document.querySelector(".scholarship-scoring-overflow").getBoundingClientRect();return footer.bottom<=innerHeight&&table.bottom<=footer.top&&table.height>100;})()'),true,'Fullscreen reserves visible space for the save and review controls');
        if(!mobile){
            const expandedHeight=await evaluate('document.querySelector(".scholarship-scoring-overflow").getBoundingClientRect().height');
            await click('[data-scoring-filters-toggle]');
            assert.ok(await evaluate('document.querySelector(".scholarship-scoring-overflow").getBoundingClientRect().height')>expandedHeight,'Collapsing fullscreen filters gives the table more room');
            await click('[data-scoring-filters-toggle]');
        }
        const fullscreenShot=await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
        await writeFile(join(root,'storage/app/testing/scoring-fullscreen-'+(mobile?'mobile':'desktop')+'.png'),Buffer.from(fullscreenShot.data,'base64'));
        await click('[data-scoring-fullscreen]');
        await until('document.querySelector("[data-scoring-fullscreen]").getAttribute("aria-pressed")==="false"');
        assert.deepEqual(await evaluate('[...document.querySelectorAll("[data-score-input]")].map(input=>input.value)'),fullscreenValues);
        await evaluate('Object.defineProperty(document.querySelector("[data-scholarship-page]"),"requestFullscreen",{value:undefined,configurable:true})');
        await click('[data-scoring-fullscreen]');
        await until('document.querySelector("[data-scoring-fullscreen]").getAttribute("aria-pressed")==="true"');
        await evaluate('document.dispatchEvent(new KeyboardEvent("keydown",{key:"Escape",bubbles:true}));delete document.querySelector("[data-scholarship-page]").requestFullscreen');
        await until('document.querySelector("[data-scoring-fullscreen]").getAttribute("aria-pressed")==="false"');
        assert.equal(await evaluate('document.body.classList.contains("scholarship-scoring-fullscreen-open")'),false);
        assert.equal(await evaluate('document.querySelectorAll("[data-review-scores]").length'),1);
        assert.equal(await evaluate('document.querySelector("[data-review-scores]").disabled'),false);
        assert.equal(await evaluate('document.querySelectorAll("[data-score-review-form] input[type=checkbox]").length'),0);
        assert.equal(await evaluate('[...document.querySelectorAll("[data-score-input]")].every(input=>input.readOnly)'),true);
        assert.equal(await evaluate('document.querySelector("[data-scoring-filters-card]").contains(document.querySelector("[data-scoring-subject]"))'),true);
        if(mobile)await click('[data-scoring-filters-toggle]');
        await click('#scholarshipScoringSubjectToggle');await click('#scholarshipScoringSubjectOptions [data-seat-option="1"]');
        await until('document.activeElement?.id==="score1_1" && !document.querySelector("#score1_1").readOnly');
        assert.equal(await evaluate('document.querySelector("#score1_2").readOnly'),true);
        await evaluate('(()=>{const input=document.querySelector("#score1_1");input.value="101";input.dispatchEvent(new Event("input",{bubbles:true}));input.dispatchEvent(new KeyboardEvent("keydown",{key:"Tab",bubbles:true,cancelable:true}));})()');
        await until('document.querySelector(".swal2-popup")!==null');
        assert.equal(await evaluate('document.querySelector("#score1_1").classList.contains("is-invalid")'),true);
        assert.ok(await evaluate('document.querySelector("#score1_1Error").textContent.includes("maximum of 100")'));
        assert.ok(await evaluate('document.querySelector(".swal2-popup").textContent.includes("maximum of 100")'));
        await click('.swal2-confirm');await until('document.activeElement?.id==="score1_1" && document.querySelector(".swal2-popup")===null');
        await evaluate('(()=>{const input=document.querySelector("#score1_1");input.value="100";input.dispatchEvent(new Event("input",{bubbles:true}));})()');
        assert.equal(await evaluate('document.querySelector("#score1_1").getAttribute("aria-invalid")'),'false');
        assert.equal(await evaluate('document.querySelector("#score1_1Error").hidden'),true);
        await evaluate('document.querySelector("#score1_1").value="90";document.querySelector("#score1_1").dispatchEvent(new Event("input",{bubbles:true}))');
        const formValues=await evaluate(`(()=>{const input=document.querySelector('[name*="[scores]["]');input.value='88.5';const form=input.form;return {values:Object.fromEntries(new FormData(form)),count:form.querySelectorAll('input[name*="[scores]["]').length,associated:[...document.querySelectorAll('[name*="[scores]["]')].every(i=>i.form===form)};})()`);
        assert.equal(await evaluate('document.querySelectorAll("[data-save-score-drafts]").length'),1);assert.equal(formValues.associated,true);assert.equal(formValues.count,0);
        assert.equal(Object.keys(formValues.values).filter(key=>key.includes('[scores][')).length,4);
        assert.ok(Object.values(formValues.values).includes('88.5'));
        await evaluate('document.querySelector(".scholarship-score-input-cell input").dispatchEvent(new Event("input",{bubbles:true}))');
        assert.equal(await evaluate('document.querySelector("[data-review-scores]").disabled'),true);
        assert.equal(await evaluate('document.querySelector("[data-unsaved-score-review]").hidden'),false);
        await click('.scholarship-navigation a');
        await until('document.querySelector(".swal2-title")?.textContent==="Unsaved Scores"');
        await click('.swal2-cancel');await until('document.querySelector(".swal2-popup")===null');
        assert.equal(await evaluate('document.querySelector("#score1_1").value'),'88.5');
        await click('[data-score-report-link]');
        await until('document.querySelector(".swal2-title")?.textContent==="Unsaved Scores"');
        await click('.swal2-cancel');await until('document.querySelector(".swal2-popup")===null');
        await evaluate('document.querySelector("#scoreFilterCenter").value="";document.querySelector("#scoreFilterCenter").dispatchEvent(new Event("change",{bubbles:true}))');
        await until('document.querySelector(".swal2-title")?.textContent==="Unsaved Scores"');
        assert.equal(await evaluate('document.querySelector("#scoreFilterCenter").value'),'0');
        assert.equal(await evaluate('document.querySelector("#scoreFilterRoom").value'),'1');
        assert.equal(await evaluate('document.querySelector("#scoreFilterStudent").value'),'1');
        await click('.swal2-cancel');await until('document.querySelector(".swal2-popup")===null');
        assert.equal(await evaluate('(()=>{const event=new Event("beforeunload",{cancelable:true});window.dispatchEvent(event);return event.defaultPrevented;})()'),true);
        await evaluate('const score=document.querySelector(".scholarship-score-input-cell input");score.value=score.defaultValue;score.dispatchEvent(new Event("input",{bubbles:true}))');
        assert.equal(await evaluate('document.querySelector("[data-review-scores]").disabled'),false);
        assert.equal(await evaluate(`(()=>{const absent=document.querySelector('.scholarship-scoring-attendance input[name$="[absent]"]');absent.checked=true;return new FormData(absent.form).get(absent.name);})()`),'1');
        await evaluate('document.querySelector(".scholarship-scoring-attendance input").dispatchEvent(new Event("change",{bubbles:true}))');
        assert.equal(await evaluate('document.querySelector("[data-review-scores]").disabled'),true);
        await evaluate('const absent=document.querySelector(".scholarship-scoring-attendance input");absent.checked=absent.defaultChecked;absent.dispatchEvent(new Event("change",{bubbles:true}))');
        // Returning to Present requires the user to re-enter/confirm the scores.
        assert.equal(await evaluate('[...document.querySelectorAll("[data-score-input]")].every(input=>input.value==="")'),true);
        await evaluate('document.querySelectorAll("[data-score-input]").forEach(input=>{input.value=input.defaultValue;input.dispatchEvent(new Event("input",{bubbles:true}));})');
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
    assert.equal(await evaluate('document.querySelector("[data-score-subject-heading] [data-subject-scored]").textContent'),'1');
    assert.equal(await evaluate('document.querySelector("[data-score-subject-heading] [data-subject-blank]").textContent'),'24');
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
    await evaluate('document.querySelector("#score1_1").max="75";document.querySelector("#score1_1").value="76";document.querySelector("#score1_1").dispatchEvent(new Event("input",{bubbles:true}))');
    await tab();await until('document.querySelector(".swal2-popup")!==null');
    assert.ok(await evaluate('document.querySelector(".swal2-popup").textContent.includes("maximum of 75")'));
    await click('.swal2-confirm');await until('document.activeElement?.id==="score1_1" && document.querySelector(".swal2-popup")===null');
    await evaluate('document.querySelector("#score1_1").value="75";document.querySelector("#score1_1").dispatchEvent(new Event("input",{bubbles:true}))');
    await tab();assert.equal(await evaluate('document.activeElement.id'),'score2_1');
    await evaluate('document.querySelector("#score1_1").max="100"');
    await evaluate('document.querySelector("#score1_1").focus();document.querySelector("#score1_1").value="101";document.querySelector("#score1_1").dispatchEvent(new Event("input",{bubbles:true}));document.querySelector("#score2_1").focus()');
    await until('document.querySelector(".swal2-popup")!==null');
    await click('.swal2-confirm');await until('document.activeElement?.id==="score1_1" && document.querySelector(".swal2-popup")===null');
    await evaluate('document.querySelector("#score1_1").value="80";document.querySelector("#score1_1").dispatchEvent(new Event("input",{bubbles:true}))');
    await click('[data-scoring-fullscreen]');await until('document.querySelector("[data-scoring-fullscreen]").getAttribute("aria-pressed")==="true"');
    await evaluate('document.querySelector("#score1_1").focus();document.querySelector("#score1_1").value="101";document.querySelector("#score1_1").dispatchEvent(new Event("input",{bubbles:true}));document.querySelector("#score1_1").dispatchEvent(new KeyboardEvent("keydown",{key:"Enter",bubbles:true,cancelable:true}))');
    await until('document.querySelector(".swal2-popup")!==null');
    assert.equal(await evaluate('document.querySelector("[data-scholarship-page]").contains(document.querySelector(".swal2-container"))'),true);
    await click('.swal2-confirm');await until('document.activeElement?.id==="score1_1" && document.querySelector(".swal2-popup")===null');
    await evaluate('document.querySelector("#score1_1").value="80";document.querySelector("#score1_1").dispatchEvent(new Event("input",{bubbles:true}))');
    await click('[data-scoring-fullscreen]');await until('document.querySelector("[data-scoring-fullscreen]").getAttribute("aria-pressed")==="false"');
    await evaluate('document.querySelectorAll("[data-score-input=\\"1\\"]")[24].focus()');
    await tab();assert.equal(await evaluate('document.activeElement.hasAttribute("data-save-score-drafts")'),true);
    await click('#scholarshipScoringSubjectToggle');await click('#scholarshipScoringSubjectOptions [data-seat-option="2"]');
    await until('document.activeElement?.id==="score1_2"');
    await tab();assert.equal(await evaluate('document.activeElement.id'),'score2_2');
    assert.equal(await evaluate('[...document.querySelectorAll("[data-score-input=\\"1\\"]")].every(input=>input.readOnly)'),true);
    await click('[data-lock-score-inputs]');
    assert.equal(await evaluate('[...document.querySelectorAll("[data-score-input]")].every(input=>input.readOnly)'),true);
    const beforeInvalidSave=reviewPosts.length;
    await evaluate('document.querySelector("#score1_1").value="101";document.querySelector("#score1_1").dispatchEvent(new Event("input",{bubbles:true}))');
    await click('[data-save-score-drafts]');await until('document.querySelector(".swal2-popup")!==null');
    assert.equal(reviewPosts.length,beforeInvalidSave);
    await click('.swal2-confirm');await until('document.activeElement?.id==="score1_1" && !document.querySelector("#score1_1").readOnly && document.querySelector(".swal2-popup")===null');
    await evaluate('document.querySelector("#score1_1").value="80";document.querySelector("#score1_1").dispatchEvent(new Event("input",{bubbles:true}))');
    await click('[data-lock-score-inputs]');
    assert.equal(await evaluate('[...new FormData(document.querySelector("#scholarshipScoreDrafts")).keys()].filter(key=>key.includes("[scores][")).length'),100);
    const packed=await evaluate(`(()=>{const data=new FormData();data.set('_token','testing');for(let id=1;id<=200;id++){data.set('rows['+id+'][version]','1');for(let subject=1;subject<=4;subject++)data.set('rows['+id+'][scores]['+subject+']','80');}document.querySelector('#scholarshipScoreDrafts').dispatchEvent(new FormDataEvent('formdata',{formData:data}));return {fields:[...data.keys()].length,rows:Object.keys(JSON.parse(data.get('score_drafts'))).length};})()`);
    assert.deepEqual(packed,{fields:2,rows:200});
    await evaluate('document.querySelectorAll("[data-score-input]").forEach(input=>input.value=input.defaultValue);document.querySelectorAll(".scholarship-scoring-attendance input").forEach(input=>input.checked=input.defaultChecked)');
    await command('Page.navigate',{url:origin+'/?score_center=0&score_room=1'});await ready();
    for(const selector of ['#scoreFilterYearToggle','#scholarshipScoringSubjectToggle']){
        await click(selector);
        const aboveHeader=await evaluate(`(()=>{
            const field=document.querySelector(${JSON.stringify(selector)}).closest('.scholarship-field');
            const menu=field.querySelector('.scholarship-select-menu');
            const header=document.querySelector('.scholarship-scoring-table thead').getBoundingClientRect();
            // Deliberately overlap the fixed menu and sticky heading, regardless of Popper's chosen placement.
            menu.style.transform='none';menu.style.left=(header.left+20)+'px';menu.style.top=(header.top+5)+'px';
            const point=document.elementFromPoint(header.left+40,header.top+25);
            return !menu.hidden && menu.contains(point);
        })()`);
        assert.equal(aboveHeader,true,selector+' options remain above the sticky table header');
        await click(selector);
    }
    await command('Page.navigate',{url:origin+'/?score_center=0&score_room=1&scoring_saved=1&score_subject=1'});await ready();
    await until('document.querySelector(".swal2-toast")!==null');
    assert.ok(await evaluate('document.querySelector(".swal2-toast").textContent.includes("Score drafts saved successfully.")'));
    assert.equal(await evaluate('document.querySelector(".swal2-container").classList.contains("swal2-top-end")'),true);
    assert.equal(await evaluate('document.querySelector(".alert-success")===null'),true);
    assert.ok(await evaluate('document.querySelector(".swal2-toast").textContent.includes("No blank scores.")'));
    assert.equal(await evaluate('document.querySelectorAll("[data-score-input].is-invalid").length'),0);
    await command('Page.navigate',{url:origin+'/?scoring_many=1&score_center=0&score_room=1&scoring_saved=1&score_subject=1'});await ready();
    await until('document.querySelector(".swal2-toast")!==null');
    assert.ok(await evaluate('document.querySelector(".swal2-toast").textContent.includes("Blank scores remaining:")'));
    assert.ok(await evaluate('document.querySelector(".swal2-toast").textContent.includes("Khmer (Grade 1): 24")'));
    assert.equal(await evaluate('document.querySelector(".swal2-toast").textContent.includes("Mathematics")'),false);
    assert.equal(await evaluate('document.querySelectorAll("[data-score-input].is-invalid").length'),24);
    assert.equal(await evaluate('document.querySelectorAll("[data-score-input=\\"2\\"].is-invalid").length'),0);
    assert.equal(await evaluate('document.querySelector("[data-scoring-subject]").value'),'1');
    assert.equal(await evaluate('new FormData(document.querySelector("#scholarshipScoreDrafts")).get("score_subject")'),'1');
    assert.ok(await evaluate('document.querySelector("#score2_1Error").textContent.includes("Score not entered")'));
    assert.equal(await evaluate('document.querySelector("#score2_1").getAttribute("aria-describedby")'),'score2_1Error');
    assert.equal(await evaluate('document.querySelector("#score1_1").classList.contains("is-invalid")'),false);
    await evaluate('document.querySelector("#score2_1").value="0";document.querySelector("#score2_1").dispatchEvent(new Event("input",{bubbles:true}))');
    assert.equal(await evaluate('document.querySelector("#score2_1").classList.contains("is-invalid")'),false);
    assert.equal(await evaluate('document.querySelector("#score2_1Error").hidden'),true);
    await evaluate('document.querySelector("#score2_1").value="";document.querySelector("#score2_1").dispatchEvent(new Event("input",{bubbles:true}))');
    assert.equal(await evaluate('document.querySelector("#score2_1").classList.contains("is-invalid")'),true);
    await evaluate('window.blankDraftSubmitted=false;document.querySelector("#scholarshipScoreDrafts").addEventListener("submit",event=>{window.blankDraftSubmitted=!event.defaultPrevented;event.preventDefault();});document.querySelector("#scholarshipScoreDrafts").requestSubmit()');
    assert.equal(await evaluate('window.blankDraftSubmitted'),true);
    assert.equal(await evaluate('document.querySelector(".swal2-popup:not(.swal2-toast)")===null'),true);
    await evaluate('const subject=document.querySelector("[data-scoring-subject]");subject.value="2";subject.dispatchEvent(new Event("change",{bubbles:true}))');
    assert.equal(await evaluate('document.querySelectorAll("[data-score-input].is-invalid").length'),0);
    assert.equal(await evaluate('new FormData(document.querySelector("#scholarshipScoreDrafts")).get("score_subject")'),'2');
    await command('Page.navigate',{url:origin+'/?scoring_many=1&scoring_sort=1&score_center=0&score_room=1'});await ready();
    const firstRows=()=>evaluate('[...document.querySelectorAll("tr[data-scoring-candidate]")].slice(0,4).map(row=>Number(row.dataset.scoringCandidate))');
    await click('[data-score-sort="1"]');assert.deepEqual(await firstRows(),[2,3,4,1]);
    assert.equal(await evaluate('document.querySelector("[data-score-subject-heading=\\"1\\"]").getAttribute("aria-sort")'),'ascending');
    assert.equal(await evaluate('[...document.querySelectorAll("tr[data-scoring-candidate]")].at(-1).dataset.scoringCandidate'),'25');
    await click('[data-score-sort="1"]');assert.deepEqual(await firstRows(),[1,3,4,2]);
    await click('[data-score-sort="total"]');assert.deepEqual(await firstRows(),[2,3,4,1]);
    await click('[data-score-sort="total"]');assert.deepEqual(await firstRows(),[1,3,4,2]);
    assert.equal(await evaluate('document.querySelector("[data-score-subject-heading=\\"1\\"]").getAttribute("aria-sort")'),'none');
    assert.deepEqual(await evaluate('[...document.querySelectorAll("tr[data-scoring-candidate]")].slice(0,4).map(row=>row.cells[0].textContent)'),['1','2','3','4']);
    await click('#scholarshipScoringSubjectToggle');await click('#scholarshipScoringSubjectOptions [data-seat-option="1"]');
    await until('document.activeElement?.id==="score1_1"');
    await tab();assert.equal(await evaluate('document.activeElement.id'),'score3_1');
    await evaluate('document.querySelector("#score3_1").value="79";document.querySelector("#score3_1").dispatchEvent(new Event("input",{bubbles:true}))');
    await click('[data-score-sort="1"]');assert.deepEqual(await firstRows(),[2,3,4,1]);
    assert.equal(await evaluate('new FormData(document.querySelector("#scholarshipScoreDrafts")).get("rows[3][scores][1]")'),'79');
    assert.equal(await evaluate('document.querySelector(".swal2-popup:not(.swal2-toast)")===null'),true);
    await evaluate('document.querySelector("#score3_1").value=document.querySelector("#score3_1").defaultValue;document.querySelector("#score3_1").dispatchEvent(new Event("input",{bubbles:true}))');
    await command('Page.navigate',{url:origin+'/?score_center=0&score_room=1'});await ready();
    await click('#scholarshipScoringSubjectToggle');await click('#scholarshipScoringSubjectOptions [data-seat-option="1"]');
    await evaluate('document.querySelector("#score1_1").value="89";document.querySelector("#score1_1").dispatchEvent(new Event("input",{bubbles:true}))');
    await click('.scholarship-navigation a');await until('document.querySelector(".swal2-title")?.textContent==="Unsaved Scores"');
    const savesBeforeWarning=reviewPosts.length;
    await click('.swal2-confirm');
    for(let attempt=0;attempt<100&&reviewPosts.length===savesBeforeWarning;attempt++)await delay(50);
    assert.equal(reviewPosts.length,savesBeforeWarning+1);
    assert.equal(reviewPosts.at(-1)['rows[1][scores][1]'],'89');
    assert.equal(reviewPosts.at(-1).score_subject,'1');
    await until('document.querySelector("#score1_1")?.value==="90" && document.querySelector("[data-scoring-filters-ready]")');
    }
    await command('Emulation.setDeviceMetricsOverride',{width:1440,height:980,deviceScaleFactor:1,mobile:false});
    await command('Page.navigate',{url:origin+'/?scoring_many=1&score_center=0&score_room=1&subject_attendance=1&scoring_saved=1&score_subject=1'});await ready();
    assert.equal(await evaluate('document.querySelector("#score1_1").value'),'0');
    assert.equal(await evaluate('document.querySelector("#score1_1").readOnly'),true);
    assert.equal(await evaluate('document.querySelector("#score1_1").closest("td").querySelector(".scholarship-score-absent-letter").hidden'),false);
    assert.equal(await evaluate('getComputedStyle(document.querySelector("#score1_1").closest("td").querySelector(".scholarship-score-absent-letter")).color'),'rgb(214, 57, 57)');
    assert.equal(await evaluate('document.querySelector("#score1_1").classList.contains("is-invalid")'),false);
    assert.equal(await evaluate('document.querySelector("#score3_1").value'),'0');
    assert.equal(await evaluate('document.querySelector("#score3_1").dataset.subjectAbsent'),'false');
    assert.equal(await evaluate('document.querySelector("#score1_2").value'),'90');
    assert.equal(await evaluate('document.querySelector("[data-score-subject-heading=\\"1\\"] [data-subject-absent]").textContent'),'2');
    assert.equal(await evaluate('document.querySelector("#score1_1").closest("tr").querySelector("[data-all-subjects-absent]").checked'),false);
    assert.equal(await evaluate('document.querySelector("#score2_1").closest("tr").querySelector("[data-all-subjects-absent]").checked'),true);
    await click('.swal2-close');await until('document.querySelector(".swal2-popup")===null');
    await click('[data-scoring-candidate="1"] [data-subject-absence="1"]');
    assert.equal(await evaluate('document.querySelector("#score1_1").value'),'');
    assert.equal(await evaluate('document.querySelector("#score1_1").readOnly'),false);
    assert.equal(await evaluate('document.querySelector("#score1_2").value'),'90');
    await click('[data-scoring-candidate="1"] [data-subject-absence="1"]');
    assert.equal(await evaluate('document.querySelector("#score1_1").value'),'0');
    await click('[data-scoring-candidate="1"] [data-all-subjects-absent]');
    assert.equal(await evaluate('[...document.querySelectorAll("[data-scoring-candidate=\\"1\\"] [data-score-input]")].every(input=>input.value==="0"&&input.dataset.subjectAbsent==="true")'),true);
    const attendanceData=await evaluate('Object.fromEntries(new FormData(document.querySelector("#scholarshipScoreDrafts")))');
    assert.equal(attendanceData['rows[1][absent]'],'1');
    for(const id of [1,2,3,4])assert.equal(attendanceData[`rows[1][subject_absences][${id}]`],'1');
    assert.equal(await evaluate(`(()=>{const data=new FormData(document.querySelector('#scholarshipScoreDrafts'));for(let id=100;id<1000;id++)data.set('rows['+id+'][version]','1');document.querySelector('#scholarshipScoreDrafts').dispatchEvent(new FormDataEvent('formdata',{formData:data}));return JSON.parse(data.get('score_drafts'))['1'].subject_absences['1'];})()`),'1');
    await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:false}).then(result=>writeFile(join(root,'storage/app/testing/scoring-subject-attendance.png'),Buffer.from(result.data,'base64')));
    // Restore the original saved state before navigating away.
    await click('[data-scoring-candidate="1"] [data-all-subjects-absent]');
    await click('[data-scoring-candidate="1"] [data-subject-absence="1"]');
    await evaluate('document.querySelectorAll("[data-scoring-candidate=\\"1\\"] [data-score-input]").forEach(input=>{input.value=input.defaultValue;input.dispatchEvent(new Event("input",{bubbles:true}));})');
    await command('Page.navigate',{url:origin+'/?tab=result&subject_attendance=1'});await until('document.querySelector("[data-result-filters-ready]")!==null');
    assert.equal(await evaluate('document.querySelectorAll("[data-result-candidate]").length'),8);
    assert.equal(await evaluate('document.querySelector("[data-result-candidate=\\"1\\"] [data-result-score=\\"1\\"]").dataset.sortValue'),'0');
    assert.equal(await evaluate('document.querySelector("[data-result-candidate=\\"1\\"] [data-result-score=\\"1\\"] .text-danger").textContent'),'Absent');
    assert.equal(await evaluate('document.querySelector("[data-result-candidate=\\"2\\"] [data-result-score=total]").dataset.sortValue'),'0');
    assert.equal(await evaluate('document.querySelector("[data-result-candidate=\\"2\\"] [data-result-award-select]").disabled'),true);
    assert.equal(await evaluate('document.querySelector("[data-result-candidate=\\"1\\"] [data-result-award-select] option[value=total_1]").disabled'),true);
    assert.equal(await evaluate('document.querySelector("[data-result-candidate=\\"1\\"] [data-result-award-select] option[value=subject_2]").disabled'),false);
    for(const [action,message] of [['winners','Scholarship winners saved for Grade 1.'],['published','Results published and locked.']]){
        await command('Page.navigate',{url:origin+'/?result_success='+action});
        await until('document.querySelector(".swal2-toast")!==null');
        assert.equal(await evaluate('document.querySelector(".swal2-title").textContent'),message);
        assert.equal(await evaluate('document.querySelector(".swal2-container").classList.contains("swal2-top-end")'),true);
        assert.equal(await evaluate('document.querySelector(".swal2-toast .swal2-success")!==null'),true);
        assert.equal(await evaluate('document.querySelector(".alert-success[role=status]")===null'),true);
        if(action==='winners'){
            await click('.swal2-close');
            await until('document.querySelector(".swal2-popup")===null');
            await evaluate(`window.publishSubmissions=0;window.confirm=()=>{throw new Error('Native confirmation must not be used');};document.addEventListener('submit',event=>{if(event.target.matches('[data-result-publish-confirm]')&&!event.defaultPrevented){window.publishSubmissions++;event.preventDefault();}});document.querySelector('[data-result-publish-confirm]').requestSubmit()`);
            assert.equal(await evaluate('document.querySelector(".swal2-popup")===null'),true);
            await click('[data-result-publish-confirm] [name=confirm]');
            await click('[data-result-publish-confirm] button');
            await until('document.querySelector(".swal2-title")?.textContent==="Publish & Lock Results?"');
            assert.equal(await evaluate('document.querySelector(".swal2-container").classList.contains("swal2-center")'),true);
            assert.equal(await evaluate('getComputedStyle(document.querySelector(".swal2-actions")).justifyContent'),'center');
            await click('.swal2-cancel');
            await until('document.querySelector(".swal2-popup")===null');
            assert.equal(await evaluate('window.publishSubmissions'),0);
            await click('[data-result-publish-confirm] button');
            await until('document.querySelector(".swal2-title")?.textContent==="Publish & Lock Results?"');
            await click('.swal2-confirm');
            await until('window.publishSubmissions===1');
        }
    }
    for(const mobile of [false,true]){
        await command('Emulation.setDeviceMetricsOverride',{width:mobile?390:1440,height:mobile?844:980,deviceScaleFactor:1,mobile});
        await command('Page.navigate',{url:origin+'/?tab=result'});
        const resultReady=()=>until('document.querySelector("[data-result-filters-ready]")!==null');
        await resultReady();
        assert.equal(await evaluate('document.querySelector("#scholarshipResultFilters").hidden'),mobile);
        assert.equal(await evaluate('document.querySelector("[data-result-card] > .card-header").contains(document.querySelector("[data-result-fullscreen]"))'),true);
        await command('Runtime.evaluate',{expression:'document.querySelector("[data-result-fullscreen]").click()',userGesture:true});
        await until('document.querySelector("[data-result-fullscreen]").getAttribute("aria-pressed")==="true"');
        assert.equal(await evaluate('getComputedStyle(document.querySelector(".scholarship-navigation")).display'),'none');
        assert.equal(await evaluate('document.querySelector("[data-result-fullscreen]").textContent.trim()'),'Exit Full Screen');
        assert.equal(await evaluate('(()=>{const r=document.querySelector("[data-scholarship-page]").getBoundingClientRect();return Math.abs(r.width-innerWidth)<2&&Math.abs(r.height-innerHeight)<2;})()'),true);
        await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:false}).then(result=>writeFile(join(root,'storage/app/testing/result-fullscreen-'+(mobile?'mobile':'desktop')+'.png'),Buffer.from(result.data,'base64')));
        await click('[data-result-fullscreen]');
        await until('document.querySelector("[data-result-fullscreen]").getAttribute("aria-pressed")==="false"');
        assert.equal(await evaluate('document.body.classList.contains("scholarship-result-fullscreen-open")'),false);
        await evaluate('Object.defineProperty(document.querySelector("[data-scholarship-page]"),"requestFullscreen",{value:undefined,configurable:true})');
        await click('[data-result-fullscreen]');
        await until('document.querySelector("[data-result-fullscreen]").getAttribute("aria-pressed")==="true"');
        await evaluate('document.dispatchEvent(new KeyboardEvent("keydown",{key:"Escape",bubbles:true}));delete document.querySelector("[data-scholarship-page]").requestFullscreen');
        await until('document.querySelector("[data-result-fullscreen]").getAttribute("aria-pressed")==="false"');
        assert.equal(await evaluate('document.querySelector("[data-exam-selector]")===null'),true);
        assert.equal(await evaluate('document.querySelector("#resultFilterGradeToggle").disabled'),false);
        assert.equal(await evaluate('document.querySelector("#resultFilterStudentToggle").disabled'),true);
        if(mobile)await click('[data-result-filters-toggle]');
        await click('#resultFilterGradeToggle');await click('#resultFilterGradeOptions [data-seat-option="1"]');
        await until('location.search.includes("result_grade=1")');await resultReady();
        assert.equal(await evaluate('document.querySelector("#resultFilterCenter").value'),'');
        assert.equal(await evaluate('document.querySelector("#resultFilterStudentToggle").disabled'),false);
        assert.equal(await evaluate('document.querySelectorAll("[data-result-candidate]").length'),8);
        if(mobile)await click('[data-result-filters-toggle]');
        await click('#resultFilterCenterToggle');await click('#resultFilterCenterOptions [data-seat-option="0"]');
        await until('location.search.includes("result_center=0")');await resultReady();
        assert.equal(await evaluate('document.querySelector("#resultFilterGradeToggle").disabled'),false);
        if(mobile)await click('[data-result-filters-toggle]');
        await click('#resultFilterGradeToggle');await click('#resultFilterGradeOptions [data-seat-option="1"]');
        await until('location.search.includes("result_grade=1")');await resultReady();
        assert.deepEqual(await evaluate('[...document.querySelector("#resultFilterStudent").options].slice(1).map(option=>Number(option.textContent.split(" - ")[0]))'),[1,2,3,4,5,6,7,8]);
        if(mobile)await click('[data-result-filters-toggle]');
        await click('#resultFilterStudentToggle');
        await evaluate('const search=document.querySelector("#resultFilterStudent").closest(".scholarship-field").querySelector("input[type=search]");search.value="AWARD STUDENT";search.dispatchEvent(new Event("input"))');
        assert.equal(await evaluate('[...document.querySelectorAll("#resultFilterStudentOptions button")].every(button=>button.textContent.includes("AWARD STUDENT"))'),true);
        const chosen=await evaluate('document.querySelector("#resultFilterStudentOptions button").dataset.seatOption');
        await click('#resultFilterStudentOptions button');
        await until('location.search.includes("result_student='+chosen+'")');await resultReady();
        assert.equal(await evaluate('document.querySelector(".scholarship-scroll-table table tbody").rows.length'),1);
        if(mobile)await click('[data-result-filters-toggle]');
        await evaluate('window.resultFilterSubmit=null;document.querySelector("[data-result-filters]").addEventListener("submit",event=>{window.resultFilterSubmit=Object.fromEntries(new FormData(event.target));event.preventDefault();})');
        await click('#resultFilterCenterToggle');await click('#resultFilterCenterOptions [data-seat-option=""]');
        const reset=await evaluate('window.resultFilterSubmit');
        assert.equal(reset.result_center,'');assert.equal('result_grade' in reset,false);assert.equal('result_student' in reset,false);
        await click('[data-result-filters-toggle]');
        assert.equal(await evaluate('document.querySelector("#scholarshipResultFilters").hidden'),true);
    }
    await command('Emulation.setDeviceMetricsOverride',{width:1440,height:980,deviceScaleFactor:1,mobile:false});
    await command('Page.navigate',{url:origin+'/?tab=result'});
    await until('document.querySelector("[data-result-filters-ready]")!==null');
    assert.equal(await evaluate('document.querySelectorAll("[data-result-candidate]").length'),8);
    assert.equal(await evaluate('document.querySelectorAll("[data-result-sort]").length'),6);
    assert.equal(await evaluate('document.querySelectorAll("[data-result-award-select]").length'),8);
    // Include the real global form rules, which normally force the surface background.
    const globalFormStyles=(await readFile(join(root,'resources/css/app.css'),'utf8')).replace(/^@import\s+.*;\s*$/gm,'');
    await evaluate(`(()=>{const style=document.createElement('style');style.textContent=${JSON.stringify(globalFormStyles)};document.head.append(style);})()`);
    const resultIds=await evaluate('[...document.querySelectorAll("[data-result-candidate]")].map(row=>row.dataset.resultCandidate)');
    await evaluate(`(()=>{const rows=[...document.querySelectorAll('[data-result-candidate]')];for(const [index,value] of [[0,'10'],[1,'2'],[2,'']]){rows[index].querySelector('[data-result-score="1"]').dataset.sortValue=value;rows[index].querySelector('[data-result-score="total"]').dataset.sortValue=value;}})()`);
    const resultRows=()=>evaluate('[...document.querySelectorAll("[data-result-candidate]")].map(row=>row.dataset.resultCandidate)');
    await click('[data-result-sort="1"]');assert.equal((await resultRows())[0],resultIds[1]);assert.equal((await resultRows()).at(-1),resultIds[2]);
    await click('[data-result-sort="1"]');assert.equal((await resultRows()).at(-1),resultIds[2]);
    await click('[data-result-sort="total"]');assert.equal((await resultRows())[0],resultIds[1]);
    const choose=async(index,slot)=>evaluate(`(()=>{const select=document.querySelector('[data-result-candidate="${resultIds[index]}"] [data-result-award-select]');select.value=${JSON.stringify(slot)};select.dispatchEvent(new Event('change',{bubbles:true}));})()`);
    assert.equal(await evaluate('document.querySelectorAll("[data-result-award-select].is-award-assigned").length'),0);
    assert.equal(await evaluate('document.querySelectorAll("[data-result-eoy-average]").length'),8);
    await choose(0,'total_1');
    assert.equal(await evaluate(`document.querySelector('[data-result-candidate="${resultIds[0]}"] [data-result-award-select]').classList.contains('is-award-assigned')`),true);
    assert.equal(await evaluate(`getComputedStyle(document.querySelector('[data-result-candidate="${resultIds[0]}"] [data-result-award-select]')).backgroundColor`),'rgb(234, 247, 236)');
    await evaluate(`document.querySelector('[data-result-candidate="${resultIds[0]}"] [data-result-award-select]').disabled=true;document.documentElement.dataset.bsTheme='dark';`);
    assert.equal(await evaluate(`getComputedStyle(document.querySelector('[data-result-candidate="${resultIds[0]}"] [data-result-award-select]')).backgroundColor`),'rgb(234, 247, 236)');
    await evaluate(`document.querySelector('[data-result-candidate="${resultIds[0]}"] [data-result-award-select]').disabled=false;delete document.documentElement.dataset.bsTheme;`);
    await choose(0,'');
    assert.equal(await evaluate(`document.querySelector('[data-result-candidate="${resultIds[0]}"] [data-result-award-select]').classList.contains('is-award-assigned')`),false);
    await choose(0,'total_1');
    assert.equal(await evaluate(`document.querySelector('[data-result-candidate="${resultIds[0]}"] [data-result-award-percent]').value`),'100');
    await click('[data-result-report-format="print"]');await until('document.querySelector(".swal2-title")?.textContent==="Unsaved Winners"');
    assert.equal(await evaluate('document.querySelector("#scholarshipResultReportScope").classList.contains("show")'),false);
    await click('.swal2-confirm');await until('document.querySelector(".swal2-popup")===null');
    await command('Runtime.evaluate',{expression:'document.querySelector("[data-result-fullscreen]").click()',userGesture:true});
    await until('document.querySelector("[data-result-fullscreen]").getAttribute("aria-pressed")==="true"');
    await choose(1,'total_1');await until('document.querySelector(".swal2-title")?.textContent==="Check Scholarship Winners"');
    assert.equal(await evaluate('document.querySelector("[data-scholarship-page]").contains(document.querySelector(".swal2-container"))'),true);
    assert.equal(await evaluate(`document.querySelector('[data-result-candidate="${resultIds[1]}"] [data-result-award-select]').value`),'');
    assert.equal(await evaluate(`document.querySelector('[data-result-candidate="${resultIds[1]}"] [data-result-award-select]').classList.contains('is-invalid')`),true);
    await click('.swal2-confirm');await until('document.querySelector(".swal2-popup")===null');
    await click('[data-result-fullscreen]');
    await until('document.querySelector("[data-result-fullscreen]").getAttribute("aria-pressed")==="false"');
    assert.equal(await evaluate(`document.querySelector('[data-result-candidate="${resultIds[0]}"] [data-result-award-select]').value`),'total_1');
    await click('[data-result-save]');await until('document.querySelector(".swal2-title")?.textContent==="Check Scholarship Winners"');
    assert.equal(awardPosts.length,0);
    await click('.swal2-confirm');await until('document.querySelector(".swal2-popup")===null');
    await click('#resultFilterCenterToggle');await click('#resultFilterCenterOptions [data-seat-option="0"]');
    await until('document.querySelector(".swal2-title")?.textContent==="Unsaved Winners"');
    assert.equal(await evaluate('document.querySelector("#resultFilterCenter").value'),'');
    await click('.swal2-confirm');await until('document.querySelector(".swal2-popup")===null');
    const awardSlots=await evaluate('[...document.querySelectorAll("[data-result-award-slot]")].map(slot=>slot.dataset.resultAwardSlot)');
    for(let index=1;index<awardSlots.length;index++)await choose(index,awardSlots[index]);
    await click('[data-result-sort="award"]');assert.deepEqual(await resultRows(),resultIds);
    await click('[data-result-sort="award"]');assert.deepEqual(await resultRows(),[...resultIds.slice(0,7).reverse(),resultIds[7]]);
    await click('[data-result-sort="award"]');assert.deepEqual(await resultRows(),resultIds);
    await choose(0,'');assert.equal((await resultRows())[0],resultIds[1]);assert.deepEqual((await resultRows()).slice(-2),[resultIds[0],resultIds[7]]);
    await choose(0,'total_1');assert.deepEqual(await resultRows(),resultIds);
    assert.deepEqual(await evaluate('[...document.querySelectorAll("[data-result-award-select]")].filter(select=>select.value).map(select=>Number(select.closest("tr").querySelector("[data-result-award-percent]").value)).sort((a,b)=>a-b)'),[25,25,25,25,50,75,100]);
    await click('[data-result-save]');await until('document.querySelector(".swal2-title")?.textContent==="Unable to Save Winners"');
    assert.equal(await evaluate('document.querySelector("[data-result-unsaved]").hidden'),false);
    await click('.swal2-confirm');await until('document.querySelector(".swal2-popup")===null');
    await click('[data-result-save]');await until('document.querySelector(".swal2-toast")!==null');
    assert.equal(awardPosts.length,2);
    assert.equal(awardPosts[1]['awards[total_1][candidate_id]'],resultIds[0]);
    assert.equal(awardPosts[1]['awards[total_2][award_percent]'],'75');
    assert.equal(await evaluate('document.querySelector("[data-result-unsaved]").hidden'),true);
    assert.equal(await evaluate('document.querySelector("[data-result-publish-confirm] [name=version]").value'),String(Number(awardPosts[1].version)+1));
    for(const mobile of [false,true]){
        await command('Emulation.setDeviceMetricsOverride',{width:mobile?390:1440,height:mobile?844:980,deviceScaleFactor:1,mobile});
        if(mobile){await command('Page.navigate',{url:origin+'/?tab=result'});await until('document.querySelector("[data-result-filters-ready]")!==null');}
        assert.equal(await evaluate('innerWidth'),mobile?390:1440);
        assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'),true);
        await command('Page.captureScreenshot',{format:'png'}).then(result=>writeFile(join(root,'storage/app/testing/result-'+(mobile?'mobile':'desktop')+'.png'),Buffer.from(result.data,'base64')));
    }
    // Result report scopes and separate signer settings.
    for(const mobile of [false,true]){
        await command('Emulation.setDeviceMetricsOverride',{width:mobile?390:1440,height:mobile?844:980,deviceScaleFactor:1,mobile});
        await command('Page.navigate',{url:origin+'/?tab=result&result_report_test=1'});await until('document.querySelector("[data-result-filters-ready]")!==null');
        await evaluate('window.resultReportOpened="";window.open=url=>{window.resultReportOpened=String(url);};');
        const resultModal=()=>until('document.querySelector("#scholarshipResultReportScope").classList.contains("show")&&document.querySelector("#scholarshipResultReportScope .modal-dialog").getAnimations().length===0');
        const closed=()=>until('!document.body.classList.contains("modal-open")');
        assert.equal(await evaluate('(()=>{const settings=document.querySelector("[data-result-print-settings]"),pdf=document.querySelector("[data-result-report-format=pdf]");return Boolean(pdf.compareDocumentPosition(settings)&Node.DOCUMENT_POSITION_FOLLOWING);})()'),true);
        await click('[data-result-report-format="print"]');await resultModal();
        assert.equal(await evaluate('document.querySelector("#resultReportScope").value'),'all-grades');
        await evaluate('document.querySelector("#resultReportScope").value="single-grade";document.querySelector("#resultReportScope").dispatchEvent(new Event("change",{bubbles:true}));');
        await click('[data-result-report-open]');assert.equal(await evaluate('document.querySelector("#resultReportGrade").classList.contains("is-invalid")'),true);
        await click('#resultReportGradeToggle');await click('#resultReportGradeOptions [data-seat-option="2"]');
        await click('[data-result-report-open]');await closed();
        assert.equal(new URL(await evaluate('window.resultReportOpened')).searchParams.get('report_grade'),'2');
        await click('[data-result-report-format="xlsx"]');await resultModal();
        await evaluate('document.querySelector("#resultReportScope").value="selected-grades";document.querySelector("#resultReportScope").dispatchEvent(new Event("change",{bubbles:true}));');
        await click('#resultReportGradesToggle');
        await click('#resultReportGradesOptions [data-seat-option="1"]');await click('#resultReportGradesOptions [data-seat-option="3"]');
        const beforeExcel=resultReportRequests.length;await click('[data-result-report-open]');await closed();
        for(let i=0;i<100&&resultReportRequests.length===beforeExcel;i++)await delay(50);
        assert.deepEqual(resultReportRequests.at(-1).grades,['1','3']);
        for(const mode of ['combined','separate']){
            await click('[data-result-report-format="pdf"]');await resultModal();
            await evaluate(`document.querySelector('#resultReportPdfMode').value='${mode}';document.querySelector('#resultReportPdfMode').dispatchEvent(new Event('change',{bubbles:true}));`);
            const beforePdf=resultReportRequests.length;await click('[data-result-report-open]');await closed();
            for(let i=0;i<100&&resultReportRequests.length===beforePdf;i++)await delay(50);
            assert.equal(resultReportRequests.at(-1).params.pdf_mode,mode);
            assert.equal(resultReportRequests.at(-1).params.report_scope,'all-grades');
        }
        await click('[data-result-print-settings]');
        await until('document.querySelector("[data-save-print-settings]")?.disabled===false');
        assert.equal(await evaluate('document.querySelector("#scholarshipPrintSettingsTitle").textContent'),'Result Print Settings');
        assert.equal(await evaluate('document.querySelector("#resultPrintLocation").value'),'Phnom Penh');
        assert.equal(await evaluate('document.querySelectorAll("[data-print-signature-role]:not([hidden])").length'),3);
        assert.equal(await evaluate('document.querySelector("[data-staff-position=prepared]").textContent'),'Position: Registrar');
        await evaluate('const campus=document.querySelector("#rankPrintCampus");campus.value="2";campus.dispatchEvent(new Event("change",{bubbles:true}));');
        await until('document.querySelector("[data-save-print-settings]").disabled===false&&document.querySelector("#rankPrintCampus").value==="2"');
        await click('#rankPrintPreparedBy');assert.equal(await evaluate('[...document.querySelectorAll("[data-signature-role=prepared] [data-staff-results] button")].every(button=>button.textContent.includes("BKK"))'),true);
        await click('[data-signature-role="prepared"] [data-staff-results] button');
        await click('#rankPrintCheckedBy');await click('[data-signature-role="checked"] [data-staff-results] button');
        await click('#rankPrintApprovedBy');await click('[data-signature-role="approved"] [data-staff-results] button');
        await evaluate('document.querySelector("#resultPrintLocation").value="Battambang";');
        await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:false}).then(result=>writeFile(join(root,'storage/app/testing/result-print-settings-'+(mobile?'mobile':'desktop')+'.png'),Buffer.from(result.data,'base64')));
        const beforeSettings=resultSettingPosts.length;await click('[data-save-print-settings]');await closed();
        assert.equal(resultSettingPosts.length,beforeSettings+1);await until('document.querySelector(".swal2-toast")!==null');
        await evaluate('document.querySelector(".swal2-close")?.click();');
        assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'),true);
    }
    execFileSync(php,[join(root,'tests/browser/scholarship-result-report-fixture.php')],{cwd:root});
    await command('Emulation.setDeviceMetricsOverride',{width:1440,height:980,deviceScaleFactor:1,mobile:false});
    await command('Page.addScriptToEvaluateOnNewDocument',{source:'window.print=()=>{window.resultPrintRequested=true;}'});
    await command('Page.navigate',{url:origin+'/result-list-preview'});await until('window.resultPrintRequested===true');
    const resultPdf=await command('Page.printToPDF',{preferCSSPageSize:true,printBackground:true,displayHeaderFooter:false});
    assert.equal([...Buffer.from(resultPdf.data,'base64').toString('latin1').matchAll(/\/Type\s*\/Page\b/g)].length,3);
    await writeFile(join(root,'storage/app/testing/result-reports/result-list-browser.pdf'),Buffer.from(resultPdf.data,'base64'));
    // Exercise report scopes using all four synthetic rooms, on desktop and phone.
    for(const mobile of [false,true]){
        await command('Emulation.setDeviceMetricsOverride',{width:mobile?390:1440,height:mobile?844:980,deviceScaleFactor:1,mobile});
        await command('Page.navigate',{url:origin+'/?scoring_many=1&score_center=0&score_room=1'});await ready();
        await evaluate('window.scoreReportOpened="";window.open=url=>{window.scoreReportOpened=String(url);};');
        const reportModal=()=>until('document.querySelector("#scholarshipScoreReportScope").classList.contains("show") && getComputedStyle(document.querySelector("#scholarshipScoreReportScope")).opacity==="1" && document.querySelector("#scholarshipScoreReportScope .modal-dialog").getAnimations().length===0');
        const reportClosed=()=>until('!document.body.classList.contains("modal-open")');
        await click('[data-score-report-format="print"]');await reportModal();
        assert.equal(await evaluate('document.querySelector("#scoreReportCenter").value'),'0');
        assert.equal(await evaluate('document.querySelector("#scoreReportRoom").value'),'1');
        assert.equal(await evaluate('document.querySelector("#scoreReportScope").value'),'single-room');
        await click('[data-score-report-open]');await reportClosed();
        let printed=new URL(await evaluate('window.scoreReportOpened'));
        assert.equal(printed.searchParams.get('report_room'),'1');assert.equal(printed.searchParams.has('score_student'),false);
        await click('[data-score-report-format="print"]');await reportModal();
        await evaluate('const scope=document.querySelector("#scoreReportScope");scope.value="selected-rooms";scope.dispatchEvent(new Event("change",{bubbles:true}));');
        await click('#scoreReportRoomsToggle');await click('#scoreReportRoomsOptions [data-seat-option="1"]');
        await click('[data-score-report-open]');
        assert.equal(await evaluate('document.querySelector("#scoreReportRooms").classList.contains("is-invalid")'),true);
        await click('#scoreReportRoomsToggle');
        for(const number of ['2','3']){
            await evaluate(`(()=>{const search=document.querySelector('#scoreReportRooms').closest('.scholarship-field').querySelector('input[type=search]');search.value='Room ${number}';search.dispatchEvent(new Event('input'));})()`);
            await click('#scoreReportRoomsOptions [data-seat-option="'+number+'"]');
        }
        await click('[data-score-report-open]');await reportClosed();
        printed=new URL(await evaluate('window.scoreReportOpened'));
        assert.deepEqual(printed.searchParams.getAll('report_rooms[]'),['2','3']);
        assert.equal(printed.searchParams.get('report_scope'),'selected-rooms');
        await click('[data-score-report-format="xlsx"]');await reportModal();
        assert.equal(await evaluate('document.querySelector("[data-score-report-pdf]").hidden'),true);
        await evaluate('document.querySelector("#scoreReportScope").value="all-rooms";document.querySelector("#scoreReportScope").dispatchEvent(new Event("change",{bubbles:true}));');
        const beforeExcel=scoreReportRequests.length;
        await click('[data-score-report-open]');await reportClosed();
        for(let i=0;i<100&&scoreReportRequests.length===beforeExcel;i++)await delay(50);
        assert.equal(scoreReportRequests.at(-1).format,'xlsx');assert.equal(scoreReportRequests.at(-1).params.report_scope,'all-rooms');
        await click('[data-scoring-fullscreen]');await until('document.querySelector("[data-scoring-fullscreen]").getAttribute("aria-pressed")==="true"');
        await click('[data-score-report-format="print"]');await reportModal();
        assert.equal(await evaluate('(()=>{const dialog=document.querySelector("#scholarshipScoreReportScope .modal-content").getBoundingClientRect();return dialog.left>=0&&dialog.right<=innerWidth&&dialog.top>=0&&dialog.bottom<=innerHeight;})()'),true,'Report scope stays inside the fullscreen viewport');
        await click('#scholarshipScoreReportScope [data-bs-dismiss="modal"]');await reportClosed();
        await click('[data-scoring-fullscreen]');await until('document.querySelector("[data-scoring-fullscreen]").getAttribute("aria-pressed")==="false"');
        for(const mode of ['combined','separate']){
            await click('[data-score-report-format="pdf"]');await reportModal();
            assert.equal(await evaluate('document.querySelector("[data-score-report-pdf]").hidden'),false);
            await evaluate(`document.querySelector('#scoreReportScope').value='all-rooms';document.querySelector('#scoreReportScope').dispatchEvent(new Event('change',{bubbles:true}));document.querySelector('#scoreReportPdfMode').value='${mode}';document.querySelector('#scoreReportPdfMode').dispatchEvent(new Event('change',{bubbles:true}));`);
            assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'),true);
            if(mode==='separate')await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:false}).then(result=>writeFile(join(root,'storage/app/testing/score-report-scope-'+(mobile?'mobile':'desktop')+'.png'),Buffer.from(result.data,'base64')));
            const beforePdf=scoreReportRequests.length;
            await click('[data-score-report-open]');await reportClosed();
            for(let i=0;i<100&&scoreReportRequests.length===beforePdf;i++)await delay(50);
            assert.equal(scoreReportRequests.at(-1).format,'pdf');assert.equal(scoreReportRequests.at(-1).params.pdf_mode,mode);
        }
    }
    // Verify the browser print path as well as the server-side PDF renderer.
    execFileSync(php,[join(root,'tests/browser/scholarship-score-report-fixture.php')],{cwd:root});
    await command('Emulation.setDeviceMetricsOverride',{width:1440,height:980,deviceScaleFactor:1,mobile:false});
    await command('Page.addScriptToEvaluateOnNewDocument',{source:'window.print=()=>{window.scorePrintRequested=true;}'});
    await command('Page.navigate',{url:origin+'/score-list-preview'});
    await until('window.scorePrintRequested===true');
    assert.equal(await evaluate('document.querySelectorAll("[data-score-report-room]").length'),2);
    assert.equal(await evaluate('document.querySelector(".logo img").alt'),'School Logo 1');
    assert.equal(await evaluate('document.querySelector(".score-table tbody").rows.length'),25);
    assert.equal(await evaluate('document.querySelectorAll(".score-table .absent").length>0'),true);
    const printPdf=await command('Page.printToPDF',{preferCSSPageSize:true,printBackground:true,displayHeaderFooter:false});
    const pdfBytes=Buffer.from(printPdf.data,'base64');
    assert.equal([...pdfBytes.toString('latin1').matchAll(/\/Type\s*\/Page\b/g)].length,2,'Browser printing keeps each room on one landscape page');
    await writeFile(join(root,'storage/app/testing/score-reports/score-list-browser.pdf'),pdfBytes);
    await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:false}).then(result=>writeFile(join(root,'storage/app/testing/score-reports/score-list-preview.png'),Buffer.from(result.data,'base64')));
    assert.deepEqual(errors,[]);
    if(!process.env.CHAT_TEST_RESULTS_ONLY)assert.ok(requests.some(request=>request.score_center==='0'&&request.score_room==='1'&&request.score_student==='1'));
    console.log('Scoring and Result desktop/mobile behavior, Score List export buttons, unsaved-score guard and landscape print preview passed.');
}finally{socket?.close();browser.kill();server.closeAllConnections();await new Promise(resolve=>server.close(resolve));}
