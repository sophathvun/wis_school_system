import { renderPagination, renderPageInfo } from "./helpers/pagination.js";
import { showSuccess, showError, showConfirm } from "./helpers/sweet-alert2.js";

const csrfToken = document
    .querySelector('meta[name="csrf-token"]')
    ?.getAttribute("content");

const modalElement = document.getElementById("academicYearModal");
const viewModalElement = document.getElementById("academicYearViewModal");
const modalTitle =
    document.getElementById("modalTitle") ||
    document.getElementById("academicYearModalLabel");
const submitBtn =
    document.getElementById("submitBtn") ||
    document.getElementById("academicYearSubmitBtn");
const academicYearForm = document.getElementById("academicYearForm");
const searchInput = document.getElementById("academic-years-search");
const academicYearsTable = document.getElementById("academicYearsTable");
let allAcademicYears = [];

const periodTypeInput = document.getElementById("period_type");
const lifecycleStatusInput = document.getElementById("lifecycle_status");
const summerPeriodFields = document.getElementById("summerPeriodFields");
const updateSummerFields = () => {
    const isSummer = periodTypeInput?.value === "summer";
    summerPeriodFields?.classList.toggle("d-none", !isSummer);
    ["start_date", "end_date"].forEach((id) => {
        const input = document.getElementById(id);
        if (input) input.required = isSummer;
    });
};
periodTypeInput?.addEventListener("change", updateSummerFields);
updateSummerFields();

// Initialize modal (guard if element not present)
const bsModal = modalElement ? new bootstrap.Modal(modalElement) : null;
const bsViewModal = viewModalElement
    ? new bootstrap.Modal(viewModalElement)
    : null;

//Action Buttons
const btnNewAcademicYear = document.getElementById("btnNewAcademicYear");
const btnNewAcademicYearMobile = document.getElementById(
    "btnNewAcademicYearMobile",
);

// --- Open create academic year modal --- //
const openCreateModal = () => {
    if (!academicYearForm) return;
    academicYearForm.reset();
    clearFormErrors();
    const ayId = document.getElementById("academic_year_id");
    if (ayId) ayId.value = "";
    if (periodTypeInput) periodTypeInput.value = "regular";
    if (lifecycleStatusInput) lifecycleStatusInput.value = "pending";
    updateSummerFields();
    if (modalTitle) modalTitle.textContent = "Create Academic Year";
    if (submitBtn) submitBtn.textContent = "Create";
    if (bsModal) bsModal.show();
};
if (btnNewAcademicYear)
    btnNewAcademicYear.addEventListener("click", openCreateModal);
if (btnNewAcademicYearMobile)
    btnNewAcademicYearMobile.addEventListener("click", openCreateModal);

const clearFormErrors = () => {
    academicYearForm?.querySelectorAll(".is-invalid").forEach((field) =>
        field.classList.remove("is-invalid"),
    );
    academicYearForm?.querySelectorAll("[data-error-for]").forEach((error) => {
        error.textContent = "";
        error.classList.remove("d-block");
    });
    const alert = academicYearForm?.querySelector("[data-form-alert]");
    if (alert) {
        alert.textContent = "";
        alert.classList.add("d-none");
    }
};

const showFormErrors = (errors, message = "Please correct the errors below.") => {
    const alert = academicYearForm?.querySelector("[data-form-alert]");
    if (alert) {
        alert.textContent = message;
        alert.classList.remove("d-none");
    }
    Object.entries(errors || {}).forEach(([field, messages]) => {
        const input = document.getElementById(field);
        const error = academicYearForm?.querySelector(`[data-error-for="${field}"]`);
        input?.classList.add("is-invalid");
        if (error) { error.textContent = messages[0]; error.classList.add("d-block"); }
    });
};

academicYearForm?.addEventListener("submit", async (event) => {
    event.preventDefault();
    clearFormErrors();
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = "Saving...";
    }

    const requiredFields = {
        academic_year: "Academic year is required.",
    };
    const clientErrors = Object.fromEntries(
        Object.entries(requiredFields)
            .filter(([field]) => !document.getElementById(field)?.value.trim())
            .map(([field, message]) => [field, [message]]),
    );
    if (Object.keys(clientErrors).length) {
        showFormErrors(clientErrors, "Please complete all required fields.");
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = document.getElementById("academic_year_id")?.value ? "Update" : "Create";
        }
        return;
    }

    try {
        const response = await fetch("/academic-years/save", {
            method: "POST",
            headers: {
                Accept: "application/json",
                "X-CSRF-TOKEN": csrfToken,
            },
            body: new FormData(academicYearForm),
        });
        const responseText = await response.text();
        let result;
        try { result = JSON.parse(responseText); } catch { throw new Error("Unable to save academic year. Please try again."); }

        if (response.status === 422) {
            const isDuplicate = result.message?.startsWith("Unable to save Academic Year.");
            showFormErrors(isDuplicate ? {} : result.errors, result.message);
            return;
        }
        if (!response.ok || result.status !== "success") {
            throw new Error(result.message || "Unable to save academic year.");
        }

        bsModal?.hide();
        showSuccess("Saved", result.message);
        fetchAcademicYears();
    } catch (error) {
        console.error("Error saving academic year:", error);
        const alert = academicYearForm?.querySelector("[data-form-alert]");
        if (alert) {
            alert.textContent = error.message || "Unable to save academic year.";
            alert.classList.remove("d-none");
        }
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = document.getElementById("academic_year_id")?.value
                ? "Update"
                : "Create";
        }
    }
});

// --- Open edit academic year modal --- //
const openEditModal = (id) => {
    const academicYear = allAcademicYears.find((year) => year.id === id);
    if (!academicYear || !academicYearForm) return;

    academicYearForm.reset();
    clearFormErrors();
    const ayId = document.getElementById("academic_year_id");
    const academicYearInput = document.getElementById("academic_year");
    const ayCodeInput = document.getElementById("ay_code");
    const descriptionInput = document.getElementById("description");
    const lifecycleStatusSelect = document.getElementById("lifecycle_status");
    const startDateInput = document.getElementById("start_date");
    const endDateInput = document.getElementById("end_date");

    if (ayId) ayId.value = academicYear.id;
    if (academicYearInput)
        academicYearInput.value = academicYear.academic_year ?? "";
    if (ayCodeInput) ayCodeInput.value = academicYear.ay_code ?? "";
    if (descriptionInput)
        descriptionInput.value = academicYear.description ?? "";
    if (lifecycleStatusSelect)
        lifecycleStatusSelect.value = academicYear.lifecycle_status ?? (academicYear.status ? "pending" : "finished");
    if (periodTypeInput) periodTypeInput.value = academicYear.period_type ?? "regular";
    if (startDateInput) startDateInput.value = academicYear.start_date ?? "";
    if (endDateInput) endDateInput.value = academicYear.end_date ?? "";
    updateSummerFields();

    if (modalTitle) modalTitle.textContent = "Edit Academic Year";
    if (submitBtn) submitBtn.textContent = "Update";
    if (bsModal) bsModal.show();
};

const setCurrentAcademicYear = async (id) => {
    const year = allAcademicYears.find((item) => item.id === id);
    const result = await showConfirm(
        "Set Started Academic Year",
        `Set ${year?.academic_year ?? "this academic year"} as started? The existing started ${year?.period_type === "summer" ? "Summer School" : "Regular Academic Year"} will be finished automatically.`,
        "Set Started",
        "Cancel",
    );

    if (!result.isConfirmed) return;

    try {
        const response = await fetch(`/academic-years/${id}/set-current`, {
            method: "POST",
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": csrfToken,
            },
        });
        const data = await response.json();

        if (!response.ok || data.status !== "success") {
            throw new Error(data.message || "Unable to set started academic year.");
        }

        showSuccess("Updated", data.message);
        fetchAcademicYears();
    } catch (error) {
        showError("Error", error.message || "Unable to set started academic year.");
    }
};

// --- Delete academic year --- //
const deleteAcademicYear = async (id) => {
    const result = await showConfirm(
        "Delete Academic Year",
        "Are you sure you want to delete this academic year?",
        "Delete",
        "Cancel",
    );

    if (!result.isConfirmed) return;

    try {
        const response = await fetch(`/academic-years/delete/${id}`, {
            method: "DELETE",
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": csrfToken,
            },
        });
        const responseData = await response.json();

        if (!response.ok) {
            throw new Error(
                responseData.message || "Unable to delete academic year.",
            );
        }

        if (responseData.status === "success") {
            showSuccess(
                "Deleted",
                responseData.message || "Academic year deleted.",
            );
            fetchAcademicYears();
        } else {
            showError(
                "Error",
                responseData.message || "Unable to delete academic year.",
            );
        }
    } catch (error) {
        console.error("Error deleting academic year:", error);
        showError("Error", "Unable to delete academic year. Please try again.");
    }
};

//---- Fetch All Academic Years ----//
const fetchAcademicYears = async (page = 1, perPage = null) => {
    let row = "";
    const searchValue = searchInput ? searchInput.value : "";
    const perPageValue =
        perPage !== null ? perPage : parseInt(perPageInput?.value ?? 10);

    try {
        const response = await fetch(
            `/academic-years/fetch?page=${page}&perPage=${perPageValue}&search=${encodeURIComponent(searchValue)}`,
        );
        if (!response.ok) {
            throw new Error(`Fetch failed with status ${response.status}`);
        }
        const result = await response.json();
        allAcademicYears = result.data;

        const lifecycleBadge = (status) => {
            const badges = {
                draft: "<span class='badge bg-secondary-lt'>Draft</span>",
                pending: "<span class='badge bg-blue-lt'>Pending</span>",
                started: "<span class='badge bg-green-lt'>Started</span>",
                finished: "<span class='badge bg-orange-lt'>Finished</span>",
                archived: "<span class='badge bg-dark-lt'>Archived</span>",
            };
            return badges[status] || "<span class='badge bg-secondary-lt'>Draft</span>";
        };

        const rowNumber = (result.current_page - 1) * perPageValue;

        allAcademicYears.forEach((year, index) => {
            row += `
            <tr>
                <td>${rowNumber + index + 1}</td>
                <td>${year.academic_year}</td>
                <td>${year.period_type === "summer" ? "Summer School" : "Regular"}</td>
                <td>${year.ay_code ?? ""}</td>
                <td>${year.start_date ?? ""}</td>
                <td>${year.end_date ?? ""}</td>
                <td>${year.description ?? ""}</td>
                <td>${lifecycleBadge(year.lifecycle_status)}</td>
                <td class="text-center">
                    ${year.lifecycle_status !== "started" ? `<button onclick="academicYears.setCurrentAcademicYear(${year.id})" class="btn btn-success btn-sm"><i class="ti ti-check icon"></i>Set Started</button>` : "<span class='badge bg-green-lt'>Started</span>"}
                </td>
                <td class="text-center">
                    <button onclick="academicYears.openEditModal(${year.id})" class="btn btn-primary btn-sm"><i class="ti ti-pencil icon"></i>Edit</button>
                    <button onclick="academicYears.deleteAcademicYear(${year.id})" class="btn btn-danger btn-sm"><i class="ti ti-trash icon"></i>Delete</button>                
                </td>
            </tr>`;
        });
        if (academicYearsTable) {
            academicYearsTable.innerHTML = row;
        }
        renderPagination(
            result,
            "academic-years-pagination-container",
            "academic-years-per-page",
            fetchAcademicYears,
        );
        renderPageInfo(result);
    } catch (error) {
        console.error("Error fetching academic years:", error);
    }
};
// Fetch per page
const perPageInput = document.getElementById("academic-years-per-page");
const fetchPerPage = () => {
    const perPage = perPageInput ? parseInt(perPageInput.value) : 10;
    fetchAcademicYears(1, perPage);
};
if (perPageInput) {
    perPageInput.addEventListener("change", fetchPerPage);
}
if (searchInput) {
    searchInput.addEventListener("keyup", () =>
        fetchAcademicYears(1, perPageInput ? parseInt(perPageInput.value) : 10),
    );
}

fetchAcademicYears();

window.academicYears = {
    openCreateModal,
    openEditModal,
    deleteAcademicYear,
    setCurrentAcademicYear,
    fetchAcademicYears,
};
window.openCreateModal = openCreateModal;
