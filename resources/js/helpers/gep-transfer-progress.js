export async function readGepTransferProgress(response, onMessage) {
    if (!response.ok || !response.headers.get('Content-Type')?.includes('application/x-ndjson')) {
        let data;try { data = await response.json(); } catch {}
        throw new Error(Object.values(data?.errors || {}).flat().join(' ') || data?.message || 'Unable to start the import. Refresh the page and check the result before trying again.');
    }
    const reader = response.body.getReader(), decoder = new TextDecoder();
    let buffer = '', complete = null;
    const process = line => {
        if (!line.trim()) return;
        const message = JSON.parse(line);
        if (message.event === 'error') throw new Error(message.message);
        onMessage(message);
        if (message.event === 'complete') complete = message;
    };
    try {
        while (true) {
            const {value, done} = await reader.read();
            buffer += decoder.decode(value, {stream: !done});
            let end;
            while ((end = buffer.indexOf('\n')) !== -1) { process(buffer.slice(0, end)); buffer = buffer.slice(end + 1); }
            if (done) break;
        }
        process(buffer);
        if (!complete) throw new Error('The connection ended before confirmation. Refresh and check the import result before retrying.');
        return complete;
    } finally { reader.releaseLock(); }
}
