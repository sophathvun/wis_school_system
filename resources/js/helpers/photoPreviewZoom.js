// Magnify the editing surface with CSS only; output pixels and crop stay fixed.
export function createPhotoPreviewZoom({ element, canvas, isBusy }) {
    const stage = element.querySelector('.student-photo-crop-stage');
    const surface = element.querySelector('.photo-editor-zoom-surface');
    const wrap = element.querySelector('.photo-editor-canvas-wrap');
    const get = id => element.querySelector(`#${id}`);
    const minus = get('photoPreviewZoomOut'), plus = get('photoPreviewZoomIn');
    const fit = get('photoPreviewFit'), pan = get('photoPreviewPan');
    let zoom = 1, panning = false, drag = null;

    const refreshControls = () => {
        minus.disabled = fit.disabled = isBusy() || zoom === 1;
        plus.disabled = isBusy() || zoom === 4;
        pan.disabled = isBusy() || zoom === 1;
        pan.setAttribute('aria-pressed', String(panning));
        pan.classList.toggle('active', panning);
        stage.classList.toggle('photo-preview-pan', panning);
        get('photoPreviewZoomValue').textContent = `${Math.round(zoom * 100)}%`;
    };
    const layout = (next = zoom, keepCenter = true) => {
        const old = canvas.getBoundingClientRect(), viewport = stage.getBoundingClientRect();
        const cx = keepCenter && old.width ? (viewport.left + stage.clientWidth / 2 - old.left) / old.width : .5;
        const cy = keepCenter && old.height ? (viewport.top + stage.clientHeight / 2 - old.top) / old.height : .5;
        zoom = Math.max(1, Math.min(4, next));
        if (zoom === 1) panning = false;
        wrap.style.width = surface.style.width = surface.style.height = stage.style.height = '';
        stage.scrollLeft = stage.scrollTop = 0;
        const base = wrap.getBoundingClientRect();
        if (zoom > 1 && base.width) {
            const padding = getComputedStyle(stage);
            const left = parseFloat(padding.paddingLeft), right = parseFloat(padding.paddingRight);
            const top = parseFloat(padding.paddingTop), bottom = parseFloat(padding.paddingBottom);
            const available = stage.clientWidth - left - right;
            stage.style.height = `${base.height + top + bottom}px`;
            wrap.style.width = `${base.width * zoom}px`;
            surface.style.width = `${Math.max(available, base.width * zoom)}px`;
            surface.style.height = `${base.height * zoom}px`;
            stage.scrollLeft = Math.max(0, (available - base.width * zoom) / 2) + cx * base.width * zoom + left - stage.clientWidth / 2;
            stage.scrollTop = cy * base.height * zoom + top - stage.clientHeight / 2;
        }
        refreshControls();
    };
    minus.addEventListener('click', () => layout(zoom - .25));
    plus.addEventListener('click', () => layout(zoom + .25));
    fit.addEventListener('click', () => layout(1, false));
    pan.addEventListener('click', () => { panning = !panning; refreshControls(); });
    // Capture Pan gestures before the canvas can move the crop or paint a mark.
    stage.addEventListener('pointerdown', event => {
        if (!panning || isBusy() || event.button > 0) return;
        event.preventDefault(); event.stopPropagation();
        drag = { id: event.pointerId, x: event.clientX, y: event.clientY, left: stage.scrollLeft, top: stage.scrollTop };
        stage.setPointerCapture(event.pointerId);
    }, true);
    stage.addEventListener('pointermove', event => {
        if (!drag || drag.id !== event.pointerId) return;
        event.preventDefault(); event.stopPropagation();
        stage.scrollLeft = drag.left + drag.x - event.clientX;
        stage.scrollTop = drag.top + drag.y - event.clientY;
    }, true);
    for (const name of ['pointerup', 'pointercancel', 'lostpointercapture']) stage.addEventListener(name, event => {
        if (!drag || drag.id !== event.pointerId) return;
        event.stopPropagation(); drag = null;
        if (stage.hasPointerCapture(event.pointerId)) stage.releasePointerCapture(event.pointerId);
    }, true);
    element.addEventListener('shown.bs.modal', () => layout(zoom, false));
    window.addEventListener('resize', () => { if (element.classList.contains('show')) layout(); });
    refreshControls();
    return { refreshControls, layout: () => layout(), reset: () => { drag = null; layout(1, false); } };
}
