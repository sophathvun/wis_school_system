import { genderKhmer } from './staffProfileFields';

export function setupStaffGenderSelect(form) {
    const select = form.querySelector('[data-staff-gender]');
    if (!select) return;
    const field = select.closest('.staff-form-field');
    const label = field.querySelector('.form-label');
    label.id = 'staffGenderLabel';
    const combo = document.createElement('div');
    combo.className = 'location-combobox staff-gender-combobox';
    combo.innerHTML = '<button type="button" class="location-combobox-toggle" aria-labelledby="staffGenderLabel" aria-haspopup="listbox" aria-expanded="false" aria-controls="staffGenderOptions"><span class="location-combobox-selected"></span><i class="ti ti-chevron-down" aria-hidden="true"></i></button><div class="location-combobox-menu d-none"><div class="location-combobox-results" id="staffGenderOptions" role="listbox" aria-labelledby="staffGenderLabel"></div></div>';
    select.after(combo);
    select.classList.add('d-none');
    const toggle = combo.querySelector('button');
    const menu = combo.querySelector('.location-combobox-menu');
    const results = combo.querySelector('.location-combobox-results');
    const selected = combo.querySelector('.location-combobox-selected');
    const options = [];
    for (const option of select.options) {
        if (!option.value) continue;
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'location-combobox-option';
        button.setAttribute('role', 'option');
        button.dataset.value = option.value;
        const english = document.createElement('span');
        english.className = 'location-combobox-english';
        english.textContent = option.value;
        const khmer = document.createElement('small');
        khmer.className = 'location-combobox-khmer staff-khmer';
        khmer.textContent = genderKhmer(option.value);
        const text = document.createElement('span');
        text.append(khmer, english);
        button.appendChild(text);
        results.appendChild(button);
        options.push(button);
    }
    const close = () => {
        combo.classList.remove('is-open');
        menu.classList.add('d-none');
        toggle.setAttribute('aria-expanded', 'false');
    };
    const sync = () => {
        selected.replaceChildren();
        if (select.value) {
            const khmer = document.createElement('span');
            khmer.className = 'location-combobox-khmer staff-khmer';
            khmer.textContent = genderKhmer(select.value);
            const english = document.createElement('small');
            english.className = 'location-combobox-english';
            english.textContent = select.value;
            selected.append(khmer, english);
        }
        field.classList.toggle('has-value', Boolean(select.value));
        options.forEach(button => {
            const active = button.dataset.value === select.value;
            button.classList.toggle('is-selected', active);
            button.setAttribute('aria-selected', String(active));
        });
    };
    const open = focus => {
        combo.classList.add('is-open');
        menu.classList.remove('d-none');
        toggle.setAttribute('aria-expanded', 'true');
        if (focus) (options.find(button => button.dataset.value === select.value) || options[0])?.focus();
    };
    toggle.addEventListener('click', () => menu.classList.contains('d-none') ? open(false) : close());
    toggle.addEventListener('keydown', event => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') { event.preventDefault(); event.stopPropagation(); open(true); }
    });
    results.addEventListener('click', event => {
        const button = event.target.closest('[data-value]');
        if (!button) return;
        select.value = button.dataset.value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        close();
        toggle.focus();
    });
    combo.addEventListener('keydown', event => {
        if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); close(); toggle.focus(); }
        const index = options.indexOf(document.activeElement);
        if (index >= 0 && ['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
            event.preventDefault();
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? options.length - 1 : (index + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
            options[next].focus();
        }
    });
    document.addEventListener('click', event => { if (!combo.contains(event.target)) close(); });
    combo.addEventListener('focusout', event => { if (!combo.contains(event.relatedTarget)) close(); });
    form.closest('.modal')?.addEventListener('hide.bs.modal', close);
    select.addEventListener('change', sync);
    form.addEventListener('reset', () => window.setTimeout(sync, 0));
    sync();
}
