/** Navigate daily registers through the existing date filter and change guards. */
export function initAttendanceDateNavigation(root, calendar) {
    const navigation = root.querySelector('[data-att-date-navigation]');
    const filters = root.querySelector('[data-att-filters]');
    const value = filters.elements.date;
    const input = root.querySelector('[data-premium-date-input]');
    const days = navigation.querySelector('[data-att-date-days]');
    const pad = number => String(number).padStart(2, '0');
    const iso = date => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
    const parse = raw => {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(raw)) return null;
        const [year, month, day] = raw.split('-').map(Number);
        const date = new Date(year, month - 1, day);
        return iso(date) === raw ? date : null;
    };
    const render = () => {
        const selected = parse(value.value);
        if (!selected) return;
        // A cancelled filter change restores the hidden value; mirror it visibly.
        input.value = `${pad(selected.getDate())}-${pad(selected.getMonth() + 1)}-${selected.getFullYear()}`;
        navigation.querySelector('[data-att-date-month]').textContent = selected.toLocaleDateString('en-GB', {month: 'long', year: 'numeric'});
        days.replaceChildren(...Array.from({length: 7}, (_, index) => {
            const date = new Date(selected);
            date.setDate(date.getDate() + index - 3);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `att-date-day${index === 3 ? ' is-selected' : ''}`;
            button.dataset.attDate = iso(date);
            button.setAttribute('aria-label', date.toLocaleDateString('en-GB', {weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'}));
            if (index === 3) button.setAttribute('aria-current', 'date');
            const weekday = document.createElement('small');
            weekday.textContent = date.toLocaleDateString('en-GB', {weekday: 'short'});
            const label = document.createElement('strong');
            label.textContent = date.toLocaleDateString('en-GB', {day: '2-digit', month: 'short'});
            button.append(weekday, label);
            return button;
        }));
    };
    navigation.addEventListener('click', event => {
        const button = event.target.closest('button');
        if (!button) return;
        let date = parse(value.value) || new Date();
        if (button.hasAttribute('data-att-date-today')) date = new Date();
        else if (button.dataset.attDate) date = parse(button.dataset.attDate);
        else if (button.dataset.attDateOffset) date.setDate(date.getDate() + Number(button.dataset.attDateOffset));
        else return;
        if (iso(date) !== value.value) calendar.setValue(iso(date));
        render();
    });
    // Capture runs even when unsaved-change guards cancel the filter event.
    filters.addEventListener('change', event => {
        if (event.target === value) queueMicrotask(render);
    }, true);
    render();
}
