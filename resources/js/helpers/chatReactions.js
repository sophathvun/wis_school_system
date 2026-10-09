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
    const buttons = reactionOptions.map(({ emoji, label }) => {
        const reaction = (message.reactions || []).find((item) => item.emoji === emoji);
        if (!reaction) return '';
        const names = (reaction.users || []).map((user) => user.name).join(', ');
        return `<button type="button" class="chat-reaction-chip" data-reaction-emoji="${emoji}" aria-pressed="${Boolean(reaction.reacted)}" aria-label="${label}: ${Number(reaction.count)}${reaction.reacted ? ', your reaction. Click to remove' : '. Click to react'}" title="${escapeHtml(names)}"><span>${emoji}</span><span>${Number(reaction.count)}</span></button>`;
    }).join('');
    return buttons ? `<div class="chat-message-reactions" data-reaction-message="${id}"><div class="chat-reaction-counts">${buttons}</div></div>` : '';
}

export function createMessageReactionController(root, { api, baseUrl, getMessage, onError }) {
    const pending = new Set();
    const refresh = () => {
        root.querySelectorAll('[data-reaction-message]').forEach((container) => {
            const id = Number(container.dataset.reactionMessage);
            container.querySelectorAll('button').forEach((button) => { button.disabled = pending.has(id); });
        });
    };
    const react = async (id, emoji) => {
        if (pending.has(id)) return false;
        const message = getMessage(id);
        if (!message?.can_react || !reactionOptions.some((option) => option.emoji === emoji)) return false;
        const remove = (message.reactions || []).some((reaction) => reaction.emoji === emoji && reaction.reacted);
        pending.add(id);
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
                const markup = renderMessageReactions(current);
                if (live) live.outerHTML = markup;
                else root.querySelector(`[data-message-id="${id}"] .chat-reaction-slot`)?.insertAdjacentHTML('beforeend', markup);
            }
            return true;
        } catch (error) {
            onError(error.message || 'Unable to save your reaction. Please try again.');
            return false;
        } finally {
            pending.delete(id);
            refresh();
        }
    };
    root.addEventListener('click', (event) => {
        const button = event.target.closest('[data-reaction-emoji]');
        if (!button || !root.contains(button)) return;
        event.preventDefault();
        event.stopPropagation();
        react(Number(button.closest('[data-reaction-message]').dataset.reactionMessage), button.dataset.reactionEmoji);
    });
    return { refresh, react };
}
