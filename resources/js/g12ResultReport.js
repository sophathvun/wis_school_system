// Measure repeated values once without forcing a table layout for every font size.
const context = document.createElement('canvas').getContext('2d');
const measurements = new Map();
const maximumSize = 11 * 96 / 72;
let pendingFrame;

function fitResultValues() {
    if (!context) return;
    const updates = [];
    document.querySelectorAll('[data-result-fit]').forEach(text => {
        const cell = text.closest('td');
        const style = getComputedStyle(cell);
        const available = cell.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight) - 1;
        if (available <= 0) return;

        const font = `${style.fontStyle} ${style.fontWeight} ${maximumSize}px ${style.fontFamily}`;
        const value = text.textContent;
        const key = `${font}\n${value}`;
        let width = measurements.get(key);
        if (width === undefined) {
            context.font = font;
            width = context.measureText(value).width;
            measurements.set(key, width);
        }
        const size = width > 0 ? Math.min(maximumSize, maximumSize * available / width) : maximumSize;
        updates.push([text, `${Math.max(1, size).toFixed(2)}px`]);
    });
    // Finish all layout reads before changing any styles.
    updates.forEach(([text, size]) => {
        if (text.style.fontSize !== size) text.style.fontSize = size;
    });
}

function scheduleFit() {
    cancelAnimationFrame(pendingFrame);
    pendingFrame = requestAnimationFrame(fitResultValues);
}

document.fonts.ready.then(fitResultValues);
window.addEventListener('resize', scheduleFit);
window.addEventListener('beforeprint', fitResultValues);
window.addEventListener('afterprint', scheduleFit);
