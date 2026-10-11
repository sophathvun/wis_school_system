export function initSkippingReportPrint(doc = document, win = window) {
    const button = doc.querySelector('[data-skipping-print]');
    if (!button) return;
    const printReport = async () => {
        if (button.disabled) return;
        button.disabled = true;
        try {
            if (doc.readyState !== 'complete') {
                await new Promise(resolve => win.addEventListener('load', resolve, { once: true }));
            }
            await doc.fonts.ready;
            await Promise.all([...doc.images].map(image => image.decode().catch(() => {})));
            await new Promise(resolve => win.requestAnimationFrame(resolve));
            win.print();
        } finally { button.disabled = false; }
    };
    button.addEventListener('click', printReport);
    if (win.self === win.top && new URL(win.location.href).searchParams.get('autoprint') === '1') {
        // The toolbar remains available if the browser declines automatic printing.
        printReport().catch(() => {});
    }
}
