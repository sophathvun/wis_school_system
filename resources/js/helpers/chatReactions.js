export const reactionOptions = [
    { emoji: '\u2764\uFE0F', label: 'Heart' },
    { emoji: '\u{1F64F}', label: 'Thanks' },
    { emoji: '\u{1F44D}', label: 'Thumbs up' },
    { emoji: '\u{1F602}', label: 'Laugh' },
    { emoji: '\u{1F62E}', label: 'Surprised' },
    { emoji: '\u{1F622}', label: 'Sad' },
];

const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
}[char]));

export function renderMessageReactions(message) {
    if (!message.can_react) return '';
    const id = Number(message.id);
    const selected = (message.reactions || []).find((reaction) => reaction.reacted)?.emoji;
    const buttons = reactionOptions.map(({ emoji, label }) => {
        const reaction = (message.reactions || []).find((item) => item.emoji === emoji);
        if (!reaction) return '';
        const names = (reaction.users || []).map((user) => user.name).join(', ');
        return `<button type="button" class="chat-reaction-chip" data-reaction-emoji="${emoji}" aria-pressed="${Boolean(reaction.reacted)}" aria-label="${label}: ${Number(reaction.count)}${reaction.reacted ? ', your reaction. Click to remove' : '. Click to react'}" title="${escapeHtml(names)}"><span>${emoji}</span><span>${Number(reaction.count)}</span></button>`;
    }).join('');
    return `<div class="chat-message-reactions" data-reaction-message="${id}">
        <div class="chat-reaction-summary">${buttons}<button type="button" class="chat-react-button" data-reaction-picker aria-expanded="false" aria-label="React to message"><i class="ti ti-mood-smile"></i><span>React</span></button></div>
        <div class="chat-reaction-picker" role="group" aria-label="Choose a reaction" hidden>${reactionOptions.map(({ emoji, label }) => `<button type="button" data-reaction-emoji="${emoji}" aria-label="${label}" aria-pressed="${selected === emoji}" title="${label}">${emoji}</button>`).join('')}</div>
    </div>`;
}

export function createMessageReactionController(root, { api, baseUrl, getMessage, onError }) {
    let openId = null;
    const pending = new Set();
    const refresh = () => {
        root.querySelectorAll('[data-reaction-message]').forEach((container) => {
            const id = Number(container.dataset.reactionMessage);
            const open = id === openId;
            container.querySelector('.chat-reaction-picker').hidden = !open;
            container.querySelector('[data-reaction-picker]').setAttribute('aria-expanded', String(open));
            container.querySelectorAll('button').forEach((button) => { button.disabled = pending.has(id); });
        });
    };
    root.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-reaction-picker], [data-reaction-emoji]');
        if (!button || !root.contains(button)) return;
        event.preventDefault();
        event.stopPropagation();
        const container = button.closest('[data-reaction-message]');
        const id = Number(container.dataset.reactionMessage);
        if (pending.has(id)) return;
        if (button.hasAttribute('data-reaction-picker')) {
            openId = openId === id ? null : id;
            refresh();
            if (openId !== null) container.querySelector('.chat-reaction-picker button')?.focus();
            return;
        }
        const message = getMessage(id);
        const emoji = button.dataset.reactionEmoji;
        if (!message || !reactionOptions.some((option) => option.emoji === emoji)) return;
        const remove = (message.reactions || []).some((reaction) => reaction.emoji === emoji && reaction.reacted);
        pending.add(id);
        openId = null;
        refresh();
        try {
            const result = await api(`${baseUrl}/messages/${id}/reaction`, {
                method: remove ? 'DELETE' : 'PUT',
                ...(remove ? {} : { body: JSON.stringify({ emoji }) }),
            });
            const current = getMessage(id);
            if (current) {
                current.reactions = result.reactions || [];
                const live = root.querySelector(`[data-reaction-message="${id}"]`);
                if (live) live.outerHTML = renderMessageReactions(current);
            }
        } catch (error) {
            onError(error.message || 'Unable to save your reaction. Please try again.');
        } finally {
            pending.delete(id);
            refresh();
        }
    });
    root.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || openId === null) return;
        const button = root.querySelector(`[data-reaction-message="${openId}"] [data-reaction-picker]`);
        openId = null;
        refresh();
        button?.focus();
        event.stopPropagation();
    });
    root.ownerDocument.addEventListener('click', (event) => {
        if (!event.target.closest('.chat-message-reactions')) {
            openId = null;
            refresh();
        }
    });
    return { refresh };
}
