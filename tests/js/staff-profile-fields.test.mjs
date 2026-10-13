import test from 'node:test';
import assert from 'node:assert/strict';
import { toKhmerDigits,dateKhmer,nationalityNote,genderKhmer,maritalKhmer } from '../../resources/js/helpers/staffProfileFields.js';

test('staff house and street numbers convert digits while retaining letters',()=>{
    assert.equal(toKhmerDigits('#12A / 123'),'#១២A / ១២៣');
    assert.equal(toKhmerDigits(null),'');
});
test('staff bilingual date and classifications match canonical English values',()=>{
    assert.equal(dateKhmer('1990-10-12'),'១២ តុលា ១៩៩០');
    assert.equal(dateKhmer(''),'');
    assert.equal(genderKhmer('Female'),'ស្រី');
    assert.equal(maritalKhmer('married'),'រៀបការ');
    assert.equal(nationalityNote('Cambodian'),'Cambodian');
    assert.equal(nationalityNote('KHMER'),'Cambodian');
    assert.equal(nationalityNote('Japanese','ជប៉ុន'),'International');
    assert.equal(nationalityNote('','ខ្មែរ'),'Cambodian');
    assert.equal(nationalityNote(''),'');
});
