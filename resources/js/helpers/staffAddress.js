import { setupStaffLocationSelect } from './staffBirthplaceSelect';
import { toKhmerDigits } from './staffProfileFields';
import { showError } from './sweet-alert2';

export function setupStaffAddress(form) {
    const card = form.querySelector('[data-staff-address]');
    if (!card) return;
    const levels = ['country','province','district','commune','village'];
    const labels = ['Country','Province/City','District/Khan','Commune/Sangkat','Village'];
    const field = name => form.elements.namedItem(name);
    const controls = levels.map((level,index) => {
        const select = card.querySelector(`[data-staff-address-level="${level}"]`);
        const ui = setupStaffLocationSelect(form,select,{
            labelId:`staffAddress${level}Label`,optionsId:`staffAddress${level}Options`,locationLabel:labels[index],
        });
        return { select, ui, original:[...select.options].map(option => option.cloneNode(true)), version:0 };
    });
    const setValue = (name,value) => {
        const input = field(name);
        if (!input) return;
        input.value = value;
        input.dispatchEvent(new Event('input',{bubbles:true}));
        input.closest('.staff-form-field')?.classList.toggle('has-value',Boolean(value));
    };
    const syncNames = index => {
        if (index === 0) return;
        for (const language of ['en','kh']) {
            setValue(`${levels[index]}_${language}`,controls[index].select.selectedOptions[0]?.dataset[language] || '');
        }
    };
    const compose = () => {
        const selected = (level,language) => controls[levels.indexOf(level)].select.selectedOptions[0]?.dataset[language] || '';
        const city = /phnom\s*penh/i.test(selected('province','en')) || /ភ្នំពេញ|រាជធានី/.test(selected('province','kh'));
        const clean = (value,pattern) => String(value || '').trim().replace(pattern,'').trim();
        const house = clean(field('house_number_en')?.value,/^(?:House No\.?|House|No\.?|#)\s*/i);
        const street = clean(field('street_en')?.value,/^(?:Street|St\.?)\s*/i);
        const englishLabel = (level,prefix) => {
            const name = clean(selected(level,'en'),/^(?:Phum|Village|Khum|Commune|SK\.?|Sangkat|Sruk|Srok|District|Khan)\s*/i);
            return name ? `${prefix} ${name}` : '';
        };
        const khmerLabel = (level,prefix) => {
            const name = clean(selected(level,'kh'),/^(?:ភូមិ|ឃុំ|សង្កាត់|ស្រុក|ខណ្ឌ|ក្រុង|ខេត្ត|រាជធានី)\s*/);
            return name ? `${prefix}${name}` : '';
        };
        setValue('house_number_kh',toKhmerDigits(field('house_number_en')?.value));
        setValue('street_kh',toKhmerDigits(field('street_en')?.value));
        setValue('current_address',[
            house ? `#${house}` : '',street ? `St. ${street}` : '',englishLabel('village','Phum'),
            englishLabel('commune',city?'SK.':'Khum'),englishLabel('district',city?'Khan':'Sruk'),
            selected('province','en'),selected('country','en'),
        ].filter(Boolean).join(', '));
        setValue('current_address_kh',[
            house ? `ផ្ទះលេខ${toKhmerDigits(house)}` : '',street ? `ផ្លូវ${toKhmerDigits(street)}` : '',khmerLabel('village','ភូមិ'),
            khmerLabel('commune',city?'សង្កាត់':'ឃុំ'),khmerLabel('district',city?'ខណ្ឌ':'ស្រុក'),
            khmerLabel('province',city?'រាជធានី':'ខេត្ត'),selected('country','kh'),
        ].filter(Boolean).join(' '));
    };
    const clear = index => {
        const control = controls[index];
        control.version++;
        control.select.replaceChildren(new Option(`Select ${labels[index]}`,''));
        control.ui.toggle.disabled = false;
        control.ui.toggle.removeAttribute('aria-busy');
        control.ui.close();control.ui.sync();syncNames(index);
    };
    const cache = new Map();
    const loadNext = async index => {
        if (index === levels.length - 1) return;
        const parentId = controls[index].select.value;
        if (!parentId) return;
        const next = controls[index+1];
        const version = ++next.version;
        next.ui.toggle.disabled = true;
        next.ui.toggle.setAttribute('aria-busy','true');
        try {
            const level = levels[index+1];
            const key = `${level}:${parentId}`;
            if (!cache.has(key)) {
                const url = new URL(card.dataset.optionsUrl,window.location.origin);
                url.searchParams.set('level',level);url.searchParams.set('parent_id',parentId);
                const response = await fetch(url,{headers:{Accept:'application/json'}});
                if (!response.ok) throw new Error('Location options could not be loaded.');
                cache.set(key,await response.json());
            }
            if (next.version !== version) return;
            for (const item of cache.get(key)) {
                const option = new Option(`${item[level+'_name_kh'] || ''} / ${item[level+'_name_en']}`,item.id);
                option.dataset.en = item[level+'_name_en'] || '';
                option.dataset.kh = item[level+'_name_kh'] || '';
                next.select.appendChild(option);
            }
            next.ui.sync();
        } catch {
            if (next.version === version) showError('Unable to load locations',`Please select ${labels[index]} again to reload its locations.`);
        } finally {
            if (next.version === version) { next.ui.toggle.disabled=false;next.ui.toggle.removeAttribute('aria-busy'); }
        }
    };
    controls.forEach((control,index) => {
        syncNames(index);
        control.select.addEventListener('change',() => {
            syncNames(index);
            for (let child=index+1;child<controls.length;child++) clear(child);
            compose();loadNext(index);
        });
    });
    ['house_number_en','street_en'].forEach(name => field(name)?.addEventListener('input',compose));
    if (!field('current_address')?.value && !field('current_address_kh')?.value) compose();
    form.addEventListener('reset',() => window.setTimeout(() => {
        controls.forEach((control,index) => {
            control.version++;
            control.select.replaceChildren(...control.original.map(option => option.cloneNode(true)));
            control.ui.toggle.disabled=false;control.ui.toggle.removeAttribute('aria-busy');
            control.ui.close();control.ui.sync();syncNames(index);
        });
        if (!field('current_address')?.value && !field('current_address_kh')?.value) compose();
    },0));
}
