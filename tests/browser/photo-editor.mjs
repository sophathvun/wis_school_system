import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { spawn, execFileSync } from 'node:child_process';
import { readFile, mkdtemp, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { build } from 'vite';

const root = resolve(import.meta.dirname, '../..');
const php = process.env.CHAT_TEST_PHP || 'C:/laragon/bin/php/php-8.4.25-Win32-vs17-x64/php.exe';
const chrome = process.env.CHAT_TEST_BROWSER || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const markup = execFileSync(php, [join(root, 'tests/browser/photo-editor-fixture.php')], { cwd: root, encoding: 'utf8' });
const files = new Map();
const result = await build({ root, configFile: false, logLevel: 'silent', build: { write: false,
    lib: { entry: join(root, 'resources/js/photoEditor.js'), formats: ['es'], fileName: () => 'editor.js', cssFileName: 'editor' } } });
for (const output of Array.isArray(result) ? result : [result]) for (const file of output.output) files.set('/' + file.fileName, file.type === 'chunk' ? file.code : file.source);
const image = format => execFileSync(php, ['-r', `$i=imagecreatetruecolor(600,800);imagefill($i,0,0,imagecolorallocate($i,140,160,180));imagefilledrectangle($i,140,150,460,800,imagecolorallocate($i,200,140,100));image${format}($i);`]);
const png = image('png'), jpg = image('jpeg'), webp = image('webp');
let downloads = 0, backgrounds = 0, failDownload = false, webpUnavailable = false;
const server = createServer(async (req, res) => {
    const path = new URL(req.url, 'http://localhost').pathname;
    const css = { '/tabler.css': 'node_modules/@tabler/core/dist/css/tabler.min.css',
        '/studio.css': 'resources/css/photo-editor.css', '/workspace.css': 'resources/css/pages/photo-editor.css' };
    if (css[path]) { res.setHeader('Content-Type', 'text/css'); res.end(await readFile(join(root, css[path]))); return; }
    if (files.has(path)) { res.setHeader('Content-Type', path.endsWith('.js') ? 'text/javascript' : 'text/css'); res.end(files.get(path)); return; }
    if (path === '/photo-editor/size') {
        const chunks = []; for await (const chunk of req) chunks.push(chunk);
        const body = Buffer.concat(chunks), offset = body.indexOf(Buffer.from([137,80,78,71,13,10,26,10]));
        res.setHeader('Content-Type', 'application/json'); res.end(JSON.stringify({ bytes: webpUnavailable ? null : webp.length, jpg_bytes: jpg.length, width: body.readUInt32BE(offset+16), height: body.readUInt32BE(offset+20), webp_available: !webpUnavailable })); return;
    }
    if (path === '/photo-editor/background') {
        for await (const chunk of req) {} backgrounds++;
        res.setHeader('Content-Type', 'image/png'); res.end(png); return;
    }
    if (path === '/photo-editor/download') {
        const chunks = []; for await (const chunk of req) chunks.push(chunk);
        const body = Buffer.concat(chunks); downloads++;
        assert.ok(body.includes(Buffer.from([137, 80, 78, 71])), 'Downloads send the accepted PNG crop');
        if (failDownload) { res.writeHead(422, { 'Content-Type': 'application/json' }); res.end(JSON.stringify({ message: 'Please try another photo.' })); return; }
        const jpeg = body.toString('latin1').includes('\r\n\r\njpg\r\n');
        const start = body.indexOf(Buffer.from([137,80,78,71,13,10,26,10])), end = body.indexOf(Buffer.from('IEND'),start)+8;
        const encoded = execFileSync(php, ['-r', `$i=imagecreatefromstring(stream_get_contents(STDIN));imageresolution($i,300,300);image${jpeg?'jpeg':'webp'}($i,null,90);`], {input:body.subarray(start,end)});
        res.setHeader('Content-Type', jpeg ? 'image/jpeg' : 'image/webp'); res.end(encoded); return;
    }
    if (path === '/portrait.png') { res.setHeader('Content-Type', 'image/png'); res.end(png); return; }
    res.setHeader('Content-Type', 'text/html');
    res.end(`<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="fixture"><link rel="stylesheet" href="/tabler.css"><link rel="stylesheet" href="/studio.css"><link rel="stylesheet" href="/workspace.css"><link rel="stylesheet" href="/editor.css"></head><body><main class="container-fluid py-3">${markup}</main><script>window.errors=[];addEventListener('error',e=>errors.push(e.message));addEventListener('unhandledrejection',e=>errors.push(String(e.reason)));window.downloaded=[];HTMLAnchorElement.prototype.click=function(){if(this.download)window.downloaded.push({name:this.download,url:this.href});};</script><script type="module" src="/editor.js"></script></body></html>`);
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const profile = await mkdtemp(join(tmpdir(), 'wis-photo-editor-'));
const browser = spawn(chrome, ['--headless=new', '--disable-gpu', '--no-sandbox', '--no-first-run', '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank'], { windowsHide: true, stdio: 'ignore' });
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
let socket;
try {
    let port;
    for (let attempt = 0; attempt < 100; attempt++) { try { port = Number((await readFile(join(profile, 'DevToolsActivePort'), 'utf8')).split('\n')[0]); break; } catch { await delay(100); } }
    assert.ok(port, 'Chrome started');
    const pages = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket = new WebSocket(pages.find(page => page.type === 'page').webSocketDebuggerUrl);
    await new Promise(resolve => socket.addEventListener('open', resolve, { once: true }));
    let id = 0; const pending = new Map();
    socket.addEventListener('message', ({ data }) => { const message = JSON.parse(data); if (!pending.has(message.id)) return; const [resolve, reject] = pending.get(message.id); pending.delete(message.id); message.error ? reject(new Error(JSON.stringify(message.error))) : resolve(message.result); });
    const command = (method, params = {}) => new Promise((resolve, reject) => {
        const key = ++id, timer = setTimeout(() => { pending.delete(key); reject(new Error(`Timeout: ${method}`)); }, 15000);
        pending.set(key, [value => { clearTimeout(timer); resolve(value); }, error => { clearTimeout(timer); reject(error); }]); socket.send(JSON.stringify({ id: key, method, params }));
    });
    const evaluate = async expression => { const result = await command('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true }); if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description || JSON.stringify(result.exceptionDetails)); return result.result.value; };
    const until = async expression => { for (let attempt = 0; attempt < 160; attempt++) { if (await evaluate(expression)) return; await delay(50); } throw new Error(`Timed out: ${expression}`); };
    const click = selector => evaluate(`document.querySelector(${JSON.stringify(selector)}).click()`);
    for (const width of [1280, 390]) {
        await command('Emulation.setDeviceMetricsOverride', { width, height: 1000, deviceScaleFactor: 1, mobile: width < 500 });
        await command('Page.navigate', { url: `http://127.0.0.1:${server.address().port}` });
        await until('document.getElementById("photoEditorWorkspace")?.dataset.ready==="true"');
        assert.equal(await evaluate('document.querySelector("[data-photo-download]").disabled'), true);
        await evaluate(`(async()=>{const blob=await(await fetch('/portrait.png')).blob(),data=new DataTransfer();data.items.add(new File([blob],'portrait.png',{type:'image/png'}));const input=document.getElementById('photoEditorInput');input.files=data.files;input.dispatchEvent(new Event('change'));})()`);
        await until('document.getElementById("photoEditorModal").classList.contains("show")'); await delay(400);
        await until('document.getElementById("photoEditorFileSize").textContent.includes("KB")');
        assert.equal(await evaluate(`document.getElementById('photoEditorFileSize').textContent.includes('JPG size: ${(jpg.length/1024).toFixed(2)} KB')`),true);
        for (const [preset, pixels] of [['3x4',[354,472]],['3.5x4.5',[413,531]],['4x6',[472,709]],['4.5x4.5',[531,531]],['600x800',[600,800]]]) {
            await evaluate(`(()=>{const size=document.getElementById('photoEditorSize');size.value=${JSON.stringify(preset)};size.dispatchEvent(new Event('change'));})()`);
            assert.deepEqual(await evaluate(`(()=>{const c=document.querySelector('canvas');return[c.width,c.height];})()`),pixels);
            assert.equal(await evaluate(`(()=>{const r=document.querySelector('canvas').getBoundingClientRect(),c=document.querySelector('canvas');return Math.abs(r.width/r.height-c.width/c.height)<.01;})()`),true,'Preview has the selected aspect ratio');
        }
        await evaluate(`document.getElementById('photoEditorSize').value='custom';document.getElementById('photoEditorSize').dispatchEvent(new Event('change'));document.getElementById('photoEditorWidth').value=0;document.getElementById('photoEditorWidth').dispatchEvent(new Event('input'));`);
        assert.equal(await evaluate('document.getElementById("studentPhotoCropUpload").disabled'),true);
        assert.equal(await evaluate('document.getElementById("photoEditorSizeError").classList.contains("d-none")'),false);
        await evaluate(`document.getElementById('photoEditorWidth').value=5;document.getElementById('photoEditorHeight').value=3;document.getElementById('photoEditorWidth').dispatchEvent(new Event('input'));`);
        assert.deepEqual(await evaluate(`(()=>{const c=document.querySelector('canvas');return[c.width,c.height];})()`),[591,354]);
        await evaluate(`document.getElementById('photoEditorSize').value='3x4';document.getElementById('photoEditorSize').dispatchEvent(new Event('change'));`);
        await evaluate(`window.setControl=(id,value,event='input')=>{const e=document.getElementById(id);e.value=value;e.dispatchEvent(new Event(event));};window.photoPixel=(x,y)=>[...document.querySelector('canvas').getContext('2d').getImageData(x,y,1,1).data];window.neutralPreview=document.querySelector('canvas').toDataURL();`);
        await click('[data-photo-preset="natural"]'); await delay(150);
        assert.equal(await evaluate('document.querySelector("[data-photo-preset=natural]").getAttribute("aria-pressed")'), 'true');
        assert.equal(await evaluate('document.querySelector("canvas").toDataURL()!==window.neutralPreview'), true, 'Natural preset changes the actual pixels');
        await click('#studentPhotoCompare');
        assert.equal(await evaluate('document.querySelector("canvas").toDataURL()===window.neutralPreview'), true, 'Comparison shows the unadjusted photo');
        await click('#studentPhotoCompare');
        await click('#studentPhotoAdjustmentReset');
        assert.equal(await evaluate('document.querySelector("canvas").toDataURL()===window.neutralPreview'), true, 'Adjustment reset is reversible');
        await evaluate(`setControl('studentPhotoAdjustmentScope','selected','change');window.unchangedFace=photoPixel(177,118);setControl('studentPhoto-warmth',50);setControl('studentPhoto-tint',10);setControl('studentPhoto-shadows',30);setControl('studentPhoto-highlights',-20);setControl('studentPhoto-vibrance',35);setControl('studentPhoto-portraitSmooth',25);`); await delay(100);
        assert.equal(await evaluate('document.querySelector("canvas").toDataURL()===window.neutralPreview'), true, 'An empty selection does not change the photo');
        await evaluate(`(()=>{const c=document.querySelector('canvas'),r=c.getBoundingClientRect();c.dispatchEvent(new PointerEvent('pointerdown',{clientX:r.left+r.width/2,clientY:r.top+r.height*.7,pointerId:1,bubbles:true}));c.dispatchEvent(new PointerEvent('pointerup',{pointerId:1,bubbles:true}));})()`);
        await click('#studentPhotoSelectionShow'); await delay(150);
        assert.equal(await evaluate('JSON.stringify(photoPixel(177,118))===JSON.stringify(window.unchangedFace)'), true, 'Area adjustments preserve the face outside the selection');
        assert.equal(await evaluate('document.querySelector("canvas").toDataURL()!==window.neutralPreview'), true);
        await click('#studentPhotoSelectionReset');
        assert.equal(await evaluate('document.querySelector("canvas").toDataURL()===window.neutralPreview'), true);
        await evaluate(`setControl('studentPhotoAdjustmentScope','whole','change');setControl('studentPhotoAngle',6);setControl('studentPhoto-exposure',.3);setControl('studentPhoto-saturation',20);`); await delay(100);
        assert.equal(await evaluate('document.getElementById("studentPhotoAngleRange").value'), '6');
        assert.equal(await evaluate('document.querySelector("[data-photo-value=exposure]").textContent'), '+0.30 EV');
        await evaluate(`setControl('studentPhotoAdjustmentScope','selected','change');setControl('studentPhoto-contrast',20);const c=document.querySelector('canvas'),r=c.getBoundingClientRect();c.dispatchEvent(new PointerEvent('pointerdown',{clientX:r.left+r.width/2,clientY:r.top+r.height*.7,pointerId:1,bubbles:true}));c.dispatchEvent(new PointerEvent('pointerup',{pointerId:1,bubbles:true}));`);
        await click('[data-photo-background="#1976d2"]');
        await until('document.getElementById("photoEditorModal").getAttribute("aria-busy")==="false"');
        await click('[data-photo-preset="studio"]');
        await evaluate(`setControl('studentPhoto-warmth',12);setControl('studentPhoto-portraitSmooth',15);document.getElementById('studentPhotoGuides').checked=true;document.getElementById('studentPhotoGuides').dispatchEvent(new Event('change'));`); await delay(150);
        await evaluate(`setControl('photoEditorSize','custom','change');setControl('photoEditorWidth',5);setControl('photoEditorHeight',3);`); await delay(150);
        assert.equal(await evaluate('document.getElementById("studentPhotoGuideOverlay").classList.contains("d-none")'), false);
        assert.equal(await evaluate(`(()=>{const canvas=document.querySelector('canvas').getBoundingClientRect(),guides=document.getElementById('studentPhotoGuideOverlay').getBoundingClientRect();return Math.abs(canvas.width-guides.width)<1&&Math.abs(canvas.height-guides.height)<1;})()`), true, 'Guides align exactly with the photo');
        await evaluate(`window.exportPixels=document.querySelector('canvas').toDataURL();`);
        assert.equal(await evaluate('document.getElementById("photoEditorModal").scrollWidth<=innerWidth'), true);
        await writeFile(join(root, `storage/app/testing/photo-editor-studio-${width}.png`), Buffer.from((await command('Page.captureScreenshot', { format: 'png' })).data, 'base64'));
        await evaluate(`document.querySelector('[data-photo-preset=natural]').scrollIntoView({block:'start'});`); await delay(150);
        await writeFile(join(root, `storage/app/testing/photo-editor-natural-tools-${width}.png`), Buffer.from((await command('Page.captureScreenshot', { format: 'png' })).data, 'base64'));
        await click('#studentPhotoCropUpload');
        await until('!document.getElementById("photoEditorModal").classList.contains("show")'); await delay(450);
        assert.equal(await evaluate(`new Promise(resolve=>{const reader=new FileReader();reader.onload=()=>resolve(reader.result===window.exportPixels);reader.readAsDataURL(document.getElementById('photoEditorInput').files[0]);})`), true, 'Saved crop includes natural adjustments and excludes guides/selection overlays');
        assert.equal(await evaluate('document.querySelector("[data-photo-download]").disabled'), false);
        assert.ok(await evaluate('document.querySelector("[data-edited-photo-size]").textContent.includes("KB")'));
        assert.equal(await evaluate(`document.querySelector('[data-edited-photo-size]').textContent.includes('JPG size: ${(jpg.length/1024).toFixed(2)} KB')`),true);
        await evaluate('window.acceptedPhoto=document.getElementById("photoEditorPreview").src');
        for (const format of ['jpg', 'webp']) {
            await click(`[data-photo-download="${format}"]`);
            await until(`document.getElementById('photoEditorDownloadStatus').textContent.startsWith('${format.toUpperCase()} ready')`);
            const info = await evaluate(`(async()=>{const entry=window.downloaded.at(-1),blob=await(await fetch(entry.url)).blob(),image=await createImageBitmap(blob);return{name:entry.name,type:blob.type,size:blob.size,width:image.width,height:image.height,magic:[...new Uint8Array(await blob.arrayBuffer()).slice(0,3)]};})()`);
            assert.equal(info.name, `edited-photo.${format}`); assert.equal(info.type, format === 'jpg' ? 'image/jpeg' : 'image/webp');
            assert.deepEqual([info.width,info.height],[591,354],'Both downloads preserve the selected custom size');
            if (format === 'jpg') assert.deepEqual(info.magic, [255, 216, 255]); else assert.ok(info.size <= 40960);
        }
        failDownload = true;
        await click('[data-photo-download="jpg"]');
        await until('document.querySelector(".swal2-container")?.textContent.includes("Please try another photo.")');
        assert.equal(await evaluate('document.querySelector("[data-photo-download]").disabled'), false);
        failDownload = false;
        await click('#photoEditorEdit');
        await until('document.getElementById("photoEditorModal").classList.contains("show")'); await delay(400);
        await click('#photoEditorModal [data-bs-dismiss]'); await delay(450);
        assert.equal(await evaluate('document.getElementById("photoEditorPreview").src===window.acceptedPhoto'), true, 'Cancel preserves accepted preview');
        await until('!document.querySelector(".swal2-container")');
        webpUnavailable = true;
        await click('#photoEditorEdit');
        await until('document.getElementById("photoEditorModal").classList.contains("show")'); await delay(400);
        await click('#studentPhotoCropUpload');
        await until('!document.getElementById("photoEditorModal").classList.contains("show")'); await delay(400);
        assert.equal(await evaluate('document.querySelector("[data-photo-download=jpg]").disabled'), false, 'JPG stays available for a large WebP');
        assert.equal(await evaluate('document.querySelector("[data-photo-download=webp]").disabled'), true, 'Cannot download an oversized WebP');
        assert.equal(await evaluate('document.querySelector("[data-edited-photo-size]").textContent.includes("JPG available")'), true);
        await click('[data-photo-download="jpg"]');
        await until(`document.getElementById('photoEditorDownloadStatus').textContent.startsWith('JPG ready')`);
        assert.equal(await evaluate('document.querySelector("[data-photo-download=webp]").disabled'), true);
        webpUnavailable = false;
        await until('!document.querySelector(".swal2-container")');
        await evaluate('document.getElementById("photoEditorWorkspace").scrollIntoView({block:"end"})');
        await writeFile(join(root, `storage/app/testing/photo-editor-workspace-${width}.png`), Buffer.from((await command('Page.captureScreenshot', { format: 'png' })).data, 'base64'));
        assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'), true, 'Workspace fits screen');
        await click('#photoEditorClear');
        assert.equal(await evaluate('document.querySelector("[data-photo-download]").disabled'), true);
        assert.equal(await evaluate('document.getElementById("photoEditorPreview").hasAttribute("src")'), false);
        assert.deepEqual(await evaluate('window.errors'), []);
        console.log(`${width}px: editing, area adjustments, rotation, background, measured size, JPG/WebP downloads, retry, cancel and clear passed.`);
    }
    assert.equal(backgrounds, 2); assert.equal(downloads, 8);
    await command('Browser.close');
} finally { socket?.close(); browser.kill(); server.closeAllConnections(); await new Promise(resolve => server.close(resolve)); }
