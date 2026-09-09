import { renderPagination, renderPageInfo } from "./helpers/pagination.js";
import { showSuccess, showConfirm, showError } from "./helpers/sweet-alert2.js";

const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const level = document.getElementById("location-level");
const levelToggle = document.getElementById("location-level-toggle");
const levelMenu = document.getElementById("location-level-menu");
const levelSearch = document.getElementById("location-level-search");
const levelResults = document.getElementById("location-level-results");
const levelSelected = document.getElementById("location-level-selected");
const table = document.getElementById("locationsTable");
const mobileCards = document.getElementById("locationsMobileCards");
const head = document.getElementById("locations-head");
const search = document.getElementById("locations-search");
const perPageInput = document.getElementById("locations-per-page");
const filterRow = document.getElementById("location-filter-row");
const parent = document.getElementById("location-parent");
const parentToggle = document.getElementById("location-parent-toggle");
const parentMenu = document.getElementById("location-parent-menu");
const parentSearch = document.getElementById("location-parent-search");
const parentResults = document.getElementById("location-parent-results");
const parentSelected = document.getElementById("location-parent-selected");
const parentWrap = document.getElementById("location-parent-wrap");
const parentLabel = document.getElementById("location-parent-label");
const form = document.getElementById("locationForm");
const modal = new bootstrap.Modal(document.getElementById("locationModal"));
const modalStatusToggle = document.getElementById("location-status-toggle");

let rows = [];
let options = {};
let parentOptionsList = [];
let selectedParentId = "";
let sortBy = "name";
let sortDir = "asc";
let locationsPrintWindow = null;

const filterSelects = {};
const levelOptionsList = Array.from(level?.options || []).map((option) => ({ value: option.value, label: option.textContent }));

const escapeHtml = (value = "") =>
    String(value)
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

const filterConfigs = {
    province: [
        ["country_id", "countries", "Country", "country_name_en", "country_name_kh"],
        ["province_id", "provinces", "Province / City", "province_name_en", "province_name_kh"],
    ],
    district: [
        ["country_id", "countries", "Country", "country_name_en", "country_name_kh"],
        ["province_id", "provinces", "Province / City", "province_name_en", "province_name_kh"],
        ["district_id", "districts", "District / Khan", "district_name_en", "district_name_kh"],
    ],
    commune: [
        ["country_id", "countries", "Country", "country_name_en", "country_name_kh"],
        ["province_id", "provinces", "Province / City", "province_name_en", "province_name_kh"],
        ["district_id", "districts", "District / Khan", "district_name_en", "district_name_kh"],
        ["commune_id", "communes", "Commune", "commune_name_en", "commune_name_kh"],
    ],
    village: [
        ["country_id", "countries", "Country", "country_name_en", "country_name_kh"],
        ["province_id", "provinces", "Province / City", "province_name_en", "province_name_kh"],
        ["district_id", "districts", "District / Khan", "district_name_en", "district_name_kh"],
        ["commune_id", "communes", "Commune", "commune_name_en", "commune_name_kh"],
    ],
};

const setModalStatus = (isActive = true) => {
    const statusInput = document.getElementById("location-status");
    if (statusInput) statusInput.value = isActive ? "1" : "0";
    if (!modalStatusToggle) return;
    modalStatusToggle.classList.toggle("is-active", isActive);
    modalStatusToggle.setAttribute("aria-pressed", isActive ? "true" : "false");
    const label = modalStatusToggle.querySelector(".status-toggle-label");
    if (label) label.textContent = isActive ? "ON" : "OFF";
};
const renderLevelOptions = () => {
    if (!levelResults || !levelSelected) return;
    const term = levelSearch?.value.trim().toLowerCase() || "";
    const items = levelOptionsList.filter((item) => item.label.toLowerCase().includes(term));
    const current = levelOptionsList.find((item) => item.value === level.value);
    levelSelected.textContent = current?.label || "Country";
    levelResults.innerHTML = items.length
        ? items.map((item) => `<button type="button" class="location-combobox-option${item.value === level.value ? " is-selected" : ""}" data-level-value="${item.value}"><span>${escapeHtml(item.label)}</span>${item.value === level.value ? '<i class="ti ti-check"></i>' : ""}</button>`).join("")
        : `<div class="text-secondary px-2 py-2">No options found</div>`;
};

const openLevelMenu = () => {
    if (!levelMenu) return;
    levelMenu.classList.remove("d-none");
    if (levelSearch) levelSearch.value = "";
    renderLevelOptions();
    levelSearch?.focus();
};

const closeLevelMenu = () => {
    levelMenu?.classList.add("d-none");
};

const changeLevel = async (newValue) => {
    if (!level) return;
    const changed = level.value !== newValue;
    level.value = newValue;
    closeLevelMenu();
    renderLevelOptions();
    if (!changed) return;
    await loadOptions();
    sortBy = "name";
    sortDir = "asc";
    renderCascadeFilters();
    setParentOptions();
    renderHead();
    renderLevelOptions();
    fetchRows(1, parseInt(perPageInput.value));
};
const ensureCountryNationalityFields = () => {
    const extra = document.getElementById("country-extra");
    if (!extra || document.getElementById("nationality-name-en")) return;
    extra.insertAdjacentHTML(
        "beforeend",
        '<div class="col-6"><label class="form-label">Nationality (English)</label><input id="nationality-name-en" class="form-control"></div><div class="col-6"><label class="form-label">Nationality (Khmer)</label><input id="nationality-name-kh" class="form-control school-profile-khmer"></div><div class="col-12"><label class="form-label">Flag Image</label><div class="logo-dropzone" id="countryFlagDropzone" tabindex="0"><i class="ti ti-cloud-upload logo-dropzone-icon"></i><div><strong>Drag and drop flag image here</strong></div><div class="text-secondary">or click, paste, or upload a file</div><input type="file" class="d-none" id="country-flag" accept="image/jpeg,image/png,image/webp"><img id="country-flag-preview" class="d-none mt-2" style="max-width:90px;max-height:60px;object-fit:contain" alt="Flag preview"></div></div>',
    );
    const dropzone = document.getElementById("countryFlagDropzone");
    const input = document.getElementById("country-flag");
    const preview = document.getElementById("country-flag-preview");
    const showPreview = (file) => {
        if (!file) return;
        preview.src = URL.createObjectURL(file);
        preview.classList.remove("d-none");
    };
    const imageFileFromPasteEvent = (event) => {
        const clipboardFiles = Array.from(event.clipboardData?.files || []);
        const directFile = clipboardFiles.find((entry) =>
            entry.type.startsWith("image/"),
        );
        if (directFile)
            return new File([directFile], "pasted-flag.png", {
                type: directFile.type || "image/png",
            });
        const items = Array.from(event.clipboardData?.items || []);
        const item = items.find(
            (entry) => entry.kind === "file" && entry.type.startsWith("image/"),
        );
        const file = item?.getAsFile();
        return file
            ? new File([file], "pasted-flag.png", {
                  type: file.type || "image/png",
              })
            : null;
    };
    const assignFlagFile = (file) => {
        if (!file || !file.type.startsWith("image/")) return;
        const transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;
        showPreview(file);
    };
    input?.addEventListener("change", () => showPreview(input.files?.[0]));
    dropzone?.addEventListener("click", () => input?.click());
    dropzone?.addEventListener("keydown", (event) => {
        if (event.key === "Enter" || event.key === " ") input?.click();
    });
    dropzone?.addEventListener("dragover", (event) => {
        event.preventDefault();
        dropzone.classList.add("is-dragging");
    });
    dropzone?.addEventListener("dragleave", () =>
        dropzone.classList.remove("is-dragging"),
    );
    dropzone?.addEventListener("drop", (event) => {
        event.preventDefault();
        dropzone.classList.remove("is-dragging");
        assignFlagFile(event.dataTransfer.files?.[0]);
    });
    dropzone?.addEventListener("paste", (event) => {
        const file = imageFileFromPasteEvent(event);
        if (!file) return;
        event.preventDefault();
        assignFlagFile(file);
    });
    document
        .getElementById("locationModal")
        ?.addEventListener("shown.bs.modal", () => {
            if (level.value === "country")
                window.setTimeout(
                    () => dropzone?.focus({ preventScroll: true }),
                    50,
                );
        });
    document.addEventListener("paste", (event) => {
        if (
            !document
                .getElementById("locationModal")
                ?.classList.contains("show")
        )
            return;
        if (level.value !== "country") return;
        if (
            event.target?.closest?.(
                "input:not([type='file']), textarea, [contenteditable='true']",
            )
        )
            return;
        const file = imageFileFromPasteEvent(event);
        if (!file) return;
        event.preventDefault();
        assignFlagFile(file);
    });
};

const loadOptions = async () => {
    const params = new URLSearchParams();
    if (filterSelects.country_id?.value)
        params.set("country_id", filterSelects.country_id.value);
    if (filterSelects.province_id?.value)
        params.set("province_id", filterSelects.province_id.value);
    if (filterSelects.district_id?.value)
        params.set("district_id", filterSelects.district_id.value);
    if (filterSelects.commune_id?.value)
        params.set("commune_id", filterSelects.commune_id.value);

    options = await (
        await fetch(`/locations/options${params.toString() ? `?${params}` : ""}`)
    ).json();
};

const optionText = (item, nameKey, khKeyName) =>
    item[khKeyName] ? `${item[khKeyName]} - ${item[nameKey]}` : item[nameKey];

const populateSelect = (
    select,
    items,
    placeholder,
    nameKey,
    khKeyName,
    selectedValue = "",
) => {
    if (!select) return;
    select.innerHTML =
        `<option value="">${placeholder}</option>` +
        items
            .map(
                (item) =>
                    `<option value="${item.id}">${optionText(item, nameKey, khKeyName)}</option>`,
            )
            .join("");
    select.value = selectedValue ? String(selectedValue) : "";
};

const currentFilterValues = () =>
    Object.fromEntries(
        Object.entries(filterSelects)
            .filter(([, select]) => select?.value)
            .map(([name, select]) => [name, select.value]),
    );

const filteredItems = (source, filterName) => {
    const items = options[source] || [];
    const countryId = filterSelects.country_id?.value || "";
    const provinceId = filterSelects.province_id?.value || "";
    const districtId = filterSelects.district_id?.value || "";

    if (source === "provinces" && countryId) {
        return items.filter((item) => String(item.country_id) === countryId);
    }

    if (source === "districts" && provinceId) {
        return items.filter((item) => String(item.province_id) === provinceId);
    }

    if (source === "communes" && districtId) {
        return items.filter((item) => String(item.district_id) === districtId);
    }

    return items;
};

const resetChildFilters = (changedName) => {
    const order = ["country_id", "province_id", "district_id", "commune_id"];
    const start = order.indexOf(changedName);
    if (start < 0) return;
    order.slice(start + 1).forEach((name) => {
        if (filterSelects[name]) filterSelects[name].value = "";
    });
};

const refreshFilterOptions = () => {
    (filterConfigs[level.value] || []).forEach(
        ([name, source, placeholder, nameKey, khKeyName]) => {
            const select = filterSelects[name];
            if (!select) return;
            const selected = select.value;
            const items = filteredItems(source, name);
            populateSelect(
                select,
                items,
                placeholder,
                nameKey,
                khKeyName,
                selected,
            );
            renderFilterComboboxOptions(select.closest(".location-filter-combobox"), select, source, placeholder, nameKey, khKeyName, items);
        },
    );
};

const renderFilterComboboxOptions = (wrap, select, source, placeholder, nameKey, khKeyName, sourceItems = null) => {
    const results = wrap.querySelector("[data-filter-results]");
    const selected = wrap.querySelector("[data-filter-selected]");
    const searchInput = wrap.querySelector("[data-filter-search]");
    const term = searchInput?.value.trim().toLowerCase() || "";
    const availableItems = sourceItems || options[source] || [];
    const items = availableItems.filter((item) => {
        if (!term) return true;
        const en = String(item[nameKey] ?? "").toLowerCase();
        const kh = String(item[khKeyName] ?? "").toLowerCase();
        return en.includes(term) || kh.includes(term);
    });

    const current = availableItems.find((item) => String(item.id) === String(select.value));
    selected.textContent = current ? optionText(current, nameKey, khKeyName) : placeholder;
    results.innerHTML = `<button type="button" class="location-combobox-option${select.value ? "" : " is-selected"}" data-filter-value=""><span>${placeholder}</span>${select.value ? "" : '<i class="ti ti-check"></i>'}</button>` +
        (items.length
            ? items.map((item) => `<button type="button" class="location-combobox-option${String(select.value) === String(item.id) ? " is-selected" : ""}" data-filter-value="${item.id}"><span class="school-profile-khmer">${escapeHtml(optionText(item, nameKey, khKeyName))}</span>${String(select.value) === String(item.id) ? '<i class="ti ti-check"></i>' : ""}</button>`).join("")
            : `<div class="text-secondary px-2 py-2">No options found</div>`);
};

const renderCascadeFilters = () => {
    if (!filterRow) return;
    filterRow.innerHTML = "";
    Object.keys(filterSelects).forEach((key) => delete filterSelects[key]);

    (filterConfigs[level.value] || []).forEach(
        ([name, source, placeholder, nameKey, khKeyName]) => {
            const wrap = document.createElement("div");
            wrap.className = "location-filter-select-wrap location-filter-combobox location-combobox";
            wrap.innerHTML = `<select class="d-none" data-filter-name="${name}"></select><button type="button" class="location-combobox-toggle location-filter-toggle"><span data-filter-selected>${placeholder}</span><i class="ti ti-chevron-down"></i></button><div class="location-combobox-menu d-none" data-filter-menu><input type="search" class="form-control form-control-sm school-profile-khmer" data-filter-search placeholder="Search ${placeholder}"><div class="location-combobox-results" data-filter-results></div></div>`;
            const select = wrap.querySelector("select");
            filterRow.appendChild(wrap);
            filterSelects[name] = select;
            const items = filteredItems(source, name);
            populateSelect(select, items, placeholder, nameKey, khKeyName);
            renderFilterComboboxOptions(wrap, select, source, placeholder, nameKey, khKeyName, items);

            wrap.querySelector(".location-filter-toggle").addEventListener("click", () => {
                const menu = wrap.querySelector("[data-filter-menu]");
                document.querySelectorAll(".location-filter-combobox [data-filter-menu]").forEach((other) => {
                    if (other !== menu) other.classList.add("d-none");
                });
                menu.classList.toggle("d-none");
                wrap.querySelector("[data-filter-search]").value = "";
                renderFilterComboboxOptions(wrap, select, source, placeholder, nameKey, khKeyName, filteredItems(source, name));
                if (!menu.classList.contains("d-none")) wrap.querySelector("[data-filter-search]").focus();
            });
            wrap.querySelector("[data-filter-search]").addEventListener("input", () => renderFilterComboboxOptions(wrap, select, source, placeholder, nameKey, khKeyName, filteredItems(source, name)));
            wrap.querySelector("[data-filter-results]").addEventListener("click", async (event) => {
                const button = event.target.closest("[data-filter-value]");
                if (!button) return;
                select.value = button.dataset.filterValue;
                wrap.querySelector("[data-filter-menu]").classList.add("d-none");
                resetChildFilters(name);
                await loadOptions();
                refreshFilterOptions();
                fetchRows(1, parseInt(perPageInput.value));
            });
        },
    );
};

const getParentNameKeys = () => {
    if (level.value === "province")
        return ["country_name_en", "country_name_kh"];
    if (level.value === "district")
        return ["province_name_en", "province_name_kh"];
    if (level.value === "commune")
        return ["district_name_en", "district_name_kh"];
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

    parent.innerHTML =
        `<option value="">Select ${parentLabel.textContent}</option>` +
        items
            .map(
                (item) =>
                    `<option value="${item.id}">${optionText(item, nameKey, khKeyName)}</option>`,
            )
            .join("");
    parentResults.innerHTML = items.length
        ? items
              .map(
                  (item) =>
                      `<button type="button" class="location-combobox-option${String(parent.value || selectedParentId) === String(item.id) ? " is-selected" : ""}" data-parent-id="${item.id}" data-parent-label="${optionText(item, nameKey, khKeyName)}"><span class="school-profile-khmer">${escapeHtml(optionText(item, nameKey, khKeyName))}</span>${String(parent.value || selectedParentId) === String(item.id) ? '<i class="ti ti-check"></i>' : ""}</button>`,
              )
              .join("")
        : `<div class="text-secondary px-2 py-2">No options found</div>`;
};

const syncParentSelectedText = () => {
    if (!parentSelected) return;
    const [nameKey, khKeyName] = getParentNameKeys();
    const selected = parentOptionsList.find(
        (item) => String(item.id) === String(parent.value || selectedParentId),
    );
    parentSelected.textContent = selected
        ? optionText(selected, nameKey, khKeyName)
        : `Select ${parentLabel?.textContent || "Parent"}`;
};

const openParentMenu = () => {
    if (!parentMenu) return;
    parentMenu.classList.remove("d-none");
    if (parentSearch) parentSearch.value = "";
    renderParentOptions();
    parentSearch?.focus();
};

const closeParentMenu = () => {
    parentMenu?.classList.add("d-none");
};

const setParentOptions = () => {
    const config = parentData[level.value];
    parentWrap.classList.toggle("d-none", !config);
    closeParentMenu();
    parent?.classList.add("d-none");
    if (parentSearch) parentSearch.value = "";
    selectedParentId = "";
    if (!config) return;

    parentLabel.textContent = config[1];
    parentOptionsList = options[config[0]] || [];
    renderParentOptions();
    syncParentSelectedText();
};

const relationCell = (item) => {
    const type = relationType[level.value];
    if (!type) return "";
    const relation = item[type];
    const flag =
        type === "country" && relation?.flag_path
            ? `<img src="/${relation.flag_path}" alt="${relation.country_name_en}" class="location-flag me-2">`
            : "";
    return `<td><div class="d-flex align-items-center">${flag}<div><div class="school-profile-khmer">${relation?.[`${type}_name_kh`] ?? ""}</div><div>${relation?.[`${type}_name_en`] ?? "-"}</div></div></div></td>`;
};

const countryCell = (item) => {
    const country =
        level.value === "district"
            ? item.province?.country
            : level.value === "commune"
              ? item.district?.province?.country
              : null;
    if (!country) return "<td>-</td>";
    return `<td><div class="d-flex align-items-center"><img src="/${country.flag_path}" alt="${country.country_name_en}" class="location-flag me-2"><div><div class="school-profile-khmer">${country.country_name_kh ?? ""}</div><div>${country.country_name_en}</div></div></div></td>`;
};

const ancestorCell = (item, type) => {
    const relation =
        type === "district"
            ? item.commune?.district
            : type === "province"
              ? item.commune?.district?.province
              : item.commune?.district?.province?.country;
    if (!relation) return "<td>-</td>";
    const flag =
        type === "country" && relation.flag_path
            ? `<img src="/${relation.flag_path}" alt="${relation.country_name_en}" class="location-flag me-2">`
            : "";
    return `<td><div class="d-flex align-items-center">${flag}<div><div class="school-profile-khmer">${relation[`${type}_name_kh`] ?? ""}</div><div>${relation[`${type}_name_en`] ?? "-"}</div></div></div></td>`;
};

const locationTextBlock = (label, item, type, extraClass = "") => {
    if (!item) return `<div class="location-card-field ${extraClass}"><div class="location-card-label">${label}</div><div>-</div></div>`;
    return `<div class="location-card-field ${extraClass}">
        <div class="location-card-label">${label}</div>
        <div class="location-card-name school-profile-khmer">${escapeHtml(item[`${type}_name_kh`] ?? "")}</div>
        <div class="location-card-subtitle">${escapeHtml(item[`${type}_name_en`] ?? "-")}</div>
    </div>`;
};

const locationCountryBlock = (country, label = "Country") => {
    if (!country) return `<div class="location-card-field"><div class="location-card-label">${label}</div><div>-</div></div>`;
    return `<div class="location-card-field location-card-country-field">
        <div class="location-card-label">${label}</div>
        <div class="location-country-line">
            ${country.flag_path ? `<img src="/${country.flag_path}" alt="${escapeHtml(country.country_name_en ?? "")}" class="location-flag">` : ""}
            <div>
                <div class="location-card-name school-profile-khmer">${escapeHtml(country.country_name_kh ?? "")}</div>
                <div class="location-card-subtitle">${escapeHtml(country.country_name_en ?? "-")}</div>
            </div>
        </div>
    </div>`;
};

const countryAncestor = (item) =>
    item.country || item.province?.country || item.district?.province?.country || item.commune?.district?.province?.country;

const renderCountryMobileContent = (item) => {
    const flag = item.flag_path ? `<img src="/${item.flag_path}" alt="${escapeHtml(item.country_name_en ?? "")}" class="location-mobile-flag">` : "";
    return `<div class="location-card-grid location-card-grid-2">
        <div>${flag}${locationTextBlock("Country", item, "country")}</div>
        <div>${locationTextBlock("Nationality", { nationality_name_kh: item.nationality_name_kh, nationality_name_en: item.nationality_name_en }, "nationality")}</div>
        <div><div class="location-card-label">Country Code</div><div>${escapeHtml(item.country_code ?? "-")}</div></div>
        <div><div class="location-card-label">Phone Code</div><div>${escapeHtml(item.international_phone_code ?? "-")}</div></div>
    </div>`;
};

const renderLocationMobileContent = (item, currentLevel) => {
    if (currentLevel === "country") return renderCountryMobileContent(item);
    if (currentLevel === "province") {
        return `<div class="location-card-grid location-card-grid-2">
            ${locationTextBlock("Province/City", item, "province")}
            ${locationCountryBlock(item.country, "Country")}
        </div>`;
    }
    if (currentLevel === "district") {
        return `<div class="location-card-grid location-card-grid-2">
            ${locationTextBlock("District/Khan", item, "district")}
            ${locationTextBlock("Province/City", item.province, "province")}
            ${locationCountryBlock(item.province?.country, "Country")}
        </div>`;
    }
    if (currentLevel === "commune") {
        return `<div class="location-card-grid location-card-grid-2">
            ${locationTextBlock("Commune", item, "commune")}
            ${locationTextBlock("District/Khan", item.district, "district")}
            ${locationTextBlock("Province/City", item.district?.province, "province")}
            ${locationCountryBlock(item.district?.province?.country, "Country")}
        </div>`;
    }
    return `<div class="location-card-grid location-card-grid-2">
        ${locationTextBlock("Village", item, "village")}
        ${locationTextBlock("Commune", item.commune, "commune")}
        ${locationTextBlock("District/Khan", item.commune?.district, "district")}
        ${locationTextBlock("Province/City", item.commune?.district?.province, "province")}
        ${locationCountryBlock(item.commune?.district?.province?.country, "Country")}
    </div>`;
};

const renderMobileCards = (offset = 0) => {
    if (!mobileCards) return;
    if (!rows.length) {
        mobileCards.innerHTML = `<div class="text-center text-secondary py-3">No locations found.</div>`;
        return;
    }

    mobileCards.innerHTML = rows.map((item, index) => {
        const currentLevel = level.value;
        const number = String(offset + index + 1).padStart(2, "0");

        return `<div class="location-mobile-card">
            <div class="location-card-top"><div class="location-card-number">${number}</div>${statusToggleMarkup(currentLevel, item.id, !!item.status)}</div>
            ${renderLocationMobileContent(item, currentLevel)}
            <div class="location-card-actions"><button type="button" class="btn btn-outline-primary" data-location-action="edit" data-location-id="${item.id}"><i class="ti ti-edit"></i></button><button type="button" class="btn btn-outline-danger" data-location-action="delete" data-location-id="${item.id}"><i class="ti ti-trash"></i></button></div>
        </div>`;
    }).join("");
};const renderHead = () => {
    const parentTitle = parentData[level.value]?.[1];
    const villageColumns =
        level.value === "village"
            ? `<th><button type="button" class="table-sort" data-sort="district">District / Khan</button></th><th><button type="button" class="table-sort" data-sort="province">Province / City</button></th><th><button type="button" class="table-sort" data-sort="country">Country</button></th>`
            : "";

    head.innerHTML = `<tr><th>No.</th><th><button type="button" class="table-sort" data-sort="name">${labels[level.value]}</button></th>${level.value === "country" ? '<th><button type="button" class="table-sort" data-sort="nationality">Nationality</button></th><th><button type="button" class="table-sort" data-sort="country_code">Country Code</button></th><th><button type="button" class="table-sort" data-sort="international_phone_code">Phone Code</button></th><th>Flag</th>' : parentTitle ? `<th><button type="button" class="table-sort" data-sort="parent">${parentTitle}</button></th>${["district", "commune"].includes(level.value) ? `<th><button type="button" class="table-sort" data-sort="country">Country</button></th>` : ""}${villageColumns}` : ""}<th>Status</th><th class="text-center">Actions</th></tr>`;

    head.querySelectorAll("[data-sort]").forEach((button) => {
        button.addEventListener("click", () => {
            const selected = button.dataset.sort;
            sortDir = sortBy === selected && sortDir === "asc" ? "desc" : "asc";
            sortBy = selected;
            fetchRows(1, parseInt(perPageInput.value));
        });
    });
};

const statusToggleMarkup = (currentLevel, id, isActive) =>
    `<button type="button" class="status-toggle${isActive ? " is-active" : ""}" data-location-action="status" data-location-id="${id}" data-location-level="${currentLevel}" aria-pressed="${isActive ? "true" : "false"}"><span class="status-toggle-label">${isActive ? "ON" : "OFF"}</span><span class="status-toggle-knob"></span></button>`;

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
    Object.entries(currentFilterValues()).forEach(([name, value]) =>
        params.set(name, value),
    );

    const j = await (
        await fetch(`/locations/fetch?${params.toString()}`)
    ).json();
    rows = j.data;
    const offset = (j.current_page - 1) * size;
    const name = key(level.value);
    const kh = khKey(level.value);
    const config = parentData[level.value];

    table.innerHTML = rows.length
        ? rows
              .map((item, index) => {
                  const villageAncestors =
                      level.value === "village"
                          ? `${ancestorCell(item, "district")}${ancestorCell(item, "province")}${ancestorCell(item, "country")}`
                          : "";
                  return `<tr><td>${offset + index + 1}</td><td class="location-name-cell"><div class="school-profile-khmer">${escapeHtml(item[kh] ?? "")}</div><div>${escapeHtml(item[name] ?? "")}</div></td>${level.value === "country" ? `<td><div class="school-profile-khmer">${escapeHtml(item.nationality_name_kh ?? "-")}</div><div>${escapeHtml(item.nationality_name_en ?? "-")}</div></td><td>${escapeHtml(item.country_code ?? "-")}</td><td>${escapeHtml(item.international_phone_code ?? "-")}</td><td>${item.flag_path ? `<img src="/${item.flag_path}" alt="${escapeHtml(item[name] ?? "")}" style="width:28px;height:20px;object-fit:contain">` : "-"}</td>` : config ? relationCell(item) : ""}${["district", "commune"].includes(level.value) ? countryCell(item) : ""}${villageAncestors}<td>${statusToggleMarkup(level.value, item.id, !!item.status)}</td><td class="text-center"><button type="button" class="btn btn-primary btn-sm" data-location-action="edit" data-location-id="${item.id}">Edit</button> <button type="button" class="btn btn-danger btn-sm" data-location-action="delete" data-location-id="${item.id}">Delete</button></td></tr>`;
              })
              .join("")
        : `<tr><td colspan="10" class="text-center">No locations found.</td></tr>`;

    renderPagination(
        j,
        "locations-pagination-container",
        "locations-per-page",
        fetchRows,
    );
    renderPageInfo(j);
    renderMobileCards(offset);
};

const currentReportParams = () => {
    const params = new URLSearchParams({
        level: level.value,
        search: search.value,
        sortBy,
        sortDir,
    });
    Object.entries(currentFilterValues()).forEach(([name, value]) =>
        params.set(name, value),
    );

    return params;
};

const openPrintPreview = () => {
    const url = `/locations/print?${currentReportParams().toString()}`;
    if (locationsPrintWindow && !locationsPrintWindow.closed) {
        locationsPrintWindow.location.href = url;
        locationsPrintWindow.focus();
        return;
    }

    locationsPrintWindow = window.open(url, "locations-print");
};

const downloadExcel = () => {
    window.location.href = `/locations/excel?${currentReportParams().toString()}`;
};

const resetForm = () => {
    ensureCountryNationalityFields();
    form.reset();
    document.getElementById("location_id").value = "";
    setModalStatus(true);
    const flagPreview = document.getElementById("country-flag-preview");
    if (flagPreview) {
        flagPreview.src = "";
        flagPreview.classList.add("d-none");
    }
    document.querySelector("[data-alert]").classList.add("d-none");
    setParentOptions();
    document
        .getElementById("country-extra")
        .classList.toggle("d-none", level.value !== "country");
};

const openCreate = async () => {
    await loadOptions();
    resetForm();
    document.getElementById("locationModalTitle").textContent =
        `Create ${labels[level.value]}`;
    modal.show();
};

const edit = async (id) => {
    await loadOptions();
    const item = rows.find((row) => row.id === id);
    if (!item) return;

    resetForm();
    document.getElementById("location_id").value = id;
    document.getElementById("location-name-en").value =
        item[key(level.value)] ?? "";
    document.getElementById("location-name-kh").value =
        item[khKey(level.value)] ?? "";
    setModalStatus(!!item.status);

    if (level.value === "country") {
        document.getElementById("country-code").value = item.country_code ?? "";
        document.getElementById("international-phone-code").value = item.international_phone_code ?? "";
        document.getElementById("flag-path").value = item.flag_path ?? "";
        const flagPreview = document.getElementById("country-flag-preview");
        if (flagPreview && item.flag_path) {
            flagPreview.src = item.flag_path.startsWith("storage/")
                ? `/${item.flag_path}`
                : `/${item.flag_path}`;
            flagPreview.classList.remove("d-none");
        }
        document.getElementById("nationality-name-en").value =
            item.nationality_name_en ?? "";
        document.getElementById("nationality-name-kh").value =
            item.nationality_name_kh ?? "";
    } else {
        const parentId =
            item[parentData[level.value][0].replace("s", "") + "_id"] ??
            item[
                `${level.value === "province" ? "country" : level.value === "district" ? "province" : level.value === "commune" ? "district" : "commune"}_id`
            ];
        if (level.value === "village") {
            selectedParentId = String(parentId ?? "");
            parent.value = selectedParentId;
            const matched = parentOptionsList.find(
                (row) => String(row.id) === selectedParentId,
            );
            if (matched)
                parentSearch.value = optionText(
                    matched,
                    "commune_name_en",
                    "commune_name_kh",
                );
            renderParentOptions();
        } else {
            parent.value = String(parentId ?? "");
            selectedParentId = parent.value;
        }
        syncParentSelectedText();
    }

    document.getElementById("locationModalTitle").textContent =
        `Edit ${labels[level.value]}`;
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
            international_phone_code: document.getElementById("international-phone-code").value,
            flag_path: document.getElementById("flag-path").value,
            nationality_name_en:
                document.getElementById("nationality-name-en")?.value || "",
            nationality_name_kh:
                document.getElementById("nationality-name-kh")?.value || "",
        });
    } else {
        payload.parent_id =
            level.value === "village" ? selectedParentId : parent.value;
    }

    const requestBody =
        level.value === "country"
            ? (() => {
                  const body = new FormData();
                  Object.entries(payload).forEach(([key, value]) =>
                      body.append(key, value ?? ""),
                  );
                  const file =
                      document.getElementById("country-flag")?.files?.[0];
                  if (file) body.append("flag_image", file);
                  return body;
              })()
            : JSON.stringify(payload);
    const headers = { Accept: "application/json", "X-CSRF-TOKEN": csrf };
    if (level.value !== "country") headers["Content-Type"] = "application/json";
    const r = await fetch("/locations/save", {
        method: "POST",
        headers,
        body: requestBody,
    });
    const j = await r.json();

    if (!r.ok) {
        document.querySelector("[data-alert]").textContent =
            j.message || "Unable to save location.";
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
    if (
        !(
            await showConfirm(
                "Delete Location",
                "Delete this location and its child locations?",
                "Delete",
                "Cancel",
            )
        ).isConfirmed
    )
        return;

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

const updateStatus = async (id) => {
    const item = rows.find((row) => Number(row.id) === Number(id));
    if (!item) return;

    const payload = {
        level: level.value,
        id,
        name_en: item[key(level.value)] ?? "",
        name_kh: item[khKey(level.value)] ?? "",
        status: item.status ? "0" : "1",
    };

    if (level.value === "country") {
        Object.assign(payload, {
            country_code: item.country_code ?? "",
            international_phone_code: item.international_phone_code ?? "",
            flag_path: item.flag_path ?? "",
            nationality_name_en: item.nationality_name_en ?? "",
            nationality_name_kh: item.nationality_name_kh ?? "",
        });
    } else {
        const parentColumn =
            level.value === "province"
                ? "country_id"
                : level.value === "district"
                  ? "province_id"
                  : level.value === "commune"
                    ? "district_id"
                    : "commune_id";
        payload.parent_id = item[parentColumn] ?? "";
    }

    const r = await fetch("/locations/save", {
        method: "POST",
        headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": csrf,
        },
        body: JSON.stringify(payload),
    });
    const j = await r.json();

    if (r.ok) {
        showSuccess("Updated", j.message || "Location status updated.");
        fetchRows();
    } else {
        showError("Error", j.message || "Unable to update status.");
    }
};

modalStatusToggle?.addEventListener("click", () => {
    setModalStatus(document.getElementById("location-status")?.value !== "1");
});

level.addEventListener("change", async () => {
    await changeLevel(level.value);
});
levelToggle?.addEventListener("click", openLevelMenu);
levelSearch?.addEventListener("input", renderLevelOptions);
levelResults?.addEventListener("click", (event) => {
    const button = event.target.closest("[data-level-value]");
    if (!button) return;
    changeLevel(button.dataset.levelValue);
});

parentToggle?.addEventListener("click", openParentMenu);
parentSearch?.addEventListener("input", renderParentOptions);
parentResults?.addEventListener("click", (event) => {
    const button = event.target.closest("[data-parent-id]");
    if (!button) return;
    selectedParentId = button.dataset.parentId || "";
    parent.value = selectedParentId;
    parentSearch.value = button.dataset.parentLabel || "";
    syncParentSelectedText();
    closeParentMenu();
});
document.addEventListener("click", (event) => {
    if (!event.target.closest(".location-level-combobox")) closeLevelMenu();
    if (!event.target.closest(".location-filter-combobox")) document.querySelectorAll(".location-filter-combobox [data-filter-menu]").forEach((menu) => menu.classList.add("d-none"));
    if (!parentWrap?.contains(event.target)) closeParentMenu();
});
document
    .getElementById("locationModal")
    ?.addEventListener("hidden.bs.modal", closeParentMenu);

perPageInput.addEventListener("change", () =>
    fetchRows(1, parseInt(perPageInput.value)),
);
search.addEventListener("input", () =>
    fetchRows(1, parseInt(perPageInput.value)),
);
document.getElementById("newLocation").addEventListener("click", openCreate);
document.getElementById("printLocations")?.addEventListener("click", openPrintPreview);
document.getElementById("excelLocations")?.addEventListener("click", downloadExcel);

document.addEventListener("click", (event) => {
    if (!event.target.closest(".location-level-combobox")) closeLevelMenu();
    if (!event.target.closest(".location-filter-combobox")) document.querySelectorAll(".location-filter-combobox [data-filter-menu]").forEach((menu) => menu.classList.add("d-none"));
    const button = event.target.closest("[data-location-action]");
    if (!button) return;
    const id = Number(button.dataset.locationId);
    if (button.dataset.locationAction === "edit") {
        edit(id);
    } else if (button.dataset.locationAction === "delete") {
        remove(id);
    } else if (button.dataset.locationAction === "status") {
        updateStatus(id);
    }
});

window.locationsPage = { edit, remove };

const initialize = async () => {
    await loadOptions();
    renderCascadeFilters();
    setParentOptions();
    renderHead();
    renderLevelOptions();
    fetchRows(1, parseInt(perPageInput.value));
};

if (document.readyState === "complete") {
    initialize();
} else {
    window.addEventListener("load", initialize, { once: true });
}




