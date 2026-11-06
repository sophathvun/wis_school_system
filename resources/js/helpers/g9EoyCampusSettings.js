import {Modal} from 'bootstrap';
import {setupStaffLocationSelect} from './staffBirthplaceSelect.js';
import {showSuccess, showConfirm} from './sweet-alert2.js';

export function setupG9EoyCampusSettings(root, year) {
    const panel = root.querySelector('[data-eoy-campus-settings]');
    if (!panel) return {canChange: () => true, load() {}};
    const modal = root.querySelector('[data-eoy-campus-modal]'), dialog = Modal.getOrCreateInstance(modal);
    const form = modal.querySelector('form'), campus = form.elements.campus_id, scope = form.elements.scope;
    const save = modal.querySelector('[data-eoy-campus-save]'), message = modal.querySelector('[data-eoy-campus-message]');
    const title = modal.querySelector('[data-eoy-campus-title]'), yearLabel = modal.querySelector('[data-eoy-campus-year]');
    const newButton = panel.querySelector('[data-eoy-campus-new]'), list = panel.querySelector('[data-eoy-campus-list]');
    const listMessage = panel.querySelector('[data-eoy-campus-list-message]'), dismiss = [...modal.querySelectorAll('[data-eoy-dismiss]')];
    const names = [form.elements.compiler_name, form.elements.director_name], combos = new Map();
    [campus, scope].forEach(select => {
        [...select.options].forEach(option => option.dataset.en = option.textContent);
        combos.set(select, setupStaffLocationSelect(form, select, {
            labelId: select.id + 'Label', optionsId: select.id + 'Options', locationLabel: select === campus ? 'Campus' : 'Apply To',
        }));
    });
    let rows = [], manageable = new Set(), listBusy = false, editorBusy = false, removing = false, ready = false, dirty = false, canManage = false;
    let version = 0, mode = 'new', opened = false, editorYear = '', lastCampus = '', lastScope = '', listVersion = 0, editorVersion = 0;
    let academicYear = '';
    const sync = () => {
        combos.forEach(combo => combo.sync());
        names.forEach(input => input.closest('.premium-form-field').classList.toggle('has-value', input.value !== ''));
    };
    const controls = () => {
        save.disabled = editorBusy || !ready || !canManage;
        names.forEach(input => input.disabled = save.disabled);
        combos.forEach(combo => combo.toggle.disabled = editorBusy || mode === 'edit');
        dismiss.forEach(button => button.disabled = editorBusy);
        newButton.disabled = listBusy || editorBusy || removing || !year.value;
        list.querySelectorAll('[data-eoy-campus-edit], [data-eoy-campus-remove]').forEach(button => button.disabled = listBusy || removing);
    };
    const request = async (path, params, body, method = body ? 'POST' : 'GET') => {
        const response = await fetch(panel.dataset.url + path + (body ? '' : '?' + new URLSearchParams(params)), {
            method, credentials: 'same-origin',
            headers: {Accept: 'application/json', ...(body ? {'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content} : {})},
            body: body ? JSON.stringify(body) : undefined,
        });
        const data = await response.json();
        if (!response.ok) throw Error(Object.values(data.errors || {}).flat().join('\n') || data.message || 'Campus names unavailable.');
        return data;
    };
    const render = emptyMessage => {
        list.replaceChildren();
        rows.forEach((item, index) => {
            const row = document.createElement('tr'), cells = Array.from({length: 6}, () => document.createElement('td'));
            const number = document.createElement('span');
            number.className = 'badge bg-blue-lt g9-number-badge'; number.textContent = String(index + 1); cells[0].append(number);
            const campusName = document.createElement('strong'); campusName.textContent = item.campus_name; cells[1].append(campusName);
            const badge = document.createElement('span'); badge.className = 'badge ' + (item.scope === 'all' ? 'bg-blue-lt' : 'bg-purple-lt'); badge.textContent = item.applies_to; cells[2].append(badge);
            [cells[3], cells[4]].forEach(cell => cell.className = 'school-profile-khmer');
            cells[3].textContent = item.compiler_name || '—'; cells[4].textContent = item.director_name || '—';
            cells[5].className = 'text-end';
            if (item.can_manage) {
                const edit = document.createElement('button'), icon = document.createElement('i'), label = document.createElement('span');
                edit.type = 'button'; edit.className = 'btn btn-sm btn-outline-primary g9-campus-edit'; edit.dataset.eoyCampusEdit = String(index);
                edit.dataset.actionNormalized = 'true'; edit.setAttribute('aria-label', 'Edit names for ' + item.campus_name + ' · ' + item.applies_to);
                icon.className = 'ti ti-edit'; icon.setAttribute('aria-hidden', 'true'); label.textContent = 'Edit'; edit.append(icon, label); cells[5].append(edit);
                const remove = document.createElement('button'), removeIcon = document.createElement('i'), removeLabel = document.createElement('span');
                remove.type = 'button'; remove.className = 'btn btn-sm btn-outline-danger g9-campus-edit ms-2'; remove.dataset.eoyCampusRemove = String(index);
                remove.dataset.actionNormalized = 'true'; remove.setAttribute('aria-label', 'Remove names for ' + item.campus_name + ' · ' + item.applies_to);
                removeIcon.className = 'ti ti-trash'; removeIcon.setAttribute('aria-hidden', 'true'); removeLabel.textContent = 'Remove'; remove.append(removeIcon, removeLabel); cells[5].append(remove);
            } else {
                const readonly = document.createElement('span'); readonly.className = 'text-secondary small'; readonly.textContent = 'Read only'; cells[5].append(readonly);
            }
            row.append(...cells); list.append(row);
        });
        if (!rows.length) {
            const row = document.createElement('tr'), cell = document.createElement('td');
            cell.colSpan = 6; cell.className = 'g9-campus-empty'; cell.textContent = emptyMessage || 'No campus names configured. Use New to add a configuration.';
            row.append(cell); list.append(row);
        }
    };
    const loadList = async () => {
        const id = ++listVersion; listBusy = true; newButton.hidden = true; controls();
        rows = []; render('Loading campus names…'); listMessage.textContent = '';
        if (!year.value) { listBusy = false; render('Select an academic year to view campus names.'); controls(); return; }
        try {
            const data = await request('/list', {academic_year_id: year.value});
            if (id !== listVersion) return;
            rows = data.rows; manageable = new Set(data.manageable_campus_ids.map(String)); academicYear = data.academic_year;
            newButton.hidden = !data.can_create; render(data.can_create ? undefined : 'No campus names configured for this academic year.');
            listMessage.textContent = rows.length + ' configuration' + (rows.length === 1 ? '' : 's') + ' · All-year defaults and ' + academicYear;
        } catch (error) {
            if (id === listVersion) { manageable.clear(); render('Campus names could not be loaded.'); listMessage.textContent = error.message; }
        } finally { if (id === listVersion) { listBusy = false; controls(); } }
    };
    const loadEditor = async () => {
        const id = ++editorVersion; editorBusy = true; ready = false; canManage = false; dirty = false;
        lastCampus = campus.value; lastScope = scope.value; names.forEach(input => input.value = ''); controls(); sync();
        message.textContent = 'Loading campus names…';
        try {
            const data = await request('', {academic_year_id: editorYear, campus_id: campus.value, scope: scope.value});
            if (id !== editorVersion) return;
            version = data.version; canManage = data.can_manage; ready = true;
            names.forEach(input => input.value = data[input.name] || '');
            title.textContent = mode === 'edit' || version ? 'Edit Campus Names' : 'New Campus Names';
            message.textContent = version ? 'Existing names loaded. Save to update this configuration.' :
                data.source === 'year-suggestion' ? 'Names suggested from the selected year. Save to create the all-year default.' :
                data.source === 'all' ? 'All-year defaults loaded. Save to create a separate configuration for this year.' : 'Enter the score compiler and principal names.';
        } catch (error) { if (id === editorVersion) message.textContent = error.message; }
        finally { if (id === editorVersion) { editorBusy = false; controls(); sync(); } }
    };
    const openEditor = item => {
        if (listBusy || editorBusy || removing || opened || !year.value) return;
        if (item && !item.can_manage || !item && !manageable.size) return;
        mode = item ? 'edit' : 'new'; dirty = false; ready = false; canManage = false; editorYear = year.value; opened = true;
        [...campus.options].forEach(option => option.disabled = !manageable.has(option.value));
        campus.value = String(item?.campus_id || [...manageable][0]); scope.value = item?.scope || 'all';
        yearLabel.textContent = 'Academic year: ' + academicYear; title.textContent = item ? 'Edit Campus Names' : 'New Campus Names';
        sync(); dialog.show(); loadEditor();
    };
    newButton.addEventListener('click', () => openEditor());
    const removeRow = async item => {
        if (!item?.can_manage || listBusy || editorBusy || removing || opened) return;
        const payload = {academic_year_id: year.value, campus_id: item.campus_id, scope: item.scope, id: item.id, version: item.version};
        removing = true; controls();
        try {
            const effect = item.scope === 'year' ? 'This year will use the all-year default names, if configured.' : 'Years without their own names will no longer have default signing names.';
            const result = await showConfirm('Remove ' + item.campus_name + ' names?', 'Applies to: ' + item.applies_to + '\nScore compiler: ' + (item.compiler_name || '—') + '\nPrincipal: ' + (item.director_name || '—') + '\n\n' + effect, 'Remove', 'Cancel');
            if (!result.isConfirmed) return;
            listMessage.textContent = 'Removing campus name configuration…';
            await request('', {}, payload, 'DELETE');
            showSuccess('Campus name configuration removed'); await loadList();
        } catch (error) { listMessage.textContent = error.message; }
        finally { removing = false; controls(); }
    };
    list.addEventListener('click', async event => {
        const remove = event.target.closest('[data-eoy-campus-remove]');
        if (remove) { await removeRow(rows[Number(remove.dataset.eoyCampusRemove)]); return; }
        const button = event.target.closest('[data-eoy-campus-edit]');
        if (button) openEditor(rows[Number(button.dataset.eoyCampusEdit)]);
    });
    const canChange = () => !listBusy && !editorBusy && !removing && (!dirty || confirm('Discard unsaved annual score card campus names?'));
    [campus, scope].forEach(select => select.addEventListener('change', () => {
        if (mode === 'edit' || editorBusy || dirty && !confirm('Discard unsaved campus names?')) {
            campus.value = lastCampus; scope.value = lastScope; sync(); return;
        }
        loadEditor();
    }));
    names.forEach(input => input.addEventListener('input', () => {
        if (!ready || !canManage || editorBusy) return;
        dirty = true; message.textContent = 'Unsaved campus names.'; sync();
    }));
    modal.addEventListener('hide.bs.modal', event => {
        if (editorBusy || dirty && !confirm('Discard unsaved campus names?')) { event.preventDefault(); return; }
        combos.forEach(combo => combo.close());
    });
    modal.addEventListener('hidden.bs.modal', () => {
        opened = false; dirty = false; ready = false; canManage = false; editorVersion++; controls();
    });
    modal.addEventListener('shown.bs.modal', () => { if (ready && canManage) names[0].focus(); });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (editorBusy || !ready || !canManage || !form.reportValidity()) return;
        const payload = {academic_year_id: editorYear, campus_id: campus.value, scope: scope.value, version,
            ...Object.fromEntries(names.map(input => [input.name, input.value.trim()]))};
        editorBusy = true; controls(); combos.forEach(combo => combo.close()); message.textContent = 'Saving campus names…';
        try {
            await request('', {}, payload); dirty = false; editorBusy = false; ready = false; controls(); dialog.hide();
            showSuccess('Campus names saved'); await loadList();
        } catch (error) { message.textContent = error.message; }
        finally { editorBusy = false; controls(); }
    });
    const load = () => {
        dirty = false; if (opened) dialog.hide();
        return loadList();
    };
    window.addEventListener('beforeunload', event => { if (dirty || editorBusy || removing) { event.preventDefault(); event.returnValue = ''; } });
    loadList(); return {load, canChange};
}
