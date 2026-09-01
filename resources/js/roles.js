const modalEl = document.getElementById("roleModal");
const modal = modalEl ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;

const wrapPremiumFields = () => {
    const body = document.querySelector("#roleModal .modal-body");
    if (!body || body.dataset.premiumFieldsReady) return;
    body.dataset.premiumFieldsReady = "1";

    [...body.querySelectorAll(":scope > .form-label")].forEach((label) => {
        const control = label.nextElementSibling;
        if (!control || !/^(INPUT|TEXTAREA|SELECT)$/.test(control.tagName)) return;
        const field = document.createElement("div");
        field.className = "role-floating-field";
        label.parentNode.insertBefore(field, label);
        field.append(label, control);
        control.classList.remove("mb-3");
    });
};

const syncFloatingFieldValues = () => {
    document.querySelectorAll("#roleModal .role-floating-field").forEach((field) => {
        const control = field.querySelector(".form-control, .form-select");
        if (!control || field.dataset.valueSyncReady) return;
        field.dataset.valueSyncReady = "1";
        const sync = () =>
            field.classList.toggle(
                "has-value",
                String(control.value || "").trim() !== "",
            );
        control.addEventListener("input", sync);
        control.addEventListener("change", sync);
        sync();
    });
};

const initDepartmentCombobox = () => {
    const select = document.querySelector('#roleModal select[name="department_id"]');
    const field = select?.closest(".role-floating-field");
    if (!select || !field || field.dataset.departmentComboboxReady) return;
    field.dataset.departmentComboboxReady = "1";

    select.classList.add("d-none");
    const combo = document.createElement("div");
    combo.className = "role-location-combobox";
    combo.innerHTML =
        '<button type="button" class="role-location-combobox-toggle"><span class="role-location-combobox-selected"></span><i class="ti ti-chevron-down"></i></button><div class="role-location-combobox-menu d-none"><input type="search" class="form-control" placeholder="Search Department"><div class="role-location-combobox-results"></div></div>';
    select.after(combo);

    const button = combo.querySelector("button");
    const menu = combo.querySelector(".role-location-combobox-menu");
    const search = combo.querySelector('input[type="search"]');
    const selected = combo.querySelector(".role-location-combobox-selected");
    const results = combo.querySelector(".role-location-combobox-results");

    const sync = () => {
        selected.textContent = select.value
            ? select.selectedOptions[0]?.textContent || ""
            : "";
        field.classList.toggle("has-value", Boolean(select.value));
    };

    const render = () => {
        const term = search.value.toLowerCase().trim();
        const options = [...select.options].filter(
            (option) => option.value && (!term || option.textContent.toLowerCase().includes(term)),
        );
        results.innerHTML = options.length
            ? options
                  .map(
                      (option) =>
                          `<button type="button" class="role-location-combobox-option" data-value="${option.value}">${option.textContent}</button>`,
                  )
                  .join("")
            : '<div class="text-secondary px-2 py-2">No departments found</div>';
    };

    button.addEventListener("click", () => {
        menu.classList.toggle("d-none");
        if (!menu.classList.contains("d-none")) {
            search.value = "";
            render();
            search.focus();
        }
    });
    search.addEventListener("input", render);
    results.addEventListener("click", (event) => {
        const option = event.target.closest("[data-value]");
        if (!option) return;
        select.value = option.dataset.value;
        select.dispatchEvent(new Event("change", { bubbles: true }));
        menu.classList.add("d-none");
        sync();
    });
    document.addEventListener("click", (event) => {
        if (!combo.contains(event.target)) menu.classList.add("d-none");
    });
    sync();
};

const initStatusToggle = () => {
    const select = document.querySelector('#roleModal select[name="status"]');
    const field = select?.closest(".role-floating-field");
    if (!select || !field || field.dataset.statusToggleReady) return;
    field.dataset.statusToggleReady = "1";

    select.classList.add("d-none");
    const toggle = document.createElement("button");
    toggle.type = "button";
    toggle.className = "status-toggle";
    toggle.innerHTML =
        '<span class="status-toggle-label"></span><span class="status-toggle-knob"></span>';
    select.after(toggle);
    field.querySelector(".form-label")?.remove();

    const globalCheck = document
        .querySelector('#roleModal input[name="is_global"]')
        ?.closest(".form-check");
    if (globalCheck) {
        const settingsRow = document.createElement("div");
        settingsRow.className = "role-settings-row";
        field.parentNode.insertBefore(settingsRow, globalCheck);
        settingsRow.append(globalCheck, toggle, select);
        field.remove();
    }

    const sync = () => {
        const active = select.value === "1";
        toggle.classList.toggle("is-active", active);
        toggle.querySelector(".status-toggle-label").textContent = active ? "ON" : "OFF";
        toggle.setAttribute("aria-pressed", active ? "true" : "false");
        field.classList.toggle("has-value", true);
    };

    toggle.addEventListener("click", () => {
        select.value = select.value === "1" ? "0" : "1";
        sync();
    });
    sync();
};

const initDeleteTriggers = () => {
    document.querySelectorAll("[data-role-delete-trigger]").forEach((button) => {
        if (button.dataset.roleDeleteReady) return;
        button.dataset.roleDeleteReady = "1";
        button.addEventListener("click", () => {
            button.closest("span")?.querySelector("form")?.requestSubmit();
        });
    });
};

const initTooltips = () => {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => {
        bootstrap.Tooltip.getOrCreateInstance(element);
    });
};

const openModalIfNeeded = () => {
    if (!modalEl || modalEl.dataset.openOnLoad !== "1") return;
    modal?.show();
};

document.addEventListener("DOMContentLoaded", () => {
    wrapPremiumFields();
    syncFloatingFieldValues();
    initDepartmentCombobox();
    initStatusToggle();
    initDeleteTriggers();
    initTooltips();

    document.getElementById("btnNewRole")?.addEventListener("click", () => modal?.show());
    openModalIfNeeded();
});
