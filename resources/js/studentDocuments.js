document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-student-documents-page]');
    if (!page || page.dataset.enhanced === '1') return;
    page.dataset.enhanced = '1';

    const $ = id => document.getElementById(id);
    const state = {};
    const optionsUrl = page.dataset.optionsUrl;
    const saveUrl = page.dataset.saveUrl;
    const csrf = page.dataset.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '';
    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[c]));
    const studentLabel = item => `${item.student_id || item.student_no || ''} - ${item.full_name_en || item.full_name_kh || ''}`;

    const studentSelect = $('student-document-student');
    studentSelect.classList.add('d-none');
    studentSelect.insertAdjacentHTML('afterend',
        '<div id="student-document-combobox" class="location-combobox"><button type="button" class="location-combobox-toggle"><span class="location-combobox-selected">Student</span><i class="ti ti-chevron-down"></i></button><div class="location-combobox-menu d-none"><input type="search" class="form-control location-combobox-search" placeholder="Search Student ID or Full Name"><div class="location-combobox-results"></div></div></div>');

    const studentCombo = $('student-document-combobox');
    const studentMenu = studentCombo.querySelector('.location-combobox-menu');
    const studentSearch = studentCombo.querySelector('.location-combobox-search');
    const studentResults = studentCombo.querySelector('.location-combobox-results');
    const studentSelected = studentCombo.querySelector('.location-combobox-selected');

    const renderStudentResults = () => {
        const term = studentSearch.value.toLowerCase().trim();
        const options = Array.from(studentSelect.options).slice(1).filter(option => !term || option.textContent.toLowerCase().includes(term));
        studentResults.innerHTML = options.length ? options.map(option =>
            `<button type="button" class="location-combobox-option" data-student-value="${option.value}">${esc(option.textContent)}</button>`
        ).join('') : '<div class="text-secondary px-2 py-3">No students found</div>';
    };

    const syncStudentSelection = () => {
        studentSelected.textContent = studentSelect.value ? studentSelect.selectedOptions[0]?.textContent || 'Student' : 'Student';
    };

    studentCombo.querySelector('.location-combobox-toggle').onclick = () => {
        document.querySelectorAll('.location-combobox-menu').forEach(menu => {
            if (menu !== studentMenu) menu.classList.add('d-none');
        });
        studentMenu.classList.toggle('d-none');
        if (!studentMenu.classList.contains('d-none')) {
            studentSearch.value = '';
            renderStudentResults();
            studentSearch.focus();
        }
    };
    studentSearch.oninput = renderStudentResults;
    studentResults.onclick = event => {
        const option = event.target.closest('[data-student-value]');
        if (!option) return;
        studentSelect.value = option.dataset.studentValue;
        studentSelect.dispatchEvent(new Event('change', { bubbles: true }));
        studentMenu.classList.add('d-none');
    };

    const documentTypeSelect = $('document-type-id');
    documentTypeSelect.classList.add('d-none');
    documentTypeSelect.insertAdjacentHTML('afterend',
        '<div id="student-document-type-combobox" class="location-combobox"><button type="button" class="location-combobox-toggle"><span class="location-combobox-selected">Document Type</span><i class="ti ti-chevron-down"></i></button><div class="location-combobox-menu d-none"><input type="search" class="form-control location-combobox-search" placeholder="Search Document Type"><div class="location-combobox-results"></div></div></div>');

    const documentTypeCombo = $('student-document-type-combobox');
    const documentTypeMenu = documentTypeCombo.querySelector('.location-combobox-menu');
    const documentTypeSearch = documentTypeCombo.querySelector('.location-combobox-search');
    const documentTypeResults = documentTypeCombo.querySelector('.location-combobox-results');

    const syncDocumentType = () => {
        documentTypeCombo.querySelector('.location-combobox-selected').textContent = documentTypeSelect.value ? documentTypeSelect.selectedOptions[0].textContent : 'Document Type';
    };
    const renderDocumentTypes = () => {
        const term = documentTypeSearch.value.toLowerCase().trim();
        const options = Array.from(documentTypeSelect.options).slice(1).filter(option => !term || option.textContent.toLowerCase().includes(term));
        documentTypeResults.innerHTML = options.length ? options.map(option =>
            `<button type="button" class="location-combobox-option" data-document-type-value="${option.value}">${esc(option.textContent)}</button>`
        ).join('') : '<div class="text-secondary px-2 py-3">No document types found</div>';
    };

    documentTypeCombo.querySelector('.location-combobox-toggle').onclick = () => {
        document.querySelectorAll('.location-combobox-menu').forEach(menu => {
            if (menu !== documentTypeMenu) menu.classList.add('d-none');
        });
        documentTypeMenu.classList.toggle('d-none');
        if (!documentTypeMenu.classList.contains('d-none')) {
            documentTypeSearch.value = '';
            renderDocumentTypes();
            documentTypeSearch.focus();
        }
    };
    documentTypeSearch.oninput = renderDocumentTypes;
    documentTypeResults.onclick = event => {
        const option = event.target.closest('[data-document-type-value]');
        if (!option) return;
        documentTypeSelect.value = option.dataset.documentTypeValue;
        documentTypeSelect.dispatchEvent(new Event('change', { bubbles: true }));
        documentTypeMenu.classList.add('d-none');
    };
    documentTypeSelect.addEventListener('change', syncDocumentType);

    const documentFile = $('document-file');
    documentFile.classList.add('d-none');
    documentFile.insertAdjacentHTML('afterend',
        '<div id="student-document-dropzone" class="premium-document-dropzone" tabindex="0"><span class="document-upload-icon"><i class="ti ti-cloud-upload"></i></span><div class="fw-bold fs-3">Drop Files Here</div><div class="mt-1">or <span class="document-browse-link">Browse File</span></div><div class="document-upload-hint mt-3">Supports PDF, JPG, PNG, DOC, DOCX · Maximum 20 MB per file</div><div class="document-upload-hint mt-1">You can also copy and paste a file here</div><div id="student-document-file-list" class="premium-document-file-list"></div></div>');

    const documentDropzone = $('student-document-dropzone');
    const documentFileList = $('student-document-file-list');

    document.querySelectorAll('#studentDocumentForm .col-md-6, #studentDocumentForm .col-12').forEach(wrapper => {
        if (!wrapper.querySelector('#student-document-dropzone')) wrapper.classList.add('premium-floating-field');
        wrapper.querySelectorAll('input.form-control, textarea.form-control').forEach(control => {
            if (!control.placeholder) control.placeholder = ' ';
        });
    });

    const fileSize = bytes => bytes < 1024 * 1024 ? `${Math.ceil(bytes / 1024)} KB` : `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
    const renderDocumentFile = () => {
        const file = documentFile.files[0];
        documentFileList.innerHTML = file ? `<div class="premium-document-file-item"><span class="document-file-icon"><i class="ti ti-file-description"></i></span><div class="min-w-0"><div class="document-file-name">${esc(file.name)}</div><div class="document-file-meta">${fileSize(file.size)} · Ready to upload</div></div><button type="button" class="document-file-remove" id="remove-student-document-file" aria-label="Remove file"><i class="ti ti-x"></i></button></div>` : '';
    };
    const assignDocumentFile = files => {
        if (!files?.length) return;
        const transfer = new DataTransfer();
        transfer.items.add(files[0]);
        documentFile.files = transfer.files;
        renderDocumentFile();
    };
    const clipboardFiles = clipboard => {
        const direct = Array.from(clipboard?.files || []);
        if (direct.length) return direct;
        return Array.from(clipboard?.items || []).filter(item => item.kind === 'file').map(item => item.getAsFile()).filter(Boolean);
    };
    documentDropzone.onclick = event => {
        documentDropzone.focus();
        if (event.target.closest('.document-browse-link')) documentFile.click();
    };
    documentDropzone.onkeydown = event => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            documentFile.click();
        }
    };
    documentFile.onchange = renderDocumentFile;
    documentDropzone.ondragover = event => {
        event.preventDefault();
        documentDropzone.classList.add('is-dragging');
    };
    documentDropzone.ondragleave = () => documentDropzone.classList.remove('is-dragging');
    documentDropzone.ondrop = event => {
        event.preventDefault();
        documentDropzone.classList.remove('is-dragging');
        assignDocumentFile(event.dataTransfer?.files);
    };
    documentDropzone.addEventListener('paste', event => {
        const files = clipboardFiles(event.clipboardData);
        if (!files.length) return;
        event.preventDefault();
        assignDocumentFile(files);
    });
    document.addEventListener('paste', event => {
        if (!document.querySelector('#studentDocumentModal.show')) return;
        if (event.target?.closest?.('input:not([type="file"]), textarea, select')) return;
        const files = clipboardFiles(event.clipboardData);
        if (!files.length) return;
        event.preventDefault();
        assignDocumentFile(files);
    });
    documentFileList.onclick = event => {
        if (event.target.closest('#remove-student-document-file')) {
            documentFile.value = '';
            renderDocumentFile();
        }
    };

    const loadOptions = async () => {
        Object.assign(state, await (await fetch(optionsUrl, { headers: { Accept: 'application/json' } })).json());
        studentSelect.innerHTML = '<option value=""></option>' + (state.students || []).map(item => `<option value="${item.id}">${esc(studentLabel(item))}</option>`).join('');
        documentTypeSelect.innerHTML = '<option value=""></option>' + (state.types || []).map(item => `<option value="${item.id}">${esc(item.name_kh || '')}${item.name_kh ? ' / ' : ''}${esc(item.name_en)}</option>`).join('');
        syncStudentSelection();
        syncDocumentType();
        renderStudentResults();
        renderDocumentTypes();
    };

    const loadDocuments = async () => {
        const id = $('student-document-student').value;
        if (!id) {
            $('student-documents-table').innerHTML = '<tr><td colspan="5" class="text-center">Select a student.</td></tr>';
            return;
        }
        const documents = await (await fetch('/student-documents/fetch/' + id)).json();
        $('student-documents-table').innerHTML = documents.length ? documents.map(doc =>
            `<tr><td>${esc(doc.type?.name_en || doc.document_type || '-')}<div class="small school-profile-khmer">${esc(doc.type?.name_kh || '')}</div></td><td>${esc(doc.title || '-')}</td><td>${esc(doc.document_number || '-')}</td><td>${doc.file_path ? `<a href="/student-documents/${doc.id}/download">${esc(doc.original_filename || 'Download')}</a>` : '-'}</td><td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" data-delete="${doc.id}" title="Delete Document" aria-label="Delete Document"><i class="ti ti-trash"></i></button></td></tr>`
        ).join('') : '<tr><td colspan="5" class="text-center">No documents found.</td></tr>';
        $('student-documents-table').querySelectorAll('[data-delete]').forEach(button => button.onclick = async () => {
            if (!confirm('Delete this document?')) return;
            await fetch('/student-documents/' + button.dataset.delete, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf
                }
            });
            loadDocuments();
        });
    };

    document.addEventListener('click', event => {
        if (!event.target.closest('#student-document-combobox')) studentMenu.classList.add('d-none');
        if (!event.target.closest('#student-document-type-combobox')) documentTypeMenu.classList.add('d-none');
    });
    $('student-document-student').onchange = loadDocuments;
    $('newStudentDocument').onclick = () => {
        if (!$('student-document-student').value) {
            if (window.Swal) window.Swal.fire({
                title: 'Student Required',
                text: 'Please select a student before uploading a document.',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            else window.alert('Please select a student first.');
            return;
        }
        $('studentDocumentForm').reset();
        syncDocumentType();
        documentFile.value = '';
        renderDocumentFile();
        $('document-student-id').value = $('student-document-student').value;
        bootstrap.Modal.getOrCreateInstance($('studentDocumentModal')).show();
        setTimeout(() => documentDropzone.focus(), 250);
    };
    $('studentDocumentForm').onsubmit = async event => {
        event.preventDefault();
        const errorBox = $('student-document-error');
        errorBox.classList.add('d-none');
        const file = $('document-file').files[0];
        if (!file) {
            errorBox.textContent = 'Please choose a document file first.';
            errorBox.classList.remove('d-none');
            return;
        }
        const form = new FormData();
        form.append('student_id', $('document-student-id').value);
        form.append('document_type_id', $('document-type-id').value);
        form.append('title', $('document-title').value);
        form.append('document_number', $('document-number').value);
        form.append('description', $('document-description').value);
        form.append('file', file);
        const button = $('studentDocumentForm').querySelector('button[type="submit"]');
        button.disabled = true;
        try {
            const response = await fetch(saveUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf
                },
                body: form
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(result.message || Object.values(result.errors || {})[0]?.[0] || 'Unable to upload document.');
            bootstrap.Modal.getOrCreateInstance($('studentDocumentModal')).hide();
            await loadDocuments();
        } catch (uploadError) {
            errorBox.textContent = uploadError.message || 'Unable to upload document.';
            errorBox.classList.remove('d-none');
        } finally {
            button.disabled = false;
        }
    };

    loadOptions();
});
