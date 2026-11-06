import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import {test} from 'node:test';

class Element {
    constructor() { this.children=[];this.events={};this.classes=new Set();this.classList={toggle:(key,on)=>on?this.classes.add(key):this.classes.delete(key)}; }
    append(...items) { this.children.push(...items); }
    setAttribute(key,value) { this[key]=value; }
    addEventListener(key,fn) { this.events[key]=fn; }
    emit(key,event={}) { return this.events[key]?.(event); }
}
function page({readonly=false,status=null,reason='',fail=false,pause=false}={}) {
    const cell=new Element(),calls=[],success=[],errors=[],events={},timers=new Map();let timerId=0;
    const record={id:12,version:3,name_en:'Candidate',attendance_status:status,attendance_reason:reason,can_edit:!readonly};
    const context={
        document:{createElement:()=>new Element(),querySelector:()=>({content:'csrf'})},
        window:{addEventListener:(name,fn)=>events[name]=fn},
        setTimeout:fn=>{timers.set(++timerId,fn);return timerId;},clearTimeout:id=>timers.delete(id),
        Option:class{constructor(label,value){this.textContent=label;this.value=value;}},
        showSuccess:text=>success.push(text),showError:(title,message)=>errors.push({title,message}),
        fetch:async(url,options)=>{
            const body=JSON.parse(options.body);calls.push({url,options,body});
            if(context.pause)await new Promise(resolve=>context.release=resolve);
            return {ok:!context.fail,json:async()=>context.fail?{message:'This candidate changed. Refresh the list.'}:{version:body.version+1,attendance_status:body.attendance_status,attendance_reason:body.attendance_reason}};
        },fail,pause,
    };
    vm.createContext(context);
    vm.runInContext(fs.readFileSync('resources/js/helpers/g9CandidateAttendance.js','utf8').replace(/^import .*;\r?\n/gm,'').replace('export function','function'),context);
    const attendance=context.createG9CandidateAttendance('/g9-national-exams/scoring');attendance.render(cell,record);
    const wrapper=cell.children[0],select=wrapper.children[0],textarea=wrapper.children[2],note=wrapper.children[3],retry=wrapper.children[4];
    return {context,cell,record,calls,success,errors,events,attendance,wrapper,select,note,textarea,retry,timers,flush:async()=>{for(const fn of [...timers.values()])fn();await attendance.whenIdle();}};
}

test('existing candidates start not marked and render without a save request',()=>{
    const state=page();assert.equal(state.select.checked,false);assert.equal(state.calls.length,0);
    assert.equal(state.select.type,'checkbox');assert.equal(state.wrapper.children[1].textContent,'Absent');
    assert.equal(state.textarea.hidden,true);
});

test('marking absent autosaves its version and clearing sends null',async()=>{
    const state=page();state.select.checked=true;state.select.emit('change');await state.attendance.whenIdle();
    assert.equal(state.calls[0].url,'/g9-national-exams/scoring/12/attendance');
    assert.equal(state.calls[0].body.version,3);assert.equal(state.calls[0].body.attendance_status,'absent');
    assert.equal(state.calls[0].options.headers['X-CSRF-TOKEN'],'csrf');
    assert.equal(state.record.version,4);assert.equal(state.record.attendance_status,'absent');
    assert.equal(state.note.textContent,'Saved');assert.equal(state.wrapper.classes.has('is-absent'),true);
    assert.equal(state.textarea.hidden,false);
    state.select.checked=false;state.select.emit('change');await state.attendance.whenIdle();
    assert.equal(state.calls[1].body.attendance_status,null);assert.equal(state.calls[1].body.version,4);
    assert.equal(state.record.attendance_status,null);assert.equal(state.success.length,2);
    assert.equal(state.textarea.hidden,true);
});

test('read-only candidates display a status badge and no editable input',()=>{
    const state=page({readonly:true,status:'absent'});
    assert.equal(state.cell.children[0].textContent,'Absent');
    assert.match(state.cell.children[0].className,/bg-red-lt/);assert.equal(state.cell.children[0].children.length,0);
    assert.equal(state.calls.length,0);
});

test('failed saves restore the saved value and allow retry without losing version',async()=>{
    const state=page({status:'present',fail:true});state.select.checked=true;state.select.emit('change');await state.attendance.whenIdle();
    assert.equal(state.select.checked,false);assert.equal(state.record.version,3);assert.equal(state.record.attendance_status,'present');
    assert.match(state.errors[0].message,/Refresh/);assert.equal(state.select.disabled,false);assert.equal(state.success.length,0);
    state.context.fail=false;state.select.checked=true;state.select.emit('change');await state.attendance.whenIdle();
    assert.equal(state.record.attendance_status,'absent');assert.equal(state.calls[1].body.version,3);
});

test('pending saves lock the selection, guard navigation and finish before list reloads',async()=>{
    const state=page({pause:true});state.select.checked=true;state.select.emit('change');
    assert.equal(state.select.disabled,true);let guarded=false;
    state.events.beforeunload({preventDefault(){guarded=true;}});assert.equal(guarded,true);
    state.select.emit('change');assert.equal(state.calls.length,1);
    let idle=false;const wait=state.attendance.whenIdle().then(()=>idle=true);await Promise.resolve();assert.equal(idle,false);
    state.context.release();await wait;assert.equal(state.record.attendance_status,'absent');assert.equal(state.select.disabled,false);
    guarded=false;state.events.beforeunload({preventDefault(){guarded=true;}});assert.equal(guarded,false);
});

test('saved reasons load and editing autosaves after a pause or blur',async()=>{
    const state=page({status:'absent',reason:'Old reason'});assert.equal(state.textarea.hidden,false);assert.equal(state.textarea.value,'Old reason');
    state.textarea.value=' Sick leave ';state.textarea.emit('input');assert.equal(state.calls.length,0);assert.equal(state.attendance.hasUnsaved(),true);
    let guarded=false;state.events.beforeunload({preventDefault(){guarded=true;}});assert.equal(guarded,true);
    await state.flush();assert.equal(state.calls[0].body.attendance_reason,'Sick leave');assert.equal(state.record.attendance_reason,'Sick leave');
    assert.equal(state.attendance.hasUnsaved(),false);assert.equal(state.textarea.value,'Sick leave');
    state.textarea.value='Updated reason';state.textarea.emit('input');state.textarea.emit('blur');await state.attendance.whenIdle();
    assert.equal(state.calls[1].body.version,4);assert.equal(state.record.attendance_reason,'Updated reason');assert.equal(state.timers.size,0);
});

test('failed reason autosave preserves the draft and retry uses the saved version',async()=>{
    const state=page({status:'absent',reason:'Old reason',fail:true});state.textarea.value='New reason';state.textarea.emit('input');await state.flush();
    assert.equal(state.record.attendance_reason,'Old reason');assert.equal(state.textarea.value,'New reason');assert.equal(state.attendance.hasUnsaved(),true);assert.equal(state.retry.hidden,false);
    state.context.fail=false;state.retry.emit('click');await state.attendance.whenIdle();
    assert.equal(state.record.attendance_reason,'New reason');assert.equal(state.calls[1].body.version,3);assert.equal(state.attendance.hasUnsaved(),false);
});

test('unticking clears the reason and cancels delayed autosave; discarding cancels drafts',async()=>{
    const state=page({status:'absent',reason:'Old reason'});state.textarea.value='Unsaved draft';state.textarea.emit('input');
    state.select.checked=false;state.select.emit('change');await state.attendance.whenIdle();
    assert.equal(state.calls.length,1);assert.equal(state.calls[0].body.attendance_reason,null);assert.equal(state.textarea.hidden,true);assert.equal(state.textarea.value,'');assert.equal(state.attendance.hasUnsaved(),false);
    const discarded=page({status:'absent'});discarded.textarea.value='Draft';discarded.textarea.emit('input');discarded.attendance.discard();await discarded.flush();
    assert.equal(discarded.calls.length,0);assert.equal(discarded.attendance.hasUnsaved(),false);
});

test('read-only absence reasons render safely as text',()=>{
    const state=page({readonly:true,status:'absent',reason:'<b>Medical</b>\nLeave'});
    assert.equal(state.cell.children[1].textContent,'<b>Medical</b>\nLeave');assert.equal(state.calls.length,0);
});
