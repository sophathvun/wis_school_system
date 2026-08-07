import { renderPagination, renderPageInfo } from "./helpers/pagination.js";
import { showSuccess, showConfirm, showError } from "./helpers/sweet-alert2.js";
import intlTelInput from "intl-tel-input";
import "intl-tel-input/styles";

const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const form = document.getElementById("enrollmentForm");
const modal = new bootstrap.Modal(document.getElementById("enrollmentModal"));
const historyModal = new bootstrap.Modal(document.getElementById("enrollmentHistoryModal"));
const table = document.getElementById("enrollmentsTable");
const search = document.getElementById("enrollments-search");
const perPage = document.getElementById("enrollments-per-page");
const submit = document.getElementById("enrollmentSubmit");
const title = document.getElementById("enrollmentModalTitle");
const studentPhotoInput = document.getElementById("student_photo");
const studentPhotoDropzone = document.getElementById("studentPhotoDropzone");
const studentPhotoPreview = document.getElementById("studentPhotoPreview");
const studentPhotoPreviewContainer = document.getElementById("studentPhotoPreviewContainer");
const studentPhotoViewModalElement = document.getElementById("studentPhotoViewModal");
const studentPhotoViewModal = studentPhotoViewModalElement ? new bootstrap.Modal(studentPhotoViewModalElement) : null;
const studentPhotoViewImage = document.getElementById("studentPhotoViewImage");
const studentPhotoViewTitle = document.getElementById("studentPhotoViewTitle");
const studentPhotoViewDownload = document.getElementById("studentPhotoViewDownload");
const studentPhotoViewZoom = document.getElementById("studentPhotoViewZoom");
const studentPhotoCropModalElement = document.getElementById("studentPhotoCropModal");
const studentPhotoCropModal = studentPhotoCropModalElement ? new bootstrap.Modal(studentPhotoCropModalElement) : null;
const studentPhotoCropCanvas = document.getElementById("studentPhotoCropCanvas");
const studentPhotoCropContext = studentPhotoCropCanvas?.getContext("2d");
const studentPhotoZoom = document.getElementById("studentPhotoZoom");
let studentPhotoCropImage = null;
let studentPhotoCropScale = 1;
let studentPhotoCropRotation = 0;
let studentPhotoCropOffsetX = 0;
let studentPhotoCropOffsetY = 0;
let studentPhotoCropDragging = false;
let studentPhotoCropStart = null;
const enrollmentHistoryTable = document.getElementById("enrollmentHistoryTable");
const field = (id) => document.getElementById(id);
document.addEventListener("click", (event) => {
    const toggle = event.target.closest(".location-combobox-toggle");
    if (!toggle) return;
    document.querySelectorAll(".location-combobox-menu").forEach((menu) => {
        const parent = menu.closest(".location-combobox");
        if (!parent?.contains(toggle)) menu.classList.add("d-none");
    });
}, true);
const familyPhoneInputs = ["mother", "father", "guardian"].map((type) => {
    const visible = field(`${type}_phone_number`);
    const hidden = field(`${type}_phone`);
    return { visible, hidden, intl: visible ? intlTelInput(visible, { initialCountry: "kh", nationalMode: true, separateDialCode: true, loadUtils: () => import("intl-tel-input/utils") }) : null };
});
const homePhoneVisible = field("home_phone_number");
const homePhoneHidden = field("home_phone");
const homePhoneIntl = homePhoneVisible ? intlTelInput(homePhoneVisible, { initialCountry: "kh", nationalMode: true, separateDialCode: true, loadUtils: () => import("intl-tel-input/utils") }) : null;
const phoneVisibleInputs = [...familyPhoneInputs.map(({ visible }) => visible), homePhoneVisible].filter(Boolean);
phoneVisibleInputs.forEach((input) => input.addEventListener("input", () => { input.value = input.value.replace(/\s+/g, ""); }));
const syncFamilyPhoneValues = () => familyPhoneInputs.forEach(({ visible, hidden, intl }) => {
    if (hidden) hidden.value = intl?.getNumber() || visible?.value.trim() || "";
});
const syncHomePhoneValue = () => { if (homePhoneHidden) homePhoneHidden.value = homePhoneIntl?.getNumber() || homePhoneVisible?.value.trim() || ""; };
const premiumFloatingWrappers = () => Array.from(document.querySelectorAll("#enrollmentModal .modal-body .row.g-3 > .col, #enrollmentModal .modal-body .row.g-3 > [class*='col-'], #enrollmentModal .family-member-row > [class*='col-']"))
    .filter((wrapper) => !wrapper.querySelector("input[type='file']") && !!wrapper.querySelector(".form-label") && !!wrapper.querySelector("input:not([type='file']):not([type='hidden']), textarea, select, .location-combobox, .phone-input-group, .date-picker, .input-icon"))
    .map((wrapper) => {
        wrapper.classList.add("premium-floating-field");
        return wrapper;
    });
const premiumFieldValue = (wrapper) => {
    const combo = wrapper.querySelector(".location-combobox-selected");
    if (combo) {
        const text = combo.textContent?.trim() || "";
        if (text && !/^Select\b/i.test(text)) return true;
    }
    const select = wrapper.querySelector("select");
    if (select && String(select.value || "").trim()) return true;
    const input = wrapper.querySelector("input:not([type='file']):not([type='hidden']), textarea");
    if (input && String(input.value || "").trim()) return true;
    return false;
};
const normalizeEnrollmentControlBackgrounds = () => {
    document.querySelectorAll("#enrollmentModal input.form-control, #enrollmentModal textarea.form-control, #enrollmentModal select.form-select, #enrollmentModal .location-combobox-toggle, #enrollmentModal .date-picker-trigger, #enrollmentModal .input-icon, #enrollmentModal .phone-input-group, #enrollmentModal .phone-input-group .iti").forEach((control) => {
        control.style.setProperty("background-color", "#fff", "important");
        if (control.matches("input.form-control, textarea.form-control")) {
            control.style.setProperty("-webkit-box-shadow", "inset 0 0 0 1000px #fff", "important");
        }
    });
    document.querySelectorAll("#enrollmentModal #date_of_birth_trigger, #enrollmentModal #enrolled_on_trigger").forEach((button) => {
        button.style.setProperty("position", "absolute", "important");
        button.style.setProperty("top", "50%", "important");
        button.style.setProperty("right", ".25rem", "important");
        button.style.setProperty("width", "42px", "important");
        button.style.setProperty("height", "32px", "important");
        button.style.setProperty("padding", "0", "important");
        button.style.setProperty("transform", "translateY(-50%)", "important");
        button.style.setProperty("background", "transparent", "important");
    });
    document.querySelectorAll("#enrollmentModal #date_of_birth_direct, #enrollmentModal #enrolled_on_direct").forEach((input) => {
        input.style.setProperty("padding-top", ".75rem", "important");
        input.style.setProperty("padding-bottom", ".75rem", "important");
    });
};
const syncDatePickerVisualStates = () => {
    [
        ["date_of_birth_picker", "date_of_birth", "date_of_birth_direct"],
        ["enrolled_on_picker", "enrolled_on", "enrolled_on_direct"],
    ].forEach(([pickerId, hiddenId, directId]) => {
        const picker = field(pickerId);
        const hasValue = Boolean(field(hiddenId)?.value?.trim() || field(directId)?.value?.trim());
        if (!picker) return;
        picker.classList.toggle("has-value", hasValue);
        picker.style.setProperty("border-color", hasValue ? "#6b5bd6" : "#dfe3ea", "important");
        picker.style.setProperty("box-shadow", hasValue
            ? "0 0 0 3px rgba(107, 91, 214, .14)"
            : "0 2px 7px rgba(31, 41, 55, .04)", "important");
    });
};
const refreshPremiumFieldStates = () => {
    normalizeEnrollmentControlBackgrounds();
    syncDatePickerVisualStates();
    premiumFloatingWrappers().forEach((wrapper) => {
        wrapper.classList.toggle("has-value", premiumFieldValue(wrapper));
    });
    document.querySelectorAll("#enrollmentModal .phone-input-group .form-control").forEach((input) => {
        const wrapper = input.closest(".premium-floating-field");
        wrapper?.classList.toggle("has-value", Boolean(input.value?.trim()));
    });
};
phoneVisibleInputs.forEach((input) => {
    input.addEventListener("input", refreshPremiumFieldStates);
    input.addEventListener("countrychange", refreshPremiumFieldStates);
});
document.getElementById("enrollmentModal")?.addEventListener("shown.bs.modal", () => {
    refreshPremiumFieldStates();
    window.setTimeout(refreshPremiumFieldStates, 50);
});
document.addEventListener("input", (event) => {
    if (!event.target.closest("#enrollmentModal")) return;
    refreshPremiumFieldStates();
});
document.addEventListener("change", (event) => {
    if (!event.target.closest("#enrollmentModal")) return;
    refreshPremiumFieldStates();
});
const dobPicker = field("date_of_birth_picker");
const dobTrigger = field("date_of_birth_trigger");
const dobDisplay = field("date_of_birth_display");
const dobPopup = field("date_of_birth_popup");
const dobYearToggle = field("date_of_birth_year_toggle");
const dobYearPopup = field("date_of_birth_year_popup");
const dobYears = field("date_of_birth_years");
const dobPrev = field("date_of_birth_prev");
const dobNext = field("date_of_birth_next");
const dobMonthLabel = field("date_of_birth_month_label");
const dobDays = field("date_of_birth_days");
const birthFields = ["country", "province", "district", "commune", "village"];
const birthConfig = {
    country: { key: "countries", parentField: null, en: "country_name_en", kh: "country_name_kh", label: "Country" },
    province: { key: "provinces", parentField: "country_id", en: "province_name_en", kh: "province_name_kh", label: "Province / City" },
    district: { key: "districts", parentField: "province_id", en: "district_name_en", kh: "district_name_kh", label: "District / Khan" },
    commune: { key: "communes", parentField: "district_id", en: "commune_name_en", kh: "commune_name_kh", label: "Commune" },
    village: { key: "villages", parentField: "commune_id", en: "village_name_en", kh: "village_name_kh", label: "Village" },
};
let rows = [];
let birthLocations = {};
let addressLocations = {};
let campusItems = [];
let familyItems = [];
let familyDetails = {};
let nextStudentNo = "";
let dobCursor = new Date();

const escapeHtml = (value = "") => String(value)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
const formatDateTime = (value) => value ? new Date(value).toLocaleString() : "-";
const bilingualSelectedLabel = (kh = "", en = "") => [kh, en].filter(Boolean).join(" / ");
const emptySelectedLabel = "";

const studentPhotoMarkup = (student) => {
    if (student?.photo_path) {
        const url = `/storage/${escapeHtml(student.photo_path)}`;
        const name = escapeHtml(student.full_name_en || [student.first_name_en, student.last_name_en].filter(Boolean).join(" ") || student.student_id || "Student Photo");
        return `<button type="button" class="btn p-0 border-0 student-photo-view-trigger" data-photo-url="${url}" data-photo-title="${name}" aria-label="View student photo"><img src="${url}" alt="${name}" style="width:44px;height:44px;object-fit:cover;border-radius:.5rem;border:1px solid var(--tblr-border-color);background:var(--tblr-bg-surface);"></button>`;
    }
    return `<span class="avatar avatar-sm" style="border-radius:.5rem;">${escapeHtml((student?.first_name_en || student?.student_no || "?").charAt(0).toUpperCase())}</span>`;
};
const setStudentPhotoViewZoom = (value) => { const zoom = Number(value); if (studentPhotoViewZoom) studentPhotoViewZoom.value = String(zoom); if (studentPhotoViewImage) studentPhotoViewImage.style.transform = `scale(${zoom})`; };
document.addEventListener("click", (event) => {
    const trigger = event.target.closest(".student-photo-view-trigger");
    if (!trigger) return;
    const url = trigger.dataset.photoUrl;
    if (studentPhotoViewImage) studentPhotoViewImage.src = url;
    if (studentPhotoViewDownload) { studentPhotoViewDownload.href = url; studentPhotoViewDownload.download = `${(trigger.dataset.photoTitle || "student-photo").replace(/[^a-z0-9_-]+/gi, "_")}.jpg`; }
    if (studentPhotoViewTitle) studentPhotoViewTitle.textContent = trigger.dataset.photoTitle || "Student Photo";
    setStudentPhotoViewZoom(1);
    studentPhotoViewModal?.show();
});
studentPhotoViewZoom?.addEventListener("input", () => setStudentPhotoViewZoom(studentPhotoViewZoom.value));
document.getElementById("studentPhotoViewZoomIn")?.addEventListener("click", () => setStudentPhotoViewZoom(Math.min(3, Number(studentPhotoViewZoom.value) + .25)));
document.getElementById("studentPhotoViewZoomOut")?.addEventListener("click", () => setStudentPhotoViewZoom(Math.max(1, Number(studentPhotoViewZoom.value) - .25)));
document.getElementById("studentPhotoViewZoomReset")?.addEventListener("click", () => setStudentPhotoViewZoom(1));

const pad2 = (value) => String(value).padStart(2, "0");
const formatDobIso = (date) => `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}`;
const parseDob = (value = "") => {
    if (!value) return null;
    const normalized = /^\d{2}-\d{2}-\d{4}$/.test(value)
        ? `${value.slice(6, 10)}-${value.slice(3, 5)}-${value.slice(0, 2)}`
        : value;
    const date = new Date(`${normalized}T00:00:00`);
    return Number.isNaN(date.getTime()) ? null : date;
};
const formatDobDirect = (value = "") => { const date = parseDob(value); return date ? `${pad2(date.getDate())}-${pad2(date.getMonth() + 1)}-${date.getFullYear()}` : ""; };
const dobDisplayLabel = (date) => date.toLocaleDateString("en-US", {
    month: "long",
    day: "numeric",
    year: "numeric",
});
const dobMonthLabelText = (date) => date.toLocaleDateString("en-US", {
    month: "long",
    year: "numeric",
});
const renderDobYears = () => {
    if (!dobYears) return;
    const current = dobCursor.getFullYear();
    const startYear = 1900;
    const endYear = new Date().getFullYear() + 5;
    dobYears.innerHTML = Array.from({ length: endYear - startYear + 1 }, (_, index) => {
        const year = startYear + index;
        const selectedClass = year === current ? " is-selected" : "";
        return `<button type="button" class="date-picker-year${selectedClass}" data-dob-year="${year}">${year}</button>`;
    }).join("");
};
const toKhmerDigits = (value) => String(value ?? "").replace(/[0-9]/g, (digit) => "០១២៣៤៥៦៧៨៩"[Number(digit)]);
const khmerMonths = [
    "\u1798\u1780\u179a\u17b6", "\u1780\u17bb\u1798\u17d2\u1797\u17c8", "\u1798\u17b8\u1793\u17b6", "\u1798\u17c1\u179f\u17b6",
    "\u17a7\u179f\u1797\u17b6", "\u1798\u17b7\u1790\u17bb\u1793\u17b6", "\u1780\u1780\u17d2\u1780\u178a\u17b6", "\u179f\u17b8\u17a0\u17b6",
    "\u1780\u1789\u17d2\u1789\u17b6", "\u178f\u17bb\u179b\u17b6", "\u179c\u17b7\u1785\u17d2\u1786\u17b7\u1780\u17b6", "\u1792\u17d2\u1793\u17bc",
];
const formatDobKhmer = (iso) => {
    const date = parseDob(iso);
    if (!date) return "";
    const day = String(date.getDate()).padStart(2, "0");
    const year = String(date.getFullYear());
    return `${toKhmerDigits(day)} ${khmerMonths[date.getMonth()]} ${toKhmerDigits(year)}`;
};
const syncDobDisplay = () => {
    if (!dobDisplay || !field("date_of_birth")) return;
    const selected = parseDob(field("date_of_birth").value);
    if (field("date_of_birth_direct")) field("date_of_birth_direct").value = formatDobDirect(field("date_of_birth").value);
    if (field("date_of_birth_kh")) field("date_of_birth_kh").value = formatDobKhmer(field("date_of_birth").value);
    dobDisplay.textContent = selected ? dobDisplayLabel(selected) : "Choose your date";
    refreshPremiumFieldStates();
};
const renderDobCalendar = () => {
    if (!dobDays || !dobMonthLabel) return;
    const year = dobCursor.getFullYear();
    const month = dobCursor.getMonth();
    dobMonthLabel.textContent = dobMonthLabelText(dobCursor);
    const selected = parseDob(field("date_of_birth")?.value || "");
    const firstDay = new Date(year, month, 1);
    const startOffset = firstDay.getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const prevDaysInMonth = new Date(year, month, 0).getDate();
    const cells = [];

    for (let index = startOffset - 1; index >= 0; index -= 1) {
        const day = prevDaysInMonth - index;
        const date = new Date(year, month - 1, day);
        cells.push({ date, outside: true });
    }

    for (let day = 1; day <= daysInMonth; day += 1) {
        const date = new Date(year, month, day);
        cells.push({ date, outside: false });
    }

    while (cells.length < 42) {
        const nextDay = cells.length - (startOffset + daysInMonth) + 1;
        const date = new Date(year, month + 1, nextDay);
        cells.push({ date, outside: true });
    }

    dobDays.innerHTML = cells.map(({ date, outside }) => {
        const iso = formatDobIso(date);
        const selectedClass = selected && date.getFullYear() === selected.getFullYear()
            && date.getMonth() === selected.getMonth()
            && date.getDate() === selected.getDate()
            ? " is-selected"
            : "";
        const outsideClass = outside ? " is-outside" : "";
        return `<button type="button" class="date-picker-day${outsideClass}${selectedClass}" data-dob-date="${iso}">${date.getDate()}</button>`;
    }).join("");
    renderDobYears();
};
const openDobPicker = () => {
    if (!dobPopup) return;
    const selected = parseDob(field("date_of_birth")?.value || "");
    const base = selected || new Date();
    dobCursor = new Date(base.getFullYear(), base.getMonth(), 1);
    dobPopup.classList.remove("d-none");
    dobYearPopup?.classList.add("d-none");
    renderDobCalendar();
};
const closeDobPicker = () => dobPopup?.classList.add("d-none");
const toggleDobYearPopup = () => {
    if (!dobYearPopup) return;
    dobYearPopup.classList.toggle("d-none");
    if (!dobYearPopup.classList.contains("d-none")) renderDobYears();
};
const setDobValue = (value) => {
    const dobInput = field("date_of_birth");
    if (!dobInput) return;
    const date = parseDob(value);
    const iso = date ? formatDobIso(date) : "";
    dobInput.value = iso;
    if (field("date_of_birth_direct")) field("date_of_birth_direct").value = formatDobDirect(iso);
    syncDobDisplay();
    renderDobCalendar();
    refreshPremiumFieldStates();
};

const locationAssetUrl = (path = "") => {
    if (!path) return "";
    if (/^https?:\/\//i.test(path) || path.startsWith("/")) return path;
    return `/${path}`;
};

const autoFamilyNumber = () => {
    const existingFamily = field("existing_family_number")?.value || "";
    const studentId = (field("student_id")?.value || "").trim();
    const familyNumber = field("family_number");
    if (!familyNumber) return;
    familyNumber.value = existingFamily || (studentId ? `F${studentId}` : "");
};

const populateSelectedFamily = () => {
    const selectedFamily = field("existing_family_number")?.value || "";
    const family = familyDetails[selectedFamily] || { members: [] };
    ["mother", "father", "guardian"].forEach((type) => {
        const member = family.members.find((item) => item.relationship_type === type) || {};
        const nameEn = document.querySelector(`[name="${type}_name_en"]`);
        const nameKh = document.querySelector(`[name="${type}_name_kh"]`);
        const workplace = document.querySelector(`[name="${type}_workplace"]`);
        if (nameEn) nameEn.value = member.name_en || [member.first_name_en, member.last_name_en].filter(Boolean).join(" ");
        if (nameKh) nameKh.value = member.name_kh || [member.first_name_kh, member.last_name_kh].filter(Boolean).join(" ");
        if (workplace) workplace.value = member.workplace || "";

        const occupation = field(`${type}_occupation_id`);
        if (occupation) { occupation.value = member.occupation_id || ""; occupation.dispatchEvent(new Event("change", { bubbles: true })); }
        const nationality = field(`${type}_nationality_country_id`);
        if (nationality) { nationality.value = member.nationality_country_id || ""; nationality.dispatchEvent(new Event("change", { bubbles: true })); }

        const phone = familyPhoneInputs.find((item) => item.visible === field(`${type}_phone_number`));
        if (phone?.intl && member.phone) phone.intl.setNumber(member.phone);
        else if (phone?.visible) phone.visible.value = member.phone || "";
        if (phone?.hidden) phone.hidden.value = member.phone || "";
    });
};

const alertError = (message) => {
    const alert = form.querySelector("[data-alert]");
    alert.textContent = message || "Please correct the errors below.";
    alert.classList.remove("d-none");
};

const setOptions = (id, list, label, empty) => {
    const select = field(id);
    if (!select) return;
    select.innerHTML = `<option value="">${empty}</option>` + list.map((item) => `<option value="${item.id}">${escapeHtml(item[label] ?? "")}</option>`).join("");
};
const searchableEnrollmentSelects = [
    ["academic_year_id", "Academic Year"],
    ["existing_family_number", "Family"],
    ["grade_id", "Grade"],
    ["class_id", "Class"],
    ["session_id", "Group"],
    ["enrollment_status", "Enrollment Status"],
    ["enrollment-document-type", "Document Type"],
];
const setupSearchableEnrollmentSelect = (id, label) => {
    const select = field(id);
    if (!select || field(`${id}-combobox`)) return;
    select.classList.add("d-none");
    select.insertAdjacentHTML("afterend", `<div id="${id}-combobox" class="location-combobox enrollment-search-combobox"><button type="button" id="${id}-toggle" class="location-combobox-toggle"><span id="${id}-selected" class="location-combobox-selected"></span><i class="ti ti-chevron-down"></i></button><div id="${id}-menu" class="location-combobox-menu d-none enrollment-search-menu"><input id="${id}-search" type="search" class="form-control location-combobox-search" placeholder="Search ${label}"><div id="${id}-results" class="location-combobox-results"></div></div></div>`);
    const toggle = field(`${id}-toggle`);
    const menu = field(`${id}-menu`);
    const searchInput = field(`${id}-search`);
    const results = field(`${id}-results`);
    const selected = field(`${id}-selected`);
    const sync = () => { selected.textContent = select.value ? (select.selectedOptions?.[0]?.textContent || "") : emptySelectedLabel; };
    const render = () => { const term = (searchInput.value || "").toLowerCase(); const options = Array.from(select.options).slice(1).filter((option) => !term || option.textContent.toLowerCase().includes(term)); results.innerHTML = options.length ? options.map((option) => `<button type="button" class="location-combobox-option" data-searchable-select-id="${id}" data-searchable-select-value="${option.value}">${escapeHtml(option.textContent)}</button>`).join("") : `<div class="text-secondary px-2 py-2">No options found</div>`; };
    toggle.addEventListener("click", () => { document.querySelectorAll(".enrollment-search-menu").forEach((other) => { if (other !== menu) other.classList.add("d-none"); }); menu.classList.toggle("d-none"); if (!menu.classList.contains("d-none")) { searchInput.value = ""; render(); searchInput.focus(); } });
    searchInput.addEventListener("input", render);
    results.addEventListener("click", (event) => { const option = event.target.closest("[data-searchable-select-value]"); if (!option) return; select.value = option.dataset.searchableSelectValue; select.dispatchEvent(new Event("change", { bubbles: true })); sync(); menu.classList.add("d-none"); });
    select.addEventListener("change", sync);
};
searchableEnrollmentSelects.forEach(([id, label]) => setupSearchableEnrollmentSelect(id, label));
document.addEventListener("click", (event) => { if (!event.target.closest(".enrollment-search-combobox")) document.querySelectorAll(".enrollment-search-menu").forEach((menu) => menu.classList.add("d-none")); });
const refreshSearchableEnrollmentLabels = () => searchableEnrollmentSelects.forEach(([id]) => field(id)?.dispatchEvent(new Event("change")));
const familyNationalityUi = (type) => ({
    select: field(`${type}_nationality_country_id`),
    toggle: field(`${type}-nationality-toggle`),
    menu: field(`${type}-nationality-menu`),
    search: field(`${type}-nationality-search`),
    results: field(`${type}-nationality-results`),
    selected: field(`${type}-nationality-selected`),
});
const familySelectedLabel = (kh = "", en = "") => [kh, en].filter(Boolean).join(" / ");
const familyOccupationUi = (type) => ({
    select: field(`${type}_occupation_id`),
    toggle: field(`${type}-occupation-toggle`),
    menu: field(`${type}-occupation-menu`),
    search: field(`${type}-occupation-search`),
    results: field(`${type}-occupation-results`),
    selected: field(`${type}-occupation-selected`),
});
const setupFamilyNativeSearch = (select, placeholder) => {
    const column = select?.closest(".col-md-3");
    if (!select || !column) return;
    let input = column.querySelector(".family-native-search");
    if (!input) {
        input = document.createElement("input");
        input.type = "search";
        input.className = "form-control family-native-search mb-1";
        input.placeholder = placeholder;
        input.autocomplete = "off";
        select.parentNode.insertBefore(input, select);
        input.addEventListener("input", () => {
            const term = input.value.trim().toLowerCase();
            const options = (select._familyAllOptions || Array.from(select.options).map((option) => ({
                value: option.value,
                text: option.textContent,
                kh: option.dataset.kh || "",
                flag: option.dataset.flag || "",
            })));
            const filtered = options.filter((option, index) => index === 0 || !term || `${option.text} ${option.kh}`.toLowerCase().includes(term));
            const selectedValue = select.value;
            select.innerHTML = filtered.map((option) => `<option value="${escapeHtml(option.value)}" data-kh="${escapeHtml(option.kh)}" data-flag="${escapeHtml(option.flag)}">${escapeHtml(option.text)}</option>`).join("");
            if (filtered.some((option) => option.value === selectedValue)) select.value = selectedValue;
        });
    }
    input.value = "";
    Array.from(select.options).forEach((option) => { option.hidden = false; });
};
const openFamilyMenu = (ui, render) => {
    if (!ui.menu || !ui.toggle) return;
    document.querySelectorAll(".family-information .location-combobox-menu").forEach((menu) => {
        if (menu !== ui.menu) menu.classList.add("d-none");
    });
    document.querySelectorAll(".family-information .location-combobox").forEach((combo) => {
        combo.classList.remove("is-open");
        combo.closest(".col-md-3")?.classList.remove("family-field-open");
    });
    ui.toggle.closest(".location-combobox")?.classList.add("is-open");
    ui.toggle.closest(".col-md-3")?.classList.add("family-field-open");
    ui.menu.style.position = "";
    ui.menu.style.top = "";
    ui.menu.style.left = "";
    ui.menu.style.width = "";
    ui.menu.style.zIndex = "";
    ui.menu.classList.remove("d-none");
    render();
    ui.search?.focus();
};
const closeFamilyMenu = (ui) => {
    ui.menu?.classList.add("d-none");
    ui.toggle?.closest(".location-combobox")?.classList.remove("is-open");
    ui.toggle?.closest(".col-md-3")?.classList.remove("family-field-open");
};
const setupFamilyOccupation = (type, occupations) => {
    const ui = familyOccupationUi(type);
    if (!ui.select) return;
    ui.select.classList.add("d-none");
    ui.toggle?.closest(".location-combobox")?.classList.remove("d-none");
    ui.select.innerHTML = `<option value="">Occupation (English)</option>` + occupations.map((item) => `<option value="${item.id}" data-kh="${escapeHtml(item.occupation_name_kh || "")}">${escapeHtml(item.occupation_name_en || "")}</option>`).join("");
    if (ui.select.dataset.familyOccupationInitialized === "true") {
        ui.select.dispatchEvent(new Event("change", { bubbles: true }));
        return;
    }
    ui.select.dataset.familyOccupationInitialized = "true";
    const render = () => {
        const term = (ui.search?.value || "").trim().toLowerCase();
        const options = Array.from(ui.select.options).slice(1).filter((option) => !term || option.textContent.toLowerCase().includes(term) || (option.dataset.kh || "").toLowerCase().includes(term));
    ui.results.innerHTML = options.length ? options.map((option) => `<button type="button" class="location-combobox-option" data-family-occupation-id="${option.value}"><span><span class="location-combobox-khmer">${escapeHtml(option.dataset.kh || "")}</span><span class="location-combobox-english">${escapeHtml(option.textContent)}</span></span></button>`).join("") : `<div class="text-secondary px-2 py-2">No occupations found</div>`;
        ui.results.querySelectorAll("[data-family-occupation-id]").forEach((button) => { button.onmousedown = (event) => { event.preventDefault(); event.stopPropagation(); ui.select.value = button.dataset.familyOccupationId; ui.select.dispatchEvent(new Event("change", { bubbles: true })); closeFamilyMenu(ui); }; });
    };
    ui.toggle?.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();
        if (ui.menu?.classList.contains("d-none")) openFamilyMenu(ui, render);
        else closeFamilyMenu(ui);
    });
    ui.search?.addEventListener("input", render);
    ui.select.addEventListener("change", () => { const option = ui.select.selectedOptions[0]; if (ui.selected) ui.selected.innerHTML = option?.value ? `<span class="location-combobox-selected-text">${escapeHtml(familySelectedLabel(option.dataset.kh || "", option.textContent || ""))}</span>` : emptySelectedLabel; });
};
const setFamilyNationalitySelected = (type) => {
    const ui = familyNationalityUi(type);
    const option = ui.select?.selectedOptions?.[0];
    if (!option || !option.value) { if (ui.selected) ui.selected.textContent = emptySelectedLabel; return; }
    const flag = option.dataset.flag ? `<img src="${escapeHtml(locationAssetUrl(option.dataset.flag))}" alt="" class="location-flag me-2">` : "";
    ui.selected.innerHTML = `${flag}<span class="location-combobox-selected-text">${escapeHtml(familySelectedLabel(option.dataset.kh || "", option.textContent || ""))}</span>`;
};
const renderFamilyNationalityResults = (type) => {
    const ui = familyNationalityUi(type);
    if (!ui.select || !ui.results) return;
    const term = (ui.search?.value || "").trim().toLowerCase();
    const options = Array.from(ui.select.options).slice(1).filter((option) => !term || option.textContent.toLowerCase().includes(term) || (option.dataset.kh || "").toLowerCase().includes(term));
    ui.results.innerHTML = options.length ? options.map((option) => `<button type="button" class="location-combobox-option" data-family-nationality-id="${option.value}"><span class="d-flex align-items-center min-w-0">${option.dataset.flag ? `<img src="${escapeHtml(locationAssetUrl(option.dataset.flag))}" alt="" class="location-flag me-2">` : ""}<span><span class="location-combobox-khmer">${escapeHtml(option.dataset.kh || "")}</span><span class="location-combobox-english d-block">${escapeHtml(option.textContent)}</span></span></span></button>`).join("") : `<div class="text-secondary px-2 py-2">No countries found</div>`;
    ui.results.querySelectorAll("[data-family-nationality-id]").forEach((button) => { button.onmousedown = (event) => { event.preventDefault(); event.stopPropagation(); ui.select.value = button.dataset.familyNationalityId; ui.select.dispatchEvent(new Event("change", { bubbles: true })); closeFamilyMenu(ui); }; });
};
const setupFamilyNationality = (type, countries) => {
    const ui = familyNationalityUi(type);
    if (!ui.select) return;
    ui.select.classList.add("d-none");
    ui.toggle?.closest(".location-combobox")?.classList.remove("d-none");
    ui.select.closest(".col-md-3")?.querySelector(".family-native-search")?.remove();
    ui.select.innerHTML = `<option value="">Nationality (English)</option>` + countries.map((item) => `<option value="${item.id}" data-kh="${escapeHtml(item.nationality_name_kh || "")}" data-flag="${escapeHtml(item.flag_path || "")}">${escapeHtml(item.nationality_name_en || item.country_name_en || "")}</option>`).join("");
    if (ui.select.dataset.familyNationalityInitialized === "true") {
        ui.select.dispatchEvent(new Event("change", { bubbles: true }));
        return;
    }
    ui.select.dataset.familyNationalityInitialized = "true";
    ui.toggle?.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();
        if (ui.menu?.classList.contains("d-none")) openFamilyMenu(ui, () => renderFamilyNationalityResults(type));
        else closeFamilyMenu(ui);
    });
    ui.search?.addEventListener("input", () => renderFamilyNationalityResults(type));
    ui.select.addEventListener("change", () => {
        const option = ui.select.selectedOptions[0];
        setFamilyNationalitySelected(type);
    });
};
const setFamilyReferenceOptions = (options) => {
    ["mother", "father", "guardian"].forEach((type) => {
        const occupation = field(`${type}_occupation_id`);
        const nationality = field(`${type}_nationality_country_id`);
        setupFamilyOccupation(type, options.occupations || []);
        setupFamilyNationality(type, options.countries || []);
    });
};

const studentBilingualUi = (type) => ({ select: field(type === "nationality" ? "nationality_country_id" : "gender"), toggle: field(`student-${type}-toggle`), menu: field(`student-${type}-menu`), search: field(`student-${type}-search`), results: field(`student-${type}-results`), selected: field(`student-${type}-selected`) });
const setStudentBilingualText = (type) => { const ui = studentBilingualUi(type); const option = ui.select?.selectedOptions?.[0]; if (!option || !option.value) { if (ui.selected) ui.selected.textContent = emptySelectedLabel; if (type === "gender") field("gender_kh").value = ""; refreshPremiumFieldStates(); return; } const flag = type === "nationality" && option.dataset.flag ? `<img src="${escapeHtml(locationAssetUrl(option.dataset.flag))}" class="location-flag me-2" alt="">` : ""; const kh = option.dataset.kh || ""; const en = option.dataset.en || option.textContent || ""; if (type === "gender") field("gender_kh").value = kh; ui.selected.innerHTML = `${flag}<span class="location-combobox-selected-text">${escapeHtml(bilingualSelectedLabel(kh, en))}</span>`; refreshPremiumFieldStates(); };
const renderStudentBilingualResults = (type) => { const ui = studentBilingualUi(type); if (!ui.select || !ui.results) return; const term = (ui.search?.value || "").toLowerCase(); const options = Array.from(ui.select.options).slice(1).filter((option) => !term || option.textContent.toLowerCase().includes(term) || (option.dataset.kh || "").toLowerCase().includes(term)); ui.results.innerHTML = options.map((option) => `<button type="button" class="location-combobox-option" data-student-bilingual-type="${type}" data-student-bilingual-id="${option.value}"><span class="d-flex align-items-center min-w-0">${type === "nationality" && option.dataset.flag ? `<img src="${escapeHtml(locationAssetUrl(option.dataset.flag))}" class="location-flag me-2" alt="">` : ""}<span><span class="location-combobox-english">${escapeHtml(option.dataset.en || option.textContent)}</span><small class="location-combobox-khmer">${escapeHtml(option.dataset.kh || "")}</small></span></span></button>`).join("") || `<div class="text-secondary px-2 py-2">No options found</div>`; };
const setupStudentBilingual = (type) => { const ui = studentBilingualUi(type); if (!ui.select || !ui.toggle) return; ui.select.classList.add("d-none"); ui.toggle.addEventListener("click", () => { ui.menu.classList.toggle("d-none"); if (!ui.menu.classList.contains("d-none")) { if (ui.search) ui.search.value = ""; renderStudentBilingualResults(type); ui.search?.focus(); } }); ui.search?.addEventListener("input", () => renderStudentBilingualResults(type)); ui.results?.addEventListener("click", (event) => { const button = event.target.closest("[data-student-bilingual-id]"); if (!button) return; ui.select.value = button.dataset.studentBilingualId; ui.select.dispatchEvent(new Event("change", { bubbles: true })); ui.menu.classList.add("d-none"); }); ui.select.addEventListener("change", () => setStudentBilingualText(type)); };
setupStudentBilingual("gender");
setupStudentBilingual("nationality");
[
    "first_name_en", "mother_name_en", "father_name_en", "guardian_name_en",
].forEach((id) => (field(id) || document.querySelector(`[name="${id}"]`))?.addEventListener("input", (event) => { event.target.value = event.target.value.toUpperCase(); }));

const arrangeEnrollmentStudentInformation = () => {
    const information = field("studentInformationFields");
    const contact = field("contactInformationFields");
    if (!information || !contact) return;
    [
        "student_no", "student_id", "existing_family_number", "family_number",
        "first_name_en", "first_name_kh", "gender", "nationality_country_id",
        "date_of_birth", "date_of_birth_kh", "home_phone_number", "email",
        "remarks",
    ].forEach((id) => {
        const element = field(id);
        const column = element?.closest(".col-md-4");
        if (!column) return;
        column.classList.remove("col-md-4");
        column.classList.add(id === "remarks" ? "col-md-12" : "col-md-3");
        if (id === "remarks") column.style.gridColumn = "1 / -1";
        information.appendChild(column);
    });
    field("contactInformationHeading")?.remove();
    contact.remove();
    document.querySelectorAll("#enrollmentInformationFields > .col-md-4").forEach((column) => { column.classList.remove("col-md-4"); column.classList.add("col-md-2"); });
};
arrangeEnrollmentStudentInformation();
refreshPremiumFieldStates();

const showStudentPhotoPreview = (fileOrUrl) => {
    if (!studentPhotoPreview || !studentPhotoPreviewContainer || !fileOrUrl) return;
    if (fileOrUrl instanceof File) {
        if (!fileOrUrl.type.startsWith("image/")) return;
        studentPhotoPreview.src = URL.createObjectURL(fileOrUrl);
    } else {
        studentPhotoPreview.src = fileOrUrl;
    }
    studentPhotoPreviewContainer.classList.remove("d-none");
};

const drawStudentPhotoCrop = () => {
    if (!studentPhotoCropContext || !studentPhotoCropCanvas || !studentPhotoCropImage) return;
    const canvas = studentPhotoCropCanvas;
    const context = studentPhotoCropContext;
    context.clearRect(0, 0, canvas.width, canvas.height);
    context.fillStyle = "#fff";
    context.fillRect(0, 0, canvas.width, canvas.height);
    const image = studentPhotoCropImage;
    const baseScale = Math.max(canvas.width / image.width, canvas.height / image.height);
    const scale = baseScale * studentPhotoCropScale;
    context.save();
    context.translate(canvas.width / 2 + studentPhotoCropOffsetX, canvas.height / 2 + studentPhotoCropOffsetY);
    context.rotate(studentPhotoCropRotation * Math.PI / 180);
    context.drawImage(image, -(image.width * scale) / 2, -(image.height * scale) / 2, image.width * scale, image.height * scale);
    context.restore();
};
const resetStudentPhotoCrop = () => {
    studentPhotoCropScale = 1;
    studentPhotoCropRotation = 0;
    studentPhotoCropOffsetX = 0;
    studentPhotoCropOffsetY = 0;
    if (studentPhotoZoom) studentPhotoZoom.value = "1";
    drawStudentPhotoCrop();
};
const openStudentPhotoCrop = (file) => {
    if (!file || !file.type.startsWith("image/")) return;
    const reader = new FileReader();
    reader.onload = () => {
        const image = new Image();
        image.onload = () => {
            studentPhotoCropImage = image;
            resetStudentPhotoCrop();
            studentPhotoCropModal?.show();
        };
        image.src = reader.result;
    };
    reader.readAsDataURL(file);
};
const setStudentPhotoFile = (blob) => {
    const file = new File([blob], "student-photo.jpg", { type: "image/jpeg" });
    const transfer = new DataTransfer();
    transfer.items.add(file);
    if (studentPhotoInput) studentPhotoInput.files = transfer.files;
    showStudentPhotoPreview(file);
};

const resetStudentPhotoPreview = () => {
    if (studentPhotoInput) studentPhotoInput.value = "";
    studentPhotoPreviewContainer?.classList.add("d-none");
};

studentPhotoInput?.addEventListener("change", () => {
    const file = studentPhotoInput.files?.[0];
    if (file) {
        studentPhotoInput.value = "";
        openStudentPhotoCrop(file);
    }
});

studentPhotoDropzone?.addEventListener("click", () => studentPhotoInput?.click());
studentPhotoDropzone?.addEventListener("keydown", (event) => {
    if (event.key === "Enter" || event.key === " ") studentPhotoInput?.click();
});
studentPhotoDropzone?.addEventListener("dragover", (event) => {
    event.preventDefault();
    studentPhotoDropzone.classList.add("is-dragging");
});
studentPhotoDropzone?.addEventListener("dragleave", () => studentPhotoDropzone.classList.remove("is-dragging"));
studentPhotoDropzone?.addEventListener("drop", (event) => {
    event.preventDefault();
    studentPhotoDropzone.classList.remove("is-dragging");
    const file = event.dataTransfer.files?.[0];
    if (!file) return;
    openStudentPhotoCrop(file);
});

studentPhotoZoom?.addEventListener("input", () => { studentPhotoCropScale = Number(studentPhotoZoom.value); drawStudentPhotoCrop(); });
document.getElementById("studentPhotoZoomIn")?.addEventListener("click", () => { studentPhotoZoom.value = Math.min(3, Number(studentPhotoZoom.value) + .1).toFixed(2); studentPhotoZoom.dispatchEvent(new Event("input")); });
document.getElementById("studentPhotoZoomOut")?.addEventListener("click", () => { studentPhotoZoom.value = Math.max(1, Number(studentPhotoZoom.value) - .1).toFixed(2); studentPhotoZoom.dispatchEvent(new Event("input")); });
document.getElementById("studentPhotoRotateLeft")?.addEventListener("click", () => { studentPhotoCropRotation -= 90; drawStudentPhotoCrop(); });
document.getElementById("studentPhotoRotateRight")?.addEventListener("click", () => { studentPhotoCropRotation += 90; drawStudentPhotoCrop(); });
document.getElementById("studentPhotoReset")?.addEventListener("click", resetStudentPhotoCrop);
studentPhotoCropCanvas?.addEventListener("pointerdown", (event) => { studentPhotoCropDragging = true; studentPhotoCropStart = { x: event.clientX, y: event.clientY }; studentPhotoCropCanvas.setPointerCapture(event.pointerId); });
studentPhotoCropCanvas?.addEventListener("pointermove", (event) => { if (!studentPhotoCropDragging || !studentPhotoCropStart) return; const scaleX = studentPhotoCropCanvas.width / studentPhotoCropCanvas.getBoundingClientRect().width; const scaleY = studentPhotoCropCanvas.height / studentPhotoCropCanvas.getBoundingClientRect().height; studentPhotoCropOffsetX += (event.clientX - studentPhotoCropStart.x) * scaleX; studentPhotoCropOffsetY += (event.clientY - studentPhotoCropStart.y) * scaleY; studentPhotoCropStart = { x: event.clientX, y: event.clientY }; drawStudentPhotoCrop(); });
studentPhotoCropCanvas?.addEventListener("pointerup", () => { studentPhotoCropDragging = false; studentPhotoCropStart = null; });
studentPhotoCropCanvas?.addEventListener("pointercancel", () => { studentPhotoCropDragging = false; studentPhotoCropStart = null; });
document.getElementById("studentPhotoCropUpload")?.addEventListener("click", () => {
    if (!studentPhotoCropCanvas) return;
    studentPhotoCropCanvas.toBlob((blob) => { if (blob) { setStudentPhotoFile(blob); studentPhotoCropModal?.hide(); } }, "image/jpeg", .9);
});

dobTrigger?.addEventListener("click", () => {
    if (dobPopup?.classList.contains("d-none")) {
        openDobPicker();
    } else {
        closeDobPicker();
    }
});

dobPrev?.addEventListener("click", () => {
    dobCursor = new Date(dobCursor.getFullYear(), dobCursor.getMonth() - 1, 1);
    dobYearPopup?.classList.add("d-none");
    renderDobCalendar();
});

dobNext?.addEventListener("click", () => {
    dobCursor = new Date(dobCursor.getFullYear(), dobCursor.getMonth() + 1, 1);
    dobYearPopup?.classList.add("d-none");
    renderDobCalendar();
});

dobYearToggle?.addEventListener("click", (event) => {
    event.stopPropagation();
    toggleDobYearPopup();
});

dobYears?.addEventListener("click", (event) => {
    event.preventDefault();
    event.stopPropagation();
    const button = event.target.closest("[data-dob-year]");
    if (!button) return;
    dobCursor = new Date(Number(button.dataset.dobYear), dobCursor.getMonth(), 1);
    dobYearPopup?.classList.add("d-none");
    dobPopup?.classList.remove("d-none");
    renderDobCalendar();
});

dobDays?.addEventListener("click", (event) => {
    const button = event.target.closest("[data-dob-date]");
    if (!button) return;
    setDobValue(button.dataset.dobDate);
    closeDobPicker();
});

const enrollmentDatePicker = field("enrolled_on_picker");
const enrollmentDatePopup = field("enrolled_on_popup");
const enrollmentDateDays = field("enrolled_on_days");
const enrollmentDateLabel = field("enrolled_on_month_label");
const enrollmentDateYearToggle = field("enrolled_on_year_toggle");
const enrollmentDateYearPopup = field("enrolled_on_year_popup");
const enrollmentDateYears = field("enrolled_on_years");
let enrollmentDateCursor = new Date();
const setEnrollmentDate = (value) => { const date = parseDob(value); const iso = date ? formatDobIso(date) : ""; field("enrolled_on").value = iso; field("enrolled_on_direct").value = formatDobDirect(iso); refreshPremiumFieldStates(); };
const renderEnrollmentYears = () => { if (!enrollmentDateYears) return; const current = enrollmentDateCursor.getFullYear(); const start = 1900; const end = new Date().getFullYear() + 5; enrollmentDateYears.innerHTML = Array.from({ length: end - start + 1 }, (_, index) => { const year = start + index; return `<button type="button" class="date-picker-year${year === current ? " is-selected" : ""}" data-enrollment-year="${year}">${year}</button>`; }).join(""); };
const renderEnrollmentCalendar = () => { if (!enrollmentDateDays) return; const year = enrollmentDateCursor.getFullYear(); const month = enrollmentDateCursor.getMonth(); enrollmentDateLabel.textContent = enrollmentDateCursor.toLocaleDateString("en-US", { month: "long", year: "numeric" }); renderEnrollmentYears(); const first = new Date(year, month, 1); const start = first.getDay(); const days = new Date(year, month + 1, 0).getDate(); const selected = parseDob(field("enrolled_on").value); const cells = []; for (let i = 0; i < start; i += 1) cells.push(new Date(year, month, i - start + 1)); for (let day = 1; day <= days; day += 1) cells.push(new Date(year, month, day)); while (cells.length < 42) cells.push(new Date(year, month + 1, cells.length - start - days + 1)); enrollmentDateDays.innerHTML = cells.map((date) => { const iso = formatDobIso(date); const outside = date.getMonth() !== month ? " is-outside" : ""; const active = selected && iso === formatDobIso(selected) ? " is-selected" : ""; return `<button type="button" class="date-picker-day${outside}${active}" data-enrollment-date="${iso}">${date.getDate()}</button>`; }).join(""); };
field("enrolled_on_trigger")?.addEventListener("click", () => { const selected = parseDob(field("enrolled_on").value); enrollmentDateCursor = selected ? new Date(selected.getFullYear(), selected.getMonth(), 1) : new Date(new Date().getFullYear(), new Date().getMonth(), 1); enrollmentDatePopup.classList.toggle("d-none"); enrollmentDateYearPopup?.classList.add("d-none"); if (!enrollmentDatePopup.classList.contains("d-none")) renderEnrollmentCalendar(); });
enrollmentDateYearToggle?.addEventListener("click", (event) => { event.stopPropagation(); enrollmentDateYearPopup.classList.toggle("d-none"); if (!enrollmentDateYearPopup.classList.contains("d-none")) renderEnrollmentYears(); });
enrollmentDateYears?.addEventListener("click", (event) => { event.preventDefault(); event.stopPropagation(); const button = event.target.closest("[data-enrollment-year]"); if (!button) return; const selectedYear = Number(button.dataset.enrollmentYear); enrollmentDateCursor = new Date(selectedYear, enrollmentDateCursor.getMonth(), 1); enrollmentDateYearPopup.classList.add("d-none"); enrollmentDatePopup.classList.remove("d-none"); renderEnrollmentCalendar(); });
field("enrolled_on_prev")?.addEventListener("click", () => { enrollmentDateCursor = new Date(enrollmentDateCursor.getFullYear(), enrollmentDateCursor.getMonth() - 1, 1); enrollmentDateYearPopup?.classList.add("d-none"); renderEnrollmentCalendar(); });
field("enrolled_on_next")?.addEventListener("click", () => { enrollmentDateCursor = new Date(enrollmentDateCursor.getFullYear(), enrollmentDateCursor.getMonth() + 1, 1); enrollmentDateYearPopup?.classList.add("d-none"); renderEnrollmentCalendar(); });
enrollmentDateDays?.addEventListener("click", (event) => { const button = event.target.closest("[data-enrollment-date]"); if (!button) return; setEnrollmentDate(button.dataset.enrollmentDate); enrollmentDatePopup.classList.add("d-none"); enrollmentDateYearPopup?.classList.add("d-none"); });
field("enrolled_on_direct")?.addEventListener("change", (event) => setEnrollmentDate(event.target.value));
document.addEventListener("click", (event) => { if (enrollmentDatePicker && !enrollmentDatePicker.contains(event.target)) enrollmentDatePopup?.classList.add("d-none"); });

const campusSelect = field("campus_id");
const campusToggle = field("campus-toggle");
const campusMenu = field("campus-menu");
const campusSearch = field("campus-search");
const campusResults = field("campus-results");
const campusSelected = field("campus-selected");

const setCampusSelectedText = () => {
    if (!campusSelected || !campusSelect) return;
    const selected = campusItems.find((item) => String(item.id) === String(campusSelect.value));
    campusSelected.textContent = selected ? selected.label : emptySelectedLabel;
    refreshPremiumFieldStates();
};

const renderCampusResults = () => {
    if (!campusResults || !campusSelect) return;
    const term = (campusSearch?.value || "").trim().toLowerCase();
    const items = term ? campusItems.filter((item) => item.label.toLowerCase().includes(term)) : campusItems;
    campusResults.innerHTML = "";

    if (!items.length) {
        campusResults.innerHTML = `<div class="text-secondary px-2 py-2">No options found</div>`;
        return;
    }

    campusResults.innerHTML = items.map((item) => `
        <button type="button" class="location-combobox-option${String(campusSelect.value) === String(item.id) ? " is-selected" : ""}" data-campus-id="${item.id}" style="display:flex;width:100%;align-items:center;justify-content:space-between;gap:.75rem;padding:.5rem .55rem;border:0;border-radius:.25rem;color:var(--tblr-body-color);background:transparent;text-align:left;">
            <span class="location-combobox-selected">${escapeHtml(item.label)}</span>
            ${String(campusSelect.value) === String(item.id) ? '<i class="ti ti-check"></i>' : ""}
        </button>
    `).join("");
};

const closeCampusMenu = () => campusMenu?.classList.add("d-none");
const openCampusMenu = () => {
    if (!campusMenu || !campusSearch) return;
    campusMenu.classList.remove("d-none");
    campusSearch.value = "";
    renderCampusResults();
    campusSearch.focus();
};

campusToggle?.addEventListener("click", () => {
    campusMenu?.classList.contains("d-none") ? openCampusMenu() : closeCampusMenu();
});

campusSearch?.addEventListener("input", renderCampusResults);

campusResults?.addEventListener("click", (event) => {
    const option = event.target.closest("[data-campus-id]");
    if (!option || !campusSelect) return;
    campusSelect.value = option.dataset.campusId;
    campusSelect.dispatchEvent(new Event("change", { bubbles: true }));
    setCampusSelectedText();
    closeCampusMenu();
});

document.addEventListener("click", (event) => {
    if (!field("campus-combobox")?.contains(event.target)) closeCampusMenu();
});

document.addEventListener("click", (event) => {
    if (dobPicker && !dobPicker.contains(event.target)) closeDobPicker();
});

document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") closeDobPicker();
});

const birthUi = (type) => ({
    select: field(`birth_${type}_id`),
    toggle: field(`birth-${type}-toggle`),
    menu: field(`birth-${type}-menu`),
    search: field(`birth-${type}-search`),
    results: field(`birth-${type}-results`),
    selected: field(`birth-${type}-selected`),
});

const setBirthSelectedText = (type) => {
    const ui = birthUi(type);
    if (!ui.select || !ui.selected) return;
    const selected = ui.select.selectedOptions?.[0];
    if (!selected || !selected.value) {
        ui.selected.innerHTML = emptySelectedLabel;
        return;
    }
    const kh = selected.dataset.kh || "";
    const en = selected.textContent.replace(/^\s*.*?-\s*/, "").trim();
    const flag = selected.dataset.flag || "";
    const flagMarkup = type === "country" && flag
        ? `<img src="${escapeHtml(locationAssetUrl(flag))}" alt="" class="location-flag me-2">`
        : "";
    ui.selected.innerHTML = kh
        ? `${flagMarkup}<span class="d-block min-w-0"><span class="location-combobox-english">${escapeHtml(en)}</span><small class="location-combobox-khmer">${escapeHtml(kh)}</small></span>`
        : `${flagMarkup}<span class="location-combobox-english">${escapeHtml(en)}</span>`;
};

const renderBirthResults = (type) => {
    const ui = birthUi(type);
    if (!ui.select || !ui.results) return;
    const term = (ui.search?.value || "").trim().toLowerCase();
    const options = Array.from(ui.select.options).slice(1);
    const filtered = term
        ? options.filter((option) => option.textContent.toLowerCase().includes(term) || (option.dataset.kh || "").toLowerCase().includes(term))
        : options;

    ui.results.innerHTML = "";
    if (!filtered.length) {
        ui.results.innerHTML = `<div class="text-secondary px-2 py-2">No options found</div>`;
        return;
    }

    ui.results.innerHTML = filtered.map((option) => `
        <button type="button" class="location-combobox-option${String(ui.select.value) === String(option.value) ? " is-selected" : ""}" data-birth-type="${type}" data-birth-id="${option.value}">
            <span class="d-flex align-items-center min-w-0">
                ${type === "country" && option.dataset.flag ? `<img src="${escapeHtml(locationAssetUrl(option.dataset.flag))}" alt="" class="location-flag me-2">` : ""}
                <span class="min-w-0">
                    <span class="location-combobox-english">${escapeHtml(option.textContent.replace(/^\s*.*?-\s*/, "").trim())}</span>
                    <small class="text-secondary d-block">${escapeHtml(option.dataset.kh || "")}</small>
                </span>
            </span>
            ${String(ui.select.value) === String(option.value) ? '<i class="ti ti-check"></i>' : ""}
        </button>
    `).join("");
};

const openBirthMenu = (type) => {
    const ui = birthUi(type);
    if (!ui.menu || !ui.search) return;
    ui.menu.classList.remove("d-none");
    ui.search.value = "";
    renderBirthResults(type);
    ui.search.focus();
};

const closeBirthMenu = (type) => {
    birthUi(type).menu?.classList.add("d-none");
};

const setBirthSelect = (type, items) => {
    const config = birthConfig[type];
    const select = field(`birth_${type}_id`);
    if (!select) return;
    select.innerHTML = `<option value="">Select ${config.label}</option>` + items.map((item) => {
        const khName = item[config.kh] ?? "";
        const enName = item[config.en] ?? "";
        const flag = type === "country" ? ` data-flag="${escapeHtml(item.flag_path || "")}"` : "";
        return `<option value="${item.id}" data-kh="${escapeHtml(khName)}"${flag}>${khName ? `${escapeHtml(khName)} - ` : ""}${escapeHtml(enName)}</option>`;
    }).join("");
    setBirthSelectedText(type);
};

const refreshBirthSelects = () => {
    setBirthSelect("country", birthLocations.countries || []);
    setBirthSelect("province", []);
    setBirthSelect("district", []);
    setBirthSelect("commune", []);
    setBirthSelect("village", []);
};

const loadBirthLocations = async () => {
    const response = await fetch("/locations/options");
    birthLocations = await response.json();
    refreshBirthSelects();
};

const addressFields = ["country", "province", "district", "commune", "village"];
const addressConfig = {
    country: { key: "countries", parentField: null, en: "country_name_en", kh: "country_name_kh", label: "Country" },
    province: { key: "provinces", parentField: "country_id", en: "province_name_en", kh: "province_name_kh", label: "Province / City" },
    district: { key: "districts", parentField: "province_id", en: "district_name_en", kh: "district_name_kh", label: "District / Khan" },
    commune: { key: "communes", parentField: "district_id", en: "commune_name_en", kh: "commune_name_kh", label: "Commune" },
    village: { key: "villages", parentField: "commune_id", en: "village_name_en", kh: "village_name_kh", label: "Village" },
};
const addressUi = (type) => ({ select: field(`address_${type}_id`), toggle: field(`address-${type}-toggle`), menu: field(`address-${type}-menu`), search: field(`address-${type}-search`), results: field(`address-${type}-results`), selected: field(`address-${type}-selected`) });
const setupAddressComboboxes = () => addressFields.forEach((type) => {
    const ui = addressUi(type);
    if (!ui.select || field(`address-${type}-combobox`)) return;
    ui.select.classList.add("d-none");
    ui.select.insertAdjacentHTML("afterend", `<div id="address-${type}-combobox" class="location-combobox"><button type="button" id="address-${type}-toggle" class="location-combobox-toggle"><span id="address-${type}-selected" class="location-combobox-selected"></span><i class="ti ti-chevron-down"></i></button><div id="address-${type}-menu" class="location-combobox-menu d-none"><input id="address-${type}-search" type="search" class="form-control location-combobox-search" placeholder="Search ${addressConfig[type].label}"><div id="address-${type}-results" class="location-combobox-results"></div></div></div>`);
});
const setAddressSelectedText = (type) => { const ui = addressUi(type); const option = ui.select?.selectedOptions?.[0]; if (!option || !option.value) { if (ui.selected) ui.selected.textContent = emptySelectedLabel; refreshPremiumFieldStates(); return; } const flag = type === "country" && option.dataset.flag ? `<img src="${escapeHtml(locationAssetUrl(option.dataset.flag))}" class="location-flag me-2" alt="">` : ""; const kh = option.dataset.kh || ""; const en = option.dataset.en || option.textContent || ""; ui.selected.innerHTML = `${flag}<span class="location-combobox-selected-text">${escapeHtml(bilingualSelectedLabel(kh, en))}</span>`; refreshPremiumFieldStates(); };
const renderAddressResults = (type) => { const ui = addressUi(type); if (!ui.select || !ui.results) return; const term = (ui.search?.value || "").toLowerCase(); const options = Array.from(ui.select.options).slice(1).filter((option) => !term || option.textContent.toLowerCase().includes(term) || (option.dataset.kh || "").toLowerCase().includes(term)); ui.results.innerHTML = options.length ? options.map((option) => `<button type="button" class="location-combobox-option" data-address-type="${type}" data-address-id="${option.value}"><span class="d-flex align-items-center min-w-0">${type === "country" && option.dataset.flag ? `<img src="${escapeHtml(locationAssetUrl(option.dataset.flag))}" class="location-flag me-2" alt="">` : ""}<span><span class="location-combobox-khmer">${escapeHtml(option.dataset.kh || "")}</span><span class="location-combobox-english d-block">${escapeHtml(option.dataset.en || option.textContent)}</span></span></span></button>`).join("") : `<div class="text-secondary px-2 py-2">No options found</div>`; };
setupAddressComboboxes();
addressFields.forEach((type) => { const ui = addressUi(type); ui.toggle?.addEventListener("click", () => { ui.menu.classList.toggle("d-none"); if (!ui.menu.classList.contains("d-none")) { ui.search.value = ""; renderAddressResults(type); ui.search.focus(); } }); ui.search?.addEventListener("input", () => renderAddressResults(type)); ui.results?.addEventListener("click", (event) => { const option = event.target.closest("[data-address-id]"); if (!option) return; ui.select.value = option.dataset.addressId; ui.select.dispatchEvent(new Event("change", { bubbles: true })); ui.menu.classList.add("d-none"); }); });
const setAddressOptions = (type, items) => {
    const select = field(`address_${type}_id`);
    if (!select) return;
    const config = addressConfig[type];
    select.innerHTML = `<option value="">Select ${config.label}</option>` + items.map((item) => `<option value="${item.id}" data-en="${escapeHtml(item[config.en] || "")}" data-kh="${escapeHtml(item[config.kh] || "")}" data-flag="${escapeHtml(item.flag_path || "")}">${escapeHtml(item[config.kh] ? `${item[config.kh]} - ${item[config.en]}` : item[config.en] || "")}</option>`).join("");
    setAddressSelectedText(type);
};
const updateCurrentAddress = () => {
    const enParts = ["address_house_no_en", "address_street_en", "address_village_id", "address_commune_id", "address_district_id", "address_province_id", "address_country_id"].map((id) => field(id)?.tagName === "SELECT" ? field(id)?.selectedOptions?.[0]?.dataset.en : field(id)?.value).filter(Boolean);
    const khParts = ["address_house_no_kh", "address_street_kh", "address_village_id", "address_commune_id", "address_district_id", "address_province_id", "address_country_id"].map((id) => field(id)?.tagName === "SELECT" ? field(id)?.selectedOptions?.[0]?.dataset.kh : field(id)?.value).filter(Boolean);
    field("current_address_en").value = enParts.join(", ");
    field("current_address_kh").value = khParts.join(", ");
};
const filterAddressLocations = (type) => {
    const index = addressFields.indexOf(type);
    if (index < 0 || index >= addressFields.length - 1) return;
    const next = addressFields[index + 1];
    const parent = field(`address_${type}_id`)?.value;
    const config = addressConfig[next];
    setAddressOptions(next, (addressLocations[config.key] || []).filter((item) => String(item[config.parentField]) === String(parent)));
    for (let i = index + 2; i < addressFields.length; i += 1) setAddressOptions(addressFields[i], []);
    updateCurrentAddress();
};
const loadAddressLocations = async () => {
    setupAddressComboboxes();
    addressLocations = await (await fetch("/locations/options")).json();
    setAddressOptions("country", addressLocations.countries || []);
    addressFields.slice(1).forEach((type) => setAddressOptions(type, []));
};
addressFields.forEach((type) => field(`address_${type}_id`)?.addEventListener("change", () => { setAddressSelectedText(type); filterAddressLocations(type); updateCurrentAddress(); }));
const syncAddressKhmer = (englishId, khmerId, prefix) => {
    const english = field(englishId);
    const khmer = field(khmerId);
    const removePrefix = (value) => String(value || "").replace(new RegExp(`^${prefix}\\s*`, "i"), "").trim();
    english?.addEventListener("input", () => { khmer.value = english.value.trim() ? `${prefix} ${english.value.trim()}` : ""; updateCurrentAddress(); });
    khmer?.addEventListener("input", () => { const value = removePrefix(khmer.value); khmer.value = value ? `${prefix} ${value}` : ""; updateCurrentAddress(); });
};
syncAddressKhmer("address_house_no_en", "address_house_no_kh", "ផ្ទះលេខ");
syncAddressKhmer("address_street_en", "address_street_kh", "ផ្លូវ");

const filterBirthLocations = (type) => {
    const order = birthFields;
    const index = order.indexOf(type);
    if (index < 0 || index >= order.length - 1) return;

    const next = order[index + 1];
    const selected = field(`birth_${type}_id`)?.value;
    const parentKey = birthConfig[next].parentField;
    const items = (birthLocations[birthConfig[next].key] || []).filter((item) => String(item[parentKey]) === String(selected));
    setBirthSelect(next, items);

    for (let i = index + 2; i < order.length; i += 1) {
        setBirthSelect(order[i], []);
    }
};

const setBirthValue = (type, id) => {
    const select = field(`birth_${type}_id`);
    if (!select) return;
    select.value = id ?? "";
    setBirthSelectedText(type);
    filterBirthLocations(type);
};

birthFields.forEach((type) => {
    const ui = birthUi(type);
    ui.toggle?.addEventListener("click", () => {
        ui.menu?.classList.contains("d-none") ? openBirthMenu(type) : closeBirthMenu(type);
    });
    ui.search?.addEventListener("input", () => renderBirthResults(type));
    ui.results?.addEventListener("click", (event) => {
        const option = event.target.closest("[data-birth-id]");
        if (!option || !ui.select) return;
        ui.select.value = option.dataset.birthId;
        ui.select.dispatchEvent(new Event("change", { bubbles: true }));
        setBirthSelectedText(type);
        filterBirthLocations(type);
        closeBirthMenu(type);
    });
    ui.select?.addEventListener("change", () => {
        setBirthSelectedText(type);
        renderBirthResults(type);
    });
});

document.addEventListener("click", (event) => {
    birthFields.forEach((type) => {
        const wrapper = field(`birth-${type}-combobox`);
        const ui = birthUi(type);
        if (wrapper && !wrapper.contains(event.target)) closeBirthMenu(type);
    });
});

const loadOptions = async () => {
    const options = await (await fetch("/student-enrollments/options")).json();
    const documentOptions = await (await fetch("/student-documents/options")).json();
    const documentType = field("enrollment-document-type");
    if (documentType) documentType.innerHTML = `<option value="">Select Document Type</option>` + (documentOptions.types || []).map((item) => `<option value="${item.id}">${escapeHtml(item.name_kh || "")}${item.name_kh ? " / " : ""}${escapeHtml(item.name_en)}</option>`).join("");
    nextStudentNo = options.nextStudentNo || "";
    familyItems = options.families || [];
    familyDetails = options.familyDetails || {};
    setFamilyReferenceOptions(options);
    setOptions("academic_year_id", options.academicYears || [], "academic_year", "Select Academic Year");
    const familySelect = field("existing_family_number");
    if (familySelect) {
        familySelect.innerHTML = `<option value="">No sibling / New family</option>` + familyItems.map((item) => {
    const studentName = item.full_name_en || [item.first_name_en, item.last_name_en].filter(Boolean).join(" ");
            const label = studentName ? `${item.family_number} - ${studentName}` : item.family_number;
            return `<option value="${escapeHtml(item.family_number)}">${escapeHtml(label)}</option>`;
        }).join("");
    }
    const campusSelect = field("campus_id");
    if (campusSelect) {
        campusItems = (options.campuses || []).map((item) => {
            const khName = item.campus_name_kh ? `${item.campus_name_kh} - ` : "";
            return { id: item.id, label: `${khName}${item.campus_name_en ?? ""}` };
        });
        campusSelect.innerHTML = `<option value="">Select Campus</option>` + campusItems.map((item) => `<option value="${item.id}">${escapeHtml(item.label)}</option>`).join("");
        setCampusSelectedText();
        renderCampusResults();
    }
    setOptions("grade_id", options.grades || [], "grade", "Select Grade");
    setOptions("class_id", options.classes || [], "class_name", "Select Class");
    field("session_id").innerHTML = `<option value="">Select Group</option>` + (options.sessions || []).map((item) => `<option value="${item.id}">${escapeHtml(item.session_short_name)}</option>`).join("");
    refreshSearchableEnrollmentLabels();
    const nationality = field("nationality_country_id");
    if (nationality) { nationality.innerHTML = `<option value="">Select Nationality</option>` + (options.countries || []).map((item) => `<option value="${item.id}" data-en="${escapeHtml(item.nationality_name_en || item.country_name_en || "")}" data-kh="${escapeHtml(item.nationality_name_kh || item.country_name_kh || "")}" data-flag="${escapeHtml(item.flag_path || "")}">${escapeHtml(item.nationality_name_en || item.country_name_en || "")}</option>`).join(""); setStudentBilingualText("nationality"); }
    refreshPremiumFieldStates();
};

const openCreate = async () => {
    form.reset();
    resetEnrollmentDocumentFiles();
    field("enrollment_id").value = "";
    field("student_record_id").value = "";
    field("student_no").value = nextStudentNo;
    field("existing_family_number").value = "";
    autoFamilyNumber();
    resetStudentPhotoPreview();
    form.querySelector("[data-alert]").classList.add("d-none");
    title.textContent = "Create Student Enrollment";
    submit.textContent = "Create";
    field("status").value = "1";
    syncDobDisplay();
    closeDobPicker();
    setCampusSelectedText();
    loadEnrollmentDocuments("");
    await loadOptions();
    field("student_no").value = nextStudentNo;
    field("existing_family_number").value = "";
    autoFamilyNumber();
    await loadBirthLocations();
    await loadAddressLocations();
    refreshPremiumFieldStates();
    modal.show();
};

const openEdit = async (id) => {
    const row = rows.find((item) => item.id === id);
    if (!row) return;
    await openCreate();
    field("enrollment_id").value = row.id;
    field("student_record_id").value = row.student_id;
    loadEnrollmentDocuments(row.student_id);
    const student = row.student || {};
    ["student_no", "student_id", "family_number", "first_name_en", "first_name_kh", "gender", "gender_kh", "date_of_birth", "nationality_country_id", "email", "address_country_id", "address_province_id", "address_district_id", "address_commune_id", "address_village_id", "address_house_no_en", "address_house_no_kh", "address_street_en", "address_street_kh", "current_address_en", "current_address_kh", "previous_school", "experienced_english", "test_result", "tested_by"].forEach((key) => {
        field(key).value = student[key] ?? "";
    });
    field("first_name_en").value = student.full_name_en ?? student.first_name_en ?? "";
    field("first_name_kh").value = student.full_name_kh ?? student.first_name_kh ?? "";
    setStudentBilingualText("gender");
    setStudentBilingualText("nationality");
    field("home_phone_number").value = student.home_phone || "";
    syncDobDisplay();
    field("existing_family_number").value = student.family_number || "";
    field("existing_family_number").dispatchEvent(new Event("change", { bubbles: true }));
    populateSelectedFamily();
    if (student.photo_path) showStudentPhotoPreview(`/storage/${student.photo_path}`);
    ["academic_year_id", "campus_id", "grade_id", "class_id", "session_id"].forEach((key) => {
        field(key).value = row[key] ?? "";
    });
    field("enrollment_status").value = row.enrollment_status || (row.status ? "active" : "cancelled");
    refreshSearchableEnrollmentLabels();
    setCampusSelectedText();
    setBirthValue("country", student.birth_country_id);
    setBirthValue("province", student.birth_province_id);
    setBirthValue("district", student.birth_district_id);
    setBirthValue("commune", student.birth_commune_id);
    setBirthValue("village", student.birth_village_id);
    setAddressOptions("country", addressLocations.countries || []);
    field("address_country_id").value = student.address_country_id || "";
    setAddressOptions("province", (addressLocations.provinces || []).filter((item) => String(item.country_id) === String(student.address_country_id)));
    field("address_province_id").value = student.address_province_id || "";
    setAddressOptions("district", (addressLocations.districts || []).filter((item) => String(item.province_id) === String(student.address_province_id)));
    field("address_district_id").value = student.address_district_id || "";
    setAddressOptions("commune", (addressLocations.communes || []).filter((item) => String(item.district_id) === String(student.address_district_id)));
    field("address_commune_id").value = student.address_commune_id || "";
    setAddressOptions("village", (addressLocations.villages || []).filter((item) => String(item.commune_id) === String(student.address_commune_id)));
    field("address_village_id").value = student.address_village_id || "";
    addressFields.forEach((type) => setAddressSelectedText(type));
    updateCurrentAddress();
    field("status").value = row.status ? "1" : "0";
    field("enrollment_status").value = row.enrollment_status || (row.status ? "active" : "cancelled");
    setEnrollmentDate(row.enrolled_on ? String(row.enrolled_on).slice(0, 10) : "");
    title.textContent = "Edit Student Enrollment";
    submit.textContent = "Update";
    refreshPremiumFieldStates();
};

let selectedEnrollmentDocumentFiles = [];
const documentDropzone = field("enrollmentDocumentDropzone");
const documentFileInput = field("enrollment-document-file");
const documentFileList = field("enrollmentDocumentFileList");
const formatDocumentFileSize = (bytes) => {
    if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
};
const documentFileIcon = (file) => {
    const extension = file.name.split(".").pop()?.toLowerCase();
    if (extension === "pdf") return "ti-file-type-pdf";
    if (["doc", "docx"].includes(extension)) return "ti-file-type-doc";
    if (["jpg", "jpeg", "png"].includes(extension)) return "ti-photo";
    return "ti-file";
};
const renderEnrollmentDocumentFiles = () => {
    if (!documentFileList) return;
    documentFileList.innerHTML = selectedEnrollmentDocumentFiles.map((file, index) => `
        <div class="premium-document-file-item">
            <span class="document-file-icon"><i class="ti ${documentFileIcon(file)}"></i></span>
            <div class="min-w-0"><div class="document-file-name">${escapeHtml(file.name)}</div><div class="document-file-meta">${formatDocumentFileSize(file.size)} · Ready to upload</div></div>
            <button type="button" class="document-file-remove" data-document-file-remove="${index}" aria-label="Remove ${escapeHtml(file.name)}"><i class="ti ti-x"></i></button>
        </div>`).join("");
};
const syncEnrollmentDocumentInput = () => {
    if (!documentFileInput) return;
    try {
        const transfer = new DataTransfer();
        selectedEnrollmentDocumentFiles.forEach((file) => transfer.items.add(file));
        documentFileInput.files = transfer.files;
    } catch {
        // The visual queue remains usable in browsers that do not expose DataTransfer.
    }
};
const addEnrollmentDocumentFiles = (files) => {
    const validFiles = Array.from(files || []).filter((file) => {
        const validType = /\.(pdf|jpe?g|png|docx?)$/i.test(file.name);
        return validType && file.size <= 2 * 1024 * 1024;
    });
    const existingKeys = new Set(selectedEnrollmentDocumentFiles.map((file) => `${file.name}:${file.size}:${file.lastModified}`));
    selectedEnrollmentDocumentFiles = [...selectedEnrollmentDocumentFiles, ...validFiles.filter((file) => !existingKeys.has(`${file.name}:${file.size}:${file.lastModified}`))];
    renderEnrollmentDocumentFiles();
    syncEnrollmentDocumentInput();
};
const resetEnrollmentDocumentFiles = () => {
    selectedEnrollmentDocumentFiles = [];
    if (documentFileInput) documentFileInput.value = "";
    renderEnrollmentDocumentFiles();
};
documentDropzone?.addEventListener("click", (event) => { if (event.target !== documentFileInput) documentFileInput?.click(); });
documentDropzone?.addEventListener("keydown", (event) => { if (event.key === "Enter" || event.key === " ") documentFileInput?.click(); });
documentDropzone?.addEventListener("dragover", (event) => { event.preventDefault(); documentDropzone.classList.add("is-dragging"); });
documentDropzone?.addEventListener("dragleave", () => documentDropzone.classList.remove("is-dragging"));
documentDropzone?.addEventListener("drop", (event) => { event.preventDefault(); documentDropzone.classList.remove("is-dragging"); addEnrollmentDocumentFiles(event.dataTransfer?.files); });
documentFileInput?.addEventListener("change", () => addEnrollmentDocumentFiles(documentFileInput.files));
documentFileList?.addEventListener("click", (event) => {
    const remove = event.target.closest("[data-document-file-remove]");
    if (!remove) return;
    selectedEnrollmentDocumentFiles.splice(Number(remove.dataset.documentFileRemove), 1);
    syncEnrollmentDocumentInput();
    renderEnrollmentDocumentFiles();
});

const uploadEnrollmentDocuments = async (studentId) => {
    const files = Array.from(field("enrollment-document-file")?.files || []);
    if (!files.length) return;
    if (!field("enrollment-document-type")?.value) throw new Error("Please select a document type for the uploaded file.");
    for (const file of files) {
        const documentForm = new FormData();
        documentForm.append("student_id", studentId);
        documentForm.append("document_type_id", field("enrollment-document-type").value);
        documentForm.append("title", field("enrollment-document-title").value);
        documentForm.append("document_number", field("enrollment-document-number").value);
        documentForm.append("description", field("enrollment-document-description").value);
        documentForm.append("file", file);
        const response = await fetch("/student-documents", { method: "POST", headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf }, body: documentForm });
        const result = await response.json();
        if (!response.ok) throw new Error(result.message || Object.values(result.errors || {})[0]?.[0] || "Unable to upload student document.");
    }
};

const loadEnrollmentDocuments = async (studentId) => {
    const list = field("enrollment-document-list");
    if (!list) return;

    if (!studentId) {
        list.innerHTML = '<tr><td colspan="4" class="text-center text-secondary py-4">Save or select a student to view submitted documents.</td></tr>';
        return;
    }

    list.innerHTML = '<tr><td colspan="4" class="text-center text-secondary py-4">Loading documents...</td></tr>';

    try {
        const response = await fetch(`/student-documents/fetch/${studentId}`, {
            headers: { Accept: "application/json" },
        });
        if (!response.ok) throw new Error("Unable to load submitted documents.");
        const documents = await response.json();

        list.innerHTML = documents.length
            ? documents.map((document) => {
                const typeName = document.type
                    ? `${escapeHtml(document.type.name_kh || "")}<br><small class="text-secondary">${escapeHtml(document.type.name_en || "")}</small>`
                    : escapeHtml(document.document_type || "-");
                return `<tr>
                    <td>${typeName}</td>
                    <td>${escapeHtml(document.title || "-")}</td>
                    <td>${escapeHtml(document.document_number || "-")}</td>
                    <td>
                        <a href="/student-documents/${document.id}/download" target="_blank" class="btn btn-sm btn-outline-primary" title="Download">
                            <i class="bi bi-download"></i>
                        </a>
                    </td>
                </tr>`;
            }).join("")
            : '<tr><td colspan="4" class="text-center text-secondary py-4">No submitted documents found.</td></tr>';
    } catch (error) {
        console.error(error);
        list.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-4">Unable to load submitted documents.</td></tr>';
    }
};

form.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    syncFamilyPhoneValues();
    syncHomePhoneValue();
    submit.disabled = true;
    try {
        const response = await fetch("/student-enrollments/save", {
            method: "POST",
            headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf },
            body: new FormData(form),
        });
        const responseText = await response.text();
        let result;
        try { result = responseText ? JSON.parse(responseText) : {}; }
        catch { throw new Error("The server returned an unexpected response. Please try again or check the enrollment information."); }
        if (response.status === 422) return alertError(result.message || Object.values(result.errors || {})[0]?.[0]);
        if (!response.ok) throw new Error(result.message || "Unable to save student enrollment.");
        await uploadEnrollmentDocuments(result.data?.student_id);
        await loadEnrollmentDocuments(result.data?.student_id);
        modal.hide();
        showSuccess("Saved", result.message);
        fetchRows();
    } catch (error) {
        alertError(error.message);
    } finally {
        submit.disabled = false;
    }
});

field("enrollment-document-list-tab-button")?.addEventListener("shown.bs.tab", () => {
    loadEnrollmentDocuments(field("student_record_id")?.value);
});

const remove = async (id) => {
    if (!(await showConfirm("Delete Enrollment", "Are you sure?", "Delete", "Cancel")).isConfirmed) return;
    const response = await fetch(`/student-enrollments/delete/${id}`, { method: "DELETE", headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf } });
    const result = await response.json();
    if (response.ok) {
        showSuccess("Deleted", result.message);
        fetchRows();
    } else {
        showError("Error", result.message);
    }
};

const showEnrollmentHistory = async (id, studentName) => {
    document.getElementById("enrollmentHistoryTitle").textContent = `Enrollment History - ${studentName}`;
    enrollmentHistoryTable.innerHTML = `<tr><td colspan="10" class="text-center">Loading...</td></tr>`;
    historyModal.show();
    const response = await fetch(`/student-enrollments/${id}/history`, { headers: { Accept: "application/json" } });
    const result = await response.json();
    const history = result.history || [];
    const changed = (item, index, key) => index < history.length - 1 && String(item[key] ?? "") !== String(history[index + 1][key] ?? "");
    const latestAssignment = ["student_type", "academic_year_id", "campus_id", "grade_id", "class_id", "session_id", "enrollment_status"];
    const cell = (item, index, key, value) => `<td class="${index === 0 && latestAssignment.includes(key) ? "bg-green-lt fw-bold" : changed(item, index, key) ? "bg-yellow-lt" : ""}">${value}</td>`;
    enrollmentHistoryTable.innerHTML = history.length ? history.map((item, index) => `
        <tr>
            ${cell(item, index, "student_type", `<span class="badge bg-${item.student_type === "old" ? "blue" : "green"}-lt">${item.student_type === "old" ? "Old" : "New"}</span>`)}
            <td>${escapeHtml(item.action_type || "-")}</td>
            ${cell(item, index, "academic_year_id", escapeHtml(item.academic_year?.academic_year || "-"))}
            ${cell(item, index, "campus_id", escapeHtml(item.campus?.campus_name_en || "-"))}
            ${cell(item, index, "grade_id", escapeHtml(item.grade?.grade || "-"))}
            ${cell(item, index, "class_id", escapeHtml(item.school_class?.class_name || "-"))}
            ${cell(item, index, "session_id", escapeHtml(item.session?.session_short_name || "-"))}
            ${cell(item, index, "enrollment_status", escapeHtml(item.enrollment_status || "-"))}
            <td class="${index === 0 ? "bg-green-lt fw-bold" : ""}">${escapeHtml(formatDateTime(item.updated_at))}</td>
            <td>${escapeHtml(item.changed_by?.name || "System")}</td>
        </tr>`).join("") : `<tr><td colspan="10" class="text-center">No enrollment history found.</td></tr>`;
};

async function fetchRows(page = 1) {
    const response = await fetch(`/student-enrollments/fetch?page=${page}&perPage=${perPage.value}&search=${encodeURIComponent(search.value)}`);
    const result = await response.json();
    rows = result.data || [];
    table.innerHTML = rows.length ? rows.map((item) => `
        <tr>
            <td>${escapeHtml(item.student?.student_no ?? "")}</td>
            <td>${studentPhotoMarkup(item.student)}</td>
            <td>${escapeHtml(item.student?.student_id ?? "-")}</td>
            <td>${escapeHtml(item.student?.first_name_en ?? "")} ${escapeHtml(item.student?.last_name_en ?? "")}</td>
            <td><span class="badge bg-${item.student_type === "old" ? "blue" : "green"}-lt">${item.student_type === "old" ? "Old" : "New"}</span></td>
            <td>${escapeHtml(item.academic_year?.academic_year ?? "-")}</td>
            <td>${escapeHtml(item.campus?.campus_name_en ?? "-")}</td>
            <td>${escapeHtml(item.grade?.grade ?? "-")}</td>
            <td>${escapeHtml(item.school_class?.class_name ?? "-")}</td>
            <td>${escapeHtml(item.session?.session_short_name ?? "-")}</td>
            <td><span class="badge bg-${item.enrollment_status === "active" ? "success" : "secondary"}-lt">${escapeHtml(item.enrollment_status || (item.status ? "active" : "inactive"))}</span></td>
            <td>
                <button class="btn btn-info btn-sm" onclick="enrollmentsPage.history(${item.id}, '${escapeHtml(`${item.student?.first_name_en ?? ""} ${item.student?.last_name_en ?? ""}`)}')">History</button>
                <button class="btn btn-primary btn-sm" onclick="enrollmentsPage.edit(${item.id})">Edit</button>
                <button class="btn btn-danger btn-sm" onclick="enrollmentsPage.remove(${item.id})">Delete</button>
    </td>
        </tr>`).join("") : `<tr><td colspan="12" class="text-center">No enrollments found.</td></tr>`;
    renderPagination(result, "enrollments-pagination-container", "enrollments-per-page", fetchRows);
    renderPageInfo(result);
}

document.getElementById("newEnrollment").onclick = openCreate;
perPage.onchange = () => fetchRows();
search.onkeyup = () => fetchRows();
field("student_id")?.addEventListener("input", autoFamilyNumber);
field("existing_family_number")?.addEventListener("change", () => { autoFamilyNumber(); populateSelectedFamily(); });
field("date_of_birth")?.addEventListener("change", syncDobDisplay);
field("date_of_birth_direct")?.addEventListener("change", (event) => setDobValue(event.target.value));
loadOptions();
loadBirthLocations();
fetchRows();
window.enrollmentsPage = { edit: openEdit, remove, history: showEnrollmentHistory };
