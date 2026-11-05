import {Modal} from 'bootstrap';

export function setupG9PhotoViewer() {
    const photoViewer = document.getElementById('g9PhotoViewer'), photoImage = photoViewer.querySelector('[data-g9-photo-image]');
    const photoDownload = photoViewer.querySelector('[data-g9-photo-download]'), photoStatus = photoViewer.querySelector('[data-g9-photo-status]');
    const photoReset = photoViewer.querySelector('[data-g9-photo-reset]');
    let photoZoom = 1, photoName = 'student-photo', photoReady = false;
    const zoomPhoto = value => {
        photoZoom = Math.max(.5, Math.min(4, value));
        photoImage.style.width = `${Math.round(300 * photoZoom)}px`;
        photoReset.textContent = `${Math.round(photoZoom * 100)}%`;
    };
    photoViewer.querySelectorAll('[data-g9-zoom]').forEach(button => button.addEventListener('click', () => zoomPhoto(photoZoom + Number(button.dataset.g9Zoom))));
    photoReset.addEventListener('click', () => zoomPhoto(1));
    photoImage.addEventListener('load', () => {
        photoReady = true; photoDownload.disabled = false;
        photoStatus.textContent = `${photoImage.naturalWidth} × ${photoImage.naturalHeight}px · JPG uses the full stored image resolution.`;
    });
    photoImage.addEventListener('error', () => {photoReady = false; photoDownload.disabled = true; photoStatus.textContent = 'The student photo could not be loaded.';});
    photoViewer.addEventListener('hidden.bs.modal', () => {photoReady = false; photoDownload.disabled = true; photoImage.removeAttribute('src');});
    photoDownload.addEventListener('click', () => {
        if (!photoReady) return;
        try {
            const canvas = document.createElement('canvas'); canvas.width = photoImage.naturalWidth; canvas.height = photoImage.naturalHeight;
            const context = canvas.getContext('2d'); context.fillStyle = '#fff'; context.fillRect(0, 0, canvas.width, canvas.height); context.drawImage(photoImage, 0, 0);
            canvas.toBlob(blob => {
                if (!blob) {photoStatus.textContent = 'JPG conversion failed. Please try again.'; return;}
                const url = URL.createObjectURL(blob), link = document.createElement('a'); link.href = url; link.download = `${photoName}.jpg`;
                document.body.append(link); link.click(); link.remove(); setTimeout(() => URL.revokeObjectURL(url), 1000);
            }, 'image/jpeg', 1);
        } catch {photoStatus.textContent = 'The photo could not be downloaded. Please try again.';}
    });
    const viewPhoto = record => {
        photoReady = false; photoDownload.disabled = true; photoStatus.textContent = 'Loading photo...'; zoomPhoto(1);
        photoName = String(record.student_code || record.name_en || 'student-photo').replace(/[^\p{L}\p{N}_-]+/gu, '-');
        photoViewer.querySelector('.modal-title').textContent = record.name_kh || record.name_en || 'Student Photo';
        photoImage.alt = record.name_kh || record.name_en || 'Student photo'; photoImage.src = record.photo_url;
        Modal.getOrCreateInstance(photoViewer).show();
    };
    return viewPhoto;
}
