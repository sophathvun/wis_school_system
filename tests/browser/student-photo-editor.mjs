// Synthetic portraits only; this fixture never uses student records or production sessions.
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { spawn, execFileSync } from 'node:child_process';
import { readFile, mkdtemp, mkdir, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';

const root = resolve(import.meta.dirname, '../..');
const php = process.env.CHAT_TEST_PHP || 'C:/laragon/bin/php/php-8.4.25-Win32-vs17-x64/php.exe';
const chrome = process.env.CHAT_TEST_BROWSER || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const editor = execFileSync(php, [join(root, 'tests/browser/student-photo-fixture.php')], { cwd: root, encoding: 'utf8' });
const cutout = execFileSync(php, ['-r', '$i=imagecreatetruecolor(600,800);imagealphablending($i,false);imagesavealpha($i,true);imagefill($i,0,0,imagecolorallocatealpha($i,0,0,0,127));imagefilledellipse($i,300,390,250,450,imagecolorallocatealpha($i,200,140,100,0));imagepng($i);'], { cwd: root });
let failBackground = false, backgroundRequests = 0;
const server = createServer(async (req, res) => {
    const path = new URL(req.url, 'http://localhost').pathname;
    if (path === '/student-enrollments/photo/background') {
        for await (const chunk of req) {} backgroundRequests++;
        res.writeHead(failBackground ? 503 : 200, { 'Content-Type': failBackground ? 'application/json' : 'image/png' });
        res.end(failBackground ? JSON.stringify({ message: 'Local model unavailable. Try again.' }) : cutout); return;
    }
    if (path === '/fail-background') { failBackground = true; res.end('ok'); return; }
    const files = {
        '/bootstrap.js': 'node_modules/bootstrap/dist/js/bootstrap.bundle.min.js',
        '/tabler.css': 'node_modules/@tabler/core/dist/css/tabler.min.css',
        '/editor.css': 'resources/css/pages/student-enrollment.css',
        '/editor.js': 'resources/js/helpers/studentPhotoEditor.js',
    };
    if (files[path]) { res.setHeader('Content-Type', path.endsWith('.js') ? 'text/javascript' : 'text/css'); res.end(await readFile(join(root, files[path]))); return; }
    if (path === '/stored.webp') {
        res.setHeader('Content-Type', 'image/webp');
        res.end(execFileSync(php, ['-r', '$i=imagecreatetruecolor(600,800);imagefill($i,0,0,imagecolorallocate($i,40,110,200));imagewebp($i);'])); return;
    }
    res.setHeader('Content-Type', 'text/html; charset=utf-8');
    res.end(`<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/tabler.css"><link rel="stylesheet" href="/editor.css"></head><body>
    <div class="modal fade" id="enrollmentModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-body"><div id="studentPhotoDropzone" tabindex="0"><input type="file" id="student_photo" hidden><div id="studentPhotoPreviewContainer" class="d-none"><img id="studentPhotoPreview" width="90" height="120"></div></div><button type="button" id="studentPhotoEdit" class="d-none">Edit Photo</button></div></div></div></div>
    ${editor}<script src="/bootstrap.js"></script><script type="module">
    import {createStudentPhotoEditor,studentPhotoJpeg} from '/editor.js';
    window.alerts=[];window.photoEditor=createStudentPhotoEditor({bootstrap,csrf:'test',showError:(...args)=>alerts.push(['error',...args]),showSuccess:(...args)=>alerts.push(['success',...args])});
    window.studentPhotoJpeg=studentPhotoJpeg;
    window.upload=async()=>{const c=document.createElement('canvas');c.width=600;c.height=800;const ctx=c.getContext('2d');ctx.fillStyle='#b0c0d0';ctx.fillRect(0,0,600,800);ctx.fillStyle='#c88c64';ctx.fillRect(170,100,260,600);const b=await new Promise(r=>c.toBlob(r));const f=new File([b],'portrait.png',{type:'image/png'});const d=new DataTransfer();d.items.add(f);document.querySelector('#student_photo').files=d.files;document.querySelector('#student_photo').dispatchEvent(new Event('change'));};
    bootstrap.Modal.getOrCreateInstance(document.querySelector('#enrollmentModal')).show();window.ready=true;
    </script></body></html>`);
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
    const until = async expression => { for (let attempt = 0; attempt < 150; attempt++) { if (await evaluate(expression)) return; await delay(50); } assert.fail(`Timed out: ${expression}`); };
    const click = selector => evaluate(`document.querySelector(${JSON.stringify(selector)}).click()`);
    await command('Page.enable');
    for (const [width, mobile] of [[1280, false], [390, true], [320, true]]) {
        await command('Emulation.setDeviceMetricsOverride', { width, height: mobile ? 844 : 1000, deviceScaleFactor: 1, mobile });
        await command('Page.navigate', { url: `http://127.0.0.1:${server.address().port}/` });
        await until('window.ready && document.querySelector("#enrollmentModal").classList.contains("show")');
        await delay(350); await evaluate('upload()');
        await until('document.querySelector("#studentPhotoCropModal").classList.contains("show")'); await delay(350);
        assert.equal(await evaluate('document.querySelectorAll(".modal.show").length'), 1, 'The studio manages the parent modal correctly');
        await evaluate('window.original=document.querySelector("canvas").toDataURL()');
        for (const angle of [5, 6, -6, 5.5, 45, -45]) {
            await evaluate(`(()=>{const input=document.querySelector('#studentPhotoAngle');input.value=${angle};input.dispatchEvent(new Event('input'));})()`);
            await delay(80);
            assert.equal(await evaluate('Number(document.querySelector("#studentPhotoAngleRange").value)'), angle, 'The precise angle and slider stay synchronized');
            assert.equal(await evaluate('document.querySelector("canvas").toDataURL() !== window.original'), true, 'Straightening changes the preview');
            assert.equal(await evaluate(`(()=>{const ctx=document.querySelector('canvas').getContext('2d');return [[2,2],[597,2],[2,797],[597,797]].every(([x,y])=>['[176,192,208,255]','[200,140,100,255]'].includes(JSON.stringify([...ctx.getImageData(x,y,1,1).data])));})()`), true, `Straightening at ${angle} degrees fills every crop corner with image pixels`);
        }
        await evaluate('document.querySelector("#studentPhotoAngle").value="";document.querySelector("#studentPhotoAngle").dispatchEvent(new Event("change"))');
        assert.equal(await evaluate('document.querySelector("#studentPhotoAngle").value'), '-45', 'An empty angle restores the last valid value');
        await click('#studentPhotoAngleReset'); await delay(80);
        assert.equal(await evaluate('document.querySelector("canvas").toDataURL() === window.original'), true, 'Reset angle restores the initial framing');
        await click('#studentPhotoRotateRight');
        await evaluate('window.quarterTurn=document.querySelector("canvas").toDataURL();document.querySelector("#studentPhotoAngleRange").value="6";document.querySelector("#studentPhotoAngleRange").dispatchEvent(new Event("input"))');
        await delay(80);
        assert.equal(await evaluate('document.querySelector("#studentPhotoAngle").value'), '6');
        assert.equal(await evaluate('document.querySelector("canvas").toDataURL() !== window.quarterTurn'), true, 'Straightening combines with quarter turns');
        await click('#studentPhotoAngleReset'); await delay(80);
        assert.equal(await evaluate('document.querySelector("canvas").toDataURL() === window.quarterTurn'), true, 'Reset angle preserves the quarter turn');
        await click('#studentPhotoReset');
        assert.equal(await evaluate('document.querySelector("canvas").toDataURL() === window.original'), true, 'Reset All also clears rotation');
        await click('#studentPhotoEnhance'); await delay(100);
        assert.equal(await evaluate('document.querySelector("canvas").toDataURL() !== window.original'), true);
        await click('#studentPhotoCompare');
        assert.equal(await evaluate('document.querySelector("canvas").toDataURL() === window.original'), true, 'Original comparison has no enhancements');
        await click('#studentPhotoReset');
        await click('[data-photo-background="#1976d2"]');
        await until('document.querySelector("#studentPhotoCropModal").getAttribute("aria-busy")==="false" && [...document.querySelectorAll("[data-photo-background]")].find(b=>b.dataset.photoBackground==="#1976d2").getAttribute("aria-pressed")==="true"');
        assert.deepEqual(await evaluate('[...document.querySelector("canvas").getContext("2d").getImageData(0,0,1,1).data]'), [25, 118, 210, 255], 'Cutout composites onto blue');
        await evaluate('document.querySelector("#studentPhotoBrush").value="erase"');
        await evaluate(`(()=>{const c=document.querySelector('canvas'),r=c.getBoundingClientRect();c.dispatchEvent(new PointerEvent('pointerdown',{clientX:r.left+r.width/2,clientY:r.top+r.height/2,pointerId:1,bubbles:true}));c.dispatchEvent(new PointerEvent('pointerup',{pointerId:1,bubbles:true}));})()`);
        await delay(100);
        assert.deepEqual(await evaluate('[...document.querySelector("canvas").getContext("2d").getImageData(300,400,1,1).data]'), [25, 118, 210, 255], 'Edge eraser removes selected foreground');
        await evaluate('document.querySelector("#studentPhotoBrush").value="restore"');
        await evaluate(`(()=>{const c=document.querySelector('canvas'),r=c.getBoundingClientRect();c.dispatchEvent(new PointerEvent('pointerdown',{clientX:r.left+r.width/2,clientY:r.top+r.height/2,pointerId:1,bubbles:true}));c.dispatchEvent(new PointerEvent('pointerup',{pointerId:1,bubbles:true}));})()`);
        await delay(100);
        assert.deepEqual(await evaluate('[...document.querySelector("canvas").getContext("2d").getImageData(300,400,1,1).data]'), [200, 140, 100, 255], 'Restore brush brings back original pixels');
        await evaluate('document.querySelector("#studentPhotoBrush").value="smooth";window.edgeBefore=[...document.querySelector("canvas").getContext("2d").getImageData(170,380,12,20).data]');
        await evaluate(`(()=>{const c=document.querySelector('canvas'),r=c.getBoundingClientRect();c.dispatchEvent(new PointerEvent('pointerdown',{clientX:r.left+r.width*175/600,clientY:r.top+r.height*390/800,pointerId:1,bubbles:true}));c.dispatchEvent(new PointerEvent('pointerup',{pointerId:1,bubbles:true}));})()`);
        await delay(100);
        assert.equal(await evaluate('JSON.stringify([...document.querySelector("canvas").getContext("2d").getImageData(170,380,12,20).data])!==JSON.stringify(window.edgeBefore)'), true, 'Smooth edge brush feathers a selected outline');
        assert.deepEqual(await evaluate('[...document.querySelector("canvas").getContext("2d").getImageData(300,400,1,1).data]'), [200, 140, 100, 255], 'Smoothing the outline leaves the subject interior unchanged');
        assert.deepEqual(await evaluate('[...document.querySelector("canvas").getContext("2d").getImageData(0,0,1,1).data]'), [25, 118, 210, 255], 'Smooth edge brush leaves the background unchanged');
        await evaluate('document.querySelector("#studentPhotoAngle").value="6";document.querySelector("#studentPhotoAngle").dispatchEvent(new Event("input"))'); await delay(80);
        // A known off-center source point (350, 300) mapped into a six-degree crop.
        await evaluate(`window.rotatedBrushPoint=(()=>{const a=6*Math.PI/180,s=(600*Math.cos(a)+800*Math.sin(a))/600;return{x:300+(50*Math.cos(a)+100*Math.sin(a))*s,y:400+(50*Math.sin(a)-100*Math.cos(a))*s};})()`);
        for (const [mode, color] of [['erase', [25,118,210,255]], ['restore', [200,140,100,255]]]) {
            await evaluate(`document.querySelector('#studentPhotoBrush').value=${JSON.stringify(mode)};(()=>{const c=document.querySelector('canvas'),r=c.getBoundingClientRect(),p=window.rotatedBrushPoint;c.dispatchEvent(new PointerEvent('pointerdown',{clientX:r.left+r.width*p.x/600,clientY:r.top+r.height*p.y/800,pointerId:1,bubbles:true}));c.dispatchEvent(new PointerEvent('pointerup',{pointerId:1,bubbles:true}));})()`); await delay(80);
            assert.deepEqual(await evaluate('(()=>{const p=window.rotatedBrushPoint;return [...document.querySelector("canvas").getContext("2d").getImageData(Math.round(p.x),Math.round(p.y),1,1).data]})()'), color, 'Brush coordinates follow the rotated photo');
        }
        assert.equal(await evaluate('document.querySelector("#studentPhotoCropModal").scrollWidth <= innerWidth'), true, 'No horizontal overflow');
        assert.ok(await evaluate('(()=>{const r=document.querySelector("canvas").getBoundingClientRect();return Math.abs(r.width/r.height-.75)<.01})()'), 'The portrait keeps its 3:4 ratio on narrow screens');
        const image = await command('Page.captureScreenshot', { format: 'png' });
        await mkdir(join(root, 'storage/app/testing'), { recursive: true });
        await writeFile(join(root, `storage/app/testing/student-photo-${mobile ? 'mobile' : 'desktop'}.png`), Buffer.from(image.data, 'base64'));
        await evaluate('window.finalPreview=document.querySelector("canvas").toDataURL()');
        await click('#studentPhotoCropUpload');
        await until('document.querySelector("#enrollmentModal").classList.contains("show")'); await delay(350);
        assert.equal(await evaluate('document.querySelector("#student_photo").files[0].type'), 'image/png');
        assert.equal(await evaluate('new Promise(resolve=>{const r=new FileReader();r.onload=()=>resolve(r.result===window.finalPreview);r.readAsDataURL(document.querySelector("#student_photo").files[0]);})'), true, 'Use Photo saves the rotated preview');
        await evaluate('window.accepted=document.querySelector("#student_photo").files[0]');
        await evaluate('upload()'); await until('document.querySelector("#studentPhotoCropModal").classList.contains("show")'); await delay(350);
        await click('#studentPhotoCropModal [data-bs-dismiss]');
        await until('document.querySelector("#enrollmentModal").classList.contains("show")'); await delay(350);
        assert.equal(await evaluate('document.querySelector("#student_photo").files[0]===window.accepted'), true, 'Cancelling a replacement preserves the accepted photo');
        const jpeg = await evaluate('(async()=>{const b=await studentPhotoJpeg("/stored.webp");return{type:b.type,magic:[...new Uint8Array(await b.arrayBuffer()).slice(0,3)]};})()');
        assert.equal(jpeg.type, 'image/jpeg'); assert.deepEqual(jpeg.magic, [255, 216, 255], 'JPG conversion produces real JPEG bytes');
        console.log(`${mobile ? 'Mobile' : 'Desktop'} photo editor: comparison, enhancement, background, brushes, cancel, crop and JPG conversion passed.`);
    }
    await evaluate('fetch("/fail-background")');
    await evaluate('upload()'); await until('document.querySelector("#studentPhotoCropModal").classList.contains("show")'); await delay(350);
    await click('#studentPhotoRemoveBackground');
    await until('!document.querySelector("#studentPhotoEditorError").classList.contains("d-none")');
    assert.equal(await evaluate('document.querySelector("#studentPhotoEditorError").textContent'), 'Local model unavailable. Try again.');
    await until('document.querySelector("#studentPhotoCropUpload").disabled===false');
    assert.equal(backgroundRequests, 4);
    console.log('Failure recovery passed; screenshots saved in storage/app/testing.');
    await command('Browser.close');
} finally { socket?.close(); browser.kill(); server.closeAllConnections(); await new Promise(resolve => server.close(resolve)); }
