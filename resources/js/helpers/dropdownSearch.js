// Search visible labels as well as bilingual metadata; ignore pasted invisible separators.
export function normalizeDropdownSearch(value) {
    return String(value ?? '').normalize('NFKC').toLowerCase()
        .replace(/[\u200B-\u200D\u2060\uFEFF]/g, '')
        .replace(/[០-៩]/g, digit => String(digit.charCodeAt(0) - 0x17e0))
        .replace(/\s+/g, ' ').trim();
}

export function dropdownOptionMatches(option, query) {
    const text = [option.textContent, option.label, option.dataset?.en, option.dataset?.kh, option.dataset?.primaryLabel, option.dataset?.secondaryLabel].filter(Boolean).join(' ');
    return normalizeDropdownSearch(text).includes(normalizeDropdownSearch(query));
}

export function bindDropdownSearch(input, render) {
    let composing = false, lastValue = input.value;
    const update = event => {
        if (composing || event.isComposing) return;
        if (event.type !== 'input' && input.value === lastValue) return;
        lastValue = input.value; render();
    };
    input.autocomplete = 'off'; input.spellcheck = false;
    input.setAttribute('autocapitalize', 'none');
    input.addEventListener('focus', () => { lastValue = input.value; });
    input.addEventListener('compositionstart', () => { composing = true; });
    input.addEventListener('compositionend', () => { composing = false; lastValue = input.value; render(); });
    for (const name of ['input', 'search', 'keyup']) input.addEventListener(name, update);
    input.addEventListener('change', event => { update(event); event.stopPropagation(); });
    input.addEventListener('keydown', event => {
        // Search text is not a form selection. Enter must not submit its parent form.
        if (event.key === 'Enter' && !event.isComposing && !composing) event.preventDefault();
    });
}
