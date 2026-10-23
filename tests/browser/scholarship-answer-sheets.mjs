import assert from 'node:assert/strict';
import { build } from 'vite';
import { createServer } from 'node:http';
import { spawn,execFileSync } from 'node:child_process';
import { readFile,mkdtemp,writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join,resolve } from 'node:path';

const root=resolve(import.meta.dirname,'../..'),php='C:/laragon/bin/php/php-8.4.25-Win32-vs17-x64/php.exe',chrome='C:/Program Files/Google/Chrome/Application/chrome.exe';
const temp=await mkdtemp(join(tmpdir(),'wis-answer-sheets-')),db=join(temp,'test.sqlite');await writeFile(db,'');
const bundle=await build({root,configFile:false,logLevel:'silent',build:{write:false,lib:{entry:{answers:join(root,'resources/js/scholarshipAnswerSheets.js'),scholarship:join(root,'resources/js/westernScholarship.js')},formats:['es'],fileName:(_,name)=>name+'.js'}}});
const files=new Map();for(const output of Array.isArray(bundle)?bundle:[bundle])for(const f of output.output)files.set('/'+f.fileName,f.type==='chunk'?f.code:f.source);
const fixture=(path,payload={})=>JSON.parse(execFileSync(php,[join(root,'tests/browser/scholarship-answer-sheets-fixture.php'),db,path],{cwd:root,input:JSON.stringify(payload),encoding:'utf8',maxBuffer:25*1024*1024}));
const server=createServer(async(req,res)=>{
    try{
        const url=new URL(req.url,'http://localhost');
        if(files.has(url.pathname)){res.setHeader('Content-Type',url.pathname.endsWith('.js')?'text/javascript':'text/css');res.end(files.get(url.pathname));return;}
        if(url.pathname.startsWith('/node_modules/')||url.pathname.startsWith('/resources/')){res.setHeader('Content-Type',url.pathname.endsWith('.css')?'text/css':'font/woff2');res.end(await readFile(join(root,url.pathname)));return;}
        const chunks=[];for await(const chunk of req)chunks.push(chunk);const body=Buffer.concat(chunks);let payload=Object.fromEntries(url.searchParams);
        if(req.headers['content-type']?.includes('multipart/form-data')){
            const form=await new Response(body,{headers:{'Content-Type':req.headers['content-type']}}).formData();
            for(const [k,v]of form)payload[k]=typeof v==='string'?v:Buffer.from(await v.arrayBuffer()).toString('base64');
        }else if(body.length)payload=JSON.parse(body.toString());
        const result=fixture(url.pathname,payload);res.statusCode=result.status;res.setHeader('Content-Type',result.type);
        let content=Buffer.from(result.content,'base64');
        if(result.type==='text/html'){
            let html=content.toString().replaceAll('http://localhost','').replaceAll('http:\\/\\/localhost','');
            if(!html.includes('answer-print-page'))html=`<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="fixture"><link rel="stylesheet" href="/node_modules/@tabler/core/dist/css/tabler.min.css"><link rel="stylesheet" href="/node_modules/@tabler/icons-webfont/dist/tabler-icons.min.css"><link rel="stylesheet" href="/resources/css/pages/western-scholarship.css"><link rel="stylesheet" href="/resources/css/pages/scholarship-answer-sheets.css"></head><body><div class="container-fluid p-3">${html}</div><script>window.errors=[];addEventListener('error',e=>errors.push(e.message));addEventListener('unhandledrejection',e=>errors.push(String(e.reason)));window.open=(url)=>{window.printUrl=url;return{location:{assign:u=>window.printUrl=u},close:()=>{}};};</script><script type="module" src="/answers.js"></script><script type="module" src="/scholarship.js"></script></body></html>`;
            content=html;
        }else if(result.type.includes('json'))content=content.toString().replaceAll('http:\\/\\/localhost','').replaceAll('http://localhost','');
        res.end(content);
    }catch(e){res.statusCode=500;res.end(JSON.stringify({message:e.stack}));}
});
await new Promise(r=>server.listen(0,'127.0.0.1',r));const origin=`http://127.0.0.1:${server.address().port}`;
const browser=spawn(chrome,['--headless=new','--no-sandbox','--disable-gpu','--no-first-run','--remote-debugging-port=0',`--user-data-dir=${join(temp,'chrome')}`,'about:blank'],{windowsHide:true,stdio:'ignore'});
let socket;const delay=ms=>new Promise(r=>setTimeout(r,ms));
try{
    let port;for(let i=0;i<100;i++){try{port=Number((await readFile(join(temp,'chrome/DevToolsActivePort'),'utf8')).split('\n')[0]);break;}catch{await delay(100);}}
    const pages=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();socket=new WebSocket(pages.find(p=>p.type==='page').webSocketDebuggerUrl);await new Promise(r=>socket.addEventListener('open',r,{once:true}));
    let id=0;const pending=new Map();socket.addEventListener('message',({data})=>{const msg=JSON.parse(data),h=pending.get(msg.id);if(h){pending.delete(msg.id);msg.error?h[1](new Error(JSON.stringify(msg.error))):h[0](msg.result);}});
    const command=(method,params={})=>new Promise((resolve,reject)=>{const n=++id;pending.set(n,[resolve,reject]);socket.send(JSON.stringify({id:n,method,params}));});
    const evaluate=async expression=>{const r=await command('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw new Error(r.exceptionDetails.exception?.description||JSON.stringify(r.exceptionDetails));return r.result.value;};
    const until=async expr=>{for(let i=0;i<120;i++){if(await evaluate(expr))return;await delay(100);}throw new Error('Timeout: '+expr+' '+await evaluate('JSON.stringify({error:document.querySelector(".swal2-popup")?.textContent,errors:window.errors})'));};
    const click=selector=>evaluate(`document.querySelector(${JSON.stringify(selector)}).click()`);
    // Decode real printed letters, outlines and filled marks, including dense custom ranges.
    await command('Page.navigate',{url:origin});await until('document.querySelector("[data-answer-filter-card]")?.dataset.resultFiltersReady==="true"');
    for(const [count,choiceCount,shade]of [[40,5,0],[100,26,0],[100,5,180]]){
        const pixels=await evaluate(`(async()=>{
            const svg=await(await fetch('/sample-layout.svg?count=${count}&choices=${choiceCount}')).text(),doc=new DOMParser().parseFromString(svg,'image/svg+xml');
            const image=new Image(),url=URL.createObjectURL(new Blob([svg],{type:'image/svg+xml'}));image.src=url;await image.decode();
            const c=document.createElement('canvas');c.width=1000;c.height=1414;const ctx=c.getContext('2d');ctx.drawImage(image,0,0,1000,1414);URL.revokeObjectURL(url);
            const dots=[...doc.querySelectorAll('circle')];ctx.fillStyle='rgb(${shade},${shade},${shade})';for(const index of [0,${choiceCount},${choiceCount*2},${choiceCount*2+1},${count*choiceCount-1}]){const dot=dots[index];ctx.beginPath();ctx.arc(Number(dot.getAttribute('cx')),Number(dot.getAttribute('cy')),Number(dot.getAttribute('r'))-.6,0,Math.PI*2);ctx.fill();}
            if(${shade}){const grid=ctx.getImageData(65,430,875,820);for(let y=0;y<820;y++)for(let x=0;x<875;x++){const i=(y*875+x)*4,factor=.84+.14*x/875;for(let channel=0;channel<3;channel++)grid.data[i+channel]*=factor;}ctx.putImageData(grid,65,430);}
            return c.toDataURL(${shade}?'image/jpeg':'image/png',.85).split(',')[1];
        })()`);
        const result=fixture('/read-layout',{image:pixels,configuration:{question_count:count,choices:Array.from({length:choiceCount},(_,i)=>String.fromCharCode(65+i)),layout_version:2}});
        assert.equal(result.status,200);const detection=JSON.parse(Buffer.from(result.content,'base64').toString());
        assert.equal(detection.responses[0],'A');assert.equal(detection.responses[1],'A');assert.equal(detection.responses[2],null);assert.equal(detection.flags[3],'multiple');
        assert.equal(detection.responses[count-1],String.fromCharCode(64+choiceCount),'Last question is detected in the printed sample layout');
        assert.equal(detection.flags[4],'blank','Printed letters in empty circles are not detected as marks');
        for(let q=3;q<count-1;q++)assert.equal(detection.flags[q+1],'blank',`Unfilled Q${q+1} remains blank even with printed letters and scan shading`);
        console.log(`Printed sample ${count} questions / ${choiceCount} choices${shade?' / grey pencil, uneven lighting and JPEG':''}: actual bubble letters, blanks and multiple marks verified.`);
    }
    // Regression: a real scanned 100-question teacher key with pencil shading
    // and small print distortions used to leave 43 answers flagged as unclear.
    const alignmentModule=await readFile(join(root,'resources/js/helpers/answerSheetAlignment.js'),'utf8');
    for(const filename of ['teacher-key-pencil-scan.jpg','teacher-key-light-pencil-scan.jpg']){
    const scannedKey=await readFile(join(root,'tests/Fixtures/Images',filename));
    const scannedPixels=await evaluate(`(async()=>{
        const {findSheetCorners,alignSheet}=await import('data:text/javascript;base64,${Buffer.from(alignmentModule).toString('base64')}');
        const image=new Image();image.src='data:image/jpeg;base64,${scannedKey.toString('base64')}';await image.decode();
        const factor=Math.min(1,1600/Math.max(image.width,image.height)),canvas=document.createElement('canvas');canvas.width=Math.round(image.width*factor);canvas.height=Math.round(image.height*factor);
        const context=canvas.getContext('2d');context.drawImage(image,0,0,canvas.width,canvas.height);
        const corners=findSheetCorners(context.getImageData(0,0,canvas.width,canvas.height).data,canvas.width,canvas.height);
        return alignSheet(canvas,corners).toDataURL().split(',')[1];
    })()`);
    const scannedResult=fixture('/read-layout',{image:scannedPixels,configuration:{question_count:100,choices:['A','B','C','D','E'],layout_version:2}});
    assert.equal(scannedResult.status,200);const scannedDetection=JSON.parse(Buffer.from(scannedResult.content,'base64').toString());
    assert.equal(scannedDetection.token,'7e3fe4cd71dee9dca1c33ca7','Real scan identity remains intact');
    assert.deepEqual(scannedDetection.responses,Array.from({length:100},(_,i)=>['A','B','C','D','E','D','C','B'][i%8]),'Every answer matches the manually checked real sheet');
    assert.deepEqual(scannedDetection.flags,[]);
    assert.equal(scannedDetection.responses[3],'D','The light grey Q4 fill must be recognized');
    console.log(`Real scan ${filename}: automatic alignment, identity and all 100 pencil answers verified.`);
    }
    // The same upload path handles browse, camera, drag/drop and clipboard files.
    await evaluate(`(()=>{const input=document.getElementById('answerScanFile'),zone=document.querySelector('[data-answer-dropzone]');window.browseCalls=0;const original=input.click;input.click=()=>browseCalls++;zone.click();zone.dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true,cancelable:true}));zone.dispatchEvent(new KeyboardEvent('keydown',{key:' ',bubbles:true,cancelable:true}));input.click=original;zone.dispatchEvent(new DragEvent('dragover',{bubbles:true,cancelable:true,dataTransfer:new DataTransfer()}));})()`);
    assert.equal(await evaluate('browseCalls'),3,'Click, Enter and Space open the file picker');
    assert.equal(await evaluate('document.querySelector("[data-answer-dropzone]").classList.contains("is-dragging")'),true);
    for(const [file,message]of [
        [`new File(['text'],'wrong.txt',{type:'text/plain'})`,'JPG, PNG or WebP'],
        [`new File([new Uint8Array(10*1024*1024+1)],'large.png',{type:'image/png'})`,'10 MB'],
        [`new File(['broken image'],'broken.png',{type:'image/png'})`,'could not be opened'],
    ]){
        await evaluate(`(()=>{const d=new DataTransfer();d.items.add(${file});document.querySelector('[data-answer-dropzone]').dispatchEvent(new DragEvent('drop',{bubbles:true,cancelable:true,dataTransfer:d}));})()`);
        await until('document.querySelector(".swal2-icon-error")!==null');await click('.swal2-confirm');
        await until('document.querySelector("[data-answer-sheets]").getAttribute("aria-busy")==="false"');
        assert.ok(await evaluate(`document.getElementById('answerScanError').textContent.includes(${JSON.stringify(message)})`));
        assert.equal(await evaluate('document.querySelector("[data-answer-dropzone]").getAttribute("aria-invalid")'),'true');
        assert.equal(await evaluate('document.querySelector("[data-answer-alignment]").hidden'),true,'Invalid files never leave an uploadable previous preview');
    }
    await evaluate(`(async()=>{const c=document.createElement('canvas');c.width=800;c.height=1100;const ctx=c.getContext('2d');ctx.fillStyle='white';ctx.fillRect(0,0,c.width,c.height);window.uploadTestPhoto=new File([await new Promise(r=>c.toBlob(r))],'selected-photo.png',{type:'image/png'});})()`);
    for(const mode of ['browse','camera','paste','clipboard-items']){
        await evaluate(`(()=>{const d=new DataTransfer();d.items.add(uploadTestPhoto);if('${mode}'==='browse'||'${mode}'==='camera'){const input=document.getElementById('${mode}'==='camera'?'answerCameraFile':'answerScanFile');input.files=d.files;input.dispatchEvent(new Event('change'));}else{const event=new Event('paste',{bubbles:true,cancelable:true});Object.defineProperty(event,'clipboardData',{value:'${mode}'==='clipboard-items'?{items:[{kind:'file',getAsFile:()=>uploadTestPhoto}]}:d});document.querySelector('[data-answer-dropzone]').dispatchEvent(event);}})()`);
        await until('!document.querySelector("[data-answer-file]").hidden&&document.querySelector("[data-answer-sheets]").getAttribute("aria-busy")==="false"');
        assert.equal(await evaluate('document.querySelector("[data-answer-file-name]").textContent'),'selected-photo.png');
        assert.equal(await evaluate('document.getElementById("answerScanError").hidden'),true);
        await click('[data-answer-file-remove]');assert.equal(await evaluate('document.querySelector("[data-answer-alignment]").hidden'),true);
    }
    assert.equal(await evaluate(`(()=>{const input=document.createElement('input');document.body.append(input);const d=new DataTransfer();d.items.add(uploadTestPhoto);const e=new ClipboardEvent('paste',{bubbles:true,cancelable:true,clipboardData:d});input.dispatchEvent(e);input.remove();return e.defaultPrevented;})()`),false,'Paste into text fields is left alone');
    await evaluate(`(()=>{const input=document.getElementById('answerScanFile');input.disabled=true;const d=new DataTransfer();d.items.add(uploadTestPhoto);document.querySelector('[data-answer-dropzone]').dispatchEvent(new DragEvent('drop',{bubbles:true,cancelable:true,dataTransfer:d}));input.disabled=false;})()`);
    assert.equal(await evaluate('document.querySelector("[data-answer-alignment]").hidden'),true,'Disabled scanning ignores dropped photos');
    console.log('Photo upload: keyboard browsing, camera, file paste, clipboard items, validation, removal and disabled-state checks passed.');
    for(const width of [1440,390]){
        await command('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<500});
        await command('Page.navigate',{url:origin});await until('document.querySelector("[data-answer-filter-card]")?.dataset.resultFiltersReady==="true"');
        assert.equal(await evaluate('document.querySelector("[data-answer-filter-toggle]").getAttribute("aria-expanded")'),width<500?'false':'true');
        assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'),true,'Workspace fits viewport');
        // Create and approve new papers through the actual setup/key dialogs,
        // rather than relying only on the seeded approved paper.
        const version=width===1440?'B':'C',style=width===1440?'letters':'numbers',lastChoice=width===1440?'F':'6';
        await click('[data-answer-setup]');await until('document.querySelector("#answerSetupModal.show")!==null');await delay(600);
        await evaluate(`window.paperCreateRequests=0;window.savedSetupFetch=window.fetch;window.fetch=(url,options)=>{if(String(url).match(/\\/exams\\/\\d+\\/papers$/))paperCreateRequests++;return savedSetupFetch(url,options);};document.querySelector('[data-answer-setup-form]').requestSubmit();`);
        await until('document.querySelector(".swal2-title")?.textContent==="Paper Already Exists"');
        assert.ok(await evaluate('document.querySelector(".swal2-popup").textContent.includes("Grade 1")&&document.querySelector(".swal2-popup").textContent.includes("Paper A")'));
        await click('.swal2-confirm');await until('document.querySelector("[data-answer-sheets]").getAttribute("aria-busy")==="false"');
        assert.equal(await evaluate('paperCreateRequests'),0,'Known duplicates are blocked before submitting');
        assert.equal(await evaluate('document.querySelector("#answerSetupModal.show")!==null'),true,'Duplicate warning keeps setup open');
        await evaluate(`(()=>{const f=document.querySelector('[data-answer-setup-form]');f.elements.existing.value='1';f.elements.existing.dispatchEvent(new Event('change'));f.requestSubmit();})()`);
        await until('document.querySelector(".swal2-title")?.textContent==="Paper Already Exists"');await click('.swal2-confirm');
        await until('document.querySelector("[data-answer-sheets]").getAttribute("aria-busy")==="false"');
        assert.equal(await evaluate('paperCreateRequests'),0,'Selecting an existing paper never updates it through Add');
        await evaluate('window.fetch=window.savedSetupFetch');
        await evaluate(`(()=>{const f=document.querySelector('[data-answer-setup-form]');f.elements.existing.value='1';f.elements.existing.dispatchEvent(new Event('change'));for(const[k,v]of Object.entries({grade:1,paper_version:'${version}',question_count:20,points:5,choice_style:'${style}',choice_count:6}))f.elements[k].value=v;f.elements.choice_count.dispatchEvent(new Event('input'));})()`);
        assert.ok(await evaluate(`document.querySelector('[data-answer-choice-preview]').textContent.endsWith('${lastChoice}')`));
        await evaluate(`document.querySelector('[data-answer-setup-form]').requestSubmit()`);
        await until('document.querySelector(".swal2-icon-success")!==null');await click('.swal2-confirm');
        await until(`document.querySelector('[data-answer-filter-card]')?.dataset.resultFiltersReady==='true'&&JSON.parse(document.getElementById('answerSheetData').textContent).papers.find(p=>p.paper_version==='${version}')?.id===Number(document.getElementById('answerPaper').value)`);
        await click('[data-answer-key]');await until('document.querySelector("#answerKeyModal.show")!==null');await delay(600);
        assert.equal(await evaluate('document.querySelectorAll("[data-answer-key-grid] select").length'),20);
        assert.deepEqual(await evaluate('[...document.querySelector("[data-answer-key-grid] select").options].map(o=>o.value)'),['',...(style==='letters'?['A','B','C','D','E','F']:['1','2','3','4','5','6'])]);
        assert.equal(await evaluate('document.querySelector("[data-answer-key-subject]").textContent'),'Khmer');
        assert.ok(await evaluate('document.querySelector("[data-answer-key-status]").textContent.includes("Grade 1")'));
        assert.equal(await evaluate('document.querySelectorAll("[data-question-score]").length'),20);
        // Print a teacher key and upload real filled pixels without saving or approving automatically.
        await click('#answerKeyModal [data-teacher-key-print]');await until('window.printUrl?.includes("/teacher-key-print/")');
        const teacherUrl=await evaluate('window.printUrl');
        const teacherHtml=await(await fetch(origin+teacherUrl)).text();assert.ok(teacherHtml.includes('ANSWER KEY')&&teacherHtml.includes('Teacher Name:'));
        await click('[data-teacher-key-open]');await until('document.querySelector("#teacherKeyScanModal.show")!==null');await delay(600);
        assert.ok(await evaluate(`document.querySelector('[data-teacher-key-heading]').textContent.includes('Paper ${version}')`));
        await writeFile(join(root,`storage/app/testing/teacher-key-dropzone-${width}.png`),Buffer.from((await command('Page.captureScreenshot',{format:'png'})).data,'base64'));
        await evaluate(`(()=>{const input=document.getElementById('teacherKeyFile'),original=input.click,zone=document.querySelector('[data-teacher-key-dropzone]');window.teacherBrowseCalls=0;input.click=()=>teacherBrowseCalls++;zone.click();zone.dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true,cancelable:true}));zone.dispatchEvent(new KeyboardEvent('keydown',{key:' ',bubbles:true,cancelable:true}));input.click=original;zone.dispatchEvent(new DragEvent('dragover',{bubbles:true,cancelable:true,dataTransfer:new DataTransfer()}));})()`);
        assert.equal(await evaluate('teacherBrowseCalls'),3,'Teacher browse works with mouse, Enter and Space');
        assert.equal(await evaluate('document.querySelector("[data-teacher-key-dropzone]").classList.contains("is-dragging")'),true);
        for(const [files,message]of [
            [`[new File(['text'],'wrong.txt',{type:'text/plain'})]`,'JPG, PNG or WebP'],
            [`[new File([new Uint8Array(10*1024*1024+1)],'large.png',{type:'image/png'})]`,'10 MB'],
            [`[new File(['broken'],'broken.png',{type:'image/png'})]`,'could not be opened'],
            [`[new File(['one'],'one.png',{type:'image/png'}),new File(['two'],'two.png',{type:'image/png'})]`,'one teacher answer key'],
        ]){
            await evaluate(`(()=>{const d=new DataTransfer();${files}.forEach(f=>d.items.add(f));document.querySelector('[data-teacher-key-dropzone]').dispatchEvent(new DragEvent('drop',{bubbles:true,cancelable:true,dataTransfer:d}));})()`);
            await until('document.querySelector(".swal2-icon-error")!==null');await click('.swal2-confirm');
            await until('document.querySelector("[data-answer-sheets]").getAttribute("aria-busy")==="false"');
            assert.ok(await evaluate(`document.getElementById('teacherKeyPhotoError').textContent.includes(${JSON.stringify(message)})`));
            assert.equal(await evaluate('document.querySelector("[data-teacher-key-dropzone]").getAttribute("aria-invalid")'),'true');
            assert.equal(await evaluate('document.querySelector("[data-teacher-alignment]").hidden'),true);
            assert.equal(await evaluate('document.querySelector("[data-teacher-key-upload]").disabled'),true);
            assert.equal(await evaluate('document.querySelector("[data-teacher-key-use]").disabled'),true);
        }
        await evaluate(`(async()=>{const c=document.createElement('canvas');c.width=800;c.height=1100;window.teacherTestPhoto=new File([await new Promise(r=>c.toBlob(r))],'selected-teacher-key.png',{type:'image/png'});})()`);
        for(const mode of ['browse','camera','clipboard-items']){
            await evaluate(`(()=>{const d=new DataTransfer();d.items.add(teacherTestPhoto);if('${mode}'==='clipboard-items'){const event=new Event('paste',{bubbles:true,cancelable:true});Object.defineProperty(event,'clipboardData',{value:{items:[{kind:'file',getAsFile:()=>teacherTestPhoto}]}});document.querySelector('[data-teacher-key-dropzone]').dispatchEvent(event);}else{const field=document.getElementById('${mode}'==='camera'?'teacherKeyCamera':'teacherKeyFile');field.files=d.files;field.dispatchEvent(new Event('change'));}})()`);
            await until('!document.querySelector("[data-teacher-key-file]").hidden&&document.querySelector("[data-answer-sheets]").getAttribute("aria-busy")==="false"');
            assert.equal(await evaluate('document.querySelector("[data-teacher-key-file-name]").textContent'),'selected-teacher-key.png');
            assert.ok(await evaluate('document.querySelector("[data-teacher-key-file-size]").textContent.includes("KB")'));
            await click('[data-teacher-key-file-remove]');
            assert.equal(await evaluate('document.querySelector("[data-teacher-alignment]").hidden'),true);
            assert.equal(await evaluate('document.querySelector("[data-teacher-key-file]").hidden'),true);
            assert.equal(await evaluate('document.querySelector("[data-teacher-key-upload]").disabled'),true);
        }
        await evaluate(`(async()=>{
            const html=await(await fetch('${teacherUrl}')).text(),doc=new DOMParser().parseFromString(html,'text/html'),svg=doc.querySelector('svg');
            const circles=[...svg.querySelectorAll('circle')];circles.forEach((dot,i)=>{const q=Math.floor(i/6),choice=i%6,shade=160+(q%4)*8;if((q!==2&&choice===5)||(q===7&&choice===0))dot.setAttribute('fill','rgb('+shade+','+shade+','+shade+')');});
            const im=new Image(),url=URL.createObjectURL(new Blob([new XMLSerializer().serializeToString(svg)],{type:'image/svg+xml'}));im.src=url;await im.decode();
            const canvas=document.createElement('canvas');canvas.width=1200;canvas.height=1600;const ctx=canvas.getContext('2d');ctx.fillStyle='white';ctx.fillRect(0,0,1200,1600);ctx.drawImage(im,80,90,1000,1414);URL.revokeObjectURL(url);
            const blob=await new Promise(r=>canvas.toBlob(r)),transfer=new DataTransfer();transfer.items.add(new File([blob],'teacher-key.png',{type:'image/png'}));
            document.querySelector('[data-teacher-key-dropzone]').dispatchEvent(${width===1440?"new DragEvent('drop',{bubbles:true,cancelable:true,dataTransfer:transfer})":"new ClipboardEvent('paste',{bubbles:true,cancelable:true,clipboardData:transfer})"});
        })()`);
        await until('document.querySelector("[data-teacher-aligned]").getAttribute("src")?.startsWith("data:")');
        assert.equal(await evaluate('document.querySelector("[data-teacher-key-file-name]").textContent'),'teacher-key.png');
        assert.equal(await evaluate('document.getElementById("teacherKeyPhotoError").hidden'),true);
        assert.equal(await evaluate('document.querySelectorAll("[data-answer-queue-id]").length'),0,'Teacher drops and pastes do not enter the student queue');
        await click('[data-teacher-key-upload]');await until('!document.querySelector("[data-teacher-detected-status]").hidden');
        assert.ok(await evaluate('document.querySelector("[data-teacher-detected-status]").textContent.includes("2 need manual review")'));
        await writeFile(join(root,`storage/app/testing/teacher-key-scan-${width}.png`),Buffer.from((await command('Page.captureScreenshot',{format:'png'})).data,'base64'));
        await click('[data-teacher-key-use]');await until('document.querySelector(".swal2-title")?.textContent==="Use detected answers?"');await click('.swal2-confirm');
        await until('document.querySelector("#answerKeyModal.show")!==null&&!document.querySelector("#teacherKeyScanModal.show")');await delay(600);
        assert.equal(await evaluate('document.querySelector("[data-answer-key-grid] select").value'),lastChoice);
        assert.equal(await evaluate('document.querySelectorAll("[data-answer-key-grid] select")[2].value'),'');
        assert.equal(await evaluate('document.querySelectorAll("[data-answer-key-grid] select")[7].value'),'');
        assert.equal(await evaluate('document.querySelectorAll("[data-answer-key-invalid]").length'),2);
        assert.ok(await evaluate('document.querySelector("#answerKeyError8").textContent.includes("Multiple bubbles")'));
        assert.deepEqual(await evaluate('[...document.querySelectorAll("[data-question-score]")].map(i=>Number(i.value))'),Array(20).fill(5),'Scan keeps question scores');
        assert.equal(await evaluate('JSON.parse(document.querySelector("#answerSheetData").textContent).papers.find(p=>p.id===Number(document.querySelector("#answerPaper").value)).answers'),null,'Scan has not saved a key');
        await writeFile(join(root,`storage/app/testing/teacher-key-import-${width}.png`),Buffer.from((await command('Page.captureScreenshot',{format:'png'})).data,'base64'));
        await evaluate('window.printUrl=undefined');
        await evaluate(`document.getElementById('answerBulkScore').value='2'`);await click('[data-answer-fill-scores]');
        assert.ok(await evaluate('document.querySelector("[data-answer-key-total]").textContent.includes("40 / 100")'));
        await evaluate(`const bulk=document.getElementById('answerBulkScore');bulk.value='custom';bulk.dispatchEvent(new Event('change'));document.getElementById('answerCustomScore').value='5'`);
        await click('[data-answer-fill-scores]');
        await evaluate(`(()=>{const inputs=document.querySelectorAll('[data-question-score]');inputs[0].value=1;inputs[1].value=2;inputs[2].value=3;inputs[19].value=14;inputs[0].dispatchEvent(new Event('input',{bubbles:true}));})()`);
        assert.ok(await evaluate('document.querySelector("[data-answer-key-total]").textContent.includes("100 / 100")'));
        await evaluate(`document.querySelectorAll('[data-answer-key-grid] select').forEach((s,i)=>s.value=[1,6].includes(i)?'':'${lastChoice}')`);
        await click('[data-answer-key-approve]');await until('document.querySelector(".swal2-title")?.textContent==="Incomplete Answer Key"');
        assert.ok(await evaluate('document.querySelector(".swal2-popup").textContent.includes("Q2, Q7")'));
        assert.equal(await evaluate('document.querySelectorAll("[data-answer-key-grid] select.is-invalid[aria-invalid=true]").length'),2);
        assert.equal(await evaluate('document.querySelector("[data-answer-key-errors]").hidden'),false);
        await click('.swal2-confirm');await until('!document.querySelector(".swal2-popup")&&document.querySelector("[data-answer-sheets]").getAttribute("aria-busy")==="false"');
        assert.equal(await evaluate('document.activeElement.dataset.question'),'1','Focus moves to the first blank answer');
        assert.equal(await evaluate('document.querySelectorAll("[data-answer-key-invalid] .answer-key-field-error:not([hidden])").length'),2,'Each blank keeps its own message after the alert closes');
        await writeFile(join(root,`storage/app/testing/answer-key-blank-${width}.png`),Buffer.from((await command('Page.captureScreenshot',{format:'png'})).data,'base64'));
        await evaluate(`(()=>{const s=document.querySelectorAll('[data-answer-key-grid] select')[1];s.value='${lastChoice}';s.dispatchEvent(new Event('change',{bubbles:true}));})()`);
        assert.equal(await evaluate('document.querySelectorAll("[data-answer-key-grid] select.is-invalid").length'),1);
        assert.ok(await evaluate('document.querySelector("[data-answer-key-errors]").textContent.startsWith("Blank answers: Q7." )'));
        await evaluate(`(()=>{const s=document.querySelectorAll('[data-answer-key-grid] select')[6];s.value='${lastChoice}';s.dispatchEvent(new Event('change',{bubbles:true}));})()`);
        assert.equal(await evaluate('document.querySelectorAll("[data-answer-key-grid] select.is-invalid").length'),0);
        assert.equal(await evaluate('document.querySelector("[data-answer-key-errors]").hidden'),true);
        await evaluate(`document.querySelectorAll('[data-answer-key-grid] select').forEach(s=>s.value='${lastChoice}')`);
        await click('[data-answer-key-approve]');await until('document.querySelector(".swal2-icon-error")!==null');
        assert.ok(await evaluate('document.querySelector(".swal2-popup").textContent.includes("Save the draft")'));
        await click('.swal2-confirm');await until('document.querySelector("[data-answer-sheets]").getAttribute("aria-busy")==="false"');
        await click('[data-answer-key-save]');await until('document.querySelector(".swal2-icon-success")!==null');await click('.swal2-confirm');
        await until('document.querySelector("[data-answer-sheets]").getAttribute("aria-busy")==="false"');
        await click('[data-answer-key-approve]');await until('document.querySelector(".swal2-title")?.textContent==="Approve this answer key?"');await click('.swal2-confirm');
        await until('document.querySelector(".swal2-icon-success")!==null');await click('.swal2-confirm');
        await until(`document.querySelector('[data-answer-filter-card]')?.dataset.resultFiltersReady==='true'&&JSON.parse(document.getElementById('answerSheetData').textContent).papers.find(p=>p.paper_version==='${version}')?.revision===1`);
        assert.deepEqual(await evaluate(`JSON.parse(document.getElementById('answerSheetData').textContent).papers.find(p=>p.paper_version==='${version}').approved`),Array(20).fill(lastChoice),'Approved key retains every letter/numeric answer');
        assert.deepEqual(await evaluate(`JSON.parse(document.getElementById('answerSheetData').textContent).papers.find(p=>p.paper_version==='${version}').approved_points`),[1,2,3,...Array(16).fill(5),14],'Approved key retains mixed question scores');
        await click('[data-answer-key]');await until('document.querySelector("#answerKeyModal.show")!==null');await delay(600);
        assert.equal(await evaluate('(()=>{const t=document.querySelector(".answer-key-table").parentElement;return t.scrollWidth<=t.clientWidth+1;})()'),true,'Answer and score columns fit without horizontal scrolling');
        await writeFile(join(root,`storage/app/testing/answer-key-weighted-${width}.png`),Buffer.from((await command('Page.captureScreenshot',{format:'png'})).data,'base64'));
        await click('#answerKeyModal [data-bs-dismiss]');await until('getComputedStyle(document.querySelector("#answerKeyModal")).display==="none"');
        const customId=await evaluate('Number(document.getElementById("answerPaper").value)');
        assert.equal(await evaluate(`document.querySelector('[data-answer-paper-delete="1"]').disabled`),true,'An issued sheet cannot be deleted');
        // Open and revise a linked paper directly while a different paper is selected.
        const linkedRevision=await evaluate('JSON.parse(document.getElementById("answerSheetData").textContent).papers.find(p=>p.id===1).revision');
        await click('[data-answer-paper-key="1"]');await until('document.querySelector("#answerKeyModal.show")!==null');await delay(600);
        assert.equal(await evaluate('Number(document.getElementById("answerPaper").value)'),customId,'Opening a row key preserves the workspace filter');
        assert.ok(await evaluate('document.querySelector("[data-answer-key-status]").textContent.includes("Paper A · 100 questions")'));
        assert.equal(await evaluate('document.querySelectorAll("[data-answer-key-grid] select").length'),100);
        await evaluate('(()=>{const s=document.querySelectorAll("[data-answer-key-grid] select")[99];s.value=s.value==="1"?"2":"1";})()');
        await click('[data-answer-key-save]');await until('document.querySelector(".swal2-icon-success")!==null');await click('.swal2-confirm');
        await until('document.querySelector("[data-answer-sheets]").getAttribute("aria-busy")==="false"');
        await click('[data-answer-key-approve]');await until('document.querySelector(".swal2-title")?.textContent==="Approve this answer key?"');await click('.swal2-confirm');
        await until('document.querySelector(".swal2-icon-success")!==null');await click('.swal2-confirm');
        await until(`document.querySelector('[data-answer-filter-card]')?.dataset.resultFiltersReady==='true'&&JSON.parse(document.getElementById('answerSheetData').textContent).papers.find(p=>p.id===1)?.revision===${linkedRevision+1}`);
        assert.equal(await evaluate('Number(document.getElementById("answerPaper").value)'),1,'Approval selects the revised paper');
        assert.equal(await evaluate(`JSON.parse(document.getElementById('answerSheetData').textContent).papers.find(p=>p.id===${customId}).revision`),1,'Another paper key is unchanged');
        await click('[data-answer-paper-link="1"]');await until('document.querySelector(".swal2-title")?.textContent==="Link Students"');await click('.swal2-confirm');
        await until('document.querySelector(".swal2-icon-success")!==null');await click('.swal2-confirm');
        await until('document.querySelector("[data-answer-filter-card]")?.dataset.resultFiltersReady==="true"&&!document.querySelector(".swal2-popup")');
        assert.equal(await evaluate('JSON.parse(document.getElementById("answerSheetData").textContent).papers.find(p=>p.id===1).linked_sheets'),2,'Link Students links this grade without opening print');
        assert.equal(await evaluate('window.printUrl===undefined'),true,'Link Students never opens print preview');
        await click('[data-answer-paper-link="1"]');await until('document.querySelector(".swal2-title")?.textContent==="Link Students"');await click('.swal2-confirm');
        await until('document.querySelector(".swal2-icon-success")!==null');
        assert.ok(await evaluate('document.querySelector(".swal2-popup").textContent.includes("0 new link(s) added")'));
        await click('.swal2-confirm');await until('document.querySelector("[data-answer-filter-card]")?.dataset.resultFiltersReady==="true"&&!document.querySelector(".swal2-popup")');
        await command('Page.navigate',{url:`${origin}/?paper_id=${customId}`});await until('document.querySelector("[data-answer-filter-card]")?.dataset.resultFiltersReady==="true"');
        await click(`[data-answer-paper-view="${customId}"]`);await until('document.querySelector("#answerPaperViewModal.show")!==null');await delay(600);
        assert.ok(await evaluate('document.querySelector("[data-answer-paper-view-heading]").textContent.includes("Khmer · Grade 1")'));
        assert.equal(await evaluate('document.querySelectorAll("[data-answer-paper-view-rows] tr").length'),20);
        await click('#answerPaperViewModal [data-bs-dismiss]');await until('getComputedStyle(document.querySelector("#answerPaperViewModal")).display==="none"');
        await click(`[data-answer-paper-link="${customId}"]`);await until('document.querySelector(".swal2-title")?.textContent==="Link Students"');await click('.swal2-confirm');
        await until('document.querySelector(".swal2-icon-success")!==null');await click('.swal2-confirm');
        await until('document.querySelector("[data-answer-filter-card]")?.dataset.resultFiltersReady==="true"&&!document.querySelector(".swal2-popup")');
        assert.equal(await evaluate(`document.querySelector('[data-answer-paper-edit="${customId}"]').disabled`),true,'Linked paper setup is protected');
        await click(`[data-answer-paper-unlink="${customId}"]`);await until('document.querySelector(".swal2-title")?.textContent==="Unlink Students"');await click('.swal2-cancel');
        await until('document.querySelector("[data-answer-sheets]").getAttribute("aria-busy")==="false"');
        assert.equal(await evaluate(`JSON.parse(document.getElementById('answerSheetData').textContent).papers.find(p=>p.id===${customId}).linked_sheets`),2,'Cancelling preserves student links');
        await click(`[data-answer-paper-unlink="${customId}"]`);await until('document.querySelector(".swal2-title")?.textContent==="Unlink Students"');await click('.swal2-confirm');
        await until('document.querySelector(".swal2-icon-success")!==null');await click('.swal2-confirm');
        await until('document.querySelector("[data-answer-filter-card]")?.dataset.resultFiltersReady==="true"&&!document.querySelector(".swal2-popup")');
        assert.equal(await evaluate(`JSON.parse(document.getElementById('answerSheetData').textContent).papers.find(p=>p.id===${customId}).linked_sheets`),0,'Unlink Students refreshes the count');
        assert.equal(await evaluate(`document.querySelector('[data-answer-paper-edit="${customId}"]').disabled`),false,'Unlinking enables paper setup editing');
        assert.equal(await evaluate(`document.querySelector('[data-answer-paper-unlink="${customId}"]').disabled`),true,'An unlinked paper cannot be unlinked again');
        await click(`[data-answer-paper-edit="${customId}"]`);await until('document.querySelector("#answerSetupModal.show")!==null');await delay(600);
        assert.equal(await evaluate('document.querySelector("[data-answer-setup-form]").elements.existing.disabled'),true);
        await evaluate(`(()=>{const f=document.querySelector('[data-answer-setup-form]');f.elements.paper_version.value='${version}-EDIT';f.elements.question_count.value=25;f.elements.points.value=4;f.requestSubmit();})()`);
        await until('document.querySelector(".swal2-title")?.textContent==="Update this answer sheet?"');await click('.swal2-confirm');
        await until('document.querySelector(".swal2-icon-success")!==null');await click('.swal2-confirm');
        await until(`document.querySelector('[data-answer-filter-card]')?.dataset.resultFiltersReady==='true'&&JSON.parse(document.getElementById('answerSheetData').textContent).papers.find(p=>p.id===${customId})?.paper_version==='${version}-EDIT'`);
        assert.equal(await evaluate(`JSON.parse(document.getElementById('answerSheetData').textContent).papers.find(p=>p.id===${customId}).revision`),null,'Editing setup requires a fresh key approval');
        await writeFile(join(root,`storage/app/testing/answer-sheet-list-${width}.png`),Buffer.from((await command('Page.captureScreenshot',{format:'png'})).data,'base64'));
        await click(`[data-answer-paper-delete="${customId}"]`);await until('document.querySelector(".swal2-title")?.textContent==="Delete this answer sheet?"');await click('.swal2-cancel');
        await until('document.querySelector("[data-answer-sheets]").getAttribute("aria-busy")==="false"');
        assert.equal(await evaluate(`document.querySelector('[data-answer-paper-row="${customId}"]')!==null`),true,'Cancelling keeps the paper');
        await click(`[data-answer-paper-delete="${customId}"]`);await until('document.querySelector(".swal2-title")?.textContent==="Delete this answer sheet?"');await click('.swal2-confirm');
        await until('document.querySelector(".swal2-icon-success")!==null');await click('.swal2-confirm');
        await until(`document.querySelector('[data-answer-filter-card]')?.dataset.resultFiltersReady==='true'&&!document.querySelector('[data-answer-paper-row="${customId}"]')`);
        assert.equal(await evaluate('Number(document.getElementById("answerPaper").value)'),1,'Deleting the selected paper returns to the available paper');
        await command('Page.navigate',{url:origin});await until('document.querySelector("[data-answer-filter-card]")?.dataset.resultFiltersReady==="true"');
        await click('[data-answer-key]');await until('document.querySelector("#answerKeyModal.show")!==null');await delay(600);
        assert.equal(await evaluate('document.querySelectorAll("[data-answer-key-grid] select").length'),100);
        assert.deepEqual(await evaluate('[...document.querySelector("[data-answer-key-grid] select").options].map(o=>o.value)'),['','1','2','3','4','5']);
        await writeFile(join(root,`storage/app/testing/answer-key-${width}.png`),Buffer.from((await command('Page.captureScreenshot',{format:'png'})).data,'base64'));
        await click('#answerKeyModal [data-bs-dismiss]');await until('getComputedStyle(document.querySelector("#answerKeyModal")).display==="none"');
        await click('[data-answer-print]');await delay(600);
        assert.equal(await evaluate('document.querySelector("[data-answer-print-form] select[name=scope]").value'),'all');
        if(width===1440){
            await click('[data-answer-print-form] button[type=submit]');await until('window.printUrl?.includes("/print/")');
            const printUrl=await evaluate('printUrl'),html=await(await fetch(origin+printUrl)).text();
            assert.equal((html.match(/class="answer-print-page"/g)||[]).length,2,'All room students are printed: '+html.slice(0,500));
            assert.ok(html.includes('>100<')&&html.includes('Print / Save as PDF'),'Actual print includes every question');
            await command('Emulation.setDeviceMetricsOverride',{width:1000,height:1400,deviceScaleFactor:1,mobile:false});
            await command('Page.navigate',{url:origin+printUrl});await until('document.querySelector(".answer-print-page svg")!==null');
            await writeFile(join(root,'storage/app/testing/answer-sheet-a4.png'),Buffer.from((await command('Page.captureScreenshot',{format:'png'})).data,'base64'));
            await command('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
            await command('Page.navigate',{url:origin});await until('document.querySelector("[data-answer-filter-card]")?.dataset.resultFiltersReady==="true"');
        }else{
            await click('#answerPrintModal [data-bs-dismiss]');await until('getComputedStyle(document.querySelector("#answerPrintModal")).display==="none"');
        }
        // Rasterize the real printable SVG, mark actual answer circles, then
        // simulate a phone photo with margins to exercise automatic alignment.
        await evaluate(`(async()=>{
            const svg=await(await fetch('/sample.svg')).text(),url=URL.createObjectURL(new Blob([svg],{type:'image/svg+xml'}));
            const im=new Image();im.src=url;await im.decode();const c=document.createElement('canvas');c.width=1000;c.height=1414;const ctx=c.getContext('2d');ctx.drawImage(im,0,0,1000,1414);URL.revokeObjectURL(url);
            const svgDoc=new DOMParser().parseFromString(svg,'image/svg+xml'),dots=[...svgDoc.querySelectorAll('circle')];
            ctx.fillStyle='rgb(220,220,220)';for(const index of [0,5,10,11]){const dot=dots[index];ctx.beginPath();ctx.arc(Number(dot.getAttribute('cx')),Number(dot.getAttribute('cy')),Number(dot.getAttribute('r'))-.6,0,Math.PI*2);ctx.fill();}
            ctx.fillRect(500,1200,${width===1440?1:2},1);
            const photo=document.createElement('canvas');photo.width=1200;photo.height=1600;photo.getContext('2d').fillStyle='white';photo.getContext('2d').fillRect(0,0,1200,1600);photo.getContext('2d').drawImage(c,80,90);
            const blob=await new Promise(r=>photo.toBlob(r)),d=new DataTransfer();d.items.add(new File([blob],'phone-sheet.png',{type:'image/png'}));
            document.querySelector('[data-answer-dropzone]').dispatchEvent(${width===1440?"new DragEvent('drop',{bubbles:true,cancelable:true,dataTransfer:d})":"new ClipboardEvent('paste',{bubbles:true,cancelable:true,clipboardData:d})"});
        })()`);
        await until('document.querySelector("[data-answer-aligned]").getAttribute("src")?.startsWith("data:")');
        assert.equal(await evaluate('document.querySelector("[data-answer-align-status]").textContent.includes("Check the aligned preview")'),true);
        assert.equal(await evaluate('document.querySelector("[data-answer-file-name]").textContent'),'phone-sheet.png');
        await evaluate('document.querySelector("[data-answer-dropzone]").scrollIntoView({block:"start"})');
        await writeFile(join(root,`storage/app/testing/answer-alignment-${width}.png`),Buffer.from((await command('Page.captureScreenshot',{format:'png'})).data,'base64'));
        await click('[data-answer-upload]');await until('document.querySelector(".swal2-icon-success")!==null');await click('.swal2-confirm');
        await until('document.querySelector("[data-answer-filter-card]")?.dataset.resultFiltersReady==="true"&&!document.querySelector(".swal2-popup")');
        await click('[data-answer-review]');await until('document.querySelector("#answerReviewModal.show")!==null');
        assert.equal(await evaluate('document.querySelector("[data-answer-response-grid] select").value'),'1');
        assert.equal(await evaluate('document.querySelectorAll("[data-answer-response-grid] select")[1].value'),'1','Light grey student bubbles reach the review form');
        assert.equal(await evaluate('document.querySelectorAll("[data-answer-response-grid] select")[2].value'),'','Multiple marks remain blank');
        assert.equal(await evaluate('document.querySelectorAll("[data-answer-response-grid] .answer-flagged").length'),98);
        await writeFile(join(root,`storage/app/testing/answer-review-${width}.png`),Buffer.from((await command('Page.captureScreenshot',{format:'png'})).data,'base64'));
        if(width===1440){
            await evaluate(`document.querySelector('[data-answer-review-form] input[name=confirmed]').checked=true;`);
            await click('[data-answer-verify]');await until('document.querySelector(".swal2-icon-success")!==null');await click('.swal2-confirm');
            await until('document.querySelector("[data-answer-filter-card]")?.dataset.resultFiltersReady==="true"&&!document.querySelector(".swal2-popup")');
            const state=JSON.parse(Buffer.from(fixture('/state').content,'base64').toString());assert.equal(state.scans[0].score,2);assert.equal(state.scans[0].status,'accepted');
            // Allow a second genuinely different image on mobile.
            await command('Page.navigate',{url:origin});await until('document.querySelector("[data-answer-filter-card]")?.dataset.resultFiltersReady==="true"');
        }
        assert.deepEqual(await evaluate('window.errors'),[]);console.log(`${width}px: list View/Edit/Delete, linked protection, paper setup, key approval, printed-sheet alignment, upload and review passed.`);
    }
    await command('Browser.close');
}finally{socket?.close();browser.kill();server.closeAllConnections();await new Promise(r=>server.close(r));}
