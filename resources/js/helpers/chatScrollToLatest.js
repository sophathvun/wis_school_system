export function bindChatScrollToLatest(messages, button, jump) {
    const refresh = () => {
        button.hidden = messages.scrollHeight - messages.scrollTop - messages.clientHeight < 80;
    };
    messages.addEventListener('scroll', refresh, { passive: true });
    messages.addEventListener('load', refresh, true);
    button.addEventListener('click', () => { jump(); refresh(); });
    if (window.ResizeObserver) new ResizeObserver(refresh).observe(messages);
    new MutationObserver(() => requestAnimationFrame(refresh)).observe(messages, { childList: true, subtree: true });
    refresh();
    return { refresh };
}
