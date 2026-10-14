const stickers = ['✅', '❌', '⭐', '❤️', '👍', '😊', '🎯', '📌', '⚠️', '🎉', '🏫', '📚'];
let activeViewer;

export function imageEditorSize(width, height, maxSide = 2048) {
    const scale = Math.min(1, maxSide / Math.max(width, height));
    return { width: Math.max(1, Math.round(width * scale)), height: Math.max(1, Math.round(height * scale)) };
}

export function imageEditorPoint(event, rect, width, height) {
    return {
        x: Math.max(0, Math.min(width, (event.clientX - rect.left) * width / rect.width)),
        y: Math.max(0, Math.min(height, (event.clientY - rect.top) * height / rect.height)),
    };
}

export function editedImageName(name = 'image') {
    const stem = name.split(/[\\/]/).pop().replace(/\.[^.]*$/, '').replace(/[\x00-\x1f<>:"|?*]/g, '').slice(0, 100) || 'image';
    return `${stem}-edited.png`;
}

// Every message image uses this viewer; accepting creates a new attachment, never an update to the original.
export function bindChatImageEditor(container, { getConversationId, onAccept, getComposer, hasAttachment = () => false }) {
    const click = (event) => {
        const link = event.target.closest('a[data-chat-image]');
        if (!link || !container.contains(link) || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const conversationId = getConversationId();
        if (!conversationId) return;
        event.preventDefault();
        if (activeViewer) return;
        openChatImageViewer({
            url: link.href,
            name: link.dataset.imageName || 'image',
            hasAttachment,
            onAccept: (file) => {
                if (getConversationId() !== conversationId) throw new Error('The conversation changed. Close this picture and open it again in the intended chat.');
                return onAccept(file);
            },
            getComposer,
        });
    };
    container.addEventListener('click', click);
    return () => container.removeEventListener('click', click);
}

export function openChatImageViewer({ url, name, onAccept, getComposer, hasAttachment = () => false }) {
    if (activeViewer) return;
    const previousFocus = document.activeElement;
    const dialog = document.createElement('dialog');
    dialog.className = 'chat-image-editor';
    dialog.setAttribute('aria-label', 'Message picture viewer and editor');
    dialog.innerHTML = `
        <header class="chat-image-editor-header">
            <button type="button" data-image-close aria-label="Close picture"><i class="ti ti-x"></i></button>
            <div class="chat-image-editor-title"><strong>Picture</strong><span data-image-name></span></div>
            <a data-image-download target="_blank" rel="noopener" aria-label="Open original picture" title="Open original"><i class="ti ti-external-link"></i></a>
            <button type="button" data-image-edit disabled><i class="ti ti-pencil"></i> Edit</button>
        </header>
        <div class="chat-image-editor-stage"><canvas aria-label="Picture preview. Use Draw to write, or Move to drag added text and stickers."></canvas></div>
        <p class="chat-image-editor-status" role="status" aria-live="polite">Loading picture…</p>
        <section class="chat-image-editor-tools" aria-label="Picture editing tools" hidden>
            <div class="chat-image-editor-history">
                <button type="button" data-image-undo disabled><i class="ti ti-arrow-back-up"></i> Undo</button>
                <span>Original picture is kept</span>
                <button type="button" data-image-clear disabled>Clear all</button>
            </div>
            <div class="chat-image-editor-tool-tabs" role="group" aria-label="Editing tool">
                <button type="button" data-image-tool="draw" aria-pressed="true"><i class="ti ti-pencil"></i> Draw</button>
                <button type="button" data-image-tool="sticker" aria-pressed="false"><i class="ti ti-mood-smile"></i> Sticker</button>
                <button type="button" data-image-tool="text" aria-pressed="false"><i class="ti ti-letter-t"></i> Text</button>
                <button type="button" data-image-tool="move" aria-pressed="false"><i class="ti ti-arrows-move"></i> Move</button>
            </div>
            <div class="chat-image-editor-settings">
                <label>Color <input type="color" data-image-color value="#ff3b30" aria-label="Drawing and text color"></label>
                <label class="chat-image-editor-size">Size <input type="range" data-image-size min="2" max="20" value="4" aria-label="Tool size"></label>
            </div>
            <div class="chat-image-editor-stickers" data-image-stickers hidden>${stickers.map(sticker => `<button type="button" data-image-sticker="${sticker}" aria-label="Add ${sticker} sticker">${sticker}</button>`).join('')}</div>
            <div class="chat-image-editor-text" data-image-text-panel hidden>
                <textarea data-image-text rows="2" maxlength="300" placeholder="Type text to add…" aria-label="Text to add to picture"></textarea>
                <button type="button" data-image-add-text>Add text</button>
            </div>
            <footer class="chat-image-editor-footer">
                <button type="button" data-image-cancel>Cancel edit</button>
                <button type="button" data-image-accept disabled><i class="ti ti-check"></i> Accept & attach</button>
            </footer>
        </section>`;
    const find = selector => dialog.querySelector(selector);
    const canvas = find('canvas');
    const ctx = canvas.getContext('2d');
    const status = find('.chat-image-editor-status');
    const tools = find('.chat-image-editor-tools');
    const edit = find('[data-image-edit]');
    const undo = find('[data-image-undo]');
    const clear = find('[data-image-clear]');
    const accept = find('[data-image-accept]');
    const color = find('[data-image-color]');
    const size = find('[data-image-size]');
    const textInput = find('[data-image-text]');
    find('[data-image-name]').textContent = name || 'Image';
    find('[data-image-download]').href = url;
    let image, objectUrl, editing = false, busy = false, accepted = false, closed = false;
    let elements = [], history = [], gesture = null, selected = null, tool = 'draw';
    const sizes = { draw: 4, text: 28, sticker: 56 };
    const abort = new AbortController();
    const snapshot = () => {
        history.push(JSON.stringify(elements));
        if (history.length > 25) history.shift();
    };
    const font = element => `${element.type === 'sticker' ? '' : '700 '}${element.size}px "Noto Sans Khmer", system-ui, "Apple Color Emoji", "Segoe UI Emoji", sans-serif`;
    const textLines = element => {
        ctx.font = font(element);
        const lines = [];
        for (const line of element.text.split('\n')) {
            let current = '';
            const characters = typeof Intl.Segmenter === 'function'
                ? Array.from(new Intl.Segmenter(undefined, { granularity: 'grapheme' }).segment(line), part => part.segment)
                : Array.from(line);
            for (const character of characters) {
                if (current && ctx.measureText(current + character).width > canvas.width * 0.9) {
                    lines.push(current);
                    current = '';
                }
                current += character;
            }
            lines.push(current);
        }
        return lines;
    };
    const bounds = element => {
        const lines = textLines(element);
        return { width: Math.max(...lines.map(line => ctx.measureText(line).width)) + element.size * 0.2, height: lines.length * element.size * 1.3 };
    };
    const render = (selection = true) => {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        if (!image) return;
        ctx.drawImage(image, 0, 0, canvas.width, canvas.height);
        for (const element of elements) {
            ctx.save();
            if (element.type === 'stroke') {
                ctx.strokeStyle = element.color;
                ctx.fillStyle = element.color;
                ctx.lineWidth = element.size;
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
                ctx.beginPath();
                element.points.forEach((point, i) => i ? ctx.lineTo(point.x, point.y) : ctx.moveTo(point.x, point.y));
                if (element.points.length === 1) {
                    ctx.arc(element.points[0].x, element.points[0].y, element.size / 2, 0, Math.PI * 2);
                    ctx.fill();
                } else ctx.stroke();
            } else {
                const lines = textLines(element);
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillStyle = element.color || '#ffffff';
                lines.forEach((line, index) => {
                    const y = element.y + (index - (lines.length - 1) / 2) * element.size * 1.3;
                    if (element.type === 'text') {
                        ctx.strokeStyle = 'rgba(0,0,0,0.75)';
                        ctx.lineWidth = Math.max(1, element.size / 14);
                        ctx.strokeText(line, element.x, y);
                    }
                    ctx.fillText(line, element.x, y);
                });
                if (editing && selection && selected === element) {
                    const box = bounds(element);
                    ctx.strokeStyle = '#93c5fd';
                    ctx.lineWidth = Math.max(1, canvas.width / 600);
                    ctx.setLineDash([ctx.lineWidth * 4, ctx.lineWidth * 3]);
                    ctx.strokeRect(element.x - box.width / 2, element.y - box.height / 2, box.width, box.height);
                }
            }
            ctx.restore();
        }
        undo.disabled = busy || history.length === 0;
        clear.disabled = busy || elements.length === 0;
        accept.disabled = busy || elements.length === 0;
    };
    const screenScale = () => canvas.width / Math.max(1, canvas.getBoundingClientRect().width);
    const setTool = value => {
        if (sizes[tool] !== undefined) sizes[tool] = Number(size.value);
        tool = value;
        dialog.dataset.tool = tool;
        dialog.querySelectorAll('[data-image-tool]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.imageTool === tool)));
        find('[data-image-stickers]').hidden = tool !== 'sticker';
        find('[data-image-text-panel]').hidden = tool !== 'text';
        color.parentElement.hidden = tool === 'sticker' || tool === 'move';
        size.parentElement.hidden = tool === 'move';
        size.min = tool === 'draw' ? 2 : tool === 'text' ? 16 : 24;
        size.max = tool === 'draw' ? 20 : tool === 'text' ? 60 : 100;
        size.value = sizes[tool] || 4;
        selected = null;
        render();
    };
    const addLabel = (type, text) => {
        if (!text.trim()) { textInput.focus(); return; }
        snapshot();
        const element = { type, text: text.trim(), color: color.value, x: canvas.width / 2, y: canvas.height / 2, size: Number(size.value) * screenScale() };
        // Keep labels usable on very short or narrow pictures too.
        element.size = Math.min(element.size, canvas.height / 3, canvas.width / 3);
        elements.push(element);
        setTool('move');
        selected = element;
        status.textContent = 'Drag the added text or sticker to position it. Accept to attach the edited copy.';
        render();
    };
    const endGesture = (event, cancelled = false) => {
        if (!gesture || event.pointerId !== gesture.id) return;
        if (cancelled) {
            elements = JSON.parse(history.pop());
            selected = null;
        }
        gesture = null;
        render();
    };
    canvas.addEventListener('pointerdown', event => {
        if (!editing || busy || gesture || (event.button !== undefined && event.button !== 0)) return;
        if (!['draw', 'move'].includes(tool)) return;
        event.preventDefault();
        const point = imageEditorPoint(event, canvas.getBoundingClientRect(), canvas.width, canvas.height);
        if (tool === 'draw') {
            snapshot();
            const element = { type: 'stroke', color: color.value, size: Number(size.value) * screenScale(), points: [point] };
            elements.push(element);
            gesture = { id: event.pointerId, element };
        } else {
            const element = [...elements].reverse().find(element => {
                if (element.type === 'stroke') return false;
                const box = bounds(element);
                return Math.abs(point.x - element.x) <= box.width / 2 && Math.abs(point.y - element.y) <= box.height / 2;
            });
            selected = element || null;
            if (!element) { render(); return; }
            snapshot();
            gesture = { id: event.pointerId, element, dx: element.x - point.x, dy: element.y - point.y };
        }
        canvas.setPointerCapture(event.pointerId);
        render();
    });
    canvas.addEventListener('pointermove', event => {
        if (!gesture || event.pointerId !== gesture.id) return;
        const point = imageEditorPoint(event, canvas.getBoundingClientRect(), canvas.width, canvas.height);
        if (gesture.element.type === 'stroke') gesture.element.points.push(point);
        else {
            const box = bounds(gesture.element);
            const insetX = Math.min(box.width / 2, canvas.width / 2), insetY = Math.min(box.height / 2, canvas.height / 2);
            gesture.element.x = Math.max(insetX, Math.min(canvas.width - insetX, point.x + gesture.dx));
            gesture.element.y = Math.max(insetY, Math.min(canvas.height - insetY, point.y + gesture.dy));
        }
        render();
    });
    canvas.addEventListener('pointerup', event => endGesture(event));
    canvas.addEventListener('pointercancel', event => endGesture(event, true));
    find('[data-image-close]').addEventListener('click', () => { if (!busy) dialog.close(); });
    dialog.addEventListener('cancel', event => { if (busy) event.preventDefault(); });
    dialog.addEventListener('close', () => {
        closed = true;
        abort.abort();
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        dialog.remove();
        activeViewer = null;
        document.body.classList.remove('chat-image-editor-open');
        const focus = accepted ? getComposer?.() : previousFocus;
        if (focus?.isConnected) focus.focus({ preventScroll: true });
    }, { once: true });
    edit.addEventListener('click', () => {
        editing = true;
        tools.hidden = false;
        edit.hidden = true;
        dialog.dataset.editing = 'true';
        status.textContent = 'Draw with your finger or mouse. Add text or stickers, then accept and send from chat.';
        setTool('draw');
    });
    find('[data-image-cancel]').addEventListener('click', () => {
        if (busy) return;
        editing = false;
        elements = []; history = []; selected = null; gesture = null;
        tools.hidden = true;
        edit.hidden = false;
        dialog.dataset.editing = 'false';
        status.textContent = 'Select Edit to draw, add text or stickers.';
        render();
        edit.focus();
    });
    dialog.querySelectorAll('[data-image-tool]').forEach(button => button.addEventListener('click', () => { if (!busy) setTool(button.dataset.imageTool); }));
    dialog.querySelectorAll('[data-image-sticker]').forEach(button => button.addEventListener('click', () => { if (!busy) addLabel('sticker', button.dataset.imageSticker); }));
    find('[data-image-add-text]').addEventListener('click', () => { if (!busy) { addLabel('text', textInput.value); textInput.value = ''; } });
    undo.addEventListener('click', () => {
        if (!history.length || busy) return;
        elements = JSON.parse(history.pop()); selected = null; gesture = null;
        render();
    });
    clear.addEventListener('click', () => {
        if (busy) return;
        snapshot(); elements = []; selected = null; gesture = null;
        render();
    });
    accept.addEventListener('click', async () => {
        if (busy || !elements.length || gesture) return;
        if (hasAttachment() && !window.confirm('Replace the attachment currently in your message draft with this edited picture?')) return;
        busy = true;
        render();
        status.textContent = 'Preparing edited picture…';
        try {
            render(false);
            const blob = await new Promise((resolve, reject) => canvas.toBlob(value => value ? resolve(value) : reject(new Error('Unable to prepare the picture. Please try again.')), 'image/png'));
            if (blob.size > 20 * 1024 * 1024) throw new Error('The edited picture exceeds the 20 MB attachment limit.');
            const file = new File([blob], editedImageName(name), { type: 'image/png', lastModified: Date.now() });
            if (await onAccept(file) === false) throw new Error('The edited picture was not attached. Please try again.');
            accepted = true;
            dialog.close();
        } catch (error) {
            status.textContent = error.message || 'Unable to attach the edited picture.';
        } finally {
            busy = false;
            if (!accepted) render();
        }
    });
    document.body.append(dialog);
    activeViewer = dialog;
    document.body.classList.add('chat-image-editor-open');
    dialog.showModal();
    find('[data-image-close]').focus();
    (async () => {
        try {
            const source = new URL(url, window.location.href);
            if (source.origin !== window.location.origin) throw new Error('This picture must be hosted on this system to edit it. Use Open original to view it.');
            const response = await fetch(source.href, { credentials: 'same-origin', signal: abort.signal });
            if (!response.ok || response.redirected) throw new Error('Unable to load this picture. Refresh chat and try again.');
            const blob = await response.blob();
            if (closed) return;
            if (!blob.type.startsWith('image/')) throw new Error('This attachment is not a supported picture.');
            objectUrl = URL.createObjectURL(blob);
            image = new Image();
            await new Promise((resolve, reject) => {
                image.onload = resolve;
                image.onerror = () => reject(new Error('Your browser could not open this image format.'));
                image.src = objectUrl;
            });
            if (closed) return;
            const dimensions = imageEditorSize(image.naturalWidth, image.naturalHeight);
            canvas.width = dimensions.width; canvas.height = dimensions.height;
            render();
            edit.disabled = false;
            status.textContent = 'Select Edit to draw, add text or stickers.';
        } catch (error) {
            if (!closed) status.textContent = error.message || 'Unable to open the picture.';
        }
    })();
}
