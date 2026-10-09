export function bindChatComposerResize(input, form, messages) {
    const win = input.ownerDocument.defaultView;
    const nearBottom = () => messages.scrollHeight - messages.scrollTop - messages.clientHeight < 80;
    let pinned = nearBottom();
    let previousWidth = input.clientWidth;
    const refresh = () => {
        pinned = nearBottom();
        const scrollTop = messages.scrollTop;
        const styles = win.getComputedStyle(input);
        const borders = parseFloat(styles.borderTopWidth) + parseFloat(styles.borderBottomWidth);
        const minimum = Math.ceil((parseFloat(styles.lineHeight) || 20)
            + parseFloat(styles.paddingTop) + parseFloat(styles.paddingBottom) + borders);
        const available = messages.clientHeight + form.offsetHeight;
        const maximum = Math.max(minimum, Math.min(240, Math.floor(available * 0.4)));
        input.style.height = 'auto';
        const natural = input.scrollHeight + borders;
        input.style.height = `${Math.max(minimum, Math.min(natural, maximum))}px`;
        input.style.overflowY = natural > maximum ? 'auto' : 'hidden';
        messages.scrollTop = pinned ? messages.scrollHeight : scrollTop;
    };
    input.addEventListener('input', refresh);
    messages.addEventListener('scroll', () => { pinned = nearBottom(); }, { passive: true });
    win.addEventListener('resize', refresh);
    if (win.ResizeObserver) {
        new win.ResizeObserver(() => {
            if (input.clientWidth !== previousWidth) {
                previousWidth = input.clientWidth;
                refresh();
            }
            if (pinned) messages.scrollTop = messages.scrollHeight;
        }).observe(form);
    }
    refresh();
    return { refresh };
}
