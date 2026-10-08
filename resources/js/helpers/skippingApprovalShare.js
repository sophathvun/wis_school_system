import Swal from 'sweetalert2';

const apps = { telegram: 'Telegram', whatsapp: 'WhatsApp', messenger: 'Facebook Messenger' };

export function initSkippingApprovalShare(root) {
    root.querySelectorAll('[data-skipping-share-approval]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            let frame, imageUrl, file, closed = false, loadTimer, cancelLoad;
            const cleanup = () => {
                closed = true;
                clearTimeout(loadTimer);
                cancelLoad?.();
                frame?.remove();
                if (imageUrl) URL.revokeObjectURL(imageUrl);
            };
            Swal.fire({
                title: 'Share Approval Image',
                customClass: { popup: 'skipping-share-popup' },
                html: `<p class="skipping-share-status" role="status">Preparing the A4 approval image…</p>
                    <img class="skipping-share-preview" alt="A4 approval form preview" hidden>
                    <p class="skipping-share-help" hidden></p>
                    <div class="skipping-share-options">
                        <button type="button" class="btn btn-outline-primary" data-share-app="telegram" disabled><i class="ti ti-brand-telegram me-1"></i>Telegram</button>
                        <button type="button" class="btn btn-outline-primary" data-share-app="whatsapp" disabled><i class="ti ti-brand-whatsapp me-1"></i>WhatsApp</button>
                        <button type="button" class="btn btn-outline-primary" data-share-app="messenger" disabled><i class="ti ti-brand-messenger me-1"></i>Facebook Messenger</button>
                    </div>
                    <button type="button" class="btn btn-outline-primary skipping-share-copy" hidden><i class="ti ti-copy me-1"></i>Copy Image</button>
                    <a class="btn btn-outline-secondary skipping-share-download" hidden><i class="ti ti-download me-1"></i>Download A4 Image</a>`,
                showConfirmButton: false, showCloseButton: true,
                didClose: cleanup,
                didOpen: async (popup) => {
                    const status = popup.querySelector('.skipping-share-status');
                    const help = popup.querySelector('.skipping-share-help');
                    const download = popup.querySelector('.skipping-share-download');
                    const copy = popup.querySelector('.skipping-share-copy');
                    const buttons = [...popup.querySelectorAll('[data-share-app]')];
                    const canCopyImage = Boolean(navigator.clipboard?.write && typeof ClipboardItem !== 'undefined');
                    const pasteShortcut = /Mac/i.test(navigator.platform) ? 'Command+V' : 'Ctrl+V';
                    const busy = (value) => { copy.disabled = value; buttons.forEach((button) => { button.disabled = value; }); };
                    const copyImage = async (app) => {
                        if (!file) return;
                        busy(true);
                        try {
                            await navigator.clipboard.write([new ClipboardItem({ 'image/png': file })]);
                            if (!closed) status.textContent = `Image copied. Open ${app || 'your messaging app'}, select a chat, and press ${pasteShortcut} to attach the image.`;
                        } catch {
                            if (!closed) status.textContent = 'Your browser could not copy the image. Allow clipboard access and try again, or download the image and attach it in your chat.';
                        } finally { if (!closed) busy(false); }
                    };
                    copy.addEventListener('click', () => copyImage());
                    buttons.forEach((button) => button.addEventListener('click', async () => {
                        if (!file) return;
                        const app = apps[button.dataset.shareApp];
                        if (!navigator.share || !navigator.canShare?.({ files: [file] })) {
                            if (canCopyImage) await copyImage(app);
                            else status.textContent = `Download the A4 image, then attach it in ${app}.`;
                            return;
                        }
                        status.textContent = `Choose ${app} in your device’s share menu. If it is not listed, use Copy Image or Download A4 Image.`;
                        busy(true);
                        try {
                            // The file is ready before this click so sharing retains user activation.
                            await navigator.share({ files: [file], title: 'Grade Skipping Approval' });
                        } catch (error) {
                            if (!closed && error.name !== 'AbortError') status.textContent = 'Your device could not share the image. Use Copy Image or Download A4 Image and attach it in your chat.';
                        } finally { if (!closed) busy(false); }
                    }));
                    try {
                        const source = new URL(trigger.dataset.skippingShareApproval, location.href);
                        if (source.origin !== location.origin) throw new Error('Unable to load the approval form.');
                        frame = document.createElement('iframe');
                        frame.className = 'skipping-share-render-frame';
                        frame.title = 'Approval image renderer';
                        frame.setAttribute('aria-hidden', 'true');
                        frame.tabIndex = -1;
                        await new Promise((resolve, reject) => {
                            cancelLoad = () => reject(new Error('Sharing closed.'));
                            loadTimer = setTimeout(() => reject(new Error('The approval form took too long to load. Please try again.')), 30000);
                            frame.addEventListener('load', () => { clearTimeout(loadTimer); resolve(); }, { once: true });
                            frame.addEventListener('error', () => { clearTimeout(loadTimer); reject(new Error('Unable to load the approval form.')); }, { once: true });
                            frame.src = source.href;
                            document.body.append(frame);
                        });
                        if (closed) return;
                        const createImage = frame.contentWindow.createSkippingApprovalImage;
                        if (!createImage) throw new Error('Unable to load the approval form. Check your permission or sign in again.');
                        const result = await createImage();
                        if (closed) return;
                        file = new File([result.blob], result.filename, { type: 'image/png' });
                        imageUrl = URL.createObjectURL(file);
                        const preview = popup.querySelector('.skipping-share-preview');
                        preview.src = imageUrl; preview.hidden = false;
                        download.href = imageUrl; download.download = file.name; download.hidden = false;
                        copy.hidden = !canCopyImage;
                        help.textContent = navigator.share && navigator.canShare?.({ files: [file] })
                            ? 'Share the A4 PNG image through your device’s share menu. If your app is not listed, copy or download the image and attach it in the app.'
                            : canCopyImage
                                ? `Copy the A4 image, then open your chat and press ${pasteShortcut} to attach it. You can also download the image.`
                                : 'Download the A4 image and attach it in Telegram, WhatsApp, or Messenger.';
                        help.hidden = false;
                        status.textContent = 'A4 image ready · 210 × 297 mm';
                        busy(false);
                    } catch (error) {
                        if (!closed) status.textContent = error.message || 'Unable to prepare the approval image. Please try again.';
                    } finally {
                        frame?.remove();
                    }
                },
            });
        });
    });
}
