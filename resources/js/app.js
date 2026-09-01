import * as bootstrap from "bootstrap";
window.bootstrap = bootstrap;

import "@tabler/core/dist/js/tabler-theme.js";
import "./theme.js";
import "./helpers/sweet-alert2.js";
import "./tabler-toasts/alert.js";
import "./helpers/tabler-alert.js";
import "./helpers/pagination.js";
import "./helpers/helper.js";
import "./helpers/status-toggle.js";
import "./premiumForms.js";
import "./sidebar.js";
import "./navbar.js";
import "./chatWidget.js";

const body = document.body;
const currentPermissions = (() => {
    try {
        return JSON.parse(body?.dataset.userPermissions || "[]");
    } catch {
        return [];
    }
})();
window.userPermissions = currentPermissions;
window.currentUserId = body?.dataset.currentUserId || "";

const navigateWithParams = ({
    pageParam = "page",
    perPage = null,
    page = null,
    maxPage = null,
} = {}) => {
    const url = new URL(window.location.href);
    if (perPage !== null) url.searchParams.set("per_page", String(perPage));
    if (page !== null) {
        const value = maxPage !== null ? Math.min(maxPage, Math.max(1, page)) : page;
        url.searchParams.set(pageParam, String(value));
    }
    window.location = url.toString();
};

document.addEventListener("change", (event) => {
    const perPageSelect = event.target.closest("[data-pagination-per-page]");
    if (perPageSelect) {
        navigateWithParams({
            pageParam: perPageSelect.dataset.paginationPageParam || "page",
            perPage: perPageSelect.value,
            page: 1,
        });
        return;
    }

    const gotoInput = event.target.closest("[data-pagination-goto]");
    if (gotoInput) {
        navigateWithParams({
            pageParam: gotoInput.dataset.paginationPageParam || "page",
            page: parseInt(gotoInput.value, 10) || 1,
            maxPage: parseInt(gotoInput.dataset.paginationMax, 10) || null,
        });
    }
});

document.addEventListener("DOMContentLoaded", () => {
    const pageHeader = document.querySelector(".page-header");
    const card = document.querySelector(".page-body .card");
    const cardHeader = card?.querySelector(".card-header");
    const actions = pageHeader?.querySelector(".col-auto");

    if (pageHeader && cardHeader) {
        cardHeader.classList.add("d-flex", "align-items-center");
        if (actions) {
            actions.classList.add("ms-auto");
            cardHeader.appendChild(actions);
        }
        pageHeader.remove();
    }

    const normalizeActions = () =>
        document.querySelectorAll("button, a").forEach((element) => {
            if (element.dataset.actionNormalized) return;
            const text = element.textContent.trim().replace(/\s+/g, " ");
            const action = text === "Edit" ? "edit" : text === "Delete" ? "delete" : null;
            if (!action) return;
            element.dataset.actionNormalized = "true";
            element.classList.remove("btn-primary", "btn-danger");
            element.classList.add(`btn-outline-${action === "edit" ? "primary" : "danger"}`, "btn-sm");
            element.setAttribute("title", action === "edit" ? "Edit" : "Delete");
            element.setAttribute("aria-label", action === "edit" ? "Edit" : "Delete");
            element.innerHTML = `<i class="ti ti-${action === "edit" ? "edit" : "trash"}"></i>`;
        });

    const normalizeSearches = () =>
        document.querySelectorAll("input").forEach((input) => {
            if (input.dataset.searchNormalized || input.closest(".input-icon") || input.closest(".location-combobox")) return;
            const isSearch = input.id.toLowerCase().includes("search") || (input.placeholder || "").toLowerCase().includes("search");
            if (!isSearch) return;
            input.dataset.searchNormalized = "true";
            input.classList.add("form-control-sm");
            const wrapper = document.createElement("div");
            wrapper.className = "input-icon";
            wrapper.innerHTML = '<span class="input-icon-addon"><i class="ti ti-search icon"></i></span>';
            input.parentElement.insertBefore(wrapper, input);
            wrapper.appendChild(input);
        });

    normalizeActions();
    normalizeSearches();
    new MutationObserver(() => {
        normalizeActions();
        normalizeSearches();
    }).observe(document.body, { childList: true, subtree: true });
});
