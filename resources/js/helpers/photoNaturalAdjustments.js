// Conservative, reversible portrait adjustments. No face reshaping or generated detail.
export const naturalDefaults = Object.freeze({ warmth: 0, tint: 0, shadows: 0, highlights: 0, vibrance: 0, portraitSmooth: 0 });
export const photoPresets = Object.freeze({
    original: { exposure: 0, brightness: 0, contrast: 0, saturation: 0, denoise: 0, sharpen: 0, ...naturalDefaults },
    natural: { exposure: .08, brightness: 0, contrast: 3, saturation: 0, denoise: 10, sharpen: 15, ...naturalDefaults, shadows: 12, highlights: -12, vibrance: 8 },
    studio: { exposure: .18, brightness: 0, contrast: 5, saturation: 0, denoise: 12, sharpen: 18, ...naturalDefaults, shadows: 18, highlights: -18, vibrance: 6 },
    warm: { exposure: .05, brightness: 0, contrast: 2, saturation: 0, denoise: 8, sharpen: 12, ...naturalDefaults, warmth: 10, shadows: 8, highlights: -10, vibrance: 5 },
});

const clamp = value => Math.max(0, Math.min(255, value));
export function hasNaturalTone(values) {
    return ['warmth', 'tint', 'shadows', 'highlights', 'vibrance'].some(key => values[key]);
}

export function applyNaturalTone(data, values) {
    if (!hasNaturalTone(values)) return;
    const warmth = (values.warmth || 0) / 100, tint = (values.tint || 0) / 100;
    const shadows = (values.shadows || 0) / 100, highlights = (values.highlights || 0) / 100;
    const vibrance = (values.vibrance || 0) / 100;
    for (let i = 0; i < data.length; i += 4) {
        if (!data[i + 3]) continue;
        let r = data[i], g = data[i + 1], b = data[i + 2];
        const light = (r * .2126 + g * .7152 + b * .0722) / 255;
        // Smooth luminance weights protect the opposite end of the tonal range.
        const shadowWeight = Math.max(0, 1 - light / .65) ** 2;
        const highlightWeight = Math.max(0, (light - .35) / .65) ** 2;
        const delta = 65 * (shadows * shadowWeight + highlights * highlightWeight);
        r += delta; g += delta; b += delta;
        // Fade white-balance shifts at black/white so neutral endpoints stay neutral.
        const midtone = 4 * light * (1 - light);
        r += (warmth * 22 + tint * 10) * midtone;
        g -= tint * 14 * midtone;
        b += (-warmth * 22 + tint * 10) * midtone;
        const maximum = Math.max(r, g, b), minimum = Math.min(r, g, b);
        const saturation = maximum > 0 ? (maximum - minimum) / maximum : 0;
        const luma = r * .2126 + g * .7152 + b * .0722;
        // Give muted colors a smaller boost near skin hues and already vivid colors.
        const skinProtection = r > g && g > b ? .5 : 1;
        const amount = vibrance * (1 - saturation) * .65 * skinProtection;
        data[i] = clamp(r + (r - luma) * amount);
        data[i + 1] = clamp(g + (g - luma) * amount);
        data[i + 2] = clamp(b + (b - luma) * amount);
    }
}

export function smoothPortrait(data, width, height, strength) {
    if (!strength) return;
    const original = new Uint8ClampedArray(data), blend = Math.min(1, Math.max(0, strength / 100)) * .45;
    // Small, edge-aware blending retains texture, eyes, hair and shirt outlines.
    for (let y = 1; y < height - 1; y++) for (let x = 1; x < width - 1; x++) {
        const i = (y * width + x) * 4;
        if (original[i + 3] !== 255) continue;
        let red = original[i] * 2, green = original[i + 1] * 2, blue = original[i + 2] * 2, weight = 2;
        for (let dy = -1; dy <= 1; dy++) for (let dx = -1; dx <= 1; dx++) {
            if (!dx && !dy) continue;
            const j = i + (dy * width + dx) * 4;
            if (original[j + 3] !== 255) continue;
            const difference = Math.max(Math.abs(original[i] - original[j]), Math.abs(original[i + 1] - original[j + 1]), Math.abs(original[i + 2] - original[j + 2]));
            if (difference > 22) continue;
            const w = (dx && dy ? .65 : 1) / (1 + difference * difference / 64);
            red += original[j] * w; green += original[j + 1] * w; blue += original[j + 2] * w; weight += w;
        }
        data[i] = original[i] + (red / weight - original[i]) * blend;
        data[i + 1] = original[i + 1] + (green / weight - original[i + 1]) * blend;
        data[i + 2] = original[i + 2] + (blue / weight - original[i + 2]) * blend;
    }
}
