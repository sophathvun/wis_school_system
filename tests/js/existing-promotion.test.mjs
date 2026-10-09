import test from 'node:test';
import assert from 'node:assert/strict';
import { existingPromotedPlacement, minimumRequestedGradeOrder } from '../../resources/js/helpers/existingPromotion.js';

const student = { grade_order: 3, existing_promotions: [{ target: { academic_year_id: '2' }, grade_order: 4, grade: 'G4A' }] };

test('the requested grade must be higher than the existing placement in the selected year', () => {
    assert.equal(existingPromotedPlacement(student, 2).grade, 'G4A');
    assert.equal(minimumRequestedGradeOrder(student, '2'), 4);
});

test('an existing placement affects only its own target year and can be cleared safely', () => {
    assert.equal(existingPromotedPlacement(student, 1), null);
    assert.equal(minimumRequestedGradeOrder(student, '1'), 3);
    assert.equal(existingPromotedPlacement(null, 2), null);
    assert.equal(minimumRequestedGradeOrder(null, 2), 0);
});
