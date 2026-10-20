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

export function createStudentPhotoEditor({ bootstrap, csrf, showError, showSuccess }) {
    const get = id => document.getElementById(id);
    const element = get("studentPhotoCropModal");
    if (!element) return { showPreview() {}, resetPreview() {} };
    const modal = new bootstrap.Modal(element), enrollment = get("enrollmentModal");
    const canvas = get("studentPhotoCropCanvas"), context = canvas.getContext("2d", { willReadFrequently: true });
    const input = get("student_photo"), dropzone = get("studentPhotoDropzone"), preview = get("studentPhotoPreview");
    const error = get("studentPhotoEditorError"), status = get("studentPhotoEditorStatus");
    const zoom = get("studentPhotoZoom"), brush = get("studentPhotoBrush");
    const before = get("studentPhotoCompare"), use = get("studentPhotoCropUpload");
    let source, cutout, acceptedFile = null, scale = 1, rotation = 0, x = 0, y = 0;
    let background = "original", comparing = false, pointer, smoothStroke, previewUrl, generation = 0, request, busy = false, returnToEnrollment = false;
    let adjustmentFrame = 0;
    const settings = { brightness: 0, contrast: 0, saturation: 0, denoise: 0, sharpen: 0 };
    const fail = message => { error.textContent = message; error.classList.remove("d-none"); showError("Photo editor", message); };
    const clearError = () => error.classList.add("d-none");
    const setBusy = value => {
        busy = value;
        element.setAttribute("aria-busy", String(value));
        element.querySelectorAll("button:not([data-bs-dismiss]), input, select").forEach(control => control.disabled = value);
        status.textContent = value ? "Processing your photo…" : "3:4 portrait · Saved as WebP, up to 40 KB";
    };
    const resetSettings = () => {
        Object.keys(settings).forEach(key => {
            settings[key] = 0;
            get(`studentPhoto-${key}`).value = "0";
            element.querySelector(`[data-photo-value="${key}"]`).textContent = "0";
        });
    };
    const syncBackground = () => element.querySelectorAll("[data-photo-background]").forEach(button => {
        const active = button.dataset.photoBackground === background;
        button.setAttribute("aria-pressed", String(active)); button.classList.toggle("active", active);
    });
    const drawImage = image => {
        if (!image) return;
        const swapped = Math.abs(rotation % 180) === 90;
        const base = Math.max(canvas.width / (swapped ? image.height : image.width), canvas.height / (swapped ? image.width : image.height));
        const factor = base * scale;
        context.save();
        context.translate(canvas.width / 2 + x, canvas.height / 2 + y);
        context.rotate(rotation * Math.PI / 180);
        context.drawImage(image, -image.width * factor / 2, -image.height * factor / 2, image.width * factor, image.height * factor);
        context.restore();
        return factor;
    };
    const draw = () => {
        context.clearRect(0, 0, canvas.width, canvas.height);
        context.fillStyle = !comparing && background !== "original" ? background : "#ffffff";
        context.fillRect(0, 0, canvas.width, canvas.height);
        if (comparing) { drawImage(source); return; }
        context.save();
        context.filter = `brightness(${100 + settings.brightness}%) contrast(${100 + settings.contrast}%) saturate(${100 + settings.saturation}%)`;
        drawImage(background === "original" ? source : (cutout || source));
        context.restore();
        // Gentle edge-preserving smoothing followed by unsharp masking; face geometry stays unchanged.
        if (settings.denoise || settings.sharpen) {
            const pixels = context.getImageData(0, 0, canvas.width, canvas.height), data = pixels.data;
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
                    data[i + channel] = original[i + channel] + (smooth - original[i + channel]) * settings.denoise / 100 + (original[i + channel] - blur) * settings.sharpen / 100;
                }
            }
            context.putImageData(pixels, 0, 0);
        }
    };
    const scheduleDraw = () => { cancelAnimationFrame(adjustmentFrame); adjustmentFrame = requestAnimationFrame(draw); };
    const compare = value => {
        comparing = value; before.setAttribute("aria-pressed", String(value));
        before.textContent = value ? "View Edited" : "View Original";
        get("studentPhotoCompareStatus").textContent = value ? "Original preview" : "Edited preview · Drag to reposition";
        draw();
    };
    const reset = () => {
        request?.abort(); cutout = null; scale = 1; rotation = 0; x = 0; y = 0;
        background = "original"; zoom.value = "1"; brush.value = "move"; resetSettings(); syncBackground(); clearError(); compare(false);
    };
    const showPreview = fileOrUrl => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = fileOrUrl instanceof Blob ? URL.createObjectURL(fileOrUrl) : null;
        preview.src = previewUrl || fileOrUrl;
        acceptedFile = fileOrUrl instanceof File ? fileOrUrl : null;
        get("studentPhotoPreviewContainer").classList.remove("d-none");
        get("studentPhotoEdit")?.classList.remove("d-none");
    };
    const resetPreview = () => {
        generation++; request?.abort(); source = null; cutout = null;
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = null; acceptedFile = null; preview.removeAttribute("src"); input.value = "";
        get("studentPhotoPreviewContainer").classList.add("d-none");
        get("studentPhotoEdit")?.classList.add("d-none");
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
                enrollment.addEventListener("hidden.bs.modal", () => { if (token === generation) modal.show(); }, { once: true });
                bootstrap.Modal.getInstance(enrollment)?.hide();
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
    dropzone?.addEventListener("click", event => { if (event.target !== input) input.click(); });
    dropzone?.addEventListener("keydown", event => { if (["Enter", " "].includes(event.key)) { event.preventDefault(); input.click(); } });
    dropzone?.addEventListener("dragover", event => { event.preventDefault(); dropzone.classList.add("is-dragging"); });
    dropzone?.addEventListener("dragleave", () => dropzone.classList.remove("is-dragging"));
    dropzone?.addEventListener("drop", event => { event.preventDefault(); dropzone.classList.remove("is-dragging"); open(event.dataTransfer.files?.[0]); });
    document.addEventListener("paste", event => {
        if (!enrollment?.classList.contains("show") || element.classList.contains("show") || event.target?.closest?.("input:not([type='file']), textarea, [contenteditable='true'], #enrollmentDocumentDropzone")) return;
        const file = [...(event.clipboardData?.files || [])].find(entry => entry.type.startsWith("image/"))
            || [...(event.clipboardData?.items || [])].find(entry => entry.kind === "file" && entry.type.startsWith("image/"))?.getAsFile();
        if (file) { event.preventDefault(); open(file); }
    });
    get("studentPhotoEdit")?.addEventListener("click", async () => {
        try {
            if (input.files?.[0]) await open(input.files[0]);
            else { const response = await fetch(preview.src); if (!response.ok) throw new Error("The photo could not be loaded."); await open(await response.blob()); }
        } catch (exception) { showError("Photo editor", exception.message); }
    });
    zoom.addEventListener("input", () => { scale = Number(zoom.value); scheduleDraw(); });
    for (const [id, amount] of [["studentPhotoZoomIn", .1], ["studentPhotoZoomOut", -.1]]) get(id).addEventListener("click", () => { zoom.value = Math.min(3, Math.max(1, Number(zoom.value) + amount)); zoom.dispatchEvent(new Event("input")); });
    for (const [id, amount] of [["studentPhotoRotateLeft", -90], ["studentPhotoRotateRight", 90]]) get(id).addEventListener("click", () => { rotation += amount; draw(); });
    get("studentPhotoReset").addEventListener("click", reset);
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
        settings[control.dataset.photoAdjustment] = Number(control.value);
        element.querySelector(`[data-photo-value="${control.dataset.photoAdjustment}"]`).textContent = control.value;
        comparing = false; before.setAttribute("aria-pressed", "false"); before.textContent = "View Original";
        get("studentPhotoCompareStatus").textContent = "Edited preview · Drag to reposition"; scheduleDraw();
    }));
    get("studentPhotoEnhance").addEventListener("click", () => {
        compare(true);
        const data = context.getImageData(0, 0, canvas.width, canvas.height).data;
        let light = 0, count = 0;
        for (let i = 0; i < data.length; i += 64) { light += data[i] * .2126 + data[i + 1] * .7152 + data[i + 2] * .0722; count++; }
        const values = { brightness: Math.round(Math.max(-10, Math.min(18, (138 - light / count) / 4))), contrast: 8, saturation: 4, denoise: 20, sharpen: 30 };
        for (const key of Object.keys(values)) { get(`studentPhoto-${key}`).value = values[key]; get(`studentPhoto-${key}`).dispatchEvent(new Event("input")); }
    });
    const point = event => {
        const rect = canvas.getBoundingClientRect();
        return { x: (event.clientX - rect.left) * canvas.width / rect.width, y: (event.clientY - rect.top) * canvas.height / rect.height };
    };
    const paint = event => {
        if (!cutout || comparing || background === "original") return;
        const p = point(event), angle = -rotation * Math.PI / 180;
        const swapped = Math.abs(rotation % 180) === 90;
        const factor = Math.max(canvas.width / (swapped ? source.height : source.width), canvas.height / (swapped ? source.width : source.height)) * scale;
        const dx = p.x - canvas.width / 2 - x, dy = p.y - canvas.height / 2 - y;
        const cx = (dx * Math.cos(angle) - dy * Math.sin(angle)) / factor + source.width / 2;
        const cy = (dx * Math.sin(angle) + dy * Math.cos(angle)) / factor + source.height / 2;
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
        if (busy || comparing) return;
        event.preventDefault(); pointer = point(event); canvas.setPointerCapture(event.pointerId);
        smoothStroke = null;
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
        if (brush.value === "move") { const next = point(event); x += next.x - pointer.x; y += next.y - pointer.y; pointer = next; scheduleDraw(); }
        else paint(event);
    });
    for (const name of ["pointerup", "pointercancel", "lostpointercapture"]) canvas.addEventListener(name, () => { pointer = null; smoothStroke = null; });
    use.addEventListener("click", async () => {
        if (busy || !source) return;
        clearError(); compare(false); setBusy(true);
        try {
            // Upload a lossless 600×800 crop; the server chooses WebP quality and
            // resolution once, so there is no double lossy compression.
            const blob = await canvasBlob(canvas), file = new File([blob], "student-photo.png", { type: "image/png" });
            const transfer = new DataTransfer(); transfer.items.add(file); input.files = transfer.files; showPreview(file);
            modal.hide(); showSuccess("Photo ready", "Save the enrollment to apply this photo.");
        } catch (exception) { fail(exception.message); }
        finally { setBusy(false); }
    });
    element.addEventListener("hidden.bs.modal", () => {
        generation++; request?.abort(); pointer = null; smoothStroke = null; source = null; cutout = null; setBusy(false);
        if (returnToEnrollment) { returnToEnrollment = false; bootstrap.Modal.getOrCreateInstance(enrollment).show(); }
    });
    return { showPreview, resetPreview };
}
