export function setupStaffLocationSelect(form, select, kind) {
    const field = select.closest('.staff-form-field');
    const multiple = select.multiple;
    const label = field.querySelector('.form-label');
    const {labelId,optionsId,locationLabel} = typeof kind === 'object' ? kind : {
        country: {labelId:'staffBirthCountryLabel',optionsId:'staffBirthCountryOptions',locationLabel:'Country'},
        province: {labelId:'staffBirthplaceLabel',optionsId:'staffBirthplaceOptions',locationLabel:'Province / City'},
        nationality: {labelId:'staffNationalityLabel',optionsId:'staffNationalityOptions',locationLabel:'Nationality'},
        marital: {labelId:'staffMaritalStatusLabel',optionsId:'staffMaritalStatusOptions',locationLabel:'Marital Status'},
    }[kind];
    label.id = labelId;
    const combo = document.createElement('div');
    combo.className = 'location-combobox staff-birthplace-combobox';
    if (kind === 'nationality') combo.classList.add('staff-nationality-combobox');
    if (kind === 'marital') combo.classList.add('staff-marital-status-combobox');
    combo.innerHTML = `<button type="button" class="location-combobox-toggle" aria-labelledby="${labelId}" aria-haspopup="listbox" aria-expanded="false" aria-controls="${optionsId}"><span class="location-combobox-selected"></span><i class="ti ti-chevron-down" aria-hidden="true"></i></button><div class="location-combobox-menu d-none"><input type="search" class="form-control location-combobox-search" placeholder="Search ${locationLabel}" aria-label="Search ${locationLabel}" data-search-normalized="true"><div class="location-combobox-results" id="${optionsId}" role="listbox" aria-labelledby="${labelId}"></div></div>`;
    select.after(combo);
    select.classList.add('d-none');
    const toggle = combo.querySelector('button');
    toggle.id = select.id+'-toggle';
    label.htmlFor = toggle.id;
    if (select.hasAttribute('aria-invalid')) toggle.setAttribute('aria-invalid',select.getAttribute('aria-invalid'));
    if (select.hasAttribute('aria-describedby')) toggle.setAttribute('aria-describedby',select.getAttribute('aria-describedby'));
    const selected = combo.querySelector('.location-combobox-selected');
    const menu = combo.querySelector('.location-combobox-menu');
    const search = combo.querySelector('input');
    const results = combo.querySelector('.location-combobox-results');
    if (multiple) results.setAttribute('aria-multiselectable','true');
    const textFor = option => {
        const text = document.createElement('span');
        text.className = 'staff-birth-location-text';
        for (const [language, tag] of [['kh','span'],['en','small']]) {
            if (!option?.dataset[language]) continue;
            const line = document.createElement(tag);
            line.className = language === 'kh' ? 'location-combobox-khmer staff-khmer' : 'location-combobox-english';
            line.textContent = option.dataset[language];
            text.appendChild(line);
        }
        if (!option?.dataset.flag) return text;
        const flagged = document.createElement('span');
        flagged.className = 'staff-birth-country-label';
        const flag = document.createElement('img');
        flag.className = 'location-flag';
        flag.alt = '';
        flag.src = option.dataset.flag;
        flagged.append(flag,text);
        return flagged;
    };
    const render = () => {
        const term = search.value.trim().toLowerCase();
        results.replaceChildren();
        [...select.options].forEach((option,index) => {
            if (option.hidden || option.disabled) return;
            if (term && !`${option.dataset.en || ''} ${option.dataset.kh || ''}`.toLowerCase().includes(term)) return;
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `location-combobox-option${option.selected ? ' is-selected' : ''}`;
            button.setAttribute('role','option');
            button.setAttribute('aria-selected',String(option.selected));
            button.dataset.index = index;
            if (multiple) {
                button.classList.add('staff-multi-campus-option');
                const check = document.createElement('i');
                check.className = option.selected ? 'ti ti-checkbox' : 'ti ti-square';
                check.setAttribute('aria-hidden','true');
                button.appendChild(check);
                button.disabled = option.dataset.locked === 'true';
            }
            if (option.dataset.en || option.dataset.kh) button.appendChild(textFor(option));
            else button.textContent = `Clear ${locationLabel}`;
            if (multiple && option.dataset.locked === 'true') {
                const badge = document.createElement('small');
                badge.className = 'badge bg-primary-lt ms-auto';
                badge.textContent = 'Primary';
                button.appendChild(badge);
            }
            results.appendChild(button);
        });
        if (!results.children.length) {
            const empty = document.createElement('div');
            empty.className = 'text-secondary px-2 py-2';
            empty.textContent = 'No options found';
            results.appendChild(empty);
        }
    };
    const sync = () => {
        const option = select.selectedOptions[0];
        if (multiple) selected.textContent = [...select.selectedOptions].map(item => item.dataset.en || item.textContent).join(', ');
        else selected.replaceChildren(textFor(option));
        field.classList.toggle('has-value', multiple ? select.selectedOptions.length > 0 : Boolean(option?.dataset.en || option?.dataset.kh));
        const fields = kind === 'nationality' ? [['en','nationality'],['kh','nationality_kh']] : kind === 'province' ? [['en','place_of_birth_en'],['kh','place_of_birth_kh']] : [];
        for (const [language,name] of fields) {
            const input = form.elements.namedItem(name);
            if (!input) continue;
            input.value = option?.dataset[language] || '';
            input.dispatchEvent(new Event('input',{bubbles:true}));
        }
        render();
    };
    const close = () => { combo.classList.remove('is-open'); menu.classList.add('d-none'); toggle.setAttribute('aria-expanded','false'); };
    const open = () => {
        combo.classList.add('is-open'); menu.classList.remove('d-none'); toggle.setAttribute('aria-expanded','true');
        search.value = ''; render(); search.focus();
    };
    toggle.addEventListener('click', () => menu.classList.contains('d-none') ? open() : close());
    search.addEventListener('input',render);
    results.addEventListener('click', event => {
        const button = event.target.closest('[data-index]');
        if (!button) return;
        const index = Number(button.dataset.index);
        if (multiple) {
            const option = select.options[index];
            if (option.dataset.locked === 'true') return;
            option.selected = !option.selected;
        } else select.selectedIndex = index;
        select.dispatchEvent(new Event('change',{bubbles:true}));
        if (multiple) results.querySelector(`[data-index="${index}"]`)?.focus();
        else { close(); toggle.focus(); }
    });
    combo.addEventListener('keydown', event => {
        if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); close(); toggle.focus(); return; }
        if (!['ArrowDown','ArrowUp','Home','End'].includes(event.key)) return;
        if (event.target === search && ['Home','End'].includes(event.key)) return;
        event.preventDefault(); event.stopPropagation();
        if (menu.classList.contains('d-none')) open();
        const options = [...results.querySelectorAll('[data-index]:not(:disabled)')];
        if (!options.length) return;
        const index = options.indexOf(document.activeElement);
        const next = event.key === 'Home' ? 0 : event.key === 'End' ? options.length - 1 : index < 0 ? (event.key === 'ArrowUp' ? options.length - 1 : 0) : (index + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
        options[next].focus();
    });
    select.addEventListener('change',sync);
    form.addEventListener('reset', () => window.setTimeout(sync,0));
    document.addEventListener('click',event => { if (!event.composedPath().includes(combo)) close(); });
    // Clicking a native scrollbar can focus the modal itself through Bootstrap's
    // focus trap. Keep the picker open for the entire pointer interaction.
    let interactingWithMenu = false;
    document.addEventListener('pointerdown',event => { interactingWithMenu = menu.contains(event.target); },true);
    const releaseMenuPointer = () => { interactingWithMenu = false; };
    document.addEventListener('pointerup',() => window.setTimeout(releaseMenuPointer,0),true);
    document.addEventListener('pointercancel',releaseMenuPointer,true);
    document.addEventListener('keydown',releaseMenuPointer,true);
    document.addEventListener('focusin',event => {
        if (!interactingWithMenu && !combo.contains(event.target)) close();
    });
    form.closest('.modal')?.addEventListener('hide.bs.modal',close);
    sync();
    return { close, sync, toggle };
}

export function setupStaffBirthplaceSelect(form) {
    const province = form.querySelector('[data-staff-birth-province]');
    if (!province) return;
    const country = form.querySelector('[data-staff-birth-country]');
    const countryUi = country && setupStaffLocationSelect(form,country,'country');
    const provinceUi = setupStaffLocationSelect(form,province,'province');
    if (!country) return;
    let previousCountry = country.value;
    const filterProvinces = preserve => {
        for (const option of province.options) {
            option.hidden = option.disabled = option.dataset.country !== undefined && option.dataset.country !== country.value;
        }
        if (!preserve || province.selectedOptions[0]?.disabled) province.selectedIndex = 0;
        province.dispatchEvent(new Event('change',{bubbles:true}));
        provinceUi.close();
    };
    country.addEventListener('change', () => {
        filterProvinces(country.value === previousCountry);
        previousCountry = country.value;
    });
    form.addEventListener('reset', () => window.setTimeout(() => {
        previousCountry = country.value;
        filterProvinces(true);
        countryUi.sync();
    },0));
    filterProvinces(true);
}

export function setupStaffNationalitySelect(form) {
    const select = form.querySelector('[data-staff-nationality]');
    if (select) setupStaffLocationSelect(form,select,'nationality');
}

export function setupStaffMaritalStatusSelect(form) {
    const select = form.querySelector('[data-staff-marital-status]');
    if (select) setupStaffLocationSelect(form,select,'marital');
}
