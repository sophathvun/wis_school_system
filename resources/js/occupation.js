import { renderPagination, renderPageInfo } from "./helpers/pagination.js";
import { showSuccess, showConfirm, showError, showWarning } from "./helpers/sweet-alert2.js";

const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const table = document.getElementById("occupationsTable");
const mobileCards = document.getElementById("occupationsMobileCards");
const search = document.getElementById("occupations-search");
const perPage = document.getElementById("occupations-per-page");
const form = document.getElementById("occupationForm");
const modal = new bootstrap.Modal(document.getElementById("occupationModal"));
const modalStatusToggle = document.getElementById("occupation-status-toggle");

let rows = [];
let currentSortBy = "occupation_name_en";
let currentSortDir = "asc";

const escapeHtml = (value = "") =>
    String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");

const statusToggleMarkup = (id, isActive) =>
    `<button type="button" class="status-toggle${isActive ? " is-active" : ""}" data-status="${id}" aria-pressed="${isActive ? "true" : "false"}"><span class="status-toggle-label">${isActive ? "ON" : "OFF"}</span><span class="status-toggle-knob"></span></button>`;

const linkedCount = (item) => Number(item.family_members_count || item.linked_count || 0);

const deleteButtonMarkup = (item, classes = "btn btn-danger btn-sm") => {
    const count = linkedCount(item);
    const title = count > 0 ? "This occupation is already linked to other data." : "Delete occupation";
    return `<button type="button" class="${classes}" data-delete="${item.id}" data-linked-count="${count}" title="${escapeHtml(title)}" aria-label="${escapeHtml(title)}"><i class="ti ti-trash"></i>${classes.includes("btn-sm") ? "" : ""}</button>`;
};
const setModalStatus = (isActive = true) => {
    const statusInput = document.getElementById("occupation_status");
    if (statusInput) statusInput.value = isActive ? "1" : "0";
    if (!modalStatusToggle) return;
    modalStatusToggle.classList.toggle("is-active", isActive);
    modalStatusToggle.setAttribute("aria-pressed", isActive ? "true" : "false");
    const label = modalStatusToggle.querySelector(".status-toggle-label");
    if (label) label.textContent = isActive ? "ON" : "OFF";
};
const reset = () => {
    form.reset();
    document.getElementById("occupation_id").value = "";
    setModalStatus(true);
    document.getElementById("occupationModalTitle").textContent = "New Occupation";
    form.querySelector("[data-alert]")?.classList.add("d-none");
};

const edit = (id) => {
    const item = rows.find((row) => Number(row.id) === Number(id));
    if (!item) return;

    document.getElementById("occupation_id").value = item.id;
    document.getElementById("occupation_name_en").value = item.occupation_name_en || "";
    document.getElementById("occupation_name_kh").value = item.occupation_name_kh || "";
    setModalStatus(!!item.status);
    document.getElementById("occupationModalTitle").textContent = "Edit Occupation";
    modal.show();
};

const remove = async (id) => {
    const item = rows.find((row) => Number(row.id) === Number(id));
    const count = item ? linkedCount(item) : 0;
    if (count > 0) {
        showWarning("Cannot delete occupation", "This occupation is already linked to other data. Please deactivate it instead.");
        return;
    }

    if (
        !(await showConfirm(
            "Delete Occupation",
            "Deactivate assigned occupations instead of deleting them.",
            "Delete",
            "Cancel",
        )).isConfirmed
    ) {
        return;
    }

    const response = await fetch(`/occupations/${id}`, {
        method: "DELETE",
        headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf },
    });
    const result = await response.json();
    if (!response.ok) {
        return showError("Unable to delete occupation", result.message || "Unable to delete occupation.");
    }

    showSuccess("Deleted", result.message);
    fetchRows();
};

const updateStatus = async (id) => {
    const item = rows.find((row) => Number(row.id) === Number(id));
    if (!item) return;

    const data = new FormData();
    data.append("occupation_id", item.id);
    data.append("occupation_name_en", item.occupation_name_en || "");
    data.append("occupation_name_kh", item.occupation_name_kh || "");
    data.append("status", item.status ? "0" : "1");

    const response = await fetch("/occupations/save", {
        method: "POST",
        headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf },
        body: data,
    });
    const result = await response.json();
    if (!response.ok) {
        return showError("Unable to update status", result.message || "Unable to update occupation status.");
    }

    showSuccess("Updated", result.message || "Occupation status updated.");
    fetchRows();
};

const bindRowActions = (root) => {
    root.querySelectorAll("[data-edit]").forEach((button) =>
        button.addEventListener("click", () => edit(Number(button.dataset.edit))),
    );
    root.querySelectorAll("[data-delete]").forEach((button) =>
        button.addEventListener("click", () => remove(Number(button.dataset.delete))),
    );
    root.querySelectorAll("[data-status]").forEach((button) =>
        button.addEventListener("click", () => updateStatus(Number(button.dataset.status))),
    );
};

const renderMobileCards = (offset = 0) => {
    if (!mobileCards) return;
    if (!rows.length) {
        mobileCards.innerHTML = `<div class="text-center text-secondary py-3">No occupations found.</div>`;
        return;
    }

    mobileCards.innerHTML = rows.map((item, index) => {
        const number = String(offset + index + 1).padStart(2, "0");
        return `<div class="occupation-mobile-card">
            <div class="occupation-card-top">
                <div class="occupation-card-number">${number}</div>
                ${statusToggleMarkup(item.id, !!item.status)}
            </div>
            <div class="occupation-card-grid">
                <div>
                    <div class="occupation-card-label">Occupation (English)</div>
                    <div class="occupation-card-name">${escapeHtml(item.occupation_name_en || "-")}</div>
                </div>
                <div>
                    <div class="occupation-card-label school-profile-khmer">មុខរបរ</div>
                    <div class="occupation-card-name school-profile-khmer">${escapeHtml(item.occupation_name_kh || "-")}</div>
                </div>
            </div>
            <div class="occupation-card-actions">
                <button type="button" class="btn btn-outline-primary" data-edit="${item.id}"><i class="ti ti-edit"></i></button>
                ${deleteButtonMarkup(item, "btn btn-outline-danger")}
            </div>
        </div>`;
    }).join("");

    bindRowActions(mobileCards);
};

const updateSortButtons = () => {
    document.querySelectorAll(".table-sort-button").forEach((button) => {
        const label = button.dataset.label || button.textContent.replace(/[↕↑↓]/g, "").trim();
        button.dataset.label = label;
        const isSelected = button.dataset.sort === currentSortBy;
        button.innerHTML = `${label} ${isSelected ? (currentSortDir === "asc" ? "↑" : "↓") : "↕"}`;
    });
};

const fetchRows = async (page = 1, size = null) => {
    const pageSize = size ?? parseInt(perPage.value, 10);
    const response = await fetch(
        `/occupations/fetch?page=${page}&perPage=${pageSize}&search=${encodeURIComponent(search.value)}&sortBy=${encodeURIComponent(currentSortBy)}&sortDir=${encodeURIComponent(currentSortDir)}`,
        { headers: { Accept: "application/json" } },
    );
    const result = await response.json();
    if (!response.ok) throw new Error(result.message || "Unable to load occupations.");

    rows = result.data || [];
    table.innerHTML = rows.length
        ? rows
              .map(
                  (item) =>
                      `<tr><td>${escapeHtml(item.occupation_name_en || "-")}</td><td class="school-profile-khmer">${escapeHtml(item.occupation_name_kh || "-")}</td><td>${statusToggleMarkup(item.id, !!item.status)}</td><td class="text-center"><button class="btn btn-primary btn-sm" data-edit="${item.id}">Edit</button> ${deleteButtonMarkup(item)}</td></tr>`,
              )
              .join("")
        : `<tr><td colspan="4" class="text-center">No occupations found.</td></tr>`;

    bindRowActions(table);
    renderMobileCards((result.current_page - 1) * pageSize);
    renderPagination(result, "occupations-pagination-container", "occupations-per-page", fetchRows);
    renderPageInfo(result);
    updateSortButtons();
};

modalStatusToggle?.addEventListener("click", () => {
    setModalStatus(document.getElementById("occupation_status")?.value !== "1");
});

document.querySelectorAll(".table-sort-button").forEach((button) => {
    button.dataset.label = button.textContent.trim();
    button.addEventListener("click", () => {
        const selectedSort = button.dataset.sort;
        if (!selectedSort) return;
        currentSortDir = currentSortBy === selectedSort && currentSortDir === "asc" ? "desc" : "asc";
        currentSortBy = selectedSort;
        fetchRows(1, parseInt(perPage.value, 10));
    });
});

document.getElementById("newOccupation")?.addEventListener("click", () => {
    reset();
    modal.show();
});

document.getElementById("printOccupations")?.addEventListener("click", () => {
    window.open(`/occupations/print?ts=${Date.now()}`, "_blank", "noopener");
});

document.getElementById("excelOccupations")?.addEventListener("click", () => {
    window.location.href = `/occupations/excel?ts=${Date.now()}`;
});

form?.addEventListener("submit", async (event) => {
    event.preventDefault();
    const response = await fetch("/occupations/save", {
        method: "POST",
        headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf },
        body: new FormData(form),
    });
    const result = await response.json();
    if (response.status === 422) {
        const alert = form.querySelector("[data-alert]");
        alert.textContent =
            result.message ||
            Object.values(result.errors || {})[0]?.[0] ||
            "Please correct the form.";
        alert.classList.remove("d-none");
        return;
    }
    if (!response.ok) {
        return showError("Unable to save occupation", result.message || "Unable to save occupation.");
    }
    modal.hide();
    showSuccess("Saved", result.message);
    fetchRows();
});

perPage?.addEventListener("change", () => fetchRows(1, parseInt(perPage.value, 10)));
search?.addEventListener("input", () => fetchRows(1, parseInt(perPage.value, 10)));

fetchRows().catch((error) => showError("Unable to load occupations", error.message));



