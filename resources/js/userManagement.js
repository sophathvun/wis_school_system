import * as bootstrap from "bootstrap";

const initStaffPhotoUploader = () => {
if (!document.querySelector('#userModal input[name="date_of_birth"]') || !document.querySelector('#userModal select[name="position_id"]')) return;
if (document.body.dataset.userFormEnhanced) return;
document.body.dataset.userFormEnhanced = '1';
const staffPhotoInput = document.getElementById("staff_photo");
const staffPhotoDropzone = document.getElementById("staffPhotoDropzone");
const staffPhotoPreview = document.getElementById("staffPhotoPreview");
const staffPhotoPreviewContainer = document.getElementById("staffPhotoPreviewContainer");
const staffPhotoCropModalElement = document.getElementById("staffPhotoCropModal");
const staffPhotoCropModal = staffPhotoCropModalElement && bootstrap.Modal
    ? bootstrap.Modal.getOrCreateInstance(staffPhotoCropModalElement)
    : null;
const staffUserModalElement = document.getElementById("userModal");
const staffUserModal = staffUserModalElement && bootstrap.Modal
    ? bootstrap.Modal.getOrCreateInstance(staffUserModalElement)
    : null;
const staffPhotoCropCanvas = document.getElementById("staffPhotoCropCanvas");
const staffPhotoCropContext = staffPhotoCropCanvas?.getContext("2d");
const staffPhotoZoom = document.getElementById("staffPhotoZoom");
let staffPhotoCropImage = null;
let staffPhotoCropScale = 1;
let staffPhotoCropRotation = 0;
let staffPhotoCropOffsetX = 0;
let staffPhotoCropOffsetY = 0;
let staffPhotoCropDragging = false;
let staffPhotoCropStart = null;
let staffReturnToUserModal = false;

if (staffPhotoDropzone && staffPhotoPreviewContainer && !staffPhotoDropzone.contains(staffPhotoPreviewContainer)) {
    staffPhotoDropzone.appendChild(staffPhotoPreviewContainer);
}

const makeUserSearchableSelect = (select, multiple = false) => {
    if (!select || select.dataset.searchableReady) return;
    select.dataset.searchableReady = "1";
    const wrapper = document.createElement("div");
    wrapper.className = "location-combobox user-searchable-combobox";
    const toggle = document.createElement("button");
    toggle.type = "button";
    toggle.className = "location-combobox-toggle";
    toggle.innerHTML = '<span class="location-combobox-selected"></span><i class="ti ti-chevron-down"></i>';
    const menu = document.createElement("div");
    menu.className = "location-combobox-menu d-none";
    const search = document.createElement("input");
    search.type = "search";
    search.className = "form-control location-combobox-search";
    search.placeholder = "Search";
    const results = document.createElement("div");
    results.className = "location-combobox-results";
    menu.append(search, results);
    wrapper.append(toggle, menu);
    select.classList.add("d-none");
    select.parentElement.insertBefore(wrapper, select);

    const selectedText = () => [...select.selectedOptions].map(option => option.textContent.trim()).filter(Boolean).join(", ");
    const sync = () => {
        toggle.querySelector(".location-combobox-selected").textContent = selectedText();
        wrapper.classList.toggle("has-value", Boolean(selectedText()));
        results.querySelectorAll("button").forEach(button => {
            const option = select.querySelector(`option[value="${CSS.escape(button.dataset.value)}"]`);
            button.classList.toggle("is-selected", Boolean(option?.selected));
        });
    };
    const render = () => {
        const term = search.value.trim().toLowerCase();
        const options = [...select.options].filter(option => option.value && option.textContent.toLowerCase().includes(term));
        results.innerHTML = options.length ? options.map(option => `<button type="button" class="location-combobox-option${option.selected ? " is-selected" : ""}" data-value="${option.value}">${option.textContent}</button>`).join("") : '<div class="text-secondary px-2 py-2">No options found</div>';
    };
    toggle.addEventListener("click", event => {
        event.stopPropagation();
        document.querySelectorAll(".user-searchable-combobox.is-open").forEach(combo => { if (combo !== wrapper) combo.classList.remove("is-open"); });
        wrapper.classList.toggle("is-open");
        menu.classList.toggle("d-none", !wrapper.classList.contains("is-open"));
        if (wrapper.classList.contains("is-open")) { render(); search.focus(); }
    });
    search.addEventListener("input", render);
    search.addEventListener("click", event => event.stopPropagation());
    results.addEventListener("click", event => {
        const button = event.target.closest("button[data-value]");
        if (!button) return;
        const option = select.querySelector(`option[value="${CSS.escape(button.dataset.value)}"]`);
        if (!option) return;
        if (multiple) option.selected = !option.selected;
        else { select.value = option.value; wrapper.classList.remove("is-open"); menu.classList.add("d-none"); }
        select.dispatchEvent(new Event("change", { bubbles: true }));
        sync();
        if (multiple) render();
    });
    select.addEventListener("change", sync);
    document.addEventListener("click", event => {
        if (!wrapper.contains(event.target)) { wrapper.classList.remove("is-open"); menu.classList.add("d-none"); }
    });
    render();
    sync();
};

const genderSelect = document.querySelector('#userModal select[name="gender"]');
document.querySelectorAll('#userModal select option[value=""]').forEach(option => { option.textContent = ""; });
const dobInput = document.querySelector('#userModal input[name="date_of_birth"]');
if (dobInput) dobInput.removeAttribute("placeholder");
document.querySelector('#userModal details')?.remove();
if (dobInput) {
    const dateColumn = dobInput.closest('.col-md-3');
    if (dateColumn && !dateColumn.querySelector('.user-date-picker')) {
        const initialValue = dobInput.value || '';
        const picker = document.createElement('div');
        picker.className = 'date-picker user-date-picker';
        const initialDisplay = initialValue ? initialValue.split('-').reverse().join('-') : '';
        picker.innerHTML = `<div class="date-picker-input-row"><input type="text" class="form-control date-picker-direct" inputmode="numeric" value="${initialDisplay}" aria-label="Date of Birth"><button type="button" class="date-picker-trigger date-picker-calendar-button" aria-label="Open calendar"><i class="ti ti-calendar"></i></button></div><input type="hidden" name="date_of_birth" value="${initialValue}"><div class="date-picker-popup d-none"><div class="date-picker-header"><button type="button" class="date-picker-nav" data-date-prev><i class="ti ti-chevron-left"></i></button><button type="button" class="date-picker-year-toggle" data-date-month></button><button type="button" class="date-picker-nav" data-date-next><i class="ti ti-chevron-right"></i></button></div><div class="date-picker-grid"><div class="date-picker-weekdays"><span>SU</span><span>MO</span><span>TU</span><span>WE</span><span>TH</span><span>FR</span><span>SA</span></div><div class="date-picker-days"></div></div></div>`;
        dobInput.remove();
        dateColumn.appendChild(picker);
        dateColumn.classList.add('premium-form-field');
        const hiddenDate = picker.querySelector('input[name="date_of_birth"]');
        const directInput = picker.querySelector('.date-picker-direct');
        const trigger = picker.querySelector('.date-picker-trigger');
        const popup = picker.querySelector('.date-picker-popup');
        const days = picker.querySelector('.date-picker-days');
        const monthButton = picker.querySelector('[data-date-month]');
        let cursor = initialValue ? new Date(`${initialValue}T00:00:00`) : new Date();
        cursor = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
        const iso = date => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        const render = () => {
            monthButton.textContent = cursor.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
            const first = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
            const count = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 0).getDate();
            const cells = [];
            for (let i = 0; i < first.getDay(); i++) cells.push(new Date(cursor.getFullYear(), cursor.getMonth(), i - first.getDay() + 1));
            for (let day = 1; day <= count; day++) cells.push(new Date(cursor.getFullYear(), cursor.getMonth(), day));
            while (cells.length < 42) cells.push(new Date(cursor.getFullYear(), cursor.getMonth() + 1, cells.length - first.getDay() - count + 1));
            days.innerHTML = cells.map(date => `<button type="button" class="date-picker-day${date.getMonth() !== cursor.getMonth() ? ' is-outside' : ''}${iso(date) === hiddenDate.value ? ' is-selected' : ''}" data-date-value="${iso(date)}">${date.getDate()}</button>`).join('');
        };
        trigger.addEventListener('click', event => { event.stopPropagation(); popup.classList.toggle('d-none'); render(); });
        picker.querySelector('[data-date-prev]').addEventListener('click', () => { cursor = new Date(cursor.getFullYear(), cursor.getMonth() - 1, 1); render(); });
        picker.querySelector('[data-date-next]').addEventListener('click', () => { cursor = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 1); render(); });
        days.addEventListener('click', event => {
            const button = event.target.closest('[data-date-value]');
            if (!button) return;
            hiddenDate.value = button.dataset.dateValue;
            directInput.value = button.dataset.dateValue.split('-').reverse().join('-');
            picker.classList.add('has-value');
            popup.classList.add('d-none');
            render();
            hiddenDate.dispatchEvent(new Event('change', { bubbles: true }));
        });
        directInput.addEventListener('input', () => {
            const value = directInput.value.trim();
            const match = value.match(/^(\d{1,2})[-\/]?(\d{1,2})[-\/]?(\d{4})$/);
            if (!match) { hiddenDate.value = ''; picker.classList.remove('has-value'); return; }
            const day = String(match[1]).padStart(2, '0');
            const month = String(match[2]).padStart(2, '0');
            const year = match[3];
            const date = new Date(Number(year), Number(month) - 1, Number(day));
            if (date.getFullYear() !== Number(year) || date.getMonth() !== Number(month) - 1 || date.getDate() !== Number(day)) return;
            hiddenDate.value = `${year}-${month}-${day}`;
            cursor = new Date(Number(year), Number(month) - 1, 1);
            picker.classList.add('has-value');
            hiddenDate.dispatchEvent(new Event('change', { bubbles: true }));
        });
        document.addEventListener('click', event => { if (!picker.contains(event.target)) popup.classList.add('d-none'); });
        if (initialValue) picker.classList.add('has-value');
        render();
    }
}
const positionSelect = document.querySelector('#userModal select[name="position_id"]');
const statusSelect = document.querySelector('#userModal select[name="status"]');
const statusColumn = statusSelect?.closest('.col-md-4');
const userIdInput = document.querySelector('#userModal input[name="user_id"]');
if (statusSelect && !userIdInput?.value) statusSelect.value = '1';
if (statusSelect && statusColumn) {
    const globalCheck = statusColumn.querySelector('label.form-check');
    const controls = document.createElement('div');
    controls.className = 'user-status-controls d-flex align-items-center gap-3';
    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = `status-toggle ${statusSelect.value === '1' ? 'is-active' : ''}`;
    toggle.innerHTML = `<span class="status-toggle-label">${statusSelect.value === '1' ? 'ON' : 'OFF'}</span><span class="status-toggle-knob"></span>`;
    toggle.addEventListener('click', () => {
        const active = statusSelect.value !== '1';
        statusSelect.value = active ? '1' : '0';
        toggle.className = `status-toggle ${active ? 'is-active' : ''}`;
        toggle.innerHTML = `<span class="status-toggle-label">${active ? 'ON' : 'OFF'}</span><span class="status-toggle-knob"></span>`;
    });
    statusSelect.classList.add('d-none');
    statusColumn.querySelector('.form-label')?.remove();
    if (globalCheck) controls.append(globalCheck);
    controls.append(toggle, statusSelect);
    statusColumn.append(controls);
}
const userPassword = document.querySelector('#userModal input[name="password"]');
const userPasswordConfirmation = document.querySelector('#userModal input[name="password_confirmation"]');
const addUserPasswordToggle = input => {
    if (!input || input.parentElement.classList.contains('premium-password-field')) return;
    const wrapper = document.createElement('div');
    wrapper.className = 'premium-password-field premium-form-field';
    input.parentElement.insertBefore(wrapper, input);
    wrapper.appendChild(input);
    const label = input.closest('[class*="col-"]')?.querySelector('.form-label');
    if (label) wrapper.prepend(label);
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'premium-password-toggle';
    button.setAttribute('aria-label', 'Show password');
    button.innerHTML = '<i class="ti ti-eye"></i>';
    button.addEventListener('click', () => {
        const visible = input.type === 'text';
        input.type = visible ? 'password' : 'text';
        button.setAttribute('aria-label', visible ? 'Show password' : 'Hide password');
        button.innerHTML = `<i class="ti ${visible ? 'ti-eye' : 'ti-eye-off'}"></i>`;
    });
    wrapper.appendChild(button);
    const syncLabel = () => wrapper.classList.toggle('has-value', Boolean(input.value));
    input.addEventListener('input', syncLabel);
    syncLabel();
};
addUserPasswordToggle(userPassword);
addUserPasswordToggle(userPasswordConfirmation);
if (userPassword && !userPassword.closest('.col-md-3')?.querySelector('.profile-password-strength')) {
    const strength = document.createElement('div');
    strength.className = 'profile-password-strength';
    strength.innerHTML = '<div class="profile-password-strength-header"><span>Password Strength</span><span class="profile-password-strength-value">Weak</span></div><div class="profile-password-strength-bar"><span class="profile-password-strength-fill"></span></div><div class="profile-password-rules"><span class="profile-password-rule" data-rule="length">8 Chars</span><span class="profile-password-rule" data-rule="upper">A-Z</span><span class="profile-password-rule" data-rule="lower">a-z</span><span class="profile-password-rule" data-rule="number">123</span><span class="profile-password-rule" data-rule="special">@#$</span></div>';
    userPassword.closest('.premium-password-field')?.after(strength);
    const note = userPassword.closest('[class*="col-"]')?.querySelector('small.text-secondary');
    const rules = strength.querySelector('.profile-password-rules');
    if (note && rules) {
        const meta = document.createElement('div');
        meta.className = 'profile-password-meta';
        note.classList.add('profile-password-note');
        meta.append(note, rules);
        strength.appendChild(meta);
    }
    const updateStrength = () => {
        const value = userPassword.value;
        const checks = { length: value.length >= 8, upper: /[A-Z]/.test(value), lower: /[a-z]/.test(value), number: /\d/.test(value), special: /[^A-Za-z0-9]/.test(value) };
        Object.entries(checks).forEach(([rule, valid]) => strength.querySelector(`[data-rule="${rule}"]`)?.classList.toggle('is-valid', valid));
        const score = Object.values(checks).filter(Boolean).length;
        const level = score >= 4 ? 'strong' : score >= 2 ? 'medium' : '';
        const label = strength.querySelector('.profile-password-strength-value');
        const fill = strength.querySelector('.profile-password-strength-fill');
        label.textContent = score >= 4 ? 'Strong' : score >= 2 ? 'Medium' : 'Weak';
        label.className = `profile-password-strength-value ${level}`;
        fill.style.width = `${score * 20}%`;
        fill.className = `profile-password-strength-fill ${level}`;
    };
    userPassword.addEventListener('input', updateStrength);
    updateStrength();
}
const userFieldsRow = document.querySelector('#userModal .modal-body > .row.g-3');
if (userFieldsRow && !userFieldsRow.dataset.userColumnsReady) {
    userFieldsRow.dataset.userColumnsReady = '1';
    const modalBody = userFieldsRow.parentElement;
    const photoField = userFieldsRow.querySelector('input[type="file"]')?.closest('.col-12');
    const field = selector => userFieldsRow.querySelector(selector)?.closest('[class*="col-"]');
    const main = document.createElement('div');
    main.className = 'user-form-main';
    const side = document.createElement('div');
    side.className = 'user-form-password';
    const rows = [
        ['input[name="name"]', 'select[name="gender"]', '.user-date-picker'],
        ['input[name="phone"]', 'select[name="position_id"]', 'select[name="department_id"]'],
        ['select[name="campuses[]"]', 'select[name="role_id"]', 'select[name="login_identifier"]'],
        ['input[name="username"]', 'input[name="email"]', '.user-status-controls'],
    ];
    rows.forEach(selectors => {
        const row = document.createElement('div');
        row.className = 'row g-3 mb-3';
        selectors.forEach(selector => {
            const item = field(selector) || userFieldsRow.querySelector(selector)?.closest('[class*="col-"]');
            if (item) { item.className = 'col-md-4'; row.appendChild(item); }
        });
        main.appendChild(row);
    });
    [userPassword, userPasswordConfirmation].forEach(input => {
        const item = input?.closest('[class*="col-"]');
        if (item) { item.className = 'mb-3'; side.appendChild(item); }
    });
    const layout = document.createElement('div');
    layout.className = 'user-form-layout';
    layout.append(main, side);
    userFieldsRow.remove();
    if (photoField) {
        const photoRow = document.createElement('div');
        photoRow.className = 'row g-3 mb-3';
        photoRow.appendChild(photoField);
        modalBody.insertBefore(photoRow, modalBody.firstChild);
    }
    modalBody.insertBefore(layout, modalBody.firstChild?.nextSibling || null);
}
const searchableSelects = [
    [document.querySelector('#userModal select[name="department_id"]'), false],
    [positionSelect, false],
    [document.querySelector('#userModal select[name="role_id"]'), false],
    [document.querySelector('#userModal select[name="campuses[]"]'), true],
];
searchableSelects.forEach(([select, multiple]) => makeUserSearchableSelect(select, multiple));

const showStaffPhotoPreview = (file) => {
    if (!staffPhotoPreview || !staffPhotoPreviewContainer || !file) return;
    staffPhotoPreview.src = URL.createObjectURL(file);
    staffPhotoPreviewContainer.classList.remove("d-none");
};

const drawStaffPhotoCrop = () => {
    if (!staffPhotoCropContext || !staffPhotoCropCanvas || !staffPhotoCropImage) return;
    const canvas = staffPhotoCropCanvas;
    const context = staffPhotoCropContext;
    const image = staffPhotoCropImage;
    context.clearRect(0, 0, canvas.width, canvas.height);
    context.fillStyle = "#fff";
    context.fillRect(0, 0, canvas.width, canvas.height);
    const baseScale = Math.max(canvas.width / image.width, canvas.height / image.height);
    const scale = baseScale * staffPhotoCropScale;
    context.save();
    context.translate(canvas.width / 2 + staffPhotoCropOffsetX, canvas.height / 2 + staffPhotoCropOffsetY);
    context.rotate(staffPhotoCropRotation * Math.PI / 180);
    context.drawImage(image, -(image.width * scale) / 2, -(image.height * scale) / 2, image.width * scale, image.height * scale);
    context.restore();
};

const resetStaffPhotoCrop = () => {
    staffPhotoCropScale = 1;
    staffPhotoCropRotation = 0;
    staffPhotoCropOffsetX = 0;
    staffPhotoCropOffsetY = 0;
    if (staffPhotoZoom) staffPhotoZoom.value = "1";
    drawStaffPhotoCrop();
};

const openStaffPhotoCrop = (file) => {
    const isImage = file && ((file.type || "").startsWith("image/") || /\.(jpe?g|png|webp|gif|bmp|heic|heif)$/i.test(file.name || ""));
    if (!isImage) return;
    const reader = new FileReader();
    reader.onload = () => {
        const image = new Image();
        image.onload = () => {
            staffPhotoCropImage = image;
            resetStaffPhotoCrop();
            const showCropModal = () => staffPhotoCropModal?.show();
            if (staffUserModalElement?.classList.contains("show") && staffUserModal) {
                staffReturnToUserModal = true;
                staffUserModalElement.addEventListener("hidden.bs.modal", showCropModal, { once: true });
                staffUserModal.hide();
            } else {
                showCropModal();
            }
        };
        image.src = reader.result;
    };
    reader.readAsDataURL(file);
};

staffPhotoCropModalElement?.addEventListener("hidden.bs.modal", () => {
    if (!staffReturnToUserModal) return;
    staffReturnToUserModal = false;
    staffUserModal?.show();
});

const setStaffPhotoFile = (blob) => {
    const file = new File([blob], "staff-photo.jpg", { type: "image/jpeg" });
    const transfer = new DataTransfer();
    transfer.items.add(file);
    if (staffPhotoInput) staffPhotoInput.files = transfer.files;
    showStaffPhotoPreview(file);
};

staffPhotoInput?.addEventListener("change", () => {
    const file = staffPhotoInput.files?.[0];
    if (!file) return;
    staffPhotoInput.value = "";
    openStaffPhotoCrop(file);
});

staffPhotoInput?.addEventListener("click", (event) => event.stopPropagation());
staffPhotoDropzone?.addEventListener("click", () => staffPhotoInput?.click());
staffPhotoDropzone?.addEventListener("keydown", (event) => {
    if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        staffPhotoInput?.click();
    }
});
staffPhotoDropzone?.addEventListener("dragover", (event) => {
    event.preventDefault();
    staffPhotoDropzone.classList.add("is-dragging");
});
staffPhotoDropzone?.addEventListener("dragleave", () => staffPhotoDropzone.classList.remove("is-dragging"));
staffPhotoDropzone?.addEventListener("drop", (event) => {
    event.preventDefault();
    staffPhotoDropzone.classList.remove("is-dragging");
    openStaffPhotoCrop(event.dataTransfer?.files?.[0]);
});

staffPhotoZoom?.addEventListener("input", () => {
    staffPhotoCropScale = Number(staffPhotoZoom.value);
    drawStaffPhotoCrop();
});
document.getElementById("staffPhotoZoomIn")?.addEventListener("click", () => {
    if (!staffPhotoZoom) return;
    staffPhotoZoom.value = Math.min(3, Number(staffPhotoZoom.value) + .1).toFixed(2);
    staffPhotoZoom.dispatchEvent(new Event("input"));
});
document.getElementById("staffPhotoZoomOut")?.addEventListener("click", () => {
    if (!staffPhotoZoom) return;
    staffPhotoZoom.value = Math.max(1, Number(staffPhotoZoom.value) - .1).toFixed(2);
    staffPhotoZoom.dispatchEvent(new Event("input"));
});
document.getElementById("staffPhotoRotateLeft")?.addEventListener("click", () => { staffPhotoCropRotation -= 90; drawStaffPhotoCrop(); });
document.getElementById("staffPhotoRotateRight")?.addEventListener("click", () => { staffPhotoCropRotation += 90; drawStaffPhotoCrop(); });
document.getElementById("staffPhotoReset")?.addEventListener("click", resetStaffPhotoCrop);

staffPhotoCropCanvas?.addEventListener("pointerdown", (event) => {
    staffPhotoCropDragging = true;
    staffPhotoCropStart = { x: event.clientX, y: event.clientY };
    staffPhotoCropCanvas.setPointerCapture(event.pointerId);
});
staffPhotoCropCanvas?.addEventListener("pointermove", (event) => {
    if (!staffPhotoCropDragging || !staffPhotoCropStart) return;
    const rect = staffPhotoCropCanvas.getBoundingClientRect();
    const scaleX = staffPhotoCropCanvas.width / rect.width;
    const scaleY = staffPhotoCropCanvas.height / rect.height;
    staffPhotoCropOffsetX += (event.clientX - staffPhotoCropStart.x) * scaleX;
    staffPhotoCropOffsetY += (event.clientY - staffPhotoCropStart.y) * scaleY;
    staffPhotoCropStart = { x: event.clientX, y: event.clientY };
    drawStaffPhotoCrop();
});
["pointerup", "pointercancel"].forEach((type) => staffPhotoCropCanvas?.addEventListener(type, () => {
    staffPhotoCropDragging = false;
    staffPhotoCropStart = null;
}));

document.getElementById("staffPhotoCropUpload")?.addEventListener("click", () => {
    if (!staffPhotoCropCanvas) return;
    staffPhotoCropCanvas.toBlob((blob) => {
        if (!blob) return;
        setStaffPhotoFile(blob);
        staffPhotoCropModal?.hide();
    }, "image/jpeg", .9);
});
};

if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", initStaffPhotoUploader, { once: true });
else initStaffPhotoUploader();
window.addEventListener("load", initStaffPhotoUploader, { once: true });
