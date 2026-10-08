// Uses the same calendar controls and shared styles as Student Enrollment DOB.
export function initPremiumDatePicker(picker) {
    const input = picker.querySelector('[data-premium-date-input]');
    const value = picker.querySelector('[data-premium-date-value]');
    const toggle = picker.querySelector('[data-premium-date-toggle]');
    const popup = picker.querySelector('.date-picker-popup');
    const month = picker.querySelector('.date-picker-year-toggle');
    const yearPopup = picker.querySelector('.date-picker-year-popup');
    const years = picker.querySelector('.date-picker-years');
    const days = picker.querySelector('.date-picker-days');
    if (!input || !value || !toggle || !popup || !month || !years || !days) return;
    const displayFormat = picker.dataset.premiumDateFormat || 'DD-MM-YYYY';
    const namedMonth = displayFormat === 'DD-MMM-YYYY';
    const shortMonths = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const pad = (number) => String(number).padStart(2, '0');
    const iso = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
    const parse = (raw) => {
        const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(raw || '');
        if (!match) return null;
        const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
        return iso(date) === raw ? date : null;
    };
    const parseTyped = () => {
        const raw = input.value.trim();
        const named = namedMonth && /^(\d{1,2})[-/]([a-z]{3})[-/](\d{4})$/i.exec(raw);
        if (named) {
            const monthIndex = shortMonths.findIndex((name) => name.toLowerCase() === named[2].toLowerCase());
            return monthIndex < 0 ? null : parse(`${named[3]}-${pad(monthIndex + 1)}-${pad(named[1])}`);
        }
        const match = /^(\d{1,2})[-/](\d{1,2})[-/](\d{4})$/.exec(raw);
        return match ? parse(`${match[3]}-${pad(match[2])}-${pad(match[1])}`) : parse(raw);
    };
    const display = (date) => `${pad(date.getDate())}-${namedMonth ? shortMonths[date.getMonth()] : pad(date.getMonth() + 1)}-${date.getFullYear()}`;
    const initial = parse(value.value);
    let cursor = initial || new Date();
    cursor = new Date(cursor.getFullYear(), cursor.getMonth(), 1);

    const close = () => {
        popup.classList.add('d-none');
        yearPopup?.classList.add('d-none');
        picker.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
    };
    const position = () => {
        popup.style.left = '0';
        const bounds = popup.getBoundingClientRect();
        const rightOverflow = Math.max(0, bounds.right - window.innerWidth + 12);
        const leftOverflow = Math.max(0, 12 - bounds.left);
        popup.style.left = `${leftOverflow - rightOverflow}px`;
    };
    const render = () => {
        month.textContent = cursor.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
        const first = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
        const start = new Date(cursor.getFullYear(), cursor.getMonth(), 1 - first.getDay());
        days.innerHTML = Array.from({ length: 42 }, (_, index) => {
            const date = new Date(start.getFullYear(), start.getMonth(), start.getDate() + index);
            const selected = iso(date) === value.value;
            return `<button type="button" class="date-picker-day${date.getMonth() !== cursor.getMonth() ? ' is-outside' : ''}${selected ? ' is-selected' : ''}" data-premium-day="${iso(date)}" aria-label="${date.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}" aria-pressed="${selected}">${date.getDate()}</button>`;
        }).join('');
        const end = Math.max(new Date().getFullYear() + 10, cursor.getFullYear());
        years.innerHTML = Array.from({ length: end - 1900 + 1 }, (_, index) => {
            const year = 1900 + index;
            return `<button type="button" class="date-picker-year${year === cursor.getFullYear() ? ' is-selected' : ''}" data-premium-year="${year}">${year}</button>`;
        }).join('');
    };
    const set = (date) => {
        value.value = iso(date);
        input.value = display(date);
        input.setCustomValidity('');
        picker.classList.add('has-value');
        cursor = new Date(date.getFullYear(), date.getMonth(), 1);
        render();
        value.dispatchEvent(new Event('change', { bubbles: true }));
    };
    const syncTyped = (format = true) => {
        const parsed = parseTyped();
        value.value = parsed ? iso(parsed) : '';
        input.setCustomValidity(input.value.trim() && !parsed ? `Enter a valid date as ${displayFormat}.` : '');
        picker.classList.toggle('has-value', Boolean(parsed));
        if (parsed && format) {
            input.value = display(parsed);
            cursor = new Date(parsed.getFullYear(), parsed.getMonth(), 1);
            value.dispatchEvent(new Event('change', { bubbles: true }));
            // Blur happens before a calendar day receives its click. Keep the
            // open day's DOM in place so that click is not lost during typing.
            if (popup.classList.contains('d-none')) render();
        }
    };
    const open = () => {
        if (input.disabled) return;
        const selected = parse(value.value) || new Date();
        cursor = new Date(selected.getFullYear(), selected.getMonth(), 1);
        yearPopup?.classList.add('d-none');
        render();
        popup.classList.remove('d-none');
        picker.classList.add('is-open');
        toggle.setAttribute('aria-expanded', 'true');
        position();
    };
    toggle.addEventListener('click', () => popup.classList.contains('d-none') ? open() : close());
    input.addEventListener('focus', open);
    input.addEventListener('input', () => syncTyped(false));
    input.addEventListener('change', () => syncTyped());
    input.addEventListener('blur', () => syncTyped());
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault(); syncTyped(); close();
        } else if (event.key === 'ArrowDown') {
            event.preventDefault(); open(); days.querySelector('.is-selected, button')?.focus();
        }
    });
    popup.addEventListener('click', (event) => {
        const day = event.target.closest('[data-premium-day]');
        const year = event.target.closest('[data-premium-year]');
        if (day) { set(parse(day.dataset.premiumDay)); close(); toggle.focus(); }
        if (year) {
            cursor = new Date(Number(year.dataset.premiumYear), cursor.getMonth(), 1);
            yearPopup?.classList.add('d-none'); render(); month.focus();
        }
    });
    ['prev', 'next'].forEach((direction) => picker.querySelector(`[data-premium-date-${direction}]`)?.addEventListener('click', () => {
        cursor = new Date(cursor.getFullYear(), cursor.getMonth() + (direction === 'prev' ? -1 : 1), 1);
        yearPopup?.classList.add('d-none'); render();
    }));
    picker.querySelector('[data-premium-date-today]')?.addEventListener('click', () => {
        set(new Date()); close(); toggle.focus();
    });
    month.addEventListener('click', () => {
        yearPopup?.classList.toggle('d-none');
        if (!yearPopup?.classList.contains('d-none')) years.querySelector('.is-selected')?.scrollIntoView({ block: 'nearest' });
    });
    picker.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        event.stopPropagation(); close(); toggle.focus();
    });
    document.addEventListener('click', (event) => { if (!event.composedPath().includes(picker)) close(); });
    window.addEventListener('resize', () => { if (!popup.classList.contains('d-none')) position(); });
    input.form?.addEventListener('submit', (event) => {
        syncTyped();
        if (!input.checkValidity()) { event.preventDefault(); input.reportValidity(); }
    });
    if (initial) { input.value = display(initial); picker.classList.add('has-value'); }
    else syncTyped(false);
    render();
}
