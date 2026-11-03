import Swal from 'sweetalert2';
import {initPremiumDatePicker} from './premiumDatePicker.js';

const editor = document.querySelector('[data-g12-certificate-editor]');
if (editor) {
    const root = editor.closest('[data-g12-exams]');
    const get = selector => editor.querySelector(selector);
    const filters = root.querySelector('[data-g12-filters]');
    const sheet = get('[data-cert-sheet]');
    const controls = get('[data-cert-controls]');
    let data, layout, selected = 'title', scope, dirty = false, loading = 0, busy = false;
    const selection = new Set();
    const issueDatePicker=initPremiumDatePicker(get('[data-cert-date-picker]'));
    const scopeNow = () => ({academic_year: filters.elements.academic_year.value, campus_id: filters.elements.campus_id.value});
    const request = async (path, body) => {
        const response = await fetch(`${root.dataset.base}/grade-a-certificate/${path}`, {
            method: body ? 'POST' : 'GET', credentials: 'same-origin',
            headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
            body: body ? JSON.stringify(body) : undefined,
        });
        if (!response.ok) {
            const error = await response.json().catch(() => ({}));
            throw new Error(Object.values(error.errors || {}).flat().join('\n') || error.message || 'Unable to complete this request.');
        }
        return response;
    };
    const action = async callback => {
        if (busy) return;
        busy = true;
        try { await callback(); }
        catch (error) { await Swal.fire({icon: 'error', title: 'Grade A Certificate', text: error.message}); }
        finally { busy = false; }
    };
    const values = () => {
        const studentValues=data?.students.find(student => String(student.id) === get('[data-cert-student]').value)?.values || {};
        if(!layout?.issue_date)return studentValues;
        const date=new Date(`${layout.issue_date}T12:00:00`);
        const day=date.getDate();
        const suffix=day%100>=11&&day%100<=13?'th':({1:'st',2:'nd',3:'rd'}[day%10]||'th');
        return {...studentValues,issue_date:`${day}${suffix} day of ${date.toLocaleDateString('en-US',{month:'long',year:'numeric'})}`};
    };
    function syncYearSettings(){
        const wasDirty=dirty;
        issueDatePicker.setValue(layout.issue_date);
        dirty=wasDirty;
        const [name,...position]=layout.fields.signer_1.text.split('\n');
        get('[data-cert-signer-name]').value=name;
        get('[data-cert-signer-position]').value=position.join('\n');
    }
    const textValue = template => String(template || '').replace(/\{\{\s*([^}]+)\s*\}\}/g, (_, key) => String(values()[key.trim()] || ''));

    function resize() {
        const container = sheet.parentElement.parentElement;
        const scale = Math.min(1, Math.max(240, container.clientWidth - 32) / (210 * 96 / 25.4));
        sheet.style.transform = `scale(${scale})`;
        sheet.parentElement.style.width = `${210 * 96 / 25.4 * scale}px`;
        sheet.parentElement.style.height = `${297 * 96 / 25.4 * scale}px`;
    }
    function draw() {
        if (!layout) return;
        const nodes = [];
        if (get('[data-cert-frame]').checked) {
            const frame = document.createElement('img'); frame.src = data.frame; frame.alt = 'Certificate frame'; nodes.push(frame);
        }
        Object.entries(layout.fields).forEach(([key, field]) => {
            if (!field.visible || key === 'logo') return;
            const node = document.createElement('div'); node.dataset.certBlock = key; node.tabIndex = 0;
            node.className = `g12-certificate-text${key === selected ? ' is-selected' : ''}`;
            node.setAttribute('aria-label', `${field.label}. Drag or use arrow keys to move.`);
            Object.assign(node.style, {left: `${field.x}mm`, top: `${field.y}mm`, width: `${field.width}mm`, height: `${field.height}mm`, fontSize: `${field.size}pt`, fontFamily: field.font, color: field.color, textAlign: field.align, fontWeight: field.bold ? '700' : '400'});
            node.textContent = textValue(field.text); nodes.push(node);
        });
        sheet.replaceChildren(...nodes); resize();
        sheet.querySelectorAll('[data-cert-block]').forEach(node => {
            const field = layout.fields[node.dataset.certBlock];
            const ratio = Math.min(1, node.clientWidth / Math.max(1, node.scrollWidth), node.clientHeight / Math.max(1, node.scrollHeight));
            if (ratio < 1) node.style.fontSize = `${Math.max(6, field.size * ratio)}pt`;
        });
    }
    function selectField(key) {
        selected = key; get('[data-cert-field]').value = key;
        const field = layout.fields[key];
        for (const input of controls.elements) {
            if (!input.name) continue;
            if (input.type === 'checkbox') input.checked = field[input.name]; else input.value = field[input.name] ?? '';
        }
        sheet.querySelectorAll('[data-cert-block]').forEach(node => node.classList.toggle('is-selected', node.dataset.certBlock === key));
    }
    function option(value, label) { const node = document.createElement('option'); node.value = value; node.textContent = label; return node; }
    async function load() {
        const next = scopeNow();
        if (scope && next.academic_year === scope.academic_year && next.campus_id === scope.campus_id) return;
        if (dirty && !confirm('Discard unsaved certificate layout changes?')) {
            filters.elements.academic_year.value = scope.academic_year; filters.elements.campus_id.value = scope.campus_id; return;
        }
        const version = ++loading; scope = next; dirty = false;
        get('[data-cert-workspace]').hidden = true;
        const message = get('[data-cert-message]'); message.hidden = false;
        if (!next.academic_year) { message.textContent = 'Select an Academic Year above to edit certificates. Campus is optional.'; return; }
        message.textContent = 'Loading Grade A certificates…';
        try {
            const response = await request(`editor?${new URLSearchParams(next)}`);
            const result = await response.json(); if (version !== loading) return;
            data = result; layout = structuredClone(result.layout); selection.clear();
            const fontSelect = controls.elements.font; fontSelect.replaceChildren();
            await Promise.all(Object.entries(data.fonts).map(async ([key, font]) => {
                fontSelect.append(option(key, font.label));
                const face = new FontFace(key, `url('/fonts/${font.directory}/${font.file}')`);
                document.fonts.add(await face.load());
            }));
            if (version !== loading) return;
            get('[data-cert-student]').replaceChildren(...data.students.map(student => option(student.id, student.name)));
            get('[data-cert-field]').replaceChildren(...Object.entries(layout.fields).filter(([key]) => key !== 'logo').map(([key, field]) => option(key, field.label)));
            get('[data-cert-students]').replaceChildren(...data.students.map(student => {
                selection.add(student.id);
                const label = document.createElement('label'); const check = document.createElement('input'); check.type = 'checkbox'; check.checked = true; check.className = 'form-check-input';
                check.addEventListener('change', () => { if (check.checked) selection.add(student.id); else selection.delete(student.id); get('[data-cert-select-all]').checked = selection.size === data.students.length; });
                const name = document.createElement('span'); name.textContent = `${student.name} — ${student.values.certificate_number}`; label.append(check, name); return label;
            }));
            get('[data-cert-select-all]').checked = true;
            get('[data-cert-save]').disabled = !data.can_save; get('[data-cert-pdf]').disabled = !data.can_save || !data.students.length;
            get('[data-cert-assign]').disabled = !data.can_save;
            message.hidden = data.students.length > 0;
            message.textContent = 'No Grade A students match this selection. You can still prepare and save the yearly layout.';
            get('[data-cert-workspace]').hidden = false; syncYearSettings(); selectField('title'); draw();
        } catch (error) { if (version === loading) message.textContent = error.message; }
    }
    filters.addEventListener('change', event => { if (['academic_year', 'campus_id'].includes(event.target.name)) void load(); });
    get('[data-cert-field]').addEventListener('change', event => selectField(event.target.value));
    get('[data-cert-student]').addEventListener('change', draw);
    get('[data-cert-frame]').addEventListener('change', draw);
    controls.addEventListener('submit', event => event.preventDefault());
    controls.addEventListener('input', event => {
        const input = event.target; if (!input.name || !layout) return;
        layout.fields[selected][input.name] = input.type === 'checkbox' ? input.checked : input.type === 'number' ? Number(input.value) : input.value;
        dirty = true; draw();
        if(selected==='signer_1')syncYearSettings();
    });
    get('[data-cert-issue-date]').addEventListener('change',event=>{layout.issue_date=event.target.value;dirty=true;draw();});
    for(const selector of ['[data-cert-signer-name]','[data-cert-signer-position]'])get(selector).addEventListener('input',()=>{
        layout.fields.signer_1.text=`${get('[data-cert-signer-name]').value}\n${get('[data-cert-signer-position]').value}`;
        dirty=true;if(selected==='signer_1')selectField(selected);draw();
    });
    get('[data-cert-save]').addEventListener('click', () => action(async () => {
        if(!get('[data-premium-date-input]').checkValidity()||!layout.issue_date)throw new Error('Enter a valid certificate issue date, for example 03-Nov-2026.');
        await request('settings', {...scope, layout}); dirty = false;
        await Swal.fire({icon: 'success', title: 'Layout Saved', text: 'Saved for the selected academic year across campuses.'});
    }));
    get('[data-cert-assign]').addEventListener('click', () => action(async () => {
        if(dirty)throw new Error('Save your layout changes before assigning certificate numbers.');
        const result=await Swal.fire({icon:'question',title:'Assign Certificate Numbers?',text:'Assign GA12 year numbers across your authorized campuses, highest score first. Existing Grade A certificate numbers will be replaced. Save unsaved layout changes first.',showCancelButton:true,confirmButtonText:'Assign Numbers'});
        if(!result.isConfirmed)return;
        const response=await request('assign-numbers',scope);
        const message=await response.json();
        await Swal.fire({icon:'success',title:'Numbers Assigned',text:message.message});
        scope=null;await load();
    }));
    get('[data-cert-reset]').addEventListener('click', () => {
        if (!confirm('Reset this layout to the sample design? Save Layout to keep the reset.')) return;
        layout = structuredClone(data.defaults); dirty = true; syncYearSettings(); selectField('title'); draw();
    });
    get('[data-cert-select-all]').addEventListener('change', event => {
        selection.clear(); if (event.target.checked) data.students.forEach(student => selection.add(student.id));
        get('[data-cert-students]').querySelectorAll('input').forEach(input => input.checked = event.target.checked);
    });
    get('[data-cert-pdf]').addEventListener('click', () => action(async () => {
        if(!get('[data-premium-date-input]').checkValidity()||!layout.issue_date)throw new Error('Enter a valid certificate issue date before exporting.');
        if (!selection.size) throw new Error('Select at least one student.');
        const response = await request('pdf', {...scope, layout, student_ids: [...selection], with_frame: get('[data-cert-frame]').checked});
        const url = URL.createObjectURL(await response.blob()); const link = document.createElement('a');
        link.href = url; link.download = 'g12-grade-a-certificates.pdf'; link.click(); setTimeout(() => URL.revokeObjectURL(url), 60000);
    }));
    let drag;
    sheet.addEventListener('pointerdown', event => {
        const node = event.target.closest('[data-cert-block]'); if (!node) return;
        selectField(node.dataset.certBlock); node.focus(); node.setPointerCapture(event.pointerId);
        const field = layout.fields[selected]; drag = {node, x: event.clientX, y: event.clientY, left: field.x, top: field.y};
    });
    sheet.addEventListener('pointermove', event => {
        if (!drag) return;
        const scale = sheet.getBoundingClientRect().width / 210;
        const field = layout.fields[selected];
        field.x = Math.round(Math.max(0, Math.min(210 - field.width, drag.left + (event.clientX - drag.x) / scale)) * 10) / 10;
        field.y = Math.round(Math.max(0, Math.min(297 - field.height, drag.top + (event.clientY - drag.y) / scale)) * 10) / 10;
        drag.node.style.left = `${field.x}mm`; drag.node.style.top = `${field.y}mm`; dirty = true;
        controls.elements.x.value = field.x; controls.elements.y.value = field.y;
    });
    const endDrag = () => { drag = null; };
    sheet.addEventListener('pointerup', endDrag); sheet.addEventListener('pointercancel', endDrag);
    sheet.addEventListener('keydown', event => {
        if (!event.target.closest('[data-cert-block]') || !['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(event.key)) return;
        event.preventDefault(); const field = layout.fields[selected];
        field.x = Math.max(0, Math.min(210 - field.width, field.x + (event.key === 'ArrowRight' ? .5 : event.key === 'ArrowLeft' ? -.5 : 0)));
        field.y = Math.max(0, Math.min(297 - field.height, field.y + (event.key === 'ArrowDown' ? .5 : event.key === 'ArrowUp' ? -.5 : 0)));
        dirty = true; draw(); selectField(selected); sheet.querySelector(`[data-cert-block="${selected}"]`)?.focus();
    });
    new ResizeObserver(resize).observe(sheet.parentElement.parentElement);
    window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
    void load();
}
