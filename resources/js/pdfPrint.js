document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;
    if (body.dataset.pdfPrintMode !== '1') return;

    if (body.dataset.pdfClearTitle === '1') {
        document.title = '';
    }

    const triggerPrint = () => {
        const run = () => window.setTimeout(() => window.print(), 150);
        if (document.fonts?.ready) {
            document.fonts.ready.then(run).catch(run);
        } else {
            run();
        }
    };

    window.addEventListener('load', triggerPrint, { once: true });
    window.addEventListener('afterprint', () => {
        const redirectUrl = body.dataset.pdfRedirectUrl || '';
        if (redirectUrl) {
            window.location.replace(redirectUrl);
            return;
        }
        window.close();
    });
});
