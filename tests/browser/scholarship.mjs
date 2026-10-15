// Run with node tests/browser/scholarship.mjs. Uses a local headless Chrome or Edge, no app/database required.
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { spawn, execFileSync } from 'node:child_process';
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


const php = process.env.CHAT_TEST_PHP || 'C:/laragon/bin/php/php-8.4.25-Win32-vs17-x64/php.exe';
const markup = execFileSync(php, [join(root,'tests/browser/scholarship-fixture.php')], {cwd:root,encoding:'utf8'});
const savedRankMarkup=execFileSync(php,[join(root,'tests/browser/scholarship-fixture.php'),'rank-saved'],{cwd:root,encoding:'utf8'});
const editRankMarkup=execFileSync(php,[join(root,'tests/browser/scholarship-fixture.php'),'rank-edit'],{cwd:root,encoding:'utf8'});
const tabs=Object.fromEntries(['assign-rank','settings','seat-plan','scoring','result','exam-committee'].map(tab=>[tab,execFileSync(php,[join(root,'tests/browser/scholarship-fixture.php'),tab],{cwd:root,encoding:'utf8'})]));
tabs.awards=execFileSync(php,[join(root,'tests/browser/scholarship-fixture.php'),'result','draft'],{cwd:root,encoding:'utf8'});
tabs['settings-new']=execFileSync(php,[join(root,'tests/browser/scholarship-fixture.php'),'settings','new'],{cwd:root,encoding:'utf8'});
tabs['seat-plan-centers']=execFileSync(php,[join(root,'tests/browser/scholarship-fixture.php'),'seat-plan','centers'],{cwd:root,encoding:'utf8'});
tabs['seat-plan-confirmed']=execFileSync(php,[join(root,'tests/browser/scholarship-fixture.php'),'seat-plan','centers-locked'],{cwd:root,encoding:'utf8'});
tabs['committee-centers']=execFileSync(php,[join(root,'tests/browser/scholarship-fixture.php'),'exam-committee','centers'],{cwd:root,encoding:'utf8'});
tabs['seat-plan-pager']=execFileSync(php,[join(root,'tests/browser/scholarship-fixture.php'),'seat-plan','pagination'],{cwd:root,encoding:'utf8'});
for(const [name,html]of Object.entries({baseline:markup,...tabs}))assert.ok(html.includes('data-scholarship-page'),name+' fixture failed to render: '+html.slice(0,600));
const queries = [];
const filterQueries=[];
const assigned=new Set(),saveRequests=[];
const printSettingsStore=new Map(),printSettingSaves=[];
const mockSigners=campus=>['PREPARER','VERIFIER','APPROVER'].flatMap((role,index)=>[false,true].map(updated=>({id:Number(campus)*100+index+1+(updated?10:0),code:`HRM-${campus}-${index+1}`,name:updated?`UPDATED CAMPUS ${role}`:`CAMPUS ${campus} ${role}`,position:['Registrar','Academic Director','Vice President'][index]})));
const rankMarkup=markup.match(/<section class="card mt-3" data-scholarship-ranks-panel[\s\S]*?<\/section>/)?.[0];
assert.ok(rankMarkup,'Rank list partial must render in the fixture');
const appCss=await readFile(join(root,'resources/css/app.css'),'utf8');
execFileSync(php,[join(root,'tests/browser/scholarship-print-fixture.php'),'75'],{cwd:root});
execFileSync(php,[join(root,'tests/browser/scholarship-print-fixture.php'),'20','campus'],{cwd:root});
execFileSync(php,[join(root,'tests/browser/scholarship-print-fixture.php'),'25'],{cwd:root});
const paginationCss=appCss.slice(appCss.indexOf('.premium-pagination {'),appCss.indexOf('.card-footer:has(.premium-pagination) .row'))+appCss.slice(appCss.indexOf('.premium-pagination-list {'),appCss.indexOf('/* Responsive form and modal baseline */'));
const paginatedRanks=(html,params)=>{
    const total=63,size=params.get('per_page')||'10',perPage=size==='all'?total:Number(size);
    const last=Math.ceil(total/perPage),page=size==='all'?1:Math.max(1,Math.min(last,Number(params.get('page')||1)));
    const from=(page-1)*perPage+1,to=Math.min(total,page*perPage);
    const row=rankMarkup.match(/<tbody>([\s\S]*?)<\/tbody>/)[1].trim().replace(/https?:\/\/[^"\s]*\/storage\/testing\/scholarship-photo\.svg/g,'/storage/testing/scholarship-photo.svg');
    const rows=Array.from({length:to-from+1},(_,i)=>row.replace('>1</td>',`>${from+i}</td>`).replaceAll('2026001','PAGER-'+(from+i)).replaceAll('CHEAN CHAMNUNY','PAGER STUDENT '+(from+i))).join('');
    const meta=JSON.stringify({current_page:page,last_page:last,from,to,total}).replaceAll('"','&quot;');
    return html.replace(/<tbody>[\s\S]*?<\/tbody>/,`<tbody>${rows}</tbody>`).replace(/data-rank-pagination="[^"]*"/,`data-rank-pagination="${meta}"`);
};
const server = createServer(async (request,response) => {
    const url = new URL(request.url,'http://test'), pathname=url.pathname;
    if(url.searchParams.has('seat_list')){
        response.setHeader('Content-Type','application/json');
        const data=JSON.parse(execFileSync(php,[join(root,'tests/browser/scholarship-fixture.php'),'seat-plan','pagination',url.searchParams.toString()],{cwd:root,encoding:'utf8'}));
        data.html=data.html.replace(/https?:\/\/[^"\s]*\/storage\/testing\/scholarship-photo\.svg/g,'/storage/testing/scholarship-photo.svg');
        response.end(JSON.stringify(data));return;
    }
    if(pathname==='/api/exam-center-campuses'){
        response.setHeader('Content-Type','application/json');
        if(url.searchParams.has('fail')){response.statusCode=403;response.end('{}');return;}
        const year=url.searchParams.get('academic_year_id');
        setTimeout(()=>response.end(JSON.stringify({campuses:year==='1'?[{id:1,label:'BCH',count:3}]:[{id:2,label:'BKK',count:5}]})),year==='1'?100:10);return;
    }
    if(pathname==='/api/print-settings'){
        response.setHeader('Content-Type','application/json');
        if(request.method==='POST'){
            const chunks=[];for await(const chunk of request)chunks.push(chunk);
            const values=Object.fromEntries(await new Response(Buffer.concat(chunks),{headers:{'Content-Type':request.headers['content-type']}}).formData());
            for(const role of ['prepared','checked','approved']){const signer=mockSigners(values.campus_id).find(person=>String(person.id)===values[`${role}_staff_id`]);values[`${role}_by_name`]=signer?.name;values[`${role}_position`]=signer?.position;}
            printSettingsStore.set(`${values.academic_year_id}:${values.campus_id}`,values);printSettingSaves.push(values);
            response.end(JSON.stringify({message:(values.campus_id==='3'?'Central':'Campus')+' print settings saved.'}));return;
        }
        const year=url.searchParams.get('academic_year_id'),campus=url.searchParams.get('campus_id')||'3';
        const values=printSettingsStore.get(`${year}:${campus}`)||{prepared_by_name:`CAMPUS ${campus} PREPARER`,prepared_date:'2026-10-01',checked_by_name:`CAMPUS ${campus} VERIFIER`,checked_date:'2026-10-05',approved_by_name:`CAMPUS ${campus} APPROVER`,approved_date:'2026-10-08'};
        ['prepared','checked','approved'].forEach((role,index)=>{values[`${role}_staff_id`]??=Number(campus)*100+index+1;});
        response.end(JSON.stringify({academic_year:{id:Number(year),label:year==='1'?'2025-2026':'2026-2027'},campuses:[{id:1,label:'BCH',is_central:false},{id:2,label:'BKK',is_central:false},{id:3,label:'Central — All Campuses (CEN)',is_central:true}],campus_id:Number(campus),requires_verifier:campus==='3',values,staff:mockSigners(campus)}));return;
    }
    if(pathname==='/test-outstanding-report'){
        response.setHeader('Content-Type','text/html; charset=utf-8');
        response.end((await readFile(join(root,'storage/app/testing/scholarship-outstanding-'+(url.searchParams.has('campus')?'campus-20':url.searchParams.has('short')?'25':'75')+'.html'),'utf8')).replace(/https?:\/\/[^/'"\s]+\/fonts\//g,'/fonts/'));return;
    }
    if(pathname.startsWith('/fonts/')){response.setHeader('Content-Type','font/ttf');response.end(await readFile(join(root,'public',pathname)));return;}
    if(pathname==='/test-pagination.css'){response.setHeader('Content-Type','text/css');response.end(paginationCss);return;}
    if(pathname==='/test-app.css'){response.setHeader('Content-Type','text/css');response.end(appCss.replace(/^@import .*;\r?\n/gm,''));return;}
    if(pathname==='/test-premium-forms.js'){response.setHeader('Content-Type','text/javascript');response.end((await readFile(join(root,'resources/js/premiumForms.js'),'utf8')).replace(/import \{ showError \} from "\.\/helpers\/sweet-alert2";/,'const showError=()=>{};'));return;}
    if(pathname==='/api/save-rank'){
        const chunks=[];for await(const chunk of request)chunks.push(chunk);
        const values=Object.fromEntries(await new Response(Buffer.concat(chunks),{headers:{'Content-Type':request.headers['content-type']}}).formData());
        saveRequests.push(values);response.setHeader('Content-Type','application/json');
        if(values.eoy_average==='91'){response.statusCode=422;response.end(JSON.stringify({errors:{eoy_average:['Check EOY Avg.: Rank 2 must be lower than Rank 1.']}}));return;}
        assigned.add(`${values.academic_year_id}:${values.enrollment_id}`);response.end(JSON.stringify({message:'Class rank saved successfully.'}));return;
    }
    if(pathname==='/storage/testing/scholarship-photo.svg'){
        response.setHeader('Content-Type','image/svg+xml');response.end('<svg xmlns="http://www.w3.org/2000/svg" width="600" height="800"><rect width="600" height="800" fill="#dbeafe"/><circle cx="300" cy="250" r="100" fill="#293959"/><path d="M100 700V550a200 200 0 0 1 400 0v150Z" fill="#293959"/></svg>');return;
    }
    if (pathname.startsWith('/resources/') || pathname.startsWith('/node_modules/')) {
        response.setHeader('Content-Type',pathname.endsWith('.js')?'text/javascript':pathname.endsWith('.css')?'text/css':'font/woff2');
        response.end(await readFile(join(root,pathname)));return;
    }
    if (pathname==='/api/students') {
        queries.push(Object.fromEntries(url.searchParams));
        const item = url.searchParams.get('grade_class')==='2:2'||url.searchParams.get('academic_year_id')==='2'
            ? {id:2,label:'2026002 · SECOND STUDENT',rank:null}
            : {id:1,label:'2026001 · CHEAN CHAMNUNY',rank:null,eoy_average:null};
        const term = (url.searchParams.get('q')||'').toLowerCase();
        response.setHeader('Content-Type','application/json');response.end(JSON.stringify({data:!assigned.has(`${url.searchParams.get('academic_year_id')}:${item.id}`) && item.label.toLowerCase().includes(term)?[item]:[],has_more:false}));return;
    }
    if(pathname==='/api/rank-filters') {
        const filters=Object.fromEntries(url.searchParams);filterQueries.push(filters);
        const year=filters.academic_year_id;
        const campuses=year==='1'?[{id:1,label:'BCH',can_assign:true},{id:2,label:'BKK',can_assign:true}]:year==='2'?[{id:2,label:'BKK',can_assign:true}]:[];
        if(!campuses.some(c=>String(c.id)===filters.campus_id))['campus_id','grade_class','enrollment_id'].forEach(k=>delete filters[k]);
        const grades=!filters.campus_id?[]:filters.campus_id==='1'?[{id:'1:1',label:'3A'},{id:'2:2',label:'4B'}]:[{id:'2:2',label:'4B'}];
        if(!grades.some(g=>g.id===filters.grade_class))['grade_class','enrollment_id'].forEach(k=>delete filters[k]);
        const scope=[year||'',filters.campus_id||'',filters.grade_class||'',filters.enrollment_id||''].join('/');
        let html=rankMarkup.replace('data-scholarship-ranks-panel','data-scholarship-ranks-panel data-filter-scope="'+scope+'"').replace(/https?:\/\/[^"\s]*\/storage\/testing\/scholarship-photo\.svg/g,'/storage/testing/scholarship-photo.svg');
        if(year==='2')html=html.replaceAll('2025-2026','2026-2027');
        if(filters.grade_class==='2:2')html=html.replaceAll('CHEAN CHAMNUNY','SECOND STUDENT');
        if(url.searchParams.has('pagination'))html=paginatedRanks(html,url.searchParams);
        response.setHeader('Content-Type','application/json');
        setTimeout(()=>response.end(JSON.stringify({filters,campuses,grades,ranks_locked:false,html})),year==='1'?100:10);return;
    }
    response.setHeader('Content-Type','text/html; charset=utf-8');
    if(pathname==='/' && !url.searchParams.has('rank_saved') && !url.searchParams.has('rank_edit'))assigned.clear();
    if(pathname==='/' && (url.searchParams.size===0||url.searchParams.has('premium_forms')))printSettingsStore.clear();
    let pageMarkup=(url.searchParams.has('rank_saved')?savedRankMarkup:url.searchParams.has('rank_edit')?editRankMarkup:(tabs[url.searchParams.get('fixture_tab')]||markup)).replace(/https?:\/\/[^"\s]*\/storage\/testing\/scholarship-photo\.svg/g,'/storage/testing/scholarship-photo.svg');
    if(url.searchParams.has('pagination'))pageMarkup=pageMarkup.replace(/<section class="card mt-3" data-scholarship-ranks-panel[\s\S]*?<\/section>/,html=>paginatedRanks(html,url.searchParams));
    pageMarkup=pageMarkup.replace(/action="[^"]*\/ranks\/print-settings"/g,'action="/api/print-settings"');
    pageMarkup=pageMarkup.replace(/data-center-campuses-url="[^"]*"/g,'data-center-campuses-url="/api/exam-center-campuses"');
    if(url.searchParams.has('app_shell'))pageMarkup='<link rel="stylesheet" href="/test-app.css"><style>body{padding:0!important}</style><div class="page"><div class="page-wrapper"><div class="page-header"><div class="container-fluid"><h2>WIS Scholarship</h2></div></div><div class="page-body"><div class="container-fluid">'+pageMarkup+'</div></div><footer class="footer"><div class="container-fluid text-center">WIS School</div></footer></div></div>';
    if(url.searchParams.has('premium_forms'))pageMarkup='<link rel="stylesheet" href="/test-app.css">'+pageMarkup+'<script type="module" src="/test-premium-forms.js"></script>';
response.end('<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/node_modules/@tabler/core/dist/css/tabler.min.css"><link rel="stylesheet" href="/node_modules/@tabler/icons-webfont/dist/tabler-icons.min.css"><link rel="stylesheet" href="/resources/css/pages/western-scholarship.css"><link rel="stylesheet" href="/test-pagination.css"><style>body{padding:20px}</style>'+(pageMarkup)+'<script>document.querySelector("[data-scholarship-page]").dataset.studentsUrl="/api/students";document.querySelector("[data-scholarship-page]").dataset.rankFiltersUrl="/api/rank-filters";document.querySelector("[data-scholarship-rank-form]")?.setAttribute("action","/api/save-rank");document.querySelector("[data-scholarship-rank-form]")?.addEventListener("submit",e=>{e.preventDefault();window.saved=Object.fromEntries(new FormData(e.target));});</script><script type="importmap">{"imports":{"bootstrap":"/node_modules/bootstrap/dist/js/bootstrap.esm.js","@popperjs/core":"/node_modules/@popperjs/core/lib/index.js","sweetalert2":"/node_modules/sweetalert2/dist/sweetalert2.esm.all.js"}}</script><script type="module" src="/resources/js/westernScholarship.js"></script>');
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
    await command('Emulation.setFocusEmulationEnabled',{enabled:true});


    await mkdir(join(root,'storage/app/testing'),{recursive:true});
    if(!process.env.SCHOLARSHIP_PRINT_ONLY){
    for (const mobile of [false,true]) {
        await command('Emulation.setDeviceMetricsOverride',{width:mobile?390:1440,height:mobile?844:980,deviceScaleFactor:1,mobile});
        await command('Emulation.setTouchEmulationEnabled',{enabled:mobile});
        await command('Page.navigate',{url:'http://127.0.0.1:'+server.address().port+'/?premium_forms=1'});
        await until('document.querySelector("#scholarshipStudent option[value=\\"1\\"]") !== null');
        assert.equal(await evaluate('document.querySelector("[data-rank-filters-toggle]").getAttribute("aria-expanded")'),mobile?'false':'true');
        assert.equal(await evaluate('document.querySelector("#scholarshipRankFilters").hidden'),mobile);
        assert.ok(await evaluate('document.querySelector("[data-scholarship-ranks-panel]").getBoundingClientRect().height>0'),'Assigned ranks remain visible when the form is collapsed');
        assert.deepEqual(await evaluate('[...document.querySelectorAll(".scholarship-rank-exports a")].map(a=>a.textContent.trim())'),['Print','Excel','PDF']);
        assert.deepEqual(await evaluate('[...document.querySelectorAll("[data-rank-count]")].map(el=>el.textContent.trim())'),['1','0','0','1']);
        assert.equal(await evaluate('getComputedStyle(document.querySelector(".scholarship-rank-summary")).color'),'rgb(255, 223, 107)');
        assert.equal(await evaluate(`(()=>{const header=document.querySelector('.scholarship-ranks-header').getBoundingClientRect();return [...document.querySelectorAll('.scholarship-rank-exports a')].every(el=>{const rect=el.getBoundingClientRect();return rect.left>=header.left && rect.right<=header.right;});})()`),true,'All export buttons fit the header');
        if(!mobile)assert.equal(await evaluate(`(()=>{const title=document.querySelector('.scholarship-ranks-header h3').getBoundingClientRect(),summary=document.querySelector('.scholarship-rank-summary').getBoundingClientRect(),buttons=document.querySelector('.scholarship-rank-exports').getBoundingClientRect();return title.right<=summary.left && summary.right<=buttons.left && Math.abs(title.top-summary.top)<12;})()`),true,'Desktop header places yellow counts between title and buttons');
        const headerClip=await evaluate(`(()=>{const r=document.querySelector('.scholarship-ranks-header').getBoundingClientRect();return {x:r.x+scrollX,y:r.y+scrollY,width:r.width,height:r.height,scale:1};})()`);
        const headerShot=await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:true,clip:headerClip});
        await writeFile(join(root,'storage/app/testing/scholarship-rank-header-'+(mobile?'mobile':'desktop')+'.png'),Buffer.from(headerShot.data,'base64'));
        await evaluate('new Promise(resolve=>{document.querySelector("#scholarshipPrintSettingsModal").addEventListener("shown.bs.modal",resolve,{once:true});document.querySelector("[data-rank-print-settings]").click();})');
        await until('document.querySelector("#rankPrintPreparedBy").value==="CAMPUS 1 PREPARER" && !document.querySelector("[data-save-print-settings]").disabled');
        assert.equal(await evaluate('document.querySelector("#rankPrintYear").value'),'2025-2026');
        assert.equal(await evaluate('document.querySelector("#rankPrintCampus").value'),'1');
        assert.equal(await evaluate('document.querySelector("#scholarshipPrintSettingsTitle").textContent'),'Campus Print Settings');
        assert.equal(await evaluate('document.querySelector("[data-print-central-shortcut]").hidden'),false,'Authorized staff have a visible Central Print Settings shortcut');
        assert.equal(await evaluate(`(()=>{const modal=document.querySelector('#scholarshipPrintSettingsModal .modal-content').getBoundingClientRect();return modal.width<=480 && modal.height<720 && modal.left>=0 && modal.right<=innerWidth;})()`),true,'The settings form fits desktop and phone screens');
        await until('document.querySelector("#rankPrintPreparedBy").closest(".premium-form-field")!==null');
        assert.equal(await evaluate(`(()=>{const form=document.querySelector('[data-rank-print-settings-form]');return [...form.querySelectorAll('.scholarship-print-field')].filter(field=>field.getClientRects().length).every(field=>{const label=field.querySelector(':scope > label').getBoundingClientRect(),input=field.querySelector(':scope > .form-control,:scope > .form-select'),control=input.getBoundingClientRect(),style=getComputedStyle(input);return label.bottom<=control.top+parseFloat(style.paddingTop) && label.left>=control.left && label.right<=control.right;});})()`),true,'Shared floating labels stay above values and inside every field');
        assert.equal(await evaluate('document.querySelectorAll("[data-print-signature-role]:not([hidden])").length'),2,'Campus settings have two signers');
        assert.equal(await evaluate('document.querySelector("#rankPrintCheckedBy").disabled && !document.querySelector("#rankPrintCheckedBy").required'),true,'The campus verifier is disabled and not required');
        for(const role of ['prepared','approved']){
            const picker=`[name="${role}_date"]`;
            await evaluate(`document.querySelector('${picker}').closest('[data-premium-date-picker]').querySelector('[data-premium-date-toggle]').click()`);
            assert.equal(await evaluate(`document.querySelector('${picker}').closest('[data-premium-date-picker]').querySelector('[data-premium-date-toggle]').getAttribute('aria-expanded')`),'true');
            assert.equal(await evaluate(`(()=>{const picker=document.querySelector('${picker}').closest('[data-premium-date-picker]'),popup=picker.querySelector('.date-picker-popup').getBoundingClientRect(),body=picker.closest('.modal-body').getBoundingClientRect();return popup.left>=body.left && popup.right<=body.right && popup.top>=body.top && popup.bottom<=body.bottom;})()`),true,'Each calendar stays inside the scrolling modal');
            if(role==='prepared'){
                await click('.signature-date.is-open .date-picker-year-toggle');
                await click('.signature-date.is-open [data-premium-year="2025"]');
                assert.equal(await evaluate('document.querySelector(".signature-date.is-open .date-picker-year-toggle").textContent'),'October 2025');
                await click('.signature-date.is-open [data-premium-date-next]');
                assert.equal(await evaluate('document.querySelector(".signature-date.is-open .date-picker-year-toggle").textContent'),'November 2025');
                await click('.signature-date.is-open [data-premium-date-prev]');
                await click('.signature-date.is-open [data-premium-day="2025-10-20"]');
                assert.equal(await evaluate('document.querySelector("[name=prepared_date]").value'),'2025-10-20');
                assert.equal(await evaluate('document.querySelector("#rankPrintPreparedDate").value'),'20-Oct-2025');
                await evaluate(`document.querySelector('${picker}').closest('[data-premium-date-picker]').querySelector('[data-premium-date-toggle]').click()`);
            }
            if(role==='approved'){
                const calendarShot=await command('Page.captureScreenshot',{format:'png'});
                await writeFile(join(root,'storage/app/testing/scholarship-print-calendar-'+(mobile?'mobile':'desktop')+'.png'),Buffer.from(calendarShot.data,'base64'));
                if(mobile){
                    await command('Emulation.setDeviceMetricsOverride',{width:390,height:500,deviceScaleFactor:1,mobile:true});
                    await delay(100);
                    assert.equal(await evaluate(`(()=>{const popup=document.querySelector('.signature-date.is-open .date-picker-popup').getBoundingClientRect(),body=document.querySelector('#scholarshipPrintSettingsModal .modal-body').getBoundingClientRect(),today=document.querySelector('.signature-date.is-open [data-premium-date-today]').getBoundingClientRect();return popup.top>=body.top && popup.bottom<=body.bottom && today.bottom<=body.bottom;})()`),true,'Calendar and Today remain reachable when the phone viewport shrinks');
                    await command('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
                }
            }
            await click('.signature-date.is-open [data-premium-date-today]');
            assert.equal(await evaluate(`(()=>{const now=new Date(),pad=n=>String(n).padStart(2,'0');return document.querySelector('${picker}').value===now.getFullYear()+'-'+pad(now.getMonth()+1)+'-'+pad(now.getDate());})()`),true,'Today selects the local date');
            assert.equal(await evaluate('document.querySelectorAll(".signature-date.is-open").length'),0,'Selecting Today closes the calendar');
        }
        const printSaveCount=printSettingSaves.length;
        await evaluate('document.querySelector("#rankPrintApprovedBy").value="";document.querySelector("#rankPrintCheckedBy").value="";document.querySelector("#rankPrintPreparedDate").value="31-02-2026";document.querySelector("[data-rank-print-settings-form]").requestSubmit()');
        assert.equal(await evaluate('document.querySelector("#rankPrintApprovedBy").getAttribute("aria-invalid")'),'true');
        assert.equal(await evaluate('document.querySelector("#rankPrintCheckedBy").getAttribute("aria-invalid")'),null,'Hidden verifier fields do not block campus saving');
        assert.equal(await evaluate('document.querySelector("#rankPrintPreparedDate").getAttribute("aria-invalid")'),'true','Invalid typed dates receive feedback on the visible field');
        assert.equal(printSettingSaves.length,printSaveCount,'Blank signature names do not save');
        for(const [role,id,name,position] of [['prepared',111,'UPDATED CAMPUS PREPARER','Registrar'],['approved',113,'UPDATED CAMPUS APPROVER','Vice President']]){
            await evaluate(`(()=>{const input=document.querySelector('[data-signature-role="${role}"] input[data-staff-input]');input.focus();input.value=${JSON.stringify(name)};input.dispatchEvent(new Event('input',{bubbles:true}));})()`);
            await click(`[data-signature-role="${role}"] [data-staff-id="${id}"]`);
            assert.equal(await evaluate(`document.querySelector('[data-staff-position="${role}"]').textContent`),`Position: ${position}`,'Selecting HRM staff fills their position');
        }
        await evaluate('document.querySelector("#rankPrintPreparedDate").value="2026-10-02";document.querySelector("#rankPrintCheckedDate").value="2026-10-06";document.querySelector("#rankPrintApprovedDate").value="2026-10-09";new Promise(resolve=>{document.querySelector("#scholarshipPrintSettingsModal").addEventListener("hidden.bs.modal",resolve,{once:true});document.querySelector("[data-rank-print-settings-form]").requestSubmit();})');
        assert.equal(printSettingSaves.length,printSaveCount+1);
        assert.equal(printSettingSaves.at(-1).campus_id,'1');
        assert.equal(printSettingSaves.at(-1).approved_date,'2026-10-09');
        assert.equal(Object.hasOwn(printSettingSaves.at(-1),'checked_date'),false,'Campus requests omit the hidden verifier date');
        assert.equal(Object.hasOwn(printSettingSaves.at(-1),'checked_staff_id'),false,'Campus requests omit the hidden verifier');
        assert.equal(printSettingSaves.at(-1).prepared_staff_id,'111');
        await evaluate('new Promise(resolve=>{document.querySelector("#scholarshipPrintSettingsModal").addEventListener("shown.bs.modal",resolve,{once:true});document.querySelector("[data-rank-print-settings]").click();})');
        await until('document.querySelector("#rankPrintPreparedBy").value==="UPDATED CAMPUS PREPARER" && !document.querySelector("[data-save-print-settings]").disabled');
        assert.equal(await evaluate('document.querySelector("[data-print-signature-role=checked]").hidden'),true);
        await evaluate('document.querySelector("#rankPrintCampus").value="2";document.querySelector("#rankPrintCampus").dispatchEvent(new Event("change",{bubbles:true}))');
        await until('document.querySelector("#rankPrintPreparedBy").value==="CAMPUS 2 PREPARER" && !document.querySelector("[data-save-print-settings]").disabled');
        assert.equal(await evaluate('document.querySelector("#rankPrintApprovedDate").value'),'08-Oct-2026','A different campus retains its own dates');
        const settingsShot=await command('Page.captureScreenshot',{format:'png'});
        await writeFile(join(root,'storage/app/testing/scholarship-print-settings-'+(mobile?'mobile':'desktop')+'.png'),Buffer.from(settingsShot.data,'base64'));
        await click('[data-print-central-shortcut]');
        await until('document.querySelector("#rankPrintCampus").value==="3" && !document.querySelector("[data-save-print-settings]").disabled');
        assert.equal(await evaluate('document.querySelector("#scholarshipPrintSettingsTitle").textContent'),'Central Print Settings');
        assert.equal(await evaluate('document.querySelector("[data-print-scope-description]").textContent'),'Choose HRM staff for the all-campus report; positions fill automatically.');
        assert.equal(await evaluate('document.querySelector("[data-print-central-shortcut]").hidden'),true);
        assert.equal(await evaluate('document.querySelectorAll("[data-print-signature-role]:not([hidden])").length'),3,'Central settings have three signers');
        const centralSaveCount=printSettingSaves.length;
        await evaluate('document.querySelector("#rankPrintCheckedBy").value="";document.querySelector("[data-rank-print-settings-form]").requestSubmit()');
        assert.equal(printSettingSaves.length,centralSaveCount,'Central requires a selected verifier');
        assert.equal(await evaluate('document.querySelector("#rankPrintCheckedBy").getAttribute("aria-invalid")'),'true');
        await evaluate('document.querySelector("#rankPrintCheckedBy").focus()');
        await click('[data-signature-role="checked"] [data-staff-id="302"]');
        await evaluate('document.querySelector("#rankPrintCheckedDate").closest("[data-premium-date-picker]").querySelector("[data-premium-date-toggle]").click()');
        assert.equal(await evaluate('document.querySelectorAll(".signature-date.is-open [data-premium-date-today]").length'),1,'Central verifier uses the premium calendar');
        await click('.signature-date.is-open [data-premium-date-today]');
        await evaluate('document.querySelector("#rankPrintCheckedDate").value="06-Oct-2026"');
        await evaluate('document.querySelector("#rankPrintPreparedBy").focus()');
        assert.deepEqual(await evaluate('[...document.querySelectorAll("[data-signature-role=prepared] [data-staff-id]")].map(option=>Number(option.dataset.staffId)).sort((a,b)=>a-b)'),[301,302,303,311,312,313],'Central staff choices exclude other campuses');
        await evaluate('document.querySelector("#rankPrintPreparedBy").blur()');
        await until('document.querySelector("[data-signature-role=prepared] [data-staff-results]").hidden');
        const centralShot=await command('Page.captureScreenshot',{format:'png'});
        await writeFile(join(root,'storage/app/testing/scholarship-central-print-settings-'+(mobile?'mobile':'desktop')+'.png'),Buffer.from(centralShot.data,'base64'));
        await evaluate('new Promise(resolve=>{document.querySelector("#scholarshipPrintSettingsModal").addEventListener("hidden.bs.modal",resolve,{once:true});document.querySelector("[data-rank-print-settings-form]").requestSubmit();})');
        assert.equal(printSettingSaves.at(-1).campus_id,'3','Central settings save under the Central scope');
        assert.equal(printSettingSaves.at(-1).checked_staff_id,'302');
        assert.equal(printSettingSaves.at(-1).checked_date,'2026-10-06');
        await evaluate('new Promise(resolve=>{document.querySelector("#scholarshipPrintSettingsModal").addEventListener("shown.bs.modal",resolve,{once:true});document.querySelector("[data-rank-print-settings]").click();})');
        await until('document.querySelector("#rankPrintPreparedBy").value==="UPDATED CAMPUS PREPARER" && !document.querySelector("[data-save-print-settings]").disabled');
        assert.equal(await evaluate('document.querySelector("#scholarshipPrintSettingsTitle").textContent'),'Campus Print Settings','Reopening the campus scope keeps campus settings separate');
        assert.equal(await evaluate('document.querySelectorAll("[data-print-signature-role]:not([hidden])").length'),2,'Switching back to campus hides the verifier again');
        await evaluate('document.documentElement.setAttribute("data-bs-theme","dark")');
        await delay(200);
        assert.match(await evaluate('getComputedStyle(document.querySelector("#scholarshipPrintSettingsModal .scholarship-print-field > label")).color'),/^rgba?\(167, 181, 201(?:, 1)?\)$/,'Dark theme keeps field labels readable');
        const darkSettingsShot=await command('Page.captureScreenshot',{format:'png'});
        await writeFile(join(root,'storage/app/testing/scholarship-print-settings-dark-'+(mobile?'mobile':'desktop')+'.png'),Buffer.from(darkSettingsShot.data,'base64'));
        await evaluate('document.documentElement.removeAttribute("data-bs-theme")');
        await delay(200);
        if(mobile){
            await command('Emulation.setDeviceMetricsOverride',{width:320,height:568,deviceScaleFactor:1,mobile:true});
            assert.equal(await evaluate(`(()=>{const modal=document.querySelector('#scholarshipPrintSettingsModal'),body=modal.querySelector('.modal-body'),footer=modal.querySelector('.modal-footer').getBoundingClientRect();return modal.scrollWidth<=innerWidth && body.scrollHeight>body.clientHeight && footer.bottom<=innerHeight && footer.top>=0;})()`),true,'Small phones scroll the form while keeping actions visible');
            await evaluate('document.querySelector("#rankPrintApprovedDate").scrollIntoView({block:"nearest"});document.querySelector("#rankPrintApprovedDate").closest("[data-premium-date-picker]").querySelector("[data-premium-date-toggle]").click()');
            assert.equal(await evaluate(`(()=>{const popup=document.querySelector('.signature-date.is-open .date-picker-popup').getBoundingClientRect(),body=document.querySelector('#scholarshipPrintSettingsModal .modal-body').getBoundingClientRect();return popup.left>=body.left && popup.right<=body.right && popup.top>=body.top && popup.bottom<=body.bottom;})()`),true,'The last date calendar also fits a small phone');
            const smallSettingsShot=await command('Page.captureScreenshot',{format:'png'});
            await writeFile(join(root,'storage/app/testing/scholarship-print-settings-small-phone.png'),Buffer.from(smallSettingsShot.data,'base64'));
            await click('.signature-date.is-open [data-premium-date-today]');
            await command('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
        }
        await evaluate('new Promise(resolve=>{document.querySelector("#scholarshipPrintSettingsModal").addEventListener("hidden.bs.modal",resolve,{once:true});document.querySelector("#scholarshipPrintSettingsModal [data-bs-dismiss=modal]").click();})');
        const filterDefaultShot=await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});
        await writeFile(join(root,'storage/app/testing/scholarship-filters-default-'+(mobile?'mobile':'desktop')+'.png'),Buffer.from(filterDefaultShot.data,'base64'));
        await click('[data-rank-filters-toggle]');
        assert.equal(await evaluate('document.querySelector("#scholarshipRankFilters").hidden'),!mobile);
        await click('[data-rank-filters-toggle]');
        if(mobile)await click('[data-rank-filters-toggle]');
        assert.equal(await evaluate('document.querySelector("#scholarshipRankFilters").hidden'),false);
        if(mobile){
            await command('Emulation.setDeviceMetricsOverride',{width:390,height:760,deviceScaleFactor:1,mobile:true});
            assert.equal(await evaluate('document.querySelector("#scholarshipRankFilters").hidden'),false,'Height changes must preserve the expanded phone form');
        }
        assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'),true,'No horizontal overflow');
        await evaluate('new Promise(resolve=>{document.querySelector("#scholarshipPhotoModal").addEventListener("shown.bs.modal",resolve,{once:true});document.querySelector("[data-scholarship-photo]").click();})');
        await until('document.querySelector("#scholarshipPhotoModal").classList.contains("show") && !document.querySelector("[data-photo-image]").hidden');
        assert.equal(await evaluate('document.querySelector("#scholarshipPhotoTitle").textContent'),'CHEAN CHAMNUNY');
        assert.equal(await evaluate('document.querySelector("[data-photo-download]").download'),'CHEAN_CHAMNUNY.svg');
        const photoWidth=await evaluate('document.querySelector("[data-photo-image]").getBoundingClientRect().width');
        const popupWidth=await evaluate('document.querySelector("#scholarshipPhotoModal .modal-dialog").getBoundingClientRect().width');
        if(mobile)assert.equal(await evaluate('(()=>{const rect=document.querySelector("#scholarshipPhotoModal .modal-content").getBoundingClientRect();return Math.abs(rect.top)<1 && Math.abs(rect.left)<1 && Math.abs(rect.width-innerWidth)<1 && Math.abs(rect.height-innerHeight)<1;})()'),true,'Phone photo popup fills the whole screen');
        else assert.equal(await evaluate('(()=>{const rect=document.querySelector("#scholarshipPhotoModal .modal-content").getBoundingClientRect();const bottomGap=innerHeight-rect.bottom;return Math.abs(rect.top-16)<1 && Math.abs(bottomGap-70)<1;})()'),true,'Desktop photo popup retains its top and bottom gaps');
        if(!mobile)assert.ok(popupWidth<800,'Photo popup starts narrower than the old large dialog');
        await click('[data-photo-zoom-in]');
        assert.equal(await evaluate('document.querySelector("[data-photo-zoom]").textContent'),'125%');
        assert.ok(await evaluate('document.querySelector("[data-photo-image]").getBoundingClientRect().width')>photoWidth);
        if(!mobile)assert.ok(await evaluate('document.querySelector("#scholarshipPhotoModal .modal-dialog").getBoundingClientRect().width')>popupWidth,'Desktop popup width increases with photo zoom');
        await click('[data-photo-zoom-out]');
        assert.equal(await evaluate('document.querySelector("[data-photo-zoom]").textContent'),'100%');
        await evaluate('for(let i=0;i<12;i++)document.querySelector("[data-photo-zoom-in]").click()');
        assert.equal(await evaluate('document.querySelector("[data-photo-zoom-in]").disabled'),true);
        assert.equal(await evaluate('document.querySelector("#scholarshipPhotoModal .modal-dialog").getBoundingClientRect().width<=innerWidth'),true,'Zoom cannot make the popup wider than the screen');
        await evaluate('for(let i=0;i<12;i++)document.querySelector("[data-photo-zoom-out]").click()');
        assert.equal(await evaluate('document.querySelector("[data-photo-zoom]").textContent'),'50%');
        assert.equal(await evaluate('document.querySelector("[data-photo-zoom-out]").disabled'),true);
        if(!mobile)assert.ok(await evaluate('document.querySelector("#scholarshipPhotoModal .modal-dialog").getBoundingClientRect().width')<popupWidth,'Desktop popup width shrinks when zooming out');
        else assert.equal(await evaluate('document.querySelector("#scholarshipPhotoModal .modal-dialog").getBoundingClientRect().width'),popupWidth,'Phone popup remains full width while zooming');
        await click('[data-photo-reset]');
        assert.equal(await evaluate('document.querySelector("[data-photo-zoom]").textContent'),'100%');
        if(mobile){
            for(const [width,height]of [[320,568],[430,932],[844,390]]){
                await command('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:true});
                await until('(()=>{const rect=document.querySelector("#scholarshipPhotoModal .modal-content").getBoundingClientRect();const footer=document.querySelector("#scholarshipPhotoModal .modal-footer").getBoundingClientRect();return Math.abs(rect.width-innerWidth)<1 && Math.abs(rect.height-innerHeight)<1 && footer.bottom<=innerHeight+1 && document.querySelector(".scholarship-photo-viewport").clientHeight>100;})()');
            }
            await command('Emulation.setDeviceMetricsOverride',{width:390,height:760,deviceScaleFactor:1,mobile:true});
        }
        const photoShot=await command('Page.captureScreenshot',{format:'png'});
        await writeFile(join(root,'storage/app/testing/scholarship-photo-'+(mobile?'mobile':'desktop')+'.png'),Buffer.from(photoShot.data,'base64'));
        const downloadPath=join(root,'storage/app/testing/downloads');await mkdir(downloadPath,{recursive:true});
        await command('Browser.setDownloadBehavior',{behavior:'allow',downloadPath});
        const downloadName='scholarship-photo-'+(mobile?'mobile-':'desktop-')+Date.now()+'.svg';
        await evaluate(`document.querySelector('[data-photo-download]').download=${JSON.stringify(downloadName)}`);
        await click('[data-photo-download]');
        for(let i=0;i<50 && !existsSync(join(downloadPath,downloadName));i++)await delay(50);
        assert.ok((await readFile(join(downloadPath,downloadName),'utf8')).includes('<svg'),'The original photo downloads successfully');
        await evaluate('new Promise(resolve=>{document.querySelector("#scholarshipPhotoModal").addEventListener("hidden.bs.modal",resolve,{once:true});document.querySelector("#scholarshipPhotoModal .btn-close").click();})');
        assert.equal(await evaluate('document.activeElement.matches("[data-scholarship-photo]")'),true,'Closing returns focus to the photo');
        // Orientation changes cross the rank form's responsive breakpoint and reset its default.
        if(mobile && await evaluate('document.querySelector("#scholarshipRankFilters").hidden'))await click('[data-rank-filters-toggle]');
        assert.equal(await evaluate('document.querySelectorAll(".scholarship-navigation a").length'),6);
        assert.equal(await evaluate('document.querySelector("[data-scholarship-apply-filters]")===null'),true);
        assert.equal(await evaluate('document.querySelector("#scholarshipClass")===null'),true);
        assert.deepEqual(await evaluate('[...document.querySelector("#scholarshipRank").options].map(o=>o.value)'),['','1','2','3']);
        assert.ok(await evaluate('document.querySelector("#scholarshipRank").getBoundingClientRect().height<=50'));
        assert.equal(await evaluate('document.body.textContent.includes("Exam Candidates")'),false);
        assert.ok(await evaluate('document.querySelector("#scholarshipYearToggle").getBoundingClientRect().height<=50'));
        if(!mobile)assert.equal(await evaluate('(()=>{const selectors=["#scholarshipYearToggle","#scholarshipCampusToggle","#scholarshipGradeToggle","#scholarshipStudentToggle","#scholarshipRank","#scholarshipEoyAverage","[data-scholarship-rank-form] [type=submit]"];const tops=selectors.map(s=>document.querySelector(s).getBoundingClientRect().top);return Math.max(...tops)-Math.min(...tops)<1;})()'),true,'Filters, rank, EOY average, and Save Rank share one desktop row');
        assert.equal(await evaluate('document.querySelector(".scholarship-tabs-toggle").getAttribute("aria-expanded")'),'false');
        const collapsedWidth=await evaluate('document.querySelector("main").getBoundingClientRect().width');
        if(mobile)assert.equal(await evaluate('getComputedStyle(document.querySelector(".scholarship-navigation nav")).display'),'none');
        else {
            assert.equal(await evaluate('Math.round(document.querySelector("aside").getBoundingClientRect().width)'),80);
            assert.equal(await evaluate('getComputedStyle(document.querySelector(".scholarship-tab-label")).display'),'none');
            await evaluate('document.querySelector(".scholarship-navigation a").focus()');
            await until('document.querySelector(".scholarship-tabs-tooltip .tooltip-inner")?.textContent === "Assign Student Rank"');
        }
        const collapsedShot=await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});
        await writeFile(join(root,'storage/app/testing/scholarship-nav-collapsed-'+(mobile?'mobile':'desktop')+'.png'),Buffer.from(collapsedShot.data,'base64'));
        await click('.scholarship-tabs-toggle');
        assert.equal(await evaluate('document.querySelector(".scholarship-tabs-toggle").getAttribute("aria-expanded")'),'true');
        assert.notEqual(await evaluate('getComputedStyle(document.querySelector(".scholarship-navigation nav")).display'),'none');
        assert.notEqual(await evaluate('getComputedStyle(document.querySelector(".scholarship-tab-label")).display'),'none');
        if(!mobile) {
            assert.equal(await evaluate('Math.round(document.querySelector("aside").getBoundingClientRect().width)'),250);
            assert.ok(collapsedWidth>await evaluate('document.querySelector("main").getBoundingClientRect().width'));
        }
        const expandedShot=await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});
        await writeFile(join(root,'storage/app/testing/scholarship-nav-expanded-'+(mobile?'mobile':'desktop')+'.png'),Buffer.from(expandedShot.data,'base64'));
        if(mobile)await click('main');else await click('.scholarship-tabs-toggle');
        assert.equal(await evaluate('document.querySelector(".scholarship-tabs-toggle").getAttribute("aria-expanded")'),'false');
        await click('#scholarshipStudentToggle');
        assert.equal(await evaluate('document.querySelector("#scholarshipStudentToggle").getAttribute("aria-expanded")'),'true');
        await evaluate('Array.from(document.querySelectorAll(".scholarship-field.is-open .scholarship-select-option")).find(b=>b.textContent.includes("2026001")).click()');
        assert.equal(await evaluate('document.querySelector("#scholarshipRank").value'),'');
        assert.equal(await evaluate('document.querySelector("#scholarshipEoyAverage").value'),'');
        await evaluate('document.querySelector("#scholarshipRank").value="3";document.querySelector("#scholarshipEoyAverage").value="87.25"');
        assert.equal(await evaluate('document.querySelector("#scholarshipRank").value'),'3');
        assert.equal(await evaluate('document.querySelector("#scholarshipEoyAverage").value'),'87.25');
        await until('document.querySelector("[data-scholarship-rank-form] [type=submit]").disabled===false');
        await click('[data-rank-filters-toggle]');await click('[data-rank-filters-toggle]');
        assert.equal(await evaluate('document.querySelector("#scholarshipRank").value'),'3');
        assert.equal(await evaluate('document.querySelector("#scholarshipEoyAverage").value'),'87.25');
        const geometry=await evaluate('(() => {const n=document.querySelector("aside").getBoundingClientRect(),m=document.querySelector("main").getBoundingClientRect();return {navRight:n.right,navBottom:n.bottom,mainLeft:m.left,mainTop:m.top};})()');
        if(mobile)assert.ok(geometry.mainTop>=geometry.navBottom);else assert.ok(geometry.mainLeft>=geometry.navRight);
        const screenshot=await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});
        await mkdir(join(root,'storage/app/testing'),{recursive:true});await writeFile(join(root,'storage/app/testing/scholarship-'+(mobile?'mobile':'desktop')+'.png'),Buffer.from(screenshot.data,'base64'));
        await click('#scholarshipGradeToggle');
        await evaluate('Array.from(document.querySelectorAll(".scholarship-field.is-open .scholarship-select-option")).find(b=>b.textContent==="4B").click()');
        assert.equal(await evaluate('document.querySelector("#scholarshipStudent").value'),'');
        assert.equal(await evaluate('document.querySelector("#scholarshipRank").value'),'');
        assert.equal(await evaluate('document.querySelector("#scholarshipEoyAverage").value'),'');
        await until('document.querySelector("#scholarshipStudent option[value=\\"2\\"]") !== null');
        assert.equal(await evaluate('document.querySelector("[data-rank-filters-toggle]").getAttribute("aria-expanded")'),'true','Selecting filters must not collapse the form');
        assert.equal(await evaluate('document.querySelector("[data-scholarship-ranks-panel]").dataset.filterScope'),'1/1/2:2/');
        await click('#scholarshipStudentToggle');
        await evaluate('Array.from(document.querySelectorAll(".scholarship-field.is-open .scholarship-select-option")).find(b=>b.textContent.includes("2026002")).click()');
        await until('document.querySelector("[data-scholarship-rank-form] [type=submit]").disabled===false');
        assert.equal(await evaluate('document.querySelector("#scholarshipEoyAverage").value'),'');
        await evaluate('document.querySelector("#scholarshipRank").value="2";document.querySelector("#scholarshipEoyAverage").value="91";document.querySelector("[data-scholarship-rank-form]").requestSubmit()');
        await until('document.querySelector(".swal2-popup")?.textContent.includes("Check EOY Avg.")');
        assert.equal(await evaluate('document.querySelector("#scholarshipEoyAverage").getAttribute("aria-invalid")'),'true');
        assert.ok(await evaluate('document.querySelector("#scholarshipEoyAverageError").textContent.includes("Check EOY Avg.")'));
        await click('.swal2-confirm');
        await until('document.querySelector("[data-scholarship-rank-form] [type=submit]").disabled===false');
        await evaluate('const avg=document.querySelector("#scholarshipEoyAverage");avg.value="88.5";avg.dispatchEvent(new Event("input",{bubbles:true}));document.querySelector("[data-scholarship-rank-form]").requestSubmit()');
        assert.equal(await evaluate('window.saved.enrollment_id'),'2');assert.equal(await evaluate('window.saved.rank'),'2');assert.equal(await evaluate('window.saved.eoy_average'),'88.5');assert.equal(await evaluate('window.saved.grade_class'),'2:2');
        assert.equal(await evaluate('document.querySelector("[data-scholarship-ranks-panel]").hidden'),false);
        await until('document.querySelector(".swal2-toast")?.textContent.includes("saved successfully")');
        assert.equal(await evaluate('document.querySelector("[data-scholarship-ranks-panel]").dataset.filterScope'),'1/1/2:2/');
        assert.equal(await evaluate('document.querySelector("#scholarshipStudent").value'),'');
        assert.equal(await evaluate('document.querySelector("#scholarshipRank").value'),'');
        assert.equal(await evaluate('document.querySelector("#scholarshipEoyAverage").value'),'');
        await until('document.querySelector("#scholarshipStudent option[value=\\"2\\"]")===null');
        await evaluate('document.querySelector(".swal2-close")?.click()');
        await evaluate('new Promise(resolve=>{document.querySelector("#scholarshipPhotoModal").addEventListener("shown.bs.modal",resolve,{once:true});document.querySelector("[data-scholarship-photo]").click();})');
        await until('!document.querySelector("[data-photo-image]").hidden');
        assert.equal(await evaluate('document.querySelector("#scholarshipPhotoTitle").textContent'),'SECOND STUDENT','Photos still open after an AJAX list refresh');
        assert.equal(await evaluate('document.querySelector("[data-photo-zoom]").textContent'),'100%','Reopening starts at the fitted size');
        await evaluate('new Promise(resolve=>{document.querySelector("#scholarshipPhotoModal").addEventListener("hidden.bs.modal",resolve,{once:true});document.querySelector("#scholarshipPhotoModal .btn-close").click();})');
        await click('#scholarshipYearToggle');
        await evaluate('Array.from(document.querySelectorAll(".scholarship-field.is-open .scholarship-select-option")).find(b=>b.textContent.includes("2026-2027")).click()');
        assert.equal(await evaluate('document.querySelector("#scholarshipCampus").value'),'');
        assert.equal(await evaluate('document.querySelector("#scholarshipStudent").value'),'');
        await until('document.querySelector("#scholarshipCampus").disabled===false');
        assert.equal(await evaluate('document.querySelector("#scholarshipCampus option[value=\\"1\\"]")===null'),true);
        assert.equal(await evaluate('document.querySelector("[data-scholarship-ranks-panel]").dataset.filterScope'),'2///');
        assert.equal(await evaluate('document.querySelector("#scholarshipGrade").disabled'),true);
        await click('#scholarshipCampusToggle');
        await evaluate('Array.from(document.querySelectorAll(".scholarship-field.is-open .scholarship-select-option")).find(b=>b.textContent.includes("BKK")).click()');
        await until('document.querySelector("#scholarshipGrade").disabled===false');
        assert.equal(await evaluate('document.querySelector("#scholarshipGrade option[value=\\"1:1\\"]")===null'),true);
        await click('#scholarshipGradeToggle');
        await evaluate('Array.from(document.querySelectorAll(".scholarship-field.is-open .scholarship-select-option")).find(b=>b.textContent==="4B").click()');
        await until('document.querySelector("#scholarshipStudent option[value=\\"2\\"]")!==null');
        assert.equal(await evaluate('document.querySelector("[data-scholarship-ranks-panel]").dataset.filterScope'),'2/2/2:2/');
        await evaluate('(async()=>{const select=document.querySelector("#scholarshipYear");select.value="1";select.dispatchEvent(new Event("change",{bubbles:true}));await new Promise(r=>setTimeout(r,20));select.value="2";select.dispatchEvent(new Event("change",{bubbles:true}));})()');
        await until('document.querySelector("#scholarshipCampus").disabled===false');await delay(200);
        assert.equal(await evaluate('document.querySelector("[data-scholarship-ranks-panel]").dataset.filterScope'),'2///','A late response must not restore the previous year');
        await evaluate('(()=>{const original=window.fetch;window.fetch=(url,options)=>{if(String(url).includes("/api/rank-filters")){window.fetch=original;return Promise.resolve(new Response(JSON.stringify({message:"Temporary filter failure"}),{status:503,headers:{"Content-Type":"application/json"}}));}return original(url,options);};const select=document.querySelector("#scholarshipYear");select.value="1";select.dispatchEvent(new Event("change",{bubbles:true}));})()');
        await until('document.querySelector("[data-scholarship-list-status]").textContent.includes("Temporary filter failure")');
        assert.equal(await evaluate('document.querySelector("[data-scholarship-ranks-panel]").hidden'),true);
        assert.equal(await evaluate('document.querySelector("[data-scholarship-rank-form] [type=submit]").disabled'),true);
        await evaluate('const retryYear=document.querySelector("#scholarshipYear");retryYear.value="2";retryYear.dispatchEvent(new Event("change",{bubbles:true}));');
        await until('document.querySelector("#scholarshipCampus").disabled===false && document.querySelector("[data-scholarship-ranks-panel]").hidden===false');
        console.log((mobile?'Mobile':'Desktop')+': compact filters, automatic rank-list updates, dependent year/campus/grade/student choices, and stale-response protection passed.');
        await command('Page.navigate',{url:'http://127.0.0.1:'+server.address().port+'/?rank_saved=1'});
        await until('document.querySelector("#scholarshipStudentToggle")!==null');
        assert.equal(await evaluate('document.querySelector("#scholarshipStudent").value'),'','Saving clears the assigned student selection');
        assert.equal(await evaluate('document.querySelector("#scholarshipYear").value'),'1');
        assert.equal(await evaluate('document.querySelector("#scholarshipCampus").value'),'1');
        assert.equal(await evaluate('document.querySelector("#scholarshipGrade").value'),'1:1');
        assert.equal(await evaluate('document.querySelector("#scholarshipRank").value'),'','Rank clears after a successful save');
        assert.equal(await evaluate('document.querySelector("#scholarshipEoyAverage").value'),'','EOY average clears after a successful save');
        assert.equal(await evaluate('document.querySelector("[data-scholarship-ranks-panel]").textContent.includes("87.25")'),true,'The saved average remains in the list');
        await until('document.querySelector(".swal2-toast")!==null');
        await evaluate('document.querySelector(".swal2-close")?.click()');
        const saveCount=saveRequests.length;
        await evaluate('for(const name of ["academic_year_id","campus_id","grade_class","enrollment_id","rank","eoy_average"])document.querySelector("[data-scholarship-rank-form]").elements[name].value="";document.querySelector("[data-scholarship-rank-form]").requestSubmit()');
        await until('document.querySelector(".swal2-popup")?.textContent.includes("Please select Academic Year")');
        assert.equal(saveRequests.length,saveCount,'Blank fields never reach the server');
        assert.equal(await evaluate('document.querySelectorAll("[data-rank-error]").length'),6,'Each blank field gets its own feedback');
        assert.equal(await evaluate('document.querySelector("#scholarshipEoyAverage").getAttribute("aria-invalid")'),'true');
        assert.equal(await evaluate('document.querySelector("#scholarshipStudentToggle").getAttribute("aria-invalid")'),'true');
        const validationShot=await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});
        await writeFile(join(root,'storage/app/testing/scholarship-rank-validation-'+(mobile?'mobile':'desktop')+'.png'),Buffer.from(validationShot.data,'base64'));
        await click('.swal2-confirm');
        await command('Page.navigate',{url:'http://127.0.0.1:'+server.address().port+'/?rank_edit=1'});
        await until('document.querySelector("#scholarshipStudentToggle")!==null');
        assert.equal(await evaluate('document.querySelector("#scholarshipRank").value'),'3','Selecting the student again still restores the saved rank for editing');
        assert.equal(await evaluate('document.querySelector("#scholarshipEoyAverage").value'),'87.25');
        assert.equal(await evaluate('document.querySelector("[name=rank_id]").value'),'1');
        await command('Page.navigate',{url:'http://127.0.0.1:'+server.address().port+'/?pagination=1'});
        await until('document.querySelector("#scholarshipRanksPagination .premium-pagination-select select")!==null');
        const pager='#scholarshipRanksPagination';
        assert.deepEqual(await evaluate(`[...document.querySelector('${pager} select').options].map(option=>option.value)`),['all','10','25','50','75','100']);
        assert.equal(await evaluate(`document.querySelector('${pager} select').value`),'10');
        assert.equal(await evaluate('document.querySelectorAll(".scholarship-rank-table tbody tr").length'),10);
        const beforePage=filterQueries.length;
        await click(`${pager} [data-page="2"]`);
        await until('document.querySelector(".scholarship-rank-sequence").textContent.trim()==="11"');
        assert.equal(filterQueries.length,beforePage+1,'Each page click sends one request');
        assert.equal(filterQueries.at(-1).per_page,'10');
        assert.equal(filterQueries.at(-1).campus_id,'1');
        assert.equal(await evaluate(`document.querySelector('${pager} .premium-pagination-info').textContent.replace(/\\s+/g,' ').trim()`),'Showing 11 to 20 of 63 entries');
        await evaluate(`(()=>{const size=document.querySelector('${pager} select');size.value='25';size.dispatchEvent(new Event('change',{bubbles:true}));})()`);
        await until('document.querySelectorAll(".scholarship-rank-table tbody tr").length===25');
        assert.equal(await evaluate('document.querySelector(".scholarship-rank-sequence").textContent.trim()'),'1','Page-size changes reset to page one');
        await evaluate(`(()=>{const page=document.querySelector('${pager} .premium-pagination-goto input');page.value='3';page.dispatchEvent(new Event('change',{bubbles:true}));})()`);
        await until('document.querySelector(".scholarship-rank-sequence").textContent.trim()==="51"');
        assert.equal(await evaluate('document.querySelectorAll(".scholarship-rank-table tbody tr").length'),13);
        await evaluate(`(()=>{const page=document.querySelector('${pager} .premium-pagination-goto input');page.value='999';page.dispatchEvent(new Event('change',{bubbles:true}));})()`);
        assert.equal(await evaluate(`document.querySelector('${pager} .premium-pagination-goto input').value`),'3','Go to Page clamps to the last page');
        for(const size of ['all','50','75','100','10']){
            await evaluate(`(()=>{const size=document.querySelector('${pager} select');size.value=${JSON.stringify(size)};size.dispatchEvent(new Event('change',{bubbles:true}));})()`);
            const count=size==='all'?63:Math.min(Number(size),63);
            await until(`document.querySelectorAll('.scholarship-rank-table tbody tr').length===${count} && document.querySelector('[data-scholarship-ranks-panel]').getAttribute('aria-busy')==='false'`);
            assert.equal(await evaluate(`document.querySelector('${pager} select').value`),size);
            if(size==='all')assert.equal(await evaluate(`document.querySelector('${pager} .premium-pagination-goto input').max`),'1');
        }
        if(mobile)await click('[data-rank-filters-toggle]');
        await click('#scholarshipCampusToggle');
        await evaluate('Array.from(document.querySelectorAll(".scholarship-field.is-open .scholarship-select-option")).find(button=>button.textContent==="BKK").click()');
        await until('document.querySelector("[data-scholarship-ranks-panel]").dataset.filterScope==="1/2//"');
        assert.equal(filterQueries.at(-1).per_page,'10','Filter changes retain the chosen page size');
        assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true,'Pagination fits the screen');
        assert.equal(await evaluate(`(()=>{const pager=document.querySelector('${pager}');return pager.querySelector('.premium-pagination-goto').getBoundingClientRect().right<=pager.getBoundingClientRect().right;})()`),true,'Go to Page controls stay inside the footer');
        const paginationClip=await evaluate(`(()=>{const rect=document.querySelector('${pager}').getBoundingClientRect();return {x:rect.left+scrollX,y:rect.top+scrollY,width:rect.width,height:rect.height,scale:1};})()`);
        const paginationShot=await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:true,clip:paginationClip});
        await writeFile(join(root,'storage/app/testing/scholarship-pagination-'+(mobile?'mobile':'desktop')+'.png'),Buffer.from(paginationShot.data,'base64'));
    }
    assert.ok(queries.some(query=>query.grade_class==='2:2'));
    for(const mobile of [false,true]) {
        await command('Emulation.setDeviceMetricsOverride',{width:mobile?390:1440,height:mobile?844:980,deviceScaleFactor:1,mobile});
        for(const tab of Object.keys(tabs)) {
            await command('Page.navigate',{url:'http://127.0.0.1:'+server.address().port+'/?fixture_tab='+tab+(['settings','settings-new'].includes(tab)?'&premium_forms=1':'')});
            await until(tab==='assign-rank'?'document.querySelector("#scholarshipStudentToggle")!==null':tab.startsWith('seat-plan')?'document.querySelector("#scholarshipSeatsPagination select")!==null':'document.querySelector("#scholarshipExamToggle")!==null');
            assert.equal(await evaluate('document.querySelector(".scholarship-tabs-toggle").getAttribute("aria-expanded")'),'false','Every scholarship tab opens collapsed');
            assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true,tab+' must not overflow the page');
            if(tab==='settings'||tab==='settings-new') {
                if(tab==='settings')assert.equal(await evaluate('document.querySelector("#examDate").value'),'20-Nov-2026');
                await evaluate(`document.querySelector('#examDate').scrollIntoView({block:'center'});document.querySelector('.scholarship-exam-date [data-premium-date-toggle]').click();`);
                assert.equal(await evaluate('document.querySelector(".scholarship-exam-date [data-premium-date-toggle]").getAttribute("aria-expanded")'),'true');
                assert.equal(await evaluate(`(()=>{const box=document.querySelector('#examDateCalendar').getBoundingClientRect(),today=document.querySelector('.scholarship-exam-date [data-premium-date-today]').getBoundingClientRect();return box.left>=0&&box.right<=innerWidth&&box.top>=0&&box.bottom<=innerHeight&&today.bottom<=innerHeight;})()`),true,'Exam calendar and Today fit the screen');
                const dateShot=await command('Page.captureScreenshot',{format:'png'});
                await writeFile(join(root,'storage/app/testing/scholarship-exam-calendar-'+tab+'-'+(mobile?'mobile':'desktop')+'.png'),Buffer.from(dateShot.data,'base64'));
                await click('.scholarship-exam-date [data-premium-date-today]');
                assert.equal(await evaluate(`(()=>{const today=new Date();return document.querySelector('[name=exam_date]').value===[today.getFullYear(),String(today.getMonth()+1).padStart(2,'0'),String(today.getDate()).padStart(2,'0')].join('-');})()`),true,'Today saves the local date in ISO format');
                await click('.scholarship-exam-date [data-premium-date-toggle]');
                await click('.scholarship-exam-date .date-picker-year-toggle');
                await click('.scholarship-exam-date [data-premium-year="2030"]');
                await evaluate(`document.querySelector('#examDate').value='20-Nov-2030';document.querySelector('#examDate').dispatchEvent(new Event('change'));document.querySelector('#examCapacity').click();document.querySelector('.scholarship-exam-date [data-premium-date-toggle]').click();`);
                await click('.scholarship-exam-date [data-premium-date-next]');
                await click('.scholarship-exam-date [data-premium-day="2030-12-10"]');
                assert.equal(await evaluate('document.querySelector("#examDate").value'),'10-Dec-2030');
                assert.equal(await evaluate('new FormData(document.querySelector("[data-exam-settings]")).get("exam_date")'),'2030-12-10','Future exam dates keep the server date format');
                await evaluate(`document.querySelector('#examDate').value='31-Feb-2030';document.querySelector('#examDate').dispatchEvent(new Event('input'));`);
                assert.equal(await evaluate('document.querySelector("#examDate").validity.valid'),false,'Invalid typed dates cannot be submitted');
                await evaluate(`document.querySelector('#examDate').value='20-Nov-2026';document.querySelector('#examDate').dispatchEvent(new Event('change'));document.querySelector('#examCapacity').click();`);
                assert.equal(await evaluate('document.querySelector("[name=name],[name=eligible_ranks_text],[name=tie_policy],[name=\\"campus_ids[]\\"]")===null'),true);
                assert.equal(await evaluate('document.querySelector("#examYear").selectedOptions[0].textContent'),'2025-2026');
                assert.equal(await evaluate('document.querySelectorAll("[data-subject-rows]>tr").length'),8);
                await click('[data-add-subject]');assert.equal(await evaluate('document.querySelectorAll("[data-subject-rows]>tr").length'),9);
                await evaluate('document.querySelector("[data-subject-rows]>tr:last-child [data-remove-config-row]").click()');
                assert.deepEqual(await evaluate('[...document.querySelectorAll("[name^=\\"award_scheme[\\"]")].map(input=>Number(input.value))'),[100,75,50,25]);
                assert.equal(await evaluate('document.querySelectorAll("[data-exam-center-row]").length'),1);
                assert.equal(await evaluate('document.querySelector("[data-campus-center]").value'),'new-main');
                await click('[data-add-center]');
                await evaluate(`(()=>{const name=document.querySelector('[data-exam-center-row]:last-child [data-center-name]');name.value='Second Center';name.dispatchEvent(new Event('input',{bubbles:true}));const select=document.querySelector('[data-campus-center]');select.value=select.options[2].value;})()`);
                assert.equal(await evaluate('document.querySelector("[data-campus-center]").selectedOptions[0].textContent'),'Second Center');
                assert.equal(await evaluate(`(()=>{const data=new FormData(document.querySelector('[data-exam-settings]'));return data.get('exam_centers[1][name]')==='Second Center'&&data.get('center_assignments[1]')===data.get('exam_centers[1][key]');})()`),true,'Center names and campus assignments submit together');
                await click('[data-exam-center-row]:last-child [data-remove-center]');
                assert.equal(await evaluate('document.querySelector("[data-campus-center]").checkValidity()'),false,'Removing an assigned center requires reassignment');
                await evaluate('document.querySelector("[data-campus-center]").value="new-main"');
                if(tab==='settings-new'){
                    await evaluate('document.querySelector("#examYear").value="2";document.querySelector("#examYear").dispatchEvent(new Event("change"))');
                    assert.equal(await evaluate('document.querySelector("[data-exam-settings] button[type=submit]").disabled'),true,'Saving waits for campus options');
                    await until('document.querySelector("[data-center-campus=\\"2\\"]")!==null && !document.querySelector("[data-exam-settings] button[type=submit]").disabled');
                    assert.equal(await evaluate('document.querySelector("[data-center-campus=\\"1\\"]")===null'),true,'New academic year replaces participating campuses');
                    await evaluate(`for(const value of ['1','2']){document.querySelector('#examYear').value=value;document.querySelector('#examYear').dispatchEvent(new Event('change'));}`);
                    await until('!document.querySelector("[data-exam-settings] button[type=submit]").disabled');await delay(150);
                    assert.equal(await evaluate('document.querySelector("[data-center-campus=\\"2\\"]")!==null'),true,'Late campus responses cannot overwrite the selected year');
                    await evaluate(`document.querySelector('[data-exam-settings]').dataset.centerCampusesUrl='/api/exam-center-campuses?fail=1';document.querySelector('#examYear').value='1';document.querySelector('#examYear').dispatchEvent(new Event('change'));`);
                    await until('!document.querySelector("[data-center-load-error]").hidden');
                    assert.equal(await evaluate('document.querySelector("[data-exam-settings] button[type=submit]").disabled'),true,'Failed campus lookup prevents incomplete save');
                    await evaluate(`document.querySelector('[data-exam-settings]').dataset.centerCampusesUrl='/api/exam-center-campuses';document.querySelector('#examYear').dispatchEvent(new Event('change'));`);
                    await until('document.querySelector("[data-center-load-error]").hidden && !document.querySelector("[data-exam-settings] button[type=submit]").disabled');
                }
                assert.equal(await evaluate(`(()=>{const section=document.querySelector('[data-exam-centers]').getBoundingClientRect();return [...document.querySelectorAll('[data-exam-centers] input:not([type=hidden]),[data-campus-center],[data-add-center]')].every(el=>{const r=el.getBoundingClientRect();return r.left>=section.left&&r.right<=section.right;});})()`),true,'Center controls fit the available width');
            }
            if(tab==='assign-rank')assert.equal(await evaluate('document.querySelector("[data-candidate-checkbox],[data-select-all-candidates]")===null'),true);
            if(tab==='assign-rank')assert.equal(await evaluate('document.querySelector("[data-rank-filters-toggle]").getAttribute("aria-expanded")'),mobile?'false':'true');
            if(tab==='seat-plan')assert.equal(await evaluate('document.querySelector("[name=plan_token]").value.length'),64);
            if(tab==='scoring')assert.equal(await evaluate('document.querySelector("[name^=\\"scores[\\"]").value'),'90');
            if(tab==='result')assert.equal(await evaluate('document.body.textContent.includes("Published results")'),true);
            if(tab==='awards') {
                assert.equal(await evaluate('document.querySelectorAll("[data-award-winner]").length'),7);
                assert.equal(await evaluate('document.querySelector("[data-award-guidance]").textContent.includes("8 students tied")'),true);
                await click('[data-award-slot] .scholarship-select-toggle');
                await click('[data-award-slot] .scholarship-select-option:nth-child(2)');
                assert.equal(await evaluate('[...document.querySelectorAll("[data-award-guidance]")][1].textContent.includes("7 students tied")'),true);
                await evaluate(`document.querySelectorAll('[data-award-winner]').forEach((select,index)=>{if(index){select.value=[...select.options].filter(o=>o.value && !o.disabled)[0].value;select.dispatchEvent(new Event('change',{bubbles:true}));}})`);
                assert.equal(await evaluate('new Set([...document.querySelectorAll("[data-award-winner]")].map(s=>s.value)).size'),7);
                await evaluate(`document.querySelector('[data-award-form]').addEventListener('submit',event=>{event.preventDefault();window.awardSubmission=Object.fromEntries(new FormData(event.target));});document.querySelector('[data-award-form]').requestSubmit();`);
                assert.equal(await evaluate('window.awardSubmission["awards[total_1][award_percent]"]'),'100');
                assert.equal(await evaluate('window.awardSubmission["awards[total_2][award_percent]"]'),'75');
                assert.equal(await evaluate('window.awardSubmission["awards[total_3][award_percent]"]'),'50');
            }
            if(tab==='exam-committee')assert.equal(await evaluate('document.querySelector("[name=role]").options.length'),4);
            if(tab==='seat-plan-centers'||tab==='seat-plan-confirmed'){
                assert.deepEqual(await evaluate(`[...document.querySelectorAll('.scholarship-seat-table thead th')].map(cell=>cell.textContent.trim())`),['No.','Photo','Student','Grade','Exam Code','Exam Center','Room No.','Seat No.']);
                assert.deepEqual(await evaluate(`[...document.querySelectorAll('.scholarship-seat-table tbody tr')].map(row=>[row.cells[5].textContent,row.cells[6].textContent,row.cells[7].textContent])`),[
                    ['North Exam Center','1','1'],['North Exam Center','2','2'],['South Exam Center','1','1'],['South Exam Center','2','2'],
                ],'Preview and confirmed lists restart room/seat numbers for each center and continue seats into Grade 2');
                assert.deepEqual(await evaluate(`[...document.querySelectorAll('.scholarship-seat-table tbody tr')].map(row=>row.cells[0].textContent.trim())`),['1','2','3','4']);
                assert.equal(await evaluate(`(()=>{const row=document.querySelector('.scholarship-seat-table tbody tr');return row.cells[2].textContent.includes('2026001')&&row.cells[2].textContent.includes('CHEAN CHAMNUNY')&&row.cells[3].textContent.includes('2025-2026')&&row.cells[3].textContent.includes('BCH-1A-III')&&row.cells[4].textContent==='G01-001';})()`),true,'Student and enrollment details accompany the exam code');
                await evaluate(`new Promise(resolve=>{document.querySelector('#scholarshipPhotoModal').addEventListener('shown.bs.modal',resolve,{once:true});document.querySelector('.scholarship-seat-table [data-scholarship-photo]').click();})`);
                await until('!document.querySelector("[data-photo-image]").hidden');
                assert.equal(await evaluate('document.querySelector("[data-photo-download]").download'),'CHEAN_CHAMNUNY.svg');
                await evaluate(`new Promise(resolve=>{document.querySelector('#scholarshipPhotoModal').addEventListener('hidden.bs.modal',resolve,{once:true});document.querySelector('#scholarshipPhotoModal [data-bs-dismiss=modal]').click();})`);
                if(tab==='seat-plan-centers')assert.equal(await evaluate('document.querySelectorAll(".d-flex.flex-wrap.gap-2.mb-3 .badge").length'),4,'The two Room 1 allocations and two Room 2 allocations remain separate');
            }
            if(tab==='committee-centers'){
                assert.equal(await evaluate('document.querySelector("#committeeRoom").disabled'),true,'Room selection requires a center');
                await evaluate(`document.querySelector('#committeeCenter').selectedIndex=1;document.querySelector('#committeeCenter').dispatchEvent(new Event('change'));document.querySelector('#committeeRoom').value='1';`);
                assert.equal(await evaluate('document.querySelector("#committeeRoom").disabled'),false);
                assert.deepEqual(await evaluate(`(()=>{const data=new FormData(document.querySelector('[data-committee-form]'));return [data.get('exam_center_id'),data.get('room_number')];})()`),['1','1'],'Room duties include the selected center');
                await evaluate(`document.querySelector('#committeeCenter').selectedIndex=2;document.querySelector('#committeeCenter').dispatchEvent(new Event('change'));document.querySelector('#committeeGrade').value='2';document.querySelector('#committeeGrade').dispatchEvent(new Event('change'));`);
                assert.deepEqual(await evaluate('[...document.querySelector("#committeeRoom").options].map(option=>option.value)'),['','2'],'Rooms match both the selected center and grade');
                await evaluate(`document.querySelector('#committeeRole').value='final-approver';document.querySelector('#committeeRole').dispatchEvent(new Event('change'));`);
                assert.equal(await evaluate('document.querySelector("#committeeCenter").disabled&&document.querySelector("#committeeGrade").disabled&&document.querySelector("#committeeRoom").disabled'),true,'Final approvers retain the entire exam scope');
            }
            if(['settings','seat-plan','scoring','result','awards','seat-plan-centers','seat-plan-confirmed','committee-centers'].includes(tab)) {
                const shot=await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});
                await writeFile(join(root,'storage/app/testing/scholarship-'+tab+'-'+(mobile?'mobile':'desktop')+'.png'),Buffer.from(shot.data,'base64'));
            }
        }
        console.log((mobile?'Mobile':'Desktop')+': all six exam tabs render; award tie guidance, seven distinct selections, default percentages and form submission pass.');
    }
    for(const mobile of [false,true]) {
        await command('Emulation.setDeviceMetricsOverride',{width:mobile?390:1440,height:mobile?844:980,deviceScaleFactor:1,mobile});
        await command('Page.navigate',{url:'http://127.0.0.1:'+server.address().port+'/?fixture_tab=seat-plan-pager&app_shell=1'});
        await until('document.querySelector("#scholarshipSeatsPagination select")?.value==="15"');
        assert.equal(await evaluate('document.querySelectorAll(".scholarship-seat-table tbody tr").length'),15);
        assert.deepEqual(await evaluate('[...document.querySelector("#scholarshipSeatsPagination select").options].map(o=>o.value)'),['all','15','25','50','75','100']);
        const token=await evaluate('document.querySelector("[name=plan_token]").value');
        await click('#scholarshipSeatsPagination [data-page="2"]');
        await until('document.querySelector("[data-scholarship-seat-list]").getAttribute("aria-busy")==="false" && document.querySelector(".scholarship-seat-table tbody tr td").textContent==="16"');
        assert.equal(await evaluate('new URL(location.href).searchParams.get("seat_page")'),'2');
        await evaluate('document.querySelector("#scholarshipSeatsPagination .premium-pagination-goto input").value="6";document.querySelector("#scholarshipSeatsPagination .premium-pagination-goto input").dispatchEvent(new Event("change"))');
        await until('document.querySelector(".scholarship-seat-table tbody tr td").textContent==="76"');
        assert.equal(await evaluate('document.querySelectorAll(".scholarship-seat-table tbody tr").length'),2);
        for(const [size,count] of [['25',25],['50',50],['75',75],['100',77],['all',77],['15',15]]){
            await evaluate(`document.querySelector('#scholarshipSeatsPagination select').value=${JSON.stringify(size)};document.querySelector('#scholarshipSeatsPagination select').dispatchEvent(new Event('change'))`);
            await until(`document.querySelector('[data-scholarship-seat-list]').getAttribute('aria-busy')==='false' && document.querySelector('[data-scholarship-seat-list]').dataset.pageSize===${JSON.stringify(size)}`);
            assert.equal(await evaluate('document.querySelectorAll(".scholarship-seat-table tbody tr").length'),count);
            assert.equal(await evaluate('document.querySelector(".scholarship-seat-table tbody tr td").textContent'),'1');
        }
        assert.equal(await evaluate('document.querySelector("[name=plan_token]").value'),token,'Paging preserves the complete plan confirmation');
        const scrolling=await evaluate(`(()=>{const wrapper=document.querySelector('.page-wrapper'),nested=[document.querySelector('.page-body'),document.querySelector('[data-scholarship-seat-list]'),document.querySelector('.scholarship-seat-overflow')];return {page:wrapper.scrollHeight>wrapper.clientHeight,nested:nested.some(el=>el.scrollHeight>el.clientHeight+1&&['auto','scroll','hidden'].includes(getComputedStyle(el).overflowY)),horizontal:document.documentElement.scrollWidth>innerWidth};})()`);
        assert.deepEqual(scrolling,{page:true,nested:false,horizontal:false},'The seat form scrolls as one page');
        if(mobile)assert.equal(await evaluate('getComputedStyle(document.querySelector(".scholarship-seat-table tbody tr")).display'),'grid','Phones use compact cards');
        const initial=await evaluate('document.querySelector(".scholarship-forms>.card").getBoundingClientRect().top');
        await evaluate('document.querySelector(".page-wrapper").scrollTop=100000');await delay(100);
        assert.ok(await evaluate('document.querySelector(".scholarship-forms>.card").getBoundingClientRect().top')<initial);
        assert.equal(await evaluate(`(()=>{const pager=document.querySelector('#scholarshipSeatsPagination').getBoundingClientRect(),footer=document.querySelector('.page-wrapper>.footer').getBoundingClientRect();return pager.top>=0&&pager.bottom<=footer.top;})()`),true,'Seat pagination remains reachable above the footer');
        const seatShot=await command('Page.captureScreenshot',{format:'png'});
        await writeFile(join(root,'storage/app/testing/scholarship-seat-pagination-'+(mobile?'mobile':'desktop')+'.png'),Buffer.from(seatShot.data,'base64'));
        console.log((mobile?'Mobile':'Desktop')+': seat page sizes, numbering, next/Go to page, complete-plan token and whole-form scrolling pass.');
    }
    for(const mobile of [false,true]) {
        await command('Emulation.setDeviceMetricsOverride',{width:mobile?390:1440,height:mobile?844:980,deviceScaleFactor:1,mobile});
        await command('Page.navigate',{url:'http://127.0.0.1:'+server.address().port+'/?pagination=1&app_shell=1'});
        await until('document.querySelector("#scholarshipRanksPagination select")?.value==="10"');
        assert.equal(await evaluate('document.querySelectorAll(".scholarship-rank-table tbody tr").length'),10,'The full app shell shows ten records by default');
        const scrolling=await evaluate(`(()=>{
            const wrapper=document.querySelector('.page-wrapper');
            const nested=[document.querySelector('.page-body'),document.querySelector('[data-scholarship-ranks-panel]'),document.querySelector('[data-scholarship-ranks-panel]>.table-responsive')];
            return {pageScrolls:wrapper.scrollHeight>wrapper.clientHeight+1,nestedScrolls:nested.some(el=>el.scrollHeight>el.clientHeight+1 && ['auto','scroll','hidden'].includes(getComputedStyle(el).overflowY)),horizontal:document.documentElement.scrollWidth>innerWidth};
        })()`);
        assert.deepEqual(scrolling,{pageScrolls:true,nestedScrolls:false,horizontal:false},'Only the whole rank page scrolls vertically');
        const initialTop=await evaluate('document.querySelector(".scholarship-rank-card").getBoundingClientRect().top');
        await evaluate('document.querySelector(".page-wrapper").scrollTop=100000');
        await delay(100);
        assert.ok(await evaluate('document.querySelector(".scholarship-rank-card").getBoundingClientRect().top')<initialTop,'The form moves with the list');
        assert.equal(await evaluate(`(()=>{const pager=document.querySelector('#scholarshipRanksPagination').getBoundingClientRect(),footer=document.querySelector('.page-wrapper>.footer').getBoundingClientRect();return pager.top>=0 && pager.bottom<=footer.top;})()`),true,'Whole-page scrolling reaches pagination above the fixed footer');
        const shot=await command('Page.captureScreenshot',{format:'png'});
        await writeFile(join(root,'storage/app/testing/scholarship-page-scroll-'+(mobile?'mobile':'desktop')+'.png'),Buffer.from(shot.data,'base64'));
        console.log((mobile?'Mobile':'Desktop')+': ten default records and whole-form scrolling pass in the app shell.');
    }
    }
    await command('Emulation.setDeviceMetricsOverride',{width:1440,height:1200,deviceScaleFactor:1,mobile:false});
    await command('Page.navigate',{url:'http://127.0.0.1:'+server.address().port+'/test-outstanding-report'});
    await until('document.querySelectorAll(".outstanding-page").length===3');
    await evaluate('document.fonts.ready');
    assert.equal(await evaluate('[...document.querySelectorAll(".national-title,.national-motto")].every(line=>getComputedStyle(line).fontFamily.includes("Khmer OS Muol Light"))'),true,'Both Khmer national header lines use the internal Muol Light font');
    assert.deepEqual(await evaluate('[...document.querySelectorAll(".student-list tbody")].map(body=>body.querySelectorAll("tr:not(.campus-group)").length)'),[25,30,20]);
    assert.equal(await evaluate('[...document.querySelectorAll(".campus-group")].some(row=>row.textContent.includes("BCH")) && [...document.querySelectorAll(".campus-group")].some(row=>row.textContent.includes("BKK"))'),true,'Central report includes campus-name group headers');
    assert.deepEqual(await evaluate(`[...document.querySelectorAll('.student-list tbody tr:not(.campus-group)')].map(row=>Number(row.cells[0].textContent))`),Array.from({length:75},(_,i)=>i+1),'Overall numbers continue across campuses and pages');
    assert.deepEqual(await evaluate(`(()=>{const rows=[...document.querySelectorAll('.student-list tbody tr:not(.campus-group)')];return [rows[25],rows[59],rows[74]].map(row=>[row.cells[0].textContent,row.cells[1].textContent]);})()`),[['26','26'],['60','1'],['75','16']],'Campus numbers continue across page breaks and restart for BKK');
    const numberingClip=await evaluate(`(()=>{const rect=document.querySelectorAll('.student-list')[2].getBoundingClientRect();return {x:rect.left+scrollX,y:rect.top+scrollY,width:rect.width,height:rect.height,scale:1};})()`);
    const numberingShot=await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:true,clip:numberingClip});
    await writeFile(join(root,'storage/app/testing/scholarship-campus-numbering.png'),Buffer.from(numberingShot.data,'base64'));
    assert.equal(await evaluate('document.querySelectorAll(".signatures .signature-name").length'),3);
    assert.deepEqual(await evaluate('[...document.querySelectorAll(".signature-position")].map(node=>node.textContent)'),['Registrar','Academic Director','Vice President']);
    assert.equal(await evaluate('document.body.textContent.includes("SAMPLE VERIFIER") && document.body.textContent.includes("Checked and Verified by")'),true);
    assert.equal(await evaluate('document.querySelector(".school-logo").naturalWidth>0'),true,'School Logo 1 loads in browser printing');
    assert.equal(await evaluate('document.querySelector(".report-campus").textContent'),'All Campuses');
    assert.ok(await evaluate('document.querySelector(".school-logo").getBoundingClientRect().width')>240,'The report uses the enlarged 65 mm logo');
    assert.equal(await evaluate('document.querySelectorAll(".report-heading").length'),1);
    assert.equal(await evaluate('document.querySelectorAll(".signatures").length'),1);
    const reportHeadingClip=await evaluate(`(()=>{const rect=document.querySelector('.report-heading').getBoundingClientRect();return {x:rect.left+scrollX,y:rect.top+scrollY,width:rect.width,height:rect.height,scale:1};})()`);
    const reportHeadingShot=await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:true,clip:reportHeadingClip});
    await writeFile(join(root,'storage/app/testing/scholarship-report-heading.png'),Buffer.from(reportHeadingShot.data,'base64'));
    await command('Emulation.setEmulatedMedia',{media:'print'});
    const reportBounds=await evaluate(`(()=>{return [...document.querySelectorAll('.outstanding-page')].map(page=>{const content=page.querySelector('.signatures')||page.querySelector('.student-list');return {bottom:content.getBoundingClientRect().bottom-page.getBoundingClientRect().top,footer:page.querySelector('.report-page-number').getBoundingClientRect().top-page.getBoundingClientRect().top};});})()`);
    assert.equal(reportBounds.every(page=>page.bottom<page.footer),true,'Tables and final signatures fit above the printed page numbers: '+JSON.stringify(reportBounds));
    const printed=await command('Page.printToPDF',{printBackground:true,preferCSSPageSize:true,displayHeaderFooter:false});
    const printPath=join(root,'storage/app/testing/scholarship-outstanding-browser.pdf');
    await writeFile(printPath,Buffer.from(printed.data,'base64'));
    const printPages=execFileSync(php,['-r',`require 'vendor/autoload.php'; $r=new \\setasign\\Fpdi\\PdfReader\\PdfReader(new \\setasign\\Fpdi\\PdfParser\\PdfParser(\\setasign\\Fpdi\\PdfParser\\StreamReader::createByFile($argv[1]))); echo $r->getPageCount();`,printPath],{cwd:root,encoding:'utf8'});
    assert.equal(printPages,'3','Browser printing produces exactly three pages without a blank trailing page');
    await command('Emulation.setEmulatedMedia',{media:''});
    console.log('Print: 75 students fit three portrait pages with branding, repeated headings, final signatures and page numbers.');
    await command('Page.navigate',{url:'http://127.0.0.1:'+server.address().port+'/test-outstanding-report?campus=1'});
    await until('document.querySelectorAll(".signatures .signature-name").length===2');
    await evaluate('document.fonts.ready');
    assert.equal(await evaluate('document.body.textContent.includes("Checked and Verified by") || document.body.textContent.includes("SAMPLE VERIFIER")'),false,'Campus printing excludes the verifier');
    assert.equal(await evaluate('document.querySelector(".report-campus").textContent'),'BCH Campus');
    assert.equal(await evaluate(`(()=>{const cells=[...document.querySelectorAll('.signatures tr:first-child td')].map(cell=>cell.getBoundingClientRect().width);return cells.length===2 && Math.abs(cells[0]-cells[1])<1;})()`),true,'Campus signature columns have equal widths');
    await command('Emulation.setEmulatedMedia',{media:'print'});
    const campusBounds=await evaluate(`(()=>{const page=document.querySelector('.outstanding-page'),bounds=page.getBoundingClientRect();return {bottom:page.querySelector('.signatures').getBoundingClientRect().bottom-bounds.top,footer:page.querySelector('.report-page-number').getBoundingClientRect().top-bounds.top};})()`);
    assert.ok(campusBounds.bottom<campusBounds.footer,'Campus signatures fit above the footer: '+JSON.stringify(campusBounds));
    await command('Emulation.setEmulatedMedia',{media:''});
    const campusReportShot=await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});
    await writeFile(join(root,'storage/app/testing/scholarship-campus-print-report.png'),Buffer.from(campusReportShot.data,'base64'));
    console.log('Print: campus reports use two equal signature columns; Central reports retain three.');
    await command('Page.navigate',{url:'http://127.0.0.1:'+server.address().port+'/test-outstanding-report?short=1'});
    await until('document.querySelectorAll(".outstanding-page").length===2');
    await evaluate('document.fonts.ready');
    assert.deepEqual(await evaluate('[...document.querySelectorAll(".student-list tbody")].map(body=>body.querySelectorAll("tr:not(.campus-group)").length)'),[22,3]);
    assert.equal(await evaluate(`(()=>{const page=document.querySelector('.outstanding-page').getBoundingClientRect(),table=document.querySelector('.student-list').getBoundingClientRect();return table.bottom-page.top>page.height*.8;})()`),true,'The first page fills its available space instead of splitting a 25-student list in half');
    const shortReportShot=await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});
    await writeFile(join(root,'storage/app/testing/scholarship-short-print-report.png'),Buffer.from(shortReportShot.data,'base64'));
    await command('Browser.close');
} finally {socket?.close();browser.kill();server.closeAllConnections();await new Promise(resolve=>server.close(resolve));}
