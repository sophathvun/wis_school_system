const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const pad = value => String(value).padStart(2, '0');
const iso = date => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
const parse = raw => {
    const named = /^(\d{1,2})-([A-Za-z]{3})-(\d{4})$/.exec(raw.trim());
    const numeric = /^(\d{4})-(\d{2})-(\d{2})$/.exec(raw.trim());
    if (!named && !numeric) return null;
    const year = Number(named ? named[3] : numeric[1]);
    const month = named ? months.findIndex(value => value.toLowerCase() === named[2].toLowerCase()) : Number(numeric[2]) - 1;
    const day = Number(named ? named[1] : numeric[3]);
    const date = new Date(year, month, day);
    return date.getFullYear() === year && date.getMonth() === month && date.getDate() === day ? date : null;
};
const display = value => {
    const date = parse(value);
    return `${pad(date.getDate())}-${months[date.getMonth()]}-${date.getFullYear()}`;
};

/** Editable, inclusive date range; submitted dates remain ISO values. */
export function initPremiumDateRangePicker(picker, onChange) {
    const input = picker.querySelector('input:not([type="hidden"])');
    const from = picker.querySelector('[name="date_from"]');
    const to = picker.querySelector('[name="date_to"]');
    const toggle = picker.querySelector('[data-range-toggle]');
    const popup = picker.querySelector('.date-picker-popup');
    let cursor = new Date(), start = '', end = '';
    popup.innerHTML = `<div class="date-picker-header"><button type="button" class="date-picker-nav" data-range-prev aria-label="Previous month"><i class="ti ti-chevron-left"></i></button><strong data-range-heading></strong><button type="button" class="date-picker-nav" data-range-next aria-label="Next month"><i class="ti ti-chevron-right"></i></button></div>
        <p class="text-secondary small px-3 mb-2" data-range-help aria-live="polite">Choose a start date, then an end date.</p>
        <div class="att-range-months" data-range-months></div>
        <div class="att-range-footer"><button type="button" class="btn btn-sm" data-range-today>Today</button><button type="button" class="btn btn-sm" data-range-clear>Clear</button><button type="button" class="btn btn-sm" data-range-cancel>Cancel</button><button type="button" class="btn btn-sm btn-primary" data-range-apply>Apply</button></div>`;
    const close = () => {
        popup.classList.add('d-none'); toggle.setAttribute('aria-expanded', 'false');
        picker.append(popup);
    };
    const commit = (first, last) => {
        from.value = first; to.value = last;
        input.value = first ? `${display(first)} to ${display(last)}` : '';
        input.setCustomValidity(''); close(); onChange();
    };
    const position = () => {
        if (popup.classList.contains('d-none')) return;
        const rect = picker.getBoundingClientRect(), width = Math.min(620, innerWidth - 24);
        Object.assign(popup.style, {position: 'fixed', width: `${width}px`, maxWidth: `${width}px`, maxHeight: `${innerHeight - 24}px`, overflowY: 'auto', left: `${Math.max(12, Math.min(rect.left, innerWidth - width - 12))}px`, top: '12px'});
        const height = popup.getBoundingClientRect().height;
        popup.style.top = `${Math.max(12, Math.min(rect.bottom + 8, innerHeight - height - 12))}px`;
    };
    const render = () => {
        const container = popup.querySelector('[data-range-months]'); container.replaceChildren();
        popup.querySelector('[data-range-heading]').textContent = `${months[cursor.getMonth()]} ${cursor.getFullYear()}`;
        popup.querySelector('[data-range-help]').textContent = !start ? 'Choose a start date, then an end date.' : !end ? `${display(start)} — choose an end date.` : `${display(start)} to ${display(end)}`;
        popup.querySelector('[data-range-apply]').disabled = !start || !end;
        for (let offset = 0; offset < 2; offset++) {
            const month = new Date(cursor.getFullYear(), cursor.getMonth() + offset, 1);
            const section = document.createElement('div'); section.className = 'att-range-month';
            const heading = document.createElement('strong'); heading.className = 'att-range-month-title'; heading.textContent = `${months[month.getMonth()]} ${month.getFullYear()}`; section.append(heading);
            const weekdays = document.createElement('div'); weekdays.className = 'date-picker-weekdays';
            ['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA'].forEach(day => {const label = document.createElement('span'); label.textContent = day; weekdays.append(label);}); section.append(weekdays);
            const grid = document.createElement('div'); grid.className = 'att-range-days';
            for (let blank = 0; blank < month.getDay(); blank++) grid.append(document.createElement('span'));
            const count = new Date(month.getFullYear(), month.getMonth() + 1, 0).getDate();
            for (let day = 1; day <= count; day++) {
                const value = iso(new Date(month.getFullYear(), month.getMonth(), day));
                const button = document.createElement('button'); button.type = 'button'; button.dataset.rangeDate = value; button.textContent = day;
                button.setAttribute('aria-label', display(value)); button.setAttribute('aria-pressed', String(value === start || value === end));
                button.classList.toggle('is-endpoint', value === start || value === end);
                button.classList.toggle('is-in-range', Boolean(start && end && value > start && value < end));
                button.classList.toggle('is-today', value === iso(new Date())); grid.append(button);
            }
            section.append(grid); container.append(section);
        }
        position();
    };
    const open = () => {
        start = from.value; end = to.value; cursor = parse(start) || new Date();
        document.body.append(popup); popup.classList.remove('d-none'); toggle.setAttribute('aria-expanded', 'true'); render();
    };
    toggle.addEventListener('click', () => popup.classList.contains('d-none') ? open() : close());
    popup.addEventListener('click', event => {
        const day = event.target.closest('[data-range-date]');
        if (day) {
            const value = day.dataset.rangeDate;
            if (!start || end) {start = value; end = '';}
            else {end = value; if (end < start) [start, end] = [end, start];}
            render(); popup.querySelector(`[data-range-date="${value}"]`)?.focus();
        }
    });
    popup.querySelector('[data-range-prev]').addEventListener('click', () => {cursor = new Date(cursor.getFullYear(), cursor.getMonth() - 1, 1); render();});
    popup.querySelector('[data-range-next]').addEventListener('click', () => {cursor = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 1); render();});
    popup.querySelector('[data-range-apply]').addEventListener('click', () => {commit(start, end); toggle.focus();});
    popup.querySelector('[data-range-today]').addEventListener('click', () => {const today = iso(new Date()); commit(today, today); toggle.focus();});
    popup.querySelector('[data-range-clear]').addEventListener('click', () => {commit('', ''); toggle.focus();});
    popup.querySelector('[data-range-cancel]').addEventListener('click', () => {close(); toggle.focus();});
    input.addEventListener('input', () => input.setCustomValidity(''));
    input.addEventListener('change', () => {
        if (!input.value.trim()) {commit('', ''); return;}
        const values = input.value.trim().split(/\s+to\s+/i);
        const first = parse(values[0] || ''), last = parse(values[1] || '');
        if (values.length !== 2 || !first || !last || first > last) {
            input.setCustomValidity('Enter a valid range, for example 01-Nov-2026 to 30-Nov-2026.'); input.reportValidity(); return;
        }
        commit(iso(first), iso(last));
    });
    document.addEventListener('click', event => {if (!event.composedPath().includes(picker) && !event.composedPath().includes(popup)) close();});
    popup.addEventListener('keydown', event => {if (event.key === 'Escape') {event.preventDefault(); close(); toggle.focus();}});
    window.addEventListener('resize', position);
    window.addEventListener('scroll', position, {capture: true, passive: true});
    return {close, valid: () => input.checkValidity()};
}
