import { showAlert } from './helpers/sweet-alert2';

const PAGE = { x: 297, y: 210, width: 297, height: 210 };
const clone = (value) => JSON.parse(JSON.stringify(value));

export function initG9CertificateEditor({ applyFieldStyle, loadFontsAndFit }) {
    const form = document.querySelector('[data-g9-template-editor]');
    const preview = document.querySelector('[data-g9-certificate-preview]');
    const page = preview?.querySelector('.g9-certificate-page');
    if (!form || !page) return;
    let layout = JSON.parse(form.dataset.g9Layout);
    const original = clone(layout), defaults = JSON.parse(form.dataset.g9DefaultLayout);
    const values = JSON.parse(page.dataset.g9Values);
    const blocks = new Map([...page.querySelectorAll('[data-g9-layout-key]')].map((node) => [node.dataset.g9LayoutKey,node]));
    const fonts = new Map([...form.querySelector('[data-g9-property="font"]').options].map((option) => [option.value,option.dataset.fontFamily]));
    const controls = [...form.querySelectorAll('[data-g9-property]')];
    const status = form.querySelector('[data-g9-editor-status]');
    const save = form.querySelector('[data-g9-save-template]');
    const tools = form.querySelector('[data-g9-editor-tools]');
    const picker = form.querySelector('[data-g9-select-block]');
    let selected = 'heading', editing = false, textEditing = null, drag = null, dirty = false;

    const markDirty = () => {
        dirty = true; save.disabled = false;
        form.querySelector('[data-g9-undo]').disabled = false;
        status.textContent = 'Unsaved changes — Save Template to use them for printing and future certificates.';
    };
    function clamp(key) {
        const field = layout.fields[key], photo = key === 'photo';
        if (key === 'qr') {
            field.width = Math.min(20, Math.max(8, field.width));
            field.x = Math.min(100-field.width, Math.max(0, field.x));
            field.y = Math.min(100-(field.width*PAGE.width/100+3)*100/PAGE.height, Math.max(0, field.y));
            return;
        }
        field.width = Math.min(100,Math.max(photo ? 2 : 5,field.width));
        field.x = Math.min(100-field.width,Math.max(0,field.x));
        if (photo) field.height = Math.min(40,Math.max(2,field.height));
        field.y = Math.min(95,photo ? 100-field.height : 95,Math.max(0,field.y));
    }
    function attachHandle(node) {
        if (!editing || node.querySelector('.g9-editor-resize') || node.isContentEditable) return;
        const handle = document.createElement('button');
        handle.type = 'button'; handle.className = 'g9-editor-resize';
        handle.setAttribute('aria-label','Resize selected certificate block');
        node.append(handle);
    }
    function render(key) {
        const field = layout.fields[key], node = blocks.get(key);
        for (const property of ['x','y','width','height']) if (property in field) node.dataset['g9'+property[0].toUpperCase()+property.slice(1)] = field[property];
        if (!['photo', 'qr'].includes(key)) {
            if (key === 'title') node.dataset.g9Curve = field.curve ?? 26;
            Object.assign(node.dataset,{g9FontName:field.font,g9FontFamily:fonts.get(field.font),g9FontSize:field.size,g9FontColor:field.color,g9Bold:field.bold?'1':'0',g9BoldFirstLine:field.bold_first_line?'1':'0',g9Align:field.align,g9Source:field.text});
            if (textEditing !== key) {
                const text = field.text.replace(/\{\{([^{}]+)\}\}/g,(token,name) => Object.hasOwn(values,name) ? values[name] : token);
                node.replaceChildren(...text.split('\n').map((line,index) => {
                    const span = document.createElement('span');
                    span.className = 'g9-certificate-line'+(field.bold_first_line&&index===0?' g9-line-bold':'');
                    span.textContent = line; return span;
                }));
            }
        }
        applyFieldStyle(node); attachHandle(node);
    }
    function syncControls() {
        const field = layout.fields[selected], photo = selected === 'photo', qr = selected === 'qr';
        picker.value = selected;
        form.querySelectorAll('[data-g9-text-tool]').forEach((node) => { node.hidden = photo || qr; });
        form.querySelectorAll('[data-g9-photo-tool]').forEach((node) => { node.hidden = !photo; });
        form.querySelectorAll('[data-g9-qr-tool]').forEach((node) => { node.hidden = !qr; });
        form.querySelectorAll('[data-g9-title-tool]').forEach((node) => { node.hidden = selected !== 'title'; });
        controls.forEach((control) => {
            const property = control.dataset.g9Property;
            control.disabled = !(property in field);
            if (control.type === 'checkbox') control.checked = !!field[property];
            else if (property in PAGE && property in field) control.value = (field[property]*PAGE[property]/100).toFixed(1);
            else control.value = field[property] ?? '';
        });
    }
    function finishText() {
        if (!textEditing) return;
        const key = textEditing, node = blocks.get(key);
        layout.fields[key].text = node.innerText.replace(/\r\n/g,'\n').slice(0,1000);
        textEditing = null; node.contentEditable = 'false';
        render(key); syncControls(); loadFontsAndFit(page);
    }
    function select(key) {
        if (selected !== key) finishText();
        selected = key;
        blocks.forEach((node,nodeKey) => node.classList.toggle('g9-block-selected',editing&&nodeKey===key));
        syncControls();
    }
    function beginText() {
        if (!editing || ['photo', 'qr'].includes(selected)) return;
        finishText(); textEditing = selected;
        const node = blocks.get(selected);
        node.replaceChildren(document.createTextNode(layout.fields[selected].text));
        node.contentEditable = 'plaintext-only'; node.focus({preventScroll:true});
        const range = document.createRange();range.selectNodeContents(node);range.collapse(false);
        const selection = window.getSelection();selection.removeAllRanges();selection.addRange(range);
    }
    function toggle(enabled) {
        finishText(); editing = enabled; page.classList.toggle('g9-is-editing',editing); tools.hidden = !editing;
        const button = form.querySelector('[data-g9-edit-toggle]');
        button.textContent = editing ? 'Finish Editing' : 'Edit Template'; button.setAttribute('aria-pressed',String(editing));
        blocks.forEach((node,key) => {
            node.tabIndex = editing ? 0 : -1;
            if (editing) attachHandle(node); else node.querySelector('.g9-editor-resize')?.remove();
        });
        select(selected);
    }
    blocks.forEach((node,key) => {
        node.addEventListener('click',(event) => {
            if (editing) {
                if (event.target.closest('a')) event.preventDefault();
                select(key);
            }
        });
        node.addEventListener('dblclick',() => { if (editing) { select(key);beginText(); } });
        node.addEventListener('blur',() => { if (textEditing === key) finishText(); });
        node.addEventListener('input',() => {
            if (textEditing !== key) return;
            layout.fields[key].text = node.innerText.replace(/\r\n/g,'\n').slice(0,1000);
            form.querySelector('[data-g9-property="text"]').value = layout.fields[key].text;markDirty();
        });
        node.addEventListener('keydown',(event) => {
            if (!editing || node.isContentEditable || !['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(event.key)) return;
            event.preventDefault();select(key);
            const axis = ['ArrowLeft','ArrowRight'].includes(event.key) ? 'x' : 'y';
            layout.fields[key][axis] += (['ArrowLeft','ArrowUp'].includes(event.key)?-1:1)*(event.shiftKey?1:.1);
            clamp(key);render(key);syncControls();markDirty();
        });
        node.addEventListener('pointerdown',(event) => {
            if (!editing || node.isContentEditable || event.button !== 0) return;
            select(key);event.preventDefault();node.focus({preventScroll:true});
            drag = {id:event.pointerId,key,x:event.clientX,y:event.clientY,start:clone(layout.fields[key]),rect:page.getBoundingClientRect(),resize:!!event.target.closest('.g9-editor-resize')};
            node.setPointerCapture(event.pointerId);
        });
    });
    // Track on the document as well: native controls can release pointer capture.
    document.addEventListener('pointermove',(event) => {
        if (!drag || drag.id !== event.pointerId) return;
        const key=drag.key,dx=100*(event.clientX-drag.x)/drag.rect.width,dy=100*(event.clientY-drag.y)/drag.rect.height;
        if(Math.abs(dx)+Math.abs(dy)<.05)return;
        const field=layout.fields[key];
        if(drag.resize){field.width=drag.start.width+dx;if(key==='photo')field.height=drag.start.height+dy;}
        else{field.x=drag.start.x+dx;field.y=drag.start.y+dy;}
        clamp(key);applyPositions(key);syncControls();markDirty();
    });
    const endDrag = (event) => {
        if(!drag||drag.id!==event.pointerId)return;
        const key=drag.key;drag=null;render(key);loadFontsAndFit(page);
    };
    document.addEventListener('pointerup',endDrag);document.addEventListener('pointercancel',endDrag);
    function applyPositions(key) {
        const field=layout.fields[key],node=blocks.get(key);
        for(const property of ['x','y','width','height'])if(property in field)node.dataset['g9'+property[0].toUpperCase()+property.slice(1)]=field[property];
        applyFieldStyle(node);
    }
    controls.forEach((control) => control.addEventListener('input',() => {
        if (!editing || !control.checkValidity()) return;
        finishText();const property=control.dataset.g9Property,field=layout.fields[selected];
        let value=control.type==='checkbox'?control.checked:control.value;
        if(property in PAGE)value=Number(value)*100/PAGE[property];
        if(['size','curve'].includes(property))value=Number(value);
        field[property]=value;clamp(selected);render(selected);loadFontsAndFit(page);markDirty();
        if(property==='curve')syncControls();
    }));
    controls.filter(control=>control.dataset.g9Property in PAGE).forEach(control=>control.addEventListener('change',syncControls));
    picker.addEventListener('change',() => select(picker.value));
    form.querySelectorAll('[data-g9-qr-nudge]').forEach((button) => button.addEventListener('click', () => {
        if (!editing || selected !== 'qr') return;
        const direction = button.dataset.g9QrNudge;
        const axis = ['left', 'right'].includes(direction) ? 'x' : 'y';
        layout.fields.qr[axis] += (['left', 'up'].includes(direction) ? -1 : 1) * 100 / PAGE[axis];
        clamp('qr'); render('qr'); syncControls(); markDirty();
    }));
    form.querySelector('[data-g9-edit-toggle]').addEventListener('click',() => {
        toggle(!editing);
        showAlert({
            title: editing ? 'Template Editing Enabled' : 'Template Preview Ready',
            message: editing ? 'Select a block to edit its text, font, color, size, or position.' : (dirty ? 'Save Template to keep your changes for printing and future certificates.' : 'The saved template is ready to preview.'),
        });
    });
    form.querySelector('[data-g9-edit-text]').addEventListener('click',beginText);
    form.querySelector('[data-g9-insert-token]').addEventListener('change',(event) => {
        if(!event.target.value||['photo','qr'].includes(selected))return;
        finishText();const input=form.querySelector('[data-g9-property="text"]');
        const text=layout.fields[selected].text,start=input.selectionStart??text.length,end=input.selectionEnd??start;
        layout.fields[selected].text=(text.slice(0,start)+'{{'+event.target.value+'}}'+text.slice(end)).slice(0,1000);
        event.target.value='';render(selected);syncControls();loadFontsAndFit(page);markDirty();
    });
    form.querySelector('[data-g9-reset]').addEventListener('click',() => {
        finishText();layout=clone(defaults);blocks.forEach((node,key)=>render(key));toggle(true);loadFontsAndFit(page);markDirty();
        showAlert({ title: 'Original Layout Restored', message: 'Save Template to use this layout for printing and future certificates.' });
    });
    form.querySelector('[data-g9-undo]').addEventListener('click',() => {
        finishText();layout=clone(original);blocks.forEach((node,key)=>render(key));select(selected);loadFontsAndFit(page);
        dirty=false;save.disabled=true;form.querySelector('[data-g9-undo]').disabled=true;status.textContent='Saved template';
        showAlert({ title: 'Changes Discarded', message: 'The last saved template has been restored.' });
    });
    form.addEventListener('submit',() => {finishText();form.querySelector('[data-g9-template-data]').value=JSON.stringify(layout);});
    document.querySelectorAll('.report-print-link,.report-pdf-link').forEach((link) => link.addEventListener('click',(event)=>{
        if(dirty){event.preventDefault();status.textContent='Save Template before printing or exporting your changes.';save.focus();}
    }));
    syncControls();
}
