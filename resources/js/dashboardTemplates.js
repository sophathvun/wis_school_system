document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("[data-template-carousel]").forEach((carousel) => {
        const list = carousel.querySelector(".dashboard-template-list");
        const cards = [...carousel.querySelectorAll(".dashboard-template-card")];
        const position = carousel.querySelector("[data-template-position]");
        const start = Number(carousel.dataset.templateStart || 1);
        const total = Number(carousel.dataset.templateTotal || cards.length);
        if (!list || !cards.length) return;

        const updatePosition = () => {
            const center = list.scrollLeft + list.clientWidth / 2;
            let nearest = 0;
            cards.forEach((card, index) => {
                if (Math.abs(card.offsetLeft + card.offsetWidth / 2 - center) < Math.abs(cards[nearest].offsetLeft + cards[nearest].offsetWidth / 2 - center)) nearest = index;
            });
            if (position) position.textContent = `${start + nearest} of ${total}`;
        };
        carousel.querySelector("[data-template-prev]")?.addEventListener("click", () => list.scrollBy({ left: -list.clientWidth * .88, behavior: "smooth" }));
        carousel.querySelector("[data-template-next]")?.addEventListener("click", () => list.scrollBy({ left: list.clientWidth * .88, behavior: "smooth" }));
        list.addEventListener("scroll", updatePosition, { passive: true });
        updatePosition();
    });

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
