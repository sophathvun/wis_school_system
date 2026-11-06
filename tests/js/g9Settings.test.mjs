import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import {test} from 'node:test';

class Element{
    constructor(){this.value='';this.events={};this.dataset={};this.children=[];this.checked=false;this.disabled=false;this.classList={add(){},remove(){},toggle(){}};this.validity={valid:true};}
    addEventListener(name,handler){(this.events[name]??=[]).push(handler);}
    async emit(name,event={}){await Promise.all((this.events[name]??[]).map(handler=>handler(event)));}
    closest(){return null;}
    removeAttribute(name){delete this[name];}
    setAttribute(name,value){this[name]=value;}
    focus(){this.focused=true;}
    reportValidity(){this.reported=true;}
    click(){}
}
class Option{constructor(label,value){this.textContent=label;this.value=String(value);this.dataset={};}}
class Select extends Element{
    constructor(){super();this.options=[];}
    replaceChildren(...options){this.options=options;this.value=options[0]?.value||'';}
}
const dateKeys=['listDate','resultDate','resultDateDOEYS','cardDate','certDate'];
const defaultDates=()=>Object.fromEntries(dateKeys.map(key=>[key,{date:'2026-07-01',solar:'Year '+key,lunar:'Lunar '+key}]));
const settle=async()=>{for(let i=0;i<4;i++)await new Promise(resolve=>setImmediate(resolve));};
function page({canManage=true,failCenter=false,saveFailure=false}={}){
    const root=new Element(),year=new Select(),center=new Select(),location=new Element(),form=new Element(),save=new Element(),message=new Element();
    year.id='year';center.id='center';year.options=[new Option('2025-2026','1'),new Option('2026-2027','2')];year.value='1';center.options=[new Option('Academic-year defaults','')];
    const preview=new Element(),dropzone=new Element(),file=new Element(),remove=new Element(),uploadTitle=new Element(),title=new Element(),scope=new Element();
    const fields=['department','signer','signer_name'].map(name=>{const input=new Element();input.dataset.centerField=name;return input;});
    const sections=dateKeys.map(key=>{
        const section=new Element();section.dataset.dateSection=key;section.date=new Element();section.solar=new Element();section.lunar=new Element();section.inherit=new Element();section.inheritWrap=new Element();section.label=new Element();section.edit=new Element();section.state=new Element();section.state.id='state-'+key;
        section.querySelector=selector=>({'[data-setting-date]':section.date,'[data-setting-solar]':section.solar,'[data-setting-lunar]':section.lunar,'[data-setting-inherit]':section.inherit,'[data-inherit-control]':section.inheritWrap,'[data-setting-edit-date]':section.edit,'[data-setting-date-state]':section.state,'.premium-form-field>label':section.label}[selector]);return section;
    });
    root.dataset.base='/g9-national-exams/settings';
    root.querySelector=selector=>({'[data-setting-year]':year,'[data-setting-center]':center,'[data-setting-location]':location,'[data-date-settings]':form,'[data-setting-save]':save,'[data-setting-message]':message,'[data-signature-preview]':preview,'[data-signature-dropzone]':dropzone,'[data-signature-file]':file,'[data-signature-remove]':remove,'[data-signature-title]':uploadTitle,'[data-center-settings-title]':title,'[data-setting-scope]':scope}[selector]);
    root.querySelectorAll=selector=>selector==='[data-center-field]'?fields:selector==='[data-date-section]'?sections:[];
    const calls=[],alerts=[],readers=[],combos=new Map(),store=new Map();let version=1,eoyLoads=0;
    const context={console,Option,URLSearchParams,confirm:()=>true,window:{addEventListener(){},formatKhmerSolarDate:(date,province)=>province+' '+date,formatKhmerLunarDate:date=>'Lunar '+date},
        document:{querySelector:selector=>selector==='[data-g9-settings]'?root:{content:'csrf'}},
        setupStaffLocationSelect:(root,select)=>{const menu=new Element();select.nextElementSibling={querySelector:()=>menu};const combo={sync(){},toggle:new Element()};combos.set(select,combo);return combo;},
        setupG9EoyCampusSettings:()=>({canChange:()=>true,load(){eoyLoads++;}}),setupG9SettingsNavigation:()=>({activate:key=>root.activePanel=key}),showSuccess:message=>alerts.push(message),
        enhanceSubjectTemporal:(value,{onChange})=>{
            const section=sections.find(section=>section.date===value),input=new Element(),toggle=new Element();input.id='date-'+section.dataset.dateSection;
            const picker={input,element:{querySelector:()=>toggle},close(){},valid:()=>input.validity.valid,setValue:date=>{value.value=date;input.value=date;onChange();}};
            section.picker=picker;return picker;
        },
        FileReader:class{constructor(){readers.push(this);}readAsDataURL(selected){this.selected=selected;}},
        fetch:async(url,options)=>{
            const parsed=new URL(url,'http://local.test'),input=options.method==='POST'?JSON.parse(options.body):Object.fromEntries(parsed.searchParams);calls.push({url,...options,input});
            if(parsed.pathname.endsWith('/dates'))return {ok:true,json:async()=>({version,can_manage:canManage,location:'Year Province',dates:defaultDates(),report_centers:input.academic_year_id==='2'?[{exam_center:'Year Two Center'}]:[{exam_center:'Center A'},{exam_center:'Center B'}]})};
            if(failCenter||options.method==='POST'&&saveFailure)return {ok:false,json:async()=>({message:'Settings changed in another window.'})};
            const key=input.academic_year_id+':'+input.exam_center;
            if(options.method==='POST'){
                const old=store.get(key)||{};store.set(key,{...old,...input,photo_signature:Object.hasOwn(input,'photo_signature')?input.photo_signature:old.photo_signature});version++;
            }
            const saved=store.get(key)||{},dates=defaultDates(),overrides=[];
            for(const [key,date] of Object.entries(saved.dates||{}))if(!date.inherit){dates[key]={date:date.date,solar:date.solar,lunar:date.lunar};overrides.push(key);}
            return {ok:true,json:async()=>({version,can_manage:canManage,exam_center:input.exam_center,location:saved.location||'Province '+input.exam_center,department:saved.department||'',signer:saved.signer||'Signer Title',signer_name:saved.signer_name||'',photo_signature:saved.photo_signature||null,dates,date_overrides:overrides,defaults_dates:defaultDates()})};
        },
    };
    vm.createContext(context);const source=fs.readFileSync('resources/js/g9Settings.js','utf8').replace(/^import .*;\r?\n/gm,'').replace(/    load\(true\);\s*}\s*$/,'    globalThis.initialLoad=load(true);\n}');vm.runInContext(source,context);
    return {context,year,center,location,form,save,message,sections,fields,preview,dropzone,file,remove,uploadTitle,title,scope,calls,alerts,readers,combos,store,get eoyLoads(){return eoyLoads;}};
}
test('loads centers for the academic year and defaults to the first center with inherited dates locked',async()=>{
    const state=page();await state.context.initialLoad;
    assert.equal(state.center.value,'Center A');assert.equal(state.center.options.length,3);assert.equal(state.location.value,'Province Center A');
    assert.match(state.calls[0].url,/\/dates\?academic_year_id=1/);assert.match(state.calls[1].url,/exam_center=Center\+A/);
    assert.ok(state.sections.every(section=>section.inherit.checked&&section.picker.input.disabled));assert.equal(state.save.disabled,false);assert.equal(state.sections[0].solar.value,'Year listDate');
});
test('unchecking inheritance enables one date and save targets only the selected year and center',async()=>{
    const state=page();await state.context.initialLoad;const card=state.sections[3];card.inherit.checked=false;await card.inherit.emit('change');
    assert.equal(card.picker.input.disabled,false);assert.equal(state.sections[0].picker.input.disabled,true);card.picker.setValue('2026-08-02');
    await state.form.emit('submit',{preventDefault(){}});const request=state.calls.find(call=>call.method==='POST');
    assert.match(request.url,/\/center$/);assert.equal(request.input.exam_center,'Center A');assert.equal(request.input.academic_year_id,'1');
    assert.deepEqual(request.input.dates.cardDate,{inherit:false,date:'2026-08-02',solar:'Province Center A 2026-08-02',lunar:'Lunar 2026-08-02'});
    assert.equal(request.input.dates.listDate.inherit,true);assert.equal(Object.hasOwn(request.input,'photo_signature'),false);
    assert.equal(state.message.textContent,'Exam-center settings saved.');
});

test('Edit Date unlocks candidate list and result dates with clear inheritance messages',async()=>{
    const state=page();await state.context.initialLoad;
    for(const index of [0,1]){
        const card=state.sections[index];assert.equal(card.edit.hidden,false);assert.match(card.state.textContent,/academic-year default/);
        assert.equal(card.picker.input['aria-describedby'],card.state.id);assert.equal(card.edit['aria-controls'],card.picker.input.id);
        await card.edit.emit('click');
        assert.equal(card.inherit.checked,false);assert.equal(card.picker.input.disabled,false);assert.equal(card.picker.input.focused,true);
        assert.equal(card.edit.hidden,true);assert.equal(card.date.value,'2026-07-01');assert.match(card.state.textContent,/Custom date/);
    }
    assert.equal(state.sections[2].picker.input.disabled,true);assert.equal(state.message.textContent,'Unsaved settings.');
    await state.form.emit('submit',{preventDefault(){}});
    const payload=state.calls.find(call=>call.method==='POST').input;
    assert.equal(payload.dates.listDate.inherit,false);assert.equal(payload.dates.resultDate.inherit,false);assert.equal(payload.dates.certDate.inherit,true);
});

test('Edit Date never bypasses read-only permissions or a failed load',async()=>{
    const state=page({canManage:false});await state.context.initialLoad;const card=state.sections[0];
    assert.equal(card.edit.hidden,true);assert.match(card.state.textContent,/Read only/);await card.edit.emit('click');
    assert.equal(card.inherit.checked,true);assert.equal(card.picker.input.disabled,true);
    const failed=page({failCenter:true});await failed.context.initialLoad;await failed.sections[0].edit.emit('click');
    assert.equal(failed.sections[0].edit.hidden,true);assert.equal(failed.sections[0].picker.input.disabled,true);assert.match(failed.sections[0].state.textContent,/unavailable/);
});
test('restoring a default resets the date text and marks inheritance in the request',async()=>{
    const state=page();await state.context.initialLoad;const card=state.sections[3];card.inherit.checked=false;await card.inherit.emit('change');card.picker.setValue('2026-08-02');
    card.inherit.checked=true;await card.inherit.emit('change');assert.equal(card.date.value,'2026-07-01');assert.equal(card.solar.value,'Year cardDate');assert.equal(card.picker.input.disabled,true);
    await state.form.emit('submit',{preventDefault(){}});assert.equal(state.calls.find(call=>call.method==='POST').input.dates.cardDate.inherit,true);
});
test('year changes reload center choices and campus names; unsaved changes can cancel navigation',async()=>{
    const state=page();await state.context.initialLoad;state.location.value='Edited';await state.location.emit('input');state.context.confirm=()=>false;
    state.center.value='Center B';await state.center.emit('change');assert.equal(state.center.value,'Center A');
    state.year.value='2';await state.year.emit('change');assert.equal(state.year.value,'1');
    state.context.confirm=()=>true;state.year.value='2';await state.year.emit('change');await settle();assert.equal(state.center.value,'Year Two Center');assert.equal(state.eoyLoads,1);assert.equal(state.center.options.length,2);
});
test('academic-year defaults remain editable without writing any center settings',async()=>{
    const state=page();await state.context.initialLoad;state.center.value='';await state.center.emit('change');await settle();
    assert.equal(state.title.textContent,'Academic-Year Defaults');assert.ok(state.sections.every(section=>!section.picker.input.disabled&&section.inheritWrap.hidden));
    await state.form.emit('submit',{preventDefault(){}});const request=state.calls.find(call=>call.method==='POST');assert.match(request.url,/\/dates$/);assert.equal(Object.hasOwn(request.input,'exam_center'),false);assert.equal(Object.hasOwn(request.input.dates.cardDate,'inherit'),false);
});
test('signature uploads protect context during reading and explicit removal sends null',async()=>{
    const state=page();await state.context.initialLoad;state.file.files=[{name:'Signature.png',type:'image/png',size:100}];await state.file.emit('change');assert.equal(state.save.disabled,true);
    state.center.value='Center B';await state.center.emit('change');assert.equal(state.center.value,'Center A');
    const reader=state.readers[0];reader.result='data:image/png;base64,TEST';reader.onload();assert.equal(state.preview.src,reader.result);assert.equal(state.save.disabled,false);
    await state.form.emit('submit',{preventDefault(){}});assert.equal(state.calls.find(call=>call.method==='POST').input.photo_signature,reader.result);
    await state.remove.emit('click');await state.form.emit('submit',{preventDefault(){}});assert.equal(state.calls.filter(call=>call.method==='POST').at(-1).input.photo_signature,null);
});
test('read-only users cannot save and load failures leave fields locked',async()=>{
    const readonly=page({canManage:false});await readonly.context.initialLoad;assert.equal(readonly.save.disabled,true);assert.equal(readonly.dropzone.disabled,true);await readonly.form.emit('submit',{preventDefault(){}});assert.equal(readonly.calls.filter(call=>call.method==='POST').length,0);
    const failed=page({failCenter:true});await failed.context.initialLoad;assert.equal(failed.save.disabled,true);assert.match(failed.message.textContent,/another window/);
});
test('invalid dates and stale save failures preserve the user edits',async()=>{
    const state=page({saveFailure:true});await state.context.initialLoad;const card=state.sections[3];card.inherit.checked=false;await card.inherit.emit('change');card.picker.input.validity.valid=false;
    await state.form.emit('submit',{preventDefault(){}});assert.equal(state.context.document.querySelector('[data-g9-settings]').activePanel,'dates');assert.equal(card.picker.input.reported,true);assert.equal(state.calls.filter(call=>call.method==='POST').length,0);
    card.picker.input.validity.valid=true;card.picker.setValue('2026-08-02');await state.form.emit('submit',{preventDefault(){}});assert.match(state.message.textContent,/another window/);assert.equal(card.date.value,'2026-08-02');assert.equal(state.save.disabled,false);
});
