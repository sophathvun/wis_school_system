document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-notification-send-page]');
    if (!page || page.dataset.enhanced === '1') return;
    page.dataset.enhanced = '1';

    const staffProfiles = JSON.parse(page.dataset.staffProfiles || '{}');
    const notificationImageUploadUrl = page.dataset.uploadUrl || '/settings/notifications/upload-image';
    const csrfToken = page.dataset.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '';
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[char]));
    const staffInitial = name => (String(name || '?').trim()[0] || '?').toUpperCase();
    const avatarHtml = staff => staff.photo
        ? `<span class="staff-avatar"><img src="${escapeHtml(staff.photo)}" alt=""></span>`
        : `<span class="staff-avatar">${escapeHtml(staffInitial(staff.name))}</span>`;
    const presenceHtml = staff => `<span class="staff-presence ${staff.online ? 'is-online' : 'is-offline'}"><span class="staff-presence-dot"></span>${staff.online ? 'Online' : 'Offline'}</span>`;

    const initNotificationEditor = () => {
        const editor = document.querySelector('[data-notification-editor]');
        const textarea = document.querySelector('[data-notification-message]');
        const fileInput = document.querySelector('[data-notification-image-input]');
        const imageTools = document.querySelector('[data-notification-image-tools]');
        if (!editor || !textarea || editor.dataset.enhanced) return;
        editor.dataset.enhanced = '1';
        editor.innerHTML = textarea.value || '';
        let selectedImage = null;
        editor.querySelectorAll('img').forEach(image => image.classList.add('is-resizable'));
        const resizeState = { active: false, table: null, colIndex: -1, startX: 0, leftWidth: 0, rightWidth: 0 };

        const syncMessage = () => {
            editor.querySelectorAll('.is-selected').forEach(node => node.classList.remove('is-selected'));
            textarea.value = editor.innerHTML.trim();
            if (selectedImage) selectedImage.classList.add('is-selected');
        };
        const selectImage = image => {
            editor.querySelectorAll('.is-selected').forEach(item => item.classList.remove('is-selected'));
            selectedImage = image;
            selectedImage?.classList.add('is-selected');
            imageTools?.classList.toggle('is-visible', Boolean(selectedImage));
        };
        const clearSelectedImage = () => selectImage(null);
        const setImageSize = size => {
            if (!selectedImage) return;
            selectedImage.style.width = size === 'auto' ? '' : size;
            selectedImage.style.height = 'auto';
            syncMessage();
        };
        const removeSelectedImage = () => {
            if (!selectedImage) return;
            const node = selectedImage;
            clearSelectedImage();
            node.remove();
            syncMessage();
        };
        const insertHtmlAtCursor = html => {
            editor.focus();
            const selection = window.getSelection();
            if (!selection || !selection.rangeCount) {
                editor.insertAdjacentHTML('beforeend', html);
                syncMessage();
                return;
            }
            const range = selection.getRangeAt(0);
            range.deleteContents();
            const container = document.createElement('div');
            container.innerHTML = html;
            const fragment = document.createDocumentFragment();
            [...container.childNodes].forEach(node => fragment.appendChild(node));
            range.insertNode(fragment);
            range.collapse(false);
            selection.removeAllRanges();
            selection.addRange(range);
            syncMessage();
        };
        const templateHtml = type => {
            if (type === 'table') return '<table class="table table-bordered" data-resizable-table><colgroup><col style="width:15%"><col style="width:55%"><col style="width:30%"></colgroup><thead><tr><th>No.<span class="table-col-resize-handle" data-table-resize="0"></span></th><th>Name<span class="table-col-resize-handle" data-table-resize="1"></span></th><th>Details</th></tr></thead><tbody><tr><td>1</td><td>Student Name</td><td>Description</td></tr></tbody></table>';
            if (type === 'columns') return '<div class="notification-columns"><div class="notification-column"><p><strong>Picture</strong></p><p>Drop or insert image here.</p></div><div class="notification-column"><p><strong>Description</strong></p><p>Type the description here.</p></div></div>';
            if (type === 'address') return '<div class="notification-address-block"><p><strong>Address</strong></p><p>House No.</p><p>Street</p><p>Village</p><p>Commune</p><p>District / Khan</p><p>Province / City</p></div>';
            return '';
        };
        const runCommand = (command, value = null) => { editor.focus(); document.execCommand(command, false, value); syncMessage(); };
        const runAlignCommand = command => { editor.focus(); document.execCommand(command, false, null); syncMessage(); };
        const ensureResizableTables = () => {
            editor.querySelectorAll('table[data-resizable-table]').forEach(table => {
                if (table.dataset.tableEnhanced) return;
                table.dataset.tableEnhanced = '1';
                const headers = table.querySelectorAll('thead th');
                const cols = table.querySelectorAll('colgroup col');
                headers.forEach((th, index) => {
                    if (index >= headers.length - 1) return;
                    if (!th.querySelector('[data-table-resize]')) {
                        const handle = document.createElement('span');
                        handle.className = 'table-col-resize-handle';
                        handle.dataset.tableResize = String(index);
                        th.appendChild(handle);
                    }
                });
                if (!cols.length && headers.length) {
                    const colgroup = document.createElement('colgroup');
                    headers.forEach(() => colgroup.appendChild(document.createElement('col')));
                    table.insertBefore(colgroup, table.firstChild);
                }
            });
        };
        const startTableResize = event => {
            const handle = event.target.closest('[data-table-resize]');
            if (!handle) return;
            const table = handle.closest('table[data-resizable-table]');
            if (!table) return;
            const index = Number(handle.dataset.tableResize);
            const cols = [...table.querySelectorAll('colgroup col')];
            if (!cols[index] || !cols[index + 1]) return;
            resizeState.active = true;
            resizeState.table = table;
            resizeState.colIndex = index;
            resizeState.startX = event.clientX;
            resizeState.leftWidth = cols[index].getBoundingClientRect().width;
            resizeState.rightWidth = cols[index + 1].getBoundingClientRect().width;
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';
            event.preventDefault();
        };
        const moveTableResize = event => {
            if (!resizeState.active || !resizeState.table) return;
            const cols = [...resizeState.table.querySelectorAll('colgroup col')];
            const delta = event.clientX - resizeState.startX;
            const minWidth = 48;
            const total = resizeState.leftWidth + resizeState.rightWidth;
            const left = Math.max(minWidth, resizeState.leftWidth + delta);
            const right = Math.max(minWidth, total - left);
            if (left <= minWidth || right <= minWidth) return;
            cols[resizeState.colIndex].style.width = `${left}px`;
            cols[resizeState.colIndex + 1].style.width = `${right}px`;
            resizeState.table.style.tableLayout = 'fixed';
            syncMessage();
        };
        const endTableResize = () => {
            if (!resizeState.active) return;
            resizeState.active = false;
            resizeState.table = null;
            resizeState.colIndex = -1;
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
            syncMessage();
        };
        const uploadImage = async file => {
            const formData = new FormData();
            formData.append('image', file);
            const uploaded = await fetch(notificationImageUploadUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
                body: formData,
            });
            if (!uploaded.ok) throw new Error('Image upload failed.');
            return uploaded.json();
        };
        const insertImageFile = async file => {
            if (!file || !file.type.startsWith('image/')) return;
            const placeholder = document.createElement('span');
            placeholder.className = 'text-secondary';
            placeholder.textContent = 'Uploading image...';
            editor.focus();
            const selection = window.getSelection();
            if (selection && selection.rangeCount) {
                const range = selection.getRangeAt(0);
                range.deleteContents();
                range.insertNode(placeholder);
            } else {
                editor.appendChild(placeholder);
            }
            try {
                const data = await uploadImage(file);
                const image = document.createElement('img');
                image.className = 'is-resizable';
                image.src = data.url;
                image.alt = file.name || 'Notification image';
                image.style.width = '50%';
                image.style.height = 'auto';
                placeholder.replaceWith(image);
                editor.appendChild(document.createElement('br'));
                selectImage(image);
                syncMessage();
            } catch (error) {
                placeholder.textContent = 'Image upload failed.';
                placeholder.classList.add('text-danger');
            }
        };

        document.querySelectorAll('[data-editor-command]').forEach(button => button.addEventListener('click', () => runCommand(button.dataset.editorCommand)));
        document.querySelector('[data-editor-link]')?.addEventListener('click', () => {
            const url = window.prompt('Enter link URL');
            if (url) runCommand('createLink', url);
        });
        document.querySelectorAll('[data-editor-align]').forEach(button => button.addEventListener('click', () => runAlignCommand(button.dataset.editorAlign)));
        document.querySelectorAll('[data-editor-template]').forEach(button => button.addEventListener('click', () => insertHtmlAtCursor(templateHtml(button.dataset.editorTemplate))));
        document.querySelector('[data-editor-image]')?.addEventListener('click', () => fileInput?.click());
        document.querySelectorAll('[data-image-size]').forEach(button => button.addEventListener('click', () => setImageSize(button.dataset.imageSize)));
        document.querySelector('[data-image-remove]')?.addEventListener('click', removeSelectedImage);
        fileInput?.addEventListener('change', event => { [...event.target.files].forEach(insertImageFile); event.target.value = ''; });
        editor.addEventListener('input', syncMessage);
        editor.addEventListener('mousedown', startTableResize);
        editor.addEventListener('click', event => {
            const image = event.target.closest('img');
            if (image && editor.contains(image)) {
                selectImage(image);
                return;
            }
            if (!event.target.closest('[data-notification-image-tools]')) clearSelectedImage();
        });
        editor.addEventListener('paste', event => {
            const imageFile = [...(event.clipboardData?.files || [])].find(file => file.type.startsWith('image/'));
            if (!imageFile) return;
            event.preventDefault();
            insertImageFile(imageFile);
        });
        document.addEventListener('mousemove', moveTableResize);
        document.addEventListener('mouseup', endTableResize);
        ensureResizableTables();
        editor.closest('form')?.addEventListener('submit', syncMessage);
    };
    initNotificationEditor();

    const makeDepartmentPicker = select => {
        if (!select || select.dataset.enhanced) return null;
        select.dataset.enhanced = '1';
        const wrapper = document.createElement('div');
        wrapper.className = 'notification-picker department-select-wrap';
        wrapper.innerHTML = '<div class="department-selected-box is-empty" role="button" tabindex="0" aria-expanded="false"><span class="department-placeholder">Departments</span></div><div class="department-menu"><input type="search" class="form-control form-control-sm notification-picker-search" placeholder="Search departments"><div class="notification-picker-list"></div></div>';
        const selectedBox = wrapper.querySelector('.department-selected-box');
        const menu = wrapper.querySelector('.department-menu');
        const search = wrapper.querySelector('input');
        const list = wrapper.querySelector('.notification-picker-list');
        const closeMenu = () => { menu.classList.remove('is-open'); selectedBox.setAttribute('aria-expanded', 'false'); };
        const syncSelected = () => {
            const selected = [...select.selectedOptions].map(option => ({ value: option.value, label: option.textContent.trim() })).filter(item => item.value);
            if (selected.length) {
                selectedBox.classList.remove('is-empty');
                selectedBox.classList.add('is-filled');
                selectedBox.innerHTML = `<span class="department-box-label">Departments</span><span class="department-chip-list">${selected.map(item => `<span class="department-chip" data-dept-chip="${escapeHtml(item.value)}">${escapeHtml(item.label)}<button type="button" class="department-chip-remove" aria-label="Remove ${escapeHtml(item.label)}"><i class="ti ti-x"></i></button></span>`).join('')}</span>`;
            } else {
                selectedBox.classList.add('is-empty');
                selectedBox.classList.remove('is-filled');
                selectedBox.innerHTML = '<span class="department-placeholder">Departments</span>';
            }
        };
        const render = () => {
            const term = search.value.toLowerCase().trim();
            const options = [...select.options].filter(option => option.value && (!term || option.textContent.toLowerCase().includes(term)));
            list.innerHTML = options.length ? options.map(option => `<label class="notification-check-row"><input class="form-check-input" type="checkbox" value="${escapeHtml(option.value)}" ${option.selected ? 'checked' : ''}><span>${escapeHtml(option.textContent)}</span></label>`).join('') : '<div class="text-secondary px-2 py-2">No departments found</div>';
            syncSelected();
        };
        list.addEventListener('change', event => {
            if (!event.target.matches('input[type="checkbox"]')) return;
            const option = [...select.options].find(item => item.value === event.target.value);
            if (option) option.selected = event.target.checked;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            render();
        });
        selectedBox.addEventListener('click', event => {
            const chip = event.target.closest('.department-chip-remove');
            if (!chip) return;
            const option = [...select.options].find(item => item.value === chip.closest('.department-chip')?.dataset.deptChip);
            if (option) option.selected = false;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            render();
        });
        search.addEventListener('input', render);
        selectedBox.addEventListener('click', event => {
            if (event.target.closest('.department-chip-remove')) return;
            menu.classList.toggle('is-open');
            selectedBox.setAttribute('aria-expanded', menu.classList.contains('is-open') ? 'true' : 'false');
            if (menu.classList.contains('is-open')) search.focus();
        });
        selectedBox.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                menu.classList.toggle('is-open');
                selectedBox.setAttribute('aria-expanded', menu.classList.contains('is-open') ? 'true' : 'false');
                if (menu.classList.contains('is-open')) search.focus();
            }
        });
        select.addEventListener('change', syncSelected);
        select.classList.add('d-none');
        select.before(wrapper);
        document.addEventListener('click', event => { if (!wrapper.contains(event.target)) closeMenu(); });
        syncSelected();
        render();
        return { wrapper, render };
    };

    const makeStaffPicker = select => {
        if (!select || select.dataset.enhanced) return null;
        select.dataset.enhanced = '1';
        const wrapper = document.createElement('div');
        wrapper.className = 'notification-picker staff-picker';
        wrapper.innerHTML = '<div class="staff-selected-box"></div><input type="search" class="form-control form-control-sm notification-picker-search" placeholder="Search staff by name, username, email, or position"><div class="notification-picker-list"></div>';
        const selectedBox = wrapper.querySelector('.staff-selected-box');
        const search = wrapper.querySelector('input');
        const list = wrapper.querySelector('.notification-picker-list');
        const syncSelected = () => {
            const selected = [...select.selectedOptions].map(option => staffProfiles[option.value]).filter(Boolean);
            selectedBox.innerHTML = selected.length ? selected.map(staff => `<div class="staff-chip" data-staff-chip="${escapeHtml(staff.id)}">${avatarHtml(staff)}<span class="staff-meta"><span class="staff-name">${escapeHtml(staff.name)}</span><span class="staff-position">${escapeHtml(staff.position)}</span>${presenceHtml(staff)}</span><button type="button" class="staff-chip-remove" aria-label="Remove ${escapeHtml(staff.name)}"><i class="ti ti-x"></i></button></div>`).join('') : '<div class="staff-selected-empty">Selected staff will appear here.</div>';
        };
        const render = () => {
            const term = search.value.toLowerCase().trim();
            const options = [...select.options].filter(option => {
                const staff = staffProfiles[option.value] || {};
                const haystack = `${staff.name || ''} ${staff.username || ''} ${staff.email || ''} ${staff.position || ''}`.toLowerCase();
                return option.value && (!term || haystack.includes(term));
            });
            list.innerHTML = options.length ? options.map(option => {
                const staff = staffProfiles[option.value] || {};
                const disabled = option.disabled;
                return `<label class="staff-option-row ${disabled ? 'is-disabled' : ''}"><input class="form-check-input" type="checkbox" value="${escapeHtml(option.value)}" ${option.selected ? 'checked' : ''} ${disabled ? 'disabled' : ''}><span class="staff-option">${avatarHtml(staff)}<span class="staff-meta"><span class="staff-name">${escapeHtml(staff.name)}</span><span class="staff-position">${escapeHtml(staff.position)}</span>${presenceHtml(staff)}</span></span></label>`;
            }).join('') : '<div class="text-secondary px-2 py-2">No staff found</div>';
        };
        list.addEventListener('change', event => {
            if (!event.target.matches('input[type="checkbox"]')) return;
            const option = [...select.options].find(item => item.value === event.target.value);
            if (option) option.selected = event.target.checked;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            syncSelected();
        });
        selectedBox.addEventListener('click', event => {
            const chip = event.target.closest('[data-staff-chip]');
            if (!chip || !event.target.closest('.staff-chip-remove')) return;
            const option = [...select.options].find(item => item.value === chip.dataset.staffChip);
            if (option) option.selected = false;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            syncSelected();
            render();
        });
        search.addEventListener('input', render);
        select.classList.add('d-none');
        select.before(wrapper);
        syncSelected();
        render();
        return { wrapper, render, syncSelected };
    };

    const departmentSelect = document.querySelector('select[name="department_ids[]"]');
    const userSelect = document.querySelector('select[name="recipient_ids[]"]');
    const departmentPicker = makeDepartmentPicker(departmentSelect);
    const staffPicker = makeStaffPicker(userSelect);

    const syncDepartmentRecipients = () => {
        if (!departmentSelect || !userSelect) return;
        const selectedDepartments = [...departmentSelect.options].filter(option => option.selected).map(option => String(option.value));
        [...userSelect.options].forEach(option => {
            const staff = staffProfiles[option.value] || {};
            const blocked = selectedDepartments.includes(String(staff.department_id || ''));
            option.disabled = blocked;
            if (blocked) option.selected = false;
        });
        staffPicker?.syncSelected();
        staffPicker?.render();
    };
    departmentSelect?.addEventListener('change', syncDepartmentRecipients);
    syncDepartmentRecipients();

    const all = document.getElementById('sendToAll');
    const boxes = [document.getElementById('departmentBox'), document.getElementById('recipientBox')];
    const syncAll = () => boxes.forEach(box => {
        box?.classList.toggle('opacity-50', all.checked);
        box?.querySelectorAll('select,input,button').forEach(control => {
            if (control !== all) control.disabled = all.checked;
        });
    });
    all?.addEventListener('change', syncAll);
    syncAll();
});
