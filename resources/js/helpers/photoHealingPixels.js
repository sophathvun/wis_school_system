const clamp = (value, low, high) => Math.max(low, Math.min(high, value));

/** Blend a frozen blurred image through a soft brush mask without changing alpha. */
export function blurPixels(pixels, blurred, width, height, mask, strength = 1) {
    let changed = false;
    for (let row = 0; row < mask.height; row++) for (let col = 0; col < mask.width; col++) {
        const x = mask.x + col, y = mask.y + row;
        if (x < 0 || y < 0 || x >= width || y >= height) continue;
        const blend = mask.data[row * mask.width + col] * clamp(strength, 0, 1), i = (y * width + x) * 4;
        if (!blend || pixels[i + 3] < 250 || blurred[i + 3] < 250) continue;
        for (let c = 0; c < 3; c++) {
            const value = Math.round(pixels[i + c] + (blurred[i + c] - pixels[i + c]) * blend);
            changed ||= value !== pixels[i + c]; pixels[i + c] = value;
        }
    }
    return changed;
}

/** Blend sampled texture with the destination's surrounding color. Alpha never changes. */
export function healPixels(pixels, donor, width, height, mask, offset, strength = 1) {
    const { x, y, width: mw, height: mh, data } = mask;
    let weight = 0; const correction = [0, 0, 0];
    for (let row = 0; row < mh; row++) for (let col = 0; col < mw; col++) {
        const alpha = data[row * mw + col];
        if (alpha <= 0 || alpha > .2) continue;
        const tx = x + col, ty = y + row, sx = tx + offset.x, sy = ty + offset.y;
        if (tx < 0 || ty < 0 || tx >= width || ty >= height || sx < 0 || sy < 0 || sx >= width || sy >= height) continue;
        const i = (ty * width + tx) * 4, j = (sy * width + sx) * 4;
        if (pixels[i + 3] < 250 || donor[j + 3] < 250) continue;
        // A dark blemish at the feather edge must not tint the entire repair.
        if (Math.max(...[0, 1, 2].map(c => Math.abs(pixels[i + c] - donor[j + c]))) > 60) continue;
        const w = 1 - alpha;
        for (let c = 0; c < 3; c++) correction[c] += (pixels[i + c] - donor[j + c]) * w;
        weight += w;
    }
    if (weight) for (let c = 0; c < 3; c++) correction[c] = clamp(correction[c] / weight, -40, 40);
    let changed = false;
    for (let row = 0; row < mh; row++) for (let col = 0; col < mw; col++) {
        const blend = data[row * mw + col] * clamp(strength, 0, 1);
        if (!blend) continue;
        const tx = x + col, ty = y + row, sx = tx + offset.x, sy = ty + offset.y;
        if (tx < 0 || ty < 0 || tx >= width || ty >= height || sx < 0 || sy < 0 || sx >= width || sy >= height) continue;
        const i = (ty * width + tx) * 4, j = (sy * width + sx) * 4;
        if (pixels[i + 3] < 250 || donor[j + 3] < 250) continue;
        for (let c = 0; c < 3; c++) {
            const value = Math.round(pixels[i + c] + (clamp(donor[j + c] + correction[c], 0, 255) - pixels[i + c]) * blend);
            changed ||= value !== pixels[i + c]; pixels[i + c] = value;
        }
    }
    return changed;
}

export function circularHealingMask(cx, cy, radius, softness = .7) {
    radius = clamp(radius, 2, 160);
    const x = Math.floor(cx - radius), y = Math.floor(cy - radius), width = Math.ceil(radius * 2 + 1), height = width;
    const data = new Float32Array(width * height), inner = Math.max(.05, 1 - softness);
    for (let row = 0; row < height; row++) for (let col = 0; col < width; col++) {
        const distance = Math.hypot(x + col - cx, y + row - cy) / radius;
        const t = clamp((1 - distance) / (1 - inner), 0, 1);
        data[row * width + col] = t * t * (3 - 2 * t);
    }
    return { x, y, width, height, data };
}

/** Pick a nearby donor by matching the ring around the mark; never sample the mark itself. */
export function findHealingDonor(pixels, width, height, cx, cy, radius) {
    let best = null, bestScore = Infinity;
    for (const distance of [2.5, 3.5, 5]) for (let direction = 0; direction < 16; direction++) {
        const angle = direction * Math.PI / 8;
        const dx = Math.round(Math.cos(angle) * radius * distance), dy = Math.round(Math.sin(angle) * radius * distance);
        if (cx + dx - radius < 0 || cy + dy - radius < 0 || cx + dx + radius >= width || cy + dy + radius >= height) continue;
        let score = 0, samples = 0;
        for (let n = 0; n < 24; n++) {
            const a = n * Math.PI / 12;
            const tx = Math.round(cx + Math.cos(a) * radius * .95), ty = Math.round(cy + Math.sin(a) * radius * .95);
            if (tx < 0 || ty < 0 || tx >= width || ty >= height) continue;
            const i = (ty * width + tx) * 4, j = ((ty + dy) * width + tx + dx) * 4;
            if (pixels[i + 3] < 250 || pixels[j + 3] < 250) continue;
            for (let c = 0; c < 3; c++) score += (pixels[i + c] - pixels[j + c]) ** 2;
            samples++;
        }
        if (samples >= 12 && score / samples < bestScore) { bestScore = score / samples; best = { x: dx, y: dy }; }
    }
    return best;
}
