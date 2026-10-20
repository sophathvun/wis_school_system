import { Modal } from 'bootstrap';
import { studentPhotoJpeg } from './studentPhotoEditor';

export function setupStaffDirectoryPhotoViewer() {
    const element = document.getElementById('staffDirectoryPhotoModal');
    if (!element || element.dataset.viewerReady) return;
    element.dataset.viewerReady = '1';
    const get = selector => element.querySelector(selector);
    const modal = Modal.getOrCreateInstance(element), image = get('[data-photo-image]');
    const stage = get('[data-photo-stage]'), zoom = get('[data-photo-zoom]');
    const download = get('[data-photo-download]'), error = get('[data-photo-error]'), status = get('[data-photo-status]');
    let jpegUrl, generation = 0, fitWidth = 0;
    const setDownload = ready => {
        download.classList.toggle('disabled', !ready);
        download.setAttribute('aria-disabled', String(!ready));
        download.tabIndex = ready ? 0 : -1;
        if (!ready) download.removeAttribute('href');
    };
    const renderZoom = () => {
        if (fitWidth) image.style.width = `${fitWidth * Number(zoom.value)}px`;
        get('[data-photo-percent]').textContent = `${Math.round(Number(zoom.value) * 100)}%`;
        get('[data-photo-out]').disabled = Number(zoom.value) <= 1;
        get('[data-photo-in]').disabled = Number(zoom.value) >= 3;
    };
    const fit = () => {
        if (!image.naturalWidth || !stage.clientWidth) return;
        const ratio = image.naturalWidth / image.naturalHeight;
        fitWidth = Math.max(1, Math.min(stage.clientWidth - 32, (stage.clientHeight - 32) * ratio));
        renderZoom();
    };
    const reset = () => { zoom.value = '1'; fit(); stage.scrollTo(0, 0); };
    image.addEventListener('load', fit);
    element.addEventListener('shown.bs.modal', fit);
    window.addEventListener('resize', fit);
    if (typeof ResizeObserver !== 'undefined') new ResizeObserver(fit).observe(stage);
    zoom.addEventListener('input', renderZoom);
    get('[data-photo-reset]').addEventListener('click', reset);
    for (const [selector, delta] of [['[data-photo-in]', .1], ['[data-photo-out]', -.1]]) {
        get(selector).addEventListener('click', () => { zoom.value = String(Math.max(1, Math.min(3, Number(zoom.value) + delta))); renderZoom(); });
    }
    download.addEventListener('click', event => { if (!jpegUrl) event.preventDefault(); });
    document.addEventListener('click', async event => {
        const trigger = event.target.closest('[data-staff-directory-photo]');
        if (!trigger) return;
        event.preventDefault(); event.stopPropagation();
        const token = ++generation, title = trigger.dataset.staffPhotoTitle || 'Staff Photo';
        if (jpegUrl) URL.revokeObjectURL(jpegUrl);
        jpegUrl = null; setDownload(false); error.classList.add('d-none');
        get('#staffDirectoryPhotoTitle').textContent = title;
        image.alt = `Photo of ${title}`; image.hidden = true; image.removeAttribute('src');
        fitWidth = 0; zoom.value = '1'; renderZoom();
        status.textContent = 'Loading photo and preparing JPG…'; modal.show();
        try {
            const blob = await studentPhotoJpeg(trigger.dataset.staffDirectoryPhoto);
            if (token !== generation) return;
            jpegUrl = URL.createObjectURL(blob);
            image.src = jpegUrl; image.hidden = false;
            await image.decode();
            if (token !== generation) return;
            fit();
            download.href = jpegUrl;
            download.download = `${title.trim().replace(/[^\p{L}\p{N}]+/gu, '-').replace(/^-+|-+$/g, '') || 'staff-photo'}.jpg`;
            setDownload(true); status.textContent = 'Use the zoom controls to enlarge the photo. Scroll to view it when zoomed in.';
            stage.scrollTo(0, 0);
        } catch {
            if (token !== generation) return;
            status.textContent = ''; error.textContent = 'The photo could not be loaded. Close this window and try again.';
            error.classList.remove('d-none');
        }
    });
    element.addEventListener('hidden.bs.modal', () => {
        generation++;
        if (jpegUrl) URL.revokeObjectURL(jpegUrl);
        jpegUrl = null; image.hidden = true; image.removeAttribute('src'); setDownload(false);
    });
}
