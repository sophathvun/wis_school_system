import * as bootstrap from "bootstrap";

document.addEventListener("DOMContentLoaded", () => {
    const nav = document.querySelector(".card-tabs .nav-tabs");
    const content = document.querySelector(".card-tabs .tab-content");
    const departmentLink = nav?.querySelector(
        'a[href="#department-permissions-tab"]',
    )?.closest(".nav-item");
    const roleLink = nav?.querySelector('a[href="#role-permissions-tab"]')?.closest(".nav-item");
    const departmentPane = document.getElementById("department-permissions-tab");
    const rolePane = document.getElementById("role-permissions-tab");
    if (nav && departmentLink && roleLink) nav.insertBefore(departmentLink, roleLink);
    if (content && departmentPane && rolePane) content.insertBefore(departmentPane, rolePane);

    const activateTab = (tabId) => {
        const tabLink = nav?.querySelector(`a[href="#${tabId}"]`);
        if (!tabLink) return;
        bootstrap.Tab.getOrCreateInstance(tabLink).show();
    };

    const urlParams = new URLSearchParams(window.location.search);
    const shouldShowPermissionList =
        window.location.hash === "#permission-list-tab" ||
        ["permissionSortBy", "permissionSortDir", "permission_page", "permission_search"].some((name) =>
            urlParams.has(name),
        );

    if (shouldShowPermissionList) {
        activateTab("permission-list-tab");
    }

    const setToggle = (button, active) => {
        if (!button) return;
        button.classList.toggle("is-active", active);
        button.dataset.status = active ? "1" : "0";
        button.setAttribute("aria-pressed", String(active));
        button.querySelector(".status-toggle-label").textContent = active ? "ON" : "OFF";
        const input =
            button.querySelector('input[type="checkbox"]') ||
            button.closest("[data-permission-group]")?.querySelector(
                `[data-permission-input="${button.dataset.permissionId}"]`,
            );
        if (input) input.checked = active;
    };

    const refreshHierarchy = (form) => {
        const fullAccess = form.querySelector("[data-full-access]");
        if (fullAccess?.dataset.status === "1") {
            form.querySelectorAll("[data-permission-toggle]:not([data-full-access])").forEach((button) =>
                setToggle(button, true),
            );
            form.querySelectorAll("[data-permission-toggle]:not([data-full-access])").forEach((button) => {
                button.disabled = true;
            });
            return;
        }
        form.querySelectorAll('[data-permission-toggle][data-permission-level="main"]').forEach((main) => {
            const mainOn = main.dataset.status === "1";
            form.querySelectorAll(`[data-parent-permission="${main.dataset.permissionId}"]`).forEach((child) => {
                const childButton = child.matches("button")
                    ? child
                    : child.querySelector("[data-permission-toggle]");
                if (!childButton) return;
                childButton.disabled = !mainOn;
                if (!mainOn) setToggle(childButton, false);
            });
        });
        form.querySelectorAll('[data-permission-toggle][data-permission-level="submenu"]').forEach((submenu) => {
            const submenuOn = submenu.dataset.status === "1";
            form.querySelectorAll(`[data-action-parent="${submenu.dataset.permissionId}"]`).forEach((action) => {
                const actionButton = action.querySelector("[data-permission-toggle]");
                if (!actionButton) return;
                actionButton.disabled = !submenuOn || submenu.disabled;
                if (!submenuOn || submenu.disabled) setToggle(actionButton, false);
            });
        });
    };

    document.querySelectorAll("[data-permission-form]").forEach((form) => {
        form.querySelectorAll("[data-permission-toggle]:not([data-full-access])").forEach((button) =>
            button.addEventListener("click", (event) => {
                event.preventDefault();
                if (button.disabled) return;
                setToggle(button, button.dataset.status !== "1");
                refreshHierarchy(form);
            }),
        );
        refreshHierarchy(form);
        const fullAccess = form.querySelector("[data-full-access]");
        fullAccess?.addEventListener("click", (event) => {
            event.preventDefault();
            const enabled = fullAccess.dataset.status !== "1";
            setToggle(fullAccess, enabled);
            form.querySelectorAll("[data-permission-toggle]:not([data-full-access])").forEach((button) => setToggle(button, enabled));
            refreshHierarchy(form);
        });
    });

    // Collapse action lists on phones so large permission trees stay readable.
    const mobilePermissionQuery = window.matchMedia("(max-width: 767px)");
    const setupMobilePermissionModules = () => {
        document.querySelectorAll("[data-permission-module]").forEach((module) => {
            const header = module.querySelector("[data-permission-module-toggle]");
            if (!header || header.dataset.mobileReady === "1") return;
            header.dataset.mobileReady = "1";
            if (mobilePermissionQuery.matches) module.classList.add("is-collapsed");
            header.addEventListener("click", (event) => {
                if (event.target.closest("button")) return;
                module.classList.toggle("is-collapsed");
            });
        });
    };
    setupMobilePermissionModules();
    mobilePermissionQuery.addEventListener?.("change", setupMobilePermissionModules);

    document.querySelectorAll("[data-user-permissions-toggle]").forEach((button) =>
        button.addEventListener("click", () => {
            const row = document.getElementById(button.dataset.userPermissionsToggle);
            if (!row) return;
            const hidden = row.classList.toggle("d-none");
            button.querySelector(".user-permissions-toggle-label").textContent = hidden ? "Show Permissions" : "Hide Permissions";
            button.querySelector("i").className = hidden ? "ti ti-chevron-down me-1" : "ti ti-chevron-up me-1";
        }),
    );

    document.querySelectorAll("[data-permission-module-search]").forEach((search) => {
        const form = search.closest("[data-permission-form]");
        if (!form) return;
        search.addEventListener("input", () => {
            const term = search.value.toLowerCase().trim();
            form.querySelectorAll("[data-permission-group]").forEach((group) => {
                const groupMatches = !term || (group.dataset.permissionGroupLabel || "").includes(term);
                let visibleModules = 0;
                group.querySelectorAll("[data-permission-module]").forEach((module) => {
                    const matches =
                        groupMatches ||
                        !term ||
                        (module.dataset.permissionModuleLabel || "").includes(term) ||
                        module.textContent.toLowerCase().includes(term);
                    module.classList.toggle("d-none", !matches);
                    if (matches) visibleModules += 1;
                });
                group.closest(".col-md-6")?.classList.toggle("d-none", Boolean(term) && !groupMatches && visibleModules === 0);
            });
        });
    });

    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => new bootstrap.Tooltip(element));

    document.querySelectorAll("[data-campus-picker]").forEach((picker) => {
        const toggle = picker.querySelector("[data-campus-picker-toggle]");
        const menu = picker.querySelector("[data-campus-picker-menu]");
        const search = picker.querySelector("[data-campus-picker-search]");
        const label = picker.querySelector("[data-campus-picker-label]");
        const updateLabel = () => {
            const selected = [...picker.querySelectorAll('input[type="checkbox"]:checked')]
                .map((input) => input.closest("label")?.querySelector("span")?.textContent.trim())
                .filter(Boolean);
            picker.classList.toggle("has-value", selected.length > 0);
            label.textContent = selected.join(", ");
        };
        toggle?.addEventListener("click", () => {
            const open = menu.classList.toggle("d-none");
            toggle.setAttribute("aria-expanded", String(!open));
            toggle.querySelector("i")?.classList.toggle("ti-chevron-up", !open);
            toggle.querySelector("i")?.classList.toggle("ti-chevron-down", open);
            if (!open) search?.focus();
        });
        search?.addEventListener("input", () => {
            const term = search.value.toLowerCase().trim();
            picker.querySelectorAll("[data-campus-name]").forEach((option) => {
                option.classList.toggle("d-none", Boolean(term) && !option.dataset.campusName.includes(term));
            });
        });
        picker.querySelectorAll('input[type="checkbox"]').forEach((input) => input.addEventListener("change", updateLabel));
        updateLabel();
    });

    document.querySelectorAll("[data-department-picker]").forEach((picker) => {
        const toggle = picker.querySelector("[data-department-picker-toggle]"), menu = picker.querySelector("[data-department-picker-menu]"), search = picker.querySelector("[data-department-picker-search]"), value = picker.querySelector("[data-department-picker-value]"), label = picker.querySelector("[data-department-picker-label]");
        toggle?.addEventListener("click", () => { const open = menu.classList.toggle("d-none"); toggle.setAttribute("aria-expanded", String(!open)); if (!open) search?.focus(); });
        search?.addEventListener("input", () => { const term = search.value.toLowerCase().trim(); picker.querySelectorAll("[data-department-id]").forEach((option) => option.classList.toggle("d-none", Boolean(term) && !option.textContent.toLowerCase().includes(term))); });
        picker.querySelectorAll("[data-department-id]").forEach((option) => option.addEventListener("click", () => { value.value = option.dataset.departmentId; label.textContent = option.textContent.trim(); menu.classList.add("d-none"); picker.closest("form")?.submit(); }));
    });

    document.addEventListener("click", (event) => {
        document.querySelectorAll("[data-campus-picker]").forEach((picker) => {
            if (picker.contains(event.target)) return;
            const menu = picker.querySelector("[data-campus-picker-menu]");
            const toggle = picker.querySelector("[data-campus-picker-toggle]");
            if (!menu?.classList.contains("d-none")) {
                menu.classList.add("d-none");
                toggle?.setAttribute("aria-expanded", "false");
                toggle?.querySelector("i")?.classList.replace("ti-chevron-up", "ti-chevron-down");
            }
        });
    });

    document.querySelectorAll("[data-access-auto-submit]").forEach((select) => {
        select.addEventListener("change", () => select.form?.submit());
    });


    document.querySelectorAll("[data-access-live-search]").forEach((input) => {
        let timer;
        input.addEventListener("input", () => {
            clearTimeout(timer);
            timer = setTimeout(() => input.form?.requestSubmit(), 350);
        });
    });

    document.querySelectorAll("[data-access-delete-trigger]").forEach((button) => {
        button.addEventListener("click", () => {
            button.closest("span")?.querySelector("form")?.requestSubmit();
        });
    });

    const usersTable = document.querySelector("#users-list-tab table");
    const emails = JSON.parse(usersTable?.dataset.userEmails || "[]");
    if (usersTable && !usersTable.dataset.emailColumnRendered) {
        usersTable.dataset.emailColumnRendered = "1";
        const header = usersTable.querySelector("thead tr");
        const usernameHeader = header?.children[2];
        if (usernameHeader) {
            const emailHeader = document.createElement("th");
            emailHeader.textContent = "Email";
            header.insertBefore(emailHeader, usernameHeader.nextSibling);
        }
        let index = 0;
        usersTable.querySelectorAll("tbody tr").forEach((row) => {
            if (row.id) {
                row.querySelector("td[colspan]")?.setAttribute("colspan", "9");
                return;
            }
            if (row.children.length === 1) {
                row.children[0].setAttribute("colspan", "9");
                return;
            }
            const emailCell = document.createElement("td");
            emailCell.textContent = emails[index++] || "-";
            row.insertBefore(emailCell, row.children[3]);
        });
    }

    const roleHeader = [...(usersTable?.querySelector("thead tr")?.children || [])].find((cell) =>
        cell.textContent.trim() === "Roles",
    );
    if (usersTable && roleHeader) {
        const roleIndex = [...usersTable.querySelector("thead tr").children].indexOf(roleHeader);
        usersTable.querySelectorAll("tbody tr").forEach((row) => {
            if (row.children.length === 1 || row.id) return;
            const cell = row.children[roleIndex];
            if (cell) {
                cell.textContent =
                    [...new Set(cell.textContent.split(",").map((role) => role.trim()).filter(Boolean))].join(", ") || "-";
            }
        });
    }
});

