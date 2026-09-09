import { renderPagination, renderPageInfo } from "./helpers/pagination.js";
import { showSuccess, showConfirm, showError } from "./helpers/sweet-alert2.js";
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const form = document.getElementById("programForm"),
    modal = new bootstrap.Modal(document.getElementById("programModal")),
    table = document.getElementById("programsTable"),
    mobileCards = document.getElementById("programsMobileCards"),
    search = document.getElementById("programs-search"),
    perPage = document.getElementById("programs-per-page"),
    submit = document.getElementById("programSubmit"),
    title = document.getElementById("programModalTitle"),
    statusToggle = document.getElementById("programStatusToggle"),
    programIdInput = document.getElementById("program_id"),
    academicYearSelect = document.getElementById("academic_year_id"),
    educationLevelSelect = document.getElementById("education_level_id"),
    programNameInput = document.getElementById("program_name"),
    programCodeInput = document.getElementById("program_code"),
    descriptionInput = document.getElementById("description"),
    statusInput = document.getElementById("status");
let rows = [];
let optionsPromise = null;
const syncStatusToggle = (active) => {
    const value = active ? "1" : "0";
    statusInput.value = value;
    if (!statusToggle) return;
    statusToggle.classList.toggle("is-active", active);
    statusToggle.dataset.status = value;
    statusToggle.setAttribute("aria-pressed", String(active));
    statusToggle.querySelector(".status-toggle-label").textContent = active ? "ON" : "OFF";
};
statusToggle?.addEventListener("click", () => syncStatusToggle(statusInput.value !== "1"));
const alertError = (message) => {
    const a = form.querySelector("[data-alert]");
    a.textContent = message || "Please correct the errors below.";
    a.classList.remove("d-none");
};
const loadOptions = async () => {
    if (!optionsPromise) {
        optionsPromise = fetch("/programs/options")
            .then((response) => response.json())
            .catch((error) => {
                optionsPromise = null;
                throw error;
            });
    }
    const j = await optionsPromise;
    academicYearSelect.innerHTML = j.academicYears
        .map((x) => `<option value="${x.id}">${x.academic_year}</option>`)
        .join("");
    educationLevelSelect.innerHTML = j.levels
        .map((x) => `<option value="${x.id}">${x.level_name}</option>`)
        .join("");
    return j;
};
const openCreate = () => {
    form.reset();
    form.querySelector("[data-alert]").classList.add("d-none");
    programIdInput.value = "";
    syncStatusToggle(true);
    title.textContent = "Create Program";
    submit.textContent = "Create";
    modal.show();
    window.setTimeout(() => {
        loadOptions().catch((error) => alertError(error.message));
    }, 0);
};
const openEdit = (id) => {
    const row = rows.find((x) => x.id === id);
    if (!row) return;
    form.reset();
    form.querySelector("[data-alert]").classList.add("d-none");
    title.textContent = "Edit Program";
    submit.textContent = "Update";
    modal.show();
    window.setTimeout(async () => {
        try {
            await loadOptions();
            programIdInput.value = row.id;
        academicYearSelect.value = row.academic_year_id ?? "";
        educationLevelSelect.value = row.education_level_id;
        programNameInput.value = row.program_name;
        programCodeInput.value = row.program_code;
        descriptionInput.value = row.description ?? "";
            syncStatusToggle(Boolean(row.status));
        } catch (error) {
            alertError(error.message);
        }
    }, 0);
};
form.addEventListener("submit", async (e) => {
    e.preventDefault();
    submit.disabled = true;
    try {
        const r = await fetch("/programs/save", {
                method: "POST",
                headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf },
                body: new FormData(form),
            }),
            j = await r.json();
        if (r.status === 422) return alertError(j.message);
        if (!r.ok) throw Error(j.message);
        modal.hide();
        showSuccess("Saved", j.message);
        fetchRows();
    } catch (e) {
        alertError(e.message);
    } finally {
        submit.disabled = false;
    }
});
const remove = async (id) => {
    if (
        !(
            await showConfirm(
                "Delete Program",
                "Are you sure?",
                "Delete",
                "Cancel",
            )
        ).isConfirmed
    )
        return;
    const r = await fetch(`/programs/delete/${id}`, {
            method: "DELETE",
            headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf },
        }),
        j = await r.json();
    r.ok
        ? (showSuccess("Deleted", j.message), fetchRows())
        : showError("Error", j.message);
};
async function fetchRows(page = 1) {
    const r = await fetch(
            `/programs/fetch?page=${page}&perPage=${perPage.value}&search=${encodeURIComponent(search.value)}`,
        ),
        j = await r.json();
    rows = j.data;
    const escapeHtml = (value) => String(value ?? "").replace(/[&<>\"']/g, (character) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '\"': "&quot;", "'": "&#039;" })[character]);
    const offset = (j.current_page - 1) * Number(perPage.value || 10);
    if (mobileCards) {
        mobileCards.innerHTML = rows.length
            ? rows.map((x, index) => {
                const active = Boolean(x.status);
                const statusToggle = window.statusToggleMarkup?.("program", x.id, active) || `<button type="button" class="status-toggle ${active ? "is-active" : ""}" data-status-toggle data-status-entity="program" data-status-id="${x.id}" data-status="${active ? 1 : 0}" aria-pressed="${active ? "true" : "false"}"><span class="status-toggle-label">${active ? "ON" : "OFF"}</span><span class="status-toggle-knob"></span></button>`;
                return `<article class="program-mobile-card">
                    <div class="program-mobile-top-row">
                        <span class="program-mobile-number">${String(offset + index + 1).padStart(2, "0")}</span>
                        <div class="program-mobile-status">${statusToggle}</div>
                    </div>
                    <div class="program-mobile-columns">
                        <div class="program-mobile-column program-mobile-name-column">
                            <strong class="program-mobile-name">${escapeHtml(x.program_name)}</strong>
                            <span>Code ${escapeHtml(x.program_code ?? "-")}</span>
                        </div>
                        <div class="program-mobile-column program-mobile-level-column">
                            <strong>Level ${escapeHtml(x.education_level?.level_name ?? "-")}</strong>
                            <span>${escapeHtml(x.academic_year?.academic_year ?? "-")}</span>
                        </div>
                    </div>
                    <div class="program-mobile-actions"><button class="btn btn-primary" onclick="programsPage.edit(${x.id})"><i class="ti ti-pencil me-1"></i>Edit</button><button class="btn btn-outline-danger" onclick="programsPage.remove(${x.id})"><i class="ti ti-trash me-1"></i>Delete</button></div>
                </article>`;
            }).join("")
            : `<div class="program-mobile-empty">No programs found.</div>`;
    }
    table.innerHTML = rows.length
        ? rows
              .map(
                  (x) => {
                      const active = Boolean(x.status);
                      const statusToggle = window.statusToggleMarkup?.("program", x.id, active) || `<button type="button" class="status-toggle ${active ? "is-active" : ""}" data-status-toggle data-status-entity="program" data-status-id="${x.id}" data-status="${active ? 1 : 0}" aria-pressed="${active ? "true" : "false"}"><span class="status-toggle-label">${active ? "ON" : "OFF"}</span><span class="status-toggle-knob"></span></button>`;
                      return `<tr><td>${escapeHtml(x.program_name)}</td><td>${escapeHtml(x.program_code)}</td><td>${escapeHtml(x.academic_year?.academic_year ?? "-")}</td><td>${escapeHtml(x.education_level?.level_name ?? "-")}</td><td>${statusToggle}</td><td><button class="btn btn-primary btn-sm" onclick="programsPage.edit(${x.id})">Edit</button> <button class="btn btn-danger btn-sm" onclick="programsPage.remove(${x.id})">Delete</button></td></tr>`;
                  },
              )
              .join("")
        : `<tr><td colspan="6" class="text-center">No programs found.</td></tr>`;
    renderPagination(
        j,
        "programs-pagination-container",
        "programs-per-page",
        fetchRows,
    );
    renderPageInfo(j);
}
document.getElementById("newProgram").onclick = openCreate;
document.getElementById("programModal")?.addEventListener("shown.bs.modal", () => {
    ["academicYearSelect", "educationLevelSelect"].forEach((id) => {
        const select = document.getElementById(id);
        if (select && select.options.length && select.options[0].value === "") {
            select.remove(0);
        }
    });
});
perPage.onchange = () => fetchRows();
search.onkeyup = () => fetchRows();
fetchRows();
window.programsPage = { edit: openEdit, remove };




