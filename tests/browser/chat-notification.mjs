// Run with node tests/browser/chat-notification.mjs. Uses a local headless Chrome or Edge, no app/database required.
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

let widgetMarkup = await readFile(join(root, 'resources/views/layouts/partials/chat-widget.blade.php'), 'utf8');
widgetMarkup = widgetMarkup.replace(/^@if.*$/gm, '').replace(/^@endif.*$/gm, '').replace(/{{[\s\S]*?}}/g, '');
const people = [{id:1,name:'Recipient',online:true},{id:2,name:'SOR SENG LONG',online:true}];
const conversation = {id:42,type:'direct',title:'SOR SENG LONG',users:people,online:true,last_message:'Latest message',unread_messages:1};
const messages = Array.from({length:20}, (_, i) => ({id:i+1,user_id:i%2?1:2,user_name:i%2?'Recipient':'SOR SENG LONG',message:i===19?'Latest message':'Earlier message '+(i+1),message_type:'text',created_at:'14-Oct-2026 17:10',read_by:[],unread_by:[]}));
const server = createServer(async (request, response) => {
    const pathname = new URL(request.url, 'http://test').pathname;
    if (pathname.startsWith('/resources/') || pathname.startsWith('/node_modules/')) {
        const path = resolve(root, '.'+pathname);
        if (!path.startsWith(root)) { response.writeHead(403).end(); return; }
        response.setHeader('Content-Type', pathname.endsWith('.js')?'text/javascript':pathname.endsWith('.css')?'text/css':'font/woff2');
        response.end(await readFile(path)); return;
    }
    if (pathname.startsWith('/api/')) {
        response.setHeader('Content-Type', 'application/json');
        response.end(JSON.stringify(pathname==='/api/conversations'?[conversation]:pathname==='/api/users'?people:pathname==='/api/unread'?{unread:1}:/\/messages$/.test(pathname)?{conversation,messages}:{calls:[]})); return;
    }
    response.setHeader('Content-Type','text/html');
    response.end('<!doctype html><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/node_modules/@tabler/core/dist/css/tabler.min.css"><link rel="stylesheet" href="/node_modules/@tabler/icons-webfont/dist/tabler-icons.min.css"><link rel="stylesheet" href="/resources/css/pages/layouts-partials-chat-widget.css"><button id="chat-open-notification">Open Messages</button>'+widgetMarkup+'<script type="module">const widget=document.querySelector("#school-chat-widget");Object.assign(widget.dataset,{openOnLoad:"true",initialConversation:"42",currentUserId:"1",usersUrl:"/api/users",conversationsUrl:"/api/conversations",unreadUrl:"/api/unread",createUrl:"/api/create",heartbeatUrl:"/api/heartbeat",messagesBase:"/api/chat",callsPendingUrl:"/api/pending",callsBase:"/api/calls"});window.errors=[];window.alert=message=>window.errors.push(message);import "/resources/js/chatWidget.js";</script>');
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

    for (const mobile of [true, false]) {
        await command('Emulation.setDeviceMetricsOverride', {width:mobile?390:1280,height:mobile?844:900,deviceScaleFactor:1,mobile});
        await command('Page.navigate', {url:'http://127.0.0.1:'+server.address().port+'/'});
        await until('document.querySelector("#school-chat-conversation-title")?.textContent === "SOR SENG LONG"');
        await until('document.querySelector("[data-conversation-id]") !== null');
        assert.equal(await evaluate('window.errors.length'),0);
        const geometry = await evaluate('(() => {const r=document.querySelector("#school-chat-drawer").getBoundingClientRect(),f=document.querySelector("#school-chat-form").getBoundingClientRect();return {x:r.x,y:r.y,width:r.width,height:r.height,formBottom:f.bottom,formWidth:f.width};})()');
        if (mobile) {assert.equal(geometry.x,0);assert.equal(geometry.y,0);assert.equal(geometry.width,390);assert.equal(geometry.height,844);assert.ok(geometry.formBottom<=844);assert.ok(geometry.formWidth>350);}
        else assert.equal(geometry.width,380);
        assert.equal(await evaluate('document.querySelector("#school-chat-drawer").classList.contains("conversation-open")'),true);
        assert.equal(await evaluate('document.querySelector("#school-chat-messages").textContent.includes("Latest message")'),true);
        await delay(150);
        const screenshot = await command('Page.captureScreenshot',{format:'png'});
        await mkdir(join(root,'storage/app/testing'),{recursive:true});
        await writeFile(join(root,'storage/app/testing/chat-notification-'+(mobile?'mobile':'desktop')+'.png'),Buffer.from(screenshot.data,'base64'));
        await click('#school-chat-back');
        assert.equal(await evaluate('document.querySelector("#school-chat-drawer").classList.contains("conversation-open")'),false);
        assert.equal(await evaluate('document.querySelector("#school-chat-conversations-pane").classList.contains("d-none")'),false);
        await click('#school-chat-minimize');
        assert.equal(await evaluate('document.querySelector("#school-chat-drawer").classList.contains("d-none")'),true);
        await click('#chat-open-notification');
        await until('document.querySelector("#school-chat-drawer").classList.contains("conversation-open")');
        console.log((mobile?'Mobile':'Desktop')+': automatic correct conversation, layout, Back, minimize and reopen passed.');
    }
    await command('Browser.close');
} finally { socket?.close(); browser.kill(); server.closeAllConnections(); await new Promise(resolve=>server.close(resolve)); }
