const printButton = document.querySelector("[data-placement-test-print]");

printButton?.addEventListener("click", () => {
    window.print();
});
