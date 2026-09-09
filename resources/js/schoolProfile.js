import { renderPagination, renderPageInfo } from "./helpers/pagination.js";
import { showSuccess, showError, showConfirm } from "./helpers/sweet-alert2.js";
import intlTelInput from "intl-tel-input";
import "intl-tel-input/styles";

const csrfToken = document
    .querySelector('meta[name="csrf-token"]')
    ?.getAttribute("content");
const form = document.getElementById("schoolProfileForm");
const modalElement = document.getElementById("schoolProfileModal");
const modal = modalElement ? new bootstrap.Modal(modalElement) : null;
const viewModalElement = document.getElementById("schoolProfileViewModal");
const viewModal = viewModalElement
    ? new bootstrap.Modal(viewModalElement)
    : null;
const viewSchoolPrintButton = document.getElementById("viewSchoolPrintBtn");
const table = document.getElementById("schoolProfilesTable");
const mobileCards = document.getElementById("schoolProfilesMobileCards");
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
const googleMapUrlInput = document.getElementById("google_map_url");
const statusInput = document.getElementById("status");
const formStatusToggle = document.getElementById("schoolProfileStatusToggle");
const schoolProfileAddressFields = [
    "country",
    "province",
    "district",
    "commune",
    "village",
];
const schoolProfileAddressConfig = {
    country: {
        key: "countries",
        parentField: null,
        en: "country_name_en",
        kh: "country_name_kh",
        label: "Country",
    },
    province: {
        key: "provinces",
        parentField: "country_id",
        en: "province_name_en",
        kh: "province_name_kh",
        label: "Province / City",
    },
    district: {
        key: "districts",
        parentField: "province_id",
        en: "district_name_en",
        kh: "district_name_kh",
        label: "District / Khan",
    },
    commune: {
        key: "communes",
        parentField: "district_id",
        en: "commune_name_en",
        kh: "commune_name_kh",
        label: "Commune",
    },
    village: {
        key: "villages",
        parentField: "commune_id",
        en: "village_name_en",
        kh: "village_name_kh",
        label: "Village",
    },
};
const phoneIntl = phoneNumber
    ? intlTelInput(phoneNumber, {
          initialCountry: "kh",
          nationalMode: true,
          separateDialCode: true,
          loadUtils: () => import("intl-tel-input/utils"),
      })
    : null;
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
let schoolProfilesPrintWindow = null;
let sortBy = "id";
let sortDir = "desc";
let locationOptionsCache = null;
let schoolProfileAddressLocations = {};

const syncFormStatusToggle = (active) => {
    if (statusInput) statusInput.value = active ? "1" : "0";
    if (!formStatusToggle) return;
    formStatusToggle.classList.toggle("is-active", active);
    formStatusToggle.dataset.status = active ? "1" : "0";
    formStatusToggle.setAttribute("aria-pressed", String(active));
    formStatusToggle.querySelector(".status-toggle-label").textContent = active
        ? "ON"
        : "OFF";
};

const clearErrors = () => {
    form?.querySelectorAll(".is-invalid").forEach((field) =>
        field.classList.remove("is-invalid"),
    );
    form?.querySelectorAll("[data-error-for]").forEach((field) => {
        field.textContent = "";
        field.classList.remove("d-block");
    });
    const alert = form?.querySelector("[data-form-alert]");
    if (alert) {
        alert.textContent = "";
        alert.classList.add("d-none");
    }
};
const showErrors = (errors, message) => {
    const alert = form?.querySelector("[data-form-alert]");
    if (alert) {
        alert.textContent = message || "Please correct the errors below.";
        alert.classList.remove("d-none");
    }
    Object.entries(errors || {}).forEach(([field, messages]) => {
        document.getElementById(field)?.classList.add("is-invalid");
        const error = form?.querySelector(`[data-error-for="${field}"]`);
        if (error) {
            error.textContent = messages[0];
            error.classList.add("d-block");
        }
    });
};
const openCreateModal = async () => {
    creatingNewProfile = true;
    form?.reset();
    clearErrors();
    document.getElementById("school_id").value = "";
    syncFormStatusToggle(true);
    resetPhoneToCambodia();
    logoPreviewContainer?.classList.add("d-none");
    modalTitle.textContent = "Create School Profile";
    submitButton.textContent = "Create";
    modal?.show();
    try {
        await loadSchoolProfileAddressLocations(true);
    } catch (error) {
        showErrors({}, error.message);
    }
};
const openEditModal = async (id) => {
    const item = schoolProfiles.find((row) => row.id === id);
    if (!item || !form) return;
    creatingNewProfile = false;
    form.reset();
    clearErrors();
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
    if (googleMapUrlInput) {
        googleMapUrlInput.value = item.google_map_url ?? "";
    }
    const savedPhone = item.phone ?? "";
    phoneIntl?.setNumber(savedPhone);
    phoneInput.value = savedPhone;
    await setSchoolProfileSavedAddress(item);
    document.getElementById("description").value = item.description ?? "";
    syncFormStatusToggle(Boolean(item.status ?? 1));
    modalTitle.textContent = "Edit School Profile";
    submitButton.textContent = "Update";
    modal?.show();
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
    const mapLink = document.getElementById("viewGoogleMapLink");
    const mapActions = document.getElementById("viewGoogleMapActions");
    const mapEmpty = document.getElementById("viewGoogleMapEmpty");
    if (mapLink) {
        const url = item.google_map_url?.trim() || "";
        mapLink.value = url;
        mapLink.classList.toggle("d-none", !url);
    }
    if (mapActions) {
        mapActions.classList.toggle("d-none", !item.google_map_url);
    }
    if (mapEmpty) {
        mapEmpty.classList.toggle("d-none", Boolean(item.google_map_url));
    }
    const status = document.getElementById("viewStatus");
    if (status)
        status.innerHTML = item.status
            ? "<span class='badge bg-success-lt'>Active</span>"
            : "<span class='badge bg-danger-lt'>Inactive</span>";
    viewModal?.show();
};
const escapeHtml = (value) =>
    String(value ?? "-")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
const field = (id) => document.getElementById(id);
const emptySelectedLabel = "Please select";
const locationAssetUrl = (path = "") => {
    if (!path) return "";
    if (/^https?:\/\//i.test(path) || path.startsWith("/")) return path;
    return `/${path}`;
};
const bilingualSelectedLabel = (kh, en) =>
    [kh, en].filter(Boolean).join(" - ") || emptySelectedLabel;
const bilingualSelectedMarkup = (kh, en) =>
    `<span class="location-combobox-khmer">${escapeHtml(kh || "")}</span><span class="location-combobox-english d-block">${escapeHtml(en || "")}</span>`;
const schoolProfileAddressUi = (type) => ({
    select: field(`address_${type}_id`),
    toggle: field(`school-profile-address-${type}-toggle`),
    menu: field(`school-profile-address-${type}-menu`),
    search: field(`school-profile-address-${type}-search`),
    results: field(`school-profile-address-${type}-results`),
    selected: field(`school-profile-address-${type}-selected`),
});
const setSchoolProfileAddressSelectedText = (type) => {
    const ui = schoolProfileAddressUi(type);
    const option = ui.select?.selectedOptions?.[0];
    if (!ui.selected) return;
    if (!option || !option.value) {
        ui.selected.textContent = "";
        return;
    }
    const flag =
        type === "country" && option.dataset.flag
            ? `<img src="${escapeHtml(locationAssetUrl(option.dataset.flag))}" class="location-flag me-2" alt="">`
            : "";
    ui.selected.innerHTML = `${flag}<span class="location-combobox-selected-text">${bilingualSelectedMarkup(option.dataset.kh || "", option.dataset.en || option.textContent || "")}</span>`;
};
const renderSchoolProfileAddressResults = (type) => {
    const ui = schoolProfileAddressUi(type);
    if (!ui.select || !ui.results) return;
    const term = (ui.search?.value || "").toLowerCase();
    const options = Array.from(ui.select.options)
        .slice(1)
        .filter(
            (option) =>
                !term ||
                option.textContent.toLowerCase().includes(term) ||
                (option.dataset.kh || "").toLowerCase().includes(term) ||
                (option.dataset.en || "").toLowerCase().includes(term),
        );
    ui.results.innerHTML = options.length
        ? options
              .map(
                  (option) =>
                      `<button type="button" class="location-combobox-option" data-school-profile-address-type="${type}" data-school-profile-address-id="${option.value}"><span class="d-flex align-items-center min-w-0">${type === "country" && option.dataset.flag ? `<img src="${escapeHtml(locationAssetUrl(option.dataset.flag))}" class="location-flag me-2" alt="">` : ""}<span><span class="location-combobox-khmer">${escapeHtml(option.dataset.kh || "")}</span><span class="location-combobox-english d-block">${escapeHtml(option.dataset.en || option.textContent)}</span></span></span></button>`,
              )
              .join("")
        : `<div class="text-secondary px-2 py-2">No options found</div>`;
};
const setupSchoolProfileAddressComboboxes = () =>
    schoolProfileAddressFields.forEach((type) => {
        const ui = schoolProfileAddressUi(type);
        if (!ui.select || field(`school-profile-address-${type}-combobox`)) return;
        ui.select.classList.add("d-none");
        ui.select.insertAdjacentHTML(
            "afterend",
            `<div id="school-profile-address-${type}-combobox" class="location-combobox school-profile-address-combobox"><button type="button" id="school-profile-address-${type}-toggle" class="location-combobox-toggle"><span id="school-profile-address-${type}-selected" class="location-combobox-selected"></span><i class="ti ti-chevron-down"></i></button><div id="school-profile-address-${type}-menu" class="location-combobox-menu d-none school-profile-address-menu"><input id="school-profile-address-${type}-search" type="search" class="form-control location-combobox-search" placeholder="Search ${schoolProfileAddressConfig[type].label}"><div id="school-profile-address-${type}-results" class="location-combobox-results"></div></div></div>`,
        );
    });
const setSchoolProfileAddressOptions = (type, items) => {
    const select = field(`address_${type}_id`);
    if (!select) return;
    const config = schoolProfileAddressConfig[type];
    select.innerHTML =
        `<option value="">Select ${config.label}</option>` +
        items
            .map(
                (item) =>
                    `<option value="${item.id}" data-en="${escapeHtml(item[config.en] || "")}" data-kh="${escapeHtml(item[config.kh] || "")}" data-flag="${escapeHtml(item.flag_path || "")}">${escapeHtml(item[config.kh] ? `${item[config.kh]} - ${item[config.en]}` : item[config.en] || "")}</option>`,
            )
            .join("");
    setSchoolProfileAddressSelectedText(type);
};
const isSchoolProfilePhnomPenh = (value = "") => {
    const text = String(value || "").trim().toLowerCase();
    return text.includes("phnom penh") || text.includes("ភ្នំពេញ");
};
const prefixSchoolProfileKhmerAddressPart = (type, value = "", provinceValue = "") => {
    const text = String(value || "").trim();
    if (!text) return "";
    if (type === "village") return /^ភូមិ/.test(text) ? text : `ភូមិ${text}`;
    if (type === "commune") {
        const name = text.replace(/^(សង្កាត់|ឃុំ)/, "");
        return isSchoolProfilePhnomPenh(provinceValue) ? `សង្កាត់${name}` : `ឃុំ${name}`;
    }
    if (type === "district") {
        const name = text.replace(/^(ខណ្ឌ|ស្រុក|ក្រុង)/, "");
        return isSchoolProfilePhnomPenh(provinceValue) ? `ខណ្ឌ${name}` : `ស្រុក${name}`;
    }
    if (type === "province") {
        if (/^(រាជធានី|ខេត្ត)/.test(text)) return text;
        return /ភ្នំពេញ/.test(text) ? `រាជធានី${text}` : `ខេត្ត${text}`;
    }
    return text;
};
const prefixSchoolProfileEnglishAddressPart = (type, value = "", provinceValue = "") => {
    const text = String(value || "").trim();
    if (!text) return "";
    if (type === "village") return /^phoum\s+/i.test(text) ? text : `Phoum ${text}`;
    if (type === "commune") {
        const name = text.replace(/^(sk\.?|sangkat|khum)\s+/i, "");
        return isSchoolProfilePhnomPenh(provinceValue) ? `SK. ${name}` : `Khum ${name}`;
    }
    if (type === "district") {
        const name = text.replace(/^(khan|srok|district)\s+/i, "");
        return isSchoolProfilePhnomPenh(provinceValue) ? `Khan ${name}` : `Srok ${name}`;
    }
    return text;
};
const updateSchoolProfileAddressText = () => {
    const selectedProvinceEn = field("address_province_id")?.selectedOptions?.[0]?.dataset.en || "";
    const selectedProvinceKh = field("address_province_id")?.selectedOptions?.[0]?.dataset.kh || "";
    const enParts = [
        "address_house_no_en",
        "address_street_en",
        "address_village_id",
        "address_commune_id",
        "address_district_id",
        "address_province_id",
        "address_country_id",
    ]
        .map((id) =>
            field(id)?.tagName === "SELECT"
                ? prefixSchoolProfileEnglishAddressPart(
                      id.replace(/^address_/, "").replace(/_id$/, ""),
                      field(id)?.selectedOptions?.[0]?.dataset.en,
                      selectedProvinceEn,
                  )
                : id === "address_house_no_en" && field(id)?.value?.trim()
                  ? `#${field(id).value.trim().replace(/^#\s*/, "")}`
                  : id === "address_street_en" && field(id)?.value?.trim()
                    ? `St. ${field(id).value.trim().replace(/^(st\.?|street)\s*/i, "")}`
                  : field(id)?.value,
        )
        .filter(Boolean);
    const khParts = [
        "address_house_no_kh",
        "address_street_kh",
        "address_village_id",
        "address_commune_id",
        "address_district_id",
        "address_province_id",
        "address_country_id",
    ]
        .map((id) =>
            field(id)?.tagName === "SELECT"
                ? prefixSchoolProfileKhmerAddressPart(
                      id.replace(/^address_/, "").replace(/_id$/, ""),
                      field(id)?.selectedOptions?.[0]?.dataset.kh,
                      selectedProvinceKh,
                  )
                : field(id)?.value,
        )
        .filter(Boolean);
    if (field("address_en")) field("address_en").value = enParts.join(", ");
    if (field("address_kh")) field("address_kh").value = khParts.join(", ");
    if (field("address")) field("address").value = enParts.join(", ");
};
const filterSchoolProfileAddressLocations = (type) => {
    const index = schoolProfileAddressFields.indexOf(type);
    if (index < 0 || index >= schoolProfileAddressFields.length - 1) return;
    const next = schoolProfileAddressFields[index + 1];
    const parent = field(`address_${type}_id`)?.value;
    const config = schoolProfileAddressConfig[next];
    setSchoolProfileAddressOptions(
        next,
        (schoolProfileAddressLocations[config.key] || []).filter(
            (item) => String(item[config.parentField]) === String(parent),
        ),
    );
    for (let i = index + 2; i < schoolProfileAddressFields.length; i += 1) {
        setSchoolProfileAddressOptions(schoolProfileAddressFields[i], []);
    }
    updateSchoolProfileAddressText();
};
const findCambodiaCountryId = () => {
    const countries = schoolProfileAddressLocations.countries || [];
    const cambodia = countries.find((country) => {
        const en = String(country.country_name_en || "").toLowerCase();
        const kh = String(country.country_name_kh || "");
        return en.includes("cambodia") || kh.includes("កម្ពុជា");
    });
    return cambodia?.id || "";
};
const loadLocationOptions = async () => {
    if (locationOptionsCache) return locationOptionsCache;
    const response = await fetch("/locations/options", {
        headers: { Accept: "application/json" },
        credentials: "same-origin",
    });
    if (!response.ok) throw new Error("Unable to load location options.");
    locationOptionsCache = await response.json();
    return locationOptionsCache;
};
const loadSchoolProfileAddressLocations = async (useDefaultCountry = false) => {
    setupSchoolProfileAddressComboboxes();
    schoolProfileAddressLocations = await loadLocationOptions();
    setSchoolProfileAddressOptions(
        "country",
        schoolProfileAddressLocations.countries || [],
    );
    schoolProfileAddressFields
        .slice(1)
        .forEach((type) => setSchoolProfileAddressOptions(type, []));
    if (useDefaultCountry) {
        const country = field("address_country_id");
        const cambodiaId = findCambodiaCountryId();
        if (country && cambodiaId) {
            country.value = String(cambodiaId);
            setSchoolProfileAddressSelectedText("country");
            filterSchoolProfileAddressLocations("country");
        }
    }
    updateSchoolProfileAddressText();
};
const setSchoolProfileSavedAddress = async (item) => {
    await loadSchoolProfileAddressLocations();
    const setAndFilter = (type, value) => {
        const select = field(`address_${type}_id`);
        if (!select) return;
        select.value = value || "";
        setSchoolProfileAddressSelectedText(type);
        filterSchoolProfileAddressLocations(type);
    };
    setAndFilter("country", item.address_country_id);
    setAndFilter("province", item.address_province_id);
    setAndFilter("district", item.address_district_id);
    setAndFilter("commune", item.address_commune_id);
    const village = field("address_village_id");
    if (village) {
        village.value = item.address_village_id || "";
        setSchoolProfileAddressSelectedText("village");
    }
    if (field("address_house_no_en")) field("address_house_no_en").value = item.address_house_no_en || "";
    if (field("address_house_no_kh")) field("address_house_no_kh").value = item.address_house_no_kh || "";
    if (field("address_street_en")) field("address_street_en").value = item.address_street_en || "";
    if (field("address_street_kh")) field("address_street_kh").value = item.address_street_kh || "";
    if (field("address_en")) field("address_en").value = item.address_en || item.address || "";
    if (field("address_kh")) field("address_kh").value = item.address_kh || "";
    if (field("address")) field("address").value = item.address_en || item.address || "";
    updateSchoolProfileAddressText();
};
const syncSchoolProfileAddressKhmer = (englishId, khmerId, prefix) => {
    const english = field(englishId);
    const khmer = field(khmerId);
    const removePrefix = (value) =>
        String(value || "")
            .replace(new RegExp(`^${prefix}\\s*`, "i"), "")
            .trim();
    english?.addEventListener("input", () => {
        if (khmer) {
            khmer.value = english.value.trim()
                ? `${prefix} ${english.value.trim()}`
                : "";
        }
        updateSchoolProfileAddressText();
    });
    khmer?.addEventListener("input", () => {
        const value = removePrefix(khmer.value);
        khmer.value = value ? `${prefix} ${value}` : "";
        updateSchoolProfileAddressText();
    });
};
const schoolProfileStatusToggle = (item) =>
    window.statusToggleMarkup?.("school-profile", item.id, Boolean(item.status)) ||
    `<button type="button" class="status-toggle ${item.status ? "is-active" : ""}" data-status-toggle data-status-entity="school-profile" data-status-id="${item.id}" data-status="${item.status ? 1 : 0}" aria-pressed="${item.status ? "true" : "false"}"><span class="status-toggle-label">${item.status ? "ON" : "OFF"}</span><span class="status-toggle-knob"></span></button>`;
const schoolProfileLogoMarkup = (item, className = "") =>
    item.logo_path
        ? `<img src="/storage/${escapeHtml(item.logo_path)}" alt="Logo" class="${className}">`
        : `<span class="school-profile-mobile-logo-empty ${className}"><i class="ti ti-building"></i></span>`;
const formatSchoolProfileHouseNo = (value = "") => {
    const number = String(value || "").trim().replace(/^#\s*/, "");
    return number ? `#${number}` : "";
};
const formatSchoolProfileStreetNo = (value = "") => {
    const number = String(value || "").trim().replace(/^(st\.?|street)\s*/i, "");
    return number ? `St. ${number}` : "";
};
const formatSchoolProfilePhone = (value = "") => {
    const text = String(value || "").trim();
    if (!text) return "";
    const digits = text.replace(/\D/g, "");
    let local = digits;
    if (local.startsWith("855")) local = local.slice(3);
    if (local.startsWith("0")) local = local.slice(1);
    if (!local) return text;
    const groups =
        local.length === 8
            ? [local.slice(0, 2), local.slice(2, 5), local.slice(5)]
            : local.length === 9
              ? [local.slice(0, 2), local.slice(2, 5), local.slice(5)]
              : [local];
    return `+855 ${groups.filter(Boolean).join(" ")}`;
};
const schoolProfileEnglishAddress = (item) => {
    const formattedHouse = formatSchoolProfileHouseNo(item.address_house_no_en);
    const formattedStreet = formatSchoolProfileStreetNo(item.address_street_en);
    const savedParts = String(item.address_en || item.address || "")
        .split(",")
        .map((part) => part.trim())
        .filter(Boolean);

    if (formattedHouse && savedParts.length) savedParts[0] = formattedHouse;
    if (formattedStreet && savedParts.length > 1) savedParts[1] = formattedStreet;
    const provincePart = savedParts.length > 5 ? savedParts[5] : "";
    if (savedParts.length > 2) savedParts[2] = prefixSchoolProfileEnglishAddressPart("village", savedParts[2]);
    if (savedParts.length > 3) savedParts[3] = prefixSchoolProfileEnglishAddressPart("commune", savedParts[3]);
    if (savedParts.length > 4) savedParts[4] = prefixSchoolProfileEnglishAddressPart("district", savedParts[4], provincePart);

    return savedParts.join(", ");
};
const schoolProfileMobileAddressMarkup = (item) => {
    const houseStreet = [
        formatSchoolProfileHouseNo(item.address_house_no_en),
        formatSchoolProfileStreetNo(item.address_street_en),
    ]
        .filter(Boolean)
        .join(", ");
    const khmerAddress = item.address_kh || "";
    const englishAddress = schoolProfileEnglishAddress(item);

    if (!houseStreet && !khmerAddress && !englishAddress) {
        return "";
    }

    return `<div class="school-profile-mobile-address">
        <span class="school-profile-mobile-label"><i class="ti ti-map-pin"></i> School Address</span>
        ${houseStreet ? `<strong>${escapeHtml(houseStreet)}</strong>` : ""}
        ${khmerAddress ? `<span class="school-profile-khmer">${escapeHtml(khmerAddress)}</span>` : ""}
        ${englishAddress ? `<span>${escapeHtml(englishAddress)}</span>` : ""}
    </div>`;
};
const schoolProfileTableAddressMarkup = (khmerAddress, englishAddress) => {
    const khmerLine = khmerAddress
        ? `<span class="school-profile-table-address-line school-profile-khmer"><i class="ti ti-map-pin"></i>${escapeHtml(khmerAddress)}</span>`
        : "";
    const englishLine = englishAddress
        ? `<span class="school-profile-table-address-line text-secondary"><i class="ti ti-map-pin"></i><small>${escapeHtml(englishAddress)}</small></span>`
        : "";
    return khmerLine || englishLine ? `${khmerLine}${englishLine}` : "-";
};
const schoolProfileDeleteButtonMarkup = (item, sizeClass = "btn-sm") => {
    const linked = Boolean(item.is_linked);
    const title = escapeHtml(
        linked
            ? item.linked_message ||
                  "This campus is linked to other data and cannot be deleted."
            : "Delete",
    );
    const btnClass = linked ? "btn btn-outline-secondary" : "btn btn-danger";

    return `<button onclick="schoolProfilesPage.deleteSchoolProfile(${item.id})" class="${btnClass} ${sizeClass}" title="${title}"><i class="ti ti-trash ${sizeClass ? "icon" : "me-1"}"></i>Delete</button>`;
};
const printSchoolProfile = () => {
    if (!profileToPrint) return;
    const item = profileToPrint;
    const logo = item.logo_path
        ? `<img src="/storage/${escapeHtml(item.logo_path)}" alt="School Logo" style="width:110px;height:110px;object-fit:contain">`
        : "";
    const phone = escapeHtml(item.phone || "-").replace(/\r?\n/g, "<br>");
    const mapUrl = escapeHtml(item.google_map_url || "-");
    const printWindow = window.open("", "_blank", "width=900,height=700");
    if (!printWindow) return;
    printWindow.document
        .write(`<!doctype html><html><head><title>School Profile</title><style>
        @font-face { font-family: 'Khmer OS Siemreap'; src: url('/fonts/khmer/KhmerOSsiemreap.ttf') format('truetype'); font-weight: 400; font-style: normal; }
        body { font-family: Arial, sans-serif; color: #263648; padding: 35px; } .header { text-align:center; margin-bottom:28px; }
        h1 { margin: 12px 0 4px; font-size: 26px; } h2 { margin:0; font-family:'Khmer OS Siemreap', Arial; font-size:22px; }
        table { width:100%; border-collapse:collapse; } td { border:1px solid #dfe4ea; padding:12px; vertical-align:top; }
        td:first-child { width:32%; font-weight:bold; background:#f5f7fa; } .khmer { font-family:'Khmer OS Siemreap', Arial; font-size:18px; }
        .text { white-space:pre-wrap; } .link { word-break:break-all; } @media print { body { padding:0; } }
    </style></head><body><div class="header">${logo}<h2>${escapeHtml(item.school_name_kh)}</h2><h1>${escapeHtml(item.school_name_en)}</h1></div>
        <table><tr><td>Campus (Khmer)</td><td class="khmer">${escapeHtml(item.campus_name_kh)}</td></tr><tr><td>Campus (English)</td><td>${escapeHtml(item.campus_name_en)}</td></tr>
        <tr><td>Phone Number</td><td>${phone}</td></tr><tr><td>Address</td><td class="text">${escapeHtml(item.address)}</td></tr>
        <tr><td>Google Map Link</td><td class="link">${mapUrl === "-" ? "-" : `<a href="${mapUrl}" target="_blank" rel="noopener noreferrer">${mapUrl}</a>`}</td></tr>
        <tr><td>Description</td><td class="text">${escapeHtml(item.description)}</td></tr><tr><td>Status</td><td>${item.status ? "Active" : "Inactive"}</td></tr></table>
    </body></html>`);
    printWindow.document.close();
    printWindow.addEventListener("load", () => {
        printWindow.focus();
        printWindow.print();
    });
};
const openSchoolProfilesPrintPreview = () => {
    if (schoolProfilesPrintWindow && !schoolProfilesPrintWindow.closed) {
        schoolProfilesPrintWindow.focus();
        return;
    }

    schoolProfilesPrintWindow = window.open("/school-info/print", "school-profile-list-print");
};
viewSchoolPrintButton?.addEventListener("click", printSchoolProfile);
const getSelectedGoogleMapUrl = () => profileToPrint?.google_map_url?.trim() || "";
const getSchoolMapUrl = (id) =>
    schoolProfiles.find((row) => row.id === id)?.google_map_url?.trim() || "";
const openGoogleMap = () => {
    const url = getSelectedGoogleMapUrl();
    if (!url) return;
    window.open(url, "_blank", "noopener,noreferrer");
};
const openSchoolMap = (id) => {
    const url = getSchoolMapUrl(id);
    if (!url) return;
    window.open(url, "_blank", "noopener,noreferrer");
};
const toggleSchoolMapShareOptions = (id, event) => {
    event?.preventDefault();
    event?.stopPropagation();
    document
        .getElementById(`school-map-share-${id}`)
        ?.classList.toggle("d-none");
};
const copyText = async (text, successMessage) => {
    try {
        await navigator.clipboard.writeText(text);
        showSuccess("Copied", successMessage);
        return true;
    } catch {
        showError("Error", "Unable to copy Google Map link.");
        return false;
    }
};
const copyGoogleMapLink = async () => {
    const url = getSelectedGoogleMapUrl();
    if (!url) return;
    await copyText(url, "Google Map link copied successfully.");
};
const copySchoolMapLink = async (id) => {
    const url = getSchoolMapUrl(id);
    if (!url) return;
    await copyText(url, "Google Map link copied successfully.");
};
const shareSchoolMapLink = async (id, provider) => {
    const url = getSchoolMapUrl(id);
    if (!url) return;
    const encodedUrl = encodeURIComponent(url);
    const item = schoolProfiles.find((row) => row.id === id);
    const title = item?.school_name_en || "School location";
    const campus = item?.campus_name_en ? ` (${item.campus_name_en})` : "";
    const shareText = `${title}${campus}`;
    const encodedTitle = encodeURIComponent(shareText);

    if (provider === "telegram") {
        window.open(
            `https://t.me/share/url?url=${encodedUrl}&text=${encodedTitle}`,
            "_blank",
            "noopener,noreferrer",
        );
        return;
    }

    if (provider === "whatsapp") {
        window.open(
            `https://wa.me/?text=${encodedTitle}%0A${encodedUrl}`,
            "_blank",
            "noopener,noreferrer",
        );
        return;
    }

    if (provider === "messenger") {
        const copied = await copyText(
            `${shareText}\n${url}`,
            "Google Map link copied. Paste it in Messenger to share.",
        );
        if (copied) {
            window.open("https://www.messenger.com/", "_blank", "noopener,noreferrer");
        }
        return;
    }

    if (provider === "native" && navigator.share) {
        try {
            await navigator.share({ title: shareText, text: shareText, url });
        } catch (error) {
            if (error.name !== "AbortError") {
                showError("Error", "Unable to share Google Map link.");
            }
        }
        return;
    }

    await copySchoolMapLink(id);
};
const shareGoogleMapLink = async () => {
    const url = getSelectedGoogleMapUrl();
    if (!url) return;
    if (!navigator.share) {
        await copyGoogleMapLink();
        return;
    }
    try {
        await navigator.share({
            title: profileToPrint?.school_name_en || "School location",
            text: profileToPrint?.school_name_en || "School location",
            url,
        });
    } catch (error) {
        if (error.name !== "AbortError") {
            showError("Error", "Unable to share Google Map link.");
        }
    }
};
document.getElementById("viewGoogleMapActions")?.addEventListener("click", (event) => {
    const button = event.target.closest("[data-school-map-action]");
    if (!button) return;
    if (button.dataset.schoolMapAction === "open") openGoogleMap();
    if (button.dataset.schoolMapAction === "copy") copyGoogleMapLink();
    if (button.dataset.schoolMapAction === "share") shareGoogleMapLink();
});
form?.addEventListener("submit", async (event) => {
    event.preventDefault();
    phoneInput.value = phoneIntl?.getNumber() || phoneNumber.value.trim();
    clearErrors();
    submitButton.disabled = true;
    submitButton.textContent = "Saving...";
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
        submitButton.textContent = document.getElementById("school_id").value
            ? "Update"
            : "Create";
        return;
    }
    try {
        const response = await fetch("/school-info/save", {
            method: "POST",
            headers: { Accept: "application/json", "X-CSRF-TOKEN": csrfToken },
            body: new FormData(form),
        });
        const text = await response.text();
        let result;
        try {
            result = JSON.parse(text);
        } catch {
            throw new Error("Unable to save school profile. Please try again.");
        }
        if (response.status === 422) {
            const isDuplicate = result.message?.startsWith(
                "Unable to save School Profile.",
            );
            showErrors(isDuplicate ? {} : result.errors, result.message);
            return;
        }
        if (!response.ok || result.status !== "success")
            throw new Error(result.message || "Unable to save school profile.");
        modal?.hide();
        showSuccess("Saved", result.message);
        fetchSchoolProfiles();
    } catch (error) {
        showErrors({}, error.message);
    } finally {
        submitButton.disabled = false;
        submitButton.textContent = document.getElementById("school_id").value
            ? "Update"
            : "Create";
    }
});
logoInput?.addEventListener("change", () => {
    const file = logoInput.files?.[0];
    if (!file) return;
    showLogoPreview(file);
});
const showLogoPreview = (file) => {
    if (!file || !file.type.startsWith("image/")) return;
    logoPreview.src = URL.createObjectURL(file);
    logoPreview.style.cssText =
        "display:block;width:120px;height:120px;max-width:120px;max-height:120px;object-fit:contain;border:1px solid var(--tblr-border-color);border-radius:.5rem;background:var(--tblr-bg-surface);";
    logoPreviewContainer.classList.remove("d-none");
};
const imageFileFromPasteEvent = (event, filename = "pasted-logo.png") => {
    const clipboardFiles = Array.from(event.clipboardData?.files || []);
    const directFile = clipboardFiles.find((entry) =>
        entry.type.startsWith("image/"),
    );
    if (directFile)
        return new File([directFile], filename, {
            type: directFile.type || "image/png",
        });

    const items = Array.from(event.clipboardData?.items || []);
    const item = items.find(
        (entry) => entry.kind === "file" && entry.type.startsWith("image/"),
    );
    const file = item?.getAsFile();
    return file
        ? new File([file], filename, { type: file.type || "image/png" })
        : null;
};
const assignLogoFile = (file) => {
    if (!file || !file.type.startsWith("image/")) return;
    const transfer = new DataTransfer();
    transfer.items.add(file);
    if (logoInput) logoInput.files = transfer.files;
    showLogoPreview(file);
};
logoDropzone?.addEventListener("click", () => logoInput?.click());
logoDropzone?.addEventListener("keydown", (event) => {
    if (event.key === "Enter" || event.key === " ") logoInput?.click();
});
logoDropzone?.addEventListener("dragover", (event) => {
    event.preventDefault();
    logoDropzone.classList.add("is-dragging");
});
logoDropzone?.addEventListener("dragleave", () =>
    logoDropzone.classList.remove("is-dragging"),
);
logoDropzone?.addEventListener("drop", (event) => {
    event.preventDefault();
    logoDropzone.classList.remove("is-dragging");
    const file = event.dataTransfer.files?.[0];
    assignLogoFile(file);
});
logoDropzone?.addEventListener("paste", (event) => {
    const file = imageFileFromPasteEvent(event);
    if (!file) return;
    event.preventDefault();
    assignLogoFile(file);
});
modalElement?.addEventListener("shown.bs.modal", () => {
    window.setTimeout(() => logoDropzone?.focus({ preventScroll: true }), 50);
});
document.addEventListener("paste", (event) => {
    if (!modalElement?.classList.contains("show")) return;
    if (
        event.target?.closest?.(
            "input:not([type='file']), textarea, [contenteditable='true']",
        )
    )
        return;
    const file = imageFileFromPasteEvent(event);
    if (!file) return;
    event.preventDefault();
    assignLogoFile(file);
});
const deleteSchoolProfile = async (id) => {
    const schoolProfile = schoolProfiles.find((item) => Number(item.id) === Number(id));
    if (schoolProfile?.is_linked) {
        showError(
            "Cannot Delete Campus",
            schoolProfile.linked_message ||
                "This campus is already linked to other data and cannot be deleted.",
        );
        return;
    }

    if (
        !(
            await showConfirm(
                "Delete School Profile",
                "Are you sure you want to delete this school profile?",
                "Delete",
                "Cancel",
            )
        ).isConfirmed
    )
        return;
    try {
        const response = await fetch(`/school-info/delete/${id}`, {
            method: "DELETE",
            headers: { Accept: "application/json", "X-CSRF-TOKEN": csrfToken },
        });
        const result = await response.json();
        if (!response.ok)
            throw new Error(
                result.message || "Unable to delete school profile.",
            );
        showSuccess("Deleted", result.message);
        fetchSchoolProfiles();
    } catch (error) {
        showError("Error", error.message);
    }
};
async function fetchSchoolProfiles(page = 1, perPage = null) {
    const size = perPage ?? parseInt(perPageInput.value);
    try {
        const response = await fetch(
            `/school-info/fetch?page=${page}&perPage=${size}&search=${encodeURIComponent(search.value)}&sortBy=${sortBy}&sortDir=${sortDir}`,
        );
        const result = await response.json();
        if (!response.ok)
            throw new Error(
                result.message || "Unable to fetch school profiles.",
            );
        schoolProfiles = result.data;
        const offset = (result.current_page - 1) * size;
        table.innerHTML = schoolProfiles.length
            ? schoolProfiles
                  .map(
                      (item, index) => {
                          const hasMap = Boolean(item.google_map_url?.trim());
                          const mapButton = hasMap
                              ? `<div class="btn-group"><button type="button" class="btn btn-success btn-sm dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"><i class="ti ti-map-pin icon"></i>Map</button><div class="dropdown-menu dropdown-menu-end"><button type="button" class="dropdown-item" onclick="schoolProfilesPage.openSchoolMap(${item.id})"><i class="ti ti-map-2 icon"></i>View Map</button><button type="button" class="dropdown-item" onclick="schoolProfilesPage.toggleSchoolMapShareOptions(${item.id}, event)"><i class="ti ti-share icon"></i>Share Map</button><div id="school-map-share-${item.id}" class="d-none px-2 pb-2 pt-1"><div class="school-map-share-options"><button type="button" class="btn btn-outline-info school-map-share-button" title="Telegram" onclick="schoolProfilesPage.shareSchoolMapLink(${item.id}, 'telegram')"><i class="ti ti-brand-telegram"></i></button><button type="button" class="btn btn-outline-primary school-map-share-button" title="Messenger" onclick="schoolProfilesPage.shareSchoolMapLink(${item.id}, 'messenger')"><i class="ti ti-brand-messenger"></i></button><button type="button" class="btn btn-outline-success school-map-share-button" title="WhatsApp" onclick="schoolProfilesPage.shareSchoolMapLink(${item.id}, 'whatsapp')"><i class="ti ti-brand-whatsapp"></i></button><button type="button" class="btn btn-outline-secondary school-map-share-button" title="Copy Link" onclick="schoolProfilesPage.copySchoolMapLink(${item.id})"><i class="ti ti-copy"></i></button><button type="button" class="btn btn-outline-secondary school-map-share-button" title="Share" onclick="schoolProfilesPage.shareSchoolMapLink(${item.id}, 'native')"><i class="ti ti-share"></i></button></div></div></div></div>`
                              : `<button type="button" class="btn btn-secondary btn-sm disabled" title="No Google Map link"><i class="ti ti-map-pin icon"></i>Map</button>`;

                          const phone = formatSchoolProfilePhone(item.phone);
                          const phoneMarkup = phone
                              ? `<span class="school-profile-table-phone"><i class="ti ti-phone"></i>${escapeHtml(phone)}</span>`
                              : "-";
                          const englishAddress = schoolProfileEnglishAddress(item);
                          const khmerAddress = item.address_kh || "";
                          return `<tr><td>${offset + index + 1}</td><td>${item.logo_path ? `<img src="/storage/${escapeHtml(item.logo_path)}" alt="Logo" style="width:40px;height:40px;object-fit:contain;vertical-align:middle">` : "-"}</td><td><small class="text-secondary school-profile-khmer">${escapeHtml(item.school_name_kh)}</small><br>${escapeHtml(item.school_name_en)}</td><td><small class="text-secondary school-profile-khmer">${escapeHtml(item.campus_name_kh)}</small><br>${escapeHtml(item.campus_name_en)}</td><td>${phoneMarkup}</td><td class="school-profile-table-address">${schoolProfileTableAddressMarkup(khmerAddress, englishAddress)}</td><td>${schoolProfileStatusToggle(item)}</td><td class="text-center"><button onclick="schoolProfilesPage.openViewModal(${item.id})" class="btn btn-info btn-sm"><i class="ti ti-eye icon"></i>View</button> ${mapButton} <button onclick="schoolProfilesPage.openEditModal(${item.id})" class="btn btn-primary btn-sm"><i class="ti ti-pencil icon"></i>Edit</button> ${schoolProfileDeleteButtonMarkup(item)}</td></tr>`;
                      },
                  )
                  .join("")
            : `<tr><td colspan="8" class="text-center">No school profiles found.</td></tr>`;
        if (mobileCards) {
            mobileCards.innerHTML = schoolProfiles.length
                ? schoolProfiles
                      .map((item, index) => {
                          const hasMap = Boolean(item.google_map_url?.trim());
                          const mapButton = hasMap
                              ? `<button type="button" class="btn btn-success" onclick="schoolProfilesPage.openSchoolMap(${item.id})"><i class="ti ti-map-pin me-1"></i>Map</button>`
                              : `<button type="button" class="btn btn-outline-secondary" disabled><i class="ti ti-map-pin me-1"></i>Map</button>`;
                          const addressBlock = schoolProfileMobileAddressMarkup(item);
                          const mobilePhone = formatSchoolProfilePhone(item.phone);

                          return `<article class="school-profile-mobile-card">
                            <span class="school-profile-mobile-number">${String(offset + index + 1).padStart(2, "0")}</span>
                            <div class="school-profile-mobile-status">${schoolProfileStatusToggle(item)}</div>
                            <div class="school-profile-mobile-main-row">
                                <div class="school-profile-mobile-logo-wrap">${schoolProfileLogoMarkup(item, "school-profile-mobile-logo")}</div>
                                <div class="school-profile-mobile-title-wrap">
                                    <strong class="school-profile-mobile-name school-profile-khmer">${escapeHtml(item.school_name_kh)}</strong>
                                    <span class="school-profile-mobile-name-en">${escapeHtml(item.school_name_en)}</span>
                                </div>
                            </div>
                            <div class="school-profile-mobile-detail-grid">
                                <div class="school-profile-mobile-campus">
                                    <span class="school-profile-mobile-label">Campus</span>
                                    <strong class="school-profile-mobile-campus-kh school-profile-khmer">${escapeHtml(item.campus_name_kh)}</strong>
                                    <span>${escapeHtml(item.campus_name_en)}</span>
                                </div>
                                <div class="school-profile-mobile-phone">
                                    <span class="school-profile-mobile-label">Phone</span>
                                    <strong>${mobilePhone ? `<i class="ti ti-phone"></i>${escapeHtml(mobilePhone)}` : "-"}</strong>
                                </div>
                            </div>
                            ${addressBlock}
                            <div class="school-profile-mobile-actions">
                                <button onclick="schoolProfilesPage.openViewModal(${item.id})" class="btn btn-info"><i class="ti ti-eye me-1"></i>View</button>
                                ${mapButton}
                                <button onclick="schoolProfilesPage.openEditModal(${item.id})" class="btn btn-primary"><i class="ti ti-pencil me-1"></i>Edit</button>
                                ${schoolProfileDeleteButtonMarkup(item, "")}
                            </div>
                        </article>`;
                      })
                      .join("")
                : `<div class="school-profile-mobile-empty">No school profiles found.</div>`;
        }
        renderPagination(
            result,
            "school-profiles-pagination-container",
            "school-profiles-per-page",
            fetchSchoolProfiles,
        );
        renderPageInfo(result);
    } catch (error) {
        console.error(error);
    }
}
document
    .getElementById("btnNewSchoolProfile")
    ?.addEventListener("click", openCreateModal);
document
    .getElementById("btnNewSchoolProfileMobile")
    ?.addEventListener("click", openCreateModal);
document
    .getElementById("btnPrintSchoolProfiles")
    ?.addEventListener("click", openSchoolProfilesPrintPreview);
formStatusToggle?.addEventListener("click", () =>
    syncFormStatusToggle(statusInput?.value !== "1"),
);
setupSchoolProfileAddressComboboxes();
schoolProfileAddressFields.forEach((type) => {
    const ui = schoolProfileAddressUi(type);
    ui.toggle?.addEventListener("click", async () => {
        try {
            if (!locationOptionsCache) await loadSchoolProfileAddressLocations(type === "country");
        } catch (error) {
            showErrors({}, error.message);
            return;
        }
        document
            .querySelectorAll(".school-profile-address-menu")
            .forEach((menu) => {
                if (menu !== ui.menu) menu.classList.add("d-none");
            });
        ui.menu.classList.toggle("d-none");
        if (!ui.menu.classList.contains("d-none")) {
            ui.search.value = "";
            renderSchoolProfileAddressResults(type);
            ui.search.focus();
        }
    });
    ui.search?.addEventListener("input", () =>
        renderSchoolProfileAddressResults(type),
    );
    ui.results?.addEventListener("click", (event) => {
        const option = event.target.closest("[data-school-profile-address-id]");
        if (!option) return;
        ui.select.value = option.dataset.schoolProfileAddressId;
        ui.select.dispatchEvent(new Event("change", { bubbles: true }));
        ui.menu.classList.add("d-none");
    });
    ui.select?.addEventListener("change", () => {
        setSchoolProfileAddressSelectedText(type);
        filterSchoolProfileAddressLocations(type);
        updateSchoolProfileAddressText();
    });
});
syncSchoolProfileAddressKhmer(
    "address_house_no_en",
    "address_house_no_kh",
    "ផ្ទះលេខ",
);
syncSchoolProfileAddressKhmer(
    "address_street_en",
    "address_street_kh",
    "ផ្លូវ",
);
["address_house_no_en", "address_house_no_kh", "address_street_en", "address_street_kh"].forEach((id) =>
    field(id)?.addEventListener("input", updateSchoolProfileAddressText),
);
document.addEventListener("click", (event) => {
    if (!event.target.closest(".school-profile-address-combobox")) {
        document
            .querySelectorAll(".school-profile-address-menu")
            .forEach((menu) => menu.classList.add("d-none"));
    }
});
modalElement?.addEventListener("show.bs.modal", () => {
    if (creatingNewProfile) resetPhoneToCambodia();
});
modalElement?.addEventListener("hidden.bs.modal", () => {
    if (creatingNewProfile) resetPhoneToCambodia();
    creatingNewProfile = false;
});
perPageInput?.addEventListener("change", () =>
    fetchSchoolProfiles(1, parseInt(perPageInput.value)),
);
search?.addEventListener("keyup", () =>
    fetchSchoolProfiles(1, parseInt(perPageInput.value)),
);
document.querySelectorAll("[data-sort]").forEach((header) =>
    header.addEventListener("click", () => {
        const selectedSort = header.dataset.sort;
        sortDir = sortBy === selectedSort && sortDir === "asc" ? "desc" : "asc";
        sortBy = selectedSort;
        fetchSchoolProfiles(1, parseInt(perPageInput.value));
    }),
);
fetchSchoolProfiles();
window.schoolProfilesPage = {
    openCreateModal,
    openViewModal,
    printSchoolProfile,
    openSchoolProfilesPrintPreview,
    openGoogleMap,
    openSchoolMap,
    toggleSchoolMapShareOptions,
    copyGoogleMapLink,
    copySchoolMapLink,
    shareGoogleMapLink,
    shareSchoolMapLink,
    openEditModal,
    deleteSchoolProfile,
    fetchSchoolProfiles,
};
