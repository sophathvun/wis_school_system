import { renderPagination, renderPageInfo } from "./helpers/pagination.js";
import { showSuccess, showConfirm, showError } from "./helpers/sweet-alert2.js";

const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const level = document.getElementById("location-level");
const table = document.getElementById("locationsTable");
const head = document.getElementById("locations-head");
const search = document.getElementById("locations-search");
const perPageInput = document.getElementById("locations-per-page");
const parent = document.getElementById("location-parent");
const parentSearch = document.getElementById("location-parent-search");
const parentResults = document.getElementById("location-parent-results");
const parentWrap = document.getElementById("location-parent-wrap");
const parentLabel = document.getElementById("location-parent-label");
const form = document.getElementById("locationForm");
const modal = new bootstrap.Modal(document.getElementById("locationModal"));

let rows = [];
let options = {};
let parentOptionsList = [];
let selectedParentId = "";
let sortBy = "name";
let sortDir = "asc";

const escapeHtml = (value = "") => String(value)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");

const labels = {
    country: "Country",
    province: "Province / City",
    district: "District / Khan",
    commune: "Commune",
    village: "Village",
};

const key = (name) => `${name}_name_en`;
const khKey = (name) => `${name}_name_kh`;

const parentData = {
    province: ["countries", "Country"],
    district: ["provinces", "Province / City"],
    commune: ["districts", "District / Khan"],
    village: ["communes", "Commune"],
};

const relationType = {
    province: "country",
    district: "province",
    commune: "district",
    village: "commune",
};

const ensureCountryNationalityFields = () => {
    const extra = document.getElementById("country-extra");
    if (!extra || document.getElementById("nationality-name-en")) return;
    extra.insertAdjacentHTML("beforeend", '<div class="col-6"><label class="form-label">Nationality (English)</label><input id="nationality-name-en" class="form-control"></div><div class="col-6"><label class="form-label">Nationality (Khmer)</label><input id="nationality-name-kh" class="form-control school-profile-khmer"></div><div class="col-12"><label class="form-label">Flag Image</label><div class="logo-dropzone" id="countryFlagDropzone" tabindex="0"><i class="ti ti-cloud-upload logo-dropzone-icon"></i><div><strong>Drag and drop flag image here</strong></div><div class="text-secondary">or click to upload a file</div><input type="file" class="d-none" id="country-flag" accept="image/jpeg,image/png,image/webp"><img id="country-flag-preview" class="d-none mt-2" style="max-width:90px;max-height:60px;object-fit:contain" alt="Flag preview"></div></div>');
    const dropzone = document.getElementById("countryFlagDropzone");
    const input = document.getElementById("country-flag");
    const preview = document.getElementById("country-flag-preview");
    const showPreview = (file) => { if (!file) return; preview.src = URL.createObjectURL(file); preview.classList.remove("d-none"); };
    input?.addEventListener("change", () => showPreview(input.files?.[0]));
    dropzone?.addEventListener("click", () => input?.click());
    dropzone?.addEventListener("keydown", (event) => { if (event.key === "Enter" || event.key === " ") input?.click(); });
    dropzone?.addEventListener("dragover", (event) => { event.preventDefault(); dropzone.classList.add("is-dragging"); });
    dropzone?.addEventListener("dragleave", () => dropzone.classList.remove("is-dragging"));
    dropzone?.addEventListener("drop", (event) => { event.preventDefault(); dropzone.classList.remove("is-dragging"); const file = event.dataTransfer.files?.[0]; if (!file) return; const transfer = new DataTransfer(); transfer.items.add(file); input.files = transfer.files; showPreview(file); });
};

const loadOptions = async () => {
    options = await (await fetch("/locations/options")).json();
};

const optionText = (item, nameKey, khKeyName) => item[khKeyName] ? `${item[khKeyName]} - ${item[nameKey]}` : item[nameKey];

const populateSelect = (select, items, placeholder, nameKey, khKeyName, selectedValue = "") => {
    if (!select) return;
    select.innerHTML = `<option value="">${placeholder}</option>` + items.map((item) => `<option value="${item.id}">${optionText(item, nameKey, khKeyName)}</option>`).join("");
    select.value = selectedValue ? String(selectedValue) : "";
};

const getParentNameKeys = () => {
    if (level.value === "province") return ["country_name_en", "country_name_kh"];
    if (level.value === "district") return ["province_name_en", "province_name_kh"];
    if (level.value === "commune") return ["district_name_en", "district_name_kh"];
    return ["commune_name_en", "commune_name_kh"];
};

const renderParentOptions = () => {
    if (!parent || !parentResults) return;
    const [nameKey, khKeyName] = getParentNameKeys();
    const term = parentSearch?.value.trim().toLowerCase() || "";
    const items = term
        ? parentOptionsList.filter((item) => {
            const en = String(item[nameKey] ?? "").toLowerCase();
            const kh = String(item[khKeyName] ?? "").toLowerCase();
            return en.includes(term) || kh.includes(term);
        })
        : parentOptionsList;

    parent.innerHTML = `<option value="">Select ${parentLabel.textContent}</option>` + items.map((item) => `<option value="${item.id}">${optionText(item, nameKey, khKeyName)}</option>`).join("");
    parentResults.innerHTML = items.length
        ? items.map((item) => `<button type="button" class="list-group-item list-group-item-action bg-dark text-light border-secondary" data-parent-id="${item.id}" data-parent-label="${optionText(item, nameKey, khKeyName)}">${optionText(item, nameKey, khKeyName)}</button>`).join("")
        : `<div class="list-group-item bg-dark text-secondary border-secondary">No communes found</div>`;
    parentResults.style.display = level.value === "village" ? "block" : "none";
};

const setParentOptions = () => {
    const config = parentData[level.value];
    parentWrap.classList.toggle("d-none", !config);
    if (parentSearch) parentSearch.style.display = level.value === "village" ? "block" : "none";
    if (parentResults) parentResults.style.display = level.value === "village" ? "block" : "none";
    if (parent) parent.style.display = level.value === "village" ? "none" : "block";
    if (parentSearch) parentSearch.value = "";
    selectedParentId = "";
    if (!config) return;

    parentLabel.textContent = config[1];
    parentOptionsList = options[config[0]] || [];
    if (level.value === "village") {
        renderParentOptions();
        parentSearch.focus();
    } else {
        const [nameKey, khKeyName] = getParentNameKeys();
        parent.innerHTML = `<option value="">Select ${config[1]}</option>` + parentOptionsList.map((item) => `<option value="${item.id}">${optionText(item, nameKey, khKeyName)}</option>`).join("");
    }
};

const relationCell = (item) => {
    const type = relationType[level.value];
    if (!type) return "";
    const relation = item[type];
    const flag = type === "country" && relation?.flag_path ? `<img src="/${relation.flag_path}" alt="${relation.country_name_en}" class="location-flag me-2">` : "";
    return `<td><div class="d-flex align-items-center">${flag}<div><div class="school-profile-khmer">${relation?.[`${type}_name_kh`] ?? ""}</div><div>${relation?.[`${type}_name_en`] ?? "-"}</div></div></div></td>`;
};

const countryCell = (item) => {
    const country = level.value === "district" ? item.province?.country : level.value === "commune" ? item.district?.province?.country : null;
    if (!country) return "<td>-</td>";
    return `<td><div class="d-flex align-items-center"><img src="/${country.flag_path}" alt="${country.country_name_en}" class="location-flag me-2"><div><div class="school-profile-khmer">${country.country_name_kh ?? ""}</div><div>${country.country_name_en}</div></div></div></td>`;
};

const ancestorCell = (item, type) => {
    const relation = type === "district"
        ? item.commune?.district
        : type === "province"
            ? item.commune?.district?.province
            : item.commune?.district?.province?.country;
    if (!relation) return "<td>-</td>";
    const flag = type === "country" && relation.flag_path ? `<img src="/${relation.flag_path}" alt="${relation.country_name_en}" class="location-flag me-2">` : "";
    return `<td><div class="d-flex align-items-center">${flag}<div><div class="school-profile-khmer">${relation[`${type}_name_kh`] ?? ""}</div><div>${relation[`${type}_name_en`] ?? "-"}</div></div></div></td>`;
};

const renderHead = () => {
    const parentTitle = parentData[level.value]?.[1];
    const villageColumns = level.value === "village"
        ? `<th><button type="button" class="table-sort" data-sort="district">District / Khan</button></th><th><button type="button" class="table-sort" data-sort="province">Province / City</button></th><th><button type="button" class="table-sort" data-sort="country">Country</button></th>`
        : "";

    head.innerHTML = `<tr><th>No.</th><th><button type="button" class="table-sort" data-sort="name">${labels[level.value]}</button></th>${level.value === "country" ? "<th>Nationality</th><th>Flag</th>" : parentTitle ? `<th><button type="button" class="table-sort" data-sort="parent">${parentTitle}</button></th>${["district", "commune"].includes(level.value) ? `<th><button type="button" class="table-sort" data-sort="country">Country</button></th>` : ""}${villageColumns}` : ""}<th>Status</th><th class="text-center">Actions</th></tr>`;

    head.querySelectorAll("[data-sort]").forEach((button) => {
        button.addEventListener("click", () => {
            const selected = button.dataset.sort;
            sortDir = sortBy === selected && sortDir === "asc" ? "desc" : "asc";
            sortBy = selected;
            fetchRows(1, parseInt(perPageInput.value));
        });
    });
};

const fetchRows = async (page = 1, perPage = null) => {
    const size = perPage ?? parseInt(perPageInput.value);
    const params = new URLSearchParams({
        level: level.value,
        page,
        perPage: size,
        sortBy,
        sortDir,
        search: search.value,
    });

    const j = await (await fetch(`/locations/fetch?${params.toString()}`)).json();
    rows = j.data;
    const offset = (j.current_page - 1) * size;
    const name = key(level.value);
    const kh = khKey(level.value);
    const config = parentData[level.value];

    table.innerHTML = rows.length
        ? rows.map((item, index) => {
            const villageAncestors = level.value === "village" ? `${ancestorCell(item, "district")}${ancestorCell(item, "province")}${ancestorCell(item, "country")}` : "";
            return `<tr><td>${offset + index + 1}</td><td class="location-name-cell"><div class="school-profile-khmer">${escapeHtml(item[kh] ?? "")}</div><div>${escapeHtml(item[name] ?? "")}</div></td>${level.value === "country" ? `<td><div class="school-profile-khmer">${escapeHtml(item.nationality_name_kh ?? "-")}</div><div>${escapeHtml(item.nationality_name_en ?? "-")}</div></td><td>${item.flag_path ? `<img src="/${item.flag_path}" alt="${escapeHtml(item[name] ?? "")}" style="width:28px;height:20px;object-fit:contain">` : "-"}</td>` : config ? relationCell(item) : ""}${["district", "commune"].includes(level.value) ? countryCell(item) : ""}${villageAncestors}<td>${statusToggleMarkup(level.value, item.id, !!item.status)}</td><td class="text-center"><button class="btn btn-primary btn-sm" onclick="locationsPage.edit(${item.id})">Edit</button> <button class="btn btn-danger btn-sm" onclick="locationsPage.remove(${item.id})">Delete</button></td></tr>`;
        }).join("")
        : `<tr><td colspan="10" class="text-center">No locations found.</td></tr>`;

    renderPagination(j, "locations-pagination-container", "locations-per-page", fetchRows);
    renderPageInfo(j);
};

const resetForm = () => {
    ensureCountryNationalityFields();
    form.reset();
    document.getElementById("location_id").value = "";
    const flagPreview = document.getElementById("country-flag-preview");
    if (flagPreview) { flagPreview.src = ""; flagPreview.classList.add("d-none"); }
    document.querySelector("[data-alert]").classList.add("d-none");
    setParentOptions();
    document.getElementById("country-extra").classList.toggle("d-none", level.value !== "country");
};

const openCreate = async () => {
    await loadOptions();
    resetForm();
    document.getElementById("locationModalTitle").textContent = `Create ${labels[level.value]}`;
    modal.show();
};

const edit = async (id) => {
    await loadOptions();
    const item = rows.find((row) => row.id === id);
    if (!item) return;

    resetForm();
    document.getElementById("location_id").value = id;
    document.getElementById("location-name-en").value = item[key(level.value)] ?? "";
    document.getElementById("location-name-kh").value = item[khKey(level.value)] ?? "";
    document.getElementById("location-status").value = item.status ? "1" : "0";

    if (level.value === "country") {
        document.getElementById("country-code").value = item.country_code ?? "";
        document.getElementById("flag-path").value = item.flag_path ?? "";
        const flagPreview = document.getElementById("country-flag-preview");
        if (flagPreview && item.flag_path) { flagPreview.src = item.flag_path.startsWith("storage/") ? `/${item.flag_path}` : `/${item.flag_path}`; flagPreview.classList.remove("d-none"); }
        document.getElementById("nationality-name-en").value = item.nationality_name_en ?? "";
        document.getElementById("nationality-name-kh").value = item.nationality_name_kh ?? "";
    } else {
        const parentId = item[parentData[level.value][0].replace("s", "") + "_id"] ?? item[`${level.value === "province" ? "country" : level.value === "district" ? "province" : level.value === "commune" ? "district" : "commune"}_id`];
        if (level.value === "village") {
            selectedParentId = String(parentId ?? "");
            const matched = parentOptionsList.find((row) => String(row.id) === selectedParentId);
            if (matched) parentSearch.value = optionText(matched, "commune_name_en", "commune_name_kh");
            renderParentOptions();
        } else {
            parent.value = parentId;
        }
    }

    document.getElementById("locationModalTitle").textContent = `Edit ${labels[level.value]}`;
    modal.show();
};

form.addEventListener("submit", async (e) => {
    e.preventDefault();

    const payload = {
        level: level.value,
        id: document.getElementById("location_id").value,
        name_en: document.getElementById("location-name-en").value,
        name_kh: document.getElementById("location-name-kh").value,
        status: document.getElementById("location-status").value,
    };

    if (level.value === "country") {
        Object.assign(payload, {
            country_code: document.getElementById("country-code").value,
            flag_path: document.getElementById("flag-path").value,
            nationality_name_en: document.getElementById("nationality-name-en")?.value || "",
            nationality_name_kh: document.getElementById("nationality-name-kh")?.value || "",
        });
    } else {
        payload.parent_id = level.value === "village" ? selectedParentId : parent.value;
    }

    const requestBody = level.value === "country" ? (() => { const body = new FormData(); Object.entries(payload).forEach(([key, value]) => body.append(key, value ?? "")); const file = document.getElementById("country-flag")?.files?.[0]; if (file) body.append("flag_image", file); return body; })() : JSON.stringify(payload);
    const headers = { Accept: "application/json", "X-CSRF-TOKEN": csrf };
    if (level.value !== "country") headers["Content-Type"] = "application/json";
    const r = await fetch("/locations/save", {
        method: "POST",
        headers,
        body: requestBody,
    });
    const j = await r.json();

    if (!r.ok) {
        document.querySelector("[data-alert]").textContent = j.message || "Unable to save location.";
        document.querySelector("[data-alert]").classList.remove("d-none");
        return;
    }

    modal.hide();
    showSuccess("Saved", j.message);
    await loadOptions();
    setParentOptions();
    fetchRows();
});

const remove = async (id) => {
    if (!(await showConfirm("Delete Location", "Delete this location and its child locations?", "Delete", "Cancel")).isConfirmed) return;

    const r = await fetch(`/locations/delete/${id}?level=${level.value}`, {
        method: "DELETE",
        headers: {
            Accept: "application/json",
            "X-CSRF-TOKEN": csrf,
        },
    });
    const j = await r.json();

    if (r.ok) {
        showSuccess("Deleted", j.message);
        loadOptions().then(() => {
            setParentOptions();
            fetchRows();
        });
    } else {
        showError("Error", j.message);
    }
};

level.addEventListener("change", async () => {
    await loadOptions();
    sortBy = "name";
    sortDir = "asc";
    setParentOptions();
    renderHead();
    fetchRows(1, parseInt(perPageInput.value));
});

parentSearch?.addEventListener("input", renderParentOptions);
parentResults?.addEventListener("click", (event) => {
    const button = event.target.closest("[data-parent-id]");
    if (!button) return;
    selectedParentId = button.dataset.parentId || "";
    parentSearch.value = button.dataset.parentLabel || "";
    parentResults.classList.add("d-none");
});

perPageInput.addEventListener("change", () => fetchRows(1, parseInt(perPageInput.value)));
search.addEventListener("input", () => fetchRows(1, parseInt(perPageInput.value)));
document.getElementById("newLocation").addEventListener("click", openCreate);

window.locationsPage = { edit, remove };

const initialize = async () => {
    await loadOptions();
    setParentOptions();
    renderHead();
    fetchRows(1, parseInt(perPageInput.value));
};

if (document.readyState === "complete") {
    initialize();
} else {
    window.addEventListener("load", initialize, { once: true });
}
