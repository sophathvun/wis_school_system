import test from 'node:test';
import assert from 'node:assert/strict';
import { selectEnrollmentAcademicYear } from '../../resources/js/helpers/enrollment-academic-year.js';

class Option {
    constructor(text, value) { this.text = text; this.value = value; }
}
globalThis.Option = Option;

const yearSelect = () => ({
    options: [new Option('Select Academic Year', ''), new Option('2025-2026', '5')],
    value: '',
    add(option) { this.options.push(option); },
});

test('editing a completed year keeps its saved year label and ID available for submission', () => {
    const select = yearSelect();
    const row = { academic_year_id: 2, academic_year: { academic_year: '2022-2023' } };
    selectEnrollmentAcademicYear(select, row);
    assert.equal(select.value, '2');
    assert.equal(select.options.find(option => option.value === select.value).text, '2022-2023');
    selectEnrollmentAcademicYear(select, row);
    assert.equal(select.options.filter(option => option.value === '2').length, 1);
});

test('active years reuse their existing option and create options are not modified', () => {
    const edit = yearSelect(), create = yearSelect();
    selectEnrollmentAcademicYear(edit, { academic_year_id: 5, academic_year: { academic_year: '2025-2026' } });
    assert.equal(edit.value, '5');
    assert.equal(edit.options.length, 2);
    selectEnrollmentAcademicYear(edit, { academic_year_id: 2, academic_year: { academic_year: '2022-2023' } });
    assert.deepEqual(create.options.map(option => option.value), ['', '5']);
});
