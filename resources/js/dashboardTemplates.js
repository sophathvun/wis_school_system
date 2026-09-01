document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => {
        bootstrap.Tooltip.getOrCreateInstance(element);
    });

    const modalElement = document.getElementById("dashboardTemplateModal");
    const modal = modalElement ? bootstrap.Modal.getOrCreateInstance(modalElement) : null;

    document.getElementById("btnNewDashboardTemplate")?.addEventListener("click", () => {
        history.replaceState(null, "", `${window.location.pathname}#new`);
        setTimeout(() => modal?.show(), 30);
    });

    if (window.location.hash === "#new" || modalElement?.dataset.openOnLoad === "1") {
        modal?.show();
    }

    modalElement?.addEventListener("hidden.bs.modal", () => {
        if (window.location.search.includes("edit=") || window.location.hash === "#new") {
            window.location.href = window.location.pathname;
        }
    });

    document.querySelectorAll("[data-confirm-message]").forEach((form) => {
        form.addEventListener("submit", (event) => {
            if (form.dataset.confirmed === "true") return;
            if (!window.confirm(form.dataset.confirmMessage || "Are you sure?")) {
                event.preventDefault();
                return;
            }
            form.dataset.confirmed = "true";
        });
    });
});
