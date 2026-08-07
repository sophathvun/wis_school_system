import * as bootstrap from "bootstrap";

const init = () => {
    const input = document.getElementById("profile_photo");
    const zone = document.getElementById("profilePhotoDropzone");
    const preview = document.querySelector("#profilePhotoPreview img");
    const previewBox = document.getElementById("profilePhotoPreview");
    const modalElement = document.getElementById("profilePhotoCropModal");
    const canvas = document.getElementById("profilePhotoCropCanvas");
    if (!input || !zone || !preview || !previewBox || !modalElement || !canvas || input.dataset.cropReady) return;
    input.dataset.cropReady = "1";
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const context = canvas.getContext("2d");
    const zoom = document.getElementById("profilePhotoZoom");
    let image = null, scale = 1, rotation = 0, offsetX = 0, offsetY = 0, dragging = false, start = null;
    const draw = () => {
        if (!image) return;
        context.clearRect(0, 0, 400, 400);
        context.fillStyle = "#fff";
        context.fillRect(0, 0, 400, 400);
        const size = Math.max(400 / image.width, 400 / image.height) * scale;
        context.save();
        context.translate(200 + offsetX, 200 + offsetY);
        context.rotate(rotation * Math.PI / 180);
        context.drawImage(image, -image.width * size / 2, -image.height * size / 2, image.width * size, image.height * size);
        context.restore();
    };
    const open = file => {
        if (!file || !(file.type || "").startsWith("image/")) return;
        const reader = new FileReader();
        reader.onload = () => { const next = new Image(); next.onload = () => { image = next; scale = 1; rotation = 0; offsetX = 0; offsetY = 0; zoom.value = "1"; draw(); modal.show(); }; next.src = reader.result; };
        reader.readAsDataURL(file);
    };
    input.addEventListener("click", event => event.stopPropagation());
    input.addEventListener("change", () => { const file = input.files?.[0]; input.value = ""; open(file); });
    zone.addEventListener("drop", event => { event.preventDefault(); zone.classList.remove("is-dragging"); open(event.dataTransfer?.files?.[0]); });
    zone.addEventListener("dragover", event => { event.preventDefault(); zone.classList.add("is-dragging"); });
    zone.addEventListener("dragleave", () => zone.classList.remove("is-dragging"));
    zoom.addEventListener("input", () => { scale = Number(zoom.value); draw(); });
    document.getElementById("profilePhotoZoomIn")?.addEventListener("click", () => { zoom.value = Math.min(3, Number(zoom.value) + .1).toFixed(2); zoom.dispatchEvent(new Event("input")); });
    document.getElementById("profilePhotoZoomOut")?.addEventListener("click", () => { zoom.value = Math.max(1, Number(zoom.value) - .1).toFixed(2); zoom.dispatchEvent(new Event("input")); });
    document.getElementById("profilePhotoRotateLeft")?.addEventListener("click", () => { rotation -= 90; draw(); });
    document.getElementById("profilePhotoRotateRight")?.addEventListener("click", () => { rotation += 90; draw(); });
    canvas.addEventListener("pointerdown", event => { dragging = true; start = { x: event.clientX, y: event.clientY }; canvas.setPointerCapture(event.pointerId); });
    canvas.addEventListener("pointermove", event => { if (!dragging || !start) return; const rect = canvas.getBoundingClientRect(); offsetX += (event.clientX - start.x) * 400 / rect.width; offsetY += (event.clientY - start.y) * 400 / rect.height; start = { x: event.clientX, y: event.clientY }; draw(); });
    ["pointerup", "pointercancel"].forEach(type => canvas.addEventListener(type, () => { dragging = false; start = null; }));
    document.getElementById("profilePhotoCropUpload")?.addEventListener("click", () => canvas.toBlob(blob => { if (!blob) return; const file = new File([blob], "profile-photo.jpg", { type: "image/jpeg" }); const transfer = new DataTransfer(); transfer.items.add(file); input.files = transfer.files; preview.src = URL.createObjectURL(file); previewBox.classList.remove("d-none"); modal.hide(); }, "image/jpeg", .9));
};

if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init, { once: true });
else init();
