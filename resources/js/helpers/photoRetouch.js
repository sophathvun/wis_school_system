import { circularHealingMask, findHealingDonor, healPixels, blurPixels } from './photoHealingPixels.js';

// Retouching lives in source coordinates, so crop, zoom, resize and rotation keep it aligned.
export function createPhotoRetouch({ element, canvas, toSource, toView, changed, enabled }) {
    const get = id => element.querySelector('#' + id);
    const tool = get('photoRetouchTool');
    const note = get('photoRetouchStatus');
    const overlay = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    overlay.classList.add('photo-retouch-overlay'); overlay.setAttribute('aria-hidden', 'true');
    canvas.parentElement.append(overlay);
    let original, working, context, pixels, originalPixels, layer, layerContext, compositeCanvas, history = [], future = [], historyBytes = 0;
    let sourcePoint, picking = false, stroke, polygon = [], patchDrag, cursor, touched = false;
    const hasTool = () => tool.value !== 'move';
    const message = value => { note.textContent = value; };
    const sync = () => {
        const blocked = !enabled();
        element.querySelectorAll('[data-photo-retouch-tool]').forEach(button => {
            const active = button.dataset.photoRetouchTool === tool.value;
            button.classList.toggle('active', active);
            button.setAttribute('aria-pressed', String(active));
        });
        get('photoRetouchUndo').disabled = blocked || !history.length;
        get('photoRetouchRedo').disabled = blocked || !future.length;
        get('photoRetouchReset').disabled = blocked || !touched;
        get('photoRetouchSource').disabled = blocked || tool.value !== 'heal';
        get('photoRetouchSource').setAttribute('aria-pressed', String(picking));
        get('photoRetouchClearPatch').disabled = blocked || !polygon.length;
    };
    const hint = () => {
        const hints = { move: 'Choose a retouch tool to begin.', spot: 'Brush over a small mark. Nearby clean texture is selected automatically.',
            blur: 'Brush over the area to soften it. Adjust brush size, softness and strength; use short strokes for a natural result.',
            heal: picking ? 'Tap a clean area in the photo to set the source.' : 'Set Source (or Alt-click a clean area), then brush over the mark.',
            patch: polygon.length ? 'Drag from inside the outlined area onto a clean area, then release to blend.' : 'Draw a closed outline around the unwanted area.' };
        message(hints[tool.value]);
    };
    const region = mask => {
        const x = Math.max(0, mask.x), y = Math.max(0, mask.y);
        return { x, y, width: Math.max(0, Math.min(working.width, mask.x + mask.width) - x), height: Math.max(0, Math.min(working.height, mask.y + mask.height) - y) };
    };
    const union = (a, b) => a ? { x: Math.min(a.x, b.x), y: Math.min(a.y, b.y), width: Math.max(a.x + a.width, b.x + b.width) - Math.min(a.x, b.x), height: Math.max(a.y + a.height, b.y + b.height) - Math.min(a.y, b.y) } : b;
    const extract = (data, box) => {
        const out = new Uint8ClampedArray(box.width * box.height * 4);
        for (let row = 0; row < box.height; row++) out.set(data.subarray(((box.y + row) * working.width + box.x) * 4, ((box.y + row) * working.width + box.x + box.width) * 4), row * box.width * 4);
        return out;
    };
    const update = box => {
        if (!box?.width || !box.height) return;
        const image = new ImageData(extract(pixels, box), box.width, box.height);
        context.putImageData(image, box.x, box.y);
        // Only changed RGB is overlaid onto a cutout; its existing alpha stays intact.
        for (let row = 0; row < box.height; row++) for (let col = 0; col < box.width; col++) {
            const i = (row * box.width + col) * 4, j = ((box.y + row) * working.width + box.x + col) * 4;
            image.data[i + 3] = [0, 1, 2].some(c => pixels[j + c] !== originalPixels[j + c]) ? pixels[j + 3] : 0;
        }
        layerContext.putImageData(image, box.x, box.y);
        touched = history.length > 0 || Boolean(stroke?.box) || touched;
        changed();
    };
    const restore = (data, box) => {
        for (let row = 0; row < box.height; row++) pixels.set(data.subarray(row * box.width * 4, (row + 1) * box.width * 4), ((box.y + row) * working.width + box.x) * 4);
        update(box);
    };
    const commit = () => {
        if (!stroke?.box) { stroke = null; return; }
        const box = stroke.box, before = extract(stroke.before, box), after = extract(pixels, box);
        if (!before.every((v, i) => v === after[i])) {
            const entry = { box, before, after }; history.push(entry); historyBytes += before.byteLength + after.byteLength;
            // Bounded history, with the most recent operation always available.
            while (history.length > 20 || (historyBytes > 48 * 1024 * 1024 && history.length > 1)) {
                const removed = history.shift(); historyBytes -= removed.before.byteLength + removed.after.byteLength;
            }
            future = []; touched = true;
        }
        stroke = null; sync();
    };
    const begin = () => { stroke = { before: new Uint8ClampedArray(pixels), box: null, offset: null, last: null }; };
    const dab = p => {
        const radius = Math.max(2, Math.min(160, Number(get('photoRetouchSize').value) / p.factor / 2));
        const mask = circularHealingMask(p.x, p.y, radius, Number(get('photoRetouchSoftness').value) / 100);
        if (tool.value === 'blur') {
            if (!stroke.blurred) {
                // Freeze one Gaussian-blurred source per stroke so pointer events
                // blend toward the same image rather than repeatedly blurring it.
                const blurred = document.createElement('canvas'); blurred.width = working.width; blurred.height = working.height;
                const target = blurred.getContext('2d', { willReadFrequently: true });
                target.filter = `blur(${Math.max(1, Math.min(16, radius * .3))}px)`;
                target.drawImage(working, 0, 0);
                stroke.blurred = target.getImageData(0, 0, working.width, working.height).data;
            }
            if (blurPixels(pixels, stroke.blurred, working.width, working.height, mask, Number(get('photoRetouchStrength').value) / 100)) {
                const box = region(mask); stroke.box = union(stroke.box, box); update(box);
            }
            return;
        }
        const offset = tool.value === 'spot' ? findHealingDonor(stroke.before, working.width, working.height, p.x, p.y, radius) : stroke.offset;
        if (!offset) { message('No clean source fits here. Use a smaller brush or choose Healing Brush and Set Source.'); return; }
        if (healPixels(pixels, stroke.before, working.width, working.height, mask, offset, Number(get('photoRetouchStrength').value) / 100)) {
            const box = region(mask); stroke.box = union(stroke.box, box); update(box);
        }
    };
    const paint = p => {
        const last = stroke.last || p, step = Math.max(2, Number(get('photoRetouchSize').value) / p.factor / 4);
        const count = Math.min(100, Math.max(1, Math.ceil(Math.hypot(p.x - last.x, p.y - last.y) / step)));
        for (let n = 1; n <= count; n++) dab({ x: last.x + (p.x - last.x) * n / count, y: last.y + (p.y - last.y) * n / count, factor: p.factor });
        stroke.last = p;
    };
    const path = points => points.map((p, i) => { const v = toView(p); return `${i ? 'L' : 'M'}${v.x.toFixed(2)},${v.y.toFixed(2)}`; }).join(' ') + ' Z';
    const guides = () => {
        overlay.setAttribute('viewBox', `0 0 ${canvas.width} ${canvas.height}`);
        if (!hasTool() || !enabled()) { overlay.replaceChildren(); return; }
        let markup = '';
        if (sourcePoint && tool.value === 'heal') {
            const p = toView(sourcePoint);
            markup += `<path d="M${p.x - 8},${p.y}h16 M${p.x},${p.y - 8}v16" stroke="#16a34a" stroke-width="2" fill="none"/>`;
        }
        if (polygon.length) {
            markup += `<path d="${path(polygon)}" fill="rgba(56,189,248,.12)" stroke="#0284c7" stroke-width="2" stroke-dasharray="5 3"/>`;
            if (patchDrag) markup += `<path d="${path(polygon.map(p => ({ x: p.x + patchDrag.dx, y: p.y + patchDrag.dy })))}" fill="none" stroke="#16a34a" stroke-width="2" stroke-dasharray="5 3"/>`;
        }
        if (cursor && ['spot', 'heal', 'blur'].includes(tool.value)) {
            const p = toView(cursor), r = Number(get('photoRetouchSize').value) / 2;
            markup += `<circle cx="${p.x}" cy="${p.y}" r="${r}" stroke="white" stroke-width="2" fill="none"/><circle cx="${p.x}" cy="${p.y}" r="${r + 1}" stroke="#334155" stroke-width="1" fill="none"/>`;
        }
        overlay.innerHTML = markup;
    };
    const inside = p => {
        let result = false;
        for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
            const a = polygon[i], b = polygon[j];
            if ((a.y > p.y) !== (b.y > p.y) && p.x < (b.x - a.x) * (p.y - a.y) / (b.y - a.y) + a.x) result = !result;
        }
        return result;
    };
    const applyPatch = () => {
        const offset = { x: Math.round(patchDrag.dx), y: Math.round(patchDrag.dy) };
        if (Math.hypot(offset.x, offset.y) < 2) return;
        const xs = polygon.map(p => p.x), ys = polygon.map(p => p.y);
        const x = Math.max(0, Math.floor(Math.min(...xs))), y = Math.max(0, Math.floor(Math.min(...ys)));
        const width = Math.min(working.width, Math.ceil(Math.max(...xs))) - x + 1, height = Math.min(working.height, Math.ceil(Math.max(...ys))) - y + 1;
        if (width < 3 || height < 3 || width * height > 500000) { message('Choose a smaller patch (up to 500,000 source pixels).'); return; }
        if (x + offset.x < 0 || y + offset.y < 0 || x + width + offset.x > working.width || y + height + offset.y > working.height) { message('Keep the source patch fully inside the photo. Drag to another clean area.'); return; }
        const maskCanvas = document.createElement('canvas'); maskCanvas.width = width; maskCanvas.height = height;
        const target = maskCanvas.getContext('2d'); target.beginPath(); polygon.forEach((p, i) => i ? target.lineTo(p.x - x, p.y - y) : target.moveTo(p.x - x, p.y - y)); target.closePath(); target.fillStyle = '#fff'; target.fill();
        const soft = document.createElement('canvas'); soft.width = width; soft.height = height;
        const sc = soft.getContext('2d'); sc.filter = `blur(${Math.max(1, Math.min(width, height) * Number(get('photoRetouchSoftness').value) / 1200)}px)`; sc.drawImage(maskCanvas, 0, 0);
        const alpha = sc.getImageData(0, 0, width, height).data, data = new Float32Array(width * height);
        for (let i = 0; i < data.length; i++) data[i] = alpha[i * 4 + 3] / 255;
        begin(); const mask = { x, y, width, height, data };
        if (healPixels(pixels, stroke.before, working.width, working.height, mask, offset, Number(get('photoRetouchStrength').value) / 100)) { stroke.box = region(mask); update(stroke.box); }
        commit(); polygon = []; hint();
    };
    const reset = image => {
        original = image; working = context = pixels = originalPixels = layer = layerContext = compositeCanvas = null;
        history = []; future = []; historyBytes = 0; stroke = null; sourcePoint = null; polygon = []; patchDrag = null; cursor = null; picking = touched = false;
        tool.value = 'move'; sync(); hint(); guides();
    };
    const ensure = () => {
        if (working || !original) return;
        working = document.createElement('canvas'); working.width = original.width; working.height = original.height;
        context = working.getContext('2d', { willReadFrequently: true }); context.drawImage(original, 0, 0);
        pixels = context.getImageData(0, 0, working.width, working.height).data; originalPixels = new Uint8ClampedArray(pixels);
        layer = document.createElement('canvas'); layer.width = working.width; layer.height = working.height; layerContext = layer.getContext('2d');
    };
    element.querySelectorAll('[data-photo-retouch-tool]').forEach(button => button.addEventListener('click', () => {
        tool.value = button.dataset.photoRetouchTool;
        tool.dispatchEvent(new Event('change'));
    }));
    tool.addEventListener('change', () => { if (stroke?.box) restore(extract(stroke.before, stroke.box), stroke.box); stroke = null; polygon = []; patchDrag = null; picking = false; hint(); sync(); guides(); });
    get('photoRetouchSource').addEventListener('click', () => { picking = !picking; hint(); sync(); });
    get('photoRetouchClearPatch').addEventListener('click', () => { polygon = []; patchDrag = null; hint(); sync(); guides(); });
    get('photoRetouchUndo').addEventListener('click', () => {
        const entry = history.pop(); if (!entry) return; historyBytes -= entry.before.byteLength + entry.after.byteLength;
        restore(entry.before, entry.box); future.push(entry); polygon = []; sync(); guides(); message('Last retouch undone.');
    });
    get('photoRetouchRedo').addEventListener('click', () => {
        const entry = future.pop(); if (!entry) return; restore(entry.after, entry.box); history.push(entry); historyBytes += entry.before.byteLength + entry.after.byteLength;
        sync(); message('Retouch restored.');
    });
    get('photoRetouchReset').addEventListener('click', () => { reset(original); changed(); message('Retouching reset. Crop, background and adjustments are unchanged.'); });
    for (const key of ['Size', 'Softness', 'Strength']) get('photoRetouch' + key).addEventListener('input', event => { get('photoRetouch' + key + 'Value').textContent = event.target.value + (key === 'Size' ? ' px' : '%'); guides(); });
    sync();
    return {
        active: hasTool, reset,
        image: () => working || original,
        composite(target) {
            if (!layer || !touched || !target) return target;
            if (!compositeCanvas) { compositeCanvas = document.createElement('canvas'); compositeCanvas.width = target.width; compositeCanvas.height = target.height; }
            const c = compositeCanvas.getContext('2d'); c.clearRect(0, 0, target.width, target.height); c.globalCompositeOperation = 'source-over';
            c.drawImage(target, 0, 0); c.globalCompositeOperation = 'source-atop'; c.drawImage(layer, 0, 0); return compositeCanvas;
        },
        refresh() { sync(); guides(); },
        down(event, point) {
            if (!hasTool()) return false;
            if (!enabled() || !original) return true;
            ensure(); const p = toSource(point); cursor = p;
            if (p.x < 0 || p.y < 0 || p.x >= working.width || p.y >= working.height) return true;
            if (tool.value === 'heal' && (picking || event.altKey)) { sourcePoint = p; picking = false; message('Source set. Brush over the mark.'); sync(); guides(); return true; }
            if (tool.value === 'patch') {
                if (polygon.length >= 3 && inside(p)) patchDrag = { start: p, dx: 0, dy: 0 };
                else { polygon = [p]; stroke = { outline: true }; }
            } else {
                if (tool.value === 'heal' && !sourcePoint) { message('Tap Set Source, then tap a clean area first.'); return true; }
                begin(); if (sourcePoint) stroke.offset = { x: Math.round(sourcePoint.x - p.x), y: Math.round(sourcePoint.y - p.y) }; paint(p);
            }
            guides(); sync(); return true;
        },
        move(point) {
            if (!hasTool() || !enabled()) return false;
            const p = toSource(point); cursor = p;
            if (patchDrag) { patchDrag.dx = p.x - patchDrag.start.x; patchDrag.dy = p.y - patchDrag.start.y; }
            else if (stroke?.outline) { if (Math.hypot(p.x - polygon.at(-1).x, p.y - polygon.at(-1).y) > 2 && polygon.length < 1000) polygon.push(p); }
            else if (stroke) paint(p);
            guides(); return true;
        },
        up(cancelled = false) {
            if (patchDrag) { if (!cancelled) applyPatch(); patchDrag = null; }
            else if (stroke?.outline) { stroke = null; if (cancelled || polygon.length < 3) polygon = []; hint(); }
            else if (stroke) { if (cancelled && stroke.box) restore(extract(stroke.before, stroke.box), stroke.box); if (cancelled) stroke = null; else commit(); }
            sync(); guides();
        },
    };
}
