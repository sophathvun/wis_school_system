document.addEventListener("DOMContentLoaded", () => {
    const profileLinks = document.querySelectorAll("[data-profile-panel]");
    const profilePanels = document.querySelectorAll("[data-profile-panel-content]");
    const activateProfilePanel = (panelName) => {
        const selectedPanel = [...profilePanels].some(
            (panel) => panel.dataset.profilePanelContent === panelName,
        )
            ? panelName
            : "profile-view";
        profileLinks.forEach((link) => {
            const isActive = link.dataset.profilePanel === selectedPanel;
            link.classList.toggle("is-active", isActive);
            link.setAttribute("aria-selected", isActive ? "true" : "false");
            link.tabIndex = isActive ? 0 : -1;
        });
        profilePanels.forEach((panel) => {
            panel.hidden = panel.dataset.profilePanelContent !== selectedPanel;
        });
    };
    profileLinks.forEach((link) => {
        link.addEventListener("click", () => {
            const panelName = link.dataset.profilePanel;
            activateProfilePanel(panelName);
            window.history.replaceState(null, "", `#${panelName}`);
        });
    });
    activateProfilePanel(window.location.hash.replace("#", ""));

    const regenerateForm = document.getElementById("regenerateNameCardForm");
    regenerateForm?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const confirmDialog = window.schoolShowConfirm
            ? await window.schoolShowConfirm(
                  "Generate a new QR code?",
                  "The current QR code and public link will stop working.",
                  "Generate QR",
                  "Cancel",
              )
            : {
                  isConfirmed: window.confirm(
                      "Generate a new QR code? The current QR code and public link will stop working.",
                  ),
              };
        if (confirmDialog.isConfirmed) regenerateForm.submit();
    });

    document.getElementById("copyPublicCardUrl")?.addEventListener("click", async () => {
        const input = document.getElementById("publicCardUrl");
        if (!input) return;
        input.select();
        input.setSelectionRange(0, input.value.length);
        try {
            await navigator.clipboard.writeText(input.value);
        } catch {
            document.execCommand("copy");
        }
    });

    const printButton = document.getElementById("profileNameCardPrintButton");
    printButton?.addEventListener("click", (event) => {
        event.preventDefault();
        const url = printButton.dataset.printUrl;
        if (!url) return;
        const printWindow = window.open(url, "staff-card-print", "popup,width=900,height=900");
        if (printWindow) printWindow.focus();
    });

    document.querySelector('form[action$="/profile"]')?.classList.add("profile-account-form");

    const form = document.querySelector('form[action$="/profile"]');
    if (!form || form.dataset.passwordUiReady) return;
    form.dataset.passwordUiReady = "1";
    const fieldsRow = form.querySelector(".row.g-3");
    if (fieldsRow && !fieldsRow.dataset.profileColumnsReady) {
        fieldsRow.dataset.profileColumnsReady = "1";
        const left = document.createElement("div");
        const right = document.createElement("div");
        left.className = "col-md-6 profile-fields-column profile-fields-left";
        right.className = "col-md-6 profile-fields-column";
        const moveField = (selector, target) => {
            const field = fieldsRow.querySelector(selector)?.closest(".col-md-6, .col-12");
            if (!field) return;
            target.appendChild(field);
        };
        moveField('input[name="name"]', left);
        moveField('input[name="username"]', left);
        moveField('input[name="email"]', left);
        moveField('input[name="password"]', right);
        moveField('input[name="password_confirmation"]', right);
        fieldsRow.append(left, right);
    }

    const password = form.querySelector('input[name="password"]');
    const confirmation = form.querySelector('input[name="password_confirmation"]');
    const addToggle = (input) => {
        if (!input || input.parentElement.classList.contains("premium-password-field")) return;
        const wrapper = document.createElement("div");
        wrapper.className = "premium-password-field";
        input.parentElement.insertBefore(wrapper, input);
        wrapper.appendChild(input);
        const button = document.createElement("button");
        button.type = "button";
        button.className = "premium-password-toggle";
        button.setAttribute("aria-label", "Show password");
        button.innerHTML = '<i class="ti ti-eye"></i>';
        button.addEventListener("click", () => {
            const visible = input.type === "text";
            input.type = visible ? "password" : "text";
            button.setAttribute("aria-label", visible ? "Show password" : "Hide password");
            button.innerHTML = `<i class="ti ${visible ? "ti-eye" : "ti-eye-off"}"></i>`;
        });
        wrapper.appendChild(button);
    };
    addToggle(password);
    addToggle(confirmation);

    if (password) {
        const strength = document.createElement("div");
        strength.className = "profile-password-strength";
        strength.innerHTML =
            '<div class="profile-password-strength-header"><span>Password Strength</span><span class="profile-password-strength-value">Weak</span></div><div class="profile-password-strength-bar"><span class="profile-password-strength-fill"></span></div><div class="profile-password-rules"><span class="profile-password-rule" data-rule="length">8 Chars</span><span class="profile-password-rule" data-rule="upper">A-Z</span><span class="profile-password-rule" data-rule="lower">a-z</span><span class="profile-password-rule" data-rule="number">123</span><span class="profile-password-rule" data-rule="special">@#$</span></div>';
        password.closest(".premium-password-field")?.after(strength);
        const note = password.closest(".col-md-6")?.querySelector("small.text-secondary");
        const rules = strength.querySelector(".profile-password-rules");
        if (note && rules) {
            const meta = document.createElement("div");
            meta.className = "profile-password-meta";
            note.classList.add("profile-password-note");
            meta.append(note, rules);
            strength.appendChild(meta);
        }
        const updateStrength = () => {
            const value = password.value;
            const checks = {
                length: value.length >= 8,
                upper: /[A-Z]/.test(value),
                lower: /[a-z]/.test(value),
                number: /\d/.test(value),
                special: /[^A-Za-z0-9]/.test(value),
            };
            Object.entries(checks).forEach(([rule, valid]) =>
                strength.querySelector(`[data-rule="${rule}"]`)?.classList.toggle("is-valid", valid),
            );
            const score = Object.values(checks).filter(Boolean).length;
            const label = strength.querySelector(".profile-password-strength-value");
            const fill = strength.querySelector(".profile-password-strength-fill");
            const level = score >= 4 ? "strong" : score >= 2 ? "medium" : "";
            label.textContent = score >= 4 ? "Strong" : score >= 2 ? "Medium" : "Weak";
            label.className = `profile-password-strength-value ${level}`;
            fill.style.width = `${score * 20}%`;
            fill.className = `profile-password-strength-fill ${level}`;
        };
        password.addEventListener("input", updateStrength);
        updateStrength();
    }

    document.querySelectorAll("[data-profile-auto-submit]").forEach((control) => {
        control.addEventListener("change", () => control.form?.submit());
    });
});
