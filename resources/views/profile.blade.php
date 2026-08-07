@extends('layouts.app')
@section('title', 'My Profile')
@section('content')
<div class="page-header"><div class="row align-items-center"><div class="col"><h2>My Profile</h2><div class="text-secondary">Manage your account and sign-in credentials.</div></div></div></div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('warning') || auth()->user()->must_change_password)<div class="alert alert-warning">{{ session('warning', 'Please change your temporary password before continuing.') }}</div>@endif
<div class="card"><div class="card-body"><form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">@csrf
<div class="row g-3"><div class="col-12"><label class="form-label">Profile Photo</label><div class="logo-dropzone" id="profilePhotoDropzone" tabindex="0"><i class="ti ti-cloud-upload logo-dropzone-icon"></i><div><strong>Drag and drop profile photo here</strong></div><div class="text-secondary">or click to upload a file</div><div id="profilePhotoPreview" class="mt-3 @if(!auth()->user()->photo_path)d-none @endif"><img src="{{ auth()->user()->photo_path ? asset('storage/'.auth()->user()->photo_path) : '#' }}" alt="Profile photo preview" style="width:140px;height:140px;object-fit:cover;border-radius:.5rem;border:1px solid var(--tblr-border-color);"></div><input class="d-none" type="file" name="photo" id="profile_photo" accept="image/jpeg,image/png,image/webp"></div><small class="form-hint">JPG, PNG, or WEBP. Maximum size: 2 MB. Crop output: 400 × 400 px.</small></div><div class="col-md-6"><label class="form-label">Staff Name</label><input class="form-control" name="name" value="{{ old('name', auth()->user()->name) }}" required></div><div class="col-md-6"><label class="form-label">Username</label><input class="form-control" name="username" value="{{ old('username', auth()->user()->username) }}" required></div><div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="{{ old('email', auth()->user()->email) }}" required></div><div class="col-md-6"><label class="form-label">New Password</label><input class="form-control" type="password" name="password" minlength="8"><small class="text-secondary">Leave blank to keep the current password.</small></div><div class="col-md-6"><label class="form-label">Confirm New Password</label><input class="form-control" type="password" name="password_confirmation" minlength="8"></div></div><button class="btn btn-primary mt-4">Save changes</button></form></div></div>
<div class="modal modal-blur fade" id="profilePhotoCropModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h3 class="modal-title">Crop Profile Photo</h3><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p class="text-secondary small">Adjust the photo. The final image will be exactly 400 × 400 px.</p><div class="profile-photo-crop-stage"><canvas id="profilePhotoCropCanvas" width="400" height="400"></canvas></div><div class="row g-2 align-items-center mt-3"><div class="col-auto"><button type="button" class="btn btn-outline-secondary" id="profilePhotoZoomOut"><i class="ti ti-zoom-out"></i></button></div><div class="col"><input type="range" class="form-range" id="profilePhotoZoom" min="1" max="3" step=".01" value="1" aria-label="Zoom photo"></div><div class="col-auto"><button type="button" class="btn btn-outline-secondary" id="profilePhotoZoomIn"><i class="ti ti-zoom-in"></i></button></div><div class="col-auto"><button type="button" class="btn btn-outline-secondary" id="profilePhotoRotateLeft">Left</button></div><div class="col-auto"><button type="button" class="btn btn-outline-secondary" id="profilePhotoRotateRight">Right</button></div></div></div><div class="modal-footer"><button type="button" class="btn btn-link" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" id="profilePhotoCropUpload">Crop and Upload</button></div></div></div></div>
<script>document.addEventListener('DOMContentLoaded',()=>{const z=document.getElementById('profilePhotoDropzone'),i=document.getElementById('profile_photo'),p=document.getElementById('profilePhotoPreview');if(!z||!i)return;const show=f=>{if(!f||!f.type.startsWith('image/')||f.size>2097152)return;const d=new DataTransfer();d.items.add(f);i.files=d.files;p.querySelector('img').src=URL.createObjectURL(f);p.classList.remove('d-none')};z.addEventListener('click',()=>i.click());z.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' ')i.click()});i.addEventListener('change',()=>show(i.files?.[0]));z.addEventListener('dragover',e=>{e.preventDefault();z.classList.add('is-dragging')});z.addEventListener('dragleave',()=>z.classList.remove('is-dragging'));z.addEventListener('drop',e=>{e.preventDefault();z.classList.remove('is-dragging');show(e.dataTransfer.files?.[0])})});</script>
<style>
@media (min-width: 768px) {
    .profile-account-form > .row.g-3 > div:has(input[name="name"]) { order: 1; }
    .profile-account-form > .row.g-3 > div:has(input[name="password"]) { order: 2; }
    .profile-account-form > .row.g-3 > div:has(input[name="username"]) { order: 3; }
    .profile-account-form > .row.g-3 > div:has(input[name="password_confirmation"]) { order: 4; }
    .profile-account-form > .row.g-3 > div:has(input[name="email"]) { order: 5; }
}
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelector('form[action$="/profile"]')?.classList.add('profile-account-form');
});
</script>
<style>
.premium-password-field { position: relative; }
.premium-password-field .form-control { height: 52px; min-height: 52px; padding: 1.25rem 3rem .35rem 1rem; border: 1.5px solid #dfe3ea; border-radius: 14px; background: #fff; box-shadow: 0 2px 7px rgba(31, 41, 55, .04); }
.premium-password-toggle { position: absolute; top: 50%; right: .75rem; transform: translateY(-50%); border: 0; background: transparent; color: #8994a5; padding: .25rem; }
.profile-password-strength { margin-top: .65rem; }
.profile-password-strength-header { display: flex; justify-content: space-between; font-weight: 600; font-size: .9rem; }
.profile-password-strength-value { color: #dc3545; }
.profile-password-strength-value.medium { color: #d97706; }
.profile-password-strength-value.strong { color: #198754; }
.profile-password-strength-bar { height: 4px; background: #edf0f4; border-radius: 99px; overflow: hidden; margin: .35rem 0 .7rem; }
.profile-password-strength-fill { display: block; height: 100%; width: 0; background: #dc3545; transition: width .2s ease, background .2s ease; }
.profile-password-strength-fill.medium { background: #d97706; }
.profile-password-strength-fill.strong { background: #198754; }
.profile-password-rules { display: flex; flex-wrap: wrap; gap: .65rem; color: #8994a5; font-size: .8rem; }
.profile-password-meta { display:flex; align-items:center; justify-content:space-between; gap:1rem; }
.profile-password-note { margin:0; white-space:nowrap; }
@media (max-width: 767.98px) { .profile-password-meta { align-items:flex-start; flex-direction:column; gap:.5rem; } .profile-password-note { white-space:normal; } }
.profile-password-rule { display: inline-flex; align-items: center; gap: .25rem; }
.profile-password-rule::before { content: ''; width: .75rem; height: .75rem; border-radius: 50%; background: #dfe5ec; }
.profile-password-rule.is-valid { color: #198754; }
.profile-password-rule.is-valid::before { background: #198754; }
.profile-fields-column { order: 0 !important; display: flex; flex-direction: column; gap: 1rem; }
.profile-fields-column > .col-md-6 { width: 100%; }
.profile-fields-left > .col-md-6 .form-control { height: 52px; min-height: 52px; padding: 1.25rem 1rem .35rem; border: 1.5px solid #dfe3ea; border-radius: 14px; background: #fff; box-shadow: 0 2px 7px rgba(31, 41, 55, .04); }
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('form[action$="/profile"]');
    if (!form || form.dataset.passwordUiReady) return;
    form.dataset.passwordUiReady = '1';
    const fieldsRow = form.querySelector('.row.g-3');
    if (fieldsRow && !fieldsRow.dataset.profileColumnsReady) {
        fieldsRow.dataset.profileColumnsReady = '1';
        const left = document.createElement('div');
        const right = document.createElement('div');
        left.className = 'col-md-6 profile-fields-column profile-fields-left';
        right.className = 'col-md-6 profile-fields-column';
        const moveField = (selector, target) => {
            const field = fieldsRow.querySelector(selector)?.closest('.col-md-6');
            if (!field) return;
            target.appendChild(field);
        };
        moveField('input[name="name"]', left);
        moveField('input[name="username"]', left);
        moveField('input[name="email"]', left);
        moveField('input[name="password"]', right);
        moveField('input[name="password_confirmation"]', right);
        fieldsRow.append(left, right);
    }
    const password = form.querySelector('input[name="password"]');
    const confirmation = form.querySelector('input[name="password_confirmation"]');
    const addToggle = input => {
        if (!input || input.parentElement.classList.contains('premium-password-field')) return;
        const wrapper = document.createElement('div');
        wrapper.className = 'premium-password-field';
        input.parentElement.insertBefore(wrapper, input);
        wrapper.appendChild(input);
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'premium-password-toggle';
        button.setAttribute('aria-label', 'Show password');
        button.innerHTML = '<i class="ti ti-eye"></i>';
        button.addEventListener('click', () => {
            const visible = input.type === 'text';
            input.type = visible ? 'password' : 'text';
            button.setAttribute('aria-label', visible ? 'Show password' : 'Hide password');
            button.innerHTML = `<i class="ti ${visible ? 'ti-eye' : 'ti-eye-off'}"></i>`;
        });
        wrapper.appendChild(button);
    };
    addToggle(password);
    addToggle(confirmation);
    if (password) {
        const strength = document.createElement('div');
        strength.className = 'profile-password-strength';
        strength.innerHTML = '<div class="profile-password-strength-header"><span>Password Strength</span><span class="profile-password-strength-value">Weak</span></div><div class="profile-password-strength-bar"><span class="profile-password-strength-fill"></span></div><div class="profile-password-rules"><span class="profile-password-rule" data-rule="length">8 Chars</span><span class="profile-password-rule" data-rule="upper">A-Z</span><span class="profile-password-rule" data-rule="lower">a-z</span><span class="profile-password-rule" data-rule="number">123</span><span class="profile-password-rule" data-rule="special">@#$</span></div>';
        password.closest('.premium-password-field')?.after(strength);
        const note = password.closest('.col-md-6')?.querySelector('small.text-secondary');
        const rules = strength.querySelector('.profile-password-rules');
        if (note && rules) {
            const meta = document.createElement('div');
            meta.className = 'profile-password-meta';
            note.classList.add('profile-password-note');
            meta.append(note, rules);
            strength.appendChild(meta);
        }
        const updateStrength = () => {
            const value = password.value;
            const checks = { length: value.length >= 8, upper: /[A-Z]/.test(value), lower: /[a-z]/.test(value), number: /\d/.test(value), special: /[^A-Za-z0-9]/.test(value) };
            Object.entries(checks).forEach(([rule, valid]) => strength.querySelector(`[data-rule="${rule}"]`)?.classList.toggle('is-valid', valid));
            const score = Object.values(checks).filter(Boolean).length;
            const label = strength.querySelector('.profile-password-strength-value');
            const fill = strength.querySelector('.profile-password-strength-fill');
            const level = score >= 4 ? 'strong' : score >= 2 ? 'medium' : '';
            label.textContent = score >= 4 ? 'Strong' : score >= 2 ? 'Medium' : 'Weak';
            label.className = `profile-password-strength-value ${level}`;
            fill.style.width = `${score * 20}%`;
            fill.className = `profile-password-strength-fill ${level}`;
        };
        password.addEventListener('input', updateStrength);
        updateStrength();
    }
});
</script>
<style>
.profile-photo-crop-stage{background:#f1f3f5;border-radius:.5rem;padding:1rem;text-align:center;touch-action:none}.profile-photo-crop-stage canvas{max-width:100%;height:auto;display:block;margin:auto;cursor:move}
[data-bs-theme="dark"] .profile-photo-crop-stage{background:#0f172a}
</style>
@vite('resources/js/profilePhoto.js')
@endsection
