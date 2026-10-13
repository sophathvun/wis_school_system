export function setupStaffAddressSizing(form) {
    const addresses = [...form.querySelectorAll('.staff-address-details-grid textarea, [data-staff-emergency-contact] textarea')];
    if (!addresses.length) return;
    let frame;
    const resize = () => {
        frame = null;
        addresses.forEach(input => {
            if (!input.getClientRects().length) return;
            const styles = getComputedStyle(input);
            const minimum = parseFloat(styles.minHeight) || 52;
            const borders = parseFloat(styles.borderTopWidth) + parseFloat(styles.borderBottomWidth);
            input.style.height = `${minimum}px`;
            input.style.height = `${Math.max(minimum,Math.ceil(input.scrollHeight + borders))}px`;
        });
    };
    const schedule = () => { if (!frame) frame = requestAnimationFrame(resize); };
    addresses.forEach(input => {
        input.addEventListener('input',schedule);
        input.addEventListener('change',schedule);
    });
    form.addEventListener('reset',schedule);
    form.addEventListener('shown.bs.tab',schedule);
    form.closest('.modal')?.addEventListener('shown.bs.modal',schedule);
    window.addEventListener('resize',schedule);
    if (typeof ResizeObserver !== 'undefined') {
        const widths = new WeakMap();
        const observer = new ResizeObserver(entries => {
            entries.forEach(entry => {
                if (widths.get(entry.target) === entry.contentRect.width) return;
                widths.set(entry.target,entry.contentRect.width);
                schedule();
            });
        });
        addresses.forEach(input => observer.observe(input));
    }
    document.fonts?.ready.then(schedule);
    schedule();
}
