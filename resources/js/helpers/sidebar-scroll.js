// Keep navigation scroll separate from the document's scroll restoration.
export function preserveSidebarScroll(sidebar, menu, {
    storage = null,
    enabled = () => window.matchMedia('(min-width: 992px)').matches,
    events = window,
} = {}) {
    const key = `schoolSidebarScroll:${sidebar.dataset.userId || 'current'}`;
    const containers = () => [
        ['main', menu],
        ...Array.from(menu.querySelectorAll('.dropdown-menu')).map((element) => {
            const item = element.closest('.nav-item.dropdown');
            const label = item?.querySelector(':scope > .nav-link .nav-link-title')?.textContent.trim();
            return [label ? `group:${label}` : null, element];
        }).filter(([name]) => name),
    ];
    const save = () => {
        if (!enabled()) return;
        try {
            (storage ?? events.sessionStorage).setItem(key, JSON.stringify(Object.fromEntries(
                containers().map(([name, element]) => [name, element.scrollTop]),
            )));
        } catch (_) { /* Navigation still works when browser storage is blocked. */ }
    };
    const restore = () => {
        if (!enabled()) return;
        try {
            const state = JSON.parse((storage ?? events.sessionStorage).getItem(key));
            if (!state || typeof state !== 'object') return;
            containers().forEach(([name, element]) => {
                const top = state[name];
                if (typeof top === 'number' && Number.isFinite(top) && top >= 0) {
                    element.scrollTop = top;
                }
            });
        } catch (_) { /* Ignore old or invalid state. */ }
    };
    // Capture before page navigation, including keyboard-activated links.
    sidebar.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (link && !event.defaultPrevented && link.getAttribute('href') !== '#') save();
    }, true);
    events.addEventListener('pagehide', save);
    events.addEventListener('pageshow', restore);
    restore();
    return {save, restore};
}
