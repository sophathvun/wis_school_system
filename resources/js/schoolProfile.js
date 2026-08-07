import { renderPagination, renderPageInfo } from "./helpers/pagination.js";
import { showSuccess, showError, showConfirm } from "./helpers/sweet-alert2.js";
import intlTelInput from "intl-tel-input";
import "intl-tel-input/styles";

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content");
const form = document.getElementById("schoolProfileForm");
const modalElement = document.getElementById("schoolProfileModal");
const modal = modalElement ? new bootstrap.Modal(modalElement) : null;
const viewModalElement = document.getElementById("schoolProfileViewModal");
const viewModal = viewModalElement ? new bootstrap.Modal(viewModalElement) : null;
const viewSchoolPrintButton = document.getElementById("viewSchoolPrintBtn");
const table = document.getElementById("schoolProfilesTable");
const search = document.getElementById("school-profiles-search");
const perPageInput = document.getElementById("school-profiles-per-page");
const submitButton = document.getElementById("schoolProfileSubmitBtn");
const modalTitle = document.getElementById("schoolProfileModalTitle");
const logoInput = document.getElementById("logo");
const logoDropzone = document.getElementById("logoDropzone");
const logoPreview = document.getElementById("logoPreview");
const logoPreviewContainer = document.getElementById("logoPreviewContainer");
const phoneNumber = document.getElementById("phone_number");
const phoneInput = document.getElementById("phone");
const phoneIntl = phoneNumber ? intlTelInput(phoneNumber, {
    initialCountry: "kh",
    nationalMode: true,
    separateDialCode: true,
    loadUtils: () => import("intl-tel-input/utils"),
}) : null;
const resetPhoneToCambodia = () => {
    phoneIntl?.setNumber("");
    phoneIntl?.setCountry("kh");
    window.setTimeout(() => phoneIntl?.setCountry("kh"), 0);
    if (phoneNumber) phoneNumber.value = "";
    if (phoneInput) phoneInput.value = "";
};
let schoolProfiles = [];
let creatingNewProfile = false;
let profileToPrint = null;

const clearErrors = () => {
    form?.querySelectorAll(".is-invalid").forEach((field) => field.classList.remove("is-invalid"));
    form?.querySelectorAll("[data-error-for]").forEach((field) => {
        field.textContent = "";
        field.classList.remove("d-block");
    });
    const alert = form?.querySelector("[data-form-alert]");
    if (alert) { alert.textContent = ""; alert.classList.add("d-none"); }
};
const showErrors = (errors, message) => {
    const alert = form?.querySelector("[data-form-alert]");
    if (alert) { alert.textContent = message || "Please correct the errors below."; alert.classList.remove("d-none"); }
    Object.entries(errors || {}).forEach(([field, messages]) => {
        document.getElementById(field)?.classList.add("is-invalid");
        const error = form?.querySelector(`[data-error-for="${field}"]`);
        if (error) {
            error.textContent = messages[0];
            error.classList.add("d-block");
        }
    });
};
const openCreateModal = () => {
    creatingNewProfile = true;
    form?.reset(); clearErrors(); document.getElementById("school_id").value = "";
    resetPhoneToCambodia();
    logoPreviewContainer?.classList.add("d-none");
    modalTitle.textContent = "Create School Profile"; submitButton.textContent = "Create"; modal?.show();
};
const openEditModal = (id) => {
    const item = schoolProfiles.find((row) => row.id === id);
    if (!item || !form) return;
    creatingNewProfile = false;
    form.reset(); clearErrors();
    document.getElementById("school_id").value = item.id;
    document.getElementById("school_name_en").value = item.school_name_en ?? "";
    document.getElementById("school_name_kh").value = item.school_name_kh ?? "";
    if (item.logo_path) {
        logoPreview.src = `/storage/${item.logo_path}`;
        logoPreviewContainer.classList.remove("d-none");
    } else {
        logoPreviewContainer.classList.add("d-none");
    }
    document.getElementById("campus_name_en").value = item.campus_name_en ?? "";
    document.getElementById("campus_name_kh").value = item.campus_name_kh ?? "";
    document.getElementById("address").value = item.address ?? "";
    const savedPhone = item.phone ?? "";
    phoneIntl?.setNumber(savedPhone);
    phoneInput.value = savedPhone;
    document.getElementById("description").value = item.description ?? "";
    document.getElementById("status").value = String(item.status ?? 1);
    modalTitle.textContent = "Edit School Profile"; submitButton.textContent = "Update"; modal?.show();
};
const openViewModal = (id) => {
    const item = schoolProfiles.find((row) => row.id === id);
    if (!item) return;
    profileToPrint = item;

    const setText = (elementId, value) => {
        const element = document.getElementById(elementId);
        if (element) element.textContent = value || "-";
    };
    const logo = document.getElementById("viewSchoolLogo");
    if (logo) {
        logo.src = item.logo_path ? `/storage/${item.logo_path}` : "";
        logo.classList.toggle("d-none", !item.logo_path);
    }
    setText("viewSchoolNameKh", item.school_name_kh);
    setText("viewSchoolNameEn", item.school_name_en);
    setText("viewCampusNameKh", item.campus_name_kh);
    setText("viewCampusNameEn", item.campus_name_en);
    setText("viewPhone", item.phone?.replace(/\r?\n/g, "\n"));
    setText("viewAddress", item.address);
    setText("viewDescription", item.description);
    const status = document.getElementById("viewStatus");
    if (status) status.innerHTML = item.status
        ? "<span class='badge bg-success-lt'>Active</span>"
        : "<span class='badge bg-danger-lt'>Inactive</span>";
    viewModal?.show();
};
const escapeHtml = (value) => String(value ?? "-")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
const printSchoolProfile = () => {
    if (!profileToPrint) return;
    const item = profileToPrint;
    const logo = item.logo_path ? `<img src="/storage/${escapeHtml(item.logo_path)}" alt="School Logo" style="width:110px;height:110px;object-fit:contain">` : "";
    const phone = escapeHtml(item.phone || "-").replace(/\r?\n/g, "<br>");
    const printWindow = window.open("", "_blank", "width=900,height=700");
    if (!printWindow) return;
    printWindow.document.write(`<!doctype html><html><head><title>School Profile</title><style>
        @font-face { font-family: NotoSansKhmer; src: url('/build/assets/noto-sans-khmer-khmer-400-normal-DnqNet9s.woff2'); }
        body { font-family: Arial, sans-serif; color: #263648; padding: 35px; } .header { text-align:center; margin-bottom:28px; }
        h1 { margin: 12px 0 4px; font-size: 26px; } h2 { margin:0; font-family:NotoSansKhmer, Arial; font-size:22px; }
        table { width:100%; border-collapse:collapse; } td { border:1px solid #dfe4ea; padding:12px; vertical-align:top; }
        td:first-child { width:32%; font-weight:bold; background:#f5f7fa; } .khmer { font-family:NotoSansKhmer, Arial; font-size:18px; }
        .text { white-space:pre-wrap; } @media print { body { padding:0; } }
    </style></head><body><div class="header">${logo}<h2>${escapeHtml(item.school_name_kh)}</h2><h1>${escapeHtml(item.school_name_en)}</h1></div>
        <table><tr><td>Campus (Khmer)</td><td class="khmer">${escapeHtml(item.campus_name_kh)}</td></tr><tr><td>Campus (English)</td><td>${escapeHtml(item.campus_name_en)}</td></tr>
        <tr><td>Phone Number</td><td>${phone}</td></tr><tr><td>Address</td><td class="text">${escapeHtml(item.address)}</td></tr>
        <tr><td>Description</td><td class="text">${escapeHtml(item.description)}</td></tr><tr><td>Status</td><td>${item.status ? "Active" : "Inactive"}</td></tr></table>
    </body></html>`);
    printWindow.document.close();
    printWindow.addEventListener("load", () => { printWindow.focus(); printWindow.print(); });
};
viewSchoolPrintButton?.addEventListener("click", printSchoolProfile);
form?.addEventListener("submit", async (event) => {
    event.preventDefault();
    phoneInput.value = phoneIntl?.getNumber() || phoneNumber.value.trim();
    clearErrors(); submitButton.disabled = true; submitButton.textContent = "Saving...";
    const requiredFields = {
        school_name_en: "School name in English is required.",
        school_name_kh: "School name in Khmer is required.",
        campus_name_en: "Campus name in English is required.",
        campus_name_kh: "Campus name in Khmer is required.",
    };
    const clientErrors = Object.fromEntries(
        Object.entries(requiredFields)
            .filter(([field]) => !document.getElementById(field)?.value.trim())
            .map(([field, message]) => [field, [message]]),
    );
    if (Object.keys(clientErrors).length) {
        showErrors(clientErrors, "Please complete all required fields.");
        submitButton.disabled = false;
        submitButton.textContent = document.getElementById("school_id").value ? "Update" : "Create";
        return;
    }
    try {
        const response = await fetch("/school-info/save", { method: "POST", headers: { Accept: "application/json", "X-CSRF-TOKEN": csrfToken }, body: new FormData(form) });
        const text = await response.text(); let result;
        try { result = JSON.parse(text); } catch { throw new Error("Unable to save school profile. Please try again."); }
        if (response.status === 422) {
            const isDuplicate = result.message?.startsWith("Unable to save School Profile.");
            showErrors(isDuplicate ? {} : result.errors, result.message);
            return;
        }
        if (!response.ok || result.status !== "success") throw new Error(result.message || "Unable to save school profile.");
        modal?.hide(); showSuccess("Saved", result.message); fetchSchoolProfiles();
    } catch (error) { showErrors({}, error.message); }
    finally { submitButton.disabled = false; submitButton.textContent = document.getElementById("school_id").value ? "Update" : "Create"; }
});
logoInput?.addEventListener("change", () => {
    const file = logoInput.files?.[0];
    if (!file) return;
    showLogoPreview(file);
});
const showLogoPreview = (file) => {
    if (!file || !file.type.startsWith("image/")) return;
    logoPreview.src = URL.createObjectURL(file);
    logoPreview.style.cssText = "display:block;width:96px;height:96px;max-width:96px;max-height:96px;object-fit:contain;border:1px solid var(--tblr-border-color);border-radius:.5rem;background:var(--tblr-bg-surface);";
    logoPreviewContainer.classList.remove("d-none");
};
logoDropzone?.addEventListener("click", () => logoInput?.click());
logoDropzone?.addEventListener("keydown", (event) => {
    if (event.key === "Enter" || event.key === " ") logoInput?.click();
});
logoDropzone?.addEventListener("dragover", (event) => {
    event.preventDefault();
    logoDropzone.classList.add("is-dragging");
});
logoDropzone?.addEventListener("dragleave", () => logoDropzone.classList.remove("is-dragging"));
logoDropzone?.addEventListener("drop", (event) => {
    event.preventDefault();
    logoDropzone.classList.remove("is-dragging");
    const file = event.dataTransfer.files?.[0];
    if (!file) return;
    const transfer = new DataTransfer();
    transfer.items.add(file);
    if (logoInput) logoInput.files = transfer.files;
    showLogoPreview(file);
});
const deleteSchoolProfile = async (id) => {
    if (!(await showConfirm("Delete School Profile", "Are you sure you want to delete this school profile?", "Delete", "Cancel")).isConfirmed) return;
    try {
        const response = await fetch(`/school-info/delete/${id}`, { method: "DELETE", headers: { Accept: "application/json", "X-CSRF-TOKEN": csrfToken } });
        const result = await response.json(); if (!response.ok) throw new Error(result.message || "Unable to delete school profile.");
        showSuccess("Deleted", result.message); fetchSchoolProfiles();
    } catch (error) { showError("Error", error.message); }
};
async function fetchSchoolProfiles(page = 1, perPage = null) {
    const size = perPage ?? parseInt(perPageInput.value);
    try {
        const response = await fetch(`/school-info/fetch?page=${page}&perPage=${size}&search=${encodeURIComponent(search.value)}`);
        const result = await response.json(); if (!response.ok) throw new Error(result.message || "Unable to fetch school profiles.");
        schoolProfiles = result.data; const offset = (result.current_page - 1) * size;
        table.innerHTML = schoolProfiles.length ? schoolProfiles.map((item, index) => `<tr><td>${offset + index + 1}</td><td>${item.logo_path ? `<img src="/storage/${item.logo_path}" alt="Logo" style="width:40px;height:40px;object-fit:contain;vertical-align:middle">` : "-"}</td><td><small class="text-secondary school-profile-khmer">${item.school_name_kh}</small><br>${item.school_name_en}</td><td><small class="text-secondary school-profile-khmer">${item.campus_name_kh}</small><br>${item.campus_name_en}</td><td>${(item.phone ?? "").replace(/\r?\n/g, "<br>")}</td><td>${item.status ? "<span class='badge bg-success-lt'>Active</span>" : "<span class='badge bg-danger-lt'>Inactive</span>"}</td><td class="text-center"><button onclick="schoolProfilesPage.openViewModal(${item.id})" class="btn btn-info btn-sm"><i class="ti ti-eye icon"></i>View</button> <button onclick="schoolProfilesPage.openEditModal(${item.id})" class="btn btn-primary btn-sm"><i class="ti ti-pencil icon"></i>Edit</button> <button onclick="schoolProfilesPage.deleteSchoolProfile(${item.id})" class="btn btn-danger btn-sm"><i class="ti ti-trash icon"></i>Delete</button></td></tr>`).join("") : `<tr><td colspan="7" class="text-center">No school profiles found.</td></tr>`;
        renderPagination(result, "school-profiles-pagination-container", "school-profiles-per-page", fetchSchoolProfiles); renderPageInfo(result);
    } catch (error) { console.error(error); }
}
document.getElementById("btnNewSchoolProfile")?.addEventListener("click", openCreateModal);
document.getElementById("btnNewSchoolProfileMobile")?.addEventListener("click", openCreateModal);
modalElement?.addEventListener("show.bs.modal", () => {
    if (creatingNewProfile) resetPhoneToCambodia();
});
modalElement?.addEventListener("hidden.bs.modal", () => {
    if (creatingNewProfile) resetPhoneToCambodia();
    creatingNewProfile = false;
});
perPageInput?.addEventListener("change", () => fetchSchoolProfiles(1, parseInt(perPageInput.value)));
search?.addEventListener("keyup", () => fetchSchoolProfiles(1, parseInt(perPageInput.value)));
fetchSchoolProfiles();
window.schoolProfilesPage = { openCreateModal, openViewModal, printSchoolProfile, openEditModal, deleteSchoolProfile, fetchSchoolProfiles };
