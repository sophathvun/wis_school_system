import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import {test} from 'node:test';

// Run the page entry point with a small DOM and mocked HTTP responses, including
// the previous header markup that may still be open when assets are refreshed.
class Element {
    constructor(){this.children=[];this.events={};this.dataset={};this.value='';this.textContent='';this.isConnected=true;this.classList={add(){}};this.style={};}
    append(...items){this.children.push(...items);}
    prepend(...items){this.children.unshift(...items);}
    before(){}
    replaceChildren(...items){this.children=items;}
    setAttribute(key,value){this[key]=value;}
    getAttribute(key){return this[key];}
    addEventListener(key,fn){(this.events[key]??=[]).push(fn);}
    emit(key,event){for(const fn of this.events[key]??[])fn(event);}
    click(){this.emit('click');}
    remove(){}
    insertRow(){const row=new Element();this.append(row);return row;}
    insertCell(){return this.insertRow();}
    checkValidity(){return true;}
}
class Option {
    constructor(label,value){this.textContent=label;this.value=String(value);this.dataset={};}
}
class Select extends Element {
    constructor(name,label){super();this.name=name;this.id=name;this['aria-label']=label;this.options=[new Option('All','')];}
    replaceChildren(...options){this.options=options;this.value=options[0]?.value??'';}
    get selectedOptions(){return [this.options.find(option=>option.value===this.value)??this.options[0]];}
}
function page({legacyHeader=false,failOptions=false,reportPicker=false}={}){
    const root=new Element(),form=new Element(),rows=new Element(),error=new Element(),status=new Element(),toggle=new Element(),header=new Element(),label=new Element(),icon=new Element();
    const template=fs.readFileSync('resources/views/g9-national-exams/eoy-average.blade.php','utf8');
    if(/<button[^>]*data-eoy-edit\b[^>]*data-action-normalized="true"/.test(template))header.dataset.actionNormalized='true';
    header.querySelector=selector=>legacyHeader?null:selector==='i'?icon:label;
    toggle.querySelector=()=>new Element();
    form.elements=Object.fromEntries([['academic_year','Academic Year'],['campus','Campus'],['grade','Grade'],['student','Student Name']].map(([name,label])=>[name,new Select(name,label)]));
    form.elements.academic_year.options.push(new Option('2025-2026','1'));
    form.querySelectorAll=()=>Object.values(form.elements);
    const printButton=new Element(),pdfButton=new Element();printButton.dataset={eoyReport:'card',format:'print'};pdfButton.dataset={eoyReport:'cover',format:'pdf'};
    root.querySelector=selector=>({'form':form,'[data-eoy-rows]':rows,'[data-eoy-error]':error,'[data-eoy-status]':status,'[data-eoy-filter-toggle]':toggle,'[data-eoy-edit]':header}[selector]);
    root.querySelectorAll=()=>reportPicker?[printButton,pdfButton]:[];
    root.dataset.base='/g9-national-exams/eoy-average';
    const pagination=new Element(),perPage=new Element();perPage.value='15';
    const urls=[],rendered=[],alerts=[],warnings=[],opened=[],downloads=[];
    const context={console,URLSearchParams,URL:{createObjectURL:()=> 'blob:eoy-pdf',revokeObjectURL(){}},Option,matchMedia:()=>({matches:false,addEventListener(){}}),window:{addEventListener(){},open:(...args)=>opened.push(args)},setTimeout,clearTimeout,confirm:()=>true,
        document:{body:{append:link=>downloads.push(link)},querySelector:selector=>selector==='[data-g9-eoy]'?root:null,createElement:()=>new Element(),createTextNode:text=>text,getElementById:id=>id==='g9-eoy-per-page'?perPage:pagination},
        FormData:class {constructor(form){this.entries=Object.values(form.elements).map(select=>[select.name,select.value]);}[Symbol.iterator](){return this.entries[Symbol.iterator]();}},
        setupG9PhotoViewer:()=>()=>{},showSuccess:(...message)=>alerts.push(message),showWarning:(...message)=>warnings.push(message),renderPagination:result=>rendered.push(result),
        setupStaffLocationSelect:(form,select)=>{
            const menu=new Element(),search=new Element(),combo=new Element();menu.querySelector=()=>search;combo.querySelector=()=>menu;select.nextElementSibling=combo;
            return {toggle:new Element(),sync(){},close(){}};
        },
        fetch:async url=>{
            urls.push(url);
            if(url.includes('/reports/'))return {ok:true,blob:async()=>({})};
            if(url.includes('/options?'))return {ok:!failOptions,json:async()=>failOptions?{message:'Options unavailable'}:{campus:[{id:1,label:'BCH'}],grade:['9A'],student:[{id:1,label:'001 | Candidate'}]}};
            return {ok:true,json:async()=>({data:[{id:1,version:1,student_code:'001',name_en:'Candidate',can_assign:true,semester_1:'10.00',semester_2:'20.00',saved_eoy:'30',scores_normalized:true}],from:1,total:1})};
        }
    };
    vm.createContext(context);
    const source=fs.readFileSync('resources/js/g9EoyAverage.js','utf8').replace(/^import .*;\r?\n/gm,'').replace(/    load\(\);\s*}\s*$/,'    globalThis.initialLoad=load();\n}');
    vm.runInContext(source,context);
    const normalize=fs.readFileSync('resources/js/app.js','utf8').split('    const normalizeActions = () =>')[1].split('    const normalizeSearches')[0];
    const normalizeActions=()=>vm.runInNewContext('const normalizeActions = () =>'+normalize+'\nnormalizeActions();',{document:{querySelectorAll:()=>[header]}});
    return {context,form,rows,error,header,label,icon,urls,rendered,alerts,normalizeActions,printButton,pdfButton,warnings,opened,downloads};
}
async function chooseYear(state){
    await state.context.initialLoad;
    state.form.elements.academic_year.value='1';
    state.form.emit('change',{target:state.form.elements.academic_year});
    await new Promise(resolve=>setImmediate(resolve));
}
for(const legacyHeader of [false,true])test(`selecting an academic year loads options and candidates (${legacyHeader?'previous':'current'} header markup)`,async()=>{
    const state=page({legacyHeader});await chooseYear(state);
    assert.equal(state.urls.length,2);
    assert.match(state.urls[0],/\/options\?academic_year=1/);
    assert.match(state.urls[1],/\/list\?academic_year=1/);
    assert.equal(state.error.textContent,'');
    assert.equal(state.form.elements.campus.options[1].textContent,'BCH');
    assert.equal(state.rows.children[0].children.length,8);
    assert.equal(state.rendered[0].total,1);
    assert.equal(state.header.hidden,false);
    assert.equal(state.rows.children[0].children[4].children[0].readOnly,true);
});
test('filter loading errors are visible instead of leaving the year selection unresponsive',async()=>{
    const state=page({failOptions:true});await chooseYear(state);
    assert.equal(state.error.textContent,'Options unavailable');
    assert.equal(state.rows.children[0].children[0].textContent,'Unable to load candidates.');
});
test('Edit changes to Lock while editing and returns to Edit after locking',async()=>{
    const state=page();await chooseYear(state);
    const score=state.rows.children[0].children[4].children[0];
    assert.equal(score.readOnly,true);
    state.header.emit('click');
    assert.equal(score.readOnly,false);
    assert.equal(state.label.textContent,'Lock');
    assert.equal(state.icon.className,'ti ti-lock');
    assert.equal(state.alerts[0][0],'Editing enabled');
    state.header.emit('click');
    await new Promise(resolve=>setImmediate(resolve));
    assert.equal(score.readOnly,true);
    assert.equal(state.label.textContent,'Edit');
    assert.equal(state.icon.className,'ti ti-pencil');
    assert.equal(state.alerts[1][0],'Editing finished');
});
test('shared action normalization preserves the header label before and after editing',async()=>{
    const state=page();
    state.header.textContent='Edit';
    state.normalizeActions();
    assert.equal(state.header.innerHTML,undefined);
    await chooseYear(state);
    state.normalizeActions();
    assert.equal(state.header.innerHTML,undefined);
    assert.equal(state.label.textContent,'Edit');
    state.header.emit('click');
    assert.equal(state.label.textContent,'Lock');
    state.header.emit('click');
    await new Promise(resolve=>setImmediate(resolve));
    state.normalizeActions();
    assert.equal(state.label.textContent,'Edit');
    assert.equal(state.header.innerHTML,undefined);
});
test('report dropdown items print immediately or download PDF using the selected filters',async()=>{
    const state=page({reportPicker:true});await state.context.initialLoad;
    state.printButton.emit('click');assert.equal(state.warnings[0][0],'Select an academic year');assert.equal(state.opened.length,0);
    await chooseYear(state);state.form.elements.campus.value='1';state.printButton.emit('click');
    assert.match(state.opened[0][0],/\/reports\/card\/print\?academic_year=1&campus=1/);
    state.pdfButton.emit('click');assert.equal(state.pdfButton.disabled,true);
    await new Promise(resolve=>setImmediate(resolve));
    assert.match(state.urls.at(-1),/\/reports\/cover\/pdf\?academic_year=1&campus=1/);
    assert.equal(state.downloads[0].download,'g9-eoy-cover.pdf');assert.equal(state.pdfButton.disabled,false);
});
