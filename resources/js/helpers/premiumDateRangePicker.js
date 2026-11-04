const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const pad = value => String(value).padStart(2, '0');
const iso = date => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
const todayDate = () => {
    const parts = new Intl.DateTimeFormat('en-CA', {timeZone: 'Asia/Bangkok', year: 'numeric', month: '2-digit', day: '2-digit'}).formatToParts(new Date());
    const value = type => parts.find(part => part.type === type).value;
    return new Date(Number(value('year')), Number(value('month')) - 1, Number(value('day')));
};
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
    let cursor = todayDate(), start = '', end = '', preset = 'custom';
    popup.innerHTML = `<div class="att-range-toolbar"><div class="att-range-presets" role="group" aria-label="Date range presets"><button type="button" data-range-preset="today">Today</button><button type="button" data-range-preset="week">Last 7 Days</button><button type="button" data-range-preset="month">This Month</button><button type="button" data-range-preset="custom">Custom</button></div><button type="button" class="btn btn-sm att-range-apply" data-range-apply><i class="ti ti-check me-1" aria-hidden="true"></i>Apply Range</button></div>
        <div class="att-range-workspace"><aside class="att-range-summary" aria-live="polite"><i class="ti ti-calendar-event" aria-hidden="true"></i><strong>Selected Range</strong><span data-range-start>Start date</span><i class="ti ti-arrow-down" aria-hidden="true"></i><span data-range-end>End date</span><span class="att-range-duration" data-range-duration>Choose dates</span></aside>
        <div class="att-range-calendar"><div class="date-picker-header"><button type="button" class="date-picker-nav" data-range-prev aria-label="Previous month"><i class="ti ti-chevron-left"></i></button><select data-range-month aria-label="Month"></select><input type="number" min="1900" max="2200" data-range-year aria-label="Year"><button type="button" class="date-picker-nav" data-range-next aria-label="Next month"><i class="ti ti-chevron-right"></i></button></div>
        <p class="text-secondary small px-3 mb-2" data-range-help aria-live="polite">Choose a start date, then an end date.</p><div class="att-range-months" data-range-months></div></div></div>
        <div class="att-range-footer"><button type="button" class="btn btn-sm" data-range-clear>Clear</button><button type="button" class="btn btn-sm" data-range-cancel>Cancel</button></div>`;
    const monthSelect = popup.querySelector('[data-range-month]');
    months.forEach((month, index) => monthSelect.add(new Option(month, index)));
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
        monthSelect.value = cursor.getMonth();
        popup.querySelector('[data-range-year]').value = cursor.getFullYear();
        popup.querySelector('[data-range-start]').textContent = start ? display(start) : 'Start date';
        popup.querySelector('[data-range-end]').textContent = end ? display(end) : 'End date';
        const days = start && end ? Math.round((Date.UTC(parse(end).getFullYear(), parse(end).getMonth(), parse(end).getDate()) - Date.UTC(parse(start).getFullYear(), parse(start).getMonth(), parse(start).getDate())) / 86400000) + 1 : 0;
        popup.querySelector('[data-range-duration]').textContent = days ? `${days} ${days === 1 ? 'Day' : 'Days'}` : 'Choose dates';
        popup.querySelectorAll('[data-range-preset]').forEach(button => {button.classList.toggle('is-active', button.dataset.rangePreset === preset); button.setAttribute('aria-pressed', String(button.dataset.rangePreset === preset));});
        popup.querySelector('[data-range-help]').textContent = !start ? 'Choose a start date, then an end date.' : !end ? `${display(start)} — choose an end date.` : `${display(start)} to ${display(end)}`;
        popup.querySelector('[data-range-apply]').disabled = !start || !end;
        for (let offset = 0; offset < 1; offset++) {
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
                button.classList.toggle('is-today', value === iso(todayDate())); grid.append(button);
            }
            section.append(grid); container.append(section);
        }
        position();
    };
    const open = () => {
        document.dispatchEvent(new CustomEvent('premium-range-open', {detail: picker}));
        start = from.value; end = to.value; cursor = parse(start) || todayDate(); preset = 'custom';
        document.body.append(popup); popup.classList.remove('d-none'); toggle.setAttribute('aria-expanded', 'true'); render();
    };
    toggle.addEventListener('click', () => popup.classList.contains('d-none') ? open() : close());
    popup.addEventListener('click', event => {
        const day = event.target.closest('[data-range-date]');
        if (day) {
            preset = 'custom';
            const value = day.dataset.rangeDate;
            if (!start || end) {start = value; end = '';}
            else {end = value; if (end < start) [start, end] = [end, start];}
            render(); popup.querySelector(`[data-range-date="${value}"]`)?.focus();
        }
    });
    monthSelect.addEventListener('change', () => {cursor = new Date(cursor.getFullYear(), Number(monthSelect.value), 1); render();});
    popup.querySelector('[data-range-year]').addEventListener('change', event => {
        const year = Math.min(2200, Math.max(1900, Number(event.target.value) || todayDate().getFullYear()));
        cursor = new Date(year, cursor.getMonth(), 1); render();
    });
    popup.querySelectorAll('[data-range-preset]').forEach(button => button.addEventListener('click', () => {
        preset = button.dataset.rangePreset;
        if (preset === 'custom') {render(); return;}
        const today = todayDate(), first = new Date(today), last = new Date(today);
        if (preset === 'week') first.setDate(first.getDate() - 6);
        if (preset === 'month') {first.setDate(1); last.setMonth(last.getMonth() + 1, 0);}
        start = iso(first); end = iso(last); cursor = first; render();
    }));
    popup.querySelector('[data-range-prev]').addEventListener('click', () => {cursor = new Date(cursor.getFullYear(), cursor.getMonth() - 1, 1); render();});
    popup.querySelector('[data-range-next]').addEventListener('click', () => {cursor = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 1); render();});
    popup.querySelector('[data-range-apply]').addEventListener('click', () => {commit(start, end); toggle.focus();});
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
    document.addEventListener('premium-range-open', event => {if (event.detail !== picker) close();});
    popup.addEventListener('keydown', event => {if (event.key === 'Escape') {event.preventDefault(); close(); toggle.focus();}});
    window.addEventListener('resize', position);
    window.addEventListener('scroll', position, {capture: true, passive: true});
    return {close, valid: () => input.checkValidity()};
}
