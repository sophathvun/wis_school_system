import Swal from 'sweetalert2';
import { withPngResolution } from './pngResolution';
import { skippingReportUrl } from './skippingReportUrl';

export async function createSkippingStatisticsImage() {
    const paper = document.querySelector('.skipping-statistics-paper');
    if (!paper) throw new Error('Unable to load the statistics report. Check your permission or sign in again.');
    await document.fonts.ready;
    await Promise.all([...paper.querySelectorAll('img')].map((image) => image.decode()));
    const { toCanvas } = await import('html-to-image');
    const bounds = paper.getBoundingClientRect();
    const height = Math.max(bounds.height, paper.scrollHeight);
    const source = await toCanvas(paper, {
        width: bounds.width, height, pixelRatio: 1,
        canvasWidth: 3508, canvasHeight: Math.ceil(height / bounds.width * 3508),
        backgroundColor: '#fff', style: { margin: '0', boxShadow: 'none' },
    });
    const canvas = document.createElement('canvas');
    canvas.width = 3508; canvas.height = 2480;
    const context = canvas.getContext('2d');
    context.fillStyle = '#fff'; context.fillRect(0, 0, canvas.width, canvas.height);
    const scale = Math.min(canvas.width / source.width, canvas.height / source.height);
    context.drawImage(source, (canvas.width - source.width * scale) / 2, 0, source.width * scale, source.height * scale);
    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
    if (!blob) throw new Error('Unable to create the image. Please try again.');
    return { blob: await withPngResolution(blob, 300), filename: document.body.dataset.statisticsFilename };
}

export function initSkippingStatisticsShare(root = document) {
    const trigger = root.querySelector('[data-skipping-share-statistics]');
    trigger?.addEventListener('click', () => {
        let imageUrl, file, frame, loadTimer, cancelLoad, closed = false;
        Swal.fire({
            title: 'Share Grade Skipping Statistics',
            customClass: { popup: 'skipping-statistics-share-popup' },
            html: `<p data-statistics-share-status role="status">Preparing the A4 landscape image…</p>
                <img class="skipping-statistics-share-preview" alt="A4 grade skipping statistics" hidden>
                <div class="skipping-statistics-share-actions">
                    <button type="button" data-statistics-share-app="Telegram" disabled>Telegram</button>
                    <button type="button" data-statistics-share-app="WhatsApp" disabled>WhatsApp</button>
                    <button type="button" data-statistics-share-app="Facebook Messenger" disabled>Facebook Messenger</button>
                    <button type="button" data-statistics-copy disabled>Copy Image</button>
                    <a data-statistics-download hidden>Download A4 Image</a>
                </div>
                <p class="skipping-statistics-share-help">Choose an installed app in your device’s share menu, or copy/download the image and attach it in a chat.</p>`,
            showConfirmButton: false, showCloseButton: true,
            didClose: () => {
                closed = true;
                clearTimeout(loadTimer);
                cancelLoad?.();
                frame?.remove();
                if (imageUrl) URL.revokeObjectURL(imageUrl);
            },
            didOpen: async (popup) => {
                const status = popup.querySelector('[data-statistics-share-status]');
                const copy = popup.querySelector('[data-statistics-copy]');
                const apps = [...popup.querySelectorAll('[data-statistics-share-app]')];
                const download = popup.querySelector('[data-statistics-download]');
                const canCopy = Boolean(navigator.clipboard?.write && typeof ClipboardItem !== 'undefined');
                const busy = (value) => { apps.forEach((button) => { button.disabled = value; }); copy.disabled = value || !canCopy; };
                const copyImage = async (app) => {
                    if (!file) return;
                    busy(true);
                    try {
                        await navigator.clipboard.write([new ClipboardItem({ 'image/png': file })]);
                        status.textContent = app ? `Image copied. Open ${app} and paste it into your chat.` : 'Image copied. Paste it into your chat.';
                    } catch {
                        status.textContent = 'Copying is unavailable or was denied. Use Download A4 Image, then attach the file in your chat.';
                    } finally { if (!closed) busy(false); }
                };
                copy.addEventListener('click', () => copyImage());
                apps.forEach((button) => button.addEventListener('click', async () => {
                    if (!file) return;
                    const app = button.dataset.statisticsShareApp;
                    if (!navigator.share || !navigator.canShare?.({ files: [file] })) {
                        if (canCopy) await copyImage(app);
                        else status.textContent = `Download the A4 image, then attach it in ${app}.`;
                        return;
                    }
                    busy(true);
                    status.textContent = `Choose ${app} in your device’s share menu.`;
                    try {
                        // The file is prepared before this click to retain user activation.
                        await navigator.share({ files: [file], title: 'Grade Skipping Statistics' });
                        status.textContent = 'Image handed to your device’s share menu.';
                    } catch (error) {
                        status.textContent = error.name === 'AbortError' ? 'Sharing cancelled. You can choose another option.' : 'Sharing is unavailable. Use Copy Image or Download A4 Image.';
                    } finally { if (!closed) busy(false); }
                }));
                try {
                    let result;
                    if (trigger.dataset.skippingShareStatistics) {
                        const filters = root.querySelector('[data-skipping-list-filters]');
                        const source = skippingReportUrl(trigger.dataset.skippingShareStatistics, filters ? new FormData(filters) : null, true);
                        if (source.origin !== location.origin) throw new Error('Unable to load the statistics report.');
                        frame = document.createElement('iframe');
                        frame.className = 'skipping-statistics-render-frame';
                        frame.title = 'Statistics image renderer';
                        frame.setAttribute('aria-hidden', 'true');
                        frame.tabIndex = -1;
                        await new Promise((resolve, reject) => {
                            cancelLoad = () => reject(new Error('Sharing closed.'));
                            loadTimer = setTimeout(() => reject(new Error('The statistics report took too long to load. Please try again.')), 30000);
                            frame.addEventListener('load', () => { clearTimeout(loadTimer); resolve(); }, { once: true });
                            frame.addEventListener('error', () => { clearTimeout(loadTimer); reject(new Error('Unable to load the statistics report.')); }, { once: true });
                            frame.src = source.href;
                            document.body.append(frame);
                        });
                        cancelLoad = undefined;
                        if (closed) return;
                        const createImage = frame.contentWindow.createSkippingStatisticsImage;
                        if (!createImage) throw new Error('Unable to load the statistics report. Check your permission or sign in again.');
                        result = await createImage();
                    } else {
                        result = await createSkippingStatisticsImage();
                    }
                    if (closed) return;
                    file = new File([result.blob], result.filename, { type: 'image/png' });
                    imageUrl = URL.createObjectURL(file);
                    const preview = popup.querySelector('.skipping-statistics-share-preview');
                    preview.src = imageUrl; preview.hidden = false;
                    download.href = imageUrl; download.download = file.name; download.hidden = false;
                    busy(false);
                    status.textContent = 'A4 landscape image ready (300 DPI).';
                } catch (error) {
                    if (!closed) status.textContent = error.message || 'Unable to prepare the image. Reload the preview and try again.';
                } finally {
                    clearTimeout(loadTimer);
                    cancelLoad = undefined;
                    frame?.remove();
                }
            },
        });
    });
}
