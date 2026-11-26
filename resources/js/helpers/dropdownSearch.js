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

export function dropdownTextMatches(text, query) {
    return normalizeDropdownSearch(text).includes(normalizeDropdownSearch(query));
}

export function bindDropdownSearch(input, render) {
    input.dataset.dropdownSearchBound = 'true';
    input.dataset.dropdownSearchActive = 'true';
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

// Cover older, page-specific pickers and menus created after page load.
// Their own input handler still owns filtering and any remote/debounced request.
export function installDropdownSearchCompatibility(root = document) {
    const states = new WeakMap();
    const searchFor = event => {
        const input = event.target;
        if (input?.tagName !== 'INPUT' || input.dataset.dropdownSearchBound) return null;
        if (!input.closest('[class*="combobox"], [class*="picker"], [class*="select-menu"], [class*="filter-menu"], [class*="dropdown-menu"], [class*="searchable-menu"], .skipping-searchable')) return null;
        if (input.type !== 'search' && !/search/i.test(input.className + ' ' + input.placeholder)) return null;
        if (!states.has(input)) {
            states.set(input, {value: input.value, composing: false});
            input.dataset.dropdownSearchActive = 'true';
            input.autocomplete = 'off'; input.spellcheck = false; input.setAttribute('autocapitalize', 'none');
        }
        return input;
    };
    const refresh = input => {
        const state = states.get(input);
        if (state.composing || state.value === input.value) return;
        state.value = input.value;
        input.dispatchEvent(new Event('input', {bubbles: true}));
    };
    root.addEventListener('focusin', event => {const input=searchFor(event);if(input)states.get(input).value=input.value;}, true);
    root.addEventListener('compositionstart', event => {const input=searchFor(event);if(input)states.get(input).composing=true;}, true);
    root.addEventListener('compositionend', event => {
        const input=searchFor(event);if(!input)return;
        const state=states.get(input);state.composing=false;state.value=null;refresh(input);
    }, true);
    root.addEventListener('input', event => {
        const input=searchFor(event);if(!input)return;
        const state=states.get(input);
        if(state.composing||event.isComposing){event.stopImmediatePropagation();return;}
        state.value=input.value;
    }, true);
    for (const name of ['keyup', 'search', 'change']) root.addEventListener(name, event => {
        const input=searchFor(event);if(input&&!event.isComposing)refresh(input);
    }, true);
    root.addEventListener('keydown', event => {
        const input=searchFor(event);
        if(input&&event.key==='Enter'&&!event.isComposing&&!states.get(input).composing)event.preventDefault();
    }, true);
}
