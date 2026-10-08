// Canvas PNGs default to 96 dpi. Set the physical resolution for an A4 image.
export async function withPngResolution(blob, dpi) {
    const bytes = new Uint8Array(await blob.arrayBuffer());
    const view = new DataView(bytes.buffer);
    const chunk = new Uint8Array(21);
    const chunkView = new DataView(chunk.buffer);
    chunkView.setUint32(0, 9);
    chunk.set([112, 72, 89, 115], 4); // pHYs
    const pixelsPerMetre = Math.round(dpi / 0.0254);
    chunkView.setUint32(8, pixelsPerMetre);
    chunkView.setUint32(12, pixelsPerMetre);
    chunk[16] = 1; // metres
    let crc = 0xffffffff;
    for (const byte of chunk.subarray(4, 17)) {
        crc ^= byte;
        for (let bit = 0; bit < 8; bit++) crc = (crc >>> 1) ^ ((crc & 1) ? 0xedb88320 : 0);
    }
    chunkView.setUint32(17, (crc ^ 0xffffffff) >>> 0);
    const parts = [bytes.subarray(0, 8)];
    for (let offset = 8; offset < bytes.length;) {
        const end = offset + view.getUint32(offset) + 12;
        const type = String.fromCharCode(...bytes.subarray(offset + 4, offset + 8));
        if (type !== 'pHYs') parts.push(bytes.subarray(offset, end));
        if (type === 'IHDR') parts.push(chunk);
        offset = end;
    }
    return new Blob(parts, { type: 'image/png' });
}
