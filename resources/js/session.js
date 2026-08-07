import { renderPagination, renderPageInfo } from "./helpers/pagination.js";
import { showSuccess, showError, showConfirm } from "./helpers/sweet-alert2.js";

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content");
const form = document.getElementById("sessionForm");
const modalElement = document.getElementById("sessionModal");
const modal = modalElement ? new bootstrap.Modal(modalElement) : null;
const table = document.getElementById("sessionsTable");
const search = document.getElementById("sessions-search");
const perPageInput = document.getElementById("sessions-per-page");
const submitButton = document.getElementById("sessionSubmitBtn");
const modalTitle = document.getElementById("sessionModalTitle");
let sessions = [];

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
    form?.reset(); clearErrors(); document.getElementById("session_id").value = "";
    modalTitle.textContent = "Create Session"; submitButton.textContent = "Create"; modal?.show();
};
const openEditModal = (id) => {
    const item = sessions.find((row) => row.id === id);
    if (!item || !form) return;
    form.reset(); clearErrors();
    document.getElementById("session_id").value = item.id;
    document.getElementById("session_name").value = item.session_name ?? "";
    document.getElementById("session_short_name").value = item.session_short_name ?? "";
    document.getElementById("session_order").value = item.session_order ?? "";
    document.getElementById("description").value = item.description ?? "";
    document.getElementById("status").value = String(item.status ?? 1);
    modalTitle.textContent = "Edit Session"; submitButton.textContent = "Update"; modal?.show();
};
form?.addEventListener("submit", async (event) => {
    event.preventDefault(); clearErrors(); submitButton.disabled = true; submitButton.textContent = "Saving...";
    const requiredFields = { session_name: "Session name is required.", session_short_name: "Short name is required." };
    const clientErrors = Object.fromEntries(Object.entries(requiredFields).filter(([field]) => !document.getElementById(field)?.value.trim()).map(([field, message]) => [field, [message]]));
    if (Object.keys(clientErrors).length) {
        showErrors(clientErrors, "Please complete all required fields.");
        submitButton.disabled = false;
        submitButton.textContent = document.getElementById("session_id").value ? "Update" : "Create";
        return;
    }
    try {
        const response = await fetch("/sessions/save", { method: "POST", headers: { Accept: "application/json", "X-CSRF-TOKEN": csrfToken }, body: new FormData(form) });
        const text = await response.text();
        let result; try { result = JSON.parse(text); } catch { throw new Error("Unable to save session. Please try again."); }
        if (response.status === 422) {
            const isDuplicate = result.message?.startsWith("Unable to save Session.");
            showErrors(isDuplicate ? {} : result.errors, result.message);
            return;
        }
        if (!response.ok || result.status !== "success") throw new Error(result.message || "Unable to save session.");
        modal?.hide(); showSuccess("Saved", result.message); fetchSessions();
    } catch (error) { showErrors({}, error.message); }
    finally { submitButton.disabled = false; submitButton.textContent = document.getElementById("session_id").value ? "Update" : "Create"; }
});
const deleteSession = async (id) => {
    if (!(await showConfirm("Delete Session", "Are you sure you want to delete this session?", "Delete", "Cancel")).isConfirmed) return;
    try {
        const response = await fetch(`/sessions/delete/${id}`, { method: "DELETE", headers: { Accept: "application/json", "X-CSRF-TOKEN": csrfToken } });
        const result = await response.json(); if (!response.ok) throw new Error(result.message || "Unable to delete session.");
        showSuccess("Deleted", result.message); fetchSessions();
    } catch (error) { showError("Error", error.message); }
};
async function fetchSessions(page = 1, perPage = null) {
    const size = perPage ?? parseInt(perPageInput.value);
    try {
        const response = await fetch(`/sessions/fetch?page=${page}&perPage=${size}&search=${encodeURIComponent(search.value)}`);
        const result = await response.json(); if (!response.ok) throw new Error(result.message || "Unable to fetch sessions.");
        sessions = result.data; const offset = (result.current_page - 1) * size;
        table.innerHTML = sessions.length ? sessions.map((item, index) => `<tr><td>${offset + index + 1}</td><td>${item.session_name}</td><td>${item.session_short_name}</td><td>${item.session_order ?? ""}</td><td>${item.description ?? ""}</td><td>${item.status ? "<span class='badge bg-success-lt'>Active</span>" : "<span class='badge bg-danger-lt'>Inactive</span>"}</td><td class="text-center"><button onclick="sessionsPage.openEditModal(${item.id})" class="btn btn-primary btn-sm"><i class="ti ti-pencil icon"></i>Edit</button> <button onclick="sessionsPage.deleteSession(${item.id})" class="btn btn-danger btn-sm"><i class="ti ti-trash icon"></i>Delete</button></td></tr>`).join("") : `<tr><td colspan="7" class="text-center">No sessions found.</td></tr>`;
        renderPagination(result, "sessions-pagination-container", "sessions-per-page", fetchSessions); renderPageInfo(result);
    } catch (error) { console.error(error); }
}
document.getElementById("btnNewSession")?.addEventListener("click", openCreateModal);
document.getElementById("btnNewSessionMobile")?.addEventListener("click", openCreateModal);
perPageInput?.addEventListener("change", () => fetchSessions(1, parseInt(perPageInput.value)));
search?.addEventListener("keyup", () => fetchSessions(1, parseInt(perPageInput.value)));
fetchSessions();
window.sessionsPage = { openCreateModal, openEditModal, deleteSession, fetchSessions };
