// A shallow circular arch, measured in the caller's units (pixels or points).
export function titleArc(widths, available, curve = 26) {
    const total = widths.reduce((sum, width) => sum + width, 0);
    if (total <= 0) return [];
    const sweep = Math.min(90, Math.max(0, curve)) * Math.PI / 180;
    if (sweep === 0) {
        let left = (available - total) / 2;
        return widths.map((width) => {
            const position = { left, top: 0, rotation: 0, width };
            left += width;
            return position;
        });
    }
    const radius = total / sweep;
    let advance = 0;
    return widths.map((width) => {
        const angle = ((advance + width / 2) / total - .5) * sweep;
        advance += width;
        return {
            left: available / 2 + radius * Math.sin(angle) - width / 2,
            top: 2 * radius * Math.sin(angle / 2) ** 2,
            rotation: angle * 180 / Math.PI,
            width,
        };
    });
}

export function curveTitle(field, context) {
    const size = parseFloat(getComputedStyle(field).fontSize);
    field.querySelectorAll('.g12-certificate-line').forEach((line) => {
        const text = line.textContent;
        const style = getComputedStyle(line);
        context.font = `${style.fontStyle} ${style.fontWeight} ${size}px ${style.fontFamily}`;
        context.fontKerning = 'none';
        const characters = typeof Intl.Segmenter === 'function'
            ? [...new Intl.Segmenter('en', { granularity: 'grapheme' }).segment(text)].map(({ segment }) => segment)
            : Array.from(text);
        const widths = characters.map((character) => context.measureText(character).width);
        const positions = titleArc(widths, field.clientWidth, Number(field.dataset.g12Curve ?? 26));
        line.classList.add('g12-title-arc');
        line.setAttribute('role', 'img');
        line.setAttribute('aria-label', text);
        line.style.height = `${size * 1.2 + Math.max(0, ...positions.map(({ top }) => top))}px`;
        line.replaceChildren(...characters.map((character, index) => {
            const letter = document.createElement('span');
            const position = positions[index];
            letter.className = 'g12-title-letter';
            letter.setAttribute('aria-hidden', 'true');
            letter.textContent = character;
            if (position) {
                letter.style.left = `${position.left}px`;
                letter.style.top = `${position.top}px`;
                letter.style.width = `${position.width}px`;
                letter.style.transform = `rotate(${position.rotation}deg)`;
            }
            return letter;
        }));
    });
}
