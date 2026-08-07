import { renderPagination, renderPageInfo } from "./helpers/pagination.js";
import { showSuccess, showConfirm, showError } from "./helpers/sweet-alert2.js";

const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const form = document.getElementById("educationLevelForm");
const modal = new bootstrap.Modal(document.getElementById("educationLevelModal"));
const table = document.getElementById("educationLevelsTable");
const search = document.getElementById("education-levels-search");
const perPage = document.getElementById("education-levels-per-page");
const submit = document.getElementById("educationLevelSubmit");
const title = document.getElementById("educationLevelModalTitle");
let rows = [];

const clearErrors = () => {
    form.querySelectorAll(".is-invalid").forEach((x) => x.classList.remove("is-invalid"));
    form.querySelectorAll("[data-error-for]").forEach((x) => { x.textContent = ""; x.classList.remove("d-block"); });
    const alert = form.querySelector("[data-form-alert]"); alert.textContent = ""; alert.classList.add("d-none");
};
const showErrors = (items, message) => {
    const alert = form.querySelector("[data-form-alert]"); alert.textContent = message || "Please correct the errors below."; alert.classList.remove("d-none");
    Object.entries(items || {}).forEach(([field, messages]) => { const input = document.getElementById(field), error = form.querySelector(`[data-error-for="${field}"]`); input?.classList.add("is-invalid"); if (error) { error.textContent = messages[0]; error.classList.add("d-block"); } });
};
const openCreate = () => { form.reset(); clearErrors(); document.getElementById("education_level_id").value = ""; title.textContent = "Create Education Level"; submit.textContent = "Create"; modal.show(); };
const openEdit = (id) => { const row = rows.find((item) => item.id === id); if (!row) return; openCreate(); document.getElementById("education_level_id").value = row.id; ["level_name", "level_short_name", "level_order", "description"].forEach((key) => { document.getElementById(key).value = row[key] ?? ""; }); document.getElementById("status").value = row.status ? "1" : "0"; title.textContent = "Edit Education Level"; submit.textContent = "Update"; };
form.addEventListener("submit", async (event) => { event.preventDefault(); clearErrors(); const required = ["level_name", "level_short_name"], missing = Object.fromEntries(required.filter((key) => !document.getElementById(key).value.trim()).map((key) => [key, [`${key === "level_name" ? "Level name" : "Short name"} is required.`]])); if (Object.keys(missing).length) { showErrors(missing, "Please complete all required fields."); return; } submit.disabled = true; try { const response = await fetch("/education-levels/save", { method: "POST", headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf }, body: new FormData(form) }); const result = await response.json(); if (response.status === 422) { showErrors(result.errors, result.message); return; } if (!response.ok) throw Error(result.message); modal.hide(); showSuccess("Saved", result.message); fetchRows(); } catch (error) { showErrors({}, error.message); } finally { submit.disabled = false; submit.textContent = document.getElementById("education_level_id").value ? "Update" : "Create"; } });
const remove = async (id) => { if (!(await showConfirm("Delete Education Level", "Are you sure?", "Delete", "Cancel")).isConfirmed) return; const response = await fetch(`/education-levels/delete/${id}`, { method: "DELETE", headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf } }); const result = await response.json(); response.ok ? (showSuccess("Deleted", result.message), fetchRows()) : showError("Error", result.message); };
async function fetchRows(page = 1) { const response = await fetch(`/education-levels/fetch?page=${page}&perPage=${perPage.value}&search=${encodeURIComponent(search.value)}`), result = await response.json(); rows = result.data; table.innerHTML = rows.length ? rows.map((row) => `<tr><td>${row.level_name}</td><td>${row.level_short_name}</td><td>${row.level_order ?? ""}</td><td>${row.description ?? ""}</td><td>${row.status ? "<span class='badge bg-success-lt'>Active</span>" : "<span class='badge bg-danger-lt'>Inactive</span>"}</td><td class="text-center"><button class="btn btn-primary btn-sm" onclick="educationLevelsPage.edit(${row.id})">Edit</button> <button class="btn btn-danger btn-sm" onclick="educationLevelsPage.remove(${row.id})">Delete</button></td></tr>`).join("") : `<tr><td colspan="6" class="text-center">No education levels found.</td></tr>`; renderPagination(result, "education-levels-pagination-container", "education-levels-per-page", fetchRows); renderPageInfo(result); }
document.getElementById("newEducationLevel").onclick = openCreate; perPage.onchange = () => fetchRows(); search.onkeyup = () => fetchRows(); fetchRows(); window.educationLevelsPage = { edit: openEdit, remove };
