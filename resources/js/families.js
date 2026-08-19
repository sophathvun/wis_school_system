import { renderPagination, renderPageInfo } from "./helpers/pagination.js";
import { showSuccess, showConfirm, showError } from "./helpers/sweet-alert2.js";

const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const table = document.getElementById("familiesTable");
const search = document.getElementById("families-search");
const perPage = document.getElementById("families-per-page");
const form = document.getElementById("familyForm");
const modal = new bootstrap.Modal(document.getElementById("familyModal"));
const membersModal = new bootstrap.Modal(document.getElementById("membersModal"));
const memberFormModal = new bootstrap.Modal(document.getElementById("memberFormModal"));
const fields = ["family_id", "family_number", "family_name", "family_name_kh", "primary_phone", "primary_email", "address", "family_status"];
const field = (id) => document.getElementById(id);
const parentRelationships = ["mother", "father", "guardian"];
const parentFields = ["full_name_en", "full_name_kh", "occupation_en", "occupation_kh", "nationality_en", "nationality_kh", "phone", "workplace", "email"];
const syncStatusToggle = (select, toggle) => { if (!select || !toggle) return; const active = String(select.value) === "1"; toggle.classList.toggle("is-active", active); toggle.setAttribute("aria-pressed", active ? "true" : "false"); const label = toggle.querySelector(".status-toggle-label"); if (label) label.textContent = active ? "ON" : "OFF"; };
const bindStatusToggle = (selectId, toggleId) => { const select = field(selectId); const toggle = field(toggleId); toggle?.addEventListener("click", () => { select.value = select.value === "1" ? "0" : "1"; syncStatusToggle(select, toggle); }); syncStatusToggle(select, toggle); };
bindStatusToggle("family_status", "familyStatusToggle");
const escapeHtml = (value = "") => String(value).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#39;");
let currentRows = [];
let activeFamily = null;
let currentMembers = [];
const expandedFamilyIds = new Set();

const familyMember = (family, relationship) => family.members?.find((member) => member.relationship_type === relationship);
const memberSummary = (family, relationship) => {
    const member = familyMember(family, relationship);
    if (!member) return '<span class="text-secondary">Not provided</span>';
    const nameEn = member.full_name_en || "";
    const nameKh = member.full_name_kh || "";
    return `<span class="school-profile-khmer text-secondary small">${escapeHtml(nameKh || "-")}</span><strong>${escapeHtml(nameEn || "-")}</strong>${member.phone ? `<span class="family-phone text-secondary small"><i class="ti ti-phone"></i>${escapeHtml(member.phone)}</span>` : ""}`;
};
const familyStudentsMarkup = (family) => {
    const students = family.students || [];
    if (!students.length) return `<tr class="family-expanded-row"><td colspan="7"><div class="family-expanded-card text-secondary">No students linked to this family.</div></td></tr>`;
    const dateLabel = (value) => { if (!value) return "-"; const date = new Date(`${value}T00:00:00`); if (Number.isNaN(date.getTime())) return value; return `${String(date.getDate()).padStart(2, "0")}-${date.toLocaleString("en-US", { month: "short" })}-${date.getFullYear()}`; };
    const khmerDateLabel = (value) => { if (!value) return "-"; const date = new Date(`${value}T00:00:00`); if (Number.isNaN(date.getTime())) return value; const digits = "០១២៣៤៥៦៧៨៩"; const khmerNumber = (number) => String(number).split("").map((digit) => digits[Number(digit)] ?? digit).join(""); const months = ["មករា", "កុម្ភៈ", "មីនា", "មេសា", "ឧសភា", "មិថុនា", "កក្កដា", "សីហា", "កញ្ញា", "តុលា", "វិច្ឆិកា", "ធ្នូ"]; return `${khmerNumber(String(date.getDate()).padStart(2, "0"))} ${months[date.getMonth()]} ${khmerNumber(date.getFullYear())}`; };
    const ageLabel = (value) => { if (!value) return "-"; const birth = new Date(`${value}T00:00:00`); if (Number.isNaN(birth.getTime())) return "-"; const today = new Date(); today.setHours(0, 0, 0, 0); if (birth > today) return "-"; let years = today.getFullYear() - birth.getFullYear(); let months = today.getMonth() - birth.getMonth(); let days = today.getDate() - birth.getDate(); if (days < 0) { months -= 1; const previousMonth = new Date(today.getFullYear(), today.getMonth(), 0); days += previousMonth.getDate(); } if (months < 0) { years -= 1; months += 12; } const unit = (number, singular) => `${number} ${singular}${number === 1 ? "" : "s"}`; return `${unit(years, "year")} ${unit(months, "month")}, ${unit(days, "day")}`; };
    const photo = (student) => student.photo_path ? `<img src="/storage/${encodeURIComponent(student.photo_path).replace(/%2F/g, "/")}" alt="Student photo" class="family-student-photo family-student-photo-large">` : `<span class="family-student-photo family-student-photo-large family-student-photo-placeholder"><i class="ti ti-user"></i></span>`;
    const enrollment = (item) => {
        const lifecycleStatus = item.academic_year?.lifecycle_status;
        const storedStatus = String(item.enrollment_status || "");
        const terminalStatus = ["withdrawn", "transferred", "graduated", "cancelled"].includes(storedStatus) ? storedStatus : null;
        const status = String(terminalStatus || (lifecycleStatus === "started" ? "active" : lifecycleStatus === "finished" ? "completed" : (storedStatus || (item.status ? "active" : "inactive"))));
        const statusClass = status === "active" ? "success" : status === "completed" || status === "graduated" ? "blue" : status === "withdrawn" ? "danger" : "secondary";
        const statusLabel = status.replace(/_/g, " ").replace(/\b\w/g, (letter) => letter.toUpperCase());
        return `<div class="family-enrollment-item"><div class="family-enrollment-year">${escapeHtml(item.academic_year?.academic_year || "-")}</div><div class="family-enrollment-campus">${escapeHtml(item.campus?.campus_name_en || "-")}</div><div class="family-enrollment-grade">${escapeHtml(`${item.grade?.grade || "-"}${item.school_class?.class_name || ""}`)}</div><div class="family-enrollment-group">${escapeHtml(item.session?.session_short_name || "-")}</div><div class="family-enrollment-status"><span class="badge bg-${statusClass}-lt">${escapeHtml(statusLabel)}</span></div></div>`;
    };
    return `<tr class="family-expanded-row"><td colspan="7"><div class="family-expanded-card"><div class="d-flex justify-content-between align-items-center mb-3"><div><div class="fw-bold fs-3">Students in this family</div><div class="text-secondary small">Student information and enrollment history</div></div><span class="badge bg-blue-lt">${students.length} Student${students.length === 1 ? "" : "s"}</span></div><div class="family-student-list">${students.map((student) => `<div class="family-student-card"><div class="family-student-profile">${photo(student)}<div class="family-student-identity"><div class="text-secondary small">Student ID: <strong>${escapeHtml(student.student_id || student.student_no || "-")}</strong></div><div class="family-student-name-kh school-profile-khmer">${escapeHtml(student.full_name_kh || "-")}</div><div class="family-student-name-en">${escapeHtml(student.full_name_en || "-")}</div><div class="family-student-gender text-secondary"><i class="ti ti-gender-bigender me-1"></i>${escapeHtml(student.gender_kh || student.gender || "-")}</div></div></div><div class="family-student-dob"><div class="family-detail-label">Date of Birth</div><div class="school-profile-khmer text-secondary">${escapeHtml(khmerDateLabel(student.date_of_birth))}</div><div>${escapeHtml(dateLabel(student.date_of_birth))}</div><div class="family-student-age"><i class="ti ti-calendar-heart me-1"></i>Age: ${escapeHtml(ageLabel(student.date_of_birth))}</div></div><div class="family-student-enrollments"><div class="family-detail-label">Enrollments</div>${student.enrollments?.length ? `<div class="family-enrollment-header"><div>Academic Year</div><div>Campus</div><div>Grade / Class</div><div>Group</div><div>Status</div></div>${student.enrollments.map(enrollment).join("")}` : '<div class="text-secondary">No enrollment records</div>'}</div><div class="family-student-status"><div class="family-detail-label">Status</div><span class="badge ${student.status ? "bg-success-lt text-success" : "bg-secondary-lt text-secondary"}">${student.status ? "Active" : "Inactive"}</span></div></div>`).join("")}</div></div></td></tr>`;
};

const resetForm = () => {
    form.reset();
    fields.forEach((id) => { if (field(id)) field(id).value = ""; });
    field("family_status").value = "1";
    field("family_id").value = "";
    parentRelationships.forEach((relationship) => parentFields.forEach((name) => { const input = field(`family_${relationship}_${name}`); if (input) input.value = name === "status" ? "1" : ""; }));
    syncStatusToggle(field("family_status"), field("familyStatusToggle"));
    document.getElementById("familyModalTitle").textContent = "New Family";
    form.querySelector("[data-alert]")?.classList.add("d-none");
};

const editFamily = (id) => {
    const family = currentRows.find((item) => item.id === id);
    if (!family) return;
    fields.forEach((name) => { if (field(name)) field(name).value = name === "family_status" ? String(family.status ?? 1) : (family[name] ?? ""); });
    parentRelationships.forEach((relationship) => { const member = family.members?.find((item) => item.relationship_type === relationship) || {}; parentFields.forEach((name) => { const input = field(`family_${relationship}_${name}`); if (input) input.value = name === "status" ? String(member.status ?? 1) : (member[name] ?? ""); }); });
    syncStatusToggle(field("family_status"), field("familyStatusToggle"));
    document.getElementById("familyModalTitle").textContent = "Edit Family";
    modal.show();
};

const resetMemberForm = () => {
    document.getElementById("memberForm")?.reset();
    document.getElementById("family_member_id").value = "";
    document.getElementById("relationship_type").value = "mother";
    document.getElementById("member_status").value = "1";
    document.getElementById("memberFormTitle").textContent = "Add Family Member";
    document.querySelector("[data-member-alert]")?.classList.add("d-none");
};

const renderMembers = () => {
    const table = document.getElementById("membersTable");
    table.innerHTML = currentMembers.length ? currentMembers.map((member) => `<tr><td>${escapeHtml(member.full_name_en || "-")}</td><td><span class="badge bg-blue-lt">${escapeHtml(member.relationship_type)}</span></td><td>${escapeHtml(member.phone || "-")}</td><td>${member.is_primary_contact ? "Yes" : "No"}</td><td>${member.has_portal_access ? "Yes" : "No"}</td><td class="text-end"><button class="btn btn-primary btn-sm" data-edit-member="${member.id}">Edit</button> <button class="btn btn-danger btn-sm" data-delete-member="${member.id}">Delete</button></td></tr>`).join("") : `<tr><td colspan="6" class="text-center">No mother, father, or guardian added yet.</td></tr>`;
    table.querySelectorAll("[data-edit-member]").forEach((button) => button.addEventListener("click", () => editMember(Number(button.dataset.editMember))));
    table.querySelectorAll("[data-delete-member]").forEach((button) => button.addEventListener("click", () => deleteMember(Number(button.dataset.deleteMember))));
};

const loadMembers = async (family) => {
    activeFamily = family;
    document.getElementById("membersModalTitle").textContent = `Family Members — ${family.family_number}`;
    document.getElementById("membersFamilyLabel").textContent = family.family_name || family.family_number;
    const response = await fetch(`/families/${family.id}/members`, { headers: { Accept: "application/json" } });
    const result = await response.json();
    if (!response.ok) throw new Error(result.message || "Unable to load family members.");
    currentMembers = result.data || [];
    renderMembers();
    membersModal.show();
};

const editMember = (id) => {
    const member = currentMembers.find((item) => item.id === id);
    if (!member) return;
    const values = { family_member_id: member.id, member_full_name_en: member.full_name_en || "", member_full_name_kh: member.full_name_kh || "", relationship_type: member.relationship_type, member_phone: member.phone, member_email: member.email, member_occupation: member.occupation, member_status: member.status };
    Object.entries(values).forEach(([idName, value]) => { if (field(idName)) field(idName).value = value ?? ""; });
    document.querySelector('[name="is_primary_contact"]').checked = !!member.is_primary_contact;
    document.querySelector('[name="has_pickup_authorization"]').checked = !!member.has_pickup_authorization;
    document.querySelector('[name="has_portal_access"]').checked = !!member.has_portal_access;
    document.getElementById("memberFormTitle").textContent = "Edit Family Member";
    memberFormModal.show();
};

const deleteMember = async (id) => {
    if (!(await showConfirm("Delete Family Member", "Remove this family member?", "Delete", "Cancel")).isConfirmed) return;
    const response = await fetch(`/families/${activeFamily.id}/members/${id}`, { method: "DELETE", headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf } });
    const result = await response.json();
    if (!response.ok) return showError("Unable to delete member", result.message || "The member could not be deleted.");
    currentMembers = currentMembers.filter((member) => member.id !== id); renderMembers(); showSuccess("Deleted", result.message);
};

const fetchFamilies = async (page = 1, pageSize = null) => {
    const size = pageSize ?? parseInt(perPage.value, 10);
    const response = await fetch(`/families/fetch?page=${page}&perPage=${size}&search=${encodeURIComponent(search.value)}`, { headers: { Accept: "application/json" } });
    const result = await response.json();
    if (!response.ok) throw new Error(result.message || "Unable to load families.");
    currentRows = result.data || [];
    const offset = (result.current_page - 1) * size;
    table.innerHTML = currentRows.length ? currentRows.map((family, index) => { const expanded = expandedFamilyIds.has(Number(family.id)); const status = window.statusToggleMarkup ? window.statusToggleMarkup("family", family.id, Boolean(family.status)) : (family.status ? "<span class='badge bg-success-lt'>Active</span>" : "<span class='badge bg-danger-lt'>Inactive</span>"); return `<tr><td>${offset + index + 1}</td><td>${escapeHtml(family.family_number)} <button type="button" class="family-expand-toggle ${expanded ? "is-expanded" : ""}" data-expand-family="${family.id}" aria-expanded="${expanded ? "true" : "false"}" title="${expanded ? "Hide students" : "Show students"}"><i class="ti ti-chevron-down"></i><span class="visually-hidden">${expanded ? "Hide students" : "Show students"}</span></button></td><td class="family-member-summary">${memberSummary(family, "mother")}</td><td class="family-member-summary">${memberSummary(family, "father")}</td><td>${family.students_count ?? 0}</td><td>${status}</td><td class="text-center"><button class="btn btn-primary btn-sm" data-edit="${family.id}"><i class="ti ti-pencil icon"></i>Edit</button> <button class="btn btn-danger btn-sm" data-delete="${family.id}"><i class="ti ti-trash icon"></i>Delete</button></td></tr>${expanded ? familyStudentsMarkup(family) : ""}`; }).join("") : `<tr><td colspan="7" class="text-center">No families found.</td></tr>`;
    table.querySelectorAll("[data-expand-family]").forEach((button) => button.addEventListener("click", () => { const id = Number(button.dataset.expandFamily); if (expandedFamilyIds.has(id)) expandedFamilyIds.delete(id); else { expandedFamilyIds.clear(); expandedFamilyIds.add(id); } fetchFamilies(result.current_page, size); }));
    table.querySelectorAll("[data-edit]").forEach((button) => button.addEventListener("click", () => editFamily(Number(button.dataset.edit))));
    table.querySelectorAll("[data-delete]").forEach((button) => button.addEventListener("click", () => deleteFamily(Number(button.dataset.delete))));
    renderPagination(result, "families-pagination-container", "families-per-page", fetchFamilies);
    renderPageInfo(result);
};

const deleteFamily = async (id) => {
    const confirmation = await showConfirm("Delete Family", "Families linked to students cannot be deleted.", "Delete", "Cancel");
    if (!confirmation.isConfirmed) return;
    const response = await fetch(`/families/${id}`, { method: "DELETE", headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf } });
    const result = await response.json();
    if (!response.ok) return showError("Unable to delete family", result.message || "The family could not be deleted.");
    showSuccess("Deleted", result.message); fetchFamilies();
};

document.getElementById("newFamily")?.addEventListener("click", () => { resetForm(); modal.show(); });
document.getElementById("newMember")?.addEventListener("click", () => { resetMemberForm(); memberFormModal.show(); });
form?.addEventListener("submit", async (event) => {
    event.preventDefault();
    const response = await fetch("/families/save", { method: "POST", headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf }, body: new FormData(form) });
    const result = await response.json();
    if (response.status === 422) { const alert = form.querySelector("[data-alert]"); alert.textContent = result.message || Object.values(result.errors || {})[0]?.[0] || "Please correct the form."; alert.classList.remove("d-none"); return; }
    if (!response.ok) return showError("Unable to save family", result.message || "The family could not be saved.");
    modal.hide(); showSuccess("Saved", result.message); fetchFamilies();
});
document.getElementById("memberForm")?.addEventListener("submit", async (event) => {
    event.preventDefault();
    const memberData = new FormData(event.currentTarget);
    ["is_primary_contact", "has_pickup_authorization", "has_portal_access"].forEach((name) => memberData.set(name, event.currentTarget.querySelector(`[name="${name}"]`).checked ? "1" : "0"));
    const response = await fetch(`/families/${activeFamily.id}/members/save`, { method: "POST", headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf }, body: memberData });
    const result = await response.json();
    if (response.status === 422) { const alert = event.currentTarget.querySelector("[data-member-alert]"); alert.textContent = result.message || Object.values(result.errors || {})[0]?.[0] || "Please correct the form."; alert.classList.remove("d-none"); return; }
    if (!response.ok) return showError("Unable to save member", result.message || "The family member could not be saved.");
    memberFormModal.hide(); await loadMembers(activeFamily); showSuccess("Saved", result.message);
});
perPage?.addEventListener("change", () => fetchFamilies(1, parseInt(perPage.value, 10)));
search?.addEventListener("input", () => fetchFamilies(1, parseInt(perPage.value, 10)));
fetchFamilies().catch((error) => showError("Unable to load families", error.message));
