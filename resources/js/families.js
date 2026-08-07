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
const escapeHtml = (value = "") => String(value).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#39;");
let currentRows = [];
let activeFamily = null;
let currentMembers = [];

const familyMember = (family, relationship) => family.members?.find((member) => member.relationship_type === relationship);
const memberSummary = (family, relationship) => {
    const member = familyMember(family, relationship);
    if (!member) return '<span class="text-secondary">Not provided</span>';
    const nameEn = member.name_en || [member.first_name_en, member.last_name_en].filter(Boolean).join(" ");
    const details = [
        nameEn,
        member.name_kh,
        member.occupation_en || member.occupation,
        member.occupation_kh,
        member.nationality_en,
        member.nationality_kh,
        member.phone,
        member.workplace,
    ].filter(Boolean);
    return details.map((value, index) => index === 0 ? '<strong>' + escapeHtml(value) + '</strong>' : '<span class="d-block text-secondary small">' + escapeHtml(value) + '</span>').join("");
};

const resetForm = () => {
    form.reset();
    fields.forEach((id) => { if (field(id)) field(id).value = ""; });
    field("family_status").value = "1";
    field("family_id").value = "";
    document.getElementById("familyModalTitle").textContent = "New Family";
    form.querySelector("[data-alert]")?.classList.add("d-none");
};

const editFamily = (id) => {
    const family = currentRows.find((item) => item.id === id);
    if (!family) return;
    fields.forEach((name) => { if (field(name)) field(name).value = name === "family_status" ? String(family.status ?? 1) : (family[name] ?? ""); });
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
    table.innerHTML = currentMembers.length ? currentMembers.map((member) => `<tr><td>${escapeHtml(member.name_en || [member.first_name_en, member.last_name_en].filter(Boolean).join(" "))}</td><td><span class="badge bg-blue-lt">${escapeHtml(member.relationship_type)}</span></td><td>${escapeHtml(member.phone || "-")}</td><td>${member.is_primary_contact ? "Yes" : "No"}</td><td>${member.has_portal_access ? "Yes" : "No"}</td><td class="text-end"><button class="btn btn-primary btn-sm" data-edit-member="${member.id}">Edit</button> <button class="btn btn-danger btn-sm" data-delete-member="${member.id}">Delete</button></td></tr>`).join("") : `<tr><td colspan="6" class="text-center">No mother, father, or guardian added yet.</td></tr>`;
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
    const values = { family_member_id: member.id, member_full_name_en: member.full_name_en || member.name_en || [member.first_name_en, member.last_name_en].filter(Boolean).join(" "), member_full_name_kh: member.full_name_kh || member.name_kh || [member.first_name_kh, member.last_name_kh].filter(Boolean).join(" "), relationship_type: member.relationship_type, member_phone: member.phone, member_email: member.email, member_occupation: member.occupation, member_status: member.status };
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
    table.innerHTML = currentRows.length ? currentRows.map((family) => `<tr><td>${escapeHtml(family.family_number)}</td><td>${escapeHtml(family.family_name || "-")}</td><td class="family-member-summary">${memberSummary(family, "mother")}</td><td class="family-member-summary">${memberSummary(family, "father")}</td><td>${escapeHtml(family.primary_phone || "-")}</td><td>${escapeHtml(family.primary_email || "-")}</td><td>${family.students_count ?? 0}</td><td>${family.status ? "<span class='badge bg-success-lt'>Active</span>" : "<span class='badge bg-danger-lt'>Inactive</span>"}</td><td class="text-center"><button class="btn btn-info btn-sm" data-members="${family.id}"><i class="ti ti-users icon"></i>Members</button> <button class="btn btn-primary btn-sm" data-edit="${family.id}"><i class="ti ti-pencil icon"></i>Edit</button> <button class="btn btn-danger btn-sm" data-delete="${family.id}"><i class="ti ti-trash icon"></i>Delete</button></td></tr>`).join("") : `<tr><td colspan="9" class="text-center">No families found.</td></tr>`;
    table.querySelectorAll("[data-members]").forEach((button) => button.addEventListener("click", () => loadMembers(currentRows.find((family) => family.id === Number(button.dataset.members)))));
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
