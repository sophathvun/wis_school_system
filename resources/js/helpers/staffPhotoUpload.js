import { Modal } from 'bootstrap';
import { showError } from './sweet-alert2';

export function setupStaffPhotoUpload(form) {
    const input = form.elements.namedItem('photo');
    const dropzone = form.querySelector('[data-staff-photo-dropzone]');
    const preview = form.querySelector('[data-staff-photo-preview]');
    const cropElement = document.getElementById('hrmStaffPhotoCropModal');
    const staffElement = form.closest('.modal');
    if (!input || !dropzone || !preview || !cropElement || !staffElement) return;
    const canvas = cropElement.querySelector('canvas');
    const context = canvas.getContext('2d');
    const zoom = cropElement.querySelector('[data-staff-photo-zoom]');
    const apply = cropElement.querySelector('[data-staff-photo-apply]');
    const cropModal = Modal.getOrCreateInstance(cropElement);
    const staffModal = Modal.getOrCreateInstance(staffElement);
    let image, scale = 1, rotation = 0, offsetX = 0, offsetY = 0, pointer;
    let selectedFile = null, previewUrl, busy = false, applying = false, returnToStaff = false;
    const setInput = file => {
        const transfer = new DataTransfer();
        if (file) transfer.items.add(file);
        input.files = transfer.files;
    };
    const draw = () => {
        if (!image) return;
        context.clearRect(0, 0, 400, 400);
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, 400, 400);
        const base = Math.max(400 / image.width, 400 / image.height) * scale;
        context.save();
        context.translate(200 + offsetX, 200 + offsetY);
        context.rotate(rotation * Math.PI / 180);
        context.drawImage(image, -image.width * base / 2, -image.height * base / 2, image.width * base, image.height * base);
        context.restore();
    };
    const reset = () => { scale = 1; rotation = offsetX = offsetY = 0; zoom.value = '1'; draw(); };
    const openCrop = async file => {
        if (!file || busy) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) {
            showError('Invalid staff photo', 'Choose a JPG, PNG, or WEBP image up to 2 MB.');
            return;
        }
        busy = true;
        const sourceUrl = URL.createObjectURL(file);
        try {
            const nextImage = new Image();
            await new Promise((resolve, reject) => {
                nextImage.onload = resolve;
                nextImage.onerror = reject;
                nextImage.src = sourceUrl;
            });
            image = nextImage;
            reset();
            returnToStaff = true;
            staffElement.addEventListener('hidden.bs.modal', () => cropModal.show(), { once: true });
            staffModal.hide();
        } catch {
            busy = false;
            showError('Unable to open photo', 'Choose another JPG, PNG, or WEBP image.');
        } finally { URL.revokeObjectURL(sourceUrl); }
    };
    input.addEventListener('click', event => event.stopPropagation());
    input.addEventListener('change', () => {
        const file = input.files?.[0];
        setInput(selectedFile);
        openCrop(file);
    });
    dropzone.addEventListener('click', () => input.click());
    dropzone.addEventListener('keydown', event => {
        if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); input.click(); }
    });
    dropzone.addEventListener('dragover', event => { event.preventDefault(); dropzone.classList.add('is-dragging'); });
    dropzone.addEventListener('dragleave', event => {
        if (!dropzone.contains(event.relatedTarget)) dropzone.classList.remove('is-dragging');
    });
    dropzone.addEventListener('drop', event => {
        event.preventDefault();
        dropzone.classList.remove('is-dragging');
        openCrop(event.dataTransfer?.files?.[0]);
    });
    document.addEventListener('paste', event => {
        if (!staffElement.classList.contains('show') || busy || event.defaultPrevented) return;
        if (event.target.closest?.('input:not([type="file"]),textarea,[contenteditable="true"]')) return;
        const file = [...(event.clipboardData?.files || [])].find(file => file.type.startsWith('image/'))
            || [...(event.clipboardData?.items || [])].find(item => item.kind === 'file' && item.type.startsWith('image/'))?.getAsFile();
        if (file) { event.preventDefault(); openCrop(file); }
    });
    zoom.addEventListener('input', () => { scale = Number(zoom.value); draw(); });
    cropElement.querySelectorAll('[data-staff-photo-zoom-step]').forEach(button => button.addEventListener('click', () => {
        zoom.value = String(Math.min(3, Math.max(1, Number(zoom.value) + Number(button.dataset.staffPhotoZoomStep))));
        zoom.dispatchEvent(new Event('input'));
    }));
    cropElement.querySelectorAll('[data-staff-photo-rotate]').forEach(button => button.addEventListener('click', () => {
        rotation += Number(button.dataset.staffPhotoRotate); draw();
    }));
    cropElement.querySelector('[data-staff-photo-reset]').addEventListener('click', reset);
    canvas.addEventListener('pointerdown', event => {
        pointer = { id: event.pointerId, x: event.clientX, y: event.clientY };
        canvas.setPointerCapture(event.pointerId);
    });
    canvas.addEventListener('pointermove', event => {
        if (!pointer || pointer.id !== event.pointerId) return;
        const rect = canvas.getBoundingClientRect();
        offsetX += (event.clientX - pointer.x) * 400 / rect.width;
        offsetY += (event.clientY - pointer.y) * 400 / rect.height;
        pointer.x = event.clientX; pointer.y = event.clientY;
        draw();
    });
    ['pointerup', 'pointercancel', 'lostpointercapture'].forEach(type => canvas.addEventListener(type, () => { pointer = null; }));
    cropElement.addEventListener('hide.bs.modal', event => { if (applying) event.preventDefault(); });
    cropElement.addEventListener('hidden.bs.modal', () => {
        busy = false;
        pointer = null;
        if (returnToStaff) {
            returnToStaff = false;
            staffElement.addEventListener('shown.bs.modal', () => dropzone.focus(), { once: true });
            staffModal.show();
        }
    });
    apply.addEventListener('click', () => {
        if (!image || applying) return;
        applying = true;
        apply.disabled = true;
        canvas.toBlob(blob => {
            applying = false;
            apply.disabled = false;
            if (!blob || blob.size > 2 * 1024 * 1024) { showError('Unable to crop photo', 'Please try again with another photo.'); return; }
            selectedFile = new File([blob], 'staff-photo.jpg', { type: 'image/jpeg' });
            setInput(selectedFile);
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            previewUrl = URL.createObjectURL(selectedFile);
            preview.src = previewUrl;
            preview.classList.remove('d-none');
            cropModal.hide();
        }, 'image/jpeg', .9);
    });
    form.addEventListener('submit', event => {
        if (busy) { event.preventDefault(); showError('Finish cropping the photo', 'Crop or cancel the photo before saving the staff profile.'); }
    });
    window.addEventListener('pagehide', () => { if (previewUrl) URL.revokeObjectURL(previewUrl); });
}
