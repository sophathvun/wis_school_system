const PAGE = { width: 54, height: 90 };
const clone = value => JSON.parse(JSON.stringify(value));

export function initStudentIdCardEditor() {
    const cards = [...document.querySelectorAll('[data-id-card]')];
    const page = document.querySelector('[data-id-editor-preview] [data-id-card]');
    const form = document.querySelector('[data-id-template-editor]');
    const scaleCard = card => {
        if (card.clientWidth) card.style.setProperty('--id-card-scale', card.clientWidth / (PAGE.width * 96 / 25.4));
    };
    const observer = new ResizeObserver(entries => entries.forEach(entry => scaleCard(entry.target)));
    cards.forEach(card => { scaleCard(card); observer.observe(card); });
    document.querySelectorAll('[data-id-print]').forEach(button => button.addEventListener('click', event => {
        if (form?.dataset.dirty === 'true') {
            event.preventDefault();
            form.querySelector('[data-id-status]').textContent = 'Save Template before printing your changes.';
            form.querySelector('[data-id-save]').focus();
            return;
        }
        if (!button.hasAttribute('href')) window.print();
    }));
    if (!form || !page) return;

    let layout = JSON.parse(form.dataset.idLayout);
    const original = clone(layout), defaults = JSON.parse(form.dataset.idDefaultLayout);
    const controls = [...form.querySelectorAll('[data-id-property]')];
    const fonts = new Map([...form.querySelector('[data-id-property="font"]').options].map(option => [option.value, option.dataset.fontFamily]));
    const picker = form.querySelector('[data-id-select-block]');
    const status = form.querySelector('[data-id-status]');
    const save = form.querySelector('[data-id-save]');
    const discard = form.querySelector('[data-id-discard]');
    const qrToggle = form.querySelector('[data-id-show-qr]');
    const assets = new Map(cards.map(card => [card, JSON.parse(card.dataset.idAssets)]));
    const values = new Map(cards.map(card => [card, JSON.parse(card.dataset.idValues)]));
    let selected = Object.keys(layout.fields)[0] ?? null, editing = false, textEditing = null, drag = null, submitting = false;

    function markDirty() {
        form.dataset.dirty = 'true';
        save.disabled = discard.disabled = false;
        status.textContent = 'Unsaved changes — Save Template to use them for printing and future cards.';
    }
    function clamp(field) {
        const round = value => Math.round(value * 10) / 10;
        field.width = round(Math.max(1, Math.min(PAGE.width, field.width)));
        field.height = field.type === 'qr' ? field.width : round(Math.max(1, Math.min(PAGE.height, field.height)));
        field.x = round(Math.max(0, Math.min(PAGE.width - field.width, field.x)));
        field.y = round(Math.max(0, Math.min(PAGE.height - field.height, field.y)));
    }
    const qrVisible = snapshot => Object.values(snapshot.fields).some(field => field.type === 'qr' && field.visible);
    function renderField(card, key, field, snapshot, editable) {
        let node = [...card.querySelectorAll('[data-id-field]')].find(node => node.dataset.idField === key);
        if (!node) {
            node = document.createElement('div');
            node.dataset.idField = key;
            card.append(node);
        }
        const active = editable && card === page;
        const visible = field.visible && (key !== 'scan' || qrVisible(snapshot));
        node.className = `student-id-template-field student-id-template-${field.type}`;
        node.classList.toggle('student-id-block-selected', active && key === selected);
        node.classList.toggle('student-id-editor-hidden', active && !visible);
        node.hidden = !visible && !active;
        node.tabIndex = active ? 0 : -1;
        node.style.cssText = `left:${field.x / PAGE.width * 100}%;top:${field.y / PAGE.height * 100}%;width:${field.width / PAGE.width * 100}%;height:${field.height / PAGE.height * 100}%;`;
        if (field.type === 'text') {
            node.style.fontFamily = fonts.get(field.font);
            node.style.fontSize = `calc(var(--id-card-scale,1) * ${field.size}pt)`;
            node.style.color = field.color;
            node.style.textAlign = field.align;
            node.style.fontWeight = field.bold ? '700' : '400';
            if (textEditing !== key || card !== page) {
                const span = document.createElement('span');
                span.className = 'student-id-field-text';
                span.textContent = (field.text ?? '').replace(/\{\{([^{}]+)\}\}/g, (token, name) => values.get(card)[name] ?? token);
                node.replaceChildren(span);
            }
        } else if (!node.querySelector('img, .student-id-media-placeholder, .student-id-logo-placeholder')) {
            const src = assets.get(card)[field.type];
            if (src) {
                const image = document.createElement('img');
                image.src = src; image.alt = field.label; image.draggable = false;
                node.replaceChildren(image);
            } else {
                const placeholder = document.createElement('span');
                placeholder.className = field.type === 'logo' ? 'student-id-logo-placeholder' : 'student-id-media-placeholder';
                if (field.type === 'logo') placeholder.textContent = 'Western International School';
                else {
                    const icon = document.createElement('i');
                    icon.className = `ti ti-${field.type === 'qr' ? 'qrcode' : 'user'}`;
                    icon.setAttribute('aria-hidden', 'true'); placeholder.append(icon);
                }
                node.replaceChildren(placeholder);
            }
        }
        node.querySelector('.student-id-editor-resize')?.remove();
        if (active && textEditing !== key) {
            const handle = document.createElement('button');
            handle.type = 'button'; handle.className = 'student-id-editor-resize';
            handle.setAttribute('aria-label', 'Resize selected card block');
            node.append(handle);
        }
    }
    function renderAll(snapshot = layout, editable = editing) {
        cards.forEach(card => {
            card.classList.toggle('student-id-is-editing', editable && card === page);
            card.querySelectorAll('[data-id-field]').forEach(node => {
                if (!Object.hasOwn(snapshot.fields, node.dataset.idField)) node.remove();
            });
            Object.entries(snapshot.fields).forEach(([key, field]) => renderField(card, key, field, snapshot, editable));
            scaleCard(card);
        });
    }
    function syncControls() {
        const field = layout.fields[selected];
        picker.replaceChildren(...Object.entries(layout.fields).map(([key, field]) => {
            const option = document.createElement('option');
            option.value = key; option.textContent = field.label + (field.visible ? '' : ' (hidden)');
            return option;
        }));
        picker.value = selected ?? '';
        form.querySelectorAll('[data-id-text-tool]').forEach(node => { node.hidden = field?.type !== 'text'; });
        controls.forEach(control => {
            const property = control.dataset.idProperty;
            control.disabled = !field || !(property in field) || (property === 'height' && field.type === 'qr');
            if (control.type === 'checkbox') control.checked = !!field?.[property];
            else control.value = field?.[property] ?? '';
        });
        form.querySelector('[data-id-remove]').disabled = !field;
        form.querySelectorAll('[data-id-nudge]').forEach(button => { button.disabled = !field; });
        qrToggle.checked = qrVisible(layout);
    }
    function finishText() {
        if (!textEditing) return;
        const key = textEditing;
        const node = [...page.querySelectorAll('[data-id-field]')].find(node => node.dataset.idField === key);
        const text = node.innerText.replace(/\r\n/g, '\n').slice(0, 1000);
        if (layout.fields[key].text !== text) { layout.fields[key].text = text; markDirty(); }
        textEditing = null; node.contentEditable = 'false';
        renderAll(); syncControls();
    }
    function select(key) {
        if (selected !== key) finishText();
        selected = Object.hasOwn(layout.fields, key) ? key : null;
        page.querySelectorAll('[data-id-field]').forEach(node => node.classList.toggle('student-id-block-selected', editing && node.dataset.idField === selected));
        syncControls();
    }
    function beginText() {
        if (!editing || layout.fields[selected]?.type !== 'text') return;
        finishText(); textEditing = selected;
        const node = [...page.querySelectorAll('[data-id-field]')].find(node => node.dataset.idField === selected);
        node.replaceChildren(document.createTextNode(layout.fields[selected].text));
        node.contentEditable = 'plaintext-only'; node.focus({ preventScroll: true });
        const range = document.createRange(); range.selectNodeContents(node); range.collapse(false);
        const selection = window.getSelection(); selection.removeAllRanges(); selection.addRange(range);
    }
    function toggle(enabled) {
        finishText(); editing = enabled;
        form.querySelector('[data-id-tools]').hidden = !editing;
        const button = form.querySelector('[data-id-edit-toggle]');
        button.textContent = editing ? 'Finish Editing' : 'Edit Template';
        button.setAttribute('aria-pressed', String(editing));
        renderAll(); syncControls();
    }
    function addField(value, label) {
        if (Object.keys(layout.fields).length >= 40) { status.textContent = 'A card can contain up to 40 fields.'; return; }
        let field;
        if (['logo', 'photo', 'qr'].includes(value)) {
            const existing = Object.entries(layout.fields).find(([, field]) => field.type === value);
            if (existing) { existing[1].visible = true; select(existing[0]); renderAll(); markDirty(); return; }
            field = clone(defaults.fields[value]);
        } else {
            field = { type: 'text', label, text: value === 'text' ? 'New text' : '{{' + value + '}}',
                x: 3, y: 50, width: 48, height: 8, font: 'arial', size: 8, color: '#203a5f', align: 'center', bold: false, visible: true };
        }
        let key = value, suffix = 1;
        while (Object.hasOwn(layout.fields, key)) key = value + '_' + suffix++;
        layout.fields[key] = field; selected = key;
        renderAll(); syncControls(); markDirty();
    }
    page.addEventListener('click', event => {
        const node = event.target.closest('[data-id-field]');
        if (editing && node) select(node.dataset.idField);
    });
    page.addEventListener('dblclick', event => {
        const node = event.target.closest('[data-id-field]');
        if (editing && node) { select(node.dataset.idField); beginText(); }
    });
    page.addEventListener('focusout', event => { if (event.target.dataset.idField === textEditing) finishText(); });
    page.addEventListener('input', event => {
        if (!textEditing || event.target.dataset.idField !== textEditing) return;
        layout.fields[textEditing].text = event.target.innerText.replace(/\r\n/g, '\n').slice(0, 1000);
        form.querySelector('[data-id-property="text"]').value = layout.fields[textEditing].text;
        markDirty();
    });
    page.addEventListener('keydown', event => {
        const node = event.target.closest('[data-id-field]');
        if (!editing || !node || node.isContentEditable || !['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) return;
        event.preventDefault(); select(node.dataset.idField);
        const field = layout.fields[selected], axis = ['ArrowLeft', 'ArrowRight'].includes(event.key) ? 'x' : 'y';
        field[axis] += (['ArrowLeft', 'ArrowUp'].includes(event.key) ? -1 : 1) * (event.shiftKey ? 1 : .1);
        clamp(field); renderAll(); syncControls(); markDirty();
        [...page.querySelectorAll('[data-id-field]')].find(node => node.dataset.idField === selected)?.focus({ preventScroll: true });
    });
    page.addEventListener('pointerdown', event => {
        const node = event.target.closest('[data-id-field]');
        if (!editing || !node || node.isContentEditable || event.button !== 0) return;
        select(node.dataset.idField); event.preventDefault(); node.focus({ preventScroll: true });
        drag = { id: event.pointerId, key: selected, x: event.clientX, y: event.clientY,
            start: clone(layout.fields[selected]), rect: page.getBoundingClientRect(), resize: !!event.target.closest('.student-id-editor-resize') };
        node.setPointerCapture(event.pointerId);
    });
    document.addEventListener('pointermove', event => {
        if (!drag || event.pointerId !== drag.id) return;
        const dx = PAGE.width * (event.clientX - drag.x) / drag.rect.width;
        const dy = PAGE.height * (event.clientY - drag.y) / drag.rect.height;
        if (Math.abs(dx) + Math.abs(dy) < .02) return;
        const field = layout.fields[drag.key];
        if (drag.resize) { field.width = drag.start.width + dx; field.height = drag.start.height + dy; }
        else { field.x = drag.start.x + dx; field.y = drag.start.y + dy; }
        clamp(field);
        // Keep the captured handle alive until the pointer is released.
        const node = [...page.querySelectorAll('[data-id-field]')].find(node => node.dataset.idField === drag.key);
        Object.assign(node.style, { left: field.x / PAGE.width * 100 + '%', top: field.y / PAGE.height * 100 + '%',
            width: field.width / PAGE.width * 100 + '%', height: field.height / PAGE.height * 100 + '%' });
        syncControls(); markDirty();
    });
    const endDrag = event => {
        if (!drag || event.pointerId !== drag.id) return;
        drag = null; renderAll(); syncControls();
    };
    document.addEventListener('pointerup', endDrag);
    document.addEventListener('pointercancel', endDrag);
    controls.forEach(control => control.addEventListener('input', () => {
        if (!editing || !selected || !control.checkValidity()) return;
        finishText(); const property = control.dataset.idProperty, field = layout.fields[selected];
        field[property] = control.type === 'checkbox' ? control.checked : control.type === 'number' ? Number(control.value) : control.value;
        clamp(field); renderAll(); markDirty();
        qrToggle.checked = qrVisible(layout);
    }));
    controls.forEach(control => control.addEventListener('change', syncControls));
    picker.addEventListener('change', () => select(picker.value));
    form.querySelector('[data-id-edit-toggle]').addEventListener('click', () => toggle(!editing));
    form.querySelector('[data-id-edit-text]').addEventListener('click', beginText);
    form.querySelectorAll('[data-id-nudge]').forEach(button => button.addEventListener('click', () => {
        if (!editing || !selected) return;
        finishText(); const direction = button.dataset.idNudge, field = layout.fields[selected];
        const axis = ['left', 'right'].includes(direction) ? 'x' : 'y';
        field[axis] += ['left', 'up'].includes(direction) ? -1 : 1;
        clamp(field); renderAll(); syncControls(); markDirty();
    }));
    form.querySelector('[data-id-insert-token]').addEventListener('change', event => {
        const token = event.target.value;
        if (!editing || !token || layout.fields[selected]?.type !== 'text') return;
        finishText(); const input = form.querySelector('[data-id-property="text"]'), field = layout.fields[selected];
        const start = input.selectionStart ?? field.text.length, end = input.selectionEnd ?? start;
        field.text = (field.text.slice(0, start) + '{{' + token + '}}' + field.text.slice(end)).slice(0, 1000);
        event.target.value = ''; renderAll(); syncControls(); markDirty();
    });
    form.querySelector('[data-id-add-field]').addEventListener('change', event => {
        if (!editing || !event.target.value) return;
        finishText(); addField(event.target.value, event.target.selectedOptions[0].textContent.trim()); event.target.value = '';
    });
    form.querySelector('[data-id-remove]').addEventListener('click', () => {
        if (!editing || !selected) return;
        finishText(); delete layout.fields[selected]; selected = Object.keys(layout.fields)[0] ?? null;
        renderAll(); syncControls(); markDirty();
    });
    qrToggle.addEventListener('change', () => {
        if (!editing) return;
        finishText(); const qr = Object.values(layout.fields).find(field => field.type === 'qr');
        if (qr) qr.visible = qrToggle.checked;
        else if (qrToggle.checked) addField('qr', 'QR Code');
        renderAll(); syncControls(); markDirty();
    });
    form.querySelector('[data-id-reset]').addEventListener('click', () => {
        finishText(); layout = clone(defaults); selected = Object.keys(layout.fields)[0]; toggle(true); markDirty();
    });
    discard.addEventListener('click', () => {
        finishText(); layout = clone(original); selected = Object.keys(layout.fields)[0] ?? null;
        renderAll(); syncControls(); form.dataset.dirty = 'false';
        save.disabled = discard.disabled = true; status.textContent = 'Saved template';
    });
    form.addEventListener('submit', event => {
        finishText();
        if (!form.checkValidity()) { event.preventDefault(); form.reportValidity(); return; }
        form.querySelector('[data-id-template-data]').value = JSON.stringify(layout);
        submitting = true;
    });
    window.addEventListener('beforeunload', event => {
        if (form.dataset.dirty === 'true' && !submitting) { event.preventDefault(); event.returnValue = ''; }
    });
    window.addEventListener('beforeprint', () => { finishText(); renderAll(original, false); });
    window.addEventListener('afterprint', () => renderAll());
    renderAll(); syncControls();
}
