import { reactionOptions } from './chatReactions.js';

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

export function renderMessageMenu(message) {
    const selected = (message.reactions || []).find((reaction) => reaction.reacted)?.emoji;
    const reactions = message.can_react ? `<div class="chat-menu-reactions" role="group" aria-label="React to message">${reactionOptions.map(({ emoji, label }) => `<button type="button" data-menu-reaction="${emoji}" aria-label="${label}" aria-pressed="${selected === emoji}" title="${label}">${emoji}</button>`).join('')}</div>` : '';
    return `${reactions}${message.can_reply ? '<button type="button" class="chat-menu-action" data-menu-reply role="menuitem"><i class="ti ti-arrow-back-up"></i><span>Reply</span></button>' : ''}${message.can_delete ? '<button type="button" class="chat-menu-action chat-menu-delete" data-menu-delete role="menuitem"><i class="ti ti-trash"></i><span>Delete</span></button>' : ''}`;
}

export function createMessageReplyController(root, { form, input, getMessage, getConversationId, onMissing, onReact, onDelete }) {
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
    let menuConversationId = null;
    let menuPoint = null;
    let menuBusy = false;
    let pressTimer = null;
    let pressPoint = null;
    let suppressClickUntil = 0;
    const closeMenu = () => { menu.hidden = true; menuMessageId = null; menuConversationId = null; menuPoint = null; };
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
    const canOpenMenu = (message) => message && (message.can_react || message.can_reply || message.can_delete);
    const showMenu = (id, x, y, focus = true) => {
        const message = getMessage(id);
        if (!canOpenMenu(message)) return;
        if (focus) doc.dispatchEvent(new win.CustomEvent('chat-message-menu-open', { detail: menu }));
        menuMessageId = id;
        menuConversationId = getConversationId();
        menuPoint = { x, y };
        menu.innerHTML = renderMessageMenu(message);
        menu.hidden = false;
        menu.style.left = `${Math.max(8, Math.min(x, win.innerWidth - menu.offsetWidth - 8))}px`;
        menu.style.top = `${Math.max(8, Math.min(y, win.innerHeight - menu.offsetHeight - 8))}px`;
        if (focus) menu.querySelector('button')?.focus();
    };
    const messageBubble = (event) => {
        if (event.target.closest('a, audio, button, input, textarea')) return null;
        const bubble = event.target.closest('[data-message-id]');
        return bubble && root.contains(bubble) ? bubble : null;
    };
    root.addEventListener('contextmenu', (event) => {
        const bubble = messageBubble(event);
        if (!bubble || !canOpenMenu(getMessage(Number(bubble.dataset.messageId)))) return;
        event.preventDefault();
        event.stopPropagation();
        showMenu(Number(bubble.dataset.messageId), event.clientX, event.clientY);
    });
    menu.addEventListener('click', async (event) => {
        const button = event.target.closest('button');
        if (!button || menuMessageId === null || menuBusy) return;
        const id = menuMessageId;
        const conversationId = menuConversationId;
        const message = getMessage(id);
        if (conversationId !== getConversationId() || !message) { closeMenu(); return; }
        if (button.hasAttribute('data-menu-reply')) { select(id); return; }
        if (button.hasAttribute('data-menu-delete') && message.can_delete) { closeMenu(); onDelete?.(id, Boolean(message.can_delete_for_everyone)); return; }
        if (button.hasAttribute('data-menu-reaction') && message.can_react) {
            menuBusy = true;
            menu.querySelectorAll('button').forEach((item) => { item.disabled = true; });
            try {
                const saved = await onReact?.(id, button.dataset.menuReaction);
                if (menuMessageId === id && menuConversationId === conversationId) {
                    if (saved) closeMenu();
                    else showMenu(id, menuPoint.x, menuPoint.y);
                }
            } finally { menuBusy = false; }
        }
    });
    banner.addEventListener('click', (event) => {
        if (event.target.closest('[data-cancel-reply]')) { clear(); input.focus(); }
    });
    root.addEventListener('click', (event) => {
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
        else if (event.pointerType === 'touch' || win.matchMedia('(pointer: coarse)').matches || win.matchMedia('(max-width: 767px)').matches) {
            const bubble = messageBubble(event);
            if (!bubble || !canOpenMenu(getMessage(Number(bubble.dataset.messageId)))) return;
            event.preventDefault(); event.stopImmediatePropagation();
            showMenu(Number(bubble.dataset.messageId), event.clientX, event.clientY);
        }
    }, true);
    root.addEventListener('scroll', () => { closeMenu(); cancelPress(); });
    doc.addEventListener('click', (event) => { if (!menu.contains(event.target)) closeMenu(); });
    doc.addEventListener('chat-message-menu-open', (event) => { if (event.detail !== menu) closeMenu(); });
    win.addEventListener('resize', closeMenu);
    menu.addEventListener('keydown', (event) => {
        if (!['ArrowDown', 'ArrowUp', 'ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        const buttons = [...menu.querySelectorAll('button:not(:disabled)')];
        const index = buttons.indexOf(doc.activeElement);
        const next = event.key === 'Home' ? 0 : event.key === 'End' ? buttons.length - 1 : (index + (['ArrowUp', 'ArrowLeft'].includes(event.key) ? -1 : 1) + buttons.length) % buttons.length;
        buttons[next]?.focus(); event.preventDefault();
    });
    doc.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !menu.hidden) {
            const bubble = root.querySelector(`[data-message-id="${menuMessageId}"]`);
            closeMenu(); if (bubble) { bubble.tabIndex = 0; bubble.focus(); } event.preventDefault();
        }
    });
    const refresh = () => {
        if (menuMessageId !== null) {
            if (menuConversationId !== getConversationId() || !canOpenMenu(getMessage(menuMessageId))) closeMenu();
            else if (!menuBusy) {
                const focusedLabel = menu.contains(doc.activeElement) ? doc.activeElement.getAttribute('aria-label') || doc.activeElement.textContent.trim() : null;
                showMenu(menuMessageId, menuPoint.x, menuPoint.y, false);
                if (focusedLabel) [...menu.querySelectorAll('button')].find((button) => (button.getAttribute('aria-label') || button.textContent.trim()) === focusedLabel)?.focus();
            }
        }
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
