import { renderPagination, renderPageInfo } from "./helpers/pagination.js";
import { showSuccess, showError, showConfirm } from "./helpers/sweet-alert2.js";

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content");
const form = document.getElementById("classForm");
const modalElement = document.getElementById("classModal");
const modal = modalElement ? new bootstrap.Modal(modalElement) : null;
const table = document.getElementById("classesTable");
const search = document.getElementById("classes-search");
const perPageInput = document.getElementById("classes-per-page");
const submitButton = document.getElementById("classSubmitBtn");
const modalTitle = document.getElementById("classModalTitle");
let classes = [];
let sortBy = "class_order";
let sortDir = "asc";

const clearErrors = () => {
    form?.querySelectorAll(".is-invalid").forEach((field) => field.classList.remove("is-invalid"));
    form?.querySelectorAll("[data-error-for]").forEach((field) => { field.textContent = ""; field.classList.remove("d-block"); });
    const alert = form?.querySelector("[data-form-alert]");
    if (alert) { alert.textContent = ""; alert.classList.add("d-none"); }
};
const showErrors = (errors, message) => {
    const alert = form?.querySelector("[data-form-alert]");
    if (alert) { alert.textContent = message || "Please correct the errors below."; alert.classList.remove("d-none"); }
    Object.entries(errors || {}).forEach(([field, messages]) => {
        document.getElementById(field)?.classList.add("is-invalid");
        const error = form?.querySelector(`[data-error-for="${field}"]`);
        if (error) { error.textContent = messages[0]; error.classList.add("d-block"); }
    });
};
const openCreateModal = () => {
    form?.reset(); clearErrors(); document.getElementById("class_id").value = "";
    modalTitle.textContent = "Create Class"; submitButton.textContent = "Create"; modal?.show();
};
const openEditModal = (id) => {
    const item = classes.find((row) => row.id === id);
    if (!item || !form) return;
    form.reset(); clearErrors();
    document.getElementById("class_id").value = item.id;
    document.getElementById("class_name").value = item.class_name ?? "";
    document.getElementById("class_order").value = item.class_order ?? "";
    document.getElementById("status").value = String(item.status ?? 1);
    modalTitle.textContent = "Edit Class"; submitButton.textContent = "Update"; modal?.show();
};
form?.addEventListener("submit", async (event) => {
    event.preventDefault(); clearErrors(); submitButton.disabled = true; submitButton.textContent = "Saving...";
    const className = document.getElementById("class_name")?.value || "";
    if (!className.trim()) {
        showErrors({ class_name: ["Class name is required."] }, "Please complete all required fields.");
        submitButton.disabled = false;
        submitButton.textContent = document.getElementById("class_id").value ? "Update" : "Create";
        return;
    }
    try {
        const response = await fetch("/classes/save", { method: "POST", headers: { Accept: "application/json", "X-CSRF-TOKEN": csrfToken }, body: new FormData(form) });
        const responseText = await response.text();
        let result;
        try { result = JSON.parse(responseText); } catch {
            throw new Error(`Unable to save class. Class '${className}' is already existed.`);
        }
        if (response.status === 422) { showErrors(result.errors, result.message); return; }
        if (!response.ok || result.status !== "success") throw new Error(result.message || "Unable to save class.");
        modal?.hide(); showSuccess("Saved", result.message); fetchClasses();
    } catch (error) { showErrors({}, error.message); }
    finally { submitButton.disabled = false; submitButton.textContent = document.getElementById("class_id").value ? "Update" : "Create"; }
});
const deleteClass = async (id) => {
    if (!(await showConfirm("Delete Class", "Are you sure you want to delete this class?", "Delete", "Cancel")).isConfirmed) return;
    try {
        const response = await fetch(`/classes/delete/${id}`, { method: "DELETE", headers: { Accept: "application/json", "X-CSRF-TOKEN": csrfToken } });
        const result = await response.json(); if (!response.ok) throw new Error(result.message || "Unable to delete class.");
        showSuccess("Deleted", result.message); fetchClasses();
    } catch (error) { showError("Error", error.message); }
};
async function fetchClasses(page = 1, perPage = null) {
    const size = perPage ?? parseInt(perPageInput.value);
    try {
        const response = await fetch(`/classes/fetch?page=${page}&perPage=${size}&search=${encodeURIComponent(search.value)}&sortBy=${sortBy}&sortDir=${sortDir}`);
        const result = await response.json(); if (!response.ok) throw new Error(result.message || "Unable to fetch classes.");
        classes = result.data; const offset = (result.current_page - 1) * size;
        table.innerHTML = classes.length ? classes.map((item, index) => `<tr><td>${offset + index + 1}</td><td>${item.class_name}</td><td>${item.class_order ?? ""}</td><td>${item.status ? "<span class='badge bg-success-lt'>Active</span>" : "<span class='badge bg-danger-lt'>Inactive</span>"}</td><td class="text-center"><button onclick="classesPage.openEditModal(${item.id})" class="btn btn-primary btn-sm"><i class="ti ti-pencil icon"></i>Edit</button> <button onclick="classesPage.deleteClass(${item.id})" class="btn btn-danger btn-sm"><i class="ti ti-trash icon"></i>Delete</button></td></tr>`).join("") : `<tr><td colspan="5" class="text-center">No classes found.</td></tr>`;
        renderPagination(result, "classes-pagination-container", "classes-per-page", fetchClasses); renderPageInfo(result);
    } catch (error) { console.error(error); }
}
document.getElementById("btnNewClass")?.addEventListener("click", openCreateModal);
document.getElementById("btnNewClassMobile")?.addEventListener("click", openCreateModal);
perPageInput?.addEventListener("change", () => fetchClasses(1, parseInt(perPageInput.value)));
search?.addEventListener("keyup", () => fetchClasses(1, parseInt(perPageInput.value)));
document.querySelectorAll("[data-sort]").forEach((header) => header.addEventListener("click", () => {
    const selectedSort = header.dataset.sort;
    sortDir = sortBy === selectedSort && sortDir === "asc" ? "desc" : "asc";
    sortBy = selectedSort;
    fetchClasses(1, parseInt(perPageInput.value));
}));
fetchClasses();
window.classesPage = { openCreateModal, openEditModal, deleteClass, fetchClasses };
