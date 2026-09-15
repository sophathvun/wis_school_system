import * as bootstrap from "bootstrap";
import intlTelInput from "intl-tel-input";
import "intl-tel-input/styles";

const initStaffPhotoViewer = () => {
    if (document.body.dataset.staffPhotoViewerReady === "1") return;
    const modalElement = document.getElementById("staffPhotoViewModal");
    const image = document.getElementById("staffPhotoViewImage");
    const title = document.getElementById("staffPhotoViewTitle");
    const zoom = document.getElementById("staffPhotoViewZoom");
    const download = document.getElementById("staffPhotoViewDownload");
    if (!modalElement || !image || !title || !zoom || !bootstrap?.Modal) return;

    document.body.dataset.staffPhotoViewerReady = "1";
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const updateZoom = () => {
        image.style.transform = `scale(${zoom.value})`;
    };
    const downloadName = (value = "staff-photo", url = "") => {
        const name =
            String(value || "staff-photo")
                .trim()
                .replace(/[^\p{L}\p{N}]+/gu, "-")
                .replace(/^-+|-+$/g, "") || "staff-photo";
        const extension =
            String(url).match(/\.([a-z0-9]{2,5})(?:[?#]|$)/i)?.[1] || "jpg";
        return `${name}.${extension}`;
    };

    document.addEventListener("click", (event) => {
        const trigger = event.target.closest(".staff-photo-view-trigger");
        if (!trigger) return;
        event.preventDefault();
        event.stopPropagation();

        const photoUrl = trigger.dataset.photoUrl || "";
        image.src = photoUrl;
        title.textContent = trigger.dataset.photoTitle || "Staff Photo";
        zoom.value = "1";
        updateZoom();
        if (download) {
            download.href = photoUrl || "#";
            download.download = downloadName(trigger.dataset.photoTitle, photoUrl);
        }
        modal.show();
    });

    zoom.addEventListener("input", updateZoom);
    document.getElementById("staffPhotoViewZoomIn")?.addEventListener("click", () => {
        zoom.value = Math.min(3, Number(zoom.value) + 0.1).toFixed(2);
        updateZoom();
    });
    document.getElementById("staffPhotoViewZoomOut")?.addEventListener("click", () => {
        zoom.value = Math.max(1, Number(zoom.value) - 0.1).toFixed(2);
        updateZoom();
    });
    document.getElementById("staffPhotoViewZoomReset")?.addEventListener("click", () => {
        zoom.value = "1";
        updateZoom();
    });
};

const initStaffPhotoUploader = () => {
    if (document.body.dataset.userFormEnhanced) return;
    document.body.dataset.userFormEnhanced = "1";
    const escapeHtml = (value) =>
        String(value ?? "").replace(
            /[&<>"']/g,
            (char) =>
                ({
                    "&": "&amp;",
                    "<": "&lt;",
                    ">": "&gt;",
                    '"': "&quot;",
                    "'": "&#039;",
                })[char],
        );
    const formatCambodiaPhoneDisplay = (value = "") => {
        let digits = String(value).replace(/\D/g, "");
        if (digits.startsWith("855")) digits = digits.slice(3);
        if (digits.startsWith("0")) digits = digits.slice(1);
        digits = digits.slice(0, 9);
        if (digits.length <= 2) return digits;
        if (digits.length <= 5) return `${digits.slice(0, 2)} ${digits.slice(2)}`;
        return `${digits.slice(0, 2)} ${digits.slice(2, 5)} ${digits.slice(5)}`;
    };
    const normalizeCambodiaPhoneValue = (value = "") => {
        let digits = String(value).replace(/\D/g, "");
        if (digits.startsWith("855")) digits = digits.slice(3);
        if (digits.startsWith("0")) digits = digits.slice(1);
        return digits ? `+855${digits}` : "";
    };
    const syncCambodiaPhoneInput = (visible, hidden, intl) => {
        if (!visible || !hidden) return;
        hidden.value = intl?.getNumber() || normalizeCambodiaPhoneValue(visible.value);
    };
    const userPhoneVisible = document.querySelector(
        "#userModal #user_phone_number",
    );
    const userPhoneHidden = document.querySelector("#userModal #user_phone");
    const userPhoneIntl = userPhoneVisible
        ? intlTelInput(userPhoneVisible, {
              initialCountry: "kh",
              nationalMode: true,
              separateDialCode: true,
              loadUtils: () => import("intl-tel-input/utils"),
          })
        : null;
    if (userPhoneIntl && userPhoneHidden?.value) {
        userPhoneIntl.setNumber(userPhoneHidden.value);
        userPhoneVisible.value = formatCambodiaPhoneDisplay(userPhoneHidden.value);
    }
    userPhoneVisible?.addEventListener("input", () => {
        userPhoneVisible.value = formatCambodiaPhoneDisplay(userPhoneVisible.value);
        syncCambodiaPhoneInput(userPhoneVisible, userPhoneHidden, userPhoneIntl);
    });
    userPhoneVisible?.addEventListener("countrychange", () => {
        syncCambodiaPhoneInput(userPhoneVisible, userPhoneHidden, userPhoneIntl);
    });
    const staffPhotoInput = document.getElementById("staff_photo");
    const staffPhotoDropzone = document.getElementById("staffPhotoDropzone");
    const staffPhotoPreview = document.getElementById("staffPhotoPreview");
    const staffPhotoPreviewContainer = document.getElementById(
        "staffPhotoPreviewContainer",
    );
    const staffPhotoCropModalElement = document.getElementById(
        "staffPhotoCropModal",
    );
    const staffPhotoCropModal =
        staffPhotoCropModalElement && bootstrap.Modal
            ? bootstrap.Modal.getOrCreateInstance(staffPhotoCropModalElement)
            : null;
    const staffUserModalElement = document.getElementById("userModal");
    const staffUserModal =
        staffUserModalElement && bootstrap.Modal
            ? bootstrap.Modal.getOrCreateInstance(staffUserModalElement)
            : null;
    const staffPhotoCropCanvas = document.getElementById(
        "staffPhotoCropCanvas",
    );
    const staffPhotoCropContext = staffPhotoCropCanvas?.getContext("2d");
    const staffPhotoZoom = document.getElementById("staffPhotoZoom");
    let staffPhotoCropImage = null;
    let staffPhotoCropScale = 1;
    let staffPhotoCropRotation = 0;
    let staffPhotoCropOffsetX = 0;
    let staffPhotoCropOffsetY = 0;
    let staffPhotoCropDragging = false;
    let staffPhotoCropStart = null;
    let staffReturnToUserModal = false;

    if (
        staffPhotoDropzone &&
        staffPhotoPreviewContainer &&
        !staffPhotoDropzone.contains(staffPhotoPreviewContainer)
    ) {
        staffPhotoDropzone.appendChild(staffPhotoPreviewContainer);
    }
    staffUserModalElement?.addEventListener("shown.bs.modal", () => {
        window.setTimeout(
            () => staffPhotoDropzone?.focus({ preventScroll: true }),
            50,
        );
    });

    const makeUserSearchableSelect = (select, multiple = false) => {
        if (!select || select.dataset.searchableReady) return;
        multiple = multiple || select.multiple;
        select.dataset.searchableReady = "1";
        const searchLabel =
            select.name === "gender"
                ? "Gender"
                : select.name === "department_id"
                  ? "Department"
                  : select.name === "role_id"
                    ? "Role"
                    : select.name === "campuses[]"
                      ? "Campus"
                      : select.name === "position_id"
                        ? "Position"
                        : select.name === "login_identifier"
                          ? "Allowed Login Method"
                          : "option";
        const wrapper = document.createElement("div");
        wrapper.className =
            "location-combobox position-combobox user-searchable-combobox";
        wrapper.classList.toggle("is-multiple", multiple);
        const toggle = document.createElement("button");
        toggle.type = "button";
        toggle.className = "location-combobox-toggle";
        toggle.innerHTML = `<span class="location-combobox-floating-label">${escapeHtml(searchLabel)}</span><span class="location-combobox-selected"></span><i class="ti ti-chevron-down"></i>`;
        const menu = document.createElement("div");
        menu.className = "location-combobox-menu d-none";
        const search = document.createElement("input");
        search.type = "search";
        search.className = "form-control location-combobox-search";
        search.placeholder = `Search ${searchLabel}`;
        const results = document.createElement("div");
        results.className = "location-combobox-results";
        menu.append(search, results);
        wrapper.append(toggle, menu);
        select.classList.add("d-none");
        select.previousElementSibling?.classList?.contains("form-label") &&
            select.previousElementSibling.classList.add("d-none");
        select.parentElement.insertBefore(wrapper, select);

        const selectedText = () =>
            [...select.selectedOptions]
                .map((option) => option.textContent.trim())
                .filter(Boolean)
                .join(", ");
        const sync = () => {
            toggle.querySelector(".location-combobox-selected").textContent =
                selectedText();
            wrapper.classList.toggle("has-value", Boolean(selectedText()));
            results.querySelectorAll("button").forEach((button) => {
                const option = select.querySelector(
                    `option[value="${CSS.escape(button.dataset.value)}"]`,
                );
                button.classList.toggle(
                    "is-selected",
                    Boolean(option?.selected),
                );
            });
        };
        const render = () => {
            const term = search.value.trim().toLowerCase();
            const options = [...select.options].filter(
                (option) =>
                    option.value &&
                    option.textContent.toLowerCase().includes(term),
            );
            results.innerHTML = options.length
                ? options
                      .map((option) => {
                          const content = multiple
                              ? `<span class="user-multi-option-content"><input class="form-check-input" type="checkbox" tabindex="-1" aria-hidden="true" ${option.selected ? "checked" : ""}><span class="user-multi-option-label">${escapeHtml(option.textContent)}</span></span>`
                              : `<span>${escapeHtml(option.textContent)}</span>`;
                          return `<button type="button" class="location-combobox-option${option.selected ? " is-selected" : ""}" data-value="${escapeHtml(option.value)}">${content}</button>`;
                      })
                      .join("")
                : '<div class="text-secondary px-2 py-2">No options found</div>';
        };
        let menuPortaled = false;
        const clearMenuPosition = () => {
            menu.style.position = "";
            menu.style.left = "";
            menu.style.right = "";
            menu.style.top = "";
            menu.style.bottom = "";
            menu.style.width = "";
            menu.style.maxHeight = "";
            results.style.maxHeight = "";
            menu.classList.remove("is-portaled");
        };
        const positionMenu = () => {
            if (!wrapper.classList.contains("is-open")) return;
            const rect = toggle.getBoundingClientRect();
            const viewportWidth = document.documentElement.clientWidth;
            const viewportHeight = window.innerHeight;
            const menuWidth = Math.min(rect.width + 8, viewportWidth - 16);
            const left = Math.max(
                8,
                Math.min(rect.left - 4, viewportWidth - menuWidth - 8),
            );
            const spaceBelow = viewportHeight - rect.bottom - 14;
            const spaceAbove = rect.top - 14;
            const optionCount =
                results.querySelectorAll(".location-combobox-option").length ||
                1;
            const estimatedMenuHeight = Math.min(360, 68 + optionCount * 46);
            const openAbove =
                spaceBelow < 140 &&
                spaceAbove > spaceBelow &&
                spaceAbove > estimatedMenuHeight;
            const availableSpace = openAbove ? spaceAbove : spaceBelow;
            const resultsHeight = Math.max(120, Math.min(300, availableSpace - 78));
            Object.assign(menu.style, {
                position: "fixed",
                left: `${left}px`,
                right: "auto",
                top: openAbove
                    ? `${Math.max(8, rect.top - Math.min(estimatedMenuHeight, resultsHeight + 78) - 6)}px`
                    : `${rect.bottom + 6}px`,
                bottom: "auto",
                width: `${menuWidth}px`,
                maxHeight: `${resultsHeight + 78}px`,
            });
            results.style.maxHeight = `${resultsHeight}px`;
        };
        const closeMenu = () => {
            wrapper.classList.remove("is-open");
            menu.classList.add("d-none");
            if (menuPortaled) {
                wrapper.appendChild(menu);
                menuPortaled = false;
            }
            clearMenuPosition();
        };
        const openMenu = () => {
            closeOtherComboboxes();
            render();
            wrapper.classList.add("is-open");
            menu.classList.remove("d-none");
            if (!menuPortaled) {
                document.body.appendChild(menu);
                menuPortaled = true;
            }
            menu.classList.add("is-portaled");
            positionMenu();
            search.focus();
        };
        const closeOtherComboboxes = () => {
            document
                .querySelectorAll(".user-searchable-combobox")
                .forEach((combo) => {
                    if (combo === wrapper) return;
                    combo.dispatchEvent(new CustomEvent("user-combobox-close"));
                });
        };
        wrapper.addEventListener("user-combobox-close", closeMenu);
        toggle.addEventListener("click", (event) => {
            event.stopPropagation();
            if (wrapper.classList.contains("is-open")) closeMenu();
            else openMenu();
        });
        search.addEventListener("input", render);
        search.addEventListener("click", (event) => event.stopPropagation());
        results.addEventListener("click", (event) => {
            const button = event.target.closest("button[data-value]");
            if (!button) return;
            event.preventDefault();
            event.stopPropagation();
            const option = select.querySelector(
                `option[value="${CSS.escape(button.dataset.value)}"]`,
            );
            if (!option) return;
            if (multiple) {
                option.selected = !option.selected;
                render();
                sync();
                wrapper.classList.add("is-open");
                menu.classList.remove("d-none");
                if (!menuPortaled) {
                    document.body.appendChild(menu);
                    menuPortaled = true;
                }
                menu.classList.add("is-portaled");
                positionMenu();
                search.focus({ preventScroll: true });
            } else {
                select.value = option.value;
                select.dispatchEvent(new Event("change", { bubbles: true }));
                sync();
                closeMenu();
                return;
            }
            select.dispatchEvent(new Event("change", { bubbles: true }));
        });
        select.addEventListener("change", sync);
        document.addEventListener("click", (event) => {
            if (!wrapper.contains(event.target) && !menu.contains(event.target))
                closeMenu();
        });
        window.addEventListener("resize", positionMenu);
        document.addEventListener("scroll", positionMenu, true);
        render();
        sync();
    };

    const genderSelect = document.querySelector(
        '#userModal select[name="gender"]',
    );
    document
        .querySelectorAll('#userModal select option[value=""]')
        .forEach((option) => {
            option.textContent = "";
        });
    const dobInput = document.querySelector(
        '#userModal input[name="date_of_birth"]',
    );
    if (dobInput) dobInput.removeAttribute("placeholder");
    document.querySelector("#userModal details")?.remove();
    if (dobInput) {
        const dateColumn = dobInput.closest(".col-md-3");
        if (dateColumn && !dateColumn.querySelector(".user-date-picker")) {
            const initialValue = dobInput.value || "";
            const picker = document.createElement("div");
            picker.className = "date-picker user-date-picker";
            const initialDisplay = initialValue
                ? initialValue.split("-").reverse().join("-")
                : "";
            picker.innerHTML = `<div class="date-picker-input-row"><input type="text" class="form-control date-picker-direct" inputmode="numeric" value="${initialDisplay}" aria-label="Date of Birth"><button type="button" class="date-picker-trigger date-picker-calendar-button" aria-label="Open calendar"><i class="ti ti-calendar"></i></button></div><input type="hidden" name="date_of_birth" value="${initialValue}"><div class="date-picker-popup d-none"><div class="date-picker-header"><button type="button" class="date-picker-nav" data-date-prev><i class="ti ti-chevron-left"></i></button><button type="button" class="date-picker-year-toggle" data-date-month></button><button type="button" class="date-picker-nav" data-date-next><i class="ti ti-chevron-right"></i></button></div><div class="date-picker-year-popup d-none"><div class="date-picker-years"></div></div><div class="date-picker-grid"><div class="date-picker-weekdays"><span>SU</span><span>MO</span><span>TU</span><span>WE</span><span>TH</span><span>FR</span><span>SA</span></div><div class="date-picker-days"></div></div></div>`;
            dobInput.remove();
            dateColumn.appendChild(picker);
            dateColumn.classList.add("premium-form-field");
            const hiddenDate = picker.querySelector(
                'input[name="date_of_birth"]',
            );
            const directInput = picker.querySelector(".date-picker-direct");
            const trigger = picker.querySelector(".date-picker-trigger");
            const popup = picker.querySelector(".date-picker-popup");
            const days = picker.querySelector(".date-picker-days");
            const monthButton = picker.querySelector("[data-date-month]");
            const yearPopup = picker.querySelector(".date-picker-year-popup");
            const years = picker.querySelector(".date-picker-years");
            let cursor = initialValue
                ? new Date(`${initialValue}T00:00:00`)
                : new Date();
            cursor = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
            const iso = (date) =>
                `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
            const renderYears = () => {
                const current = cursor.getFullYear();
                const startYear = 1900;
                const endYear = new Date().getFullYear() + 5;
                years.innerHTML = Array.from(
                    { length: endYear - startYear + 1 },
                    (_, index) => {
                        const year = startYear + index;
                        const selectedClass = year === current ? " is-selected" : "";
                        return `<button type="button" class="date-picker-year${selectedClass}" data-date-year="${year}">${year}</button>`;
                    },
                ).join("");
                years.querySelector(".is-selected")?.scrollIntoView({ block: "center" });
            };
            const render = () => {
                monthButton.textContent = cursor.toLocaleDateString("en-US", {
                    month: "long",
                    year: "numeric",
                });
                const first = new Date(
                    cursor.getFullYear(),
                    cursor.getMonth(),
                    1,
                );
                const count = new Date(
                    cursor.getFullYear(),
                    cursor.getMonth() + 1,
                    0,
                ).getDate();
                const cells = [];
                for (let i = 0; i < first.getDay(); i++)
                    cells.push(
                        new Date(
                            cursor.getFullYear(),
                            cursor.getMonth(),
                            i - first.getDay() + 1,
                        ),
                    );
                for (let day = 1; day <= count; day++)
                    cells.push(
                        new Date(cursor.getFullYear(), cursor.getMonth(), day),
                    );
                while (cells.length < 42)
                    cells.push(
                        new Date(
                            cursor.getFullYear(),
                            cursor.getMonth() + 1,
                            cells.length - first.getDay() - count + 1,
                        ),
                    );
                days.innerHTML = cells
                    .map(
                        (date) =>
                            `<button type="button" class="date-picker-day${date.getMonth() !== cursor.getMonth() ? " is-outside" : ""}${iso(date) === hiddenDate.value ? " is-selected" : ""}" data-date-value="${iso(date)}">${date.getDate()}</button>`,
                    )
                    .join("");
                renderYears();
            };
            trigger.addEventListener("click", (event) => {
                event.stopPropagation();
                popup.classList.toggle("d-none");
                render();
            });
            picker
                .querySelector("[data-date-prev]")
                .addEventListener("click", () => {
                    cursor = new Date(
                        cursor.getFullYear(),
                        cursor.getMonth() - 1,
                        1,
                    );
                    render();
                });
            picker
                .querySelector("[data-date-next]")
                .addEventListener("click", () => {
                    cursor = new Date(
                        cursor.getFullYear(),
                        cursor.getMonth() + 1,
                        1,
                    );
                    yearPopup.classList.add("d-none");
                    render();
                });
            monthButton.addEventListener("click", (event) => {
                event.preventDefault();
                event.stopPropagation();
                yearPopup.classList.toggle("d-none");
                if (!yearPopup.classList.contains("d-none")) renderYears();
            });
            years.addEventListener("click", (event) => {
                event.preventDefault();
                event.stopPropagation();
                const button = event.target.closest("[data-date-year]");
                if (!button) return;
                cursor = new Date(Number(button.dataset.dateYear), cursor.getMonth(), 1);
                yearPopup.classList.add("d-none");
                render();
            });
            days.addEventListener("click", (event) => {
                const button = event.target.closest("[data-date-value]");
                if (!button) return;
                hiddenDate.value = button.dataset.dateValue;
                directInput.value = button.dataset.dateValue
                    .split("-")
                    .reverse()
                    .join("-");
                picker.classList.add("has-value");
                popup.classList.add("d-none");
                render();
                hiddenDate.dispatchEvent(
                    new Event("change", { bubbles: true }),
                );
            });
            directInput.addEventListener("input", () => {
                const value = directInput.value.trim();
                const match = value.match(
                    /^(\d{1,2})[-\/]?(\d{1,2})[-\/]?(\d{4})$/,
                );
                if (!match) {
                    hiddenDate.value = "";
                    picker.classList.remove("has-value");
                    return;
                }
                const day = String(match[1]).padStart(2, "0");
                const month = String(match[2]).padStart(2, "0");
                const year = match[3];
                const date = new Date(
                    Number(year),
                    Number(month) - 1,
                    Number(day),
                );
                if (
                    date.getFullYear() !== Number(year) ||
                    date.getMonth() !== Number(month) - 1 ||
                    date.getDate() !== Number(day)
                )
                    return;
                hiddenDate.value = `${year}-${month}-${day}`;
                cursor = new Date(Number(year), Number(month) - 1, 1);
                picker.classList.add("has-value");
                hiddenDate.dispatchEvent(
                    new Event("change", { bubbles: true }),
                );
            });
            document.addEventListener("click", (event) => {
                if (!picker.contains(event.target)) {
                    popup.classList.add("d-none");
                    yearPopup.classList.add("d-none");
                }
            });
            if (initialValue) picker.classList.add("has-value");
            render();
        }
    }
    const positionSelect = document.querySelector(
        '#userModal select[name="position_id"]',
    );
    const statusSelect = document.querySelector(
        '#userModal select[name="status"]',
    );
    const statusColumn = statusSelect?.closest(".col-md-4");
    const userIdInput = document.querySelector(
        '#userModal input[name="user_id"]',
    );
    const editingCurrentUser =
        userIdInput?.value &&
        String(userIdInput.value) === String(window.currentUserId || "");
    if (statusSelect && !userIdInput?.value) statusSelect.value = "1";
    if (statusSelect && editingCurrentUser) statusSelect.value = "1";
    if (statusSelect && statusColumn) {
        const globalCheck = statusColumn.querySelector("label.form-check");
        const controls = document.createElement("div");
        controls.className =
            "user-status-controls d-flex align-items-center gap-3";
        const toggle = document.createElement("button");
        toggle.type = "button";
        toggle.className = `status-toggle ${statusSelect.value === "1" ? "is-active" : ""}`;
        toggle.disabled = editingCurrentUser;
        if (editingCurrentUser)
            toggle.title = "You cannot deactivate your own account";
        toggle.innerHTML = `<span class="status-toggle-label">${statusSelect.value === "1" ? "ON" : "OFF"}</span><span class="status-toggle-knob"></span>`;
        toggle.addEventListener("click", () => {
            const active = statusSelect.value !== "1";
            statusSelect.value = active ? "1" : "0";
            toggle.className = `status-toggle ${active ? "is-active" : ""}`;
            toggle.innerHTML = `<span class="status-toggle-label">${active ? "ON" : "OFF"}</span><span class="status-toggle-knob"></span>`;
        });
        statusSelect.classList.add("d-none");
        statusColumn.querySelector(".form-label")?.remove();
        if (globalCheck) controls.append(globalCheck);
        controls.append(toggle, statusSelect);
        statusColumn.append(controls);
    }
    const userPassword = document.querySelector(
        '#userModal input[name="password"]',
    );
    const userPasswordConfirmation = document.querySelector(
        '#userModal input[name="password_confirmation"]',
    );
    document
        .querySelector("#userModal form")
        ?.addEventListener("submit", () => {
            if (userPhoneHidden)
                userPhoneHidden.value =
                    userPhoneIntl?.getNumber() ||
                    normalizeCambodiaPhoneValue(userPhoneVisible?.value || "");
        });
    const addUserPasswordToggle = (input) => {
        if (
            !input ||
            input.parentElement.classList.contains("premium-password-field")
        )
            return;
        const wrapper = document.createElement("div");
        wrapper.className = "premium-password-field premium-form-field";
        input.parentElement.insertBefore(wrapper, input);
        wrapper.appendChild(input);
        const label = input
            .closest('[class*="col-"]')
            ?.querySelector(".form-label");
        if (label) wrapper.prepend(label);
        const button = document.createElement("button");
        button.type = "button";
        button.className = "premium-password-toggle";
        button.setAttribute("aria-label", "Show password");
        button.innerHTML = '<i class="ti ti-eye"></i>';
        button.addEventListener("click", () => {
            const visible = input.type === "text";
            input.type = visible ? "password" : "text";
            button.setAttribute(
                "aria-label",
                visible ? "Show password" : "Hide password",
            );
            button.innerHTML = `<i class="ti ${visible ? "ti-eye" : "ti-eye-off"}"></i>`;
        });
        wrapper.appendChild(button);
        const syncLabel = () =>
            wrapper.classList.toggle("has-value", Boolean(input.value));
        input.addEventListener("input", syncLabel);
        syncLabel();
    };
    addUserPasswordToggle(userPassword);
    addUserPasswordToggle(userPasswordConfirmation);
    if (
        userPassword &&
        !userPassword
            .closest(".col-md-3")
            ?.querySelector(".profile-password-strength")
    ) {
        const strength = document.createElement("div");
        strength.className = "profile-password-strength";
        strength.innerHTML =
            '<div class="profile-password-strength-header"><span>Password Strength</span><span class="profile-password-strength-value">Weak</span></div><div class="profile-password-strength-bar"><span class="profile-password-strength-fill"></span></div><div class="profile-password-rules"><span class="profile-password-rule" data-rule="length">8 Chars</span><span class="profile-password-rule" data-rule="upper">A-Z</span><span class="profile-password-rule" data-rule="lower">a-z</span><span class="profile-password-rule" data-rule="number">123</span><span class="profile-password-rule" data-rule="special">@#$</span></div>';
        userPassword.closest(".premium-password-field")?.after(strength);
        const note = userPassword
            .closest('[class*="col-"]')
            ?.querySelector("small.text-secondary");
        const rules = strength.querySelector(".profile-password-rules");
        if (note && rules) {
            const meta = document.createElement("div");
            meta.className = "profile-password-meta";
            note.classList.add("profile-password-note");
            meta.append(note, rules);
            strength.appendChild(meta);
        }
        const updateStrength = () => {
            const value = userPassword.value;
            const checks = {
                length: value.length >= 8,
                upper: /[A-Z]/.test(value),
                lower: /[a-z]/.test(value),
                number: /\d/.test(value),
                special: /[^A-Za-z0-9]/.test(value),
            };
            Object.entries(checks).forEach(([rule, valid]) =>
                strength
                    .querySelector(`[data-rule="${rule}"]`)
                    ?.classList.toggle("is-valid", valid),
            );
            const score = Object.values(checks).filter(Boolean).length;
            const level = score >= 4 ? "strong" : score >= 2 ? "medium" : "";
            const label = strength.querySelector(
                ".profile-password-strength-value",
            );
            const fill = strength.querySelector(
                ".profile-password-strength-fill",
            );
            label.textContent =
                score >= 4 ? "Strong" : score >= 2 ? "Medium" : "Weak";
            label.className = `profile-password-strength-value ${level}`;
            fill.style.width = `${score * 20}%`;
            fill.className = `profile-password-strength-fill ${level}`;
        };
        userPassword.addEventListener("input", updateStrength);
        updateStrength();
    }
    const userModal = document.getElementById("userModal");
    const userFieldsRow = document.querySelector(
        "#userModal .modal-body > .row.g-3",
    );
    const normalizeUserFormLayout = () => {
        const row = document.querySelector("#userModal .modal-body > .row.g-3");
        const columns = row?.querySelector(".user-form-columns");
        const left = columns?.querySelector(".user-form-column-main");
        const right = columns?.querySelector(".user-form-column-account");
        if (!row || !columns || !left || !right) return;
        const photoField = row
            .querySelector('input[name="photo"]')
            ?.closest('[class*="col-"]');
        if (photoField) {
            photoField.classList.add("user-form-photo-field");
            row.insertBefore(photoField, columns);
        }
        const leftNames = new Set([
            "staff_id",
            "name",
            "gender",
            "date_of_birth",
            "phone",
            "position_id",
            "department_id",
            "campuses[]",
            "role_id",
            "login_identifier",
            "status",
        ]);
        const rightNames = new Set([
            "username",
            "email",
            "password",
            "password_confirmation",
        ]);
        const fieldColumns = new Set();
        row.querySelectorAll("[name]").forEach((control) => {
            if (control.name === "photo") return;
            const column = control.closest('[class*="col-"]');
            if (column) fieldColumns.add(column);
        });
        fieldColumns.forEach((column) => {
            const control = column.querySelector("[name]");
            if (!control || column.querySelector("#staffPhotoDropzone")) return;
            if (leftNames.has(control.name)) left.appendChild(column);
            else if (rightNames.has(control.name)) right.appendChild(column);
        });
        const staffOrder = [
            "staff_id",
            "name",
            "gender",
            "date_of_birth",
            "phone",
            "campuses[]",
            "position_id",
            "department_id",
        ];
        [...left.children]
            .filter((item) => item.querySelector("[name]"))
            .sort((a, b) => {
                const aName = a.querySelector("[name]")?.name;
                const bName = b.querySelector("[name]")?.name;
                const aIndex = staffOrder.indexOf(aName);
                const bIndex = staffOrder.indexOf(bName);
                return (aIndex < 0 ? 999 : aIndex) - (bIndex < 0 ? 999 : bIndex);
            })
            .forEach((field) => left.appendChild(field));
    };
    if (userFieldsRow && !userFieldsRow.dataset.userColumnsReady) {
        userFieldsRow.dataset.userColumnsReady = "1";
        const modalBody = userFieldsRow.parentElement;
        const photoField = userFieldsRow
            .querySelector('input[type="file"]')
            ?.closest(".col-12");
        photoField?.classList.add("user-form-photo-field");
        const field = (selector) =>
            userFieldsRow.querySelector(selector)?.closest('[class*="col-"]');
        const left = document.createElement("div");
        const right = document.createElement("div");
        left.className = "user-form-column user-form-column-main";
        right.className = "user-form-column user-form-column-account";
        const leftHeading = document.createElement("div");
        leftHeading.className = "user-form-section-title user-form-section-title-staff";
        leftHeading.textContent = "STAFF INFORMATION";
        const rightHeading = document.createElement("div");
        rightHeading.className = "user-form-section-title user-form-section-title-login";
        rightHeading.textContent = "USERNAME - LOGIN";
        left.appendChild(leftHeading);
        right.appendChild(rightHeading);
        const leftRows = [
            ["staff_id", "name"],
            ["gender", "date_of_birth"],
            ["phone", "campuses[]"],
            ["position_id", "department_id"],
            ["role_id", "login_identifier"],
            ["status"],
        ];
        leftRows.forEach((names) => {
            names.forEach((name) => {
                const item =
                    field(`input[name="${name}"]`) ||
                    field(`select[name="${name}"]`) ||
                    field(`textarea[name="${name}"]`) ||
                    userFieldsRow
                        .querySelector(`[name="${name}"]`)
                        ?.closest('[class*="col-"]');
                if (item) left.appendChild(item);
            });
        });
        [
            "username",
            "email",
            "password",
            "password_confirmation",
        ].forEach((name) => {
            const item =
                field(`input[name="${name}"]`) ||
                field(`select[name="${name}"]`) ||
                field(`textarea[name="${name}"]`) ||
                userFieldsRow
                    .querySelector(`[name="${name}"]`)
                    ?.closest('[class*="col-"]');
            if (item) right.appendChild(item);
        });
        const columns = document.createElement("div");
        columns.className = "user-form-columns";
        columns.append(left, right);
        const untouched = [];
        [...userFieldsRow.children].forEach((column) => {
            const control = column.querySelector("[name]");
            if (!control) {
                untouched.push(column);
                return;
            }
            if (
                control.name === "photo" ||
                column === photoField ||
                left.contains(column) ||
                right.contains(column)
            )
                return;
            untouched.push(column);
        });
        const beforeSections = photoField
            ? [photoField, ...untouched.filter((column) => column !== photoField)]
            : untouched;
        userFieldsRow.replaceChildren(...beforeSections, columns);
        normalizeUserFormLayout();
    }
    const preview = document.getElementById("staffPhotoPreview");
    const previewContainer = document.getElementById(
        "staffPhotoPreviewContainer",
    );
    const initialPreview = preview?.dataset.initialPhoto;
    if (preview && previewContainer && initialPreview) {
        preview.src = initialPreview;
        previewContainer.classList.remove("d-none");
    }
    document
        .querySelectorAll(".card table .badge")
        .forEach((badge) => {
            if (
                !/^(active|inactive)$/i.test(badge.textContent.trim()) ||
                badge.closest("[data-status-toggle]")
            )
                return;
            const row = badge.closest("tr");
            const edit = row?.querySelector('a[href*="edit="]');
            const id = edit?.href.match(/[?&]edit=(\d+)/)?.[1];
            if (!id || !window.statusToggleMarkup) return;
            badge.outerHTML = window.statusToggleMarkup(
                "user",
                id,
                /^active$/i.test(badge.textContent.trim()),
            );
        });
    const openUserModalSafely = () => {
        const modal = document.getElementById("userModal");
        if (!modal) return;
        try {
            if (bootstrap?.Modal) {
                bootstrap.Modal.getOrCreateInstance(modal).show();
                return;
            }
        } catch (error) {
            console.warn(
                "Bootstrap modal initialization failed; using fallback.",
                error,
            );
        }
        document.querySelector(".modal-backdrop.user-modal-backdrop")?.remove();
        const backdrop = document.createElement("div");
        backdrop.className = "modal-backdrop fade show user-modal-backdrop";
        document.body.appendChild(backdrop);
        modal.classList.add("show");
        modal.style.display = "block";
        modal.setAttribute("aria-hidden", "false");
        modal.removeAttribute("inert");
        document.body.classList.add("modal-open");
    };
    window.openUserModalSafely = openUserModalSafely;
    window.openUserModal = () => openUserModalSafely();
    if (userModal?.dataset.openOnLoad === "1") openUserModalSafely();
    const searchableSelects = [
        [genderSelect, false],
        [
            document.querySelector('#userModal select[name="department_id"]'),
            false,
        ],
        [positionSelect, false],
        [document.querySelector('#userModal select[name="role_id"]'), false],
        [
            document.querySelector('#userModal select[name="login_identifier"]'),
            false,
        ],
        [document.querySelector('#userModal select[name="campuses[]"]'), true],
    ];
    searchableSelects.forEach(([select, multiple]) =>
        makeUserSearchableSelect(select, multiple),
    );

    const showStaffPhotoPreview = (file) => {
        if (!staffPhotoPreview || !staffPhotoPreviewContainer || !file) return;
        staffPhotoPreview.src = URL.createObjectURL(file);
        staffPhotoPreviewContainer.classList.remove("d-none");
    };

    const drawStaffPhotoCrop = () => {
        if (
            !staffPhotoCropContext ||
            !staffPhotoCropCanvas ||
            !staffPhotoCropImage
        )
            return;
        const canvas = staffPhotoCropCanvas;
        const context = staffPhotoCropContext;
        const image = staffPhotoCropImage;
        context.clearRect(0, 0, canvas.width, canvas.height);
        const _root = getComputedStyle(document.documentElement);
        const _surface =
            _root.getPropertyValue("--tblr-bg-surface")?.trim() ||
            (window.matchMedia &&
            window.matchMedia("(prefers-color-scheme: dark)").matches
                ? "#111827"
                : "#fff");
        context.fillStyle = _surface;
        context.fillRect(0, 0, canvas.width, canvas.height);
        const baseScale = Math.max(
            canvas.width / image.width,
            canvas.height / image.height,
        );
        const scale = baseScale * staffPhotoCropScale;
        context.save();
        context.translate(
            canvas.width / 2 + staffPhotoCropOffsetX,
            canvas.height / 2 + staffPhotoCropOffsetY,
        );
        context.rotate((staffPhotoCropRotation * Math.PI) / 180);
        context.drawImage(
            image,
            -(image.width * scale) / 2,
            -(image.height * scale) / 2,
            image.width * scale,
            image.height * scale,
        );
        context.restore();
    };

    const resetStaffPhotoCrop = () => {
        staffPhotoCropScale = 1;
        staffPhotoCropRotation = 0;
        staffPhotoCropOffsetX = 0;
        staffPhotoCropOffsetY = 0;
        if (staffPhotoZoom) staffPhotoZoom.value = "1";
        drawStaffPhotoCrop();
    };

    const openStaffPhotoCrop = (file) => {
        const isImage =
            file &&
            ((file.type || "").startsWith("image/") ||
                /\.(jpe?g|png|webp|gif|bmp|heic|heif)$/i.test(file.name || ""));
        if (!isImage) return;
        const reader = new FileReader();
        reader.onload = () => {
            const image = new Image();
            image.onload = () => {
                staffPhotoCropImage = image;
                resetStaffPhotoCrop();
                const showCropModal = () => staffPhotoCropModal?.show();
                if (
                    staffUserModalElement?.classList.contains("show") &&
                    staffUserModal
                ) {
                    staffReturnToUserModal = true;
                    staffUserModalElement.addEventListener(
                        "hidden.bs.modal",
                        showCropModal,
                        { once: true },
                    );
                    staffUserModal.hide();
                } else {
                    showCropModal();
                }
            };
            image.src = reader.result;
        };
        reader.readAsDataURL(file);
    };

    const imageFileFromPasteEvent = (
        event,
        filename = "pasted-staff-photo.png",
    ) => {
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

    staffPhotoCropModalElement?.addEventListener("hidden.bs.modal", () => {
        if (!staffReturnToUserModal) return;
        staffReturnToUserModal = false;
        staffUserModal?.show();
    });

    const setStaffPhotoFile = (blob) => {
        const file = new File([blob], "staff-photo.jpg", {
            type: "image/jpeg",
        });
        const transfer = new DataTransfer();
        transfer.items.add(file);
        if (staffPhotoInput) staffPhotoInput.files = transfer.files;
        showStaffPhotoPreview(file);
    };

    staffPhotoInput?.addEventListener("change", () => {
        const file = staffPhotoInput.files?.[0];
        if (!file) return;
        staffPhotoInput.value = "";
        openStaffPhotoCrop(file);
    });

    staffPhotoInput?.addEventListener("click", (event) =>
        event.stopPropagation(),
    );
    staffPhotoDropzone?.addEventListener("click", () =>
        staffPhotoInput?.click(),
    );
    staffPhotoDropzone?.addEventListener("keydown", (event) => {
        if (event.key === "Enter" || event.key === " ") {
            event.preventDefault();
            staffPhotoInput?.click();
        }
    });
    staffPhotoDropzone?.addEventListener("dragover", (event) => {
        event.preventDefault();
        staffPhotoDropzone.classList.add("is-dragging");
    });
    staffPhotoDropzone?.addEventListener("dragleave", () =>
        staffPhotoDropzone.classList.remove("is-dragging"),
    );
    staffPhotoDropzone?.addEventListener("drop", (event) => {
        event.preventDefault();
        staffPhotoDropzone.classList.remove("is-dragging");
        openStaffPhotoCrop(event.dataTransfer?.files?.[0]);
    });
    staffPhotoDropzone?.addEventListener("paste", (event) => {
        const file = imageFileFromPasteEvent(event);
        if (!file) return;
        event.preventDefault();
        openStaffPhotoCrop(file);
    });
    document.addEventListener("paste", (event) => {
        if (!staffUserModalElement?.classList.contains("show")) return;
        if (staffPhotoCropModalElement?.classList.contains("show")) return;
        if (
            event.target?.closest?.(
                "input:not([type='file']), textarea, [contenteditable='true']",
            )
        )
            return;

        const file = imageFileFromPasteEvent(event);
        if (!file) return;
        event.preventDefault();
        openStaffPhotoCrop(file);
    });

    staffPhotoZoom?.addEventListener("input", () => {
        staffPhotoCropScale = Number(staffPhotoZoom.value);
        drawStaffPhotoCrop();
    });
    document
        .getElementById("staffPhotoZoomIn")
        ?.addEventListener("click", () => {
            if (!staffPhotoZoom) return;
            staffPhotoZoom.value = Math.min(
                3,
                Number(staffPhotoZoom.value) + 0.1,
            ).toFixed(2);
            staffPhotoZoom.dispatchEvent(new Event("input"));
        });
    document
        .getElementById("staffPhotoZoomOut")
        ?.addEventListener("click", () => {
            if (!staffPhotoZoom) return;
            staffPhotoZoom.value = Math.max(
                1,
                Number(staffPhotoZoom.value) - 0.1,
            ).toFixed(2);
            staffPhotoZoom.dispatchEvent(new Event("input"));
        });
    document
        .getElementById("staffPhotoRotateLeft")
        ?.addEventListener("click", () => {
            staffPhotoCropRotation -= 90;
            drawStaffPhotoCrop();
        });
    document
        .getElementById("staffPhotoRotateRight")
        ?.addEventListener("click", () => {
            staffPhotoCropRotation += 90;
            drawStaffPhotoCrop();
        });
    document
        .getElementById("staffPhotoReset")
        ?.addEventListener("click", resetStaffPhotoCrop);

    staffPhotoCropCanvas?.addEventListener("pointerdown", (event) => {
        staffPhotoCropDragging = true;
        staffPhotoCropStart = { x: event.clientX, y: event.clientY };
        staffPhotoCropCanvas.setPointerCapture(event.pointerId);
    });
    staffPhotoCropCanvas?.addEventListener("pointermove", (event) => {
        if (!staffPhotoCropDragging || !staffPhotoCropStart) return;
        const rect = staffPhotoCropCanvas.getBoundingClientRect();
        const scaleX = staffPhotoCropCanvas.width / rect.width;
        const scaleY = staffPhotoCropCanvas.height / rect.height;
        staffPhotoCropOffsetX +=
            (event.clientX - staffPhotoCropStart.x) * scaleX;
        staffPhotoCropOffsetY +=
            (event.clientY - staffPhotoCropStart.y) * scaleY;
        staffPhotoCropStart = { x: event.clientX, y: event.clientY };
        drawStaffPhotoCrop();
    });
    ["pointerup", "pointercancel"].forEach((type) =>
        staffPhotoCropCanvas?.addEventListener(type, () => {
            staffPhotoCropDragging = false;
            staffPhotoCropStart = null;
        }),
    );

    document
        .getElementById("staffPhotoCropUpload")
        ?.addEventListener("click", () => {
            if (!staffPhotoCropCanvas) return;
            staffPhotoCropCanvas.toBlob(
                (blob) => {
                    if (!blob) return;
                    setStaffPhotoFile(blob);
                    staffPhotoCropModal?.hide();
                },
                "image/jpeg",
                0.9,
            );
        });
};

const bootUserManagementPage = () => {
    const userModal = document.getElementById("userModal");
    initStaffPhotoViewer();

    // Keep the mobile carousel position visible between the scroll arrows.
    const mobileList = document.querySelector(".user-management-mobile-list");
    const mobilePosition = document.querySelector(
        ".user-management-mobile-scroll-position",
    );
    const syncMobilePosition = () => {
        if (!mobileList || !mobilePosition) return;
        const cards = [...mobileList.querySelectorAll(".user-management-mobile-card")];
        if (!cards.length) {
            mobilePosition.textContent = "0 of 0";
            return;
        }
        const cardWidth = cards[0].getBoundingClientRect().width;
        const gap = Number.parseFloat(getComputedStyle(mobileList).columnGap) || 0;
        const current = Math.min(
            cards.length,
            Math.max(1, Math.round(mobileList.scrollLeft / (cardWidth + gap)) + 1),
        );
        mobilePosition.textContent = `${current} of ${cards.length}`;
    };
    mobileList?.addEventListener("scroll", syncMobilePosition, { passive: true });
    window.addEventListener("resize", syncMobilePosition);
    syncMobilePosition();

    document.addEventListener(
        "click",
        (event) => {
            const newUserButton = event.target.closest("#btnNewUser");
            if (!newUserButton) return;
            event.preventDefault();
            initStaffPhotoUploader();
            window.openUserModalSafely?.();
        },
        true,
    );

    if (userModal?.dataset.openOnLoad === "1") initStaffPhotoUploader();
};

if (document.readyState === "loading")
    document.addEventListener("DOMContentLoaded", bootUserManagementPage, {
        once: true,
    });
else bootUserManagementPage();
