import { renderPagination, renderPageInfo } from "./helpers/pagination.js";
import { showSuccess, showConfirm, showError } from "./helpers/sweet-alert2.js";
import intlTelInput from "intl-tel-input";
import "intl-tel-input/styles";

const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const table = document.getElementById("familiesTable");
const search = document.getElementById("families-search");
const perPage = document.getElementById("families-per-page");
const form = document.getElementById("familyForm");
const modal = new bootstrap.Modal(document.getElementById("familyModal"));
const changeStudentFamilyModal = new bootstrap.Modal(
    document.getElementById("changeStudentFamilyModal"),
);
const membersModal = new bootstrap.Modal(
    document.getElementById("membersModal"),
);
const memberFormModal = new bootstrap.Modal(
    document.getElementById("memberFormModal"),
);
const fields = [
    "family_id",
    "family_number",
    "family_status",
];
const field = (id) => document.getElementById(id);
const parentRelationships = ["mother", "father", "guardian"];
const parentFields = [
    "full_name_en",
    "full_name_kh",
    "occupation_id",
    "nationality_country_id",
    "phone",
    "workplace",
];
let familyPhoneInputs = [];
const formatCambodiaPhoneDisplay = (value = "") => {
    let digits = String(value).replace(/\D/g, "");
    if (digits.startsWith("855")) digits = digits.slice(3);
    if (digits.startsWith("0")) digits = digits.slice(1);
    digits = digits.slice(0, 9);
    if (digits.length <= 2) return digits;
    if (digits.length <= 5) return `${digits.slice(0, 2)} ${digits.slice(2)}`;
    return `${digits.slice(0, 2)} ${digits.slice(2, 5)} ${digits.slice(5)}`;
};
const normalizeCambodiaPhoneValue = (value = "") => {
    let digits = String(value).replace(/\D/g, "");
    if (digits.startsWith("855")) digits = digits.slice(3);
    if (digits.startsWith("0")) digits = digits.slice(1);
    return digits ? `+855${digits}` : "";
};
const syncFamilyPhoneInput = ({ visible, hidden, intl }) => {
    if (!visible || !hidden) return;
    hidden.value =
        intl?.getNumber() ||
        normalizeCambodiaPhoneValue(visible.value);
    visible
        .closest(".premium-floating-field")
        ?.classList.toggle("has-value", Boolean(visible.value.trim()));
    refreshFamilyFloatingFields();
};
const initFamilyPhoneInputs = () => {
    if (familyPhoneInputs.length) return;
    parentRelationships.forEach((relationship) => {
        const hidden = field(`family_${relationship}_phone`);
        const visible = field(`family_${relationship}_phone_number`);
        if (!hidden || !visible) return;
        const intl = intlTelInput(visible, {
            initialCountry: "kh",
            nationalMode: true,
            separateDialCode: true,
            loadUtils: () => import("intl-tel-input/utils"),
        });
        const item = { visible, hidden, intl };
        familyPhoneInputs.push(item);
        visible.addEventListener("input", () => {
            visible.value = formatCambodiaPhoneDisplay(visible.value);
            syncFamilyPhoneInput(item);
        });
        visible.addEventListener("countrychange", () =>
            syncFamilyPhoneInput(item),
        );
    });
};
const syncFamilyPhoneInputs = () => {
    initFamilyPhoneInputs();
    familyPhoneInputs.forEach(syncFamilyPhoneInput);
};
const setFamilyPhoneValue = (relationship, value = "") => {
    initFamilyPhoneInputs();
    const item = familyPhoneInputs.find(
        (phoneInput) => phoneInput.hidden.id === `family_${relationship}_phone`,
    );
    if (!item) return;
    item.hidden.value = value || "";
    item.visible.value = formatCambodiaPhoneDisplay(value || "");
    syncFamilyPhoneInput(item);
};
const syncStatusToggle = (select, toggle) => {
    if (!select || !toggle) return;
    const active = String(select.value) === "1";
    toggle.classList.toggle("is-active", active);
    toggle.setAttribute("aria-pressed", active ? "true" : "false");
    const label = toggle.querySelector(".status-toggle-label");
    if (label) label.textContent = active ? "ON" : "OFF";
};
const bindStatusToggle = (selectId, toggleId) => {
    const select = field(selectId);
    const toggle = field(toggleId);
    toggle?.addEventListener("click", () => {
        select.value = select.value === "1" ? "0" : "1";
        syncStatusToggle(select, toggle);
    });
    syncStatusToggle(select, toggle);
};
bindStatusToggle("family_status", "familyStatusToggle");
const familyFloatingFields = () =>
    Array.from(
        document.querySelectorAll(
            "#familyModal .premium-floating-field",
        ),
    );
const familyFloatingFieldHasValue = (wrapper) => {
    const select = wrapper.querySelector("select");
    if (select && String(select.value || "").trim()) return true;
    const input = wrapper.querySelector(
        "input:not([type='hidden']), textarea",
    );
    return Boolean(input && String(input.value || "").trim());
};
const refreshFamilyFloatingFields = () => {
    familyFloatingFields().forEach((wrapper) => {
        wrapper.classList.toggle(
            "has-value",
            familyFloatingFieldHasValue(wrapper),
        );
    });
};
document.getElementById("familyModal")?.addEventListener("shown.bs.modal", () => {
    initFamilyPhoneInputs();
    refreshFamilyFloatingFields();
    window.setTimeout(refreshFamilyFloatingFields, 50);
});
document.addEventListener("input", (event) => {
    const wrapper = event.target.closest?.("#familyModal .premium-floating-field");
    if (wrapper) {
        wrapper.classList.toggle("has-value", familyFloatingFieldHasValue(wrapper));
    }
});
document.addEventListener("change", (event) => {
    const wrapper = event.target.closest?.("#familyModal .premium-floating-field");
    if (wrapper) {
        wrapper.classList.toggle("has-value", familyFloatingFieldHasValue(wrapper));
    }
});
const escapeHtml = (value = "") =>
    String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
const familyComboboxLabel = (kh = "", en = "") =>
    [kh, en].filter(Boolean).join(" / ");
const familyComboUi = (relationship, type) => {
    const selectSuffix =
        type === "nationality" ? "nationality_country_id" : "occupation_id";
    return {
        select: field(`family_${relationship}_${selectSuffix}`),
        toggle: field(`family-${relationship}-${type}-toggle`),
        menu: field(`family-${relationship}-${type}-menu`),
        search: field(`family-${relationship}-${type}-search`),
        results: field(`family-${relationship}-${type}-results`),
        selected: field(`family-${relationship}-${type}-selected`),
    };
};
const closeFamilyComboboxes = (exceptMenu = null) => {
    document
        .querySelectorAll("#familyModal .location-combobox-menu")
        .forEach((menu) => {
            if (menu !== exceptMenu) menu.classList.add("d-none");
        });
    document
        .querySelectorAll("#familyModal .location-combobox")
        .forEach((combo) => {
            if (!exceptMenu || combo.querySelector(".location-combobox-menu") !== exceptMenu)
                combo.classList.remove("is-open");
        });
};
const setFamilyComboSelected = (ui, type) => {
    const option = ui.select?.selectedOptions?.[0];
    if (!ui.selected) return;
    if (!option?.value) {
        ui.selected.textContent = "";
        refreshFamilyFloatingFields();
        return;
    }
    const en = option.textContent?.trim() || "";
    const kh = option.dataset.kh || "";
    const flag =
        type === "nationality" && option.dataset.flag
            ? `<img src="${escapeHtml(option.dataset.flag)}" alt="" class="location-flag me-2">`
            : "";
    ui.selected.innerHTML = `${flag}<span class="location-combobox-selected-text">${escapeHtml(familyComboboxLabel(kh, en))}</span>`;
    refreshFamilyFloatingFields();
};
const renderFamilyComboResults = (ui, type) => {
    if (!ui.select || !ui.results) return;
    const term = (ui.search?.value || "").trim().toLowerCase();
    const options = Array.from(ui.select.options)
        .slice(1)
        .filter((option) => {
            const haystack = `${option.textContent || ""} ${option.dataset.kh || ""}`.toLowerCase();
            return !term || haystack.includes(term);
        });
    const emptyMessage =
        type === "occupation" ? "No occupations found" : "No nationalities found";
    ui.results.innerHTML = options.length
        ? options
              .map((option) => {
                  const flag =
                      type === "nationality" && option.dataset.flag
                          ? `<img src="${escapeHtml(option.dataset.flag)}" alt="" class="location-flag me-2">`
                          : "";
                  return `<button type="button" class="location-combobox-option" data-family-combo-value="${escapeHtml(option.value)}"><span class="d-flex align-items-center min-w-0">${flag}<span class="min-w-0"><span class="location-combobox-khmer">${escapeHtml(option.dataset.kh || "")}</span><span class="location-combobox-english d-block">${escapeHtml(option.textContent || "")}</span></span></span></button>`;
              })
              .join("")
        : `<div class="text-secondary px-2 py-2">${emptyMessage}</div>`;
    ui.results.querySelectorAll("[data-family-combo-value]").forEach((button) => {
        button.addEventListener("mousedown", (event) => {
            event.preventDefault();
            event.stopPropagation();
            ui.select.value = button.dataset.familyComboValue;
            ui.select.dispatchEvent(new Event("change", { bubbles: true }));
            closeFamilyComboboxes();
        });
    });
};
const setupFamilyCombo = (relationship, type) => {
    const ui = familyComboUi(relationship, type);
    if (!ui.select || !ui.toggle || !ui.menu) return;
    setFamilyComboSelected(ui, type);
    ui.toggle.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();
        const isClosed = ui.menu.classList.contains("d-none");
        closeFamilyComboboxes(isClosed ? ui.menu : null);
        if (isClosed) {
            ui.menu.classList.remove("d-none");
            ui.toggle.closest(".location-combobox")?.classList.add("is-open");
            renderFamilyComboResults(ui, type);
            ui.search?.focus();
        } else {
            ui.menu.classList.add("d-none");
            ui.toggle.closest(".location-combobox")?.classList.remove("is-open");
        }
    });
    ui.search?.addEventListener("input", () => renderFamilyComboResults(ui, type));
    ui.select.addEventListener("change", () => setFamilyComboSelected(ui, type));
};
const setupFamilyComboboxes = () => {
    parentRelationships.forEach((relationship) => {
        setupFamilyCombo(relationship, "occupation");
        setupFamilyCombo(relationship, "nationality");
    });
    document.addEventListener("click", (event) => {
        if (!event.target.closest("#familyModal .location-combobox")) {
            closeFamilyComboboxes();
        }
    });
};
const changeFamilyUi = () => ({
    select: field("change_family_id"),
    toggle: field("change-family-toggle"),
    menu: field("change-family-menu"),
    search: field("change-family-search"),
    results: field("change-family-results"),
    selected: field("change-family-selected"),
});
const setChangeFamilySelected = () => {
    const ui = changeFamilyUi();
    if (!ui.selected) return;
    const option = ui.select?.selectedOptions?.[0];
    ui.selected.textContent = option?.value ? option.textContent.trim() : "";
    ui.selected
        .closest(".premium-floating-field")
        ?.classList.toggle("has-value", Boolean(option?.value));
};
const renderChangeFamilyResults = () => {
    const ui = changeFamilyUi();
    if (!ui.select || !ui.results) return;
    const term = (ui.search?.value || "").trim().toLowerCase();
    const options = Array.from(ui.select.options)
        .slice(1)
        .filter(
            (option) =>
                !option.hidden &&
                (!term || option.textContent.toLowerCase().includes(term)),
        );
    ui.results.innerHTML = options.length
        ? options
              .map(
                  (option) =>
                      `<button type="button" class="location-combobox-option" data-change-family-value="${escapeHtml(option.value)}">${escapeHtml(option.textContent)}</button>`,
              )
              .join("")
        : `<div class="text-secondary px-2 py-2">No families found</div>`;
    ui.results
        .querySelectorAll("[data-change-family-value]")
        .forEach((button) => {
            button.addEventListener("mousedown", (event) => {
                event.preventDefault();
                ui.select.value = button.dataset.changeFamilyValue;
                ui.select.dispatchEvent(new Event("change", { bubbles: true }));
                ui.menu?.classList.add("d-none");
                ui.toggle
                    ?.closest(".location-combobox")
                    ?.classList.remove("is-open");
            });
        });
};
const setupChangeFamilyCombobox = () => {
    const ui = changeFamilyUi();
    if (!ui.select || !ui.toggle || !ui.menu) return;
    ui.toggle.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();
        const isClosed = ui.menu.classList.contains("d-none");
        closeFamilyComboboxes();
        document
            .querySelectorAll("#changeStudentFamilyModal .location-combobox-menu")
            .forEach((menu) => {
                if (menu !== ui.menu) menu.classList.add("d-none");
            });
        ui.menu.classList.toggle("d-none", !isClosed);
        ui.toggle.closest(".location-combobox")?.classList.toggle("is-open", isClosed);
        if (isClosed) {
            if (ui.search) ui.search.value = "";
            renderChangeFamilyResults();
            ui.search?.focus();
        }
    });
    ui.search?.addEventListener("input", renderChangeFamilyResults);
    ui.select.addEventListener("change", setChangeFamilySelected);
    document.addEventListener("click", (event) => {
        if (!event.target.closest("#changeStudentFamilyModal .location-combobox")) {
            ui.menu?.classList.add("d-none");
            ui.toggle?.closest(".location-combobox")?.classList.remove("is-open");
        }
    });
    setChangeFamilySelected();
};
const upsertChangeFamilyOption = (family) => {
    const select = field("change_family_id");
    if (!select || !family?.id) return;
    let option = Array.from(select.options).find(
        (item) => String(item.value) === String(family.id),
    );
    if (!option) {
        option = document.createElement("option");
        option.value = family.id;
        select.appendChild(option);
    }
    option.textContent = family.family_number || "";
    setChangeFamilySelected();
};
let currentRows = [];
let activeFamily = null;
let currentMembers = [];
const expandedFamilyIds = new Set();
let currentPage = 1;
let sortBy = "family_number";
let sortDir = "asc";
let changeFamilyStudent = null;

const familyMember = (family, relationship) =>
    family.members?.find((member) => member.relationship_type === relationship);
const memberSummary = (family, relationship) => {
    const member = familyMember(family, relationship);
    if (!member) return '<span class="text-secondary">Not provided</span>';
    const nameEn = member.full_name_en || "";
    const nameKh = member.full_name_kh || "";
    return `<span class="school-profile-khmer text-secondary small">${escapeHtml(nameKh || "-")}</span><strong>${escapeHtml(nameEn || "-")}</strong>${member.phone ? `<span class="family-phone text-secondary small"><i class="ti ti-phone"></i>${escapeHtml(member.phone)}</span>` : ""}`;
};
const memberMobileSummary = (family, relationship, label) => {
    const member = familyMember(family, relationship);
    if (!member)
        return `<div class="family-mobile-detail family-mobile-parent-detail"><span>${escapeHtml(label)}</span><strong class="text-secondary">Not provided</strong></div>`;
    return `<div class="family-mobile-detail family-mobile-parent-detail"><span>${escapeHtml(label)}</span>${member.full_name_kh ? `<div class="school-profile-khmer family-mobile-parent-kh">${escapeHtml(member.full_name_kh)}</div>` : ""}<strong>${escapeHtml(member.full_name_en || "-")}</strong>${member.phone ? `<div class="text-secondary small"><i class="ti ti-phone me-1"></i>${escapeHtml(member.phone)}</div>` : ""}</div>`;
};
const localStatusToggleMarkup = (entity, id, active) => `
    <button type="button" class="status-toggle ${active ? "is-active" : ""}"
        data-status-toggle data-status-entity="${entity}" data-status-id="${id}" data-status="${active ? 1 : 0}"
        aria-label="Set status ${active ? "inactive" : "active"}" aria-pressed="${active}">
        <span class="status-toggle-label">${active ? "ON" : "OFF"}</span><span class="status-toggle-knob"></span>
    </button>`;
const refreshSortIcons = () => {
    document.querySelectorAll("[data-family-sort]").forEach((button) => {
        button.classList.toggle(
            "text-primary",
            button.dataset.familySort === sortBy,
        );
    });
};
const familyMobileCard = (family, index, absoluteNumber) => {
    const expanded = expandedFamilyIds.has(Number(family.id));
    const status =
        window.statusToggleMarkup?.("family", family.id, Boolean(family.status)) ||
        localStatusToggleMarkup("family", family.id, Boolean(family.status));
    return `
        <article class="family-mobile-card">
            <div class="family-mobile-card-top">
                <div class="family-mobile-number">${String(absoluteNumber).padStart(2, "0")}</div>
                ${status}
            </div>
            <div class="family-mobile-main">
                <div class="family-mobile-label">Family Number</div>
                <div class="family-mobile-family-row">
                    <div class="family-mobile-family-number">
                        ${escapeHtml(family.family_number || "-")}
                        <button type="button" class="family-expand-toggle ${expanded ? "is-expanded" : ""}" data-expand-family="${family.id}" aria-expanded="${expanded ? "true" : "false"}" title="${expanded ? "Hide students" : "Show students"}">
                            <i class="ti ti-chevron-down"></i><span class="visually-hidden">${expanded ? "Hide students" : "Show students"}</span>
                        </button>
                    </div>
                    <div class="family-mobile-students"><span>Students</span><strong>${escapeHtml(family.students_count ?? 0)}</strong></div>
                </div>
            </div>
            <div class="family-mobile-details">
                ${memberMobileSummary(family, "mother", "Mother Information")}
                ${memberMobileSummary(family, "father", "Father Information")}
            </div>
            ${expanded ? `<div class="family-mobile-expanded">${familyStudentsMarkup(family).replace(/^<tr class="family-expanded-row"><td colspan="7">|<\/td><\/tr>$/g, "")}</div>` : ""}
            <div class="family-mobile-actions">
                <button class="btn btn-primary btn-sm" data-edit="${family.id}"><i class="ti ti-pencil icon"></i>Edit</button>
                <button class="btn btn-danger btn-sm" data-delete="${family.id}"><i class="ti ti-trash icon"></i>Delete</button>
            </div>
        </article>
    `;
};
const renderFamilyMobileCards = (rows, offset) => {
    const mobileCards = document.getElementById("familiesMobileCards");
    if (!mobileCards) return;
    mobileCards.innerHTML = rows.length
        ? rows
              .map((family, index) =>
                  familyMobileCard(family, index, offset + index + 1),
              )
              .join("")
        : `<div class="family-mobile-empty text-center text-secondary">No families found.</div>`;
};
const bindFamilyListActions = (root) => {
    root.querySelectorAll("[data-expand-family]").forEach((button) =>
        button.addEventListener("click", () => {
            const id = Number(button.dataset.expandFamily);
            if (expandedFamilyIds.has(id)) expandedFamilyIds.delete(id);
            else {
                expandedFamilyIds.clear();
                expandedFamilyIds.add(id);
            }
            fetchFamilies(currentPage, parseInt(perPage.value, 10));
        }),
    );
    root
        .querySelectorAll("[data-edit]")
        .forEach((button) =>
            button.addEventListener("click", () =>
                editFamily(Number(button.dataset.edit)),
            ),
        );
    root
        .querySelectorAll("[data-delete]")
        .forEach((button) =>
            button.addEventListener("click", () =>
                deleteFamily(Number(button.dataset.delete)),
            ),
        );
    root.querySelectorAll("[data-change-student-family]").forEach((button) =>
        button.addEventListener("click", () => {
            changeFamilyStudent = Number(button.dataset.changeStudentFamily);
            field("change_family_student_id").value = String(changeFamilyStudent);
            field("change_family_student_label").textContent =
                button.dataset.studentLabel || "Selected student";
            const familySelect = field("change_family_id");
            if (familySelect) {
                familySelect.value = "";
                Array.from(familySelect.options).forEach((option) => {
                    option.hidden =
                        String(option.value) ===
                        String(button.dataset.currentFamily);
                });
                familySelect.dispatchEvent(new Event("change", { bubbles: true }));
            }
            const changeSearch = field("change-family-search");
            if (changeSearch) changeSearch.value = "";
            renderChangeFamilyResults();
            document
                .querySelector("[data-change-family-alert]")
                ?.classList.add("d-none");
            changeStudentFamilyModal.show();
        }),
    );
};
const familyStudentsMarkup = (family) => {
    const students = family.students || [];
    if (!students.length)
        return `<tr class="family-expanded-row"><td colspan="7"><div class="family-expanded-card text-secondary">No students linked to this family.</div></td></tr>`;
    const dateLabel = (value) => {
        if (!value) return "-";
        const date = new Date(`${value}T00:00:00`);
        if (Number.isNaN(date.getTime())) return value;
        return `${String(date.getDate()).padStart(2, "0")}-${date.toLocaleString("en-US", { month: "short" })}-${date.getFullYear()}`;
    };
    const khmerDateLabel = (value) => {
        if (!value) return "-";
        const date = new Date(`${value}T00:00:00`);
        if (Number.isNaN(date.getTime())) return value;
        const digits = "០១២៣៤៥៦៧៨៩";
        const khmerNumber = (number) =>
            String(number)
                .split("")
                .map((digit) => digits[Number(digit)] ?? digit)
                .join("");
        const months = [
            "មករា",
            "កុម្ភៈ",
            "មីនា",
            "មេសា",
            "ឧសភា",
            "មិថុនា",
            "កក្កដា",
            "សីហា",
            "កញ្ញា",
            "តុលា",
            "វិច្ឆិកា",
            "ធ្នូ",
        ];
        return `${khmerNumber(String(date.getDate()).padStart(2, "0"))} ${months[date.getMonth()]} ${khmerNumber(date.getFullYear())}`;
    };
    const ageLabel = (value) => {
        if (!value) return "-";
        const birth = new Date(`${value}T00:00:00`);
        if (Number.isNaN(birth.getTime())) return "-";
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        if (birth > today) return "-";
        let years = today.getFullYear() - birth.getFullYear();
        let months = today.getMonth() - birth.getMonth();
        let days = today.getDate() - birth.getDate();
        if (days < 0) {
            months -= 1;
            const previousMonth = new Date(
                today.getFullYear(),
                today.getMonth(),
                0,
            );
            days += previousMonth.getDate();
        }
        if (months < 0) {
            years -= 1;
            months += 12;
        }
        const unit = (number, singular) =>
            `${number} ${singular}${number === 1 ? "" : "s"}`;
        return `${unit(years, "year")} ${unit(months, "month")}, ${unit(days, "day")}`;
    };
    const photo = (student) =>
        student.photo_path
            ? `<img src="/storage/${encodeURIComponent(student.photo_path).replace(/%2F/g, "/")}" alt="Student photo" class="family-student-photo family-student-photo-large">`
            : `<span class="family-student-photo family-student-photo-large family-student-photo-placeholder"><i class="ti ti-user"></i></span>`;
    const enrollment = (item) => {
        const lifecycleStatus = item.academic_year?.lifecycle_status;
        const storedStatus = String(item.enrollment_status || "");
        const terminalStatus = [
            "withdrawn",
            "transferred",
            "graduated",
            "cancelled",
        ].includes(storedStatus)
            ? storedStatus
            : null;
        const status = String(
            terminalStatus ||
                (lifecycleStatus === "started"
                    ? "active"
                    : lifecycleStatus === "finished"
                      ? "completed"
                      : storedStatus || (item.status ? "active" : "inactive")),
        );
        const statusClass =
            status === "active"
                ? "success"
                : status === "completed" || status === "graduated"
                  ? "blue"
                  : status === "withdrawn"
                    ? "danger"
                    : "secondary";
        const statusLabel = status
            .replace(/_/g, " ")
            .replace(/\b\w/g, (letter) => letter.toUpperCase());
        const gradeClass = `${item.grade?.grade || "-"}${item.school_class?.class_name || ""}`;
        const group = item.session?.session_short_name || "-";
        return `<div class="family-enrollment-item"><span>${escapeHtml(item.academic_year?.academic_year || "-")}</span><span>${escapeHtml(item.campus?.campus_name_en || "-")}</span><span>${escapeHtml(`${gradeClass}-${group}`)}</span><span class="family-enrollment-status"><span class="badge bg-${statusClass}-lt">${escapeHtml(statusLabel)}</span></span></div>`;
    };
    return `<tr class="family-expanded-row"><td colspan="7"><div class="family-expanded-card"><div class="d-flex justify-content-between align-items-center mb-3"><div><div class="fw-bold fs-3">Students in this family</div><div class="text-secondary small">Student information and enrollment history</div></div><span class="badge bg-blue-lt">${students.length} Student${students.length === 1 ? "" : "s"}</span></div><div class="family-student-list">${students.map((student) => `<div class="family-student-card"><div class="family-student-profile">${photo(student)}<div class="family-student-identity"><div class="text-secondary small">Student ID: <strong>${escapeHtml(student.student_id || student.student_no || "-")}</strong></div><div class="family-student-name-kh school-profile-khmer">${escapeHtml(student.full_name_kh || "-")}</div><div class="family-student-name-en">${escapeHtml(student.full_name_en || "-")}</div><div class="family-student-gender text-secondary"><i class="ti ti-gender-bigender me-1"></i>${escapeHtml(student.gender_kh || student.gender || "-")}</div></div></div><div class="family-student-dob"><div class="family-detail-label">Date of Birth</div><div class="school-profile-khmer text-secondary">${escapeHtml(khmerDateLabel(student.date_of_birth))}</div><div>${escapeHtml(dateLabel(student.date_of_birth))}</div><div class="family-student-age"><i class="ti ti-calendar-heart me-1"></i>Age: ${escapeHtml(ageLabel(student.date_of_birth))}</div></div><div class="family-student-enrollments"><div class="family-detail-label">Enrollments</div>${student.enrollments?.length ? student.enrollments.map(enrollment).join("") : '<div class="text-secondary">No enrollment records</div>'}</div><div class="family-student-actions"><button type="button" class="btn btn-primary btn-sm" data-change-student-family="${student.id}" data-current-family="${family.id}" data-student-label="${escapeHtml(`${student.full_name_en || "-"} (${student.student_id || student.student_no || "-"})`)}"><i class="ti ti-users-group icon"></i>Change Family</button></div></div>`).join("")}</div></div></td></tr>`;
};

const resetForm = () => {
    form.reset();
    fields.forEach((id) => {
        if (field(id)) field(id).value = "";
    });
    field("family_status").value = "1";
    field("family_id").value = "";
    parentRelationships.forEach((relationship) =>
        parentFields.forEach((name) => {
            const input = field(`family_${relationship}_${name}`);
            if (name === "phone") {
                setFamilyPhoneValue(relationship, "");
                return;
            }
            if (input) {
                input.value = name === "status" ? "1" : "";
                input.dispatchEvent(new Event("change", { bubbles: true }));
            }
        }),
    );
    syncStatusToggle(field("family_status"), field("familyStatusToggle"));
    document.getElementById("familyModalTitle").textContent = "New Family";
    form.querySelector("[data-alert]")?.classList.add("d-none");
    refreshFamilyFloatingFields();
};

const editFamily = (id) => {
    const family = currentRows.find((item) => item.id === id);
    if (!family) return;
    fields.forEach((name) => {
        if (field(name))
            field(name).value =
                name === "family_status"
                    ? String(family.status ?? 1)
                    : (family[name] ?? "");
    });
    parentRelationships.forEach((relationship) => {
        const member =
            family.members?.find(
                (item) => item.relationship_type === relationship,
            ) || {};
        parentFields.forEach((name) => {
            const input = field(`family_${relationship}_${name}`);
            if (name === "phone") {
                setFamilyPhoneValue(relationship, member[name] ?? "");
                return;
            }
            if (input) {
                input.value =
                    name === "status"
                        ? String(member.status ?? 1)
                        : (member[name] ?? "");
                input.dispatchEvent(new Event("change", { bubbles: true }));
            }
        });
    });
    syncStatusToggle(field("family_status"), field("familyStatusToggle"));
    document.getElementById("familyModalTitle").textContent = "Edit Family";
    refreshFamilyFloatingFields();
    modal.show();
};

const resetMemberForm = () => {
    document.getElementById("memberForm")?.reset();
    document.getElementById("family_member_id").value = "";
    document.getElementById("relationship_type").value = "mother";
    document.getElementById("member_status").value = "1";
    document.getElementById("memberFormTitle").textContent =
        "Add Family Member";
    document.querySelector("[data-member-alert]")?.classList.add("d-none");
};

const renderMembers = () => {
    const table = document.getElementById("membersTable");
    table.innerHTML = currentMembers.length
        ? currentMembers
              .map(
                  (member) =>
                      `<tr><td>${escapeHtml(member.full_name_en || "-")}</td><td><span class="badge bg-blue-lt">${escapeHtml(member.relationship_type)}</span></td><td>${escapeHtml(member.phone || "-")}</td><td>${member.is_primary_contact ? "Yes" : "No"}</td><td>${member.has_portal_access ? "Yes" : "No"}</td><td class="text-end"><button class="btn btn-primary btn-sm" data-edit-member="${member.id}">Edit</button> <button class="btn btn-danger btn-sm" data-delete-member="${member.id}">Delete</button></td></tr>`,
              )
              .join("")
        : `<tr><td colspan="6" class="text-center">No mother, father, or guardian added yet.</td></tr>`;
    table
        .querySelectorAll("[data-edit-member]")
        .forEach((button) =>
            button.addEventListener("click", () =>
                editMember(Number(button.dataset.editMember)),
            ),
        );
    table
        .querySelectorAll("[data-delete-member]")
        .forEach((button) =>
            button.addEventListener("click", () =>
                deleteMember(Number(button.dataset.deleteMember)),
            ),
        );
};

const loadMembers = async (family) => {
    activeFamily = family;
    document.getElementById("membersModalTitle").textContent =
        `Family Members — ${family.family_number}`;
    document.getElementById("membersFamilyLabel").textContent =
        family.family_name || family.family_number;
    const response = await fetch(`/families/${family.id}/members`, {
        headers: { Accept: "application/json" },
    });
    const result = await response.json();
    if (!response.ok)
        throw new Error(result.message || "Unable to load family members.");
    currentMembers = result.data || [];
    renderMembers();
    membersModal.show();
};

const editMember = (id) => {
    const member = currentMembers.find((item) => item.id === id);
    if (!member) return;
    const values = {
        family_member_id: member.id,
        member_full_name_en: member.full_name_en || "",
        member_full_name_kh: member.full_name_kh || "",
        relationship_type: member.relationship_type,
        member_phone: member.phone,
        member_email: member.email,
        member_occupation: member.occupation,
        member_status: member.status,
    };
    Object.entries(values).forEach(([idName, value]) => {
        if (field(idName)) field(idName).value = value ?? "";
    });
    document.querySelector('[name="is_primary_contact"]').checked =
        !!member.is_primary_contact;
    document.querySelector('[name="has_pickup_authorization"]').checked =
        !!member.has_pickup_authorization;
    document.querySelector('[name="has_portal_access"]').checked =
        !!member.has_portal_access;
    document.getElementById("memberFormTitle").textContent =
        "Edit Family Member";
    memberFormModal.show();
};

const deleteMember = async (id) => {
    if (
        !(
            await showConfirm(
                "Delete Family Member",
                "Remove this family member?",
                "Delete",
                "Cancel",
            )
        ).isConfirmed
    )
        return;
    const response = await fetch(`/families/${activeFamily.id}/members/${id}`, {
        method: "DELETE",
        headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf },
    });
    const result = await response.json();
    if (!response.ok)
        return showError(
            "Unable to delete member",
            result.message || "The member could not be deleted.",
        );
    currentMembers = currentMembers.filter((member) => member.id !== id);
    renderMembers();
    showSuccess("Deleted", result.message);
};

const fetchFamilies = async (page = 1, pageSize = null) => {
    const size = pageSize ?? parseInt(perPage.value, 10);
    currentPage = page;
    const params = new URLSearchParams({
        page: String(page),
        perPage: String(size),
        search: search.value,
        sort: sortBy,
        dir: sortDir,
    });
    const response = await fetch(
        `/families/fetch?${params.toString()}`,
        { headers: { Accept: "application/json" } },
    );
    const result = await response.json();
    if (!response.ok)
        throw new Error(result.message || "Unable to load families.");
    currentRows = result.data || [];
    const offset = (result.current_page - 1) * size;
    table.innerHTML = currentRows.length
        ? currentRows
              .map((family, index) => {
                  const expanded = expandedFamilyIds.has(Number(family.id));
                  const status = window.statusToggleMarkup
                      ? window.statusToggleMarkup(
                            "family",
                            family.id,
                            Boolean(family.status),
                        )
                      : "";
                  const statusMarkup =
                      status ||
                      localStatusToggleMarkup(
                          "family",
                          family.id,
                          Boolean(family.status),
                      );
                  return `<tr><td>${offset + index + 1}</td><td>${escapeHtml(family.family_number)} <button type="button" class="family-expand-toggle ${expanded ? "is-expanded" : ""}" data-expand-family="${family.id}" aria-expanded="${expanded ? "true" : "false"}" title="${expanded ? "Hide students" : "Show students"}"><i class="ti ti-chevron-down"></i><span class="visually-hidden">${expanded ? "Hide students" : "Show students"}</span></button></td><td class="family-member-summary">${memberSummary(family, "mother")}</td><td class="family-member-summary">${memberSummary(family, "father")}</td><td>${family.students_count ?? 0}</td><td>${statusMarkup}</td><td class="text-center"><button class="btn btn-primary btn-sm" data-edit="${family.id}"><i class="ti ti-pencil icon"></i>Edit</button> <button class="btn btn-danger btn-sm" data-delete="${family.id}"><i class="ti ti-trash icon"></i>Delete</button></td></tr>${expanded ? familyStudentsMarkup(family) : ""}`;
              })
              .join("")
        : `<tr><td colspan="7" class="text-center">No families found.</td></tr>`;
    renderFamilyMobileCards(currentRows, offset);
    refreshSortIcons();
    bindFamilyListActions(table);
    bindFamilyListActions(document.getElementById("familiesMobileCards"));
    renderPagination(
        result,
        "families-pagination-container",
        "families-per-page",
        fetchFamilies,
    );
    renderPageInfo(result);
};

const deleteFamily = async (id) => {
    const confirmation = await showConfirm(
        "Delete Family",
        "Families linked to students cannot be deleted.",
        "Delete",
        "Cancel",
    );
    if (!confirmation.isConfirmed) return;
    const response = await fetch(`/families/${id}`, {
        method: "DELETE",
        headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf },
    });
    const result = await response.json();
    if (!response.ok)
        return showError(
            "Unable to delete family",
            result.message || "The family could not be deleted.",
        );
    showSuccess("Deleted", result.message);
    fetchFamilies();
};

document.getElementById("newFamily")?.addEventListener("click", () => {
    resetForm();
    modal.show();
});
document.getElementById("newMember")?.addEventListener("click", () => {
    resetMemberForm();
    memberFormModal.show();
});
form?.addEventListener("submit", async (event) => {
    event.preventDefault();
    syncFamilyPhoneInputs();
    const response = await fetch("/families/save", {
        method: "POST",
        headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf },
        body: new FormData(form),
    });
    const result = await response.json();
    if (response.status === 422) {
        const duplicateFamilyNumber = result.errors?.family_number?.[0];
        if (duplicateFamilyNumber) {
            field("family_number")?.focus();
            return showError("Duplicate Family Number", duplicateFamilyNumber);
        }
        const alert = form.querySelector("[data-alert]");
        alert.textContent =
            result.message ||
            Object.values(result.errors || {})[0]?.[0] ||
            "Please correct the form.";
        alert.classList.remove("d-none");
        return;
    }
    if (!response.ok)
        return showError(
            "Unable to save family",
            result.message || "The family could not be saved.",
        );
    modal.hide();
    upsertChangeFamilyOption(result.data);
    showSuccess("Saved", result.message);
    fetchFamilies();
});
document
    .getElementById("changeStudentFamilyForm")
    ?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const response = await fetch("/families/change-student-family", {
            method: "POST",
            headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf },
            body: new FormData(event.currentTarget),
        });
        const result = await response.json();
        if (response.status === 422) {
            const alert = event.currentTarget.querySelector(
                "[data-change-family-alert]",
            );
            alert.textContent =
                result.message ||
                Object.values(result.errors || {})[0]?.[0] ||
                "Please select a new family.";
            alert.classList.remove("d-none");
            return;
        }
        if (!response.ok)
            return showError(
                "Unable to change family",
                result.message || "The student family could not be changed.",
            );
        changeStudentFamilyModal.hide();
        changeFamilyStudent = null;
        expandedFamilyIds.clear();
        showSuccess("Family Changed", result.message);
        fetchFamilies(currentPage, parseInt(perPage.value, 10));
    });
document
    .getElementById("memberForm")
    ?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const memberData = new FormData(event.currentTarget);
        [
            "is_primary_contact",
            "has_pickup_authorization",
            "has_portal_access",
        ].forEach((name) =>
            memberData.set(
                name,
                event.currentTarget.querySelector(`[name="${name}"]`).checked
                    ? "1"
                    : "0",
            ),
        );
        const response = await fetch(
            `/families/${activeFamily.id}/members/save`,
            {
                method: "POST",
                headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf },
                body: memberData,
            },
        );
        const result = await response.json();
        if (response.status === 422) {
            const alert = event.currentTarget.querySelector(
                "[data-member-alert]",
            );
            alert.textContent =
                result.message ||
                Object.values(result.errors || {})[0]?.[0] ||
                "Please correct the form.";
            alert.classList.remove("d-none");
            return;
        }
        if (!response.ok)
            return showError(
                "Unable to save member",
                result.message || "The family member could not be saved.",
            );
        memberFormModal.hide();
        await loadMembers(activeFamily);
        showSuccess("Saved", result.message);
    });
perPage?.addEventListener("change", () =>
    fetchFamilies(1, parseInt(perPage.value, 10)),
);
search?.addEventListener("input", () =>
    fetchFamilies(1, parseInt(perPage.value, 10)),
);
document.querySelectorAll("[data-family-sort]").forEach((button) => {
    button.addEventListener("click", () => {
        const nextSort = button.dataset.familySort;
        if (sortBy === nextSort) sortDir = sortDir === "asc" ? "desc" : "asc";
        else {
            sortBy = nextSort;
            sortDir = "asc";
        }
        expandedFamilyIds.clear();
        refreshSortIcons();
        fetchFamilies(1, parseInt(perPage.value, 10));
    });
});
document.addEventListener("status:updated", () =>
    fetchFamilies(currentPage, parseInt(perPage.value, 10)),
);
setupFamilyComboboxes();
setupChangeFamilyCombobox();
refreshSortIcons();
fetchFamilies().catch((error) =>
    showError("Unable to load families", error.message),
);
