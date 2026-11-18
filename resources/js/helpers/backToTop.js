import "../../css/components/back-to-top.css";

export function setupBackToTop(button) {
    if (!button) return;
    const containers = [...new Set([
        button.closest(".page-body"),
        button.closest(".page-wrapper"),
        document.scrollingElement,
    ].filter(Boolean))];
    const sync = () => { button.hidden = !containers.some(container => container.scrollTop >= 250); };
    containers.forEach(container => container.addEventListener("scroll", sync, { passive: true }));
    window.addEventListener("scroll", sync, { passive: true });
    window.addEventListener("resize", sync);
    document.addEventListener("shown.bs.tab", sync);
    button.addEventListener("click", () => {
        const behavior = window.matchMedia("(prefers-reduced-motion: reduce)").matches ? "instant" : "smooth";
        containers.forEach(container => {
            if (container.scrollTop > 0) container.scrollTo({ top: 0, behavior });
        });
    });
    sync();
}
