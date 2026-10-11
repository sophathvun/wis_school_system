export const initSkippingWorkflowTimeline = (root) => {
    root.querySelectorAll('[data-skipping-workflow-timeline]').forEach((timeline) => {
        const count = timeline.children.length;
        if (!count) return;
        const resize = () => {
            const width = timeline.clientWidth;
            timeline.classList.toggle('is-vertical', width < 600 || width / count < 105);
        };
        resize();
        if (typeof ResizeObserver !== 'undefined') {
            const observer = new ResizeObserver(resize);
            observer.observe(timeline.parentElement);
            window.addEventListener('pagehide', () => observer.disconnect(), { once: true });
        } else {
            window.addEventListener('resize', resize);
            window.addEventListener('pagehide', () => window.removeEventListener('resize', resize), { once: true });
        }
    });
};
