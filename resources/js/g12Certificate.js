import { initG12CertificateEditor } from './g12CertificateEditor';
import { showAlert } from './helpers/sweet-alert2';
import { curveTitle } from './g12CertificateTitle';

function applyFieldStyle(field) {
    field.style.left = `${field.dataset.g12X}%`;
    field.style.top = `${field.dataset.g12Y}%`;
    field.style.width = `${field.dataset.g12Width}%`;
    if (!field.dataset.g12FontKey) {
        if (field.dataset.g12Height) field.style.height = `${field.dataset.g12Height}%`;
        return;
    }
    field.style.fontFamily = field.dataset.g12FontFamily;
    field.style.fontSize = `${field.dataset.g12FontSize}pt`;
    field.style.color = field.dataset.g12FontColor;
    field.style.textAlign = field.dataset.g12Align;
    field.style.fontWeight = field.dataset.g12Bold === '1' ? 'bold' : 'normal';
}

function fitText(root = document) {
    const context = document.createElement('canvas').getContext('2d');
    if (!context) return;
    root.querySelectorAll('[data-g12-font-key]').forEach((field) => {
        if (field.isContentEditable) return;
        const size = Number(field.dataset.g12FontSize);
        field.style.fontSize = `${size}pt`;
        const style = getComputedStyle(field);
        // Measure the unscaled printed field, using the widest line.
        const lines = [...field.querySelectorAll('.g12-certificate-line')];
        const width = Math.max(0,...lines.map((line) => {
            const lineStyle = getComputedStyle(line);
            context.font = `${lineStyle.fontStyle} ${lineStyle.fontWeight} ${style.fontSize} ${style.fontFamily}`;
            return context.measureText(line.textContent).width;
        }));
        const available = field.clientWidth - 4;
        if (available > 0 && width > available) field.style.fontSize = `${size * available / width}pt`;
        if (field.dataset.g12FontKey === 'title') curveTitle(field, context);
    });
}

async function loadFontsAndFit(root = document) {
    const families = new Set([...root.querySelectorAll('[data-g12-font-key]')].map((field) => field.dataset.g12FontFamily));
    // A failed font request should retain the browser's fallback font and print action.
    await Promise.allSettled([...families].map((family) => document.fonts.load(`12px ${family}`)));
    fitText(root);
}

function initG12Certificate() {
    const filterForm = document.querySelector('.report-filter-form-g12-certificate-wis');
    const qrToggle = document.querySelector('[data-g12-qr-toggle]');
    const syncQrPreview = () => {
        if (!qrToggle) return;
        document.querySelectorAll('[data-g12-certificate-preview] [data-g12-layout-key="qr"]').forEach((node) => {
            node.hidden = !qrToggle.checked;
        });
    };
    syncQrPreview();
    qrToggle?.addEventListener('change', () => {
        const value = qrToggle.checked ? '1' : '0';
        syncQrPreview();
        filterForm.querySelector('[data-g12-qr-value]').value = value;
        document.querySelectorAll('.g12-certificate-settings, [data-g12-template-editor]').forEach((form) => {
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
    filterForm?.addEventListener('change', (event) => {
        const id = event.target.id;
        if (['reportAcademicYearValue', 'reportCampusValue', 'reportGradeClassValue'].includes(id)) {
            filterForm.querySelector('#reportCertificateStudentValue').value = '';
        }
        if (id === 'reportAcademicYearValue') filterForm.querySelector('#reportCampusValue').value = '';
        if (['reportAcademicYearValue', 'reportCampusValue'].includes(id)) filterForm.querySelector('#reportGradeClassValue').value = '';
    });
    const feedback = document.querySelector('[data-g12-action-feedback="error"]') || document.querySelector('[data-g12-action-feedback]');
    if (feedback) {
        showAlert({ type: feedback.dataset.g12ActionFeedback, title: feedback.dataset.g12ActionTitle, message: feedback.textContent.trim() });
        document.querySelectorAll('[data-g12-action-feedback]').forEach((node) => { node.hidden = true; });
    }

    document.querySelectorAll('.g12-certificate-settings, [data-g12-template-editor]').forEach((form) => {
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

    document.querySelectorAll('[data-g12-layout-key]').forEach(applyFieldStyle);
    document.querySelectorAll('.g12-screen-preview').forEach((preview) => {
        const page = preview.querySelector('.g12-certificate-page');
        if (!page) return;
        const fitPage = () => { page.style.transform = `scale(${preview.clientWidth / page.offsetWidth})`; };
        new ResizeObserver(fitPage).observe(preview);
        fitPage();
    });

    initG12CertificateEditor({ applyFieldStyle, loadFontsAndFit });

    document.querySelectorAll('[data-g12-print]').forEach((button) => {
        button.addEventListener('click', async () => {
            await loadFontsAndFit();
            window.print();
        });
    });
    document.querySelectorAll('[data-g12-close]').forEach((button) => {
        button.addEventListener('click', () => window.close());
    });
    loadFontsAndFit();
    window.addEventListener('beforeprint', () => fitText());
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initG12Certificate);
else initG12Certificate();
