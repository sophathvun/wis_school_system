import Swal from 'sweetalert2';
import { showSuccess } from './helpers/sweet-alert2';
import './studentSkippingGrade';

const root = document.querySelector('[data-skipping-editor]');
if (root) {
    const config = JSON.parse(root.querySelector('[data-template-config]').textContent);
    const iframe = root.querySelector('[data-template-preview]');
    const wrap = iframe.parentElement;
    const controls = root.querySelector('[data-template-controls]');
    const inputs = [...root.querySelectorAll('[data-template-control]')];
    const picker = root.querySelector('[data-template-block-select]');
    const status = root.querySelector('[data-template-status]');
    const save = root.querySelector('[data-template-save]');
    const discard = root.querySelector('[data-template-discard]');
    const restore = root.querySelector('[data-template-restore]');
    const content = root.querySelector('[data-template-control="text"]');
    const addButtons = [...root.querySelectorAll('[data-template-add]')];
    const removeButton = root.querySelector('[data-template-remove]');
    const tokenPicker = root.querySelector('[data-template-token]');
    const noFill = root.querySelector('[data-template-no-fill]');
    const checkedControl = root.querySelector('[data-template-checked]');
    const optionLabelControl = root.querySelector('[data-template-option-label]');
    const checkboxSourceControl = root.querySelector('[data-template-checkbox-source]');
    const editorToggle = root.querySelector('[data-template-editor-toggle]');
    const editorPanel = root.querySelector('[data-template-editor-panel]');
    editorToggle.hidden = !config.canEdit;
    let state = { ...config.template };
    let saved = { ...config.template };
    let revision = config.revision;
    let selected = null;
    let nodes = new Map();
    let busy = false;
    let dirty = false;
    let ready = false;
    let editing = false;
    const defaults = new Map();
    const clamp = (value, min, max) => Math.min(max, Math.max(min, value));
    const copy = (value) => structuredClone(value);
    const resolve = (text) => text.replace(/\{([^{}]+)\}/g, (match, key) => Object.hasOwn(config.values, key) ? config.values[key] : match);
    const name = (key) => key.replaceAll('-', ' ').replace(/^./, (letter) => letter.toUpperCase());
    const block = () => state[selected.dataset.skippingBlock] || {};
    const isCustom = (key) => key.startsWith('custom-');
    const isText = (node) => ['text', 'score'].includes(node?.dataset.objectType);
    const label = (node) => `${isCustom(node.dataset.skippingBlock) ? `${name(node.dataset.objectType)}${node.dataset.objectType === 'checkbox' && state[node.dataset.skippingBlock]?.option_label ? `: ${state[node.dataset.skippingBlock].option_label}` : ''} ${node.dataset.skippingBlock.slice(-6)}` : name(node.dataset.skippingBlock)}${node.dataset.removed === 'true' ? ' (Removed)' : ''}`;
    const customDefault = (type) => ({ type, text: type === 'text' ? 'New text' : '', left: 20, top: 20, width: type === 'checkbox' ? 4 : type === 'text' ? 60 : 40, height: type === 'checkbox' ? 4 : type === 'line' ? 0 : type === 'text' ? 14 : 20, font: 'Default', size: 11, color: type === 'checkbox' ? '#206bc4' : '#000000', border_style: type === 'line' ? 'dotted' : ['box', 'checkbox'].includes(type) ? 'solid' : 'none', border_width: 1, border_color: type === 'checkbox' ? '#206bc4' : '#333333', fill: 'transparent', ...(type === 'checkbox' ? { checked: false, option_label: 'New option' } : {}) });

    function refreshPicker() {
        picker.replaceChildren(...[...nodes].map(([key, node]) => new Option(label(node), key)));
        if (selected) picker.value = selected.dataset.skippingBlock;
    }

    function resize() {
        if (!iframe.contentDocument?.querySelector('.skipping-paper')) return;
        const paper = iframe.contentDocument.querySelector('.skipping-paper');
        const width = paper.offsetWidth;
        const height = Math.max(paper.scrollHeight, paper.offsetHeight);
        const padding = getComputedStyle(wrap.parentElement);
        const available = wrap.parentElement.clientWidth - parseFloat(padding.paddingLeft) - parseFloat(padding.paddingRight);
        const scale = Math.min(1, available / width);
        iframe.style.width = `${width}px`;
        iframe.style.height = `${height}px`;
        iframe.style.transform = `scale(${scale})`;
        wrap.style.width = `${width * scale}px`;
        wrap.style.height = `${height * scale}px`;
    }

    function syncNodeEditing(node) {
        node.tabIndex = editing ? 0 : -1;
        if (editing) {
            node.setAttribute('role', 'button');
            node.setAttribute('aria-label', `Edit ${label(node)}`);
        } else {
            node.removeAttribute('role');
            node.removeAttribute('aria-label');
            node.classList.remove('is-selected');
        }
    }

    function setEditing(enabled) {
        if (!config.canEdit || !ready || busy) return;
        editing = enabled;
        editorPanel.hidden = !editing;
        iframe.contentDocument.body.classList.toggle('is-editing', editing);
        editorToggle.setAttribute('aria-expanded', String(editing));
        editorToggle.setAttribute('aria-pressed', String(editing));
        editorToggle.querySelector('[data-template-editor-toggle-label]').textContent = editing ? 'Finish Editing' : 'Edit Report';
        editorToggle.querySelector('i').className = `ti ti-${editing ? 'check' : 'edit'} me-1`;
        nodes.forEach(syncNodeEditing);
        if (!editing) editorToggle.focus();
        if (editing) select(selected || [...nodes.values()].find((node) => node.dataset.removed !== 'true'));
        changed();
    }
    editorToggle.addEventListener('click', () => setEditing(!editing));

    function changed() {
        dirty = JSON.stringify(state) !== JSON.stringify(saved);
        status.textContent = dirty ? 'Unsaved changes' : 'Saved template';
        editorToggle.disabled = !config.canEdit || busy || !ready;
        save.disabled = !editing || busy || !dirty;
        discard.disabled = !editing || busy || !dirty;
        restore.disabled = !editing || busy;
        controls.disabled = !editing || busy || !selected;
        addButtons.forEach((button) => button.disabled = !editing || busy || !ready);
        const customCheckbox = selected?.dataset.objectType === 'checkbox' && isCustom(selected.dataset.skippingBlock);
        const linkedCheckbox = customCheckbox && Boolean(block().checkbox_source);
        checkboxSourceControl.hidden = !customCheckbox;
        checkedControl.hidden = !customCheckbox || linkedCheckbox;
        optionLabelControl.hidden = !customCheckbox || linkedCheckbox;
        inputs.forEach((input) => {
            const field = input.dataset.templateControl;
            input.disabled = !isText(selected) && ['text', 'font', 'size', 'bold'].includes(field)
                || field === 'color' && !isText(selected) && selected?.dataset.objectType !== 'checkbox'
                || ['checked', 'option_label'].includes(field) && (!customCheckbox || linkedCheckbox)
                || field === 'checkbox_source' && !customCheckbox
                || field === 'bullet' && selected?.dataset.objectType !== 'text'
                || selected?.dataset.objectType === 'image' && !['left', 'top', 'width', 'height'].includes(field)
                || selected?.dataset.objectType === 'line' && ['height', 'fill'].includes(field);
        });
        tokenPicker.disabled = !isText(selected);
        noFill.disabled = ['line', 'image'].includes(selected?.dataset.objectType);
        removeButton.disabled = !selected || selected.dataset.removed === 'true';
        requestAnimationFrame(resize);
    }

    function apply(node) {
        const key = node.dataset.skippingBlock;
        const item = state[key] || {};
        const type = node.dataset.objectType;
        const text = item.text ?? config.defaults[key] ?? '';
        const bulleted = type === 'text' && Boolean(item.bullet);
        node.classList.toggle('skipping-template-bulleted', bulleted);
        if (bulleted) {
            node.replaceChildren(...resolve(text).split(/\r\n|\r|\n/).map((line) => {
                const row = node.ownerDocument.createElement('span');
                if (!line.trim()) {
                    row.className = 'skipping-template-bullet-space';
                    row.setAttribute('aria-hidden', 'true');
                    row.textContent = ' ';
                } else {
                    row.className = 'skipping-template-bullet-row';
                    const marker = node.ownerDocument.createElement('span');
                    marker.className = 'skipping-template-bullet-marker';
                    marker.setAttribute('aria-hidden', 'true');
                    marker.textContent = '•';
                    const content = node.ownerDocument.createElement('span');
                    content.textContent = line;
                    row.append(marker, content);
                }
                return row;
            }));
        } else if (type !== 'image') node.textContent = isText(node) ? resolve(text) : '';
        if (['committee-heading', 'decision-heading'].includes(key)) {
            const walker = node.ownerDocument.createTreeWalker(node, 4);
            const textNodes = [];
            while (walker.nextNode()) textNodes.push(walker.currentNode);
            textNodes.forEach((textNode) => {
                const parts = textNode.textContent.split(/([\u1780-\u17FF\u19E0-\u19FF]+)/u);
                if (parts.length === 1) return;
                textNode.replaceWith(...parts.map((part, index) => {
                    if (index % 2 === 0) return node.ownerDocument.createTextNode(part);
                    const khmer = node.ownerDocument.createElement('span');
                    khmer.className = 'approval-committee-kh';
                    khmer.textContent = part;
                    return khmer;
                }));
            });
        }
        node.dataset.templateText = text;
        node.dataset.removed = item.removed ? 'true' : 'false';
        node.classList.toggle('is-removed', Boolean(item.removed));
        if (isCustom(key) && type === 'checkbox') node.classList.toggle('checked', item.checkbox_source ? Boolean(config.checkboxValues[item.checkbox_source]) : Boolean(item.checked));
        node.removeAttribute('style');
        const styles = node.style;
        styles.fontFamily = item.font && item.font !== 'Default' ? `"${item.font}"` : '';
        styles.fontSize = item.size !== undefined ? `${item.size}pt` : '';
        styles.color = item.color ?? '';
        if (isCustom(key) || ['line', 'box', 'checkbox', 'image'].includes(type)) styles.transform = `translate(${item.left ?? 0}mm, ${item.top ?? 0}mm)`;
        else {
            styles.left = `${item.left ?? 0}mm`;
            styles.top = `${item.top ?? 0}mm`;
        }
        styles.fontWeight = item.bold !== undefined ? (item.bold ? '700' : '400') : '';
        for (const dimension of ['width', 'height']) if (item[dimension] !== undefined) styles[dimension] = `${item[dimension]}mm`;
        if (isText(node) && (item.width !== undefined || item.height !== undefined)) styles.display = 'inline-block';
        if (item.border_width !== undefined) styles[type === 'line' ? 'borderTopWidth' : 'borderWidth'] = `${item.border_width}px`;
        if (item.border_style !== undefined) styles[type === 'line' ? 'borderTopStyle' : 'borderStyle'] = item.border_style;
        styles.borderColor = item.border_color ?? '';
        styles.backgroundColor = item.fill ?? '';
    }

    function select(node) {
        if (!editing) return;
        selected?.classList.remove('is-selected');
        selected = node;
        if (!selected) { changed(); return; }
        selected.classList.add('is-selected');
        picker.value = selected.dataset.skippingBlock;
        picker.closest('.premium-form-field')?.classList.add('has-value');
        const item = block();
        const base = defaults.get(selected.dataset.skippingBlock);
        inputs.forEach((input) => {
            const key = input.dataset.templateControl;
            let value = item[key] ?? (key === 'text' ? config.defaults[selected.dataset.skippingBlock] ?? '' : base?.[key] ?? '');
            if (key === 'fill' && (!value || value === 'transparent')) value = '#ffffff';
            if (input.type === 'checkbox') input.checked = Boolean(value);
            else input.value = value;
            input.closest('.premium-form-field')?.classList.add('has-value');
        });
        changed();
    }

    function update(key, value) {
        if (!editing || !selected || busy) return;
        const id = selected.dataset.skippingBlock;
        state[id] = { ...(state[id] || {}), [key]: value };
        apply(selected);
        changed();
    }

    inputs.forEach((input) => input.addEventListener('input', () => {
        if (input.type === 'number' && (!input.value || !Number.isFinite(Number(input.value)))) return;
        let value = input.type === 'checkbox' ? input.checked : input.value;
        if (input.type === 'number') {
            value = clamp(Number(value), Number(input.min), Number(input.max));
            input.value = value;
        }
        update(input.dataset.templateControl, value);
        if (['text', 'option_label'].includes(input.dataset.templateControl)) refreshPicker();
    }));
    picker.addEventListener('change', () => select(nodes.get(picker.value)));
    tokenPicker.addEventListener('change', (event) => {
        if (!editing || busy || !selected || !event.target.value) return;
        const start = content.selectionStart;
        const end = content.selectionEnd;
        const text = content.value.slice(0, start) + event.target.value + content.value.slice(end);
        if (text.length > 4000) return;
        content.value = text;
        update('text', text);
        content.focus();
        content.setSelectionRange(start + event.target.value.length, start + event.target.value.length);
        event.target.value = '';
    });

    function move(direction, amount = 0.5) {
        if (!editing || !selected || busy) return;
        const key = ['left', 'right'].includes(direction) ? 'left' : 'top';
        const delta = ['left', 'up'].includes(direction) ? -amount : amount;
        const limit = key === 'left' ? 210 : 297;
        update(key, clamp(Number(block()[key] ?? 0) + delta, -limit, limit));
        select(selected);
    }
    root.querySelectorAll('[data-template-move]').forEach((button) => button.addEventListener('click', () => move(button.dataset.templateMove)));
    root.querySelector('[data-template-reset-block]').addEventListener('click', () => {
        if (!editing || !selected || busy) return;
        const key = selected.dataset.skippingBlock;
        if (isCustom(key)) state[key] = customDefault(selected.dataset.objectType);
        else delete state[key];
        apply(selected); refreshPicker(); select(selected);
    });
    noFill.addEventListener('click', () => update('fill', 'transparent'));

    function register(node) {
        const key = node.dataset.skippingBlock;
        nodes.set(key, node);
        const savedStyle = node.getAttribute('style') || '';
        node.removeAttribute('style');
        const computed = iframe.contentWindow.getComputedStyle(node);
        const rgb = computed.color.match(/\d+/g)?.slice(0, 3) || [0, 0, 0];
        const definition = config.definitions[key] || customDefault(node.dataset.objectType);
        defaults.set(key, {
            font: 'Default', size: Math.round(parseFloat(computed.fontSize) * 0.75 * 10) / 10,
            color: `#${rgb.map((n) => Number(n).toString(16).padStart(2, '0')).join('')}`,
            left: 0, top: 0, bold: Number(computed.fontWeight) >= 600,
            width: definition.width ?? '', height: definition.height ?? (node.dataset.objectType === 'line' ? 0 : ''),
            border_style: definition.border_style ?? 'none', border_width: definition.border_width ?? 0,
            border_color: definition.border_color ?? '#333333', fill: 'transparent',
            option_label: `Checkbox ${key.slice(-6)}`,
        });
        node.setAttribute('style', savedStyle);
        picker.add(new Option(label(node), key));
        syncNodeEditing(node);
        node.addEventListener('click', () => select(node));
        node.addEventListener('dblclick', () => { if (!editing || busy) return; select(node); if (isText(node)) content.focus(); });
        node.addEventListener('keydown', (event) => {
            if (!editing || busy) return;
            const direction = { ArrowLeft: 'left', ArrowRight: 'right', ArrowUp: 'up', ArrowDown: 'down' }[event.key];
            if (direction) { event.preventDefault(); select(node); move(direction, event.shiftKey ? 2 : 0.5); }
            if (event.key === 'Enter') { event.preventDefault(); select(node); if (isText(node)) content.focus(); }
        });
        node.addEventListener('pointerdown', (event) => {
            if (!editing || busy || event.button !== 0) return;
            select(node);
            const x = event.clientX, y = event.clientY;
            const left = Number(block().left ?? 0), top = Number(block().top ?? 0);
            let moved = false;
            const drag = (next) => {
                if (!editing || busy) return;
                if (!moved && Math.hypot(next.clientX - x, next.clientY - y) < 3) return;
                moved = true;
                update('left', Math.round(clamp(left + (next.clientX - x) * 25.4 / 96, -210, 210) * 10) / 10);
                update('top', Math.round(clamp(top + (next.clientY - y) * 25.4 / 96, -297, 297) * 10) / 10);
                select(node);
            };
            const end = () => { node.removeEventListener('pointermove', drag); node.removeEventListener('pointerup', end); node.removeEventListener('pointercancel', end); };
            node.setPointerCapture(event.pointerId);
            node.addEventListener('pointermove', drag);
            node.addEventListener('pointerup', end);
            node.addEventListener('pointercancel', end);
        });
    }

    let initializedDocument;
    async function initializePreview() {
        const doc = iframe.contentDocument;
        if (!doc || doc.URL === 'about:blank' || doc === initializedDocument) return;
        initializedDocument = doc;
        const elements = [...doc.querySelectorAll('[data-skipping-block]')];
        if (!elements.length) {
            status.textContent = 'Unable to load preview. Refresh the page and sign in again if needed.';
            return;
        }
        await doc.fonts.ready;
        await Promise.all([...doc.images].map((image) => image.decode().catch(() => {})));
        nodes = new Map(elements.map((node) => [node.dataset.skippingBlock, node]));
        picker.replaceChildren();
        elements.forEach(register);
        ready = true;
        if (editing) select(elements[0]);
        else changed();
        doc.body.classList.toggle('is-editing', editing);
        new ResizeObserver(resize).observe(wrap.parentElement);
        new ResizeObserver(resize).observe(doc.querySelector('.skipping-paper'));
        doc.fonts.addEventListener('loadingdone', resize);
        resize();
    }
    iframe.addEventListener('load', initializePreview);
    if (iframe.contentDocument?.readyState === 'complete') initializePreview();

    function createCustom(key, item) {
        const node = iframe.contentDocument.createElement('span');
        node.className = `skipping-template-object skipping-template-${item.type} skipping-template-custom${item.type === 'checkbox' ? ' print-check' : ''}`;
        node.dataset.skippingBlock = key;
        node.dataset.objectType = item.type;
        iframe.contentDocument.querySelector('.skipping-paper').append(node);
        register(node); apply(node);
        return node;
    }

    addButtons.forEach((button) => button.addEventListener('click', () => {
        if (!editing || !ready || busy) return;
        if (Object.keys(state).length >= 220) { Swal.fire({ icon: 'info', title: 'Object limit reached', text: 'Remove an added object before adding another.' }); return; }
        const randomId = crypto.randomUUID?.() || [...crypto.getRandomValues(new Uint8Array(16))].map((byte) => byte.toString(16).padStart(2, '0')).join('');
        const key = `custom-${randomId}`;
        state[key] = customDefault(button.dataset.templateAdd);
        const node = createCustom(key, state[key]);
        refreshPicker(); select(node);
    }));
    removeButton.addEventListener('click', async () => {
        if (!editing || !selected || busy) return;
        const key = selected.dataset.skippingBlock;
        if (!(await Swal.fire({ icon: 'question', title: 'Remove selected object?', text: 'Save Template to apply this removal.', showCancelButton: true, confirmButtonText: 'Remove' })).isConfirmed) return;
        if (!editing || busy) return;
        if (isCustom(key)) { selected.remove(); nodes.delete(key); defaults.delete(key); delete state[key]; }
        else { state[key] = { ...block(), removed: true }; apply(selected); }
        selected.classList.remove('is-selected');
        selected = null;
        refreshPicker(); select([...nodes.values()].find((node) => node.dataset.removed !== 'true') || null);
    });
    function redraw() {
        const key = selected?.dataset.skippingBlock;
        for (const [id, node] of nodes) if (isCustom(id) && !state[id]) { node.remove(); nodes.delete(id); defaults.delete(id); }
        for (const [id, item] of Object.entries(state)) if (isCustom(id) && !nodes.has(id)) createCustom(id, item);
        nodes.forEach(apply);
        refreshPicker(); select(nodes.get(key) || [...nodes.values()].find((node) => node.dataset.removed !== 'true') || null);
    }
    discard.addEventListener('click', async () => {
        if (!editing || busy) return;
        if (!(await Swal.fire({ icon: 'question', title: 'Discard changes?', text: 'Return to the saved template.', showCancelButton: true, confirmButtonText: 'Discard Changes' })).isConfirmed) return;
        if (!editing || busy) return;
        state = copy(saved); redraw();
        showSuccess('Changes discarded');
    });
    restore.addEventListener('click', async () => {
        if (!editing || busy) return;
        if (!(await Swal.fire({ icon: 'question', title: 'Restore original layout?', text: 'Save Template to keep the restored layout.', showCancelButton: true, confirmButtonText: 'Restore' })).isConfirmed) return;
        if (!editing || busy) return;
        state = {}; redraw();
        showSuccess('Original layout restored', 'Click Save Template to apply it to future prints.');
    });
    save.addEventListener('click', async () => {
        if (!editing || busy || !dirty) return;
        busy = true; changed();
        try {
            const response = await fetch(root.dataset.saveUrl, {
                method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ blocks: state, revision }),
            });
            const json = response.headers.get('content-type')?.includes('application/json') ? await response.json() : null;
            if (!response.ok || !json || response.redirected) throw new Error(Object.values(json?.errors || {}).flat().join('\n') || json?.message || 'Unable to save. Refresh the page and try again.');
            saved = copy(state); revision = json.revision;
            showSuccess('Template saved');
        } catch (error) { await Swal.fire({ icon: 'error', title: 'Unable to save template', text: error.message }); }
        finally { busy = false; changed(); }
    });
    window.addEventListener('beforeunload', (event) => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
}
