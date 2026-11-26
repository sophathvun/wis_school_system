import test from 'node:test';
import assert from 'node:assert/strict';
import {formatGepCambodiaPhone,gepPhoneValue} from '../../resources/js/helpers/gepEnrollmentPhone.js';

test('GEP home phone separates the Cambodia dial code and preserves optional blanks',()=>{
    assert.equal(formatGepCambodiaPhone('+85512345678'),'12 345 678');
    assert.equal(formatGepCambodiaPhone('012 345 678'),'12 345 678');
    assert.equal(gepPhoneValue('012 345 678'),'+85512345678');
    assert.equal(gepPhoneValue('85512345678'),'+85512345678');
    assert.equal(gepPhoneValue(''),'');
    assert.equal(gepPhoneValue('   '),'');
    assert.equal(gepPhoneValue('0'),'');
});

test('GEP phone fallback keeps international numbers and the selected dial code',()=>{
    assert.equal(gepPhoneValue('+66 812345678','66'),'+66812345678');
    assert.equal(gepPhoneValue('812345678','66'),'+66812345678');
    assert.equal(gepPhoneValue('0612345678','39'),'+390612345678');
});
