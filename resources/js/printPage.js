const shouldPrint = document.body?.dataset.printMode === "1";
if (shouldPrint) {
    window.addEventListener(
        "load",
        () => {
            window.setTimeout(() => window.print(), 150);
        },
        { once: true },
    );
    window.addEventListener("afterprint", () => window.close(), { once: true });
}
