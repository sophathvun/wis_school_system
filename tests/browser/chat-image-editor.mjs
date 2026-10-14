// Run with node tests/browser/chat-image-editor.mjs. Uses a local headless Chrome or Edge, no app/database required.
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { spawn } from 'node:child_process';
import { existsSync } from 'node:fs';
import { readFile, mkdtemp, mkdir, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';

const executable = process.env.CHAT_TEST_BROWSER || [
    'C:/Program Files/Google/Chrome/Application/chrome.exe',
    'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
    '/usr/bin/google-chrome', '/usr/bin/chromium',
].find(existsSync);
assert.ok(executable, 'Set CHAT_TEST_BROWSER to a Chrome/Edge executable to run browser checks.');
const root = resolve(import.meta.dirname, '../..');
const server = createServer(async (request, response) => {
    if (request.url === '/icons.css' || request.url.startsWith('/fonts/tabler-icons.')) {
        const filename = request.url === '/icons.css' ? 'tabler-icons.min.css' : request.url.slice(1).split('?')[0];
        response.setHeader('Content-Type', filename.endsWith('.css') ? 'text/css' : 'font/woff2');
        response.end(await readFile(join(root, 'node_modules/@tabler/icons-webfont/dist', filename)));
        return;
    }
    if (request.url === '/editor.js' || request.url === '/editor.css') {
        response.setHeader('Content-Type', request.url.endsWith('.js') ? 'text/javascript' : 'text/css');
        response.end(await readFile(join(root, request.url.endsWith('.js') ? 'resources/js/helpers/chatImageEditor.js' : 'resources/css/components/chat-image-editor.css')));
        return;
    }
    if (request.url === '/picture.svg') {
        response.setHeader('Content-Type', 'image/svg+xml');
        response.end('<svg xmlns="http://www.w3.org/2000/svg" width="800" height="1000"><rect width="800" height="1000" fill="#eef2ff"/><rect x="60" y="80" width="680" height="200" rx="30" fill="#1e3a5f"/><text x="100" y="180" font-size="42" fill="white">Staff message picture</text><path d="M100 380h600M100 480h600M100 580h600M100 680h600" stroke="#cbd5e1" stroke-width="20"/></svg>');
        return;
    }
    response.setHeader('Content-Type', 'text/html');
    response.end(`<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/icons.css"><link rel="stylesheet" href="/editor.css"></head><body><div id="messages"><a href="/picture.svg" data-chat-image data-image-name="staff-photo.jpg">Open picture</a></div><input id="composer"><script type="module">
        import { bindChatImageEditor } from '/editor.js';
        window.conversationId = 1; window.files = [];
        bindChatImageEditor(document.querySelector('#messages'), {
            getConversationId: () => window.conversationId,
            onAccept: file => window.files.push(file), getComposer: () => document.querySelector('#composer'),
        }); window.ready = true;
    </script></body></html>`);
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const profile = await mkdtemp(join(tmpdir(), 'wis-chat-editor-'));
// The disposable profile loads only the local fixture; disable the nested browser sandbox in restricted test environments.
const browser = spawn(executable, ['--headless=new', '--disable-gpu', '--no-sandbox', '--no-first-run', '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank'], { windowsHide: true, stdio: 'ignore' });
let socket;
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
try {
    let port;
    for (let attempt = 0; attempt < 100; attempt++) {
        try { port = Number((await readFile(join(profile, 'DevToolsActivePort'), 'utf8')).split('\n')[0]); break; } catch { await delay(100); }
    }
    assert.ok(port, 'Headless browser started');
    const pages = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket = new WebSocket(pages.find(page => page.type === 'page').webSocketDebuggerUrl);
    await new Promise((resolve, reject) => { socket.addEventListener('open', resolve, { once: true }); socket.addEventListener('error', reject, { once: true }); });
    let id = 0;
    const pending = new Map();
    socket.addEventListener('message', ({ data }) => {
        const message = JSON.parse(data);
        if (!pending.has(message.id)) return;
        const [resolve, reject] = pending.get(message.id); pending.delete(message.id);
        message.error ? reject(new Error(JSON.stringify(message.error))) : resolve(message.result);
    });
    const command = (method, params = {}) => new Promise((resolve, reject) => {
        const commandId = ++id;
        const timeout = setTimeout(() => { pending.delete(commandId); reject(new Error(`CDP timeout: ${method}`)); }, 10000);
        pending.set(commandId, [value => { clearTimeout(timeout); resolve(value); }, error => { clearTimeout(timeout); reject(error); }]);
        socket.send(JSON.stringify({ id: commandId, method, params }));
    });
    const evaluate = async expression => {
        const result = await command('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
        if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description || JSON.stringify(result.exceptionDetails));
        return result.result.value;
    };
    const until = async expression => {
        for (let attempt = 0; attempt < 100; attempt++) { if (await evaluate(expression)) return; await delay(50); }
        assert.fail(`Timed out: ${expression}`);
    };
    const click = selector => evaluate(`document.querySelector(${JSON.stringify(selector)}).click()`);
    await command('Page.enable');
    await command('Page.navigate', { url: `http://127.0.0.1:${server.address().port}/` });
    await until('window.ready === true');
    for (const mobile of [false, true]) {
        await command('Emulation.setDeviceMetricsOverride', { width: mobile ? 390 : 1280, height: mobile ? 844 : 900, deviceScaleFactor: 1, mobile });
        await command('Emulation.setTouchEmulationEnabled', { enabled: mobile });
        await click('[data-chat-image]');
        await until('document.querySelector("[data-image-edit]")?.disabled === false');
        assert.equal(await evaluate('document.querySelector("dialog").open'), true);
        await evaluate('window.baseImage = document.querySelector("canvas").toDataURL()');
        await click('[data-image-edit]');
        const rect = await evaluate('(() => { const r = document.querySelector("canvas").getBoundingClientRect(); return { x: r.x, y: r.y, width: r.width, height: r.height }; })()');
        assert.ok(rect.height > 100 && rect.width > 100);
        assert.ok(Math.abs(rect.width / rect.height - .8) < .01, 'Displayed canvas retains image proportions for accurate drawing');
        const x = rect.x + rect.width / 4, y = rect.y + rect.height / 3;
        if (mobile) {
            await command('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x, y }] });
            await command('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: x + 70, y: y + 50 }] });
            await command('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
        } else {
            await command('Input.dispatchMouseEvent', { type: 'mousePressed', x, y, button: 'left', clickCount: 1 });
            await command('Input.dispatchMouseEvent', { type: 'mouseMoved', x: x + 100, y: y + 70, buttons: 1 });
            await command('Input.dispatchMouseEvent', { type: 'mouseReleased', x: x + 100, y: y + 70, button: 'left', clickCount: 1 });
        }
        assert.equal(await evaluate('document.querySelector("[data-image-accept]").disabled'), false, 'Drawing creates a draft edit');
        await click('[data-image-tool="text"]');
        await evaluate('document.querySelector("[data-image-text]").value = "Please review សូមពិនិត្យ"');
        await click('[data-image-add-text]');
        const label = await evaluate('(() => { const r = document.querySelector("canvas").getBoundingClientRect(); return { x: r.x + r.width / 2, y: r.y + r.height / 2 }; })()');
        if (mobile) {
            await command('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [label] });
            await command('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: label.x + 20, y: label.y + 50 }] });
            await command('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
        } else {
            await command('Input.dispatchMouseEvent', { type: 'mousePressed', ...label, button: 'left', clickCount: 1 });
            await command('Input.dispatchMouseEvent', { type: 'mouseMoved', x: label.x + 20, y: label.y + 50, buttons: 1 });
            await command('Input.dispatchMouseEvent', { type: 'mouseReleased', x: label.x + 20, y: label.y + 50, button: 'left', clickCount: 1 });
        }
        await click('[data-image-tool="sticker"]');
        await click('[data-image-sticker]');
        await click('[data-image-undo]');
        await click('[data-image-tool="sticker"]'); await click('[data-image-sticker]');
        await click('[data-image-clear]');
        assert.equal(await evaluate('document.querySelector("[data-image-accept]").disabled'), true);
        assert.equal(await evaluate('document.querySelector("canvas").toDataURL() === window.baseImage'), true, 'Clear keeps original pixels');
        await click('[data-image-undo]');
        assert.equal(await evaluate('document.querySelector("[data-image-accept]").disabled'), false, 'Clear can be undone');
        const output = await command('Page.captureScreenshot', { format: 'png' });
        const artifact = join(root, 'storage/app/testing');
        await mkdir(artifact, { recursive: true });
        await writeFile(join(artifact, `chat-image-editor-${mobile ? 'mobile' : 'desktop'}.png`), Buffer.from(output.data, 'base64'));
        await click('[data-image-accept]');
        await until('!document.querySelector("dialog")');
        assert.equal(await evaluate('window.files.at(-1).name'), 'staff-photo-edited.png');
        assert.equal(await evaluate('window.files.at(-1).type'), 'image/png');
        assert.ok(await evaluate('window.files.at(-1).size > 100'));
        assert.equal(await evaluate('document.activeElement.id'), 'composer');
        console.log(`${mobile ? 'Mobile touch' : 'Desktop mouse'}: drawing, text, stickers, undo, clear, PNG attachment and focus passed.`);
    }
    // A conversation switch must not send an edited picture to a different recipient.
    await click('[data-chat-image]');
    await until('document.querySelector("[data-image-edit]")?.disabled === false');
    await click('[data-image-edit]'); await click('[data-image-tool="sticker"]'); await click('[data-image-sticker]');
    await evaluate('window.conversationId = 2'); await click('[data-image-accept]');
    await until('document.querySelector(".chat-image-editor-status").textContent.includes("conversation changed")');
    assert.equal(await evaluate('window.files.length'), 2, 'No file is attached to a different conversation');
    await click('[data-image-close]');
    await until('!document.querySelector("dialog")');
    await click('[data-chat-image]');
    await until('document.querySelector("[data-image-edit]")?.disabled === false');
    await click('[data-image-edit]'); await click('[data-image-tool="sticker"]'); await click('[data-image-sticker]');
    await click('[data-image-cancel]');
    assert.equal(await evaluate('document.querySelector("canvas").toDataURL() === window.baseImage'), true);
    await click('[data-image-close]');
    await until('!document.querySelector("dialog")');
    assert.equal(await evaluate('window.files.length'), 2, 'Cancelling never attaches or sends a file');
    console.log('Conversation isolation passed. Screenshots saved in storage/app/testing.');
    await command('Browser.close');
} finally {
    socket?.close(); browser.kill(); server.closeAllConnections(); await new Promise(resolve => server.close(resolve));
}
