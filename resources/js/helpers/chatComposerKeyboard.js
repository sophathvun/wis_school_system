export function bindChatComposerKeyboard(input, form) {
    const win = input.ownerDocument.defaultView;
    const isPhone = () => Boolean(win.navigator.userAgentData?.mobile)
        || /iPhone|iPod|Android.*Mobile|Windows Phone/i.test(win.navigator.userAgent || '')
        || win.matchMedia('(max-width: 767px) and (pointer: coarse)').matches;
    const phone = isPhone();
    input.setAttribute('enterkeyhint', phone ? 'enter' : 'send');
    input.title = phone
        ? 'Enter for a new line. Use the Send button to send. Paste an image or file with Ctrl+V.'
        : 'Enter to send; Shift+Enter for a new line. Paste an image or file with Ctrl+V.';
    if (phone) input.removeAttribute('aria-keyshortcuts');
    input.addEventListener('keydown', (event) => {
        if (isPhone()) return;
        if (event.key !== 'Enter' || event.shiftKey || event.ctrlKey || event.altKey || event.metaKey
            || event.isComposing || event.keyCode === 229) return;
        event.preventDefault();
        if (!event.repeat) form.requestSubmit();
    });
}
