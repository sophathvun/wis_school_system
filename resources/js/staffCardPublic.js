const isPrintPage = new URLSearchParams(window.location.search).get("print") === "1";
const normalCardUrl = document.body?.dataset.staffCardPublicUrl || window.location.href;

if (isPrintPage) {
    window.addEventListener("load", () => setTimeout(() => window.print(), 300), { once: true });
    window.addEventListener(
        "afterprint",
        () => {
            setTimeout(() => {
                window.close();
                setTimeout(() => {
                    if (!window.closed) window.location.replace(normalCardUrl);
                }, 150);
            }, 50);
        },
        { once: true },
    );
}

document.querySelectorAll("[data-staff-card-print]").forEach((button) => {
    button.addEventListener("click", () => window.print());
});

document.getElementById("copyStaffCardLink")?.addEventListener("click", async (event) => {
    const url = event.currentTarget.dataset.url;
    try {
        await navigator.clipboard.writeText(url);
        event.currentTarget.textContent = "Copied";
    } catch {
        window.prompt("Copy this link", url);
    }
});
