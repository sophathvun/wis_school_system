import { renderPagination, renderPageInfo } from "./helpers/pagination.js";
import { showSuccess, showConfirm, showError } from "./helpers/sweet-alert2.js";

const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const table = document.getElementById("academicTracksTable");
const search = document.getElementById("academic-tracks-search");
const perPage = document.getElementById("academic-tracks-per-page");
const form = document.getElementById("academicTrackForm");
const modal = new bootstrap.Modal(document.getElementById("academicTrackModal"));
let rows = [];

const escapeHtml = (value = "") => String(value).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#39;");
const nice = (value = "") => String(value).replace(/_/g, " ").replace(/\b\w/g, (letter) => letter.toUpperCase());

const reset = () => {
    form.reset();
    document.getElementById("academic_track_id").value = "";
    document.getElementById("academic_track_grade_id").value = "";
    document.getElementById("academic_track_stream_type").value = "science";
    document.getElementById("academic_track_language").value = "khmer";
    document.getElementById("academic_track_status").value = "1";
    document.getElementById("academicTrackModalTitle").textContent = "New Academic Track";
    form.querySelector("[data-alert]")?.classList.add("d-none");
};

const edit = (id) => {
    const item = rows.find((row) => row.id === id);
    if (!item) return;
    document.getElementById("academic_track_id").value = item.id;
    document.getElementById("academic_track_name_en").value = item.name_en || "";
    document.getElementById("academic_track_name_kh").value = item.name_kh || "";
    document.getElementById("academic_track_code").value = item.code || "";
    document.getElementById("academic_track_grade_id").value = item.grade_id || "";
    document.getElementById("academic_track_stream_type").value = item.stream_type || "science";
    document.getElementById("academic_track_language").value = item.language || "khmer";
    document.getElementById("academic_track_status").value = String(item.status ? 1 : 0);
    document.getElementById("academicTrackModalTitle").textContent = "Edit Academic Track";
    form.querySelector("[data-alert]")?.classList.add("d-none");
    modal.show();
};

const remove = async (id) => {
    if (!(await showConfirm("Delete Academic Track", "Deactivate assigned tracks instead of deleting them.", "Delete", "Cancel")).isConfirmed) return;
    const response = await fetch(`/academic-tracks/${id}`, { method: "DELETE", headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf } });
    const result = await response.json();
    if (!response.ok) return showError("Unable to delete academic track", result.message || "Unable to delete academic track.");
    showSuccess("Deleted", result.message);
    fetchRows();
};

const fetchRows = async (page = 1, size = null) => {
    const pageSize = size ?? parseInt(perPage.value, 10);
    const response = await fetch(`/academic-tracks/fetch?page=${page}&perPage=${pageSize}&search=${encodeURIComponent(search.value)}`, { headers: { Accept: "application/json" } });
    const result = await response.json();
    if (!response.ok) throw new Error(result.message || "Unable to load academic tracks.");
    rows = result.data || [];
    table.innerHTML = rows.length ? rows.map((item) => `
        <tr>
            <td><span class="school-profile-khmer">${escapeHtml(item.name_kh || "-")}</span><br><small class="text-secondary">${escapeHtml(item.name_en || "-")}</small></td>
            <td>${escapeHtml(item.code || "-")}</td>
            <td>${escapeHtml(item.grade?.grade || "-")}</td>
            <td>${escapeHtml(nice(item.stream_type))}</td>
            <td>${escapeHtml(nice(item.language))}</td>
            <td>${window.statusToggleMarkup ? window.statusToggleMarkup("academic-track", item.id, Boolean(item.status)) : (item.status ? "<span class='badge bg-success-lt'>Active</span>" : "<span class='badge bg-danger-lt'>Inactive</span>")}</td>
            <td class="text-center"><button class="btn btn-primary btn-sm" data-edit="${item.id}">Edit</button> <button class="btn btn-danger btn-sm" data-delete="${item.id}">Delete</button></td>
        </tr>`).join("") : `<tr><td colspan="7" class="text-center">No academic tracks found.</td></tr>`;
    table.querySelectorAll("[data-edit]").forEach((button) => button.addEventListener("click", () => edit(Number(button.dataset.edit))));
    table.querySelectorAll("[data-delete]").forEach((button) => button.addEventListener("click", () => remove(Number(button.dataset.delete))));
    renderPagination(result, "academic-tracks-pagination-container", "academic-tracks-per-page", fetchRows);
    renderPageInfo(result);
};

document.getElementById("newAcademicTrack")?.addEventListener("click", () => { reset(); modal.show(); });
form?.addEventListener("submit", async (event) => {
    event.preventDefault();
    const response = await fetch("/academic-tracks/save", { method: "POST", headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf }, body: new FormData(form) });
    const result = await response.json();
    if (response.status === 422) {
        const alert = form.querySelector("[data-alert]");
        alert.textContent = result.message || Object.values(result.errors || {})[0]?.[0] || "Please correct the form.";
        alert.classList.remove("d-none");
        return;
    }
    if (!response.ok) return showError("Unable to save academic track", result.message || "Unable to save academic track.");
    modal.hide();
    showSuccess("Saved", result.message);
    fetchRows();
});
perPage?.addEventListener("change", () => fetchRows(1, parseInt(perPage.value, 10)));
search?.addEventListener("input", () => fetchRows(1, parseInt(perPage.value, 10)));
fetchRows().catch((error) => showError("Unable to load academic tracks", error.message));
