(function () {
    const ready = (callback) => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
        } else {
            callback();
        }
    };

    ready(() => {
        const form = document.querySelector('[data-feedback-form]');
        if (!form) return;

        const editor = form.querySelector('[data-feedback-editor]');
        const messageField = form.querySelector('[data-feedback-message]');
        const attachmentInput = form.querySelector('[data-feedback-attachment]');
        const imageButton = form.querySelector('[data-feedback-image-button]');
        const preview = form.querySelector('[data-feedback-attachment-preview]');
        const previewImage = form.querySelector('[data-feedback-attachment-image]');
        const previewName = form.querySelector('[data-feedback-attachment-name]');
        const removeImage = form.querySelector('[data-feedback-attachment-remove]');
        const fontFamily = form.querySelector('[data-feedback-font-family]');
        const fontSize = form.querySelector('[data-feedback-font-size]');
        const colorPicker = form.querySelector('[data-feedback-color-picker]');
        const textColor = form.querySelector('[data-feedback-color]');
        const textColorOpen = form.querySelector('[data-feedback-color-open]');
        const textColorPreview = form.querySelector('[data-feedback-color-preview]');
        const textNativeOpen = form.querySelector('[data-feedback-native-open]');
        const colorPalette = form.querySelector('[data-feedback-color-palette]');
        const colorSwatches = form.querySelectorAll('[data-feedback-color-swatch]');
        let previewUrl = null;
        let currentFontFamily = '';
        let currentFontSize = '14px';
        let currentColor = '';

        if (!editor || !messageField || !attachmentInput) return;

        if (messageField.value.trim()) {
            editor.innerHTML = messageField.value;
        }

        const focusEditor = () => {
            editor.focus({ preventScroll: true });
        };

        const cleanEditorClone = () => {
            const clone = editor.cloneNode(true);
            clone.querySelectorAll('[data-feedback-local-image]').forEach((node) => node.remove());
            clone.querySelectorAll('font[size]').forEach((font) => {
                const span = document.createElement('span');
                span.style.fontSize = '14px';
                span.innerHTML = font.innerHTML;
                font.replaceWith(span);
            });
            clone.querySelectorAll('span').forEach((span) => {
                if (!span.getAttribute('style')) span.removeAttribute('style');
            });
            return clone.innerHTML.trim();
        };

        const syncMessage = () => {
            messageField.value = cleanEditorClone();
        };

        const selectionInsideEditor = () => {
            const selection = window.getSelection();
            if (!selection || selection.rangeCount === 0) return null;
            const range = selection.getRangeAt(0);
            if (!editor.contains(range.commonAncestorContainer)) return null;
            return { selection, range };
        };

        const applyInlineStyle = (styles) => {
            focusEditor();
            const selected = selectionInsideEditor();

            if (!selected || selected.range.collapsed) {
                if (Object.prototype.hasOwnProperty.call(styles, 'fontFamily')) {
                    currentFontFamily = styles.fontFamily || '';
                    editor.style.fontFamily = currentFontFamily || '';
                }
                if (styles.fontSize) {
                    currentFontSize = styles.fontSize;
                    editor.style.fontSize = styles.fontSize;
                }
                if (Object.prototype.hasOwnProperty.call(styles, 'color')) {
                    currentColor = styles.color || '';
                    editor.style.color = currentColor || '';
                }
                syncMessage();
                return;
            }

            const span = document.createElement('span');
            if (Object.prototype.hasOwnProperty.call(styles, 'fontFamily') && styles.fontFamily) span.style.fontFamily = styles.fontFamily;
            if (styles.fontSize) span.style.fontSize = styles.fontSize;
            if (Object.prototype.hasOwnProperty.call(styles, 'color') && styles.color) span.style.color = styles.color;
            span.appendChild(selected.range.extractContents());
            selected.range.insertNode(span);
            selected.selection.removeAllRanges();
            const range = document.createRange();
            range.selectNodeContents(span);
            selected.selection.addRange(range);
            syncMessage();
        };

        const setPreview = (file) => {
            if (!file || !file.type.startsWith('image/')) return;

            if (previewUrl) URL.revokeObjectURL(previewUrl);
            previewUrl = URL.createObjectURL(file);
            previewImage.src = previewUrl;
            previewName.textContent = file.name || 'Screenshot image';
            preview.classList.remove('d-none');
        };

        const setAttachmentFiles = (files) => {
            const file = Array.from(files || []).find((item) => item.type?.startsWith('image/'));
            if (!file) return false;

            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            attachmentInput.files = dataTransfer.files;
            setPreview(file);
            return true;
        };

        const clearAttachment = () => {
            attachmentInput.value = '';
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            previewUrl = null;
            previewImage.removeAttribute('src');
            previewName.textContent = '';
            preview.classList.add('d-none');
        };

        form.querySelectorAll('[data-feedback-command]').forEach((button) => {
            button.addEventListener('click', () => {
                focusEditor();
                document.execCommand(button.dataset.feedbackCommand, false, null);
                syncMessage();
            });
        });

        fontFamily?.addEventListener('change', () => {
            applyInlineStyle({ fontFamily: fontFamily.value || '' });
        });

        fontSize?.addEventListener('change', () => {
            applyInlineStyle({ fontSize: fontSize.value || currentFontSize });
        });

        const setTextColor = (color) => {
            if (!color) return;
            if (textColor) textColor.value = color;
            textColorPreview?.style.setProperty('--feedback-selected-color', color);
            applyInlineStyle({ color });
        };

        const closeColorPalette = () => {
            colorPalette?.classList.remove('is-open');
            document.body.classList.remove('feedback-color-open');
            textColorOpen?.setAttribute('aria-expanded', 'false');
        };

        textColorOpen?.addEventListener('click', () => {
            const isOpen = colorPalette?.classList.toggle('is-open');
            document.body.classList.toggle('feedback-color-open', Boolean(isOpen));
            textColorOpen.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        textNativeOpen?.addEventListener('click', () => {
            textColor?.click();
        });

        textColor?.addEventListener('change', () => {
            setTextColor(textColor.value || '#2563eb');
        });

        colorSwatches.forEach((swatch) => {
            swatch.addEventListener('click', () => {
                const color = swatch.dataset.feedbackColorSwatch || '#2563eb';
                setTextColor(color);
                closeColorPalette();
            });
        });

        document.addEventListener('click', (event) => {
            if (!colorPicker || colorPicker.contains(event.target)) return;
            closeColorPalette();
        });

        editor.addEventListener('beforeinput', () => {
            editor.style.fontFamily = currentFontFamily || '';
            if (currentFontSize) editor.style.fontSize = currentFontSize;
            editor.style.color = currentColor || '';
        });

        imageButton?.addEventListener('click', () => attachmentInput.click());

        attachmentInput.addEventListener('change', () => {
            if (attachmentInput.files?.[0]) {
                setPreview(attachmentInput.files[0]);
            }
        });

        removeImage?.addEventListener('click', clearAttachment);

        editor.addEventListener('input', syncMessage);
        editor.addEventListener('blur', syncMessage);

        editor.addEventListener('paste', (event) => {
            const files = event.clipboardData?.files;
            if (files?.length && setAttachmentFiles(files)) {
                event.preventDefault();
            }
        });

        form.addEventListener('submit', (event) => {
            syncMessage();
            const plainText = editor.textContent.replace(/\u00a0/g, ' ').trim();
            const hasAttachment = attachmentInput.files && attachmentInput.files.length > 0;

            if (!plainText && !hasAttachment) {
                event.preventDefault();
                editor.focus();
                editor.classList.add('is-invalid');
                setTimeout(() => editor.classList.remove('is-invalid'), 1800);
            }
        });

        window.addEventListener('beforeunload', () => {
            if (previewUrl) URL.revokeObjectURL(previewUrl);
        });
    });
})();
