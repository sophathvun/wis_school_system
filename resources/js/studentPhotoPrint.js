const printButton = document.querySelector('[data-student-photo-print]');
const status = document.querySelector('[data-student-photo-status]');
const photos = [...document.querySelectorAll('.student-photo-print-image')];

async function preparePhotos() {
    await Promise.all(photos.map((photo) => new Promise((resolve) => {
        if (photo.complete) return resolve();
        photo.addEventListener('load', resolve, { once: true });
        photo.addEventListener('error', resolve, { once: true });
    })));
    const failed = photos.filter((photo) => !photo.naturalWidth).length;
    if (failed) {
        status.textContent = `${failed} photo(s) could not load. Reload this page before printing.`;
        printButton.textContent = 'Photos unavailable';
        return;
    }
    printButton.textContent = 'Print';
    printButton.disabled = photos.length === 0;
}

printButton?.addEventListener('click', () => window.print());
document.querySelector('[data-student-photo-close]')?.addEventListener('click', () => window.close());
if (printButton) preparePhotos();
