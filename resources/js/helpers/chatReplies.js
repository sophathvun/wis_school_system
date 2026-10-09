const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
}[char]));

export function replyPreview(message) {
    const text = String(message.message || message.media_name || '').replace(/\s+/g, ' ').trim();
    const label = ({ image: 'Photo', file: 'File', voice: 'Voice message', call: 'Voice call' })[message.message_type];
    return (label ? `${label}${text && text !== label ? `: ${text}` : ''}` : (text || 'Message')).slice(0, 180);
}

export function renderReplyQuote(message) {
    const reply = message.reply_to;
    if (!reply) return '';
    if (reply.unavailable) return '<div class="chat-reply-quote chat-reply-unavailable">Message unavailable</div>';
    return `<button type="button" class="chat-reply-quote" data-jump-reply="${Number(reply.id)}" aria-label="Go to replied message"><strong>${escapeHtml(reply.user_name || 'Staff')}</strong><span>${escapeHtml(replyPreview(reply))}</span></button>`;
}

export function renderReplyAction(message) {
    return message.can_reply ? `<button type="button" class="chat-reply-button" data-reply-message="${Number(message.id)}"><i class="ti ti-arrow-back-up"></i><span>Reply</span></button>` : '';
}

export function createMessageReplyController(root, { form, input, getMessage, getConversationId, onMissing }) {
    const doc = root.ownerDocument;
    const win = doc.defaultView;
    const banner = doc.createElement('div');
    banner.className = 'chat-reply-composer';
    banner.hidden = true;
    banner.setAttribute('aria-live', 'polite');
    form.prepend(banner);
    const menu = doc.createElement('div');
    menu.className = 'chat-message-context-menu';
    menu.setAttribute('role', 'menu');
    menu.hidden = true;
    doc.body.append(menu);
    let selection = null;
    let menuMessageId = null;
    let pressTimer = null;
    let pressPoint = null;
    let suppressClickUntil = 0;
    const closeMenu = () => { menu.hidden = true; menuMessageId = null; };
    const clear = () => { selection = null; banner.hidden = true; banner.innerHTML = ''; closeMenu(); };
    const showBanner = (message) => {
        banner.innerHTML = `<div class="chat-reply-composer-text"><strong>Replying to ${escapeHtml(message.user_name || 'Staff')}</strong><span>${escapeHtml(replyPreview(message))}</span></div><button type="button" data-cancel-reply aria-label="Cancel reply"><i class="ti ti-x"></i></button>`;
        banner.hidden = false;
    };
    const select = (id) => {
        const message = getMessage(id);
        if (!message?.can_reply) return;
        selection = { id, conversationId: getConversationId() };
        showBanner(message);
        closeMenu();
        input.focus();
    };
    const showMenu = (id, x, y) => {
        if (!getMessage(id)?.can_reply) return;
        menuMessageId = id;
        menu.innerHTML = '<button type="button" role="menuitem"><i class="ti ti-arrow-back-up"></i> Reply</button>';
        menu.hidden = false;
        menu.style.left = `${Math.max(8, Math.min(x, win.innerWidth - menu.offsetWidth - 8))}px`;
        menu.style.top = `${Math.max(8, Math.min(y, win.innerHeight - menu.offsetHeight - 8))}px`;
        menu.querySelector('button').focus();
    };
    const messageBubble = (event) => {
        if (event.target.closest('a, audio, button, input, textarea')) return null;
        const bubble = event.target.closest('[data-message-id]');
        return bubble && root.contains(bubble) ? bubble : null;
    };
    root.addEventListener('contextmenu', (event) => {
        const bubble = messageBubble(event);
        if (!bubble || !getMessage(Number(bubble.dataset.messageId))?.can_reply) return;
        event.preventDefault();
        event.stopPropagation();
        showMenu(Number(bubble.dataset.messageId), event.clientX, event.clientY);
    });
    menu.addEventListener('click', (event) => {
        if (event.target.closest('button') && menuMessageId !== null) select(menuMessageId);
    });
    banner.addEventListener('click', (event) => {
        if (event.target.closest('[data-cancel-reply]')) { clear(); input.focus(); }
    });
    root.addEventListener('click', (event) => {
        const reply = event.target.closest('[data-reply-message]');
        if (reply) { event.preventDefault(); event.stopPropagation(); select(Number(reply.dataset.replyMessage)); return; }
        const quote = event.target.closest('[data-jump-reply]');
        if (!quote) return;
        event.preventDefault();
        event.stopPropagation();
        const target = root.querySelector(`[data-message-id="${Number(quote.dataset.jumpReply)}"]`);
        if (!target) { onMissing?.('The original message is outside the loaded history.'); return; }
        target.scrollIntoView({ block: 'center', behavior: 'smooth' });
        target.classList.add('chat-reply-highlight');
        win.setTimeout(() => target.classList.remove('chat-reply-highlight'), 1600);
    });
    const cancelPress = () => { win.clearTimeout(pressTimer); pressTimer = null; pressPoint = null; };
    root.addEventListener('pointerdown', (event) => {
        if (event.pointerType !== 'touch') return;
        const bubble = messageBubble(event);
        if (!bubble) return;
        cancelPress();
        pressPoint = { x: event.clientX, y: event.clientY };
        pressTimer = win.setTimeout(() => {
            suppressClickUntil = Date.now() + 800;
            showMenu(Number(bubble.dataset.messageId), event.clientX, event.clientY);
        }, 500);
    });
    root.addEventListener('pointermove', (event) => {
        if (pressPoint && Math.hypot(event.clientX - pressPoint.x, event.clientY - pressPoint.y) > 10) cancelPress();
    });
    root.addEventListener('pointerup', cancelPress);
    root.addEventListener('pointercancel', cancelPress);
    root.addEventListener('click', (event) => {
        if (Date.now() < suppressClickUntil) { event.preventDefault(); event.stopImmediatePropagation(); }
    }, true);
    root.addEventListener('scroll', () => { closeMenu(); cancelPress(); });
    doc.addEventListener('click', (event) => { if (!menu.contains(event.target)) closeMenu(); });
    doc.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !menu.hidden) {
            closeMenu(); input.focus(); event.preventDefault();
        }
    });
    const refresh = () => {
        closeMenu();
        cancelPress();
        if (!selection) return;
        const message = getMessage(selection.id);
        if (selection.conversationId !== getConversationId() || !message) clear();
        else showBanner(message);
    };
    return {
        refresh,
        clear,
        appendTo(formData) {
            if (selection?.conversationId === getConversationId()) formData.append('reply_to_message_id', selection.id);
            return selection;
        },
        sent(token) { if (selection === token) clear(); },
    };
}
