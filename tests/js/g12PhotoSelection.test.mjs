import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import {test} from 'node:test';

function setup({allowed=true,completed=false}={}){
    const all={},replaceAll={events:{},addEventListener(type,listener){this.events[type]=listener;}};
    const rows=Array.from({length:3},(_,index)=>{
        const selected={checked:index===0,disabled:completed&&index===2},replace={checked:false,disabled:!allowed};
        return {selected,replace,querySelector:selector=>selector==='[data-photo-selected]'?selected:replace};
    });
    const context={photoRows:{querySelectorAll:()=>rows},photoConfirm:{},photoBatch:1,photoYear:'2025-2026',permissions:{'photos-replace':allowed},busy:false,
        get:selector=>({'[data-g12-photo-select-all]':all,'[data-g12-photo-replace-all]':replaceAll,'#photoYear':{value:'2025-2026'}}[selector])};
    vm.createContext(context);
    const source=fs.readFileSync('resources/js/g12NationalExams.js','utf8');
    vm.runInContext(source.slice(source.indexOf('    const pendingPhotoRows='),source.indexOf('    const resetPhotoPreview='))+
        source.slice(source.indexOf("    get('[data-g12-photo-replace-all]')?.addEventListener"),source.indexOf("    get('[data-g12-photos-form]')?.addEventListener('submit'"))+
        '\nglobalThis.sync=updatePhotoSelection;',context);
    context.sync();
    return {rows,all,replaceAll,context,toggle(value){replaceAll.checked=value;replaceAll.events.change({target:replaceAll});}};
}

test('Replace All checks and clears pending replacements without selecting extra uploads',()=>{
    const s=setup();s.toggle(true);
    assert.ok(s.rows.every(row=>row.replace.checked));assert.equal(s.replaceAll.checked,true);assert.equal(s.replaceAll.indeterminate,false);
    assert.equal(s.rows.filter(row=>row.selected.checked).length,1);assert.equal(s.context.photoConfirm.textContent,'Upload Selected Photos (1)');
    s.toggle(false);assert.ok(s.rows.every(row=>!row.replace.checked));assert.equal(s.replaceAll.checked,false);
});

test('individual replacements show the mixed state and become checked when all are checked',()=>{
    const s=setup();s.rows[0].replace.checked=true;s.context.sync();
    assert.equal(s.replaceAll.checked,false);assert.equal(s.replaceAll.indeterminate,true);
    s.rows.forEach(row=>row.replace.checked=true);s.context.sync();assert.equal(s.replaceAll.checked,true);assert.equal(s.replaceAll.indeterminate,false);
});

test('completed photos and disabled replacements are skipped; no pending rows disables Replace All',()=>{
    const s=setup({completed:true});s.rows[1].replace.disabled=true;s.toggle(true);
    assert.equal(s.rows[0].replace.checked,true);assert.equal(s.rows[1].replace.checked,false);assert.equal(s.rows[2].replace.checked,false);
    assert.equal(s.replaceAll.checked,true);
    s.rows.forEach(row=>row.selected.disabled=true);s.context.sync();assert.equal(s.replaceAll.disabled,true);assert.equal(s.replaceAll.checked,false);assert.equal(s.replaceAll.indeterminate,false);
});

test('Replace All cannot bypass missing replacement permission',()=>{
    const s=setup({allowed:false});assert.equal(s.replaceAll.disabled,true);s.toggle(true);
    assert.ok(s.rows.every(row=>!row.replace.checked));assert.equal(s.replaceAll.checked,false);
});

test('Replace All does not change replacements during an upload',()=>{
    const s=setup();s.context.busy=true;s.toggle(true);
    assert.ok(s.rows.every(row=>!row.replace.checked));assert.equal(s.replaceAll.checked,false);
});
