import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import {test} from 'node:test';

class Element {
    constructor(){this.events={};this.children=[];this.value='';this.textContent='';this.dataset={};this.classes=new Set();this.classList={add:key=>this.classes.add(key),remove:key=>this.classes.delete(key),toggle:(key,on)=>on?this.classes.add(key):this.classes.delete(key)};}
    addEventListener(type,fn){this.events[type]=fn;}emit(type,event={}){return this.events[type]?.(event);}
    setAttribute(key,value){this[key]=value;}append(...items){this.children.push(...items);}replaceChildren(...items){this.children=items;}
    contains(target){return this.children.includes(target);}getClientRects(){return this.visible===false?[]:[{}];}focus(){this.focused=true;}click(){this.clicked=true;return this.emit('click');}
}
const photo=(name='001.jpg',size=2048)=>({name,size});
function setup(){
    const form=new Element(),input=new Element(),year=new Element(),document=new Element();year.disabled=false;
    const elements=Object.fromEntries(['drop','files','file-selection','file-summary','files-clear','upload-error','preview-button'].map(key=>[key,new Element()]));
    form.querySelector=selector=>selector==='input[type=file]'?input:selector==='#photoYear'?year:elements[selector.match(/data-g12-photo-([^\]]+)/)[1]];
    document.querySelector=()=>document.modal?{}:null;document.createElement=()=>new Element();
    let busy=false,changes=0;
    const context={document};vm.createContext(context);vm.runInContext(fs.readFileSync('resources/js/helpers/g12PhotoUpload.js','utf8').replace('export function','function'),context);
    const upload=context.setupG12PhotoUpload(form,{isBusy:()=>busy,onChange:()=>changes++});
    return {form,input,year,document,e:elements,upload,changes:()=>changes,busy:value=>{busy=value;upload.sync();},choose:files=>{input.files=files;input.emit('change');},paste:(files,extra={})=>{const event={clipboardData:{files},preventDefault(){this.prevented=true;},...extra};document.emit('paste',event);return event;}};
}

test('browse, drag and paste add files to the standard selected-file list',()=>{
    const s=setup();assert.equal(s.e['preview-button'].disabled,true);assert.equal(s.e['file-selection'].hidden,true);
    s.e.drop.click();assert.equal(s.input.clicked,true);s.choose([photo(),photo('002.png')]);
    const dataTransfer={files:[photo('photos.zip')],dropEffect:''};s.e.drop.emit('dragover',{dataTransfer,preventDefault(){}});assert.equal(dataTransfer.dropEffect,'copy');
    s.e.drop.emit('drop',{dataTransfer,preventDefault(){}});assert.equal(s.e.drop.classes.has('is-dragging'),false);
    assert.equal(s.paste([photo('003.webp')]).prevented,true);assert.equal(s.e.files.children.length,4);assert.equal(s.e['preview-button'].disabled,false);
    assert.equal(s.changes(),3);assert.equal(s.upload.files()[2].name,'photos.zip');assert.match(s.e['file-summary'].textContent,/4 files selected/);
});

test('individual remove and Clear All invalidate preview and keep the remaining filenames intact',()=>{
    const s=setup();s.choose([photo(),photo('002.jpg')]);s.e.files.children[0].children[2].click();
    assert.equal(s.upload.files().length,1);assert.equal(s.upload.files()[0].name,'002.jpg');assert.equal(s.changes(),2);assert.equal(s.e.drop.focused,true);
    s.e['files-clear'].click();assert.equal(s.upload.files().length,0);assert.equal(s.e['file-selection'].hidden,true);assert.equal(s.e['preview-button'].disabled,true);assert.equal(s.changes(),3);
});

for(const [files,message] of [
    [[photo('bad.pdf')],'Choose JPG'],[[photo('empty.jpg',0)],'empty'],[[photo('huge.jpg',20*1024*1024+1)],'20 MB'],
    [[photo('huge.zip',200*1024*1024+1)],'200 MB'],[Array.from({length:101},(_,i)=>photo(i+'.jpg')),'100 files'],
])test(`invalid batch is rejected while preserving previous files: ${message}`,()=>{
    const s=setup();s.choose([photo('kept.jpg')]);s.choose(files);
    assert.match(s.e['upload-error'].textContent,new RegExp(message));assert.equal(s.e['upload-error'].hidden,false);assert.equal(s.e.drop['aria-invalid'],'true');
    assert.equal(s.upload.files().length,1);assert.equal(s.upload.files()[0].name,'kept.jpg');assert.equal(s.changes(),1);
});

test('G12 photo and ZIP size boundaries are accepted without reducing the existing limits',()=>{
    const s=setup();s.choose([photo('image.jpg',20*1024*1024),photo('photos.zip',200*1024*1024)]);
    assert.equal(s.upload.files().length,2);assert.equal(s.e['upload-error'].hidden,true);
});

test('busy operations lock selection, paste, remove and clear until they finish',()=>{
    const s=setup();s.choose([photo()]);s.busy(true);assert.equal(s.e.drop.disabled,true);assert.equal(s.input.disabled,true);assert.equal(s.e['preview-button'].disabled,true);
    s.choose([photo('blocked.jpg')]);s.paste([photo('blocked.png')]);s.e.files.children[0].children[2].click();s.e['files-clear'].click();
    assert.equal(s.upload.files().length,1);assert.equal(s.changes(),1);s.busy(false);assert.equal(s.e.drop.disabled,false);assert.equal(s.e['preview-button'].disabled,false);
    s.year.disabled=true;s.upload.sync();assert.equal(s.e['preview-button'].disabled,true);
});

test('paste ignores active text controls, open modals and hidden upload areas',()=>{
    const s=setup();assert.equal(s.paste([photo()],{target:{closest:()=>({})}}).prevented,undefined);
    s.document.modal=true;assert.equal(s.paste([photo()]).prevented,undefined);s.document.modal=false;
    s.e.drop.visible=false;assert.equal(s.paste([photo()]).prevented,undefined);s.e.drop.visible=true;
    const event={clipboardData:{items:[{kind:'file',getAsFile:()=>photo('<pasted>.png')}]},preventDefault(){this.prevented=true;}};s.document.emit('paste',event);
    assert.equal(s.e.files.children[0].children[1].children[0].textContent,'<pasted>.png');assert.equal(event.prevented,true);
});

test('reset clears the file selection, errors and previous matching preview',()=>{
    const s=setup();s.choose([photo()]);s.choose([photo('bad.pdf')]);s.form.emit('reset');
    assert.equal(s.upload.files().length,0);assert.equal(s.e['upload-error'].hidden,true);assert.equal(s.e['file-selection'].hidden,true);assert.equal(s.changes(),2);
});
