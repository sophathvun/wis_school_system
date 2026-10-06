import test from 'node:test';
import assert from 'node:assert/strict';
import { formatStudentBirthplace } from '../../resources/js/helpers/student-profile.js';

const provinceStudent = {
    birth_village: { village_name_kh: 'ជោគជ័យ', village_name_en: 'Chok Chey' },
    birth_commune: { commune_name_kh: 'ស្ពឺ', commune_name_en: 'Speu' },
    birth_district: { district_name_kh: 'ចំការលើ', district_name_en: 'Chamkar Leu' },
    birth_province: { province_name_kh: 'កំពង់ចាម', province_name_en: 'Kampong Cham' },
};

test('formats all four birthplace levels in both languages for a province', () => {
    assert.equal(formatStudentBirthplace(provinceStudent), 'ភូមិជោគជ័យ ឃុំស្ពឺ ស្រុកចំការលើ ខេត្តកំពង់ចាម');
    assert.equal(formatStudentBirthplace(provinceStudent, 'en'), 'Phum Chok Chey, Khum Speu, Sruk Chamkar Leu, Kampong Cham');
});

test('uses Phnom Penh administrative labels and removes imported prefixes', () => {
    const student = {
        birth_village: { village_name_kh: 'ភូមិជោគជ័យ', village_name_en: 'Phum Chok Chey' },
        birth_commune: { commune_name_kh: 'ឃុំទឹកថ្លា', commune_name_en: 'SK. Tuek Thla' },
        birth_district: { district_name_kh: 'ស្រុកសែនសុខ', district_name_en: 'Khan Sen Sok' },
        birth_province: { province_name_kh: 'រាជធានីភ្នំពេញ', province_name_en: 'Phnom Penh Capital' },
    };
    assert.equal(formatStudentBirthplace(student), 'ភូមិជោគជ័យ សង្កាត់ទឹកថ្លា ខណ្ឌសែនសុខ រាជធានីភ្នំពេញ');
    assert.equal(formatStudentBirthplace(student, 'en'), 'Phum Chok Chey, Sangkat Tuek Thla, Khan Sen Sok, Phnom Penh');
});

test('does not duplicate province prefixes and skips only unsaved levels', () => {
    assert.equal(formatStudentBirthplace({ birth_province: { province_name_kh: 'ខេត្តកំពង់ចាម', province_name_en: 'Kampong Cham Province' } }), 'ខេត្តកំពង់ចាម');
    assert.equal(formatStudentBirthplace({ birth_province: { province_name_en: 'Kampong Cham Province' } }, 'en'), 'Kampong Cham');
    assert.equal(formatStudentBirthplace({}), '-');
    assert.equal(formatStudentBirthplace({ birth_province: null, birth_village: null }, 'en'), '-');
});

test('does not invent missing translations', () => {
    assert.equal(formatStudentBirthplace({ birth_province: { province_name_en: 'Phnom Penh' } }), '-');
});

test('uses permanent imported birthplace text when no lookup IDs were imported', () => {
    const student = { birth_village_kh: 'ភូមិជោគជ័យ', birth_commune_kh: 'សង្កាត់ទឹកថ្លា', birth_district_kh: 'ខណ្ឌសែនសុខ', birth_province_kh: 'រាជធានីភ្នំពេញ', birth_province_en: 'Phnom Penh' };
    assert.equal(formatStudentBirthplace(student), 'ភូមិជោគជ័យ សង្កាត់ទឹកថ្លា ខណ្ឌសែនសុខ រាជធានីភ្នំពេញ');
    assert.equal(formatStudentBirthplace(student, 'en'), 'Phnom Penh');
    student.birth_province = { province_name_kh: 'ខេត្តកំពង់ចាម', province_name_en: 'Kampong Cham' };
    assert.ok(formatStudentBirthplace(student).endsWith('ខេត្តកំពង់ចាម'));
});

test('does not turn the legacy country value into Capital Cambodia', () => {
    const student = { birth_province_en: 'Cambodia', birth_province_kh: 'ភ្នំពេញ' };
    assert.equal(formatStudentBirthplace(student, 'en'), 'Phnom Penh');
    assert.equal(formatStudentBirthplace(student), 'រាជធានីភ្នំពេញ');
});

test('keeps selected locations authoritative even when a lookup translation is missing', () => {
    const student = {
        birth_province: { province_name_en: 'Kampong Cham', province_name_kh: null },
        birth_province_en: 'Cambodia', birth_province_kh: 'ភ្នំពេញ',
    };
    assert.equal(formatStudentBirthplace(student, 'en'), 'Kampong Cham');
    assert.equal(formatStudentBirthplace(student), '-');
});
