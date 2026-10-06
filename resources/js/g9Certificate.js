import { initG9CertificateEditor } from './g9CertificateEditor';
import { showAlert } from './helpers/sweet-alert2';
import { curveTitle } from './g9CertificateTitle';

function applyFieldStyle(field) {
    field.style.left = `${field.dataset.g9X}%`;
    field.style.top = `${field.dataset.g9Y}%`;
    field.style.width = `${field.dataset.g9Width}%`;
    if (!field.dataset.g9FontKey) {
        field.style.height = `${field.dataset.g9Height}%`;
        return;
    }
    field.style.fontFamily = field.dataset.g9FontFamily;
    field.style.fontSize = `${field.dataset.g9FontSize}pt`;
    field.style.color = field.dataset.g9FontColor;
    field.style.textAlign = field.dataset.g9Align;
    field.style.fontWeight = field.dataset.g9Bold === '1' ? 'bold' : 'normal';
}

function fitText(root = document) {
    const context = document.createElement('canvas').getContext('2d');
    if (!context) return;
    root.querySelectorAll('[data-g9-font-key]').forEach((field) => {
        if (field.isContentEditable) return;
        const size = Number(field.dataset.g9FontSize);
        field.style.fontSize = `${size}pt`;
        const style = getComputedStyle(field);
        // Measure the unscaled printed field, using the widest line.
        const lines = [...field.querySelectorAll('.g9-certificate-line')];
        const width = Math.max(0,...lines.map((line) => {
            const lineStyle = getComputedStyle(line);
            context.font = `${lineStyle.fontStyle} ${lineStyle.fontWeight} ${style.fontSize} ${style.fontFamily}`;
            return context.measureText(line.textContent).width;
        }));
        const available = field.clientWidth - 4;
        if (available > 0 && width > available) field.style.fontSize = `${size * available / width}pt`;
        if (field.dataset.g9FontKey === 'title') curveTitle(field, context);
    });
}

async function loadFontsAndFit(root = document) {
    const families = new Set([...root.querySelectorAll('[data-g9-font-key]')].map((field) => field.dataset.g9FontFamily));
    // A failed font request should retain the browser's fallback font and print action.
    await Promise.allSettled([...families].map((family) => document.fonts.load(`12px ${family}`)));
    fitText(root);
}

function initG9Certificate() {
    const filterForm = document.querySelector('.report-filter-form-g9-certificate-wis');
    filterForm?.addEventListener('change', (event) => {
        const id = event.target.id;
        if (['reportAcademicYearValue', 'reportCampusValue', 'reportGradeClassValue'].includes(id)) {
            filterForm.querySelector('#reportCertificateStudentValue').value = '';
        }
        if (id === 'reportAcademicYearValue') filterForm.querySelector('#reportCampusValue').value = '';
        if (['reportAcademicYearValue', 'reportCampusValue'].includes(id)) filterForm.querySelector('#reportGradeClassValue').value = '';
    });
    const feedback = document.querySelector('[data-g9-action-feedback="error"]') || document.querySelector('[data-g9-action-feedback]');
    if (feedback) {
        showAlert({ type: feedback.dataset.g9ActionFeedback, title: feedback.dataset.g9ActionTitle, message: feedback.textContent.trim() });
        document.querySelectorAll('[data-g9-action-feedback]').forEach((node) => { node.hidden = true; });
    }

    document.querySelectorAll('.g9-certificate-settings, [data-g9-template-editor]').forEach((form) => {
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

    document.querySelectorAll('[data-g9-layout-key]').forEach(applyFieldStyle);
    document.querySelectorAll('.g9-screen-preview').forEach((preview) => {
        const page = preview.querySelector('.g9-certificate-page');
        if (!page) return;
        const fitPage = () => { page.style.transform = `scale(${preview.clientWidth / page.offsetWidth})`; };
        new ResizeObserver(fitPage).observe(preview);
        fitPage();
    });

    initG9CertificateEditor({ applyFieldStyle, loadFontsAndFit });

    document.querySelectorAll('[data-g9-print]').forEach((button) => {
        button.addEventListener('click', async () => {
            await loadFontsAndFit();
            window.print();
        });
    });
    document.querySelectorAll('[data-g9-close]').forEach((button) => {
        button.addEventListener('click', () => window.close());
    });
    loadFontsAndFit();
    window.addEventListener('beforeprint', () => fitText());
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initG9Certificate);
else initG9Certificate();
