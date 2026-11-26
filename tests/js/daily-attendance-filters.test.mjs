import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
import {dailyAttendanceGroups, matchesDailyAttendance} from '../../resources/js/helpers/dailyAttendanceFilters.js';

const students=[
    {id:1,name:'MORNING STUDENT',code:'00123',study_group:'M'},
    {id:2,name:'AFTERNOON STUDENT',name_kh:'ភ្នំពេញ',code:'00456',study_group:'A'},
    {id:3,name:'FULL DAY STUDENT',code:'00789',study_group:'III'},
    {id:4,name:'UNASSIGNED STUDENT',code:'00111',study_group:null},
];
test('daily group choices follow the loaded class roster and exclude duplicates and unassigned groups',()=>{
    assert.deepEqual(dailyAttendanceGroups(students),['M','A','III']);
    assert.deepEqual(dailyAttendanceGroups([students[1],students[1],students[3]]),['A']);
    assert.deepEqual(dailyAttendanceGroups([]),[]);
});
test('All groups includes every student and a chosen group only includes its own students',()=>{
    assert.equal(students.filter(student=>matchesDailyAttendance(student)).length,4);
    assert.deepEqual(students.filter(student=>matchesDailyAttendance(student,{group:'A'})).map(student=>student.id),[2]);
    assert.equal(matchesDailyAttendance(students[3],{group:'III'}),false);
});
test('daily group selection combines with student names IDs Khmer text and search',()=>{
    assert.equal(matchesDailyAttendance(students[1],{group:'A',studentId:'2',search:'ភ្នំពេញ'}),true);
    assert.equal(matchesDailyAttendance(students[1],{group:'M',studentId:'2'}),false);
    assert.equal(matchesDailyAttendance(students[0],{group:'M',search:'០០១២៣\u200b'}),true);
    assert.equal(matchesDailyAttendance(students[0],{studentId:'2'}),false);
});

test('Apply to Blank leaves students hidden by Group and existing statuses untouched',()=>{
    const source=readFileSync(new URL('../../resources/js/dailyAttendance.js',import.meta.url),'utf8');
    const handler=source.slice(source.indexOf("    get('[data-daily-present]').addEventListener"),source.indexOf("    get('[data-daily-form]').addEventListener('click',event=>"));
    const make=(hidden,value,disabled=false)=>{
        const row={hidden,querySelector:()=>({value:0})};
        return {value,disabled,closest:()=>row};
    };
    const inputs=[make(false,''),make(true,''),make(false,'EX'),make(false,'',true)];
    let click,changes=0;
    const context={locked:false,busy:false,dirty:false,blankOnly:false,changed:()=>changes++,get:selector=>selector==='[data-daily-present]'?{addEventListener:(_,callback)=>click=callback}:{querySelectorAll:()=>inputs}};
    vm.runInNewContext(handler,context);click();
    assert.deepEqual(inputs.map(input=>input.value),['P','','EX','']);
    assert.equal(changes,1);assert.equal(context.blankOnly,true);
});
