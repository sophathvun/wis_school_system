@extends('layouts.app')
@section('title','Send Notification')
@section('page-header')
<div class="container-fluid"><div class="row g-2 align-items-center"><div class="col"><div class="page-pretitle">Administrator</div><h2 class="page-title">Send Notification</h2></div><div class="col-auto"><a class="btn btn-outline-primary" href="{{ route('notifications.manage') }}">Notification Management</a></div></div></div>
@endsection
@section('content')
<style>
.notification-picker{border:1px solid var(--tblr-border-color);border-radius:14px;padding:.75rem;background:var(--tblr-bg-surface,#fff)}
.notification-picker-search{height:40px;border-radius:10px}
.notification-picker-list{max-height:245px;overflow:auto;margin-top:.65rem}
.notification-editor{overflow:hidden;border:1px solid var(--tblr-border-color);border-radius:14px;background:var(--tblr-bg-surface,#fff);box-shadow:0 2px 7px rgba(31,41,55,.04)}
.notification-editor-toolbar{display:flex;flex-wrap:wrap;gap:.35rem;padding:.55rem;border-bottom:1px solid var(--tblr-border-color);background:rgba(var(--tblr-primary-rgb),.04)}
.notification-editor-toolbar .btn{display:inline-grid;width:2.15rem;height:2.15rem;padding:0;place-items:center;border-radius:10px}
.notification-editor-toolbar .btn-wide{width:auto;padding:0 .75rem}
.notification-editor-toolbar .dropdown-menu{min-width:12rem}
.notification-editor-toolbar .align-menu{min-width:10rem}
.notification-editor-body{min-height:190px;padding:1rem;outline:0;line-height:1.6}
.notification-editor-body:empty:before{content:attr(data-placeholder);color:var(--tblr-secondary)}
.notification-editor-body img{max-width:100%;height:auto;border-radius:10px;margin:.5rem 0;cursor:pointer;box-shadow:0 5px 18px rgba(31,41,55,.12)}
.notification-editor-body img.is-selected{outline:3px solid rgba(var(--tblr-primary-rgb),.45);outline-offset:3px}
.notification-editor-body img.is-resizable{display:block;resize:both;overflow:auto;min-width:140px;min-height:100px}
.notification-editor-body figure.notification-image-wrap{display:inline-block;position:relative;max-width:100%;margin:.5rem 0;resize:both;overflow:auto;vertical-align:top}
.notification-editor-body figure.notification-image-wrap img{display:block;width:100%;height:auto;margin:0;border-radius:10px;box-shadow:0 5px 18px rgba(31,41,55,.12)}
.notification-editor-body figure.notification-image-wrap.is-selected{outline:3px solid rgba(var(--tblr-primary-rgb),.45);outline-offset:3px}
.notification-editor-body table{width:100%;border-collapse:collapse;margin:.75rem 0}
.notification-editor-body table td,.notification-editor-body table th{border:1px solid #d7dce6;padding:.55rem;vertical-align:top}
.notification-editor-body table[data-resizable-table] th{position:relative}
.notification-editor-body table[data-resizable-table] th .table-col-resize-handle{position:absolute;top:0;right:-4px;z-index:2;width:8px;height:100%;cursor:col-resize;user-select:none}
.notification-editor-body table[data-resizable-table] th .table-col-resize-handle:before{content:'';position:absolute;top:0;right:3px;width:2px;height:100%;background:rgba(var(--tblr-primary-rgb),.35)}
.notification-editor-body .notification-columns{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin:.75rem 0}
.notification-editor-body .notification-column{min-height:120px;padding:.75rem;border:1px dashed #cfd6e4;border-radius:10px;background:rgba(var(--tblr-primary-rgb),.03)}
.notification-editor-body .notification-address-block{padding:.75rem;border:1px solid #d7dce6;border-radius:10px;background:rgba(var(--tblr-primary-rgb),.03)}
.notification-image-tools{display:none;flex-wrap:wrap;align-items:center;gap:.35rem;padding:.45rem .55rem;border-bottom:1px solid var(--tblr-border-color);background:rgba(var(--tblr-primary-rgb),.08)}
.notification-image-tools.is-visible{display:flex}
.notification-image-tools .btn{height:1.85rem;padding:0 .55rem;border-radius:9px;font-size:.75rem}
.notification-editor-help{display:flex;justify-content:space-between;gap:.75rem;padding:.45rem .75rem;border-top:1px solid var(--tblr-border-color);color:var(--tblr-secondary);font-size:.78rem}
.notification-check-row{display:flex;align-items:center;gap:.55rem;padding:.48rem .55rem;border-radius:10px;cursor:pointer}
.notification-check-row:hover{background:rgba(var(--tblr-primary-rgb),.08)}
.notification-check-row.is-disabled{opacity:.45;cursor:not-allowed}
.department-select-wrap{position:relative}
.department-selected-box{display:flex;flex-wrap:wrap;align-items:center;gap:.35rem;min-height:48px;padding:.75rem .8rem;border:1.5px solid #dfe3ea;border-radius:14px;background:var(--tblr-bg-surface,#fff);cursor:pointer;box-shadow:0 2px 7px rgba(31,41,55,.04)}
.department-selected-box.is-empty{justify-content:flex-start}
.department-selected-box.is-empty .department-placeholder{display:block;width:100%;padding-left:.15rem;color:var(--tblr-secondary-color);font-size:.95rem;line-height:1.1}
.department-selected-box.is-filled{flex-direction:column;align-items:stretch;gap:.25rem;padding:.55rem .8rem .6rem}
.department-box-label{display:block;padding-left:.15rem;color:#5b4bd1;font-size:.72rem;font-weight:700;line-height:1.1}
.department-chip-list{display:flex;flex-wrap:wrap;align-items:center;gap:.45rem}
.department-selected-box:after{content:'\f078';margin-left:auto;font-family:'tabler-icons';font-size:.75rem;color:var(--tblr-secondary)}
.department-chip{display:inline-flex;align-items:center;gap:.45rem;padding:.38rem .65rem;border:1px solid rgba(var(--tblr-primary-rgb),.18);border-radius:999px;background:var(--tblr-bg-surface,#fff);font-size:.85rem;font-weight:600;white-space:nowrap}
.department-chip-remove{display:grid;place-items:center;width:1.3rem;height:1.3rem;padding:0;border:0;border-radius:50%;background:transparent;color:var(--tblr-secondary)}
.department-chip-remove:hover{background:rgba(220,53,69,.1);color:#dc3545}
.department-menu{display:none;position:absolute;z-index:20;top:calc(100% + .45rem);left:0;right:0;padding:.75rem;border:1px solid var(--tblr-border-color);border-radius:14px;background:var(--tblr-bg-surface,#fff);box-shadow:0 18px 40px rgba(15,23,42,.12)}
.department-menu.is-open{display:block}
.staff-selected-box{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:.55rem;min-height:58px;margin-bottom:.75rem;padding:.6rem;border:1px dashed rgba(var(--tblr-primary-rgb),.35);border-radius:12px;background:rgba(var(--tblr-primary-rgb),.04)}
.staff-selected-empty{display:flex;align-items:center;color:var(--tblr-secondary);font-size:.9rem}
.staff-chip,.staff-option{display:flex;align-items:center;gap:.65rem;min-width:0}
.staff-chip{position:relative;padding:.5rem 2rem .5rem .55rem;border:1px solid rgba(var(--tblr-primary-rgb),.18);border-radius:12px;background:var(--tblr-bg-surface,#fff);box-shadow:0 2px 7px rgba(31,41,55,.05)}
.staff-chip-remove{position:absolute;right:.4rem;top:50%;display:grid;width:1.5rem;height:1.5rem;padding:0;place-items:center;border:0;border-radius:50%;background:transparent;color:var(--tblr-secondary);transform:translateY(-50%)}
.staff-chip-remove:hover{background:rgba(220,53,69,.1);color:#dc3545}
.staff-avatar{display:grid;flex:0 0 auto;width:38px;height:38px;place-items:center;overflow:hidden;border-radius:50%;background:rgba(var(--tblr-primary-rgb),.12);color:var(--tblr-primary);font-weight:700}
.staff-avatar img{width:100%;height:100%;object-fit:cover}
.staff-meta{min-width:0}
.staff-name{overflow:hidden;color:var(--tblr-body-color);font-weight:650;white-space:nowrap;text-overflow:ellipsis}
.staff-position{display:block;overflow:hidden;color:var(--tblr-secondary);font-size:.82rem;white-space:nowrap;text-overflow:ellipsis}
.staff-presence{display:flex;align-items:center;gap:.35rem;color:var(--tblr-secondary);font-size:.75rem;line-height:1.1;white-space:nowrap}
.staff-presence-dot{width:.42rem;height:.42rem;border-radius:50%;background:#9aa4b2;box-shadow:0 0 0 2px rgba(154,164,178,.14)}
.staff-presence.is-online{color:#198754}
.staff-presence.is-online .staff-presence-dot{background:#20c997;box-shadow:0 0 0 2px rgba(32,201,151,.16)}
.staff-option-row{display:flex;align-items:center;gap:.55rem;width:100%;padding:.55rem;border-radius:10px;cursor:pointer}
.staff-option-row:hover{background:rgba(var(--tblr-primary-rgb),.08)}
.staff-option-row.is-disabled{opacity:.45;cursor:not-allowed}
.staff-option .staff-meta{display:flex;flex-direction:column;gap:.1rem}
[data-bs-theme="dark"] .notification-picker,[data-bs-theme="dark"] .staff-chip,[data-bs-theme="dark"] .notification-editor{background:var(--tblr-bg-forms,#1e293b)}
</style>
@php
    $oldDepartmentIds = collect(old('department_ids', []))->map(fn ($id) => (string) $id);
    $oldRecipientIds = collect(old('recipient_ids', []))->map(fn ($id) => (string) $id);
    $staffProfiles = $users->mapWithKeys(fn ($user) => [(string) $user->id => [
        'id' => (string) $user->id,
        'name' => $user->name ?: $user->username,
        'username' => $user->username,
        'email' => $user->email,
        'department_id' => $user->department_id ? (string) $user->department_id : '',
        'position' => $user->position?->name ?: 'No position',
        'photo' => $user->photo_path ? asset('storage/'.$user->photo_path) : null,
        'online' => $user->last_seen_at?->greaterThan(now()->subMinutes(5)) ?? false,
    ]]);
@endphp
<script>
document.addEventListener('DOMContentLoaded', () => {
    const staffProfiles = @json($staffProfiles);
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    const staffInitial = name => (String(name || '?').trim()[0] || '?').toUpperCase();
    const avatarHtml = staff => staff.photo ? `<span class="staff-avatar"><img src="${escapeHtml(staff.photo)}" alt=""></span>` : `<span class="staff-avatar">${escapeHtml(staffInitial(staff.name))}</span>`;
    const presenceHtml = staff => `<span class="staff-presence ${staff.online ? 'is-online' : 'is-offline'}"><span class="staff-presence-dot"></span>${staff.online ? 'Online' : 'Offline'}</span>`;
    const notificationImageUploadUrl = @json('/settings/notifications/upload-image');
    const csrfToken = @json(csrf_token());

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
            if (size === 'auto') {
                selectedImage.style.width = '';
            } else {
                selectedImage.style.width = size;
            }
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
            if (type === 'table') {
                return '<table class="table table-bordered" data-resizable-table><colgroup><col style="width:15%"><col style="width:55%"><col style="width:30%"></colgroup><thead><tr><th>No.<span class="table-col-resize-handle" data-table-resize="0"></span></th><th>Name<span class="table-col-resize-handle" data-table-resize="1"></span></th><th>Details</th></tr></thead><tbody><tr><td>1</td><td>Student Name</td><td>Description</td></tr></tbody></table>';
            }
            if (type === 'columns') {
                return '<div class="notification-columns"><div class="notification-column"><p><strong>Picture</strong></p><p>Drop or insert image here.</p></div><div class="notification-column"><p><strong>Description</strong></p><p>Type the description here.</p></div></div>';
            }
            if (type === 'address') {
                return '<div class="notification-address-block"><p><strong>Address</strong></p><p>House No.</p><p>Street</p><p>Village</p><p>Commune</p><p>District / Khan</p><p>Province / City</p></div>';
            }
            return '';
        };
        const runCommand = (command, value = null) => {
            editor.focus();
            document.execCommand(command, false, value);
            syncMessage();
        };
        const runAlignCommand = command => {
            editor.focus();
            document.execCommand(command, false, null);
            syncMessage();
        };
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
            const leftWidth = cols[index].getBoundingClientRect().width;
            const rightWidth = cols[index + 1].getBoundingClientRect().width;
            resizeState.active = true;
            resizeState.table = table;
            resizeState.colIndex = index;
            resizeState.startX = event.clientX;
            resizeState.leftWidth = leftWidth;
            resizeState.rightWidth = rightWidth;
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
            const response = await fetch(notificationImageUploadUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
                body: formData,
            });
            if (!response.ok) throw new Error('Image upload failed.');
            return response.json();
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

        document.querySelectorAll('[data-editor-command]').forEach(button => {
            button.addEventListener('click', () => runCommand(button.dataset.editorCommand));
        });
        document.querySelector('[data-editor-link]')?.addEventListener('click', () => {
            const url = window.prompt('Enter link URL');
            if (url) runCommand('createLink', url);
        });
        document.querySelectorAll('[data-editor-align]').forEach(button => {
            button.addEventListener('click', () => runAlignCommand(button.dataset.editorAlign));
        });
        document.querySelectorAll('[data-editor-template]').forEach(button => {
            button.addEventListener('click', () => insertHtmlAtCursor(templateHtml(button.dataset.editorTemplate)));
        });
        document.querySelector('[data-editor-image]')?.addEventListener('click', () => fileInput?.click());
        document.querySelectorAll('[data-image-size]').forEach(button => {
            button.addEventListener('click', () => setImageSize(button.dataset.imageSize));
        });
        document.querySelector('[data-image-remove]')?.addEventListener('click', removeSelectedImage);
        fileInput?.addEventListener('change', event => {
            [...event.target.files].forEach(insertImageFile);
            event.target.value = '';
        });
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
        const closeMenu = () => {
            menu.classList.remove('is-open');
            selectedBox.setAttribute('aria-expanded', 'false');
        };
        const openMenu = () => {
            menu.classList.add('is-open');
            selectedBox.setAttribute('aria-expanded', 'true');
            search.focus();
        };
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
        document.addEventListener('click', event => {
            if (!wrapper.contains(event.target)) closeMenu();
        });
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
        box?.querySelectorAll('select,input,button').forEach(control => { if (control !== all) control.disabled = all.checked; });
    });
    all?.addEventListener('change', syncAll);
    syncAll();
});
</script>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="card"><div class="card-header"><h3 class="card-title">Create Notification</h3></div><form method="POST" action="{{ route('notifications.send.save') }}">@csrf<div class="card-body"><div class="row g-3"><div class="col-md-4"><label class="form-label">Notification Type</label><select class="form-select" name="type"><option value="announcement">Announcement</option><option value="system">System</option><option value="enrollment">Enrollment</option><option value="approval">Approval</option></select></div><div class="col-md-8"><label class="form-label">Title</label><input class="form-control" name="title" value="{{ old('title') }}" required></div><div class="col-12"><div class="notification-editor"><div class="notification-editor-toolbar"><button class="btn btn-outline-secondary" type="button" data-editor-command="bold" title="Bold"><i class="ti ti-bold"></i></button><button class="btn btn-outline-secondary" type="button" data-editor-command="italic" title="Italic"><i class="ti ti-italic"></i></button><button class="btn btn-outline-secondary" type="button" data-editor-command="underline" title="Underline"><i class="ti ti-underline"></i></button><button class="btn btn-outline-secondary" type="button" data-editor-command="insertUnorderedList" title="Bullet list"><i class="ti ti-list"></i></button><button class="btn btn-outline-secondary" type="button" data-editor-command="insertOrderedList" title="Numbered list"><i class="ti ti-list-numbers"></i></button><button class="btn btn-outline-secondary" type="button" data-editor-align="justifyLeft" title="Align left"><i class="ti ti-align-left"></i></button><button class="btn btn-outline-secondary" type="button" data-editor-align="justifyCenter" title="Align center"><i class="ti ti-align-center"></i></button><button class="btn btn-outline-secondary" type="button" data-editor-align="justifyRight" title="Align right"><i class="ti ti-align-right"></i></button><button class="btn btn-outline-secondary" type="button" data-editor-link title="Insert link"><i class="ti ti-link"></i></button><div class="dropdown"><button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Insert layout"><i class="ti ti-layout-grid"></i></button><div class="dropdown-menu dropdown-menu-end p-1 align-menu"><button class="dropdown-item" type="button" data-editor-template="table">Insert Table</button><button class="dropdown-item" type="button" data-editor-template="columns">Picture + Description</button><button class="dropdown-item" type="button" data-editor-template="address">Address Block</button></div></div><button class="btn btn-primary btn-wide" type="button" data-editor-image><i class="ti ti-photo-up me-1"></i> Image</button><input class="d-none" type="file" accept="image/*" data-notification-image-input></div><div class="notification-image-tools" data-notification-image-tools><span class="text-secondary small me-1">Image size</span><button class="btn btn-outline-primary" type="button" data-image-size="25%">25%</button><button class="btn btn-outline-primary" type="button" data-image-size="50%">50%</button><button class="btn btn-outline-primary" type="button" data-image-size="75%">75%</button><button class="btn btn-outline-primary" type="button" data-image-size="100%">100%</button><button class="btn btn-outline-secondary" type="button" data-image-size="auto">Auto</button><button class="btn btn-outline-danger ms-auto" type="button" data-image-remove><i class="ti ti-trash me-1"></i> Remove</button></div><div class="notification-editor-body" contenteditable="true" data-notification-editor aria-label="Notification message" data-placeholder="Write notification message..."></div><div class="notification-editor-help"><span>Supports formatted text and images.</span><span>Click an image to resize or remove it.</span></div></div><textarea class="d-none" name="message" data-notification-message>{{ old('message') }}</textarea></div><div class="col-12"><label class="form-label">Action URL <span class="text-secondary">(optional)</span></label><input class="form-control" type="url" name="action_url" value="{{ old('action_url') }}" placeholder="https://..."></div><div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="send_to_all" value="1" id="sendToAll" @checked(old('send_to_all'))><span class="form-check-label">Send to all active users</span></label></div><div class="col-md-6" id="departmentBox"><select class="form-select d-none" name="department_ids[]" multiple size="7">@foreach($departments as $department)<option value="{{ $department->id }}" @selected($oldDepartmentIds->contains((string) $department->id))>{{ $department->name }}</option>@endforeach</select><small class="text-secondary d-none">Select one or multiple departments.</small></div><div class="col-md-6" id="recipientBox"><select class="form-select" name="recipient_ids[]" multiple size="7">@foreach($users as $user)<option value="{{ $user->id }}" @selected($oldRecipientIds->contains((string) $user->id))>{{ $user->name }} — {{ $user->username }} — {{ $user->email }}</option>@endforeach</select><small class="text-secondary">Staff already covered by selected departments are disabled to avoid duplicate notification.</small></div></div></div><div class="card-footer text-end"><button class="btn btn-primary"><i class="ti ti-send me-1"></i> Send Notification</button></div></form></div>
@endsection
