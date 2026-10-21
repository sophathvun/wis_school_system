import test from 'node:test';
import assert from 'node:assert/strict';
import { naturalDefaults, photoPresets, applyNaturalTone, smoothPortrait } from '../../resources/js/helpers/photoNaturalAdjustments.js';

const tone = (rgb, values) => {
    const pixels = new Uint8ClampedArray([...rgb, 255]);
    applyNaturalTone(pixels, { ...naturalDefaults, ...values });
    return [...pixels].slice(0, 3);
};

test('neutral processing preserves all pixels and transparency exactly', () => {
    const pixels = new Uint8ClampedArray([0, 0, 0, 255, 210, 140, 100, 128, 255, 255, 255, 255]);
    const before = [...pixels];
    applyNaturalTone(pixels, photoPresets.original);
    smoothPortrait(pixels, 3, 1, 0);
    assert.deepEqual([...pixels], before);
});

test('shadows lift dark tones while highlights recover bright tones independently', () => {
    const dark = tone([35, 35, 35], { shadows: 60 }), light = tone([220, 220, 220], { shadows: 60 });
    assert.ok(dark[0] > 35 && dark[0] - 35 > light[0] - 220);
    const recovered = tone([220, 220, 220], { highlights: -60 });
    const preserved = tone([35, 35, 35], { highlights: -60 });
    assert.ok(recovered[0] < 220);
    assert.deepEqual(preserved, [35, 35, 35]);
});

test('white balance corrects cool/warm and green/magenta without tinting neutral endpoints', () => {
    const warm = tone([130, 130, 130], { warmth: 60 });
    const cool = tone([130, 130, 130], { warmth: -60 });
    const magenta = tone([130, 130, 130], { tint: 60 });
    assert.ok(warm[0] > warm[2] && cool[2] > cool[0]);
    assert.ok(magenta[0] > magenta[1] && magenta[2] > magenta[1]);
    assert.deepEqual(tone([0, 0, 0], { warmth: 100, tint: 100 }), [0, 0, 0]);
    assert.deepEqual(tone([255, 255, 255], { warmth: 100, tint: 100 }), [255, 255, 255]);
});

test('vibrance boosts muted colors and leaves fully saturated colors unchanged', () => {
    const muted = tone([100, 125, 160], { vibrance: 80 });
    assert.ok(muted[2] - muted[0] > 60);
    assert.deepEqual(tone([0, 0, 255], { vibrance: 100 }), [0, 0, 255]);
    const pixels = new Uint8ClampedArray([180, 120, 90, 100, 30, 40, 50, 0]);
    applyNaturalTone(pixels, { warmth: 50, shadows: 30, vibrance: 40 });
    assert.equal(pixels[3], 100);
    assert.deepEqual([...pixels].slice(4), [30, 40, 50, 0]);
});

test('portrait smoothing softens small variations while retaining texture and strong edges', () => {
    const pixels = new Uint8ClampedArray(5 * 5 * 4);
    for (let i = 0; i < pixels.length; i += 4) pixels.set([160, 130, 110, 255], i);
    const center = (2 * 5 + 2) * 4;
    pixels.set([170, 140, 120, 255], center);
    smoothPortrait(pixels, 5, 5, 60);
    assert.ok(pixels[center] < 170 && pixels[center] > 160, 'Keep some texture');
    pixels.set([0, 0, 0, 255], center);
    smoothPortrait(pixels, 5, 5, 60);
    assert.deepEqual([...pixels.slice(center, center + 4)], [0, 0, 0, 255], 'Strong detail is retained');
    pixels.set([160, 130, 110, 80], center);
    smoothPortrait(pixels, 5, 5, 60);
    assert.deepEqual([...pixels.slice(center, center + 4)], [160, 130, 110, 80], 'Cutout edge pixels stay intact');
});
