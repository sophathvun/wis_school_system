import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import {test} from 'node:test';

class Element {
    constructor(value = '') {
        this.value = value; this.events = {}; this.dataset = {}; this.disabled = false; this.children = [];
        this.classList = {toggle() {}};
    }
    addEventListener(key, handler) { (this.events[key] ??= []).push(handler); }
    async emit(key, event = {}) { for (const handler of this.events[key] || []) await handler(event); }
    append(...elements) { this.children.push(...elements); elements.forEach(element => element.parentElement = this); }
    replaceChildren(...elements) { this.children = []; this.append(...elements); }
    setAttribute(key, value) { this[key] = value; }
    closest(selector) {
        if (selector === '.premium-form-field' || selector === '[data-eoy-campus-edit]' && this.dataset.eoyCampusEdit !== undefined || selector === '[data-eoy-campus-remove]' && this.dataset.eoyCampusRemove !== undefined) return this;
        return this.parentElement?.closest(selector);
    }
    querySelectorAll(selector) {
        return [...this.children.filter(child => child.dataset.eoyCampusEdit !== undefined || child.dataset.eoyCampusRemove !== undefined), ...this.children.flatMap(child => child.querySelectorAll(selector))];
    }
    focus() { this.focused = true; }
}
const settle = () => new Promise(resolve => setImmediate(resolve));
function page({canManage = true, failList = false, failEditor = false} = {}) {
    const year = new Element('1'), campus = new Element('10'), scope = new Element('all');
    const save = new Element(), message = new Element(), title = new Element(), yearLabel = new Element();
    const newButton = new Element(), list = new Element(), listMessage = new Element(), dismiss = [new Element(), new Element()];
    const compiler = new Element(), director = new Element(); compiler.name = 'compiler_name'; director.name = 'director_name';
    campus.options = ['10', '20'].map(value => ({value, dataset: {}, textContent: value}));
    scope.options = ['all', 'year'].map(value => ({value, dataset: {}, textContent: value}));
    const form = new Element(); form.elements = {campus_id: campus, scope, compiler_name: compiler, director_name: director}; form.reportValidity = () => true;
    const panel = {dataset: {url: '/settings/eoy-campus'}, querySelector: key => ({
        '[data-eoy-campus-new]': newButton, '[data-eoy-campus-list]': list, '[data-eoy-campus-list-message]': listMessage,
    }[key])};
    const modal = new Element();
    modal.querySelector = key => ({form, '[data-eoy-campus-save]': save, '[data-eoy-campus-message]': message,
        '[data-eoy-campus-title]': title, '[data-eoy-campus-year]': yearLabel}[key]);
    modal.querySelectorAll = () => dismiss;
    const root = {querySelector: key => ({'[data-eoy-campus-settings]': panel, '[data-eoy-campus-modal]': modal}[key])};
    const calls = [], combos = new Map(), alerts = [], confirmations = [], stored = new Map();
    stored.set('10:year:1', {id: 7, campus_id: '10', scope: 'year', academic_year_id: '1', compiler_name: 'Year Compiler', director_name: 'Year Principal', version: 3});
    const dialog = {
        shown: false,
        show() { this.shown = true; modal.emit('shown.bs.modal'); },
        hide() {
            let prevented = false;
            modal.emit('hide.bs.modal', {preventDefault() { prevented = true; }});
            if (!prevented) { this.shown = false; modal.emit('hidden.bs.modal'); }
        },
    };
    const context = {
        URLSearchParams, confirm: () => true, window: {addEventListener() {}},
        document: {querySelector: () => ({content: 'csrf-token'}), createElement: () => new Element()},
        Modal: {getOrCreateInstance: () => dialog},
        setupStaffLocationSelect: (form, select) => {
            const combo = {toggle: new Element(), sync() { combo.synced = true; }, close() {}};
            combos.set(select, combo); return combo;
        },
        showSuccess: text => alerts.push(text),
        showConfirm: async (...args) => {
            confirmations.push(args);
            if (context.pauseConfirmation) await new Promise(resolve => context.releaseConfirmation = resolve);
            return {isConfirmed: context.confirmRemoval};
        },
        fetch: async (url, options) => {
            const isList = new URL(url, 'http://local.test').pathname.endsWith('/list');
            const input = options.body ? JSON.parse(options.body) : Object.fromEntries(new URL(url, 'http://local.test').searchParams);
            calls.push({input, options, isList});
            if (!isList && context.pauseEditor) await new Promise(resolve => context.releaseEditor = resolve);
            if (isList && context.failList || !isList && context.failEditor || options.method === 'POST' && context.failSave || options.method === 'DELETE' && context.failRemove)
                return {ok: false, json: async () => ({message: 'Names changed in another window.'})};
            if (isList) {
                const rows = [...stored.values()].filter(row => row.scope === 'all' || row.academic_year_id === input.academic_year_id).map(row => ({
                    ...row, campus_name: 'BCH', applies_to: row.scope === 'all' ? 'All academic years' : '2025-2026', can_manage: canManage,
                }));
                rows.push({campus_id: '20', campus_name: 'BKK', scope: 'all', applies_to: 'All academic years', compiler_name: 'Other Compiler', director_name: 'Other Principal', can_manage: false});
                return {ok: true, json: async () => ({rows, manageable_campus_ids: canManage ? [10] : [], can_create: canManage, academic_year: input.academic_year_id === '1' ? '2025-2026' : '2026-2027'})};
            }
            const key = input.campus_id + ':' + input.scope + ':' + (input.scope === 'year' ? input.academic_year_id : '');
            if (options.method === 'DELETE') { stored.delete(key); return {ok: true, json: async () => ({message: 'Removed'})}; }
            if (options.method === 'POST') stored.set(key, {...input, version: input.version + 1});
            const data = stored.get(key);
            return {ok: true, json: async () => ({scope: input.scope, source: data ? input.scope : 'year-suggestion', version: 0,
                compiler_name: 'Year Compiler', director_name: 'Year Principal', ...data, can_manage: canManage})};
        }, failList, failEditor, failSave: false, failRemove: false, confirmRemoval: true,
    };
    vm.createContext(context);
    vm.runInContext(fs.readFileSync('resources/js/helpers/g9EoyCampusSettings.js', 'utf8').replace(/^import .*;\r?\n/gm, '').replace('export function', 'function'), context);
    const settings = context.setupG9EoyCampusSettings(root, year);
    const editButton = () => list.children[0].children[5].children[0];
    const removeButton = () => list.children[0].children[5].children[1];
    return {context, settings, year, campus, scope, compiler, director, form, save, message, title, newButton, list, listMessage, modal, dialog, calls, combos, alerts, confirmations, editButton, removeButton};
}

test('loads a readable list with edit labels without opening the editor or changing data', async () => {
    const state = page(); await settle();
    assert.equal(state.calls.length, 1); assert.equal(state.calls[0].isList, true);
    assert.equal(state.dialog.shown, false); assert.equal(state.list.children.length, 2);
    assert.equal(state.list.children[0].children[3].textContent, 'Year Compiler');
    assert.equal(state.editButton().children[1].textContent, 'Edit');
    assert.equal(state.editButton().dataset.actionNormalized, 'true');
    assert.equal(state.list.children[1].children[5].children[0].textContent, 'Read only');
});

test('New opens a modal and saving adds all-year names then refreshes the list', async () => {
    const state = page(); await settle(); await state.newButton.emit('click'); await settle();
    assert.equal(state.dialog.shown, true); assert.equal(state.title.textContent, 'New Campus Names');
    assert.equal(state.scope.value, 'all'); assert.equal(state.campus.options[1].disabled, true);
    state.director.value = ' New Principal '; await state.director.emit('input');
    await state.form.emit('submit', {preventDefault() {}});
    const request = state.calls.find(call => call.options.method === 'POST');
    assert.equal(request.input.scope, 'all'); assert.equal(request.input.version, 0);
    assert.equal(request.input.director_name, 'New Principal');
    assert.equal(request.options.headers['X-CSRF-TOKEN'], 'csrf-token');
    assert.equal(state.dialog.shown, false); assert.equal(state.list.children.length, 3);
    assert.equal(state.alerts.length, 1); assert.equal(state.calls.at(-1).isList, true);
});

test('Edit loads fresh row data, locks its identity and saves its year and version', async () => {
    const state = page(); await settle();
    await state.list.emit('click', {target: state.editButton()}); await settle();
    assert.equal(state.title.textContent, 'Edit Campus Names'); assert.equal(state.scope.value, 'year');
    assert.equal(state.director.value, 'Year Principal'); assert.equal(state.combos.get(state.campus).toggle.disabled, true);
    state.scope.value = 'all'; await state.scope.emit('change'); assert.equal(state.scope.value, 'year');
    state.director.value = 'Updated'; await state.director.emit('input');
    await state.form.emit('submit', {preventDefault() {}});
    const request = state.calls.find(call => call.options.method === 'POST');
    assert.equal(request.input.scope, 'year'); assert.equal(request.input.academic_year_id, '1'); assert.equal(request.input.version, 3);
    assert.equal(state.list.children[0].children[4].textContent, 'Updated');
});

test('unsaved names can cancel scope changes, year navigation and closing the modal', async () => {
    const state = page(); await settle(); await state.newButton.emit('click'); await settle();
    state.compiler.value = 'Unsaved'; await state.compiler.emit('input'); state.context.confirm = () => false;
    state.scope.value = 'year'; await state.scope.emit('change'); assert.equal(state.scope.value, 'all');
    assert.equal(state.settings.canChange(), false); state.dialog.hide();
    assert.equal(state.dialog.shown, true); assert.equal(state.compiler.value, 'Unsaved');
    state.context.confirm = () => true; state.dialog.hide(); assert.equal(state.dialog.shown, false);
    assert.equal(state.settings.canChange(), true);
});

test('read-only users and failed lists cannot open an editing form', async () => {
    const state = page({canManage: false}); await settle();
    assert.equal(state.newButton.hidden, true); await state.newButton.emit('click'); assert.equal(state.dialog.shown, false);
    assert.equal(state.list.children[0].children[5].children[0].textContent, 'Read only');
    const failed = page({failList: true}); await settle();
    assert.equal(failed.newButton.hidden, true); assert.match(failed.listMessage.textContent, /another window/);
    assert.equal(failed.calls.length, 1);
});

test('conflicting saves keep the modal open and preserve edits without a success notification', async () => {
    const state = page(); await settle(); await state.newButton.emit('click'); await settle();
    state.director.value = 'Unsaved Principal'; await state.director.emit('input'); state.context.failSave = true;
    await state.form.emit('submit', {preventDefault() {}});
    assert.equal(state.dialog.shown, true); assert.equal(state.director.value, 'Unsaved Principal');
    assert.match(state.message.textContent, /another window/); assert.equal(state.alerts.length, 0);
    state.context.confirm = () => false; assert.equal(state.settings.canChange(), false);
});

test('an editor load failure keeps fields and saving locked', async () => {
    const state = page({failEditor: true}); await settle(); await state.newButton.emit('click'); await settle();
    assert.equal(state.compiler.disabled, true); assert.equal(state.save.disabled, true);
    await state.form.emit('submit', {preventDefault() {}});
    assert.equal(state.calls.filter(call => call.options.method === 'POST').length, 0);
    state.dialog.hide(); assert.equal(state.dialog.shown, false);
});

test('year changes reload the list and close a clean editor', async () => {
    const state = page(); await settle(); await state.newButton.emit('click'); await settle();
    assert.equal(state.settings.canChange(), true);
    state.year.value = '2'; await state.settings.load();
    assert.equal(state.dialog.shown, false); assert.equal(state.calls.at(-1).input.academic_year_id, '2');
    assert.match(state.listMessage.textContent, /2026-2027/);
    assert.equal(state.list.children.length, 1);
});

test('pending editor requests lock identity, closing and year navigation until data loads', async () => {
    const state = page(); await settle(); state.context.pauseEditor = true;
    await state.newButton.emit('click');
    assert.equal(state.save.disabled, true); assert.equal(state.settings.canChange(), false);
    state.dialog.hide(); assert.equal(state.dialog.shown, true);
    state.scope.value = 'year'; await state.scope.emit('change'); assert.equal(state.scope.value, 'all');
    state.context.releaseEditor(); await settle();
    assert.equal(state.save.disabled, false); assert.equal(state.settings.canChange(), true);
});

test('Remove identifies the entry and cancelling leaves it unchanged', async () => {
    const state = page(); await settle(); state.context.confirmRemoval = false;
    await state.list.emit('click', {target: state.removeButton()});
    assert.match(state.confirmations[0][0], /BCH/); assert.match(state.confirmations[0][1], /2025-2026/);
    assert.match(state.confirmations[0][1], /Year Principal/); assert.match(state.confirmations[0][1], /all-year default/);
    assert.equal(state.calls.length, 1); assert.equal(state.list.children.length, 2);
    assert.equal(state.settings.canChange(), true); assert.equal(state.removeButton().disabled, false);
});

test('confirmed removal targets only that row and refreshes the list', async () => {
    const state = page(); await settle();
    await state.list.emit('click', {target: state.removeButton()});
    const request = state.calls.find(call => call.options.method === 'DELETE');
    assert.equal(request.input.academic_year_id, '1'); assert.equal(request.input.campus_id, '10');
    assert.equal(request.input.scope, 'year'); assert.equal(request.input.id, 7); assert.equal(request.input.version, 3);
    assert.equal(request.options.headers['X-CSRF-TOKEN'], 'csrf-token');
    assert.equal(state.list.children.length, 1); assert.equal(state.list.children[0].children[1].children[0].textContent, 'BKK');
    assert.equal(state.alerts.length, 1); assert.equal(state.calls.at(-1).isList, true);
});

test('conflicting removal preserves the list and shows an error', async () => {
    const state = page(); await settle(); state.context.failRemove = true;
    await state.list.emit('click', {target: state.removeButton()});
    assert.equal(state.list.children.length, 2); assert.match(state.listMessage.textContent, /another window/);
    assert.equal(state.alerts.length, 0); assert.equal(state.removeButton().disabled, false);
});

test('year changes and duplicate actions are blocked while removal awaits confirmation', async () => {
    const state = page(); await settle(); state.context.pauseConfirmation = true;
    const pending = state.list.emit('click', {target: state.removeButton()}); await settle();
    assert.equal(state.settings.canChange(), false); assert.equal(state.newButton.disabled, true);
    await state.list.emit('click', {target: state.removeButton()}); assert.equal(state.confirmations.length, 1);
    state.context.confirmRemoval = false; state.context.releaseConfirmation(); await pending;
    assert.equal(state.settings.canChange(), true);
});
