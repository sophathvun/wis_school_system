import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import {test} from 'node:test';

class Element {
    constructor(){this.children=[];this.events={};this.dataset={};this.value='';this.textContent='';this.isConnected=true;this.style={};this.classes=new Set();this.classList={add:key=>this.classes.add(key),remove:key=>this.classes.delete(key),toggle:(key,on)=>on?this.classes.add(key):this.classes.delete(key)};}
    append(...items){this.children.push(...items);}
    prepend(...items){this.children.unshift(...items);}
    before(){}
    replaceChildren(...items){this.children=items;}
    setAttribute(key,value){this[key]=value;}
    getAttribute(key){return this[key];}
    addEventListener(key,fn){(this.events[key]??=[]).push(fn);}
    async emit(key,event={}){return Promise.all((this.events[key]??[]).map(fn=>fn(event)));}
    insertRow(){const row=new Element();this.append(row);return row;}
    insertCell(){return this.insertRow();}
    checkValidity(){return this.valid!==false;}
    focus(){this.focused=true;}
    select(){this.selected=true;}
    click(){this.clicked=true;}
    remove(){}
}
class Option {constructor(label,value){this.textContent=label;this.value=String(value);this.dataset={};}}
class Select extends Element {
    constructor(name){super();this.name=name;this.id=name;this['aria-label']=name;this.options=[new Option('All','')];}
    replaceChildren(...items){this.options=items;this.value=items[0]?.value??'';}
}
const fields=['khmer_essay','chemistry','earth_science','physics','geography','values','math','khmer_dictation','biology','history','english'].map(name=>'score_'+name);
function page({canEdit=true,mobile=false,saveStatus=200,failOptions=false,records=null,reportPicker=false,reportError=false,realAttendance=false}={}){
    const form=new Element(),root=new Element(),rows=new Element(),error=new Element(),status=new Element(),edit=new Element(),label=new Element(),icon=new Element(),toggle=new Element(),mode=new Element();
    const names=['academic_year','exam_center','campus','room','grade','student'];form.elements=Object.fromEntries(names.map(name=>[name,new Select(name)]));form.querySelectorAll=()=>Object.values(form.elements);
    form.elements.academic_year.options.push(new Option('2025-2026','1'));edit.querySelector=selector=>selector==='i'?icon:label;toggle.querySelector=()=>new Element();
    const perPage=new Element(),pagination=new Element();perPage.value='25';
    const entry=new Element(),subject=new Select('subject');subject.options.push(...fields.map(field=>new Option(field,field)));
    root.dataset={base:'/g9-national-exams/scoring',subjects:JSON.stringify(Object.fromEntries(fields.map(field=>[field,'Exam Score '+field]))),subjectMaxima:JSON.stringify(Object.fromEntries(fields.map((field,index)=>[field,[60,25,25,35,32,35,100,40,35,33,50][index]])))};
    root.querySelector=selector=>({'form':form,'[data-scoring-rows]':rows,'[data-scoring-error]':error,'[data-scoring-status]':status,'[data-scoring-edit]':edit,'[data-scoring-mode]':mode,'[data-scoring-filter-toggle]':toggle,'[data-scoring-entry]':entry,'[data-scoring-entry-subject]':subject}[selector]);
    const printButton=new Element(),pdfButton=new Element();printButton.dataset.scoringReport='print';pdfButton.dataset.scoringReport='pdf';
    const collectionPrint=new Element(),collectionPdf=new Element();collectionPrint.dataset={scoringReport:'print',scoringReportType:'scores'};collectionPdf.dataset={scoringReport:'pdf',scoringReportType:'scores'};
    root.querySelectorAll=selector=>selector==='[data-scoring-report]'&&reportPicker?[printButton,pdfButton,collectionPrint,collectionPdf]:[];
    const calls=[],alerts=[],warnings=[],opened=[],downloads=[],timers=new Map(),scores=Object.fromEntries(fields.map(field=>[field,null]));let timerId=0;
    const context={console,Option,URLSearchParams,matchMedia:()=>({matches:mobile,addEventListener(){}}),confirm:()=>true,
        window:{addEventListener(){},open:(...args)=>opened.push(args)},URL:{createObjectURL:()=> 'blob:verify-pdf',revokeObjectURL(){}},setTimeout:fn=>{timers.set(++timerId,fn);return timerId;},clearTimeout:id=>timers.delete(id),
        document:{body:{append:link=>downloads.push(link)},querySelector:selector=>selector==='[data-g9-scoring]'?root:{content:'csrf'},createElement:()=>new Element(),createTextNode:text=>text,getElementById:id=>id==='g9-scoring-per-page'?perPage:pagination},
        FormData:class{constructor(form){this.entries=Object.values(form.elements).map(select=>[select.name,select.value]);}[Symbol.iterator](){return this.entries[Symbol.iterator]();}},
        setupG9PhotoViewer:()=>()=>{},showSuccess:(...message)=>alerts.push(message),showError:(...message)=>warnings.push(message),showWarning:(...message)=>warnings.push(message),renderPagination(){},
        createG9CandidateAttendance:()=>({hasUnsaved:()=>false,discard(){},render(cell,record){const badge=new Element();badge.textContent=record.attendance_status||'Not marked';cell.append(badge);return {refresh(){}};}}),
        setupStaffLocationSelect:(form,select)=>{const menu=new Element(),combo=new Element();menu.querySelector=()=>new Element();combo.querySelector=()=>menu;select.nextElementSibling=combo;return {toggle:new Element(),sync(){},close(){}};},
        fetch:async(url,options)=>{
            calls.push({url,...options});
            if(url.includes('/reports/'))return {ok:!reportError,blob:async()=>({}),json:async()=>({message:'Report export failed.'})};
            if(options.method==='POST'){
                const input=JSON.parse(options.body);Object.assign(scores,input.scores);
                if(url.endsWith('/attendance')){
                    if(context.pauseAttendance)await new Promise(resolve=>context.releaseAttendance=resolve);
                    return {ok:true,json:async()=>({version:input.version+1,attendance_status:input.attendance_status,attendance_reason:input.attendance_reason})};
                }
                return {ok:saveStatus===200,status:saveStatus,json:async()=>saveStatus===200?{version:2,scores}:{message:saveStatus===409?'This candidate changed. Reload.':'Save unavailable.'}};
            }
            if(url.includes('/options?'))return {ok:!failOptions,status:failOptions?500:200,json:async()=>failOptions?{message:'Options unavailable.'}:{exam_center:['Center'],campus:[{id:1,label:'BCH'}],room:['1'],grade:['9A'],student:[{id:1,label:'001 | Candidate'}]}};
            return {ok:true,json:async()=>({data:(records||[{id:1,can_edit:canEdit}]).map(record=>({version:1,student_code:'001',name_en:'Candidate',name_kh:'សិស្ស',scores,room:'1',seat:'2',exam_language:'Khmer',...record})),from:1,total:records?.length||1,current_page:1})};
        },
    };
    vm.createContext(context);
    if(realAttendance)vm.runInContext(fs.readFileSync('resources/js/helpers/g9CandidateAttendance.js','utf8').replace(/^import .*;\r?\n/gm,'').replace('export function','function'),context);
    const source=fs.readFileSync('resources/js/g9Scoring.js','utf8').replace(/^import .*;\r?\n/gm,'').replace(/    load\(\);\s*}\s*$/,'    globalThis.initialLoad=load();\n}');
    vm.runInContext(source,context);
    return {context,form,rows,error,status,edit,label,icon,mode,calls,alerts,warnings,subject,timers,perPage,printButton,pdfButton,collectionPrint,collectionPdf,opened,downloads,flush:async()=>{for(const fn of [...timers.values()])await fn();}};
}
async function chooseSubject(state,field=fields[0]){state.subject.value=field;await state.subject.emit('change');}
async function chooseYear(state){await state.context.initialLoad;state.form.elements.academic_year.value='1';await state.form.emit('change',{target:state.form.elements.academic_year});await new Promise(resolve=>setImmediate(resolve));await chooseSubject(state);}
const inputs=state=>state.rows.children[0].children.slice(5,16).map(cell=>cell.children[0]);

test('academic year loads cascading options and all eleven subjects locked by default',async()=>{
    const state=page();await chooseYear(state);
    assert.match(state.calls[0].url,/\/options\?academic_year=1/);assert.match(state.calls[1].url,/\/list\?academic_year=1/);
    assert.equal(state.rows.children[0].children.length,19);assert.equal(inputs(state).length,11);
    assert.ok(inputs(state).every(input=>input.readOnly));assert.equal(state.edit.hidden,false);assert.equal(state.label.textContent,'Edit');
    assert.equal(state.edit.dataset.actionNormalized,'true');
});
test('filters expand on desktop and collapse on mobile',async()=>{
    for(const mobile of [false,true]){const state=page({mobile});await state.context.initialLoad;assert.equal(state.form.hidden,mobile);}
});

test('the score collection Print and PDF items use their own report path and selected filters',async()=>{
    const state=page({reportPicker:true});await chooseYear(state);state.form.elements.room.value='18';
    await state.collectionPrint.emit('click');assert.match(state.opened[0][0],/\/reports\/scores\/print\?/);assert.match(state.opened[0][0],/room=18/);
    await state.collectionPdf.emit('click');assert.match(state.calls.at(-1).url,/\/reports\/scores\/pdf\?/);assert.equal(state.downloads[0].download,'g9-score-collection.pdf');
    await state.printButton.emit('click');assert.match(state.opened[1][0],/\/reports\/verify\/print\?/);
});

test('each subject is red strictly below half of its maximum including zero and fractional boundaries',async()=>{
    const maxima=[60,25,25,35,32,35,100,40,35,33,50];
    for(const offset of [-.001,0,.001]){
        const scores=Object.fromEntries(fields.map((field,index)=>[field,String(maxima[index]/2+offset)]));
        const state=page({records:[{id:1,can_edit:false,scores}]});await chooseYear(state);
        assert.deepEqual(inputs(state).map(input=>input.classes.has('is-below-half')),Array(11).fill(offset<0));
    }
    const scores=Object.fromEntries(fields.map(field=>[field,'0']));scores.score_math=null;
    const state=page({records:[{id:1,can_edit:true,scores}]});await chooseYear(state);
    assert.equal(inputs(state)[0].classes.has('is-below-half'),true);assert.equal(inputs(state)[6].classes.has('is-below-half'),false);
});

test('subject score color updates during editing and clears at half, when blank or invalid',async()=>{
    const state=page();await chooseYear(state);await chooseSubject(state,'score_math');await state.edit.emit('click');
    const input=inputs(state)[6];
    for(const [value,expected] of [['49.999',true],['50',false],['0',true],['',false]]){
        input.value=value;await input.emit('input');assert.equal(input.classes.has('is-below-half'),expected);
    }
    input.value='-1';input.valid=false;await input.emit('input');assert.equal(input.classes.has('is-below-half'),false);
});

test('Attendance follows Total Score, autosaves and shares its version with later score changes',async()=>{
    const state=page({realAttendance:true});await chooseYear(state);
    const cells=state.rows.children[0].children,attendance=cells[17].children[0].children[0];
    assert.equal(cells.length,19);assert.equal(attendance.checked,false);assert.equal(attendance.disabled,false);
    attendance.checked=true;await attendance.emit('change');await new Promise(resolve=>setImmediate(resolve));
    const saved=state.calls.find(call=>call.url.endsWith('/attendance'));assert.match(saved.url,/\/scoring\/1\/attendance$/);
    assert.deepEqual(JSON.parse(saved.body),{version:1,attendance_status:'absent',attendance_reason:''});
    await state.edit.emit('click');assert.equal(attendance.disabled,true);
    const input=inputs(state)[0];input.value='25';await input.emit('input');await state.edit.emit('click');
    assert.equal(attendance.disabled,false);
    const scoreSave=state.calls.filter(call=>call.method==='POST'&&!call.url.endsWith('/attendance')).at(-1);
    assert.equal(JSON.parse(scoreSave.body).version,2);
});

test('pending attendance blocks score editing and filter changes; read-only users see a badge',async()=>{
    const state=page({realAttendance:true});await chooseYear(state);state.context.pauseAttendance=true;
    const attendance=state.rows.children[0].children[17].children[0].children[0];attendance.checked=true;await attendance.emit('change');
    assert.equal(state.edit.disabled,true);await state.edit.emit('click');assert.equal(state.label.textContent,'Edit');
    state.form.elements.academic_year.value='';await state.form.emit('change',{target:state.form.elements.academic_year});
    assert.equal(state.form.elements.academic_year.value,'1');
    state.context.releaseAttendance();await new Promise(resolve=>setImmediate(resolve));assert.equal(state.edit.disabled,false);
    const readonly=page({realAttendance:true,canEdit:false});await chooseYear(readonly);
    assert.equal(readonly.rows.children[0].children[17].children[0].textContent,'—');
});

test('reason drafts block score editing until autosave finishes with the next record version',async()=>{
    const state=page({realAttendance:true,records:[{id:1,can_edit:true,attendance_status:'absent',attendance_reason:'Original'}]});await chooseYear(state);
    const wrapper=state.rows.children[0].children[17].children[0],reason=wrapper.children[2];
    assert.equal(reason.hidden,false);assert.equal(reason.value,'Original');reason.value='Medical leave';await reason.emit('input');
    assert.equal(state.edit.disabled,true);await state.edit.emit('click');assert.equal(state.label.textContent,'Edit');
    state.context.confirm=()=>false;state.form.elements.academic_year.value='';await state.form.emit('change',{target:state.form.elements.academic_year});
    assert.equal(state.form.elements.academic_year.value,'1');assert.equal(reason.value,'Medical leave');
    await reason.emit('blur');await new Promise(resolve=>setImmediate(resolve));
    assert.equal(state.edit.disabled,false);assert.equal(reason.value,'Medical leave');
    const saved=JSON.parse(state.calls.find(call=>call.url.endsWith('/attendance')).body);assert.equal(saved.attendance_reason,'Medical leave');
    await state.edit.emit('click');inputs(state)[0].value='20';await inputs(state)[0].emit('input');await state.edit.emit('click');
    assert.equal(JSON.parse(state.calls.filter(call=>call.method==='POST'&&!call.url.endsWith('/attendance')).at(-1).body).version,2);
});
test('Edit unlocks only the selected subject and autosave sends changed scores including zero',async()=>{
    const state=page();await chooseYear(state);await chooseSubject(state,'score_math');await state.edit.emit('click');
    assert.equal(state.label.textContent,'Lock');assert.equal(state.icon.className,'ti ti-lock');assert.equal(inputs(state)[6].readOnly,false);assert.ok(inputs(state).filter((_,i)=>i!==6).every(input=>input.readOnly));
    const math=inputs(state)[6];math.value='0';await math.emit('input');assert.equal(state.calls.length,2);await state.flush();
    const payload=JSON.parse(state.calls[2].body);assert.deepEqual(payload,{version:1,scores:{score_math:'0'}});
    assert.equal(state.status.textContent,'Saved scores for Candidate.');assert.equal(state.alerts.at(-1)[0],'Scores saved');
    await state.edit.emit('click');assert.equal(state.label.textContent,'Edit');assert.ok(inputs(state).every(input=>input.readOnly));
});
test('Lock flushes pending changes and cancels the debounce without a duplicate save',async()=>{
    const state=page();await chooseYear(state);await state.edit.emit('click');const input=inputs(state)[0];input.value='42.125';await input.emit('input');
    await state.edit.emit('click');await state.flush();assert.equal(state.calls.filter(call=>call.method==='POST').length,1);assert.equal(state.label.textContent,'Edit');
});
test('invalid scores prevent locking and preserve unsaved changes',async()=>{
    const state=page();await chooseYear(state);await state.edit.emit('click');const input=inputs(state)[0];input.value='-1';input.valid=false;await input.emit('input');
    await state.edit.emit('click');assert.equal(state.label.textContent,'Lock');assert.match(state.error.textContent,/not saved/);assert.equal(state.calls.length,2);assert.equal(input.value,'-1');
});
test('save conflicts keep scores unsaved and require reloading rather than overwriting',async()=>{
    const state=page({saveStatus:409});await chooseYear(state);await state.edit.emit('click');const input=inputs(state)[0];input.value='25';await input.emit('input');await state.flush();
    const statusCell=state.rows.children[0].children[18];assert.match(statusCell.children[0].textContent,/not saved/);assert.equal(statusCell.children[2].textContent,'Reload');
    assert.equal(input.value,'25');assert.equal(input.readOnly,true);await state.edit.emit('click');assert.equal(state.label.textContent,'Lock');assert.equal(state.calls.length,3);
});
test('incomplete numeric input is kept dirty even when the browser exposes an empty value',async()=>{
    const state=page();await chooseYear(state);await state.edit.emit('click');const input=inputs(state)[0];input.value='';input.valid=false;input.validity={badInput:true};await input.emit('input');
    await state.edit.emit('click');assert.equal(state.label.textContent,'Lock');assert.match(state.error.textContent,/not saved/);assert.equal(state.calls.length,2);
});
test('view-only users cannot unlock scores and filter failures remain visible',async()=>{
    const view=page({canEdit:false});await chooseYear(view);assert.equal(view.edit.hidden,true);assert.ok(inputs(view).every(input=>input.disabled));
    const failed=page({failOptions:true});await chooseYear(failed);assert.equal(failed.error.textContent,'Options unavailable.');assert.equal(failed.calls.length,1);
});
test('changing filters discards only confirmed edits and cancels pending autosave',async()=>{
    const state=page();await chooseYear(state);await state.edit.emit('click');const input=inputs(state)[0];input.value='25';await input.emit('input');
    state.context.confirm=()=>false;state.form.elements.campus.value='1';await state.form.emit('change',{target:state.form.elements.campus});assert.equal(state.form.elements.campus.value,'');
    state.context.confirm=()=>true;state.form.elements.campus.value='1';await state.form.emit('change',{target:state.form.elements.campus});await new Promise(resolve=>setImmediate(resolve));await state.flush();
    assert.equal(state.calls.filter(call=>call.method==='POST').length,0);assert.equal(state.label.textContent,'Edit');
});

test('Edit requires a subject selection',async()=>{
    const state=page();await chooseYear(state);await chooseSubject(state,'');await state.edit.emit('click');
    assert.equal(state.label.textContent,'Edit');assert.equal(state.warnings[0][0],'Select a subject');assert.ok(inputs(state).every(input=>input.readOnly));
});
test('Tab moves down the selected subject and Shift+Tab moves up, skipping view-only students',async()=>{
    const state=page({records:[{id:1,can_edit:true},{id:2,can_edit:false},{id:3,can_edit:true}]});await chooseYear(state);await state.edit.emit('click');
    const first=inputs(state)[0],last=state.rows.children[2].children[5].children[0];let prevented=false;
    await first.emit('keydown',{key:'Tab',preventDefault(){prevented=true;}});assert.equal(prevented,true);assert.equal(last.focused,true);assert.equal(last.selected,true);
    first.focused=false;await last.emit('keydown',{key:'Tab',shiftKey:true,preventDefault(){}});assert.equal(first.focused,true);
    prevented=false;await last.emit('keydown',{key:'Tab',preventDefault(){prevented=true;}});assert.equal(prevented,false);
    prevented=false;await first.emit('keydown',{key:'Tab',shiftKey:true,preventDefault(){prevented=true;}});assert.equal(prevented,false);
});
test('Tab saves the current score and keeps invalid input in place',async()=>{
    const state=page({records:[{id:1,can_edit:true},{id:2,can_edit:true}]});await chooseYear(state);await state.edit.emit('click');
    const first=inputs(state)[0],next=state.rows.children[1].children[5].children[0];first.value='55';await first.emit('input');await first.emit('keydown',{key:'Tab',preventDefault(){}});
    await new Promise(resolve=>setImmediate(resolve));assert.equal(next.focused,true);assert.equal(state.calls.filter(call=>call.method==='POST').length,1);assert.equal(state.timers.size,0);
    next.value='-1';next.valid=false;let prevented=false;await next.emit('keydown',{key:'Tab',shiftKey:true,preventDefault(){prevented=true;}});assert.equal(prevented,true);
});
test('changing subject flushes pending changes before unlocking the new column',async()=>{
    const state=page();await chooseYear(state);await state.edit.emit('click');const first=inputs(state)[0];first.value='55';await first.emit('input');
    await chooseSubject(state,'score_math');assert.equal(state.calls.filter(call=>call.method==='POST').length,1);assert.equal(first.readOnly,true);assert.equal(inputs(state)[6].readOnly,false);assert.equal(state.label.textContent,'Lock');
});
test('failed autosave prevents changing subject and preserves the original column',async()=>{
    const state=page({saveStatus:500});await chooseYear(state);await state.edit.emit('click');inputs(state)[0].value='55';await inputs(state)[0].emit('input');
    await chooseSubject(state,'score_math');assert.equal(state.subject.value,fields[0]);assert.equal(inputs(state)[0].value,'55');assert.equal(inputs(state)[6].readOnly,true);assert.match(state.error.textContent,/before changing subjects/);
});
test('EOY-Avg precedes subjects and Khmer Total Score includes only the English score above 25',async()=>{
    const scoreValues=Object.fromEntries(fields.map(field=>[field,'42.500']));
    const state=page({records:[{id:1,can_edit:true,eoy_average:'90.000',scores:scoreValues}]});await chooseYear(state);
    const cells=state.rows.children[0].children;
    assert.equal(cells[4].children[0].textContent,'90');assert.equal(cells[16].children[0].textContent,'532.5');assert.equal(cells[16].children[1].textContent,'');
    await state.edit.emit('click');inputs(state)[0].value='43.625';await inputs(state)[0].emit('input');assert.equal(cells[16].children[0].textContent,'533.625');
});
test('totals flag missing scores, keep zero scores valid, and avoid floating point errors',async()=>{
    const zeroScores=Object.fromEntries(fields.map(field=>[field,'0']));
    const state=page({records:[{id:1,can_edit:true,eoy_average:'0',scores:zeroScores}]});await chooseYear(state);
    const total=state.rows.children[0].children[16];assert.equal(total.children[0].textContent,'0');assert.equal(total.children[1].textContent,'');
    await state.edit.emit('click');inputs(state)[0].value='0.1';await inputs(state)[0].emit('input');await chooseSubject(state,'score_chemistry');
    inputs(state)[1].value='0.2';await inputs(state)[1].emit('input');assert.equal(total.children[0].textContent,'0.3');
    inputs(state)[1].value='';await inputs(state)[1].emit('input');assert.equal(total.children[0].textContent,'0.1');assert.equal(total.children[1].textContent,'Partial');
    inputs(state)[1].value='-1';inputs(state)[1].valid=false;await inputs(state)[1].emit('input');assert.equal(total.children[0].textContent,'—');assert.equal(total.children[1].textContent,'Check scores');
});
test('an empty score record has no total until a score is entered',async()=>{
    const state=page();await chooseYear(state);const total=state.rows.children[0].children[16];assert.equal(total.children[0].textContent,'—');assert.equal(total.children[1].textContent,'');
    await state.edit.emit('click');inputs(state)[0].value='0';await inputs(state)[0].emit('input');assert.equal(total.children[0].textContent,'0');assert.equal(total.children[1].textContent,'Partial');
});
test('English-exam totals exclude English even when blank or filled and support language aliases',async()=>{
    for(const language of ['English',' en ','អង់គ្លេស']){
        for(const english of [null,'99.999']){
            const scores=Object.fromEntries(fields.map(field=>[field,'40']));scores.score_english=english;
            const state=page({records:[{id:1,can_edit:true,eoy_average:'90',exam_language:language,scores}]});await chooseYear(state);
            const cells=state.rows.children[0].children;assert.equal(cells[16].children[0].textContent,'490');assert.equal(cells[16].children[1].textContent,'');assert.equal(cells[18].children[1].textContent,'10 / 10 recorded');
        }
    }
});
test('Khmer-exam English contribution is zero through 25 and only the excess above 25 counts',async()=>{
    for(const language of ['Khmer',' kh ','km','ខ្មែរ']){
        for(const [english,expected] of [['0','490'],['24.999','490'],['25','490'],['25.001','490.001'],['50','515']]){
            const scores=Object.fromEntries(fields.map(field=>[field,'40']));scores.score_english=english;
            const state=page({records:[{id:1,can_edit:true,eoy_average:'90',exam_language:language,scores}]});await chooseYear(state);
            const total=state.rows.children[0].children[16];assert.equal(total.children[0].textContent,expected);assert.equal(total.children[1].textContent,'');
        }
    }
});
test('missing Khmer English score remains partial and unknown exam language never assumes a formula',async()=>{
    const scores=Object.fromEntries(fields.map(field=>[field,'40']));scores.score_english=null;
    const khmer=page({records:[{id:1,can_edit:true,eoy_average:'90',exam_language:'ខ្មែរ',scores}]});await chooseYear(khmer);
    assert.equal(khmer.rows.children[0].children[16].children[0].textContent,'490');assert.equal(khmer.rows.children[0].children[16].children[1].textContent,'Partial');
    for(const language of ['',null,'Other']){
        const state=page({records:[{id:1,can_edit:true,eoy_average:'90',exam_language:language,scores}]});await chooseYear(state);
        const total=state.rows.children[0].children[16];assert.equal(total.children[0].textContent,'—');assert.equal(total.children[1].textContent,'Set exam language');
    }
});
test('English adjustment updates immediately while editing without changing the entered raw score',async()=>{
    const scores=Object.fromEntries(fields.map(field=>[field,'40']));scores.score_english='25';
    const state=page({records:[{id:1,can_edit:true,eoy_average:'90',exam_language:'Khmer',scores}]});await chooseYear(state);await chooseSubject(state,'score_english');await state.edit.emit('click');
    const english=inputs(state)[10];english.value='50';await english.emit('input');assert.equal(state.rows.children[0].children[16].children[0].textContent,'515');assert.equal(english.value,'50');
    english.value='20';await english.emit('input');assert.equal(state.rows.children[0].children[16].children[0].textContent,'490');assert.equal(english.value,'20');
});
test('EOY-Avg displays whole-number rounding and Total Score uses the same rounded value',async()=>{
    const scores=Object.fromEntries(fields.map(field=>[field,'40']));
    for(const [saved,rounded,total] of [['90.499','90','490'],['90.500','91','491'],['90.999','91','491'],['0.000','0','400']]){
        const state=page({records:[{id:1,can_edit:true,eoy_average:saved,exam_language:'English',scores}]});await chooseYear(state);
        const cells=state.rows.children[0].children;assert.equal(cells[4].children[0].textContent,rounded);assert.equal(cells[16].children[0].textContent,total);
    }
    const missing=page({records:[{id:1,can_edit:true,eoy_average:null,exam_language:'English',scores}]});await chooseYear(missing);
    const cells=missing.rows.children[0].children;assert.equal(cells[4].children[0].textContent,'—');assert.equal(cells[16].children[1].textContent,'Partial');
});
test('Result and Khmer grade use the supplied total-score boundaries including decimals',async()=>{
    for(const [score,result,grade] of [['0','ធ្លាក់','ធ្លាក់'],['259.999','ធ្លាក់','ធ្លាក់'],['260','ជាប់','មធ្យម'],['337','ជាប់','មធ្យម'],['337.999','ជាប់','មធ្យម'],['338','ជាប់','ល្អបង្គួរ'],['415','ជាប់','ល្អបង្គួរ'],['415.999','ជាប់','ល្អបង្គួរ'],['416','ជាប់','ល្អ'],['500','ជាប់','ល្អ']]){
        const scores=Object.fromEntries(fields.map(field=>[field,'0']));scores.score_math=score;
        const state=page({records:[{id:1,can_edit:true,eoy_average:'0',exam_language:'English',scores}]});await chooseYear(state);
        const cells=state.rows.children[0].children;assert.equal(cells[16].children[2].children[0].textContent,result);assert.equal(cells[16].children[2].children[1].textContent,grade);
    }
});
test('incomplete, invalid and unknown-language scores do not publish a result or grade',async()=>{
    const scores=Object.fromEntries(fields.map(field=>[field,'40']));
    for(const record of [{eoy_average:null},{exam_language:'Other'},{scores:{...scores,score_math:null}}]){
        const state=page({records:[{id:1,can_edit:true,eoy_average:'0',exam_language:'English',scores,...record}]});await chooseYear(state);
        const cells=state.rows.children[0].children;assert.equal(cells[16].children[2].hidden,true);assert.equal(cells[16].children[2].children[0].textContent,'');assert.equal(cells[16].children[2].children[1].textContent,'');
    }
    const state=page({records:[{id:1,can_edit:true,eoy_average:'0',exam_language:'English',scores}]});await chooseYear(state);await state.edit.emit('click');inputs(state)[0].value='-1';inputs(state)[0].valid=false;await inputs(state)[0].emit('input');
    const cells=state.rows.children[0].children;assert.equal(cells[16].children[2].hidden,true);assert.equal(cells[16].children[2].children[0].textContent,'');assert.equal(cells[16].children[2].children[1].textContent,'');
});
test('Khmer English bonus and rounded EOY contribute before determining Result and grade',async()=>{
    const scores=Object.fromEntries(fields.map(field=>[field,'0']));scores.score_math='234';scores.score_english='50';
    const state=page({records:[{id:1,can_edit:true,eoy_average:'0.5',exam_language:'ខ្មែរ',scores}]});await chooseYear(state);
    const cells=state.rows.children[0].children;assert.equal(cells[16].children[0].textContent,'260');assert.equal(cells[16].children[2].children[0].textContent,'ជាប់');assert.equal(cells[16].children[2].children[1].textContent,'មធ្យម');
    await chooseSubject(state,'score_english');await state.edit.emit('click');inputs(state)[10].value='49.999';await inputs(state)[10].emit('input');
    assert.equal(cells[16].children[0].textContent,'259.999');assert.equal(cells[16].children[2].children[0].textContent,'ធ្លាក់');assert.equal(cells[16].children[2].children[1].textContent,'ធ្លាក់');
});
test('result and grade badges stack inside Total Score with distinct grades and red failure styling',async()=>{
    const gradeClasses=new Set();
    for(const [score,style] of [['259','is-failed'],['260','is-average'],['338','is-good'],['416','is-very-good']]){
        const scores=Object.fromEntries(fields.map(field=>[field,'0']));scores.score_math=score;
        const state=page({records:[{id:1,can_edit:true,eoy_average:'0',exam_language:'English',scores}]});await chooseYear(state);
        const cells=state.rows.children[0].children,outcomes=cells[16].children[2];assert.equal(cells.length,19);assert.equal(outcomes.hidden,false);
        assert.match(outcomes.children[0].className,/badge/);assert.match(outcomes.children[1].className,new RegExp(style));gradeClasses.add(outcomes.children[1].className);
        assert.equal(outcomes.children[0]['aria-label'].startsWith('Result: '),true);assert.equal(outcomes.children[1]['aria-label'].startsWith('Letter Grade: '),true);
        if(score==='259')assert.match(outcomes.children[0].className,/is-failed/);
    }
    assert.equal(gradeClasses.size,4);
});
test('Total Score gets red styling below 260 and clears it at 260 without coloring empty totals',async()=>{
    const scores=Object.fromEntries(fields.map(field=>[field,'0']));scores.score_math='259.999';
    const state=page({records:[{id:1,can_edit:true,eoy_average:'0',exam_language:'English',scores}]});await chooseYear(state);
    const total=state.rows.children[0].children[16].children[0],toggles=[];
    total.classList.toggle=(name,enabled)=>toggles.push({name,enabled});
    await chooseSubject(state,'score_math');assert.deepEqual(toggles.at(-1),{name:'is-below-pass',enabled:true});
    await state.edit.emit('click');inputs(state)[6].value='260';await inputs(state)[6].emit('input');assert.deepEqual(toggles.at(-1),{name:'is-below-pass',enabled:false});
    inputs(state)[6].value='0';await inputs(state)[6].emit('input');assert.deepEqual(toggles.at(-1),{name:'is-below-pass',enabled:true});
    inputs(state)[6].value='-1';inputs(state)[6].valid=false;await inputs(state)[6].emit('input');assert.equal(total.textContent,'—');assert.deepEqual(toggles.at(-1),{name:'is-below-pass',enabled:false});
});
test('Print and PDF use active filters for all matching candidates without pagination or subject selection',async()=>{
    const state=page({reportPicker:true});await chooseYear(state);state.form.elements.campus.value='1';state.form.elements.room.value='2';state.perPage.value='50';
    await state.printButton.emit('click');assert.equal(state.opened.length,1);const url=state.opened[0][0];
    assert.match(url,/\/reports\/verify\/print\?/);assert.match(url,/academic_year=1/);assert.match(url,/campus=1/);assert.match(url,/room=2/);assert.doesNotMatch(url,/per_page=|subject=|page=/);
    await state.pdfButton.emit('click');assert.equal(state.downloads[0].download,'g9-scoring-verification.pdf');assert.equal(state.downloads[0].clicked,true);assert.equal(state.pdfButton.disabled,false);
});
test('reports require an academic year and block unsaved scores or show export errors',async()=>{
    const state=page({reportPicker:true});await state.context.initialLoad;await state.printButton.emit('click');assert.equal(state.warnings.at(-1)[0],'Select an academic year');assert.equal(state.opened.length,0);
    await chooseYear(state);await state.edit.emit('click');inputs(state)[0].value='1';await inputs(state)[0].emit('input');await state.printButton.emit('click');assert.equal(state.warnings.at(-1)[0],'Scores are not ready');assert.equal(state.opened.length,0);
    const failed=page({reportPicker:true,reportError:true});await chooseYear(failed);await failed.pdfButton.emit('click');assert.equal(failed.error.textContent,'Report export failed.');assert.equal(failed.pdfButton.disabled,false);assert.equal(failed.downloads.length,0);
});
