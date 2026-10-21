import { naturalDefaults, photoPresets, hasNaturalTone, applyNaturalTone, smoothPortrait } from './photoNaturalAdjustments.js';
import { photoOutputSize } from './photoOutputSize.js';

const MAX_SOURCE_BYTES = 10 * 1024 * 1024;
const canvasBlob = (canvas, type = "image/png", quality) => new Promise((resolve, reject) =>
    canvas.toBlob(blob => blob ? resolve(blob) : reject(new Error("The photo could not be prepared.")), type, quality));
const loadImage = blob => new Promise((resolve, reject) => {
    const url = URL.createObjectURL(blob), image = new Image();
    image.onload = () => { URL.revokeObjectURL(url); resolve(image); };
    image.onerror = () => { URL.revokeObjectURL(url); reject(new Error("This image cannot be read. Please choose a JPG, PNG, or WebP photo.")); };
    image.src = url;
});

// Both the viewer and the download use actual JPEG pixels, including legacy PNG/WebP photos.
export async function studentPhotoJpeg(url) {
    const source = new URL(url, window.location.href);
    if (source.origin !== window.location.origin) throw new Error("The student photo must be stored on this server.");
    const response = await fetch(source, { credentials: "same-origin" });
    if (!response.ok) throw new Error("The student photo could not be loaded.");
    const image = await loadImage(await response.blob());
    const canvas = document.createElement("canvas");
    canvas.width = image.naturalWidth; canvas.height = image.naturalHeight;
    const context = canvas.getContext("2d");
    context.fillStyle = "#fff"; context.fillRect(0, 0, canvas.width, canvas.height);
    context.drawImage(image, 0, 0);
    return canvasBlob(canvas, "image/jpeg", .95);
}

export function createPhotoEditor({ bootstrap, csrf, showError, showSuccess, editorId = "studentPhotoCropModal", elements = {}, saveMessage = "Save the enrollment to apply this photo.", onApply = () => {} }) {
    const get = id => document.getElementById(id);
    const element = get(editorId);
    if (!element) return { showPreview() {}, resetPreview() {} };
    const modal = bootstrap.Modal.getOrCreateInstance(element);
    const enrollment = elements.parent === undefined ? get("enrollmentModal") : elements.parent;
    const canvas = get("studentPhotoCropCanvas"), context = canvas.getContext("2d", { willReadFrequently: true });
    const input = elements.input || get("student_photo"), dropzone = elements.dropzone || get("studentPhotoDropzone"), preview = elements.preview || get("studentPhotoPreview");
    const previewContainer = elements.previewContainer || get("studentPhotoPreviewContainer");
    const editButton = elements.editButton || get("studentPhotoEdit");
    const fileSize = get("photoEditorFileSize");
    const previewSize = document.createElement("div");
    previewSize.className = "small text-secondary mt-2 d-none photo-edited-file-size";
    previewSize.setAttribute("role", "status");
    previewSize.dataset.editedPhotoSize = "1";
    (editButton || previewContainer || preview).insertAdjacentElement(editButton ? "beforebegin" : "afterend", previewSize);
    const error = get("studentPhotoEditorError"), status = get("studentPhotoEditorStatus");
    const zoom = get("studentPhotoZoom"), brush = get("studentPhotoBrush");
    const angleInput = get("studentPhotoAngle"), angleRange = get("studentPhotoAngleRange");
    const before = get("studentPhotoCompare"), use = get("studentPhotoCropUpload");
    const outputSizeSelect = get('photoEditorSize');
    let outputSize = outputSizeSelect ? photoOutputSize(outputSizeSelect.value) : null, outputSizeError = '';
    const scope = get("studentPhotoAdjustmentScope"), selectionBrush = get("studentPhotoSelectionBrush");
    const selectionShow = get("studentPhotoSelectionShow");
    const selectionPreview = document.createElement("canvas"), adjustedPreview = document.createElement("canvas");
    selectionPreview.width = adjustedPreview.width = canvas.width;
    selectionPreview.height = adjustedPreview.height = canvas.height;
    const selectionContext = selectionPreview.getContext("2d", { willReadFrequently: true });
    const adjustedContext = adjustedPreview.getContext("2d", { willReadFrequently: true });
    let source, cutout, acceptedFile = null, scale = 1, rotation = 0, fineRotation = 0, x = 0, y = 0;
    let selection, selectionVisible = true;
    let background = "original", comparing = false, pointer, smoothStroke, previewUrl, generation = 0, request, busy = false, returnToEnrollment = false;
    let adjustmentFrame = 0;
    let sizeTimer, sizeVersion = 0, sizeCache;
    const sizeLabel = data => {
        const label = data.photo_size ? `${data.photo_size.label} · ` : '';
        const webp = data.webp_available === false ? `${label}WebP exceeds 40 KB at this size. JPG available.`
            : `${label}WebP size: ${(data.bytes / 1024).toFixed(2)} KB · ${data.width} × ${data.height} px`;
        return Number.isInteger(data.jpg_bytes) ? `${webp}\nJPG size: ${(data.jpg_bytes / 1024).toFixed(2)} KB` : webp;
    };
    const settings = { ...photoPresets.original };
    const areaSettings = { ...settings };
    const currentSettings = () => scope.value === "selected" ? areaSettings : settings;
    const fail = message => { error.textContent = message; error.classList.remove("d-none"); showError("Photo editor", message); };
    const clearError = () => error.classList.add("d-none");
    const setBusy = value => {
        busy = value;
        element.setAttribute("aria-busy", String(value));
        element.querySelectorAll("button:not([data-bs-dismiss]), input, select").forEach(control => control.disabled = value);
        use.disabled = value || Boolean(outputSizeError);
        status.textContent = value ? "Processing your photo…" : `${element.dataset.photoFormat || "3:4 portrait"} · Saved as WebP, up to 40 KB`;
        if (!value && source) queueSize();
    };
    const adjustmentLabel = (key, value) => key === "exposure" ? `${value > 0 ? "+" : ""}${Number(value).toFixed(2)} EV` : String(value);
    const syncPresets = () => element.querySelectorAll('[data-photo-preset]').forEach(button => {
        const active = Object.entries(photoPresets[button.dataset.photoPreset]).every(([key, value]) => currentSettings()[key] === value);
        button.classList.toggle('active', active);
        button.setAttribute('aria-pressed', String(active));
    });
    const syncSettings = () => {
        Object.keys(settings).forEach(key => {
            const value = currentSettings()[key];
            get(`studentPhoto-${key}`).value = String(value);
            element.querySelector(`[data-photo-value="${key}"]`).textContent = adjustmentLabel(key, value);
        });
        syncPresets();
    };
    const resetSettings = () => {
        for (const key of Object.keys(settings)) settings[key] = areaSettings[key] = 0;
        syncSettings();
    };
    const syncPreviewStatus = () => {
        const action = scope.value === "selected" && selectionBrush.value !== "move"
            ? (selectionBrush.value === "erase" ? "Erase selection" : "Paint selection") : "Drag to reposition";
        get("studentPhotoCompareStatus").textContent = comparing ? "Original preview" : `Edited preview · ${action}`;
    };
    const syncSelectionTools = () => {
        const selected = scope.value === "selected";
        get("studentPhotoSelectionTools").classList.toggle("d-none", !selected);
        selectionShow.setAttribute("aria-pressed", String(selectionVisible));
        selectionShow.textContent = selectionVisible ? "Hide Selection" : "Show Selection";
        canvas.style.cursor = selected && selectionBrush.value !== "move" ? "crosshair" : "move";
        syncSettings(); syncPreviewStatus();
    };
    const syncBackground = () => element.querySelectorAll("[data-photo-background]").forEach(button => {
        const active = button.dataset.photoBackground === background;
        button.setAttribute("aria-pressed", String(active)); button.classList.toggle("active", active);
    });
    const rotationRadians = () => (rotation + fineRotation) * Math.PI / 180;
    const imageScale = image => {
        const angle = rotationRadians(), cosine = Math.abs(Math.cos(angle)), sine = Math.abs(Math.sin(angle));
        // Fit the inverse-rotated crop inside the image, covering all four corners.
        return Math.max((canvas.width * cosine + canvas.height * sine) / image.width,
            (canvas.width * sine + canvas.height * cosine) / image.height) * scale;
    };
    const drawImage = (image, target = context) => {
        if (!image) return;
        const factor = imageScale(image);
        target.save();
        target.translate(canvas.width / 2 + x, canvas.height / 2 + y);
        target.rotate(rotationRadians());
        target.drawImage(image, -image.width * factor / 2, -image.height * factor / 2, image.width * factor, image.height * factor);
        target.restore();
        return factor;
    };
    const colorFilter = values => `brightness(${100 + values.brightness}%) contrast(${100 + values.contrast}%) saturate(${100 + values.saturation}%)`;
    const linearChannels = Float64Array.from({ length: 256 }, (_, value) => {
        const channel = value / 255;
        return channel <= .04045 ? channel / 12.92 : ((channel + .055) / 1.055) ** 2.4;
    });
    const applyExposure = (target, ev) => {
        if (!ev) return;
        // Exposure stops multiply linear light, then convert back to sRGB.
        // Keep alpha unchanged so a cutout still blends into its chosen background.
        const multiplier = 2 ** ev;
        const lookup = Uint8ClampedArray.from(linearChannels, channel => {
            const light = Math.min(1, channel * multiplier);
            return 255 * (light <= .0031308 ? light * 12.92 : 1.055 * light ** (1 / 2.4) - .055);
        });
        const pixels = target.getImageData(0, 0, canvas.width, canvas.height);
        for (let i = 0; i < pixels.data.length; i += 4) {
            for (let channel = 0; channel < 3; channel++) pixels.data[i + channel] = lookup[pixels.data[i + channel]];
        }
        target.putImageData(pixels, 0, 0);
    };
    const applyDetails = (target, values) => {
        // Gentle edge-preserving smoothing followed by unsharp masking; face geometry stays unchanged.
        if (values.denoise || values.sharpen) {
            const pixels = target.getImageData(0, 0, canvas.width, canvas.height), data = pixels.data;
            const original = new Uint8ClampedArray(data), width = canvas.width;
            for (let row = 1; row < canvas.height - 1; row++) for (let column = 1; column < width - 1; column++) {
                const i = (row * width + column) * 4;
                for (let channel = 0; channel < 3; channel++) {
                    let sum = original[i + channel] * 4, weight = 4;
                    for (const offset of [-width * 4, -4, 4, width * 4]) {
                        const neighbor = original[i + offset + channel];
                        if (Math.abs(neighbor - original[i + channel]) < 30) { sum += neighbor; weight++; }
                    }
                    const smooth = sum / weight;
                    const blur = (original[i - width * 4 + channel] + original[i + width * 4 + channel] + original[i - 4 + channel] + original[i + 4 + channel] + original[i + channel] * 4) / 8;
                    data[i + channel] = original[i + channel] + (smooth - original[i + channel]) * values.denoise / 100 + (original[i + channel] - blur) * values.sharpen / 100;
                }
            }
            target.putImageData(pixels, 0, 0);
        }
    };
    const applyNatural = (target, values) => {
        if (!hasNaturalTone(values) && !values.portraitSmooth) return;
        const pixels = target.getImageData(0, 0, canvas.width, canvas.height);
        applyNaturalTone(pixels.data, values);
        smoothPortrait(pixels.data, canvas.width, canvas.height, values.portraitSmooth);
        target.putImageData(pixels, 0, 0);
    };
    const draw = (showSelection = true, measure = true) => {
        if (measure && source && !busy && !comparing) queueSize();
        context.clearRect(0, 0, canvas.width, canvas.height);
        context.fillStyle = !comparing && background !== "original" ? background : "#ffffff";
        context.fillRect(0, 0, canvas.width, canvas.height);
        if (comparing) { drawImage(source); return; }
        context.save(); context.filter = colorFilter(settings);
        const photo = background === "original" ? source : (cutout || source);
        if ((settings.exposure || hasNaturalTone(settings) || settings.portraitSmooth) && photo) {
            adjustedContext.clearRect(0, 0, canvas.width, canvas.height);
            drawImage(photo, adjustedContext); applyExposure(adjustedContext, settings.exposure);
            applyNatural(adjustedContext, settings);
            context.drawImage(adjustedPreview, 0, 0);
        } else drawImage(photo);
        context.restore(); applyDetails(context, settings);
        if (!selection) return;
        // The mask lives in source coordinates, sharing crop/rotation with the photo.
        selectionContext.clearRect(0, 0, canvas.width, canvas.height);
        drawImage(selection, selectionContext);
        if (Object.values(areaSettings).some(value => value !== 0)) {
            adjustedContext.clearRect(0, 0, canvas.width, canvas.height);
            adjustedContext.drawImage(canvas, 0, 0);
            applyExposure(adjustedContext, areaSettings.exposure);
            applyNatural(adjustedContext, areaSettings);
            adjustedContext.save(); adjustedContext.filter = colorFilter(areaSettings);
            adjustedContext.drawImage(adjustedPreview, 0, 0); adjustedContext.restore();
            applyDetails(adjustedContext, areaSettings);
            const original = context.getImageData(0, 0, canvas.width, canvas.height);
            const adjusted = adjustedContext.getImageData(0, 0, canvas.width, canvas.height).data;
            const mask = selectionContext.getImageData(0, 0, canvas.width, canvas.height).data;
            for (let i = 0; i < original.data.length; i += 4) {
                const weight = mask[i + 3] / 255;
                if (!weight) continue;
                for (let c = 0; c < 3; c++) original.data[i + c] += (adjusted[i + c] - original.data[i + c]) * weight;
            }
            context.putImageData(original, 0, 0);
        }
        if (showSelection && selectionVisible && scope.value === "selected") {
            selectionContext.save(); selectionContext.globalCompositeOperation = "source-in";
            selectionContext.fillStyle = "rgba(217, 70, 239, .35)";
            selectionContext.fillRect(0, 0, canvas.width, canvas.height); selectionContext.restore();
            context.drawImage(selectionPreview, 0, 0);
        }
    };
    const editedBlob = () => {
        const originalComparison = comparing;
        comparing = false; draw(false, false);
        const blob = canvasBlob(canvas);
        comparing = originalComparison; draw(true, false);
        return blob;
    };
    const measureSize = async blob => {
        const bytes = new Uint8Array(await blob.arrayBuffer());
        if (sizeCache && sizeCache.bytes.length === bytes.length && bytes.every((value, i) => value === sizeCache.bytes[i])) return sizeCache.promise;
        sizeCache?.controller.abort();
        const controller = new AbortController(), timeout = setTimeout(() => controller.abort(), 20000);
        const entry = { bytes, controller };
        entry.promise = (async () => {
            try {
                const data = new FormData(); data.append("photo", blob, "edited-photo.png");
                data.append("format", canvas.width === canvas.height ? "square" : "portrait");
                const response = await fetch(element.dataset.sizeUrl, {
                    method: "POST", credentials: "same-origin", signal: controller.signal,
                    headers: { "X-CSRF-TOKEN": csrf || "", Accept: "application/json" }, body: data,
                });
                const result = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(result.errors?.photo?.[0] || result.message || "The WebP size could not be calculated. Please try again.");
                const unavailable = outputSizeSelect && result.webp_available === false && result.bytes === null;
                if ((!unavailable && (!Number.isInteger(result.bytes) || result.bytes <= 0 || result.bytes > 40960)) || !result.width || !result.height) throw new Error("The server did not return a valid WebP size. Please try again.");
                if (outputSizeSelect && (!Number.isInteger(result.jpg_bytes) || result.jpg_bytes <= 0)) throw new Error('The JPG size could not be calculated. Please try again.');
                return outputSize ? { ...result, photo_size: { ...outputSize } } : result;
            } catch (exception) { if (sizeCache === entry) sizeCache = null; throw exception; }
            finally { clearTimeout(timeout); }
        })();
        sizeCache = entry;
        return entry.promise;
    };
    const queueSize = () => {
        clearTimeout(sizeTimer);
        const version = ++sizeVersion, token = generation;
        fileSize.textContent = outputSizeSelect ? 'Calculating JPG and WebP sizes…' : 'Calculating WebP size…';
        sizeTimer = setTimeout(async () => {
            if (!source || busy) return;
            try {
                const data = await measureSize(await editedBlob());
                if (version === sizeVersion && token === generation && source && !busy) fileSize.textContent = sizeLabel(data);
            } catch {
                if (version === sizeVersion && token === generation && source) fileSize.textContent = outputSizeSelect
                    ? 'Photo sizes unavailable. They will be checked when you click Use Photo.'
                    : 'WebP size unavailable. It will be checked when you click Use Photo.';
            }
        }, 1200);
    };
    const scheduleDraw = () => { cancelAnimationFrame(adjustmentFrame); adjustmentFrame = requestAnimationFrame(() => draw()); };
    const updateOutputSize = () => {
        get('photoEditorCustomSize').classList.toggle('d-none', outputSizeSelect.value !== 'custom');
        const widthInput = get('photoEditorWidth'), heightInput = get('photoEditorHeight'), errorBox = get('photoEditorSizeError');
        try {
            const next = photoOutputSize(outputSizeSelect.value, widthInput.value, heightInput.value);
            outputSizeError = '';
            errorBox.classList.add('d-none');
            widthInput.setAttribute('aria-invalid', 'false'); heightInput.setAttribute('aria-invalid', 'false');
            use.disabled = busy;
            get('photoEditorSizeHint').textContent = `${next.label} · ${next.width} × ${next.height} pixels · JPG at 300 DPI`;
            element.dataset.photoFormat = next.label;
            element.style.setProperty('--photo-editor-ratio', next.width / next.height);
            x *= next.width / canvas.width; y *= next.height / canvas.height;
            outputSize = next; pointer = null;
            canvas.width = selectionPreview.width = adjustedPreview.width = next.width;
            canvas.height = selectionPreview.height = adjustedPreview.height = next.height;
            clearTimeout(sizeTimer); sizeVersion++; sizeCache?.controller.abort(); sizeCache = null;
            cancelAnimationFrame(adjustmentFrame); draw();
            if (!busy) status.textContent = `${next.label} · JPG at 300 DPI · WebP up to 40 KB`;
        } catch (exception) {
            outputSizeError = exception.message; errorBox.textContent = outputSizeError; errorBox.classList.remove('d-none');
            widthInput.setAttribute('aria-invalid', String(!widthInput.checkValidity() || widthInput.value === ''));
            heightInput.setAttribute('aria-invalid', String(!heightInput.checkValidity() || heightInput.value === ''));
            use.disabled = true;
        }
    };
    if (outputSizeSelect) {
        outputSizeSelect.addEventListener('change', updateOutputSize);
        for (const id of ['photoEditorWidth', 'photoEditorHeight']) get(id).addEventListener('input', updateOutputSize);
    }
    const compare = value => {
        comparing = value; before.setAttribute("aria-pressed", String(value));
        before.textContent = value ? "View Edited" : "View Original";
        syncPreviewStatus();
        draw();
    };
    const reset = () => {
        clearTimeout(sizeTimer); sizeVersion++; sizeCache?.controller.abort(); sizeCache = null;
        request?.abort(); cutout = null; scale = 1; rotation = 0; fineRotation = 0; x = 0; y = 0;
        angleInput.value = angleRange.value = "0";
        selection = null; selectionVisible = true; scope.value = "whole"; selectionBrush.value = "paint";
        get("studentPhotoSelectionSize").value = "45"; syncSelectionTools();
        background = "original"; zoom.value = "1"; brush.value = "move"; resetSettings(); syncBackground(); clearError(); compare(false);
        const guides = get('studentPhotoGuides');
        if (guides) { guides.checked = false; get('studentPhotoGuideOverlay').classList.add('d-none'); }
    };
    const showPreview = (fileOrUrl, size = null) => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = fileOrUrl instanceof Blob ? URL.createObjectURL(fileOrUrl) : null;
        preview.src = previewUrl || fileOrUrl;
        acceptedFile = fileOrUrl instanceof File ? fileOrUrl : null;
        preview.classList.remove("d-none"); previewContainer?.classList.remove("d-none");
        editButton?.classList.remove("d-none");
        previewSize.textContent = size ? sizeLabel(size) : "";
        previewSize.classList.toggle("d-none", !size);
    };
    const resetPreview = () => {
        clearTimeout(sizeTimer); sizeVersion++; sizeCache?.controller.abort(); sizeCache = null;
        generation++; request?.abort(); source = null; cutout = null; selection = null;
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = null; acceptedFile = null; preview.removeAttribute("src"); input.value = "";
        previewContainer?.classList.add("d-none");
        editButton?.classList.add("d-none");
        previewSize.classList.add("d-none"); previewSize.textContent = "";
    };
    const open = async file => {
        const token = ++generation;
        if (!file || !["image/jpeg", "image/png", "image/webp"].includes(file.type)) { showError("Invalid photo", "Choose a JPG, PNG, or WebP photo."); return; }
        if (file.size > MAX_SOURCE_BYTES) { showError("Photo too large", "Please choose a photo up to 10 MB."); return; }
        try {
            const image = await loadImage(file);
            if (token !== generation) return;
            if (image.naturalWidth * image.naturalHeight > 24000000) throw new Error("Please choose a photo smaller than 24 megapixels.");
            const factor = Math.min(1, 1600 / Math.max(image.naturalWidth, image.naturalHeight));
            source = document.createElement("canvas"); source.width = Math.round(image.naturalWidth * factor); source.height = Math.round(image.naturalHeight * factor);
            source.getContext("2d").drawImage(image, 0, 0, source.width, source.height);
            reset(); setBusy(false);
            returnToEnrollment = enrollment?.classList.contains("show") || false;
            if (returnToEnrollment) {
                const hideParent = () => { if (token === generation) bootstrap.Modal.getInstance(enrollment)?.hide(); };
                // Bootstrap ignores hide() during its opening transition. Retry once
                // shown, so a quick Edit Photo click cannot leave the studio waiting.
                enrollment.addEventListener("shown.bs.modal", hideParent, { once: true });
                enrollment.addEventListener("hidden.bs.modal", () => {
                    enrollment.removeEventListener("shown.bs.modal", hideParent);
                    if (token === generation) modal.show();
                }, { once: true });
                hideParent();
            } else modal.show();
        } catch (exception) { showError("Photo editor", exception.message); }
    };
    const removeBackground = async (color = "#1976d2") => {
        if (!source || busy) return;
        if (cutout) { background = color; syncBackground(); compare(false); return; }
        const token = generation;
        clearError(); setBusy(true); request = new AbortController();
        const timeout = setTimeout(() => request?.abort(), 80000);
        try {
            const data = new FormData(); data.append("photo", await canvasBlob(source), "portrait.png");
            const response = await fetch(element.dataset.backgroundUrl, { method: "POST", credentials: "same-origin", headers: { "X-CSRF-TOKEN": csrf || "", Accept: "application/json" }, body: data, signal: request.signal });
            if (!response.ok) { const payload = await response.json().catch(() => ({})); throw new Error(payload.errors?.photo?.[0] || payload.message || "The background could not be removed. Please try again."); }
            const image = await loadImage(await response.blob());
            if (token !== generation) return;
            cutout = document.createElement("canvas"); cutout.width = source.width; cutout.height = source.height;
            cutout.getContext("2d").drawImage(image, 0, 0, source.width, source.height);
            background = color; syncBackground(); compare(false);
        } catch (exception) {
            if (token === generation && element.classList.contains("show")) fail(exception.name === "AbortError" ? "Processing timed out. Please try again." : exception.message);
        } finally { clearTimeout(timeout); if (token === generation) setBusy(false); }
    };
    input?.addEventListener("change", () => {
        const file = input.files?.[0];
        const transfer = new DataTransfer(); if (acceptedFile) transfer.items.add(acceptedFile);
        input.files = transfer.files;
        if (file) open(file);
    });
    input?.addEventListener("click", event => event.stopPropagation());
    dropzone?.addEventListener("click", event => { if (event.target !== input) input.click(); });
    dropzone?.addEventListener("keydown", event => { if (["Enter", " "].includes(event.key)) { event.preventDefault(); input.click(); } });
    dropzone?.addEventListener("dragover", event => { event.preventDefault(); dropzone.classList.add("is-dragging"); });
    dropzone?.addEventListener("dragleave", () => dropzone.classList.remove("is-dragging"));
    dropzone?.addEventListener("drop", event => { event.preventDefault(); dropzone.classList.remove("is-dragging"); open(event.dataTransfer.files?.[0]); });
    document.addEventListener("paste", event => {
        const pasteActive = enrollment ? enrollment.classList.contains("show") : elements.pasteTarget?.getClientRects().length > 0;
        if (!pasteActive || event.defaultPrevented || element.classList.contains("show") || event.target?.closest?.("input:not([type='file']), textarea, [contenteditable='true'], #enrollmentDocumentDropzone")) return;
        const file = [...(event.clipboardData?.files || [])].find(entry => entry.type.startsWith("image/"))
            || [...(event.clipboardData?.items || [])].find(entry => entry.kind === "file" && entry.type.startsWith("image/"))?.getAsFile();
        if (file) { event.preventDefault(); open(file); }
    });
    editButton?.addEventListener("click", async event => {
        event.preventDefault(); event.stopPropagation();
        try {
            if (input.files?.[0]) await open(input.files[0]);
            else { const response = await fetch(preview.src); if (!response.ok) throw new Error("The photo could not be loaded."); await open(await response.blob()); }
        } catch (exception) { showError("Photo editor", exception.message); }
    });
    zoom.addEventListener("input", () => { scale = Number(zoom.value); scheduleDraw(); });
    for (const [id, amount] of [["studentPhotoZoomIn", .1], ["studentPhotoZoomOut", -.1]]) get(id).addEventListener("click", () => { zoom.value = Math.min(3, Math.max(1, Number(zoom.value) + amount)); zoom.dispatchEvent(new Event("input")); });
    for (const [id, amount] of [["studentPhotoRotateLeft", -90], ["studentPhotoRotateRight", 90]]) get(id).addEventListener("click", () => { rotation += amount; draw(); });
    const straighten = (value, syncInput = true) => {
        fineRotation = Math.round(Math.max(-45, Math.min(45, value)) * 10) / 10;
        angleRange.value = String(fineRotation);
        if (syncInput) angleInput.value = String(fineRotation);
        scheduleDraw();
    };
    angleInput.addEventListener("input", () => {
        if (angleInput.value !== "" && Number.isFinite(angleInput.valueAsNumber)) straighten(angleInput.valueAsNumber, false);
    });
    angleInput.addEventListener("change", () => straighten(Number.isFinite(angleInput.valueAsNumber) ? angleInput.valueAsNumber : fineRotation));
    angleRange.addEventListener("input", () => straighten(Number(angleRange.value)));
    get("studentPhotoAngleReset").addEventListener("click", () => straighten(0));
    scope.addEventListener("change", () => { pointer = null; syncSelectionTools(); compare(false); });
    selectionBrush.addEventListener("change", () => { pointer = null; syncSelectionTools(); });
    selectionShow.addEventListener("click", () => { selectionVisible = !selectionVisible; syncSelectionTools(); draw(); });
    get("studentPhotoSelectionClear").addEventListener("click", () => { selection = null; draw(); });
    get("studentPhotoSelectionReset").addEventListener("click", () => {
        for (const key of Object.keys(areaSettings)) areaSettings[key] = 0;
        syncSettings(); draw();
    });
    get("studentPhotoReset").addEventListener("click", reset);
    get('studentPhotoGuides')?.addEventListener('change', event => {
        get('studentPhotoGuideOverlay').classList.toggle('d-none', !event.target.checked);
    });
    element.querySelectorAll('[data-photo-preset]').forEach(button => button.addEventListener('click', () => {
        Object.assign(currentSettings(), photoPresets[button.dataset.photoPreset]);
        syncSettings(); compare(false);
    }));
    get('studentPhotoAdjustmentReset')?.addEventListener('click', () => {
        Object.assign(currentSettings(), photoPresets.original);
        syncSettings(); compare(false);
    });
    before.addEventListener("click", () => compare(!comparing));
    get("studentPhotoRemoveBackground").addEventListener("click", () => removeBackground());
    element.querySelectorAll("[data-photo-background]").forEach(button => button.addEventListener("click", () => {
        const color = button.dataset.photoBackground;
        if (color === "original") { background = color; syncBackground(); compare(false); }
        else removeBackground(color);
    }));
    get("studentPhotoBackgroundColor").addEventListener("input", event => { if (cutout) { background = event.target.value; syncBackground(); compare(false); } });
    get("studentPhotoBackgroundColor").addEventListener("change", event => removeBackground(event.target.value));
    element.querySelectorAll("[data-photo-adjustment]").forEach(control => control.addEventListener("input", () => {
        currentSettings()[control.dataset.photoAdjustment] = Number(control.value);
        element.querySelector(`[data-photo-value="${control.dataset.photoAdjustment}"]`).textContent = adjustmentLabel(control.dataset.photoAdjustment, Number(control.value));
        syncPresets();
        comparing = false; before.setAttribute("aria-pressed", "false"); before.textContent = "View Original";
        syncPreviewStatus(); scheduleDraw();
    }));
    get("studentPhotoEnhance").addEventListener("click", () => {
        compare(true);
        const data = context.getImageData(0, 0, canvas.width, canvas.height).data;
        let light = 0, count = 0;
        for (let i = 0; i < data.length; i += 64) { light += data[i] * .2126 + data[i + 1] * .7152 + data[i + 2] * .0722; count++; }
        const values = { ...naturalDefaults, exposure: 0, brightness: Math.round(Math.max(-10, Math.min(18, (138 - light / count) / 4))), contrast: 5, saturation: 0, denoise: 12, sharpen: 18, shadows: 12, highlights: -12, vibrance: 6 };
        for (const key of Object.keys(values)) { get(`studentPhoto-${key}`).value = values[key]; get(`studentPhoto-${key}`).dispatchEvent(new Event("input")); }
    });
    const point = event => {
        const rect = canvas.getBoundingClientRect();
        return { x: (event.clientX - rect.left) * canvas.width / rect.width, y: (event.clientY - rect.top) * canvas.height / rect.height };
    };
    const sourcePoint = p => {
        const angle = -rotationRadians();
        const factor = imageScale(source);
        const dx = p.x - canvas.width / 2 - x, dy = p.y - canvas.height / 2 - y;
        return { x: (dx * Math.cos(angle) - dy * Math.sin(angle)) / factor + source.width / 2,
            y: (dx * Math.sin(angle) + dy * Math.cos(angle)) / factor + source.height / 2, factor };
    };
    const paintSelection = (from, to) => {
        if (!selection) { selection = document.createElement("canvas"); selection.width = source.width; selection.height = source.height; }
        const start = sourcePoint(from), end = sourcePoint(to), target = selection.getContext("2d");
        const radius = Number(get("studentPhotoSelectionSize").value) / end.factor;
        const steps = Math.max(1, Math.ceil(Math.hypot(end.x - start.x, end.y - start.y) / (radius * .3)));
        target.save(); target.globalCompositeOperation = selectionBrush.value === "erase" ? "destination-out" : "source-over";
        for (let step = 0; step <= steps; step++) {
            const cx = start.x + (end.x - start.x) * step / steps, cy = start.y + (end.y - start.y) * step / steps;
            const gradient = target.createRadialGradient(cx, cy, radius * .65, cx, cy, radius);
            gradient.addColorStop(0, "rgba(255,255,255,1)"); gradient.addColorStop(1, "rgba(255,255,255,0)");
            target.fillStyle = gradient; target.fillRect(cx - radius, cy - radius, radius * 2, radius * 2);
        }
        target.restore(); scheduleDraw();
    };
    const paint = event => {
        if (!cutout || comparing || background === "original") return;
        const { x: cx, y: cy, factor } = sourcePoint(point(event));
        const brushContext = cutout.getContext("2d");
        brushContext.save(); brushContext.beginPath(); brushContext.arc(cx, cy, Number(get("studentPhotoBrushSize").value) / factor, 0, Math.PI * 2); brushContext.clip();
        if (brush.value === "smooth" && smoothStroke) {
            brushContext.clearRect(0, 0, source.width, source.height);
            brushContext.drawImage(smoothStroke, 0, 0);
        }
        else if (brush.value === "erase") brushContext.clearRect(0, 0, source.width, source.height);
        else if (brush.value === "restore") { brushContext.clearRect(0, 0, source.width, source.height); brushContext.drawImage(source, 0, 0); }
        brushContext.restore(); scheduleDraw();
    };
    canvas.addEventListener("pointerdown", event => {
        if (busy || comparing || !source) return;
        event.preventDefault(); pointer = point(event); canvas.setPointerCapture(event.pointerId);
        smoothStroke = null;
        if (scope.value === "selected") {
            if (selectionBrush.value !== "move") paintSelection(pointer, pointer);
            return;
        }
        if (brush.value === "smooth" && cutout && background !== "original") {
            // Canvas blur blends premultiplied RGBA, avoiding a dark/white fringe
            // where transparent pixels become partially visible. Freeze it for
            // this stroke so repeated pointer events do not accumulate blur.
            smoothStroke = document.createElement("canvas");
            smoothStroke.width = cutout.width; smoothStroke.height = cutout.height;
            const softContext = smoothStroke.getContext("2d");
            softContext.filter = `blur(${Math.max(.5, Math.min(1.5, Math.max(cutout.width, cutout.height) / 1000))}px)`;
            softContext.drawImage(cutout, 0, 0);
            const original = cutout.getContext("2d").getImageData(0, 0, cutout.width, cutout.height);
            const softened = softContext.getImageData(0, 0, cutout.width, cutout.height);
            for (let i = 0; i < original.data.length; i += 4) {
                if (original.data[i + 3] === 255 && softened.data[i + 3] >= 254) {
                    softened.data.set(original.data.subarray(i, i + 4), i);
                }
            }
            softContext.putImageData(softened, 0, 0);
        }
        if (brush.value !== "move") paint(event);
    });
    canvas.addEventListener("pointermove", event => {
        if (!pointer) return;
        if (scope.value === "selected" && selectionBrush.value !== "move") {
            const next = point(event); paintSelection(pointer, next); pointer = next; return;
        }
        if (scope.value === "selected") { const next = point(event); x += next.x - pointer.x; y += next.y - pointer.y; pointer = next; scheduleDraw(); return; }
        if (brush.value === "move") { const next = point(event); x += next.x - pointer.x; y += next.y - pointer.y; pointer = next; scheduleDraw(); }
        else paint(event);
    });
    for (const name of ["pointerup", "pointercancel", "lostpointercapture"]) canvas.addEventListener(name, () => { pointer = null; smoothStroke = null; });
    use.addEventListener("click", async () => {
        if (busy || !source || outputSizeError) return;
        const token = generation;
        clearError(); compare(false); cancelAnimationFrame(adjustmentFrame); draw(false); setBusy(true);
        clearTimeout(sizeTimer); sizeVersion++;
        try {
            // Upload a lossless crop; the server chooses WebP quality and
            // resolution once, so there is no double lossy compression.
            const blob = await canvasBlob(canvas), file = new File([blob], "edited-photo.png", { type: "image/png" });
            const size = await measureSize(blob);
            if (token !== generation || !source) return;
            const transfer = new DataTransfer(); transfer.items.add(file); input.files = transfer.files; showPreview(file, size);
            onApply(file, size);
            modal.hide(); showSuccess("Photo ready", saveMessage);
        } catch (exception) { if (token === generation) fail(exception.message); }
        finally { if (token === generation) { setBusy(false); if (source) draw(); } }
    });
    element.addEventListener("hidden.bs.modal", () => {
        clearTimeout(sizeTimer); sizeVersion++; sizeCache?.controller.abort(); sizeCache = null;
        generation++; request?.abort(); pointer = null; smoothStroke = null; source = null; cutout = null; selection = null; setBusy(false);
        if (returnToEnrollment) { returnToEnrollment = false; bootstrap.Modal.getOrCreateInstance(enrollment).show(); }
    });
    return { showPreview, resetPreview };
}

export const createStudentPhotoEditor = createPhotoEditor;
