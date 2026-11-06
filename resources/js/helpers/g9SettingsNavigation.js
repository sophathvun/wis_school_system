export function setupG9SettingsNavigation(root, onChange = () => {}) {
    const toggle = root.querySelector('[data-settings-nav-toggle]');
    const body = root.querySelector('[data-settings-nav-body]');
    const tabs = [...root.querySelectorAll('[data-settings-tab]')];
    const panels = [...root.querySelectorAll('[data-settings-panel]')];
    const form = root.querySelector('[data-date-settings]');
    if (!toggle || !body || !tabs.length) return {activate() {}};

    const mobile = window.matchMedia('(max-width: 767.98px)');
    const setExpanded = expanded => {
        toggle.setAttribute('aria-expanded', String(expanded));
        toggle.setAttribute('aria-label', `${expanded ? 'Collapse' : 'Expand'} settings navigation`);
        toggle.querySelector('i').className = `ti ti-layout-sidebar-left-${expanded ? 'collapse' : 'expand'}`;
        body.hidden = !expanded;
        root.classList.toggle('is-nav-collapsed', !expanded);
    };
    const activate = key => {
        if (!tabs.some(tab => tab.dataset.settingsTab === key)) return;
        onChange();
        tabs.forEach(tab => {
            const selected = tab.dataset.settingsTab === key;
            tab.setAttribute('aria-selected', String(selected));
            tab.tabIndex = selected ? 0 : -1;
        });
        panels.forEach(panel => panel.hidden = panel.dataset.settingsPanel !== key);
        form.hidden = key === 'campus';
    };
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => {
            activate(tab.dataset.settingsTab);
            if (mobile.matches) { setExpanded(false); toggle.focus(); }
        });
        tab.addEventListener('keydown', event => {
            let next;
            if (['ArrowDown', 'ArrowRight'].includes(event.key)) next = (index + 1) % tabs.length;
            else if (['ArrowUp', 'ArrowLeft'].includes(event.key)) next = (index - 1 + tabs.length) % tabs.length;
            else if (event.key === 'Home') next = 0;
            else if (event.key === 'End') next = tabs.length - 1;
            else return;
            event.preventDefault();
            activate(tabs[next].dataset.settingsTab);
            tabs[next].focus();
        });
    });
    toggle.addEventListener('click', () => setExpanded(toggle.getAttribute('aria-expanded') !== 'true'));
    mobile.addEventListener('change', () => setExpanded(!mobile.matches));
    setExpanded(!mobile.matches);
    activate('center');
    return {activate};
}
