import test from 'node:test';
import assert from 'node:assert/strict';
import {dailyAttendanceDateReason} from '../../resources/js/helpers/dailyAttendanceDate.js';

test('weekends follow configured school weekdays',()=>{
    const calendar={weekdays:[0,1,2,3,4,5,6]};
    assert.equal(dailyAttendanceDateReason('2026-11-07',calendar),null);
    assert.equal(dailyAttendanceDateReason('2026-11-08',calendar),null);
    assert.equal(dailyAttendanceDateReason('2026-11-07',{}),'Saturday');
    assert.equal(dailyAttendanceDateReason('2026-11-08',{}),'Sunday');
    assert.equal(dailyAttendanceDateReason('2026-11-06',calendar),null);
});
test('uses the selected campus calendar holidays and their descriptions',()=>{
    const calendar={holidays:['2026-11-09'],holiday_descriptions:{'2026-11-09':'Independence Day'}};
    assert.equal(dailyAttendanceDateReason('2026-11-09',calendar),'Public holiday: Independence Day');
    assert.equal(dailyAttendanceDateReason('2026-11-09',{}),null);
    assert.equal(dailyAttendanceDateReason('2026-11-09',{holidays:['2026-11-09']}),'Public holiday');
});
test('respects non-school weekdays and leaves empty dates alone',()=>{
    assert.equal(dailyAttendanceDateReason('2026-11-09',{weekdays:[2,3,4,5]}),'Non-school day');
    assert.equal(dailyAttendanceDateReason('',{}),null);
});
