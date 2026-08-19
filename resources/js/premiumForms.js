const fieldControlSelector = "input:not([type='hidden']):not([type='file']), textarea, select, .location-combobox, .date-picker, .input-icon, .phone-input-group";

const fieldHasValue = (wrapper) => {
    const selected = wrapper.querySelector(":scope > .location-combobox .location-combobox-selected");
    if (selected && selected.textContent.trim() && !/^Select\b/i.test(selected.textContent.trim())) return true;
    const select = wrapper.querySelector(":scope > select");
    if (select?.value) return true;
    const input = wrapper.querySelector(":scope > input:not([type='hidden']):not([type='file']), :scope > textarea, :scope > .input-icon input, :scope > .phone-input-group input, :scope > .date-picker input");
    return Boolean(input?.value?.trim());
};

let premiumFormRefreshFrame = null;
const refreshPremiumForms = (root = document) => {
    root.querySelectorAll("form .form-label").forEach((label) => {
        const wrapper = label.parentElement;
        if (!wrapper || !wrapper.querySelector(`:scope > ${fieldControlSelector}`)) return;
        if (wrapper.querySelector("input[type='file']") && !wrapper.querySelector("input:not([type='file']):not([type='hidden']), select, textarea")) return;
        wrapper.classList.add("premium-form-field");
        wrapper.classList.toggle("has-value", fieldHasValue(wrapper));
    });
};
const schedulePremiumFormRefresh = (root = document) => {
    if (premiumFormRefreshFrame !== null) return;
    premiumFormRefreshFrame = window.requestAnimationFrame(() => {
        premiumFormRefreshFrame = null;
        refreshPremiumForms(root);
    });
};

document.addEventListener("input", (event) => {
    if (event.target.closest("form")) schedulePremiumFormRefresh(event.target.closest("form"));
});
document.addEventListener("change", (event) => {
    if (event.target.closest("form")) schedulePremiumFormRefresh(event.target.closest("form"));
});
document.addEventListener("shown.bs.modal", (event) => schedulePremiumFormRefresh(event.target));
document.addEventListener("DOMContentLoaded", () => schedulePremiumFormRefresh());

schedulePremiumFormRefresh();
