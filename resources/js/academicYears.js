import { renderPagination, renderPageInfo } from "./helpers/pagination.js";
import { showAlert, showSuccess, showError, showConfirm } from "./helpers/sweet-alert2.js";

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
const academicYearFilter = document.getElementById("academic-year-filter");
const academicYearTypeFilter = document.getElementById("academic-year-type-filter");
const academicYearsTable = document.getElementById("academicYearsTable");
const academicYearsMobileCards = document.getElementById("academicYearsMobileCards");
let allAcademicYears = [];
let sortBy = "academic_year";
let sortDir = "desc";

const filterComboboxes = [...document.querySelectorAll("[data-filter-combobox]")].map((wrapper) => {
    const type = wrapper.dataset.filterCombobox;
    const input = wrapper.querySelector(`#${type === "academic-year" ? "academic-year-filter" : "academic-year-type-filter"}`);
    const toggle = wrapper.querySelector(".academic-year-combobox-toggle");
    const menu = wrapper.querySelector(".academic-year-combobox-menu");
    const search = wrapper.querySelector(".academic-year-combobox-search");
    const results = wrapper.querySelector(".academic-year-combobox-results");
    const label = toggle.querySelector("span");
    const emptyLabel = type === "academic-year" ? "Academic Year" : "Academic Year Type";
    const allLabel = type === "academic-year" ? "All Academic Years" : "All Academic Year Types";

    const close = () => menu.classList.add("d-none");
    const render = (options = []) => {
        const term = search.value.trim().toLowerCase();
        const filtered = options.filter((option) => option.label.toLowerCase().includes(term));
        results.innerHTML = [
            `<button type="button" class="academic-year-combobox-option" data-value="">${allLabel}</button>`,
            ...filtered.map((option) => `<button type="button" class="academic-year-combobox-option${input.value === option.value ? " is-selected" : ""}" data-value="${option.value}">${option.label}</button>`),
        ].join("");
    };

    toggle.addEventListener("click", () => {
        document.querySelectorAll(".academic-year-combobox-menu").forEach((otherMenu) => {
            if (otherMenu !== menu) otherMenu.classList.add("d-none");
        });
        menu.classList.toggle("d-none");
        search.value = "";
        search.focus();
    });
    search.addEventListener("input", () => render(filterComboboxes.find((combo) => combo.type === type)?.options ?? []));
    results.addEventListener("click", (event) => {
        const option = event.target.closest("[data-value]");
        if (!option) return;
        input.value = option.dataset.value;
        label.textContent = option.textContent;
        close();
        fetchWithFilters();
    });

    return { type, input, label, emptyLabel, render, options: [] };
});

document.addEventListener("click", (event) => {
    if (!event.target.closest(".academic-year-combobox")) {
        document.querySelectorAll(".academic-year-combobox-menu").forEach((menu) => menu.classList.add("d-none"));
    }
});

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
const btnToggleDeletedAcademicYears = document.getElementById(
    "btnToggleDeletedAcademicYears",
);
let includeDeletedAcademicYears = false;

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
if (btnToggleDeletedAcademicYears) {
    btnToggleDeletedAcademicYears.addEventListener("click", () => {
        includeDeletedAcademicYears = !includeDeletedAcademicYears;
        btnToggleDeletedAcademicYears.innerHTML = includeDeletedAcademicYears
            ? '<i class="ti ti-eye-off icon"></i> Hide Deleted'
            : '<i class="ti ti-trash icon"></i> Show Deleted';
        fetchAcademicYears(1, parseInt(perPageInput?.value ?? 10));
    });
}

const clearFormErrors = () => {
    academicYearForm
        ?.querySelectorAll(".is-invalid")
        .forEach((field) => field.classList.remove("is-invalid"));
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

const showFormErrors = (
    errors,
    message = "Please correct the errors below.",
) => {
    const alert = academicYearForm?.querySelector("[data-form-alert]");
    if (alert) {
        alert.textContent = message;
        alert.classList.remove("d-none");
    }
    Object.entries(errors || {}).forEach(([field, messages]) => {
        const input = document.getElementById(field);
        const error = academicYearForm?.querySelector(
            `[data-error-for="${field}"]`,
        );
        input?.classList.add("is-invalid");
        if (error) {
            error.textContent = messages[0];
            error.classList.add("d-block");
        }
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
            submitBtn.textContent = document.getElementById("academic_year_id")
                ?.value
                ? "Update"
                : "Create";
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
        try {
            result = JSON.parse(responseText);
        } catch {
            throw new Error("Unable to save academic year. Please try again.");
        }

        if (response.status === 422) {
            const isDuplicate = result.message?.startsWith(
                "Unable to save Academic Year.",
            );
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
            alert.textContent =
                error.message || "Unable to save academic year.";
            alert.classList.remove("d-none");
        }
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = document.getElementById("academic_year_id")
                ?.value
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
        lifecycleStatusSelect.value =
            academicYear.lifecycle_status ??
            (academicYear.status ? "pending" : "finished");
    if (periodTypeInput)
        periodTypeInput.value = academicYear.period_type ?? "regular";
    if (startDateInput) startDateInput.value = academicYear.start_date ?? "";
    if (endDateInput) endDateInput.value = academicYear.end_date ?? "";
    updateSummerFields();

    if (modalTitle) modalTitle.textContent = "Edit Academic Year";
    if (submitBtn) submitBtn.textContent = "Update";
    if (bsModal) bsModal.show();
};

const viewAcademicYear = (id) => {
    const year = allAcademicYears.find((item) => item.id === id);
    const body = document.getElementById("academicYearViewBody");
    if (!year || !body) return;

    const escapeHtml = (value) => String(value ?? "-").replace(/[&<>"']/g, (character) => ({
        "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;",
    })[character]);
    const type = year.period_type === "summer" ? "Summer School" : "Regular Academic Year";
    const status = year.deleted_at ? "Deleted" : (year.lifecycle_status || "-");

    body.innerHTML = `
        <div class="row g-3">
            <div class="col-md-6"><strong>Academic Year</strong><div>${escapeHtml(year.academic_year)}</div></div>
            <div class="col-md-6"><strong>Type</strong><div>${type}</div></div>
            <div class="col-md-6"><strong>AY Code</strong><div>${escapeHtml(year.ay_code)}</div></div>
            <div class="col-md-6"><strong>Status</strong><div class="text-capitalize">${escapeHtml(status)}</div></div>
            <div class="col-md-6"><strong>Start Date</strong><div>${escapeHtml(year.start_date)}</div></div>
            <div class="col-md-6"><strong>End Date</strong><div>${escapeHtml(year.end_date)}</div></div>
            <div class="col-12"><strong>Description</strong><div>${escapeHtml(year.description)}</div></div>
        </div>`;

    bsViewModal?.show();
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
            throw new Error(
                data.message || "Unable to set started academic year.",
            );
        }

        showSuccess("Updated", data.message);
        fetchAcademicYears();
    } catch (error) {
        showError(
            "Error",
            error.message || "Unable to set started academic year.",
        );
    }
};

const createNextAcademicYear = async (id) => {
    const year = allAcademicYears.find((item) => item.id === id);
    if (!year || year.period_type !== "regular") return;

    const result = await showConfirm(
        "Create Next Academic Year",
        `Create ${getNextAcademicYearLabel(year.academic_year)} as Pending?`,
        "Create",
        "Cancel",
    );

    if (!result.isConfirmed) return;

    try {
        const response = await fetch(`/academic-years/${id}/create-next`, {
            method: "POST",
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": csrfToken,
            },
        });
        const data = await response.json();

        if (!response.ok || data.status !== "success") {
            throw new Error(data.message || "Unable to create next academic year.");
        }

        showSuccess("Created", data.message);
        fetchAcademicYears();
    } catch (error) {
        showError("Error", error.message || "Unable to create next academic year.");
    }
};

const getNextAcademicYearLabel = (academicYear) => {
    const match = String(academicYear ?? "").match(/^(\d{4})([^\d]+)(\d{4})$/);
    if (!match || Number(match[3]) !== Number(match[1]) + 1) {
        return "the next academic year";
    }

    return `${Number(match[1]) + 1}${match[2]}${Number(match[3]) + 1}`;
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
            throw new Error(responseData.message || "Unable to delete academic year.");
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
        showAlert({
            type: "warning",
            title: "Cannot Delete",
            message: error.message || "Unable to delete academic year.",
            background: "#fff3cd",
            color: "#664d03",
        });
    }
};

const restoreAcademicYear = async (id) => {
    try {
        const response = await fetch(`/academic-years/${id}/restore`, {
            method: "POST",
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": csrfToken,
            },
        });
        const data = await response.json();

        if (!response.ok || data.status !== "success") {
            throw new Error(data.message || "Unable to restore academic year.");
        }

        showSuccess("Restored", data.message);
        fetchAcademicYears();
    } catch (error) {
        showError("Restore Failed", error.message || "Unable to restore academic year.");
    }
};

//---- Fetch All Academic Years ----//
const fetchAcademicYears = async (page = 1, perPage = null) => {
    let row = "";
    let mobileCards = "";
    const searchValue = searchInput ? searchInput.value : "";
    const perPageValue =
        perPage !== null ? perPage : parseInt(perPageInput?.value ?? 10);

    try {
        const response = await fetch(
            `/academic-years/fetch?page=${page}&perPage=${perPageValue}&include_deleted=${includeDeletedAcademicYears ? 1 : 0}&search=${encodeURIComponent(searchValue)}&academic_year_filter=${encodeURIComponent(academicYearFilter?.value ?? "")}&period_type_filter=${encodeURIComponent((academicYearTypeFilter?.value ?? "").toLowerCase().replace("summer school", "summer").replace("regular", "regular"))}&sortBy=${encodeURIComponent(sortBy)}&sortDir=${sortDir}`,
        );
        if (!response.ok) {
            throw new Error(`Fetch failed with status ${response.status}`);
        }
        const result = await response.json();
        allAcademicYears = result.data;

        filterComboboxes.forEach((combo) => {
            combo.options = combo.type === "academic-year"
                ? (result.filterOptions?.academicYears ?? []).map((year) => ({ value: year, label: year }))
                : (result.filterOptions?.periodTypes ?? []).map((type) => ({
                    value: type,
                    label: type === "summer" ? "Summer School" : "Regular",
                }));
            combo.render(combo.options);
        });

        const periodTypeLabel = (type) => type === "summer" ? "Summer School" : "Regular";
        const escapeHtml = (value) => String(value ?? "").replace(/[&<>"']/g, (character) => ({
            "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;",
        })[character]);

        const lifecycleBadge = (status) => {
            const badges = {
                draft: "<span class='badge bg-secondary-lt'>Draft</span>",
                pending: "<span class='badge bg-orange-lt'>Pending</span>",
                started: "<span class='badge bg-green-lt'>Started</span>",
                finished: "<span class='badge bg-red-lt'>Finished</span>",
                archived: "<span class='badge bg-dark-lt'>Archived</span>",
            };
            return (
                badges[status] ||
                "<span class='badge bg-secondary-lt'>Draft</span>"
            );
        };

        const rowNumber = (result.current_page - 1) * perPageValue;

        allAcademicYears.forEach((year, index) => {
            const isDeleted = Boolean(year.deleted_at);
            const hasLinkedData = Number(year.linked_data_count ?? 0) > 0;
            const deleteButton = hasLinkedData
                ? `<span title="Cannot delete an academic year with linked data"><button type="button" class="btn btn-danger btn-sm" disabled><i class="ti ti-trash icon"></i>Delete</button></span>`
                : `<button onclick="academicYears.deleteAcademicYear(${year.id})" class="btn btn-danger btn-sm"><i class="ti ti-trash icon"></i>Delete</button>`;
            row += `
            <tr>
                <td>${rowNumber + index + 1}</td>
                <td>${year.academic_year}</td>
                <td>${periodTypeLabel(year.period_type)}</td>
                <td>${escapeHtml(year.ay_code ?? "")}</td>
                <td>${year.start_date ?? ""}</td>
                <td>${year.end_date ?? ""}</td>
                <td>${year.description ?? ""}</td>
                <td>${isDeleted ? "<span class='badge bg-orange-lt'>Deleted</span>" : lifecycleBadge(year.lifecycle_status)}</td>
                <td class="text-center">
                    ${isDeleted ? "<span class='text-secondary'>—</span>" : year.lifecycle_status !== "started" ? `<button onclick="academicYears.setCurrentAcademicYear(${year.id})" class="btn btn-success btn-sm"><i class="ti ti-check icon"></i>Set Started</button>` : "<span class='badge bg-green-lt'>Started</span>"}
                </td>
                <td class="text-center">
                    ${isDeleted ? `<button onclick="academicYears.restoreAcademicYear(${year.id})" class="btn btn-success btn-sm"><i class="ti ti-refresh icon"></i>Restore</button>` : `${year.period_type === "regular" ? `<button onclick="academicYears.createNextAcademicYear(${year.id})" class="btn btn-outline-primary btn-sm" title="Create the next academic year"><i class="ti ti-calendar-plus icon"></i>Next Year</button>` : ""}
                    <button onclick="academicYears.openEditModal(${year.id})" class="btn btn-primary btn-sm"><i class="ti ti-pencil icon"></i>Edit</button>
                    ${deleteButton}`}
                </td>
            </tr>`;
            mobileCards += `<article class="academic-year-mobile-card">
                <div class="academic-year-mobile-card-head"><span class="academic-year-mobile-number">${rowNumber + index + 1}</span><div><strong>${escapeHtml(year.academic_year)}</strong><span>${periodTypeLabel(year.period_type)}</span></div>${isDeleted ? "<span class='badge bg-orange-lt'>Deleted</span>" : lifecycleBadge(year.lifecycle_status)}</div>
                <div class="academic-year-mobile-details"><div><span>AY Code</span><strong>${escapeHtml(year.ay_code ?? "-")}</strong></div><div><span>Start Date</span><strong>${escapeHtml(year.start_date ?? "-")}</strong></div><div><span>End Date</span><strong>${escapeHtml(year.end_date ?? "-")}</strong></div>${year.description ? `<div class="academic-year-mobile-description"><span>Description</span><strong>${escapeHtml(year.description)}</strong></div>` : ""}</div>
                <div class="academic-year-mobile-actions">${isDeleted ? `<button onclick="academicYears.restoreAcademicYear(${year.id})" class="btn btn-success"><i class="ti ti-refresh me-1"></i>Restore</button>` : `${year.period_type === "regular" ? `<button onclick="academicYears.createNextAcademicYear(${year.id})" class="btn btn-outline-primary"><i class="ti ti-calendar-plus me-1"></i>Next Year</button>` : ""}<button onclick="academicYears.openEditModal(${year.id})" class="btn btn-primary"><i class="ti ti-pencil me-1"></i>Edit</button>${hasLinkedData ? `<button class="btn btn-outline-danger" disabled><i class="ti ti-trash me-1"></i>Delete</button>` : `<button onclick="academicYears.deleteAcademicYear(${year.id})" class="btn btn-outline-danger"><i class="ti ti-trash me-1"></i>Delete</button>`}`}</div>
            </article>`;
        });
        if (academicYearsTable) {
            academicYearsTable.innerHTML = row;
        }
        if (academicYearsMobileCards) academicYearsMobileCards.innerHTML = mobileCards || `<div class="academic-year-mobile-empty">No academic years found.</div>`;
        renderPagination(
            result,
            "academic-years-pagination-container",
            "academic-years-per-page",
            fetchAcademicYears,
        );
        renderPageInfo(result);
        updateSortButtons();
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
    searchInput.addEventListener("input", () => fetchAcademicYears(1, parseInt(perPageInput?.value ?? 10)));
}

const fetchWithFilters = () => fetchAcademicYears(1, parseInt(perPageInput?.value ?? 10));
const updateSortButtons = () => {
    document.querySelectorAll(".table-sort-button").forEach((button) => {
        const isSelected = button.dataset.sort === sortBy;
        button.innerHTML = `${button.dataset.label || button.textContent.replace(/[↕↑↓]/g, "").trim()} ${isSelected ? (sortDir === "asc" ? "↑" : "↓") : "↕"}`;
        button.dataset.label = button.dataset.label || button.textContent.replace(/[↕↑↓]/g, "").trim();
    });
};
document.querySelectorAll(".table-sort-button").forEach((button) => {
    button.dataset.label = button.textContent.trim();
    button.addEventListener("click", () => {
        const selectedSort = button.dataset.sort;
        sortDir = sortBy === selectedSort && sortDir === "asc" ? "desc" : "asc";
        sortBy = selectedSort;
        fetchWithFilters();
    });
});

    fetchAcademicYears();

window.academicYears = {
    openCreateModal,
    openEditModal,
    viewAcademicYear,
    deleteAcademicYear,
    createNextAcademicYear,
    restoreAcademicYear,
    setCurrentAcademicYear,
    fetchAcademicYears,
};
window.openCreateModal = openCreateModal;
