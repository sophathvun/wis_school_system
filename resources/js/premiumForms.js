import { showError } from "./helpers/sweet-alert2";

const fieldControlSelector =
    "input:not([type='hidden']):not([type='file']), textarea, select, .location-combobox, .date-picker, .input-icon, .phone-input-group";

const fieldHasValue = (wrapper) => {
    const selected = wrapper.querySelector(
        ":scope > .location-combobox .location-combobox-selected",
    );
    if (
        selected &&
        selected.textContent.trim() &&
        !/^Select\b/i.test(selected.textContent.trim())
    )
        return true;
    const select = wrapper.querySelector(":scope > select");
    if (select?.value) return true;
    const input = wrapper.querySelector(
        ":scope > input:not([type='hidden']):not([type='file']), :scope > textarea, :scope > .input-icon input, :scope > .phone-input-group input, :scope > .date-picker input",
    );
    return Boolean(input?.value?.trim());
};

let premiumFormRefreshFrame = null;
const enhanceEmailFields = (root = document) => {
    root.querySelectorAll("form input[type='email']").forEach((input) => {
        if (input.closest(".summer-email-input")) return;
        if (input.dataset.emailEnhanced === "1") return;
        input.dataset.emailEnhanced = "1";
        input.inputMode = "email";
        input.autocomplete ||= "email";
        const field = input.closest(".premium-floating-field, .premium-form-field, .col-md-3, .col-md-4, .col-md-6, .mb-3") || input.parentElement;
        const existingIconWrapper = input.closest(".input-icon, .phone-input-group, .summer-email-input");
        let control = existingIconWrapper;
        if (!existingIconWrapper) {
            control = document.createElement("div");
            control.className = "premium-email-input";
            control.innerHTML = '<i class="ti ti-mail premium-email-icon"></i>';
            input.parentElement?.insertBefore(control, input);
            control.appendChild(input);
        }
        field?.classList.add("premium-email-field");
        const feedback = document.createElement("div");
        feedback.className = "premium-email-feedback";
        feedback.textContent = "Enter a valid email address, for example name@example.com.";
        if (!field?.querySelector(":scope > .premium-email-feedback")) field?.appendChild(feedback);
        const validate = (showMessage = false) => {
            const value = input.value.trim();
            const valid = !value || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
            input.setCustomValidity(valid ? "" : "Please enter a valid email address.");
            control?.classList.toggle("is-invalid", !valid);
            field?.querySelector(":scope > .premium-email-feedback")?.classList.toggle("is-visible", !valid);
            input.setAttribute("aria-invalid", valid ? "false" : "true");
            if (!valid && showMessage) {
                showError("Invalid Email", "Please enter a valid email address, for example name@example.com.");
                window.setTimeout(() => input.focus(), 0);
            }
        };
        input.addEventListener("input", () => validate(false));
        input.addEventListener("blur", () => validate(true));
    });
};
const refreshPremiumForms = (root = document) => {
    root.querySelectorAll("form .form-label").forEach((label) => {
        const wrapper = label.parentElement;
        if (
            !wrapper ||
            !wrapper.querySelector(`:scope > ${fieldControlSelector}`)
        )
            return;
        if (
            wrapper.querySelector("input[type='file']") &&
            !wrapper.querySelector(
                "input:not([type='file']):not([type='hidden']), select, textarea",
            )
        )
            return;
        wrapper.classList.add("premium-form-field");
        wrapper.classList.toggle("has-value", fieldHasValue(wrapper));
    });
    enhanceEmailFields(root);
};
const schedulePremiumFormRefresh = (root = document) => {
    if (premiumFormRefreshFrame !== null) return;
    premiumFormRefreshFrame = window.requestAnimationFrame(() => {
        premiumFormRefreshFrame = null;
        refreshPremiumForms(root);
    });
};

document.addEventListener("input", (event) => {
    if (event.target.closest("form"))
        schedulePremiumFormRefresh(event.target.closest("form"));
});
document.addEventListener("change", (event) => {
    if (event.target.closest("form"))
        schedulePremiumFormRefresh(event.target.closest("form"));
});
document.addEventListener("shown.bs.modal", (event) =>
    schedulePremiumFormRefresh(event.target),
);
document.addEventListener("DOMContentLoaded", () =>
    schedulePremiumFormRefresh(),
);

schedulePremiumFormRefresh();
