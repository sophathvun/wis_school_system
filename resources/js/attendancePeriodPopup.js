/** Keep controls in their student row while the popover escapes table clipping. */
export function initAttendancePeriodPopup(root) {
    let current;
    const close = () => {
        if (!current) return;
        current.panel.hidePopover?.();
        current.picker.open = false;
        current.summary.setAttribute('aria-expanded', 'false');
        current = null;
    };
    const position = () => {
        if (!current) return;
        const rect = current.summary.getBoundingClientRect();
        const viewport = window.visualViewport;
        const top = (viewport?.offsetTop || 0) + 12;
        const bottom = (viewport?.offsetTop || 0) + (viewport?.height || innerHeight) - 12;
        const width = Math.min(360, innerWidth - 24);
        Object.assign(current.panel.style, {
            width: `${width}px`, maxHeight: `${bottom - top}px`,
            left: `${Math.max(12, Math.min(rect.left, innerWidth - width - 12))}px`, top: '0px',
        });
        const height = current.panel.getBoundingClientRect().height;
        current.panel.style.top = `${Math.max(top, Math.min(rect.bottom + 8 + height <= bottom ? rect.bottom + 8 : rect.top - height - 8, bottom - height))}px`;
    };
    root.addEventListener('click', event => {
        const summary = event.target.closest('.att-period-picker > summary');
        if (!summary) return;
        event.preventDefault();
        const picker = summary.parentElement;
        if (current?.picker === picker) { close(); return; }
        close();
        const panel = picker.querySelector('.att-period-panel');
        panel.setAttribute('popover', 'manual');
        panel.setAttribute('role', 'group');
        panel.setAttribute('aria-label', 'Select absent periods');
        picker.open = true;
        summary.setAttribute('aria-expanded', 'true');
        current = {picker, panel, summary};
        panel.showPopover?.();
        position();
    });
    document.addEventListener('pointerdown', event => {
        if (current && !current.picker.contains(event.target)) close();
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && current) {
            const summary = current.summary;
            event.preventDefault(); close(); summary.focus();
        }
    });
    document.addEventListener('focusin', event => {
        if (current && !current.picker.contains(event.target)) close();
    });
    window.addEventListener('resize', position);
    window.addEventListener('scroll', position, {capture: true, passive: true});
    window.visualViewport?.addEventListener('resize', position);
    return {close};
}
