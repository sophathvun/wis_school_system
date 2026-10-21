export const photoSizePresets = Object.freeze({
    '3x4': [3, 4], '3.5x4.5': [3.5, 4.5], '4x6': [4, 6], '4.5x4.5': [4.5, 4.5],
});

export function photoOutputSize(preset, customWidth = 3, customHeight = 4) {
    if (preset === '600x800') {
        return { widthCm: 600 / 300 * 2.54, heightCm: 800 / 300 * 2.54,
            width: 600, height: 800, label: '5.08 × 6.77 cm (600 × 800 px)' };
    }
    const dimensions = preset === 'custom' ? [Number(customWidth), Number(customHeight)] : photoSizePresets[preset];
    if (!dimensions || dimensions.some(value => !Number.isFinite(value) || value < 1 || value > 10 || Math.abs(value * 10 - Math.round(value * 10)) > .00001)) {
        throw new Error('Enter a width and height from 1 to 10 cm, in steps of 0.1 cm.');
    }
    const [widthCm, heightCm] = dimensions;
    return { widthCm, heightCm, width: Math.round(widthCm / 2.54 * 300), height: Math.round(heightCm / 2.54 * 300),
        label: `${widthCm} × ${heightCm} cm` };
}
