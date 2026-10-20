// Synthetic portraits and isolated form fixtures; no production records or sessions.
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { spawn, execFileSync } from 'node:child_process';
import { readFile, mkdtemp, mkdir, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { build } from 'vite';

const root = resolve(import.meta.dirname, '../..');
const php = process.env.CHAT_TEST_PHP || 'C:/laragon/bin/php/php-8.4.25-Win32-vs17-x64/php.exe';
const chrome = process.env.CHAT_TEST_BROWSER || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const modes = {
    staff: { editor: 'hrmStaffPhotoCropModal', parent: 'staffModal', input: 'staff-photo', zone: 'staffZone', preview: 'staffPreview', box: 'staffPreview', edit: 'staffEdit', attrs: 'data-staff-profile-form', zoneAttrs: 'data-staff-photo-dropzone', previewAttrs: 'data-staff-photo-preview', editAttrs: 'data-staff-photo-edit' },
    user: { editor: 'staffPhotoCropModal', parent: 'userModal', input: 'staff_photo', zone: 'staffPhotoDropzone', preview: 'staffPhotoPreview', box: 'staffPhotoPreviewContainer', edit: 'userPhotoEdit' },
    profile: { editor: 'profilePhotoCropModal', parent: null, input: 'profile_photo', zone: 'profilePhotoDropzone', preview: 'profilePreviewImage', box: 'profilePhotoPreview', edit: 'profilePhotoEdit' },
};
const files = new Map();
const studios = {};
for (const [mode, config] of Object.entries(modes)) {
    studios[mode] = execFileSync(php, [join(root, 'tests/browser/person-photo-fixture.php'), mode], { cwd: root, encoding: 'utf8' });
    if(mode==='staff') studios[mode] += execFileSync(php,[join(root,'tests/browser/person-photo-fixture.php'),'viewer'],{cwd:root,encoding:'utf8'});
    const entry = mode === 'staff'
        ? `import {setupStaffPhotoUpload} from '../../../resources/js/helpers/staffPhotoUpload';import {setupStaffDirectoryPhotoViewer} from '../../../resources/js/helpers/staffDirectoryPhotoViewer';setupStaffPhotoUpload(document.querySelector('form'));setupStaffDirectoryPhotoViewer();`
        : `import '../../../resources/js/${mode === 'user' ? 'userManagement' : 'profilePhoto'}.js';`;
    await mkdir(join(root,'storage/app/testing'),{recursive:true});
    const entryPath=join(root,`storage/app/testing/${mode}-studio-entry.mjs`);
    await writeFile(entryPath,`import {Modal} from 'bootstrap';window.fixtureModal=Modal;${entry} ${config.parent ? `Modal.getOrCreateInstance(document.getElementById('${config.parent}')).show();` : ''}`);
    const result = await build({
        root, configFile:false, logLevel:'silent',
        build:{write:false,lib:{entry:entryPath,formats:['es'],fileName:()=>`${mode}.js`,cssFileName:mode}},
    });
    for (const output of Array.isArray(result)?result:[result]) for (const file of output.output) files.set('/'+file.fileName,file.type==='chunk'?file.code:file.source);
}
const cutout = execFileSync(php, ['-r', '$i=imagecreatetruecolor(400,400);imagealphablending($i,false);imagesavealpha($i,true);imagefill($i,0,0,imagecolorallocatealpha($i,0,0,0,127));imagefilledellipse($i,200,200,220,340,imagecolorallocatealpha($i,200,140,100,0));imagepng($i);']);
const stored = execFileSync(php, ['-r', '$i=imagecreatetruecolor(400,400);imagefill($i,0,0,imagecolorallocate($i,176,192,208));imagefilledrectangle($i,100,50,300,350,imagecolorallocate($i,200,140,100));imagewebp($i,null,100);']);
let backgroundRequests = 0;
const server = createServer(async (req, res) => {
    const url = new URL(req.url, 'http://localhost'), path = url.pathname;
    if(path==='/photos/size') {
        for await(const chunk of req){}
        res.setHeader('Content-Type','application/json');res.end(JSON.stringify({bytes:15616,width:400,height:400}));return;
    }
    if (path.endsWith('/photo/background')) {
        for await (const chunk of req) {} backgroundRequests++;
        res.setHeader('Content-Type','image/png'); res.end(cutout); return;
    }
    if (files.has(path)) { res.setHeader('Content-Type',path.endsWith('.js') ? 'text/javascript' : 'text/css');res.end(files.get(path));return; }
    if (path === '/stored.webp') { res.setHeader('Content-Type','image/webp');res.end(stored);return; }
    if(path==='/missing.webp') {res.writeHead(404);res.end('Not found');return;}
    const assets = { '/tabler.css': 'node_modules/@tabler/core/dist/css/tabler.min.css', '/studio.css': 'resources/css/photo-editor.css', '/staff-host.css':'resources/css/pages/staff-management.css', '/user-host.css':'resources/css/pages/user-management.css', '/profile-host.css':'resources/css/pages/profile.css' };
    if (assets[path]) { res.setHeader('Content-Type','text/css');res.end(await readFile(join(root,assets[path])));return; }
    const mode = url.searchParams.get('mode') || 'staff', c = modes[mode];
    const form = `<form ${c.attrs || ''}><div id="${c.zone}" ${c.zoneAttrs || ''} tabindex="0"><input type="file" name="photo" id="${c.input}" hidden><div id="${c.box === c.preview ? 'photoBox' : c.box}"><img id="${c.preview}" src="/stored.webp" data-initial-photo="/stored.webp" ${c.previewAttrs || ''} width="80" height="80"></div></div><button type="button" id="${c.edit}" class="d-none" ${c.editAttrs || ''}>Edit Photo</button></form>`;
    const viewer = mode==='user'?'<button type="button" class="staff-photo-view-trigger" data-photo-url="/stored.webp" data-photo-title="Test User">View Photo</button><div class="modal fade" id="staffPhotoViewModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><h3 id="staffPhotoViewTitle"></h3><img id="staffPhotoViewImage"><input type="range" id="staffPhotoViewZoom" min="1" max="3"><a id="staffPhotoViewDownload">Download</a><button type="button" data-bs-dismiss="modal">Close</button></div></div></div>':'';
    const directoryPhoto=mode==='staff'?'<button type="button" data-staff-directory-photo="/stored.webp" data-staff-photo-title="Test Staff">Staff Photo</button>':'';
    res.setHeader('Content-Type','text/html');
    res.end(`<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="fixture"><link rel="stylesheet" href="/tabler.css"><link rel="stylesheet" href="/studio.css"><link rel="stylesheet" href="/${mode}-host.css"><link rel="stylesheet" href="/${mode}.css"></head><body>${c.parent ? `<div class="modal fade" id="${c.parent}" data-open-on-load="1" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-body">${form}</div></div></div></div>` : `<section id="profile-panel-profile-edit">${form}</section>`}${studios[mode]}${viewer}${directoryPhoto}<script type="module" src="/${mode}.js"></script></body></html>`);
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const profile = await mkdtemp(join(tmpdir(), 'wis-student-photo-'));
const browser = spawn(chrome, ['--headless=new', '--disable-gpu', '--no-sandbox', '--no-first-run', '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank'], { windowsHide: true, stdio: 'ignore' });
let socket;
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
try {
    let port;
    for (let attempt = 0; attempt < 100; attempt++) { try { port = Number((await readFile(join(profile, 'DevToolsActivePort'), 'utf8')).split('\n')[0]); break; } catch { await delay(100); } }
    assert.ok(port, 'Browser started');
    const pages = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket = new WebSocket(pages.find(page => page.type === 'page').webSocketDebuggerUrl);
    await new Promise((resolve, reject) => { socket.addEventListener('open', resolve, { once: true }); socket.addEventListener('error', reject, { once: true }); });
    let id = 0; const pending = new Map();
    socket.addEventListener('message', ({ data }) => { const message = JSON.parse(data); if (!pending.has(message.id)) return; const [resolve, reject] = pending.get(message.id); pending.delete(message.id); message.error ? reject(new Error(JSON.stringify(message.error))) : resolve(message.result); });
    const command = (method, params = {}) => new Promise((resolve, reject) => {
        const commandId = ++id, timeout = setTimeout(() => { pending.delete(commandId); reject(new Error(`CDP timeout: ${method}`)); }, 15000);
        pending.set(commandId, [value => { clearTimeout(timeout); resolve(value); }, error => { clearTimeout(timeout); reject(error); }]); socket.send(JSON.stringify({ id: commandId, method, params }));
    });
    const evaluate = async expression => { const result = await command('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true }); if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description || JSON.stringify(result.exceptionDetails)); return result.result.value; };
    const until = async expression => { for (let attempt = 0; attempt < 150; attempt++) { if (await evaluate(expression)) return; await delay(50); } console.log(await evaluate(`JSON.stringify({errors:window.fixtureErrors,clicks:window.fixtureClicks,photos:[...document.querySelectorAll('img')].map(i=>[i.src,i.naturalWidth]),parentState:window.fixtureModal.getInstance(document.getElementById('staffModal'))?._isShown,alerts:document.querySelector('.swal2-container')?.textContent,modals:[...document.querySelectorAll('.modal')].map(m=>[m.id,m.className])})`));assert.fail(`Timed out: ${expression}`); };
    const click = selector => evaluate(`document.querySelector(${JSON.stringify(selector)}).click()`);
    await command('Page.enable');
    await command('Page.addScriptToEvaluateOnNewDocument',{source:'window.fixtureErrors=[];window.addEventListener("error",e=>fixtureErrors.push(e.message));window.addEventListener("unhandledrejection",e=>fixtureErrors.push(String(e.reason)));window.fixtureClicks=[];document.addEventListener("click",e=>fixtureClicks.push(e.target.id),true)'});
    for (const [mode,c] of Object.entries(modes)) for (const width of [1280,390]) {
        await command('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<500});
        await command('Page.navigate',{url:`http://127.0.0.1:${server.address().port}/?mode=${mode}`});
        await until(`document.getElementById('${c.input}')?.dataset.photoStudioReady==='1'`); await delay(350);
        assert.equal(await evaluate(`document.getElementById('${c.edit}').classList.contains('d-none')`),false,'Existing photos can be edited');
        await click('#'+c.edit); await until(`document.getElementById('${c.editor}').classList.contains('show')`); await delay(350);
        await until('document.getElementById("photoEditorFileSize").textContent.includes("15.25 KB")');
        assert.equal(await evaluate('document.querySelectorAll(".modal.show").length'),1,'Editor does not stack parent modals');
        assert.deepEqual(await evaluate(`(()=>{const c=document.querySelector('canvas');return [c.width,c.height];})()`),[400,400]);
        await evaluate(`window.setControl=(id,value,event='input')=>{const c=document.getElementById(id);c.value=value;c.dispatchEvent(new Event(event));};window.pixel=(x,y)=>[...document.querySelector('canvas').getContext('2d').getImageData(x,y,1,1).data];window.beforeFace=pixel(200,100);setControl('studentPhotoAdjustmentScope','selected','change');setControl('studentPhoto-exposure',0.58);setControl('studentPhoto-saturation',25);(()=>{const c=document.querySelector('canvas'),r=c.getBoundingClientRect();c.dispatchEvent(new PointerEvent('pointerdown',{clientX:r.left+r.width/2,clientY:r.top+r.height*.65,pointerId:1,bubbles:true}));c.dispatchEvent(new PointerEvent('pointerup',{pointerId:1,bubbles:true}));})()`); await delay(100);
        await click('#studentPhotoSelectionShow');
        assert.deepEqual(await evaluate('pixel(200,100)'),await evaluate('window.beforeFace'),'Area editing leaves the face unchanged');
        assert.ok((await evaluate('pixel(200,260)'))[0]>225,'Area exposure applies');
        await evaluate(`setControl('studentPhotoAngle',6)`); await delay(80);
        assert.equal(await evaluate('document.getElementById("studentPhotoAngleRange").value'),'6');
        await click('[data-photo-background="#1976d2"]');
        await until(`document.getElementById('${c.editor}').getAttribute('aria-busy')==='false'`);
        assert.deepEqual(await evaluate('pixel(0,0)'),[25,118,210,255],'Local background removal works for each context');
        await evaluate('window.savedPreview=document.querySelector("canvas").toDataURL()');
        await click('#studentPhotoSelectionShow');
        await evaluate('document.getElementById("studentPhoto-exposure").scrollIntoView({block:"center"})'); await delay(100);
        await until('document.getElementById("photoEditorFileSize").textContent.includes("15.25 KB")');
        assert.equal(await evaluate(`document.getElementById('${c.editor}').scrollWidth<=innerWidth`),true,'Studio fits the screen');
        const screenshot=await command('Page.captureScreenshot',{format:'png'});
        await mkdir(join(root,'storage/app/testing'),{recursive:true});
        await writeFile(join(root,`storage/app/testing/${mode}-photo-${width}.png`),Buffer.from(screenshot.data,'base64'));
        await click('#studentPhotoCropUpload'); await until(`!document.getElementById('${c.editor}').classList.contains('show')`);
        await until(`!document.querySelector('.swal2-container')`); await delay(350);
        assert.equal(await evaluate(`new Promise(resolve=>{const r=new FileReader();r.onload=()=>resolve(r.result===window.savedPreview);r.readAsDataURL(document.getElementById('${c.input}').files[0]);})`),true,'Saved PNG includes the edit but no selection guide');
        assert.equal(await evaluate('document.querySelector("[data-edited-photo-size]").textContent'),'WebP size: 15.25 KB · 400 × 400 px','The form preview shows the measured WebP size');
        if(c.parent) assert.equal(await evaluate(`document.getElementById('${c.parent}').classList.contains('show')`),true,'Parent form returns after editing');
        await evaluate(`window.accepted=document.getElementById('${c.input}').files[0]`);
        // Replacing a photo and cancelling must retain the accepted file.
        await evaluate(`(async()=>{const blob=await (await fetch('/stored.webp')).blob(),data=new DataTransfer();data.items.add(new File([blob],'replacement.webp',{type:'image/webp'}));const input=document.getElementById('${c.input}');input.files=data.files;input.dispatchEvent(new Event('change'));})()`);
        await until(`document.getElementById('${c.editor}').classList.contains('show')`); await delay(350);
        await click('#'+c.editor+' [data-bs-dismiss]'); await delay(700);
        assert.equal(await evaluate(`document.getElementById('${c.input}').files[0]===window.accepted`),true,'Cancel preserves the accepted photo');
        if(mode==='user') {
            await evaluate(`window.fixtureModal.getInstance(document.getElementById('${c.parent}')).hide()`); await delay(500);
            await click('.staff-photo-view-trigger');
            await until('document.getElementById("staffPhotoViewDownload").href.startsWith("blob:")');
            const jpeg=await evaluate(`(async()=>{const a=document.getElementById('staffPhotoViewDownload'),b=await(await fetch(a.href)).blob();return {name:a.download,type:b.type,magic:[...new Uint8Array(await b.arrayBuffer()).slice(0,3)]};})()`);
            assert.equal(jpeg.name,'Test-User.jpg');assert.equal(jpeg.type,'image/jpeg');assert.deepEqual(jpeg.magic,[255,216,255],'Viewer downloads a real JPEG');
        }
        if(mode==='staff') {
            await evaluate(`window.fixtureModal.getInstance(document.getElementById('${c.parent}')).hide()`);await delay(500);
            await click('[data-staff-directory-photo]');
            await until('document.querySelector("#staffDirectoryPhotoModal [data-photo-download]").href.startsWith("blob:")');await delay(350);
            const jpeg=await evaluate(`(async()=>{const a=document.querySelector('#staffDirectoryPhotoModal [data-photo-download]'),b=await(await fetch(a.href)).blob();return{name:a.download,type:b.type,magic:[...new Uint8Array(await b.arrayBuffer()).slice(0,3)]};})()`);
            assert.equal(jpeg.name,'Test-Staff.jpg');assert.equal(jpeg.type,'image/jpeg');assert.deepEqual(jpeg.magic,[255,216,255]);
            await evaluate('window.photoFitWidth=document.querySelector("[data-photo-image]").getBoundingClientRect().width');
            await click('[data-photo-in]');
            assert.equal(await evaluate('document.querySelector("[data-photo-percent]").textContent'),'110%');
            const zoomState=await evaluate('(()=>{const i=document.querySelector("[data-photo-image]"),s=document.querySelector("[data-photo-stage]");return{width:i.getBoundingClientRect().width,before:window.photoFitWidth,style:i.style.width,natural:i.naturalWidth,naturalHeight:i.naturalHeight,errors:window.fixtureErrors,stageWidth:s.clientWidth,stageHeight:s.clientHeight};})()');
            assert.ok(zoomState.width>zoomState.before,`Zoom in enlarges the photo: ${JSON.stringify(zoomState)}`);
            await click('[data-photo-out]');
            assert.equal(await evaluate('document.querySelector("[data-photo-percent]").textContent'),'100%');
            await evaluate('const range=document.querySelector("[data-photo-zoom]");range.value="3";range.dispatchEvent(new Event("input"))');
            assert.ok(await evaluate('(()=>{const s=document.querySelector("[data-photo-stage]");return s.scrollWidth>s.clientWidth||s.scrollHeight>s.clientHeight;})()'),'Zoomed photo can be scrolled');
            assert.ok(await evaluate('(()=>{const r=document.querySelector("[data-photo-image]").getBoundingClientRect();return Math.abs(r.width/r.height-1)<.01;})()'),'Photo proportions remain unchanged');
            await click('[data-photo-reset]');
            assert.equal(await evaluate('document.querySelector("[data-photo-percent]").textContent'),'100%');
            assert.equal(await evaluate('document.getElementById("staffDirectoryPhotoModal").scrollWidth<=innerWidth'),true,'Viewer fits mobile width');
            const screenshot=await command('Page.captureScreenshot',{format:'png'});await writeFile(join(root,`storage/app/testing/staff-directory-photo-${width}.png`),Buffer.from(screenshot.data,'base64'));
            await click('#staffDirectoryPhotoModal [data-bs-dismiss]');await delay(500);
            assert.equal(await evaluate('document.querySelector("[data-photo-download]").getAttribute("href")'),null,'Closing releases the download URL');
            await evaluate('document.querySelector("[data-staff-directory-photo]").dataset.staffDirectoryPhoto="/missing.webp"');
            await click('[data-staff-directory-photo]');await until('!document.querySelector("[data-photo-error]").classList.contains("d-none")');
            assert.equal(await evaluate('document.querySelector("[data-photo-download]").getAttribute("aria-disabled")'),'true','Failed photos cannot download an invalid JPG');
        }
        assert.deepEqual(await evaluate('window.fixtureErrors'),[],'No browser errors');
        console.log(`${mode} ${width}px: existing photo, square crop, selection, exposure/saturation, rotation, background, save and cancel passed.`);
    }
    assert.equal(backgroundRequests,6,'Selection adjustments do not call the AI endpoint');
    await command('Browser.close');
} finally { socket?.close();browser.kill();server.closeAllConnections();await new Promise(resolve=>server.close(resolve)); }
