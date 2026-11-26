import test from 'node:test';
import assert from 'node:assert/strict';
import {bindDropdownSearch, dropdownOptionMatches, installDropdownSearchCompatibility} from '../../resources/js/helpers/dropdownSearch.js';

test('searches visible option labels when bilingual metadata is absent or incomplete', () => {
    assert.equal(dropdownOptionMatches({textContent:'2A',dataset:{}},'2a'),true);
    assert.equal(dropdownOptionMatches({textContent:'Campus BCH',dataset:{en:'BCH'}},'Campus'),true);
    assert.equal(dropdownOptionMatches({textContent:'BCH',dataset:{kh:'ភ្នំពេញ'}},'ភ្នំពេញ'),true);
    assert.equal(dropdownOptionMatches({textContent:'2B',dataset:{}},'2a'),false);
});

test('normalizes mobile pasted whitespace invisible separators and Khmer digits', () => {
    assert.equal(dropdownOptionMatches({textContent:'Grade 12A'},'  Grade\u00a0១២A\u200b '),true);
    assert.equal(dropdownOptionMatches({textContent:'២០២៦-២០២៧'},'2026'),true);
});

function setup() {
    const input=new EventTarget();input.value='';input.dataset={};input.setAttribute=()=>{};
    let renders=0;bindDropdownSearch(input,()=>renders++);
    return {input,count:()=>renders,event:(type,options={})=>{const event=new Event(type,{cancelable:true,bubbles:true});Object.assign(event,options);input.dispatchEvent(event);return event;}};
}
test('updates on typing paste search clear and keyboard fallback without duplicate fallback renders', () => {
    const page=setup();page.input.value='2A';page.event('input');assert.equal(page.count(),1);
    page.event('keyup');assert.equal(page.count(),1);
    page.input.value='2B';page.event('keyup');assert.equal(page.count(),2);
    page.input.value='';page.event('search');assert.equal(page.count(),3);
    page.input.value='BCH';page.event('change');assert.equal(page.count(),4);
    page.input.value='paste';page.event('input',{inputType:'insertFromPaste'});assert.equal(page.count(),5);
    assert.equal(page.event('keydown',{key:'Enter'}).defaultPrevented,true);
});
test('waits for Khmer IME completion and leaves composition confirmation alone', () => {
    const page=setup();page.event('compositionstart');page.input.value='ភ';
    page.event('input',{isComposing:true});page.event('keyup');assert.equal(page.count(),0);
    assert.equal(page.event('keydown',{key:'Enter',isComposing:true}).defaultPrevented,false);
    page.input.value='ភ្នំពេញ';page.event('compositionend');assert.equal(page.count(),1);
});
test('typing the same query after reopening still filters', () => {
    const page=setup();page.input.value='2A';page.event('input');
    page.input.value='';page.event('focus');page.input.value='2A';page.event('input');assert.equal(page.count(),2);
});

function legacySetup(inMenu=true) {
    const root=new EventTarget();installDropdownSearchCompatibility(root);
    const input=new EventTarget();Object.assign(input,{tagName:'INPUT',type:'search',value:'',dataset:{},className:'form-control',placeholder:'Search',setAttribute(){},closest:()=>inMenu?{}:null});
    let count=0;input.addEventListener('input',()=>count++);
    const fire=(type,options={})=>{const event=new Event(type,{bubbles:true,cancelable:true});Object.defineProperty(event,'target',{value:input});Object.assign(event,options);root.dispatchEvent(event);return event;};
    return {root,input,fire,count:()=>count};
}
test('legacy and dynamically created dropdowns receive browser event fallbacks exactly once', () => {
    const page=legacySetup();page.fire('focusin');page.input.value='BCH';page.fire('keyup');assert.equal(page.count(),1);
    page.fire('change');page.fire('search');assert.equal(page.count(),1);
    page.input.value='';page.fire('search');assert.equal(page.count(),2);
    assert.equal(page.input.dataset.dropdownSearchActive,'true');
    assert.equal(page.fire('keydown',{key:'Enter'}).defaultPrevented,true);
});
test('legacy Khmer input completes once and ordinary page searches are left alone', () => {
    const page=legacySetup();page.fire('focusin');page.fire('compositionstart');page.input.value='ភ្នំពេញ';
    page.fire('keyup',{isComposing:true});assert.equal(page.count(),0);
    page.fire('compositionend');assert.equal(page.count(),1);
    page.fire('keyup');assert.equal(page.count(),1);
    const outside=legacySetup(false);outside.fire('focusin');outside.input.value='BCH';outside.fire('keyup');assert.equal(outside.count(),0);
});
test('globally delegated fallbacks leave pickers with direct bindings alone', () => {
    const page=legacySetup();bindDropdownSearch(page.input,()=>{});
    page.fire('focusin');page.input.value='BCH';page.fire('keyup');assert.equal(page.count(),0);
});
