import { setupPersonPhotoEditor } from './personPhotoEditor';

export function setupStaffPhotoUpload(form) {
    return setupPersonPhotoEditor({
        editorId: 'hrmStaffPhotoCropModal', input: form.elements.namedItem('photo'),
        dropzone: form.querySelector('[data-staff-photo-dropzone]'),
        preview: form.querySelector('[data-staff-photo-preview]'),
        editButton: form.querySelector('[data-staff-photo-edit]'),
        parent: form.closest('.modal'), saveMessage: 'Save the staff profile to apply this photo.',
    });
}
