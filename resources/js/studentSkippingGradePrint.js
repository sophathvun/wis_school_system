import { withPngResolution } from './helpers/pngResolution';

const button = document.querySelector('[data-skipping-print]');
button?.addEventListener('click', async () => {
    button.disabled = true;
    try {
        await document.fonts.ready;
        await Promise.all([...document.images].map((image) => image.decode().catch(() => {})));
        window.print();
    } finally { button.disabled = false; }
});
document.querySelector('[data-skipping-close]')?.addEventListener('click', () => window.close());

if (document.body.hasAttribute('data-skipping-image-export')) {
    window.createSkippingApprovalImage = async () => {
        const paper = document.querySelector('.skipping-approval');
        await document.fonts.ready;
        await Promise.all([...paper.querySelectorAll('img')].map((image) => image.decode()));
        const bounds = paper.getBoundingClientRect();
        const pageHeight = bounds.width * 297 / 210;
        const outsidePage = [...paper.querySelectorAll('*')].some((element) => {
            if (!element.getClientRects().length) return false;
            const rect = element.getBoundingClientRect();
            return rect.bottom > bounds.top + pageHeight + 2 || rect.top < bounds.top - 2
                || rect.left < bounds.left - 2 || rect.right > bounds.right + 2;
        });
        if (bounds.height > pageHeight + 2 || outsidePage) {
            throw new Error('The approval template extends beyond one A4 page. Adjust the template layout before sharing.');
        }
        const { toCanvas } = await import('html-to-image');
        const canvas = await toCanvas(paper, {
            width: bounds.width, height: pageHeight,
            canvasWidth: 1654, canvasHeight: 2339, pixelRatio: 1,
            backgroundColor: '#fff', style: { margin: '0' },
        });
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
        if (!blob) throw new Error('Unable to create the approval image. Please try again.');
        return { blob: await withPngResolution(blob, 200), filename: document.body.dataset.approvalFilename };
    };
}

