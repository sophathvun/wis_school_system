document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-student-documents-page]');
    if (!page || page.dataset.enhanced === '1') return;
    page.dataset.enhanced = '1';

    const $ = id => document.getElementById(id);
    const state = { allStudents: [], students: [], filterOptions: {}, types: [] };
    let studentSearchTimer = null;
    const optionsUrl = page.dataset.optionsUrl;
    const saveUrl = page.dataset.saveUrl;
    const csrf = page.dataset.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '';
    const permissions = (() => {
        try { return JSON.parse(document.body?.dataset.userPermissions || '[]'); }
        catch (_) { return []; }
    })();
    const can = permission => permissions.includes('*') || permissions.includes(permission);
    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[c]));
    const studentLabel = item => `${item.student_id || item.student_no || ''} - ${item.full_name_en || item.full_name_kh || ''}`;
    const selectedStudent = () => (state.students || []).find(student => String(student.id) === String($('student-document-student').value));
    const familyMember = (student, relationship) => (student?.family_members || []).find(member => String(member.relationship_type || member.pivot?.relationship_type || '').toLowerCase() === relationship) || {};
    const formatPhone = value => {
        const digits = String(value || '').replace(/\D/g, '').replace(/^855/, '').replace(/^0+/, '');
        if (!digits) return '-';
        if (digits.length <= 2) return digits;
        if (digits.length <= 5) return `${digits.slice(0, 2)} ${digits.slice(2)}`;
        return `+855 ${digits.slice(0, 2)} ${digits.slice(2, 5)} ${digits.slice(5)}`;
    };
    const formatDate = value => {
        if (!value) return '-';
        const date = new Date(String(value).includes('T') ? value : `${value}T00:00:00`);
        if (Number.isNaN(date.getTime())) return value;
        return date.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
    };
    const genderIcon = value => {
        const gender = String(value || '').toLowerCase();
        if (gender.startsWith('f')) return 'ti-gender-female';
        if (gender.startsWith('m')) return 'ti-gender-male';
        return 'ti-gender-bigender';
    };
    const enrollmentForStudent = student => (student?.enrollments || []).find(enrollmentMatchesFilters) || (student?.enrollments || []).find(enrollment => String(enrollment.enrollment_status || '').toLowerCase() === 'active') || (student?.enrollments || [])[0] || {};
    const enrollmentClassLabel = enrollment => {
        const campus = enrollment.campus?.campus_name_en;
        const grade = enrollment.grade?.grade ? `Grade ${enrollment.grade.grade}${enrollment.school_class?.class_name || ''}` : '';
        const group = enrollment.session?.session_short_name;
        return [campus, grade, group].filter(Boolean).join(' - ') || '-';
    };
    const gradeFilterLabel = enrollment => `${enrollment.grade?.grade || ''}${enrollment.school_class?.class_name || ''}`.trim();
    const normalizeText = value => String(value || '').toLowerCase().replace(/\s+/g, ' ').trim();
    const normalizeDigits = value => String(value || '').replace(/\D/g, '');
    const enrollmentsFor = student => student?.enrollments || [];
    const optionKey = value => String(value ?? '');
    const uniqueOptions = (items, getValue, getLabel = getValue) => {
        const map = new Map();
        items.forEach(item => {
            const value = getValue(item);
            if (value === undefined || value === null || value === '') return;
            const key = optionKey(value);
            if (!map.has(key)) map.set(key, { value: key, label: getLabel(item) });
        });
        return Array.from(map.values()).sort((a, b) => String(a.label).localeCompare(String(b.label), undefined, { numeric: true }));
    };
    const fillFilterSelect = (select, placeholder, options, keepValue = true) => {
        const currentValue = keepValue ? select.value : '';
        select.innerHTML = `<option value="">${placeholder}</option>` + options.map(option => `<option value="${esc(option.value)}">${esc(option.label)}</option>`).join('');
        if (currentValue && options.some(option => String(option.value) === String(currentValue))) {
            select.value = currentValue;
        }
    };

    const studentSelect = $('student-document-student');
    studentSelect.classList.add('d-none');
    studentSelect.insertAdjacentHTML('afterend',
        '<div id="student-document-combobox" class="location-combobox"><button type="button" class="location-combobox-toggle"><span class="location-combobox-selected">Student Name</span><i class="ti ti-chevron-down"></i></button><div class="location-combobox-menu d-none"><input type="search" class="form-control location-combobox-search" placeholder="Search Student ID or Full Name"><div class="location-combobox-results"></div></div></div>');

    const studentCombo = $('student-document-combobox');
    const studentMenu = studentCombo.querySelector('.location-combobox-menu');
    const studentSearch = studentCombo.querySelector('.location-combobox-search');
    const studentResults = studentCombo.querySelector('.location-combobox-results');
    const studentSelected = studentCombo.querySelector('.location-combobox-selected');
    const filterYear = $('student-document-filter-year');
    const filterCampus = $('student-document-filter-campus');
    const filterGrade = $('student-document-filter-grade');
    const globalSearch = $('student-document-global-search');
    if (!can('student-documents.create')) $('newStudentDocument')?.classList.add('d-none');

    const enrollmentMatchesFilters = enrollment => {
        const year = filterYear.value;
        const campus = filterCampus.value;
        const grade = filterGrade.value;

        return (!year || optionKey(enrollment.academic_year?.id) === year)
            && (!campus || optionKey(enrollment.campus?.id) === campus)
            && (!grade || gradeFilterLabel(enrollment) === grade);
    };

    const studentMatchesSearch = student => {
        const term = normalizeText(globalSearch.value);
        const digits = normalizeDigits(globalSearch.value);
        if (!term && !digits) return true;
        const familyPhones = (student.family_members || []).map(member => member.phone).join(' ');
        const textHaystack = normalizeText([
            student.student_id,
            student.student_no,
            student.full_name_en,
            student.full_name_kh,
            student.home_phone,
            familyPhones,
        ].filter(Boolean).join(' '));
        const digitHaystack = normalizeDigits([student.home_phone, familyPhones].filter(Boolean).join(' '));
        return (term && textHaystack.includes(term)) || (digits && digitHaystack.includes(digits));
    };

    const studentMatchesFilters = student => {
        const hasEnrollmentFilters = Boolean(filterYear.value || filterCampus.value || filterGrade.value);
        const enrollmentMatch = !hasEnrollmentFilters || enrollmentsFor(student).some(enrollmentMatchesFilters);
        return enrollmentMatch && studentMatchesSearch(student);
    };

    const renderFilterOptions = (keepValue = true) => {
        fillFilterSelect(filterYear, 'All Academic Year', state.filterOptions?.academicYears || [], keepValue);
        fillFilterSelect(filterCampus, 'All Campus', state.filterOptions?.campuses || [], keepValue);
        fillFilterSelect(filterGrade, 'All Grade', state.filterOptions?.grades || [], keepValue);
    };

    const renderStudentResults = () => {
        const term = studentSearch.value.toLowerCase().trim();
        const options = Array.from(studentSelect.options).slice(1).filter(option => !term || option.textContent.toLowerCase().includes(term));
        studentResults.innerHTML = options.length ? options.map(option =>
            `<button type="button" class="location-combobox-option" data-student-value="${option.value}">${esc(option.textContent)}</button>`
        ).join('') : '<div class="text-secondary px-2 py-3">No students found</div>';
    };

    const setStudentOptions = students => {
        const currentValue = studentSelect.value;
        state.allStudents = students || [];
        state.students = state.allStudents.filter(studentMatchesFilters);
        studentSelect.innerHTML = '<option value=""></option>' + state.students.map(item => `<option value="${item.id}">${esc(studentLabel(item))}</option>`).join('');
        if (currentValue && state.students.some(student => String(student.id) === String(currentValue))) {
            studentSelect.value = currentValue;
        }
        syncStudentSelection();
        renderStudentResults();
        renderStudentProfileCard();
    };

    const applyStudentFilters = (keepStudent = true) => {
        const currentValue = keepStudent ? studentSelect.value : '';
        renderFilterOptions(true);
        state.students = state.allStudents.filter(studentMatchesFilters);
        studentSelect.innerHTML = '<option value=""></option>' + state.students.map(item => `<option value="${item.id}">${esc(studentLabel(item))}</option>`).join('');
        if (currentValue && state.students.some(student => String(student.id) === String(currentValue))) {
            studentSelect.value = currentValue;
        }
        syncStudentSelection();
        renderStudentResults();
        renderStudentProfileCard();
        if (!studentSelect.value) {
            $('student-documents-table').innerHTML = '<tr><td colspan="6" class="text-center">Select a student.</td></tr>';
        }
    };

    const fetchStudentOptions = async (term = '') => {
        const url = new URL(optionsUrl, window.location.origin);
        if (term) url.searchParams.set('search', term);
        if (filterYear.value) url.searchParams.set('academic_year_id', filterYear.value);
        if (filterCampus.value) url.searchParams.set('campus_id', filterCampus.value);
        if (filterGrade.value) url.searchParams.set('grade', filterGrade.value);
        const data = await (await fetch(url, { headers: { Accept: 'application/json' } })).json();
        setStudentOptions(data.students || []);
        if (data.types) {
            state.types = data.types;
            documentTypeSelect.innerHTML = '<option value=""></option>' + (state.types || []).map(item => `<option value="${item.id}">${esc(item.name_kh || '')}${item.name_kh ? ' / ' : ''}${esc(item.name_en)}</option>`).join('');
            syncDocumentType();
            renderDocumentTypes();
        }
    };

    const fetchFilterOptions = async (keepValue = true) => {
        const url = new URL(optionsUrl, window.location.origin);
        url.searchParams.set('meta', '1');
        if (filterYear.value) url.searchParams.set('academic_year_id', filterYear.value);
        if (filterCampus.value) url.searchParams.set('campus_id', filterCampus.value);
        const data = await (await fetch(url, { headers: { Accept: 'application/json' } })).json();
        state.filterOptions = data.filterOptions || {};
        renderFilterOptions(keepValue);
        if (data.types) {
            state.types = data.types;
            documentTypeSelect.innerHTML = '<option value=""></option>' + (state.types || []).map(item => `<option value="${item.id}">${esc(item.name_kh || '')}${item.name_kh ? ' / ' : ''}${esc(item.name_en)}</option>`).join('');
            syncDocumentType();
            renderDocumentTypes();
        }
    };

    const syncStudentSelection = () => {
        studentSelected.textContent = studentSelect.value ? studentSelect.selectedOptions[0]?.textContent || 'Student Name' : 'Student Name';
    };

    const renderStudentProfileCard = () => {
        const container = $('student-document-profile-card');
        const student = selectedStudent();
        if (!container) return;
        if (!student) {
            container.classList.add('d-none');
            container.innerHTML = '';
            return;
        }

        const mother = familyMember(student, 'mother');
        const father = familyMember(student, 'father');
        const enrollment = enrollmentForStudent(student);
        const photo = student.photo_path
            ? `<img class="student-document-profile-photo" src="/storage/${esc(student.photo_path)}" alt="Student photo">`
            : '<div class="student-document-profile-photo student-document-profile-photo-empty"><i class="ti ti-user"></i></div>';
        const genderText = student.gender || student.gender_kh || '-';

        container.innerHTML = `
            <div class="student-document-profile-grid">
                <div class="student-document-photo-section">
                    ${photo}
                </div>
                <div class="student-document-profile-section student-document-student-section">
                    <div class="student-document-section-title">STUDENT INFORMATION</div>
                    <div class="student-document-info-primary">ID: ${esc(student.student_id || student.student_no || '-')}</div>
                    <div class="student-document-info-primary school-profile-khmer">${esc(student.full_name_kh || '-')}</div>
                    <div class="student-document-info-primary">${esc(student.full_name_en || '-')}</div>
                    <div class="student-document-info-primary"><i class="ti ${genderIcon(student.gender)} me-1 text-primary"></i>${esc(genderText)}</div>
                    <div class="student-document-info-primary">DOB: ${esc(formatDate(student.date_of_birth))}</div>
                </div>
                <div class="student-document-profile-section student-document-parent-section student-document-mother-section">
                    <div class="student-document-section-title">MOTHER</div>
                    <div class="student-document-info-primary school-profile-khmer">${esc(mother.full_name_kh || '-')}</div>
                    <div class="student-document-info-primary">${esc(mother.full_name_en || '-')}</div>
                    <div class="student-document-info-primary"><i class="ti ti-phone me-1 text-primary"></i>${esc(formatPhone(mother.phone))}</div>
                </div>
                <div class="student-document-profile-section student-document-parent-section student-document-father-section">
                    <div class="student-document-section-title">FATHER</div>
                    <div class="student-document-info-primary school-profile-khmer">${esc(father.full_name_kh || '-')}</div>
                    <div class="student-document-info-primary">${esc(father.full_name_en || '-')}</div>
                    <div class="student-document-info-primary"><i class="ti ti-phone me-1 text-primary"></i>${esc(formatPhone(father.phone))}</div>
                </div>
                <div class="student-document-profile-section student-document-enrollment-section">
                    <div class="student-document-section-title">ENROLLMENT</div>
                    <div class="student-document-info-primary">${esc(enrollment.academic_year?.academic_year || '-')}</div>
                    <div class="student-document-info-primary">${esc(enrollmentClassLabel(enrollment))}</div>
                    <div class="student-document-info-primary">${esc(enrollment.enrollment_status || '-')}</div>
                </div>
            </div>`;
        container.classList.remove('d-none');
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
    studentSearch.oninput = () => {
        renderStudentResults();
        window.clearTimeout(studentSearchTimer);
        studentSearchTimer = window.setTimeout(async () => {
            const term = studentSearch.value.trim();
            studentResults.innerHTML = '<div class="text-secondary px-2 py-3">Searching students...</div>';
            try {
                await fetchStudentOptions(term);
            } catch {
                renderStudentResults();
            }
        }, 250);
    };
    studentResults.onclick = event => {
        const option = event.target.closest('[data-student-value]');
        if (!option) return;
        studentSelect.value = option.dataset.studentValue;
        studentSelect.dispatchEvent(new Event('change', { bubbles: true }));
        studentMenu.classList.add('d-none');
    };
    filterYear.addEventListener('change', async () => {
        filterCampus.value = '';
        filterGrade.value = '';
        studentSelect.value = '';
        await fetchFilterOptions(true);
        await fetchStudentOptions(globalSearch.value.trim());
    });
    filterCampus.addEventListener('change', async () => {
        filterGrade.value = '';
        studentSelect.value = '';
        await fetchFilterOptions(true);
        await fetchStudentOptions(globalSearch.value.trim());
    });
    filterGrade.addEventListener('change', async () => {
        studentSelect.value = '';
        await fetchStudentOptions(globalSearch.value.trim());
    });
    globalSearch.addEventListener('input', () => {
        const term = globalSearch.value.trim();
        studentSelect.value = '';
        studentSearch.value = '';
        syncStudentSelection();
        renderStudentProfileCard();
        $('student-documents-table').innerHTML = '<tr><td colspan="6" class="text-center">Select a student.</td></tr>';
        if (term) {
            studentSelected.textContent = 'Searching...';
            studentResults.innerHTML = '<div class="text-secondary px-2 py-3">Searching students...</div>';
            studentMenu.classList.remove('d-none');
        } else {
            studentMenu.classList.add('d-none');
        }
        window.clearTimeout(studentSearchTimer);
        studentSearchTimer = window.setTimeout(async () => {
            try {
                await fetchStudentOptions(term);
            } catch {
                applyStudentFilters(false);
            }
        }, 300);
    });

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
        clearUploadValidation();
        documentTypeMenu.classList.add('d-none');
    };
    documentTypeSelect.addEventListener('change', syncDocumentType);

    const documentFile = $('document-file');
    documentFile.required = false;
    documentFile.removeAttribute('required');
    documentFile.classList.add('d-none');
    documentFile.insertAdjacentHTML('afterend',
        '<div id="student-document-dropzone" class="premium-document-dropzone" tabindex="0"><span class="document-upload-icon"><i class="ti ti-cloud-upload"></i></span><div class="fw-bold fs-3">Drop Files Here</div><div class="mt-1">or <span class="document-browse-link">Browse File</span></div><div class="document-upload-hint mt-3">Supports PDF, JPG, PNG, DOC, DOCX &middot; Maximum 20 MB per file</div><div class="document-upload-hint mt-1">You can also copy and paste a file here</div><div id="student-document-file-list" class="premium-document-file-list"></div></div>');

    const documentDropzone = $('student-document-dropzone');
    const documentFileList = $('student-document-file-list');
    const uploadStudentInfo = $('student-document-upload-student');
    let selectedDocumentFile = null;

    const showUploadAlert = (title, text, icon = 'warning') => {
        if (window.Swal) {
            window.Swal.fire({
                title,
                text,
                icon,
                position: 'top',
                confirmButtonText: 'OK',
                customClass: {
                    popup: 'student-document-swal-popup'
                }
            });
        } else {
            window.alert(text || title);
        }
    };

    const setUploadFieldError = (target, message) => {
        if (!target) return;
        target.classList.add('is-invalid');
        let feedback = target.nextElementSibling;
        if (!feedback?.classList.contains('student-document-field-error')) {
            feedback = document.createElement('div');
            feedback.className = 'student-document-field-error';
            target.insertAdjacentElement('afterend', feedback);
        }
        feedback.textContent = message;
    };

    const clearUploadValidation = () => {
        documentTypeCombo.classList.remove('is-invalid');
        documentDropzone.classList.remove('is-invalid');
        document.querySelectorAll('#studentDocumentForm .student-document-field-error').forEach(item => item.remove());
        const errorBox = $('student-document-error');
        errorBox.textContent = '';
        errorBox.classList.add('d-none');
    };

    document.querySelectorAll('#studentDocumentForm .col-md-6, #studentDocumentForm .col-12').forEach(wrapper => {
        if (!wrapper.querySelector('#student-document-dropzone')) wrapper.classList.add('premium-floating-field');
        wrapper.querySelectorAll('input.form-control, textarea.form-control').forEach(control => {
            if (!control.placeholder) control.placeholder = ' ';
        });
    });

    const fileSize = bytes => bytes < 1024 * 1024 ? `${Math.ceil(bytes / 1024)} KB` : `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
    const renderDocumentFile = () => {
        const file = selectedDocumentFile || documentFile.files[0];
        documentDropzone.classList.toggle('has-file', !!file);
        documentFileList.innerHTML = file ? `<div class="premium-document-file-item"><span class="document-file-icon"><i class="ti ti-file-description"></i></span><div class="min-w-0"><div class="document-file-name">${esc(file.name)}</div><div class="document-file-meta">${fileSize(file.size)} &middot; Ready to upload</div></div><button type="button" class="document-file-remove" id="remove-student-document-file" aria-label="Remove file"><i class="ti ti-x"></i></button></div>` : '';
    };
    const renderUploadStudentInfo = () => {
        const student = selectedStudent();
        if (!uploadStudentInfo || !student) {
            uploadStudentInfo?.classList.add('d-none');
            if (uploadStudentInfo) uploadStudentInfo.innerHTML = '';
            return;
        }
        const photo = student.photo_path
            ? `<img class="student-document-upload-photo" src="/storage/${esc(student.photo_path)}" alt="Student photo">`
            : '<div class="student-document-upload-photo student-document-upload-photo-empty"><i class="ti ti-user"></i></div>';
        uploadStudentInfo.innerHTML = `
            <div class="student-document-upload-photo-wrap">
                ${photo}
            </div>
            <div class="student-document-upload-details">
                <strong>${esc(student.student_id || student.student_no || '-')}</strong>
                <strong class="school-profile-khmer">${esc(student.full_name_kh || '-')}</strong>
                <strong>${esc(student.full_name_en || '-')}</strong>
            </div>`;
        uploadStudentInfo.classList.remove('d-none');
    };
    const clearDocumentFile = () => {
        selectedDocumentFile = null;
        documentFile.value = '';
        renderDocumentFile();
    };
    const assignDocumentFile = files => {
        if (!files?.length) return;
        selectedDocumentFile = files[0];
        try {
            const transfer = new DataTransfer();
            transfer.items.add(selectedDocumentFile);
            documentFile.files = transfer.files;
        } catch (error) {
            documentFile.value = '';
        }
        renderDocumentFile();
        clearUploadValidation();
    };
    const clipboardFiles = clipboard => {
        const direct = Array.from(clipboard?.files || []);
        if (direct.length) return direct;
        return Array.from(clipboard?.items || []).filter(item => item.kind === 'file').map(item => item.getAsFile()).filter(Boolean);
    };
    documentDropzone.onclick = event => {
        if (event.target.closest('#remove-student-document-file')) return;
        documentDropzone.focus();
        documentFile.click();
    };
    documentDropzone.onkeydown = event => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            documentFile.click();
        }
    };
    documentFile.onchange = () => {
        selectedDocumentFile = documentFile.files[0] || null;
        renderDocumentFile();
        clearUploadValidation();
    };
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
            clearDocumentFile();
        }
    };

    const loadOptions = async () => {
        await fetchFilterOptions(false);
        studentSelect.innerHTML = '<option value=""></option>';
        syncStudentSelection();
        renderStudentResults();
        fetchStudentOptions().catch(() => {
            studentResults.innerHTML = '<div class="text-secondary px-2 py-3">Unable to load students</div>';
        });
    };

    const loadDocuments = async () => {
        const id = $('student-document-student').value;
        renderStudentProfileCard();
        if (!id) {
            $('student-documents-table').innerHTML = '<tr><td colspan="6" class="text-center">Select a student.</td></tr>';
            return;
        }
        const documents = await (await fetch('/student-documents/fetch/' + id)).json();
        $('student-documents-table').innerHTML = documents.length ? documents.map((doc, index) => {
            const titleKh = doc.type?.name_kh || '';
            const titleEn = doc.type?.name_en || doc.document_type || '-';
            const fileName = doc.original_filename || '-';
            const viewButton = doc.file_path && can('student-documents.preview') ? `<a class="btn btn-sm btn-outline-primary student-document-action-btn" href="/student-documents/${doc.id}/view" target="_blank" rel="noopener" title="View Document" aria-label="View Document"><i class="ti ti-eye"></i><span>View</span></a>` : '';
            const downloadButton = doc.file_path && can('student-documents.download') ? `<a class="btn btn-sm btn-outline-success student-document-action-btn" href="/student-documents/${doc.id}/download" title="Download Document" aria-label="Download Document"><i class="ti ti-download"></i><span>Download</span></a>` : '';
            const deleteButton = can('student-documents.delete') ? `<button type="button" class="btn btn-sm btn-outline-danger student-document-action-btn" data-delete="${doc.id}" title="Delete Document" aria-label="Delete Document"><i class="ti ti-trash"></i><span>Delete</span></button>` : '';
            const actions = viewButton + downloadButton + deleteButton;
            return `<tr><td>${String(index + 1).padStart(2, '0')}</td><td>${titleKh ? `<div class="student-document-type-khmer school-profile-khmer">${esc(titleKh)}</div>` : ''}<div class="small text-secondary">${esc(titleEn)}</div></td><td>${esc(doc.title || '-')}</td><td>${esc(doc.document_number || '-')}</td><td>${esc(fileName)}</td><td class="text-center"><div class="student-document-actions">${actions || '<span class="text-secondary">-</span>'}</div></td></tr>`;
        }).join('') : '<tr><td colspan="6" class="text-center">No documents found.</td></tr>';
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
        clearDocumentFile();
        clearUploadValidation();
        $('document-student-id').value = $('student-document-student').value;
        renderUploadStudentInfo();
        bootstrap.Modal.getOrCreateInstance($('studentDocumentModal')).show();
        setTimeout(() => documentDropzone.focus(), 250);
    };
    $('studentDocumentForm').onsubmit = async event => {
        event.preventDefault();
        const errorBox = $('student-document-error');
        clearUploadValidation();
        const file = selectedDocumentFile || $('document-file').files[0];
        const missingFields = [];
        if (!$('document-student-id').value) {
            missingFields.push('Student');
        }
        if (!documentTypeSelect.value) {
            missingFields.push('Document Type');
            setUploadFieldError(documentTypeCombo, 'Please select document type.');
        }
        if (!file) {
            missingFields.push('File');
            setUploadFieldError(documentDropzone, 'Please choose, drag and drop, or paste a document file.');
        }
        if (missingFields.length) {
            const message = `Please complete: ${missingFields.join(', ')}.`;
            errorBox.textContent = message;
            errorBox.classList.remove('d-none');
            showUploadAlert('Required Fields Missing', message);
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
            const message = uploadError.message || 'Unable to upload document.';
            errorBox.textContent = message;
            errorBox.classList.remove('d-none');
            showUploadAlert('Upload Failed', message, 'error');
        } finally {
            button.disabled = false;
        }
    };

    loadOptions();
});
