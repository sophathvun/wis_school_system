import { renderPagination, renderPageInfo } from "./helpers/pagination.js";
import { showSuccess, showError, showConfirm } from "./helpers/sweet-alert2.js";

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content");
const modalElement = document.getElementById("gradeModal");
const form = document.getElementById("gradeForm");
const modal = modalElement ? new bootstrap.Modal(modalElement) : null;
const table = document.getElementById("gradesTable");
const search = document.getElementById("grades-search");
const perPageInput = document.getElementById("grades-per-page");
const submitButton = document.getElementById("gradeSubmitBtn");
const modalTitle = document.getElementById("gradeModalTitle");
let grades = [];
let sortBy = "grade_order";
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
    form?.reset(); clearErrors();
    document.getElementById("grade_id").value = "";
    modalTitle.textContent = "Create Grade"; submitButton.textContent = "Create"; modal?.show();
};

const openEditModal = (id) => {
    const grade = grades.find((item) => item.id === id);
    if (!grade || !form) return;
    form.reset(); clearErrors();
    document.getElementById("grade_id").value = grade.id;
    document.getElementById("grade").value = grade.grade ?? "";
    document.getElementById("grade_short_name").value = grade.grade_short_name ?? "";
    document.getElementById("grade_order").value = grade.grade_order ?? "";
    document.getElementById("description").value = grade.description ?? "";
    document.getElementById("status").value = String(grade.status ?? 1);
    modalTitle.textContent = "Edit Grade"; submitButton.textContent = "Update"; modal?.show();
};

form?.addEventListener("submit", async (event) => {
    event.preventDefault(); clearErrors(); submitButton.disabled = true; submitButton.textContent = "Saving...";
    const requiredFields = { grade: "Grade is required.", grade_short_name: "Short name is required." };
    const clientErrors = Object.fromEntries(Object.entries(requiredFields).filter(([field]) => !document.getElementById(field)?.value.trim()).map(([field, message]) => [field, [message]]));
    if (Object.keys(clientErrors).length) {
        showErrors(clientErrors, "Please complete all required fields.");
        submitButton.disabled = false;
        submitButton.textContent = document.getElementById("grade_id").value ? "Update" : "Create";
        return;
    }
    try {
        const response = await fetch("/grades/save", { method: "POST", headers: { Accept: "application/json", "X-CSRF-TOKEN": csrfToken }, body: new FormData(form) });
        const responseText = await response.text();
        let result;
        try { result = JSON.parse(responseText); } catch { throw new Error("Unable to save grade. Please try again."); }
        if (response.status === 422) {
            const isDuplicate = result.message?.startsWith("Unable to save Grade.");
            showErrors(isDuplicate ? {} : result.errors, result.message);
            return;
        }
        if (!response.ok || result.status !== "success") throw new Error(result.message || "Unable to save grade.");
        modal?.hide(); showSuccess("Saved", result.message); fetchGrades();
    } catch (error) {
        showErrors({}, error.message || "Unable to save grade.");
    } finally {
        submitButton.disabled = false;
        submitButton.textContent = document.getElementById("grade_id").value ? "Update" : "Create";
    }
});

const deleteGrade = async (id) => {
    if (!(await showConfirm("Delete Grade", "Are you sure you want to delete this grade?", "Delete", "Cancel")).isConfirmed) return;
    try {
        const response = await fetch(`/grades/delete/${id}`, { method: "DELETE", headers: { Accept: "application/json", "X-CSRF-TOKEN": csrfToken } });
        const result = await response.json();
        if (!response.ok) throw new Error(result.message || "Unable to delete grade.");
        showSuccess("Deleted", result.message); fetchGrades();
    } catch (error) { showError("Error", error.message); }
};

async function fetchGrades(page = 1, perPage = null) {
    const size = perPage ?? parseInt(perPageInput.value);
    try {
        const response = await fetch(`/grades/fetch?page=${page}&perPage=${size}&search=${encodeURIComponent(search.value)}&sortBy=${sortBy}&sortDir=${sortDir}`);
        const result = await response.json();
        if (!response.ok) throw new Error(result.message || "Unable to fetch grades.");
        grades = result.data;
        const offset = (result.current_page - 1) * size;
        table.innerHTML = grades.length ? grades.map((item, index) => `<tr><td>${offset + index + 1}</td><td>${item.grade}</td><td>${item.grade_short_name}</td><td>${item.grade_order ?? ""}</td><td>${item.description ?? ""}</td><td>${item.status ? "<span class='badge bg-success-lt'>Active</span>" : "<span class='badge bg-danger-lt'>Inactive</span>"}</td><td class="text-center"><button onclick="gradesPage.openEditModal(${item.id})" class="btn btn-primary btn-sm"><i class="ti ti-pencil icon"></i>Edit</button> <button onclick="gradesPage.deleteGrade(${item.id})" class="btn btn-danger btn-sm"><i class="ti ti-trash icon"></i>Delete</button></td></tr>`).join("") : `<tr><td colspan="7" class="text-center">No grades found.</td></tr>`;
        renderPagination(result, "grades-pagination-container", "grades-per-page", fetchGrades);
        renderPageInfo(result);
    } catch (error) { console.error(error); }
}

document.getElementById("btnNewGrade")?.addEventListener("click", openCreateModal);
document.getElementById("btnNewGradeMobile")?.addEventListener("click", openCreateModal);
perPageInput?.addEventListener("change", () => fetchGrades(1, parseInt(perPageInput.value)));
search?.addEventListener("keyup", () => fetchGrades(1, parseInt(perPageInput.value)));
document.querySelectorAll("[data-sort]").forEach((header) => header.addEventListener("click", () => {
    const selectedSort = header.dataset.sort;
    sortDir = sortBy === selectedSort && sortDir === "asc" ? "desc" : "asc";
    sortBy = selectedSort;
    fetchGrades(1, parseInt(perPageInput.value));
}));
fetchGrades();

window.gradesPage = { openCreateModal, openEditModal, deleteGrade, fetchGrades };
