// Submit one search after typing pauses; Enter and clearing run immediately.
export function setupEnrollmentSearch({ input, run, cancelPending, onError, delay = 300 }) {
    let timer;
    const submit = () => {
        clearTimeout(timer);
        Promise.resolve().then(run).catch(onError);
    };
    input.addEventListener('input', () => {
        clearTimeout(timer);
        cancelPending();
        if (!input.value.trim()) submit();
        else timer = setTimeout(submit, delay);
    });
    input.addEventListener('keydown', event => {
        if (event.key !== 'Enter') return;
        event.preventDefault(); cancelPending(); submit();
    });
}
