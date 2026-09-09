document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;
    if (body.dataset.pdfPrintMode !== '1') return;

    if (body.dataset.pdfClearTitle === '1') {
        document.title = '';
    }

    const setOrientation = (orientation = 'portrait') => {
        const normalized = orientation === 'landscape' ? 'landscape' : 'portrait';
        let style = document.getElementById('print-orientation-style');
        if (!style) {
            style = document.createElement('style');
            style.id = 'print-orientation-style';
            document.head.appendChild(style);
        }
        style.textContent = `@page { size: A4 ${normalized}; margin: 14mm; }`;
        body.dataset.printOrientation = normalized;
    };

    const triggerPrint = (orientation = body.dataset.defaultPrintOrientation || 'portrait') => {
        setOrientation(orientation);
        const run = () => window.setTimeout(() => window.print(), 150);
        if (document.fonts?.ready) {
            document.fonts.ready.then(run).catch(run);
        } else {
            run();
        }
    };

    document.querySelectorAll('[data-print-orientation]').forEach((button) => {
        button.addEventListener('click', () => triggerPrint(button.dataset.printOrientation));
    });

    if (body.dataset.pdfAutoPrint !== '0') {
        window.addEventListener('load', () => triggerPrint(), { once: true });
    } else {
        window.addEventListener('load', () => setOrientation(body.dataset.defaultPrintOrientation || 'portrait'), { once: true });
    }

    window.addEventListener('afterprint', () => {
        const redirectUrl = body.dataset.pdfRedirectUrl || '';
        if (body.dataset.pdfAutoPrint === '0') return;
        if (redirectUrl) {
            window.location.replace(redirectUrl);
            return;
        }
        window.close();
    });
});
