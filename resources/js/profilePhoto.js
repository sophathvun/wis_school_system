import { setupPersonPhotoEditor } from './helpers/personPhotoEditor';

const init = () => setupPersonPhotoEditor({
    editorId: 'profilePhotoCropModal', input: document.getElementById('profile_photo'),
    dropzone: document.getElementById('profilePhotoDropzone'),
    preview: document.querySelector('#profilePhotoPreview img'),
    previewContainer: document.getElementById('profilePhotoPreview'),
    editButton: document.getElementById('profilePhotoEdit'),
    pasteTarget: document.getElementById('profile-panel-profile-edit'),
    saveMessage: 'Save your profile to apply this photo.',
});
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
else init();
