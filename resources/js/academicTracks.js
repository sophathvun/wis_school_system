import { renderPagination, renderPageInfo } from "./helpers/pagination.js";
import { showSuccess, showConfirm, showError } from "./helpers/sweet-alert2.js";

const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const table = document.getElementById("academicTracksTable");
const mobileCards = document.getElementById("academicTracksMobileCards");
const search = document.getElementById("academic-tracks-search");
const perPage = document.getElementById("academic-tracks-per-page");
const form = document.getElementById("academicTrackForm");
const modal = new bootstrap.Modal(document.getElementById("academicTrackModal"));
const modalStatusToggle = document.getElementById("academic-track-status-toggle");
const gradeSelect = document.getElementById("academic_track_grade_id");
const streamSelect = document.getElementById("academic_track_stream_type");
const languageSelect = document.getElementById("academic_track_language");

let rows = [];
let currentSortBy = "grade_id";
let currentSortDir = "asc";
let gradeCombo = null;
const searchableCombos = new Map();

const escapeHtml = (value = "") =>
    String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");

const nice = (value = "") =>
    String(value)
        .replace(/_/g, " ")
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

const statusToggleMarkup = (id, isActive) =>
    `<button type="button" class="status-toggle ${isActive ? "is-active" : ""}" data-status-toggle data-status-entity="academic-track" data-status-id="${id}" data-status="${isActive ? 1 : 0}" aria-pressed="${isActive ? "true" : "false"}"><span class="status-toggle-label">${isActive ? "ON" : "OFF"}</span><span class="status-toggle-knob"></span></button>`;

const setModalStatus = (isActive = true) => {
    const statusInput = document.getElementById("academic_track_status");
    if (statusInput) statusInput.value = isActive ? "1" : "0";
    if (!modalStatusToggle) return;
    modalStatusToggle.classList.toggle("is-active", isActive);
    modalStatusToggle.setAttribute("aria-pressed", isActive ? "true" : "false");
    const label = modalStatusToggle.querySelector(".status-toggle-label");
    if (label) label.textContent = isActive ? "ON" : "OFF";
};

const refreshGradeCombo = () => {
    if (!gradeCombo || !gradeSelect) return;
    const selected = gradeSelect.selectedOptions[0];
    gradeCombo.selected.textContent = selected?.textContent?.trim() || "";
    gradeCombo.results.querySelectorAll(".location-combobox-option").forEach((option) => {
        option.classList.toggle("is-selected", option.dataset.value === gradeSelect.value);
    });
};

const renderGradeOptions = () => {
    if (!gradeCombo || !gradeSelect) return;
    const term = gradeCombo.search.value.trim().toLowerCase();
    const options = Array.from(gradeSelect.options).filter((option) =>
        !term || option.textContent.toLowerCase().includes(term),
    );
    gradeCombo.results.innerHTML = options.length
        ? options
              .map((option) => `<button type="button" class="location-combobox-option${option.value === gradeSelect.value ? " is-selected" : ""}" data-value="${escapeHtml(option.value)}">${escapeHtml(option.textContent.trim())}</button>`)
              .join("")
        : `<div class="text-secondary px-2 py-2">No grades found</div>`;
};

const setupGradeCombobox = () => {
    if (!gradeSelect || gradeSelect.dataset.searchableReady === "1") return;
    gradeSelect.dataset.searchableReady = "1";
    gradeSelect.classList.add("d-none");
    gradeSelect.insertAdjacentHTML(
        "afterend",
        `<div class="location-combobox academic-track-grade-combobox"><button type="button" class="location-combobox-toggle"><span class="location-combobox-selected"></span><i class="ti ti-chevron-down"></i></button><div class="location-combobox-menu d-none"><input type="search" class="form-control location-combobox-search" placeholder="Search Grade"><div class="location-combobox-results"></div></div></div>`,
    );
    const wrapper = gradeSelect.nextElementSibling;
    gradeCombo = {
        wrapper,
        toggle: wrapper.querySelector(".location-combobox-toggle"),
        menu: wrapper.querySelector(".location-combobox-menu"),
        search: wrapper.querySelector(".location-combobox-search"),
        selected: wrapper.querySelector(".location-combobox-selected"),
        results: wrapper.querySelector(".location-combobox-results"),
    };

    gradeCombo.toggle.addEventListener("click", () => {
        gradeCombo.menu.classList.toggle("d-none");
        renderGradeOptions();
        if (!gradeCombo.menu.classList.contains("d-none")) {
            gradeCombo.search.value = "";
            gradeCombo.search.focus();
        }
    });
    gradeCombo.search.addEventListener("input", renderGradeOptions);
    gradeCombo.results.addEventListener("click", (event) => {
        const option = event.target.closest("[data-value]");
        if (!option) return;
        gradeSelect.value = option.dataset.value;
        gradeSelect.dispatchEvent(new Event("change", { bubbles: true }));
        gradeCombo.menu.classList.add("d-none");
    });
    gradeSelect.addEventListener("change", refreshGradeCombo);
    document.addEventListener("click", (event) => {
        if (!event.target.closest(".academic-track-grade-combobox")) gradeCombo.menu.classList.add("d-none");
    });
    renderGradeOptions();
    refreshGradeCombo();
};

const setupSearchableSelect = (select, label) => {
    if (!select || select.dataset.searchableReady === "1") return;
    select.dataset.searchableReady = "1";
    select.classList.add("d-none");
    select.insertAdjacentHTML(
        "afterend",
        `<div class="location-combobox academic-track-search-combobox"><button type="button" class="location-combobox-toggle"><span class="location-combobox-selected"></span><i class="ti ti-chevron-down"></i></button><div class="location-combobox-menu d-none"><input type="search" class="form-control location-combobox-search" placeholder="Search ${escapeHtml(label)}"><div class="location-combobox-results"></div></div></div>`,
    );

    const wrapper = select.nextElementSibling;
    const combo = {
        wrapper,
        toggle: wrapper.querySelector(".location-combobox-toggle"),
        menu: wrapper.querySelector(".location-combobox-menu"),
        search: wrapper.querySelector(".location-combobox-search"),
        selected: wrapper.querySelector(".location-combobox-selected"),
        results: wrapper.querySelector(".location-combobox-results"),
    };
    searchableCombos.set(select.id, combo);

    const refresh = () => {
        const selected = select.selectedOptions[0];
        combo.selected.textContent = selected?.textContent?.trim() || "";
        combo.results.querySelectorAll(".location-combobox-option").forEach((option) => {
            option.classList.toggle("is-selected", option.dataset.value === select.value);
        });
    };

    const render = () => {
        const term = combo.search.value.trim().toLowerCase();
        const options = Array.from(select.options).filter((option) =>
            !term || option.textContent.toLowerCase().includes(term),
        );
        combo.results.innerHTML = options.length
            ? options
                  .map((option) => `<button type="button" class="location-combobox-option${option.value === select.value ? " is-selected" : ""}" data-value="${escapeHtml(option.value)}">${escapeHtml(option.textContent.trim())}</button>`)
                  .join("")
            : `<div class="text-secondary px-2 py-2">No options found</div>`;
    };

    combo.toggle.addEventListener("click", () => {
        document.querySelectorAll("#academicTrackModal .location-combobox-menu").forEach((menu) => {
            if (menu !== combo.menu) menu.classList.add("d-none");
        });
        combo.menu.classList.toggle("d-none");
        render();
        if (!combo.menu.classList.contains("d-none")) {
            combo.search.value = "";
            combo.search.focus();
        }
    });
    combo.search.addEventListener("input", render);
    combo.results.addEventListener("click", (event) => {
        const option = event.target.closest("[data-value]");
        if (!option) return;
        select.value = option.dataset.value;
        select.dispatchEvent(new Event("change", { bubbles: true }));
        combo.menu.classList.add("d-none");
    });
    select.addEventListener("change", refresh);
    document.addEventListener("click", (event) => {
        if (!event.target.closest(".academic-track-search-combobox")) combo.menu.classList.add("d-none");
    });
    render();
    refresh();
};

const refreshSearchableSelect = (select) => {
    const combo = searchableCombos.get(select?.id);
    if (!combo || !select) return;
    const selected = select.selectedOptions[0];
    combo.selected.textContent = selected?.textContent?.trim() || "";
};

const reset = () => {
    form.reset();
    document.getElementById("academic_track_id").value = "";
    document.getElementById("academic_track_grade_id").value = "";
    refreshGradeCombo();
    document.getElementById("academic_track_stream_type").value = "science";
    document.getElementById("academic_track_language").value = "khmer";
    refreshSearchableSelect(streamSelect);
    refreshSearchableSelect(languageSelect);
    setModalStatus(true);
    document.getElementById("academicTrackModalTitle").textContent = "New Academic Track";
    form.querySelector("[data-alert]")?.classList.add("d-none");
};

const edit = (id) => {
    const item = rows.find((row) => Number(row.id) === Number(id));
    if (!item) return;
    document.getElementById("academic_track_id").value = item.id;
    document.getElementById("academic_track_name_en").value = item.name_en || "";
    document.getElementById("academic_track_name_kh").value = item.name_kh || "";
    document.getElementById("academic_track_code").value = item.code || "";
    document.getElementById("academic_track_grade_id").value = item.grade_id || "";
    refreshGradeCombo();
    document.getElementById("academic_track_stream_type").value = item.stream_type || "science";
    document.getElementById("academic_track_language").value = item.language || "khmer";
    refreshSearchableSelect(streamSelect);
    refreshSearchableSelect(languageSelect);
    setModalStatus(Boolean(item.status));
    document.getElementById("academicTrackModalTitle").textContent = "Edit Academic Track";
    form.querySelector("[data-alert]")?.classList.add("d-none");
    modal.show();
};

const remove = async (id) => {
    if (!(await showConfirm("Delete Academic Track", "Deactivate assigned tracks instead of deleting them.", "Delete", "Cancel")).isConfirmed) return;

    const response = await fetch(`/academic-tracks/${id}`, {
        method: "DELETE",
        headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf },
    });
    const result = await response.json();
    if (!response.ok) return showError("Unable to delete academic track", result.message || "Unable to delete academic track.");
    showSuccess("Deleted", result.message);
    fetchRows();
};

const bindRowActions = (root) => {
    root.querySelectorAll("[data-edit]").forEach((button) =>
        button.addEventListener("click", () => edit(Number(button.dataset.edit))),
    );
    root.querySelectorAll("[data-delete]").forEach((button) =>
        button.addEventListener("click", () => remove(Number(button.dataset.delete))),
    );
};

const sortIcon = () => {
    if (currentSortDir === "asc") return "&uarr;";
    return "&darr;";
};

const updateSortButtons = () => {
    document.querySelectorAll(".table-sort-button").forEach((button) => {
        const label = button.dataset.label || button.textContent.replace(/[↕↑↓]/g, "").trim();
        button.dataset.label = label;
        const isSelected = button.dataset.sort === currentSortBy;
        button.innerHTML = `${escapeHtml(label)} ${isSelected ? sortIcon() : "&varr;"}`;
    });
};

const renderMobileCards = (offset = 0) => {
    if (!mobileCards) return;
    if (!rows.length) {
        mobileCards.innerHTML = `<div class="text-center text-secondary py-3">No academic tracks found.</div>`;
        return;
    }

    mobileCards.innerHTML = rows.map((item, index) => {
        const number = String(offset + index + 1).padStart(2, "0");
        return `<div class="academic-track-mobile-card">
            <div class="academic-track-card-top">
                <div class="academic-track-card-number">${number}</div>
                ${statusToggleMarkup(item.id, Boolean(item.status))}
            </div>
            <div class="academic-track-card-grid">
                <div>
                    <div class="academic-track-card-label">Track</div>
                    <div class="academic-track-card-name school-profile-khmer">${escapeHtml(item.name_kh || "-")}</div>
                    <div class="academic-track-card-subtitle">${escapeHtml(item.name_en || "-")}</div>
                </div>
                <div>
                    <div class="academic-track-card-label">Grade</div>
                    <div class="academic-track-card-value">${escapeHtml(item.grade?.grade || "-")}</div>
                </div>
                <div>
                    <div class="academic-track-card-label">Code</div>
                    <div class="academic-track-card-value">${escapeHtml(item.code || "-")}</div>
                </div>
                <div>
                    <div class="academic-track-card-label">Stream / Language</div>
                    <div class="academic-track-card-value">${escapeHtml(nice(item.stream_type))}</div>
                    <div class="academic-track-card-subtitle">${escapeHtml(nice(item.language))}</div>
                </div>
            </div>
            <div class="academic-track-card-actions">
                <button type="button" class="btn btn-outline-primary" data-edit="${item.id}"><i class="ti ti-edit"></i></button>
                <button type="button" class="btn btn-outline-danger" data-delete="${item.id}"><i class="ti ti-trash"></i></button>
            </div>
        </div>`;
    }).join("");

    bindRowActions(mobileCards);
};

const fetchRows = async (page = 1, size = null) => {
    const pageSize = size ?? parseInt(perPage.value, 10);
    const response = await fetch(
        `/academic-tracks/fetch?page=${page}&perPage=${pageSize}&search=${encodeURIComponent(search.value)}&sortBy=${encodeURIComponent(currentSortBy)}&sortDir=${encodeURIComponent(currentSortDir)}`,
        { headers: { Accept: "application/json" } },
    );
    const result = await response.json();
    if (!response.ok) throw new Error(result.message || "Unable to load academic tracks.");

    rows = result.data || [];
    table.innerHTML = rows.length
        ? rows.map((item) => `
        <tr>
            <td><span class="school-profile-khmer">${escapeHtml(item.name_kh || "-")}</span><br><small class="text-secondary">${escapeHtml(item.name_en || "-")}</small></td>
            <td>${escapeHtml(item.code || "-")}</td>
            <td>${escapeHtml(item.grade?.grade || "-")}</td>
            <td>${escapeHtml(nice(item.stream_type))}</td>
            <td>${escapeHtml(nice(item.language))}</td>
            <td>${statusToggleMarkup(item.id, Boolean(item.status))}</td>
            <td class="text-center"><button class="btn btn-primary btn-sm" data-edit="${item.id}">Edit</button> <button class="btn btn-danger btn-sm" data-delete="${item.id}">Delete</button></td>
        </tr>`).join("")
        : `<tr><td colspan="7" class="text-center">No academic tracks found.</td></tr>`;

    bindRowActions(table);
    renderMobileCards((result.current_page - 1) * pageSize);
    renderPagination(result, "academic-tracks-pagination-container", "academic-tracks-per-page", fetchRows);
    renderPageInfo(result);
    updateSortButtons();
};

setupGradeCombobox();
setupSearchableSelect(streamSelect, "Stream");
setupSearchableSelect(languageSelect, "Language");

modalStatusToggle?.addEventListener("click", () => {
    setModalStatus(document.getElementById("academic_track_status")?.value !== "1");
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

document.getElementById("newAcademicTrack")?.addEventListener("click", () => {
    reset();
    modal.show();
});

document.getElementById("printAcademicTracks")?.addEventListener("click", () => {
    window.open(`/academic-tracks/print?ts=${Date.now()}`, "_blank", "noopener");
});

document.getElementById("excelAcademicTracks")?.addEventListener("click", () => {
    window.location.href = `/academic-tracks/excel?ts=${Date.now()}`;
});

form?.addEventListener("submit", async (event) => {
    event.preventDefault();
    const response = await fetch("/academic-tracks/save", {
        method: "POST",
        headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf },
        body: new FormData(form),
    });
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
