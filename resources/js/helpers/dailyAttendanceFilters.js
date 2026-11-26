import {dropdownTextMatches} from './dropdownSearch.js';

export const dailyAttendanceGroups = students => ['M', 'A', 'III'].filter(group => students.some(student => student.study_group === group));

export function matchesDailyAttendance(student, {group = '', studentId = '', search = ''} = {}) {
    return (!group || student.study_group === group)
        && (!studentId || String(student.id) === String(studentId))
        && dropdownTextMatches([student.name, student.name_kh, student.code].filter(Boolean).join(' '), search);
}
