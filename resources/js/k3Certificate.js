import { initK3CertificateEditor } from './k3CertificateEditor';
import { showAlert } from './helpers/sweet-alert2';

function applyFieldStyle(field) {
    field.style.left = `${field.dataset.k3X}%`;
    field.style.top = `${field.dataset.k3Y}%`;
    field.style.width = `${field.dataset.k3Width}%`;
    if (!field.dataset.k3FontKey) {
        if (field.dataset.k3Height) field.style.height = `${field.dataset.k3Height}%`;
        return;
    }
    field.style.fontFamily = field.dataset.k3FontFamily;
    field.style.fontSize = `${field.dataset.k3FontSize}pt`;
    field.style.color = field.dataset.k3FontColor;
    field.style.textAlign = field.dataset.k3Align;
    field.style.fontWeight = field.dataset.k3Bold === '1' ? 'bold' : 'normal';
}

function fitText(root = document) {
    const context = document.createElement('canvas').getContext('2d');
    if (!context) return;
    root.querySelectorAll('[data-k3-font-key]').forEach((field) => {
        if (field.isContentEditable) return;
        const size = Number(field.dataset.k3FontSize);
        field.style.fontSize = `${size}pt`;
        const style = getComputedStyle(field);
        // Measure the unscaled printed field, using the widest line.
        const lines = [...field.querySelectorAll('.k3-certificate-line')];
        const width = Math.max(0,...lines.map((line) => {
            const lineStyle = getComputedStyle(line);
            context.font = `${lineStyle.fontStyle} ${lineStyle.fontWeight} ${style.fontSize} ${style.fontFamily}`;
            return context.measureText(line.textContent).width;
        }));
        const available = field.clientWidth - 4;
        if (available > 0 && width > available) field.style.fontSize = `${size * available / width}pt`;
    });
}

async function loadFontsAndFit(root = document) {
    const families = new Set([...root.querySelectorAll('[data-k3-font-key]')].map((field) => field.dataset.k3FontFamily));
    // A failed font request should retain the browser's fallback font and print action.
    await Promise.allSettled([...families].map((family) => document.fonts.load(`12px ${family}`)));
    fitText(root);
}

function initK3Certificate() {
    const filterForm = document.querySelector('.report-filter-form-k3-certificate-wis');
    const qrToggle = document.querySelector('[data-k3-qr-toggle]');
    const syncQrPreview = () => {
        if (!qrToggle) return;
        document.querySelectorAll('[data-k3-certificate-preview] [data-k3-layout-key="qr"]').forEach((node) => {
            node.hidden = !qrToggle.checked;
        });
    };
    syncQrPreview();
    qrToggle?.addEventListener('change', () => {
        const value = qrToggle.checked ? '1' : '0';
        syncQrPreview();
        filterForm.querySelector('[data-k3-qr-value]').value = value;
        document.querySelectorAll('.k3-certificate-settings, [data-k3-template-editor]').forEach((form) => {
            const input = form.querySelector('[name="certificate_show_qr"]');
            if (input) input.value = value;
        });
        const withQr = (address) => {
            const url = new URL(address, window.location.href);
            url.searchParams.set('certificate_show_qr', value);
            return url.toString();
        };
        window.history.replaceState(null, '', withQr(window.location.href));
        document.querySelectorAll('.premium-pagination a[href]').forEach((link) => {
            if (link.getAttribute('href') !== '#') link.href = withQr(link.href);
        });
        document.querySelectorAll('[data-preview-page-size] option[data-url]').forEach((option) => { option.dataset.url = withQr(option.dataset.url); });
        document.querySelectorAll('[data-preview-goto-url]').forEach((input) => { input.dataset.previewGotoUrl = withQr(input.dataset.previewGotoUrl); });
    });
    const feedback = document.querySelector('[data-k3-action-feedback="error"]') || document.querySelector('[data-k3-action-feedback]');
    if (feedback) {
        showAlert({ type: feedback.dataset.k3ActionFeedback, title: feedback.dataset.k3ActionTitle, message: feedback.textContent.trim() });
        document.querySelectorAll('[data-k3-action-feedback]').forEach((node) => { node.hidden = true; });
    }

    document.querySelectorAll('.k3-certificate-settings, [data-k3-template-editor]').forEach((form) => {
        let showingValidation = false;
        const notifyInvalid = (event) => {
            if (showingValidation) return;
            showingValidation = true;
            showAlert({ type: 'error', title: 'Check Required Fields', message: event.target.validationMessage })
                .finally(() => { showingValidation = false; });
        };
        // Settings inputs share the filter row and belong to this form via form="".
        [...form.elements].forEach((field) => field.addEventListener('invalid', notifyInvalid));
    });

    document.querySelectorAll('[data-k3-layout-key]').forEach(applyFieldStyle);
    document.querySelectorAll('.k3-screen-preview').forEach((preview) => {
        const page = preview.querySelector('.k3-certificate-page');
        if (!page) return;
        const fitPage = () => { page.style.transform = `scale(${preview.clientWidth / page.offsetWidth})`; };
        new ResizeObserver(fitPage).observe(preview);
        fitPage();
    });

    initK3CertificateEditor({ applyFieldStyle, loadFontsAndFit });

    document.querySelectorAll('[data-k3-print]').forEach((button) => {
        button.addEventListener('click', async () => {
            await loadFontsAndFit();
            window.print();
        });
    });
    document.querySelectorAll('[data-k3-close]').forEach((button) => {
        button.addEventListener('click', () => window.close());
    });
    loadFontsAndFit();
    window.addEventListener('beforeprint', () => fitText());
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initK3Certificate);
else initK3Certificate();
