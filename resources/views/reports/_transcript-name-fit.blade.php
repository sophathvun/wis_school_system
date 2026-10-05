<script>
(() => {
    const names = document.querySelectorAll('.transcript-template-primary .transcript-content-student-name, .transcript-template-secondary .transcript-content-student-name, .transcript-template-secondary .transcript-content-student-name-en');
    if (!names.length) return;
    const originalFontSizes = new Map([...names].map((name) => [name, name.style.fontSize]));

    // Measure rendered glyphs rather than character counts. Keep short names at their original size.
    const fitNames = () => {
        names.forEach((name) => {
            name.style.fontSize = originalFontSizes.get(name);
            const defaultSize = parseFloat(getComputedStyle(name).fontSize);
            const width = name.getBoundingClientRect().width;
            if (!width || !name.textContent.trim()) return;

            const text = document.createRange();
            text.selectNodeContents(name);
            if (text.getBoundingClientRect().width <= width) return;

            let smaller = 0;
            let larger = defaultSize;
            for (let attempt = 0; attempt < 12; attempt++) {
                const size = (smaller + larger) / 2;
                name.style.fontSize = `${size}px`;
                if (text.getBoundingClientRect().width <= width) smaller = size;
                else larger = size;
            }
            name.style.fontSize = `${Math.floor(smaller * 100) / 100}px`;
        });
    };

    // Inline so the same fitting also runs in the standalone PDF export HTML.
    fitNames();
    if (document.fonts) document.fonts.ready.then(fitNames);
    window.addEventListener('load', fitNames);
    window.addEventListener('resize', fitNames);
    window.addEventListener('beforeprint', fitNames);
})();
</script>
