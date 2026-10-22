import assert from 'node:assert/strict';
import {createServer} from 'node:http';
import {spawn,execFileSync} from 'node:child_process';
import {readFile,writeFile,mkdtemp,mkdir} from 'node:fs/promises';
import {join,resolve} from 'node:path';
import {tmpdir} from 'node:os';
import {build} from 'vite';
const root=resolve(import.meta.dirname,'../..'),php='C:/laragon/bin/php/php-8.4.25-Win32-vs17-x64/php.exe',chrome='C:/Program Files/Google/Chrome/Application/chrome.exe';
const files=new Map(),markup=new Map();
const modes={standalone:{input:'photoEditorInput',modal:'photoEditorModal'},student:{input:'student_photo',modal:'studentPhotoCropModal'},staff:{input:'staff-photo',modal:'hrmStaffPhotoCropModal'}};
await mkdir(join(root,'storage/app/testing'),{recursive:true});
for(const mode of Object.keys(modes)){
    markup.set(mode,execFileSync(php,[join(root,'tests/browser/photo-retouch-fixture.php'),mode],{encoding:'utf8'}));
    const entry=join(root,`storage/app/testing/retouch-${mode}-entry.mjs`);
    const js=mode==='standalone'?`import '../../../resources/js/photoEditor.js';`:
        mode==='student'?`import {createStudentPhotoEditor} from '../../../resources/js/helpers/studentPhotoEditor.js';createStudentPhotoEditor({bootstrap:{Modal},showError:(...a)=>window.alerts.push(a),showSuccess:()=>{}});Modal.getOrCreateInstance(document.getElementById('enrollmentModal')).show();`:
        `import {setupStaffPhotoUpload} from '../../../resources/js/helpers/staffPhotoUpload.js';setupStaffPhotoUpload(document.querySelector('form'));Modal.getOrCreateInstance(document.getElementById('staffModal')).show();`;
    await writeFile(entry,`import {Modal} from 'bootstrap';window.alerts=[];${js}window.ready=true;`);
    const bundle=await build({root,configFile:false,logLevel:'silent',build:{write:false,lib:{entry,formats:['es'],fileName:()=>`${mode}.js`,cssFileName:mode}}});
    for(const output of Array.isArray(bundle)?bundle:[bundle])for(const f of output.output)files.set('/'+f.fileName,f.type==='chunk'?f.code:f.source);
}
const source=execFileSync(php,['-r',`$i=imagecreatetruecolor(600,800);imagefill($i,0,0,imagecolorallocate($i,180,150,130));imagefilledrectangle($i,0,450,599,799,imagecolorallocate($i,210,210,215));imagefilledellipse($i,300,240,10,10,imagecolorallocate($i,30,30,30));imagefilledrectangle($i,260,550,310,580,imagecolorallocate($i,40,40,45));imagepng($i);`]);
const cutout=execFileSync(php,['-r',`$i=imagecreatefromstring(stream_get_contents(STDIN));imagealphablending($i,false);imagesavealpha($i,true);$a=imagecolorallocatealpha($i,0,0,0,127);imagefilledrectangle($i,0,0,30,799,$a);imagepng($i);`],{input:source});
let backgroundCalls=0;
const server=createServer(async(req,res)=>{
    const u=new URL(req.url,'http://localhost'),p=u.pathname;
    if(files.has(p)){res.setHeader('Content-Type',p.endsWith('.js')?'text/javascript':'text/css');res.end(files.get(p));return;}
    if(p.startsWith('/node_modules/')||p.startsWith('/resources/')){res.setHeader('Content-Type',p.endsWith('.css')?'text/css':'font/woff2');res.end(await readFile(join(root,p)));return;}
    if(p==='/portrait.png'){res.setHeader('Content-Type','image/png');res.end(source);return;}
    if(p.endsWith('/size')){for await(const c of req){}res.setHeader('Content-Type','application/json');res.end(JSON.stringify({bytes:12000,jpg_bytes:24000,width:600,height:800,webp_available:true}));return;}
    if(p.endsWith('/background')){for await(const c of req){}backgroundCalls++;res.setHeader('Content-Type','image/png');res.end(cutout);return;}
    const mode=u.searchParams.get('mode')||'standalone';res.setHeader('Content-Type','text/html; charset=utf-8');
    res.end(`<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="fixture"><link rel="stylesheet" href="/node_modules/@tabler/core/dist/css/tabler.min.css"><link rel="stylesheet" href="/node_modules/@tabler/icons-webfont/dist/tabler-icons.min.css"><link rel="stylesheet" href="/resources/css/photo-editor.css"><link rel="stylesheet" href="/resources/css/pages/photo-editor.css"><link rel="stylesheet" href="/${mode}.css"></head><body>${markup.get(mode)}<script>window.errors=[];addEventListener('error',e=>errors.push(e.message));addEventListener('unhandledrejection',e=>errors.push(String(e.reason)));</script><script type="module" src="/${mode}.js"></script></body></html>`);
});
await new Promise(r=>server.listen(0,'127.0.0.1',r));
const profile=await mkdtemp(join(tmpdir(),'wis-retouch-')),browser=spawn(chrome,['--headless=new','--disable-gpu','--no-sandbox','--no-first-run','--remote-debugging-port=0',`--user-data-dir=${profile}`,'about:blank'],{windowsHide:true,stdio:'ignore'});
let socket;const delay=ms=>new Promise(r=>setTimeout(r,ms));
try{
    let port;for(let i=0;i<100;i++){try{port=Number((await readFile(join(profile,'DevToolsActivePort'),'utf8')).split('\n')[0]);break;}catch{await delay(100);}}
    assert.ok(port);const pages=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();socket=new WebSocket(pages.find(p=>p.type==='page').webSocketDebuggerUrl);await new Promise(r=>socket.addEventListener('open',r,{once:true}));
    let id=0;const pending=new Map();socket.addEventListener('message',({data})=>{const m=JSON.parse(data),h=pending.get(m.id);if(!h)return;pending.delete(m.id);m.error?h[1](new Error(JSON.stringify(m.error))):h[0](m.result);});
    const command=(method,params={})=>new Promise((resolve,reject)=>{const n=++id;pending.set(n,[resolve,reject]);socket.send(JSON.stringify({id:n,method,params}));});
    const evaluate=async expression=>{const r=await command('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw new Error(r.exceptionDetails.exception?.description||JSON.stringify(r.exceptionDetails));return r.result.value;};
    const until=async expr=>{for(let i=0;i<160;i++){if(await evaluate(expr))return;await delay(50);}throw new Error('Timeout: '+expr+'; '+await evaluate('JSON.stringify(errors)'));};
    const click=selector=>evaluate(`document.querySelector(${JSON.stringify(selector)}).click()`);
    for(const [mode,config] of Object.entries(modes))for(const width of [1280,390]){
        await command('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<500});
        await command('Page.navigate',{url:`http://127.0.0.1:${server.address().port}/?mode=${mode}`});await until('window.ready===true');await delay(400);
        await evaluate(`(async()=>{const b=await(await fetch('/portrait.png')).blob(),d=new DataTransfer();d.items.add(new File([b],'portrait.png',{type:'image/png'}));const i=document.getElementById('${config.input}');i.files=d.files;i.dispatchEvent(new Event('change'));})()`);
        await until(`document.getElementById('${config.modal}').classList.contains('show')`);await delay(400);
        await evaluate(`window.set=(id,value,event='input')=>{const e=document.getElementById(id);e.value=value;e.dispatchEvent(new Event(event));};window.rotation=0;window.view=(x,y)=>{const c=document.querySelector('canvas'),a=rotation*Math.PI/180,f=Math.max((c.width*Math.abs(Math.cos(a))+c.height*Math.abs(Math.sin(a)))/600,(c.width*Math.abs(Math.sin(a))+c.height*Math.abs(Math.cos(a)))/800)*Number(document.getElementById('studentPhotoZoom').value);return{x:c.width/2+(x-300)*f*Math.cos(a)-(y-400)*f*Math.sin(a),y:c.height/2+(x-300)*f*Math.sin(a)+(y-400)*f*Math.cos(a)};};window.pixel=(x,y)=>{const p=view(x,y);return[...document.querySelector('canvas').getContext('2d').getImageData(Math.round(p.x),Math.round(p.y),1,1).data];};window.point=(name,x,y,extra={})=>{const c=document.querySelector('canvas'),r=c.getBoundingClientRect(),p=view(x,y);c.dispatchEvent(new PointerEvent(name,{clientX:r.left+p.x/c.width*r.width,clientY:r.top+p.y/c.height*r.height,pointerId:1,pointerType:${width<500?"'touch'":"'mouse'"},bubbles:true,...extra}));};window.tap=(x,y,extra={})=>{point('pointerdown',x,y,extra);point('pointerup',x,y);};window.originalPixels=document.querySelector('canvas').toDataURL();`);
        await evaluate(`window.fitWidth=document.querySelector('canvas').getBoundingClientRect().width;`);
        for(let n=0;n<4;n++)await click('#photoPreviewZoomIn');
        assert.equal(await evaluate('document.getElementById("photoPreviewZoomValue").textContent'),'200%');
        assert.ok(Math.abs(await evaluate('document.querySelector("canvas").getBoundingClientRect().width/fitWidth')-2)<.02,'Preview enlarges for detailed editing');
        assert.equal(await evaluate('document.querySelector("canvas").toDataURL()===originalPixels'),true,'Preview zoom leaves output pixels unchanged');
        assert.equal(await evaluate('Number(document.getElementById("studentPhotoZoom").value)'),1,'Preview zoom does not change crop zoom');
        await writeFile(join(root,`storage/app/testing/preview-zoom-${mode}-${width}.png`),Buffer.from((await command('Page.captureScreenshot',{format:'png'})).data,'base64'));
        await click('#photoPreviewPan');
        await evaluate(`window.panBefore=document.querySelector('.student-photo-crop-stage').scrollTop;tap(300,400);point('pointerdown',300,400);point('pointermove',260,360);point('pointerup',260,360);`);
        assert.ok(await evaluate("document.querySelector('.student-photo-crop-stage').scrollTop>panBefore"),'Pan scrolls the enlarged preview');
        assert.equal(await evaluate('document.querySelector("canvas").toDataURL()===originalPixels'),true,'Panning does not paint or reposition the crop');
        await click('#photoPreviewPan');
        await click('[data-photo-retouch-tool="spot"]');
        assert.equal(await evaluate('document.querySelector("[data-photo-retouch-tool=spot]").getAttribute("aria-pressed")'),'true');
        assert.equal(await evaluate('document.getElementById("photoRetouchTool").value'),'spot');
        await evaluate(`set('photoRetouchStrength',100);set('photoRetouchSize',18);tap(300,240);`);await delay(100);
        assert.ok((await evaluate('pixel(300,240)'))[0]>140,'Spot Healing removes the actual dark mark');
        assert.deepEqual(await evaluate('pixel(250,240)'),[180,150,130,255],'Outside pixels are preserved');
        await click('#photoPreviewZoomOut');
        assert.equal(await evaluate('document.getElementById("photoPreviewZoomValue").textContent'),'175%');
        await click('#photoPreviewFit');
        assert.equal(await evaluate('document.getElementById("photoPreviewZoomValue").textContent'),'100%');
        assert.equal(await evaluate('document.getElementById("photoPreviewPan").disabled'),true,'Pan is unnecessary at Fit');
        await click('#photoRetouchUndo');await delay(100);assert.equal(await evaluate("document.querySelector('canvas').toDataURL()===originalPixels"),true,'Undo restores exact pixels');
        await click('#photoRetouchRedo');await delay(100);assert.ok((await evaluate('pixel(300,240)'))[0]>140);
        await click('#studentPhotoCompare');assert.equal(await evaluate("document.querySelector('canvas').toDataURL()===originalPixels"),true,'Original comparison excludes retouch');
        assert.equal(await evaluate("document.querySelector('.photo-retouch-overlay').childElementCount"),0,'Original comparison hides markers');await click('#studentPhotoCompare');
        await click('#photoRetouchReset');await delay(100);
        await evaluate(`set('photoRetouchTool','spot','change');point('pointerdown',300,240);point('pointercancel',300,240);`);await delay(100);
        assert.equal(await evaluate("document.querySelector('canvas').toDataURL()===originalPixels"),true,'Cancelled touch stroke restores the photo');
        await click('[data-photo-retouch-tool="blur"]');
        assert.equal(await evaluate('document.querySelector("[data-photo-retouch-tool=blur]").getAttribute("aria-pressed")'),'true');
        assert.equal(await evaluate('document.getElementById("photoRetouchSource").disabled'),true,'Blur does not require a sampled source');
        await evaluate('tap(300,240)');await delay(100);
        const blurredPixel=await evaluate('pixel(300,240)');
        assert.ok(blurredPixel[0]>35&&blurredPixel[0]<180,'Blur softens the mark rather than replacing it with a clean source');
        assert.deepEqual(await evaluate('pixel(250,240)'),[180,150,130,255],'Blur leaves unpainted pixels unchanged');
        await click('#photoRetouchUndo');await delay(100);assert.equal(await evaluate("document.querySelector('canvas').toDataURL()===originalPixels"),true,'Blur Undo restores exact pixels');
        await click('#photoRetouchRedo');await delay(100);assert.deepEqual(await evaluate('pixel(300,240)'),blurredPixel,'Blur Redo restores the effect');
        await click('#photoRetouchReset');await delay(100);
        await click('[data-photo-retouch-tool="heal"]');await evaluate('tap(300,240)');
        assert.equal(await evaluate("document.querySelector('#photoRetouchStatus').textContent.includes('Set Source')"),true);
        await click('#photoRetouchSource');await evaluate('tap(350,240);tap(300,240)');await delay(100);
        assert.ok((await evaluate('pixel(300,240)'))[0]>140,'Source picking works with mouse and touch');
        await click('#photoRetouchUndo');await evaluate('tap(350,240,{altKey:true});tap(300,240)');await delay(100);
        assert.ok((await evaluate('pixel(300,240)'))[0]>140,'Alt-click sets a source');
        await click('#photoRetouchReset');
        await click('[data-photo-retouch-tool="patch"]');
        await evaluate(`point('pointerdown',250,540);for(const p of [[320,540],[320,590],[250,590],[250,540]])point('pointermove',...p);point('pointerup',250,540);point('pointerdown',280,565);point('pointermove',400,565);point('pointerup',400,565);`);await delay(150);
        assert.ok((await evaluate('pixel(280,565)'))[0]>170,'Patch replaces a larger mark with clean shirt texture');
        await click('#photoRetouchUndo');await delay(100);assert.ok((await evaluate('pixel(280,565)'))[0]<80);await click('#photoRetouchRedo');await delay(100);
        if(mode==='standalone'){
            await evaluate(`set('photoEditorSize','4.5x4.5','change');`);await delay(100);
            assert.ok((await evaluate('pixel(280,565)'))[0]>170,'Retouch stays aligned after changing photo size');
            await evaluate(`set('photoEditorSize','3x4','change');`);await delay(100);
        }
        await evaluate(`set('studentPhotoAngle',6);window.rotation=6;set('studentPhotoZoom',1.2);`);await delay(100);
        assert.ok((await evaluate('pixel(280,565)'))[0]>170,'Patch tracks rotation and zoom');
        await click('#photoRetouchReset');await evaluate(`set('photoRetouchTool','spot','change');set('photoRetouchStrength',100);tap(300,240);`);await delay(100);
        const rotatedRetouch=await evaluate('({pixel:pixel(300,240),point:view(300,240),status:document.getElementById("photoRetouchStatus").textContent,errors,canvas:[document.querySelector("canvas").width,document.querySelector("canvas").height]})');
        assert.ok(rotatedRetouch.pixel[0]>140,`Retouch maps correctly after rotation and zoom (${mode} ${width}): ${JSON.stringify(rotatedRetouch)}`);
        await click('[data-photo-background="#1976d2"]');await until(`document.getElementById('${config.modal}').getAttribute('aria-busy')==='false'`);
        assert.ok((await evaluate('pixel(300,240)'))[0]>140,'Background processing keeps retouch');
        await evaluate(`window.rotation=0;set('studentPhotoAngle',0);set('studentPhotoZoom',1);`);await delay(100);
        assert.deepEqual(await evaluate('pixel(10,400)'),[25,118,210,255],'Retouch preserves cutout transparency');
        await click('#photoRetouchUndo');await delay(100);assert.ok((await evaluate('pixel(300,240)'))[0]<80,'Undo also restores the cutout');
        await click('#photoRetouchRedo');await delay(100);assert.ok((await evaluate('pixel(300,240)'))[0]>140);
        await click('[data-photo-retouch-tool="blur"]');await evaluate('tap(260,550)');await delay(100);
        assert.deepEqual(await evaluate('pixel(10,400)'),[25,118,210,255],'Blur preserves the chosen background and cutout transparency');
        await evaluate(`set('photoRetouchTool','heal','change');`);await click('#photoRetouchSource');await evaluate('tap(350,240);');
        await evaluate(`document.querySelector('.photo-retouch-tool-buttons').scrollIntoView({block:'center'});`);await delay(100);
        assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true,'Icon buttons fit the screen');
        await writeFile(join(root,`storage/app/testing/retouch-${mode}-${width}.png`),Buffer.from((await command('Page.captureScreenshot',{format:'png'})).data,'base64'));
        await click('#photoPreviewZoomIn');
        await evaluate("window.savedPixels=document.querySelector('canvas').toDataURL()");await click('#studentPhotoCropUpload');
        await until(`!document.getElementById('${config.modal}').classList.contains('show')`);
        assert.equal(await evaluate(`new Promise(resolve=>{const r=new FileReader();r.onload=()=>resolve(r.result===window.savedPixels);r.readAsDataURL(document.getElementById('${config.input}').files[0]);})`),true,'Saved photo includes healing, excludes all source/brush/patch guides');
        assert.deepEqual(await evaluate('errors'),[]);console.log(`${mode} ${width}px: healing, patch, blur, Undo/Redo, original comparison, crop/rotation, cutout and saved pixels passed.`);
    }
    assert.equal(backgroundCalls,6);await command('Browser.close');
}finally{socket?.close();browser.kill();server.closeAllConnections();await new Promise(r=>server.close(r));}
