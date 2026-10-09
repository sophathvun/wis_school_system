const maxAttachmentBytes = 20 * 1024 * 1024;
const allowedExtensions = new Set(['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar']);
const clipboardExtensions = {
    'image/jpeg': 'jpg', 'image/png': 'png', 'image/gif': 'gif', 'image/webp': 'webp',
    'application/pdf': 'pdf', 'text/plain': 'txt',
    'application/msword': 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'docx',
    'application/vnd.ms-excel': 'xls',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'xlsx',
    'application/vnd.ms-powerpoint': 'ppt',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation': 'pptx',
    'application/zip': 'zip', 'application/x-zip-compressed': 'zip',
    'application/vnd.rar': 'rar', 'application/x-rar-compressed': 'rar',
};

export function clipboardAttachmentFiles(data) {
    if (!data) return [];
    const files = Array.from(data.items || [])
        .filter((item) => item.kind === 'file')
        .map((item) => item.getAsFile?.())
        .filter(Boolean);
    // Browsers expose the same files through items and files; use one source.
    return files.length ? files : Array.from(data.files || []);
}

export function prepareChatAttachment(file) {
    if (file.size > maxAttachmentBytes) return { error: 'Attachments must be 20 MB or smaller.' };
    const extension = String(file.name || '').split('.').pop().toLowerCase();
    if (allowedExtensions.has(extension)) return { file };
    const inferred = clipboardExtensions[String(file.type || '').toLowerCase()];
    // Clipboard screenshots may have no filename or extension.
    if (inferred && (!file.name || !file.name.includes('.'))) {
        return { file: new File([file], `${file.name || 'pasted-file'}.${inferred}`, { type: file.type, lastModified: file.lastModified }) };
    }
    return { error: 'Use a JPG, PNG, GIF, WEBP, PDF, Word, Excel, PowerPoint, TXT, ZIP, or RAR attachment.' };
}

export function bindChatAttachmentPaste(form, { enabled, selectFile, onError }) {
    const paste = (event) => {
        if (!enabled()) return;
        const files = clipboardAttachmentFiles(event.clipboardData);
        if (!files.length) return;
        event.preventDefault();
        if (files.length > 1) {
            onError('Attach one file per message. Please paste or select one file at a time.');
            return;
        }
        selectFile(files[0]);
    };
    form.addEventListener('paste', paste);
    return () => form.removeEventListener('paste', paste);
}
