import test from 'node:test';
import assert from 'node:assert/strict';
import {renderDailyAttendanceRow} from '../../resources/js/dailyAttendanceRow.js';

test('mixed imported periods show each status and reason and cannot be replaced by a single daily choice',()=>{
    const escape=value=>String(value??'').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('"','&quot;');
    const student={id:1,name:'Test student',code:'00123',mark:{status:'EX',missed_sessions:2,absent_periods:[
        {id:1,name:'Period 1',starts_at:'08:00',ends_at:'08:50',attendance_status:'T',attendance_remarks:'Late <first> period'},
        {id:2,name:'Period 2',starts_at:'09:00',ends_at:'09:50',attendance_status:'EX',attendance_remarks:'Sick second period'},
    ]}};
    const html=renderDailyAttendanceRow(student,0,false,{P:'Present',EX:'Excused',UX:'Unexcused',T:'Tardy'},escape);
    assert.match(html,/data-period-records="true"/);
    assert.match(html,/data-daily-status[^>]+disabled/);
    assert.match(html,/<strong>T<\/strong> 08:00–08:50/);
    assert.match(html,/<strong>EX<\/strong> 09:00–09:50/);
    assert.match(html,/Late &lt;first> period/);
    assert.match(html,/Sick second period/);
});
