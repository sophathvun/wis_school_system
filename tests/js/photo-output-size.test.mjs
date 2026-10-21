import test from 'node:test';
import assert from 'node:assert/strict';
import { photoOutputSize } from '../../resources/js/helpers/photoOutputSize.js';

test('standard centimetre sizes convert to the correct 300 DPI pixel dimensions', () => {
    for (const [preset, expected] of [['3x4', [354, 472]], ['3.5x4.5', [413, 531]], ['4x6', [472, 709]], ['4.5x4.5', [531, 531]], ['600x800', [600, 800]]]) {
        const size = photoOutputSize(preset);
        assert.deepEqual([size.width, size.height], expected);
    }
});
test('custom sizes support portrait, square and landscape photos with bounded memory', () => {
    assert.deepEqual(photoOutputSize('custom', '5', '3'), { widthCm: 5, heightCm: 3, width: 591, height: 354, label: '5 × 3 cm' });
    assert.equal(photoOutputSize('custom', 10, 10).width, 1181);
    assert.equal(photoOutputSize('custom', 1, 10).height, 1181);
});
test('blank, invalid, oversized and overly precise custom sizes cannot be applied', () => {
    for (const value of ['', 0, -1, 10.1, NaN, Infinity, 'bad', 3.55]) assert.throws(() => photoOutputSize('custom', value, 4));
    assert.throws(() => photoOutputSize('unknown'));
});
