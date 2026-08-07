@extends('layouts.app')
@section('title', 'Users')
@section('page-header')
<div class="container-fluid"><div class="row g-2 align-items-center"><div class="col"><div class="page-pretitle">Administrator</div><h2 class="page-title">Users</h2></div><div class="col-auto ms-auto d-print-none"><button type="button" id="btnNewUser" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="event.preventDefault();const m=document.getElementById('userModal');if(window.bootstrap&&m){window.bootstrap.Modal.getOrCreateInstance(m).show();}"><i class="ti ti-plus icon"></i> New User</button></div></div></div>
<style>
#userModal .modal-content { max-height: calc(100vh - 2rem); }
#userModal .modal-dialog { height: calc(100vh - 2rem); max-height: calc(100vh - 2rem); }
#userModal .modal-content { height: 100%; }
#userModal form { min-height: 0; display: flex; flex: 1 1 auto; flex-direction: column; }
#userModal .modal-body { min-height: 0; height: 0; flex: 1 1 auto; max-height: none; overflow-y: scroll !important; overflow-x: hidden; }
#userModal .modal-footer { flex: 0 0 auto; }
#userModal { overflow-y: auto !important; }
#userModal .modal-dialog { height: auto; max-height: none; margin-top: 1rem; margin-bottom: 1rem; }
#userModal .modal-content { height: auto; max-height: none; }
#userModal form { display: block; }
#userModal .modal-body { height: auto; max-height: none; overflow: visible !important; }
[data-bs-theme="dark"] #userModal .form-control,[data-bs-theme="dark"] #userModal .form-select,[data-bs-theme="dark"] #userModal input[type="file"]{background:#1e293b!important;color:#f8fafc!important;border-color:#475569!important}
[data-bs-theme="dark"] #userModal .form-control::placeholder{color:#94a3b8!important}
[data-bs-theme="dark"] #userModal select option{background:#1e293b;color:#f8fafc}
[data-bs-theme="dark"] #userModal .form-label,[data-bs-theme="dark"] #userModal .form-check-label,[data-bs-theme="dark"] #userModal summary{color:#f8fafc}
[data-bs-theme="dark"] #userModal .text-secondary,[data-bs-theme="dark"] #userModal small{color:#94a3b8!important}
[data-bs-theme="dark"] #userModal .border,[data-bs-theme="dark"] #userModal details{border-color:#475569!important}
[data-bs-theme="dark"] #userModal .logo-dropzone{background:#1e293b!important;border-color:#475569!important;color:#f8fafc}
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const table = document.querySelector('.card table');
    if (!table || table.dataset.staffPhotoColumn) return;
    table.dataset.staffPhotoColumn = '1';
    const header = table.querySelector('thead tr');
    const staffHeader = header?.children[1];
    if (!staffHeader) return;
    const photoHeader = document.createElement('th');
    photoHeader.textContent = 'Photo';
    header.insertBefore(photoHeader, staffHeader);
    const photos = @json($users->pluck('photo_path')->values());
    const names = @json($users->pluck('name')->values());
    let index = 0;
    table.querySelectorAll('tbody tr').forEach(row => {
        if (row.children.length === 1) {
            row.children[0].setAttribute('colspan', '10');
            return;
        }
        const cell = document.createElement('td');
        const path = photos[index];
        if (path) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn p-0 border-0 staff-photo-view-trigger';
            button.dataset.photoUrl = `/storage/${path}`;
            button.dataset.photoTitle = names[index] || 'Staff Photo';
            button.innerHTML = `<img src="${button.dataset.photoUrl}" alt="${button.dataset.photoTitle}" style="width:44px;height:44px;object-fit:cover;border-radius:.5rem;border:1px solid var(--tblr-border-color)">`;
            cell.appendChild(button);
        } else {
            cell.innerHTML = '<span class="avatar avatar-sm bg-secondary-lt"><i class="ti ti-user"></i></span>';
        }
        row.insertBefore(cell, row.children[1]);
        index += 1;
    });
    const viewModalElement = document.getElementById('staffPhotoViewModal');
    const image = document.getElementById('staffPhotoViewImage');
    const title = document.getElementById('staffPhotoViewTitle');
    const zoom = document.getElementById('staffPhotoViewZoom');
    if (!viewModalElement || !image || !title || !zoom) return;
    const viewModal = bootstrap.Modal.getOrCreateInstance(viewModalElement);
    const updateZoom = () => { image.style.transform = `scale(${zoom.value})`; };
    document.querySelectorAll('.staff-photo-view-trigger').forEach(button => button.addEventListener('click', () => {
        image.src = button.dataset.photoUrl;
        title.textContent = button.dataset.photoTitle;
        zoom.value = '1';
        updateZoom();
        viewModal.show();
    }));
    zoom.addEventListener('input', updateZoom);
    document.getElementById('staffPhotoViewZoomIn')?.addEventListener('click', () => { zoom.value = Math.min(3, Number(zoom.value) + .1).toFixed(2); updateZoom(); });
    document.getElementById('staffPhotoViewZoomOut')?.addEventListener('click', () => { zoom.value = Math.max(1, Number(zoom.value) - .1).toFixed(2); updateZoom(); });
    document.getElementById('staffPhotoViewZoomReset')?.addEventListener('click', () => { zoom.value = '1'; updateZoom(); });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('#userModal form');
    const row = form?.querySelector('.row.g-3');
    const emailField = row?.querySelector('input[name="email"]')?.closest('.col-md-3');
    if (!row || !emailField || row.dataset.staffInformationReady) return;
    row.dataset.staffInformationReady = '1';
    const makeField = (label, name, type, value) => {
        const column = document.createElement('div');
        column.className = 'col-md-3';
        const caption = document.createElement('label');
        caption.className = 'form-label';
        caption.textContent = label;
        column.appendChild(caption);
        let control;
        if (type === 'select') {
            control = document.createElement('select');
            control.className = 'form-select';
            [['', 'Select'], ['male', 'Male'], ['female', 'Female'], ['other', 'Other']].forEach(([optionValue, optionLabel]) => {
                const option = new Option(optionLabel, optionValue, false, String(value || '') === optionValue);
                control.add(option);
            });
        } else {
            control = document.createElement('input');
            control.type = type;
            control.className = 'form-control';
            control.value = value || '';
        }
        control.name = name;
        column.appendChild(control);
        return column;
    };
    const gender = makeField('Gender', 'gender', 'select', @json(old('gender', $editUser?->gender)));
    const dateOfBirth = makeField('Date of Birth', 'date_of_birth', 'date', @json(old('date_of_birth', $editUser?->date_of_birth?->format('Y-m-d'))));
    const phone = makeField('Phone Number', 'phone', 'tel', @json(old('phone', $editUser?->phone)));
    const positionColumn = document.createElement('div');
    positionColumn.className = 'col-md-3';
    positionColumn.innerHTML = '<label class="form-label">Position</label>';
    const positionSelect = document.createElement('select');
    positionSelect.className = 'form-select';
    positionSelect.name = 'position_id';
    positionSelect.add(new Option('Select', ''));
    @foreach($positions as $position)
        const positionOption = new Option(@json($position->name), @json((string) $position->id), false, @json((string) old('position_id', $editUser?->position_id)) === @json((string) $position->id));
        positionOption.dataset.departmentId = @json((string) $position->department_id);
        positionSelect.add(positionOption);
    @endforeach
    positionColumn.appendChild(positionSelect);
    const departmentField = row.querySelector('select[name="department_id"]')?.closest('.col-md-3');
    const departmentSelect = row.querySelector('select[name="department_id"]');
    const fillDepartmentFromPosition = () => {
        const selected = positionSelect.options[positionSelect.selectedIndex];
        if (selected?.dataset.departmentId && departmentSelect) {
            departmentSelect.value = selected.dataset.departmentId;
            departmentSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }
    };
    positionSelect.addEventListener('change', fillDepartmentFromPosition);
    fillDepartmentFromPosition();
    if (departmentField) departmentField.before(positionColumn);
    emailField.after(gender, dateOfBirth, phone);
});
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const table = document.querySelector('.card table');
    if (!table || table.dataset.staffDetailsColumns) return;
    table.dataset.staffDetailsColumns = '1';
    const header = table.querySelector('thead tr');
    if (!header) return;
    const users = @json($staffDetails);
    const rows = [...table.querySelectorAll('tbody tr')];
    const addColumn = (before, label, values) => {
        const target = [...header.children].find(cell => cell.textContent.trim() === before);
        if (!target) return;
        const newHeader = document.createElement('th');
        newHeader.textContent = label;
        header.insertBefore(newHeader, target);
        const index = [...header.children].indexOf(newHeader);
        let dataIndex = 0;
        rows.forEach(row => {
            if (row.children.length === 1) {
                row.children[0].setAttribute('colspan', String(header.children.length));
                return;
            }
            const cell = document.createElement('td');
            cell.textContent = values[dataIndex++] || '';
            row.insertBefore(cell, row.children[index]);
        });
    };
    const staffNameHeader = [...header.children].find(cell => cell.textContent.trim() === 'Staff Name');
    if (staffNameHeader) staffNameHeader.textContent = 'Staff Full Name';
    addColumn('Username', 'Gender', users.map(user => user.gender));
    addColumn('Username', 'Date of Birth', users.map(user => user.date_of_birth));
    addColumn('Email', 'Phone Number', users.map(user => user.phone));
    addColumn('Department', 'Campus Assignment', users.map(user => user.campus));
    addColumn('Department', 'Position', users.map(user => user.position));
    const positionHeader = [...header.children].find(cell => cell.textContent.trim() === 'Position');
    const departmentHeader = [...header.children].find(cell => cell.textContent.trim() === 'Department');
    if (positionHeader && departmentHeader) {
        const positionIndex = [...header.children].indexOf(positionHeader);
        const departmentIndex = [...header.children].indexOf(departmentHeader);
        rows.forEach(row => {
            if (row.children.length === 1) return;
            const positionCell = row.children[positionIndex];
            const departmentCell = row.children[departmentIndex];
            if (!positionCell || !departmentCell) return;
            const department = departmentCell.textContent.trim();
            if (department && department !== '—' && department !== '-') {
                const detail = document.createElement('div');
                detail.className = 'text-secondary small';
                detail.textContent = `Dept: ${department}`;
                positionCell.appendChild(detail);
            }
            departmentCell.remove();
        });
        departmentHeader.remove();
    }
    const staffHeader = [...header.children].find(cell => cell.textContent.trim() === 'Staff Full Name');
    const genderHeader = [...header.children].find(cell => cell.textContent.trim() === 'Gender');
    const dateHeader = [...header.children].find(cell => cell.textContent.trim() === 'Date of Birth');
    if (staffHeader && genderHeader && dateHeader) {
        const staffIndex = [...header.children].indexOf(staffHeader);
        const genderIndex = [...header.children].indexOf(genderHeader);
        const dateIndex = [...header.children].indexOf(dateHeader);
        rows.forEach(row => {
            if (row.children.length === 1) return;
            const staffCell = row.children[staffIndex];
            const genderCell = row.children[genderIndex];
            const dateCell = row.children[dateIndex];
            if (!staffCell || !genderCell || !dateCell) return;
            [['Gender', genderCell.textContent.trim()], ['DOB', dateCell.textContent.trim()]].forEach(([label, value]) => {
                const detail = document.createElement('div');
                detail.className = 'text-secondary small';
                detail.textContent = `${label}: ${value}`;
                staffCell.appendChild(detail);
            });
            dateCell.remove();
            genderCell.remove();
        });
        dateHeader.remove();
        genderHeader.remove();
    }
    const userLoginHeader = [...header.children].find(cell => cell.textContent.trim() === 'Username');
    const emailHeader = [...header.children].find(cell => cell.textContent.trim() === 'Email');
    if (userLoginHeader && emailHeader) {
        userLoginHeader.textContent = 'User Login';
        const emailIndex = [...header.children].indexOf(emailHeader);
        const userLoginIndex = [...header.children].indexOf(userLoginHeader);
        rows.forEach(row => {
            if (row.children.length === 1) return;
            const loginCell = row.children[userLoginIndex];
            const emailCell = row.children[emailIndex];
            if (!loginCell || !emailCell) return;
            const email = emailCell.textContent.trim();
            if (email) {
                const emailText = document.createElement('div');
                emailText.className = 'text-secondary small';
                emailText.textContent = email;
                loginCell.appendChild(emailText);
            }
            emailCell.remove();
        });
        emailHeader.remove();
    }
    const roleHeader = [...header.children].find(cell => cell.textContent.trim() === 'Role');
    if (roleHeader) {
        const roleIndex = [...header.children].indexOf(roleHeader);
        rows.forEach(row => {
            if (row.children.length === 1) return;
            const roleCell = row.children[roleIndex];
            if (roleCell) roleCell.textContent = [...new Set(roleCell.textContent.split(',').map(role => role.trim()).filter(Boolean))].join(', ') || '—';
        });
    }
});
</script>
@if($editUser?->photo_path)
<script>
document.addEventListener('DOMContentLoaded', () => {
    const preview = document.getElementById('staffPhotoPreview');
    const container = document.getElementById('staffPhotoPreviewContainer');
    if (preview && container) {
        preview.src = @json(asset('storage/'.$editUser->photo_path));
        container.classList.remove('d-none');
    }
});
</script>
@endif
@endsection
@section('content')
<style>
[data-bs-theme="dark"] #userModal .form-control,[data-bs-theme="dark"] #userModal .form-select,[data-bs-theme="dark"] #userModal input[type="file"]{background:#1e293b!important;color:#f8fafc!important;border-color:#475569!important}
[data-bs-theme="dark"] #userModal .form-control::placeholder{color:#94a3b8!important}
[data-bs-theme="dark"] #userModal select option{background:#1e293b!important;color:#f8fafc!important}
[data-bs-theme="dark"] #userModal .form-label,[data-bs-theme="dark"] #userModal .form-check-label,[data-bs-theme="dark"] #userModal summary{color:#f8fafc!important}
[data-bs-theme="dark"] #userModal .text-secondary,[data-bs-theme="dark"] #userModal small{color:#94a3b8!important}
[data-bs-theme="dark"] #userModal .border,[data-bs-theme="dark"] #userModal details{border-color:#475569!important}
[data-bs-theme="dark"] #userModal .logo-dropzone{background:#1e293b!important;border-color:#64748b!important;color:#f8fafc!important}
[data-bs-theme="dark"] #userModal .user-date-picker .date-picker-direct,[data-bs-theme="dark"] #userModal .user-date-picker .date-picker-trigger,[data-bs-theme="dark"] #userModal .user-date-picker .date-picker-popup{background:#1e293b!important;color:#f8fafc!important;border-color:#475569!important}
[data-bs-theme="dark"] #userModal .user-date-picker .date-picker-popup button{color:#f8fafc!important}
.user-date-picker .date-picker-input-row{position:relative}
.user-date-picker .date-picker-direct{padding-right:3rem!important}
.user-date-picker .date-picker-calendar-button{position:absolute;right:.35rem;top:50%;z-index:2;width:2.5rem;min-height:2.5rem;padding:0;border:0!important;background:transparent!important;box-shadow:none!important;transform:translateY(-50%)}
.premium-password-field{position:relative}
.premium-password-field .form-control{height:52px;min-height:52px;padding:1.25rem 3rem .35rem 1rem;border:1.5px solid #dfe3ea;border-radius:14px;background:#fff;box-shadow:0 2px 7px rgba(31,41,55,.04)}
.premium-password-toggle{position:absolute;top:50%;right:.75rem;transform:translateY(-50%);border:0;background:transparent;color:#8994a5;padding:.25rem}
.profile-password-strength{margin-top:.65rem}.profile-password-strength-header{display:flex;justify-content:space-between;font-weight:600;font-size:.9rem}.profile-password-strength-value{color:#dc3545}.profile-password-strength-value.medium{color:#d97706}.profile-password-strength-value.strong{color:#198754}
.profile-password-strength-bar{height:4px;background:#edf0f4;border-radius:99px;overflow:hidden;margin:.35rem 0 .7rem}.profile-password-strength-fill{display:block;height:100%;width:0;background:#dc3545;transition:width .2s ease,background .2s ease}.profile-password-strength-fill.medium{background:#d97706}.profile-password-strength-fill.strong{background:#198754}
.profile-password-rules{display:flex;flex-wrap:wrap;gap:.65rem;color:#8994a5;font-size:.8rem}.profile-password-rule{display:inline-flex;align-items:center;gap:.25rem}.profile-password-rule::before{content:'';width:.75rem;height:.75rem;border-radius:50%;background:#dfe5ec}.profile-password-rule.is-valid{color:#198754}.profile-password-rule.is-valid::before{background:#198754}
.profile-password-meta{display:flex;align-items:center;justify-content:space-between;gap:1rem}.profile-password-note{margin:0;white-space:nowrap}@media (max-width:767.98px){.profile-password-meta{align-items:flex-start;flex-direction:column;gap:.5rem}.profile-password-note{white-space:normal}}
.user-form-layout{display:flex;align-items:flex-start;gap:1.5rem}.user-form-main{flex:0 0 calc(70% - .75rem);max-width:calc(70% - .75rem)}.user-form-password{flex:0 0 calc(30% - .75rem);max-width:calc(30% - .75rem)}.user-form-main .row:last-child{margin-bottom:0!important}
[data-bs-theme="dark"] #userModal .user-form-password .text-secondary{color:#94a3b8!important}
@media (max-width: 991.98px){.user-form-layout{display:block}.user-form-main,.user-form-password{max-width:none;width:100%}.user-form-password{margin-top:1rem}}
[data-bs-theme="dark"] #userModal .premium-password-field .form-control{background:#1e293b!important;color:#f8fafc!important;border-color:#475569!important}
[data-bs-theme="dark"] #userModal .modal-footer{border-color:#334155!important}
.staff-photo-crop-stage{background:#f1f3f5;border-radius:.5rem;padding:1rem;text-align:center;touch-action:none}
.staff-photo-crop-stage canvas{max-width:100%;height:auto;display:block;margin:auto;cursor:move}
#staffPhotoDropzone{position:relative;min-height:152px;padding:1rem 150px 1rem 1rem}
#staffPhotoDropzone #staffPhotoPreviewContainer{position:absolute;right:1rem;top:50%;display:flex;justify-content:center;width:120px;margin:0;transform:translateY(-50%)}
#staffPhotoDropzone #staffPhotoPreview{width:120px;height:120px;min-height:0;object-fit:cover;border-radius:.5rem;border:1px solid var(--tblr-border-color)}
[data-bs-theme="dark"] .staff-photo-crop-stage{background:#0f172a}
</style>
<div class="modal modal-blur fade" id="staffPhotoCropModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Crop Staff Photo</h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-secondary small">Adjust the photo inside the square frame. The final image will be exactly 400 x 400 px.</p>
                <div class="staff-photo-crop-stage">
                    <canvas id="staffPhotoCropCanvas" width="400" height="400"></canvas>
                </div>
                <div class="row g-2 align-items-center mt-3">
                    <div class="col-auto">
                        <button type="button" class="btn btn-outline-secondary" id="staffPhotoZoomOut"><i class="ti ti-zoom-out"></i></button>
                    </div>
                    <div class="col">
                        <input type="range" class="form-range" id="staffPhotoZoom" min="1" max="3" step="0.01" value="1" aria-label="Zoom photo">
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-outline-secondary" id="staffPhotoZoomIn"><i class="ti ti-zoom-in"></i></button>
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-outline-secondary" id="staffPhotoRotateLeft"><i class="ti ti-rotate-2"></i> Left</button>
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-outline-secondary" id="staffPhotoRotateRight"><i class="ti ti-rotate-clockwise-2"></i> Right</button>
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-outline-secondary" id="staffPhotoReset">Reset</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="staffPhotoCropUpload"><i class="ti ti-crop me-1"></i>Crop and Upload</button>
            </div>
        </div>
    </div>
</div>
<div class="modal modal-blur fade" id="staffPhotoViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="staffPhotoViewTitle">Staff Photo</h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center overflow-hidden">
                <img id="staffPhotoViewImage" src="#" alt="Staff photo" style="max-width:100%;max-height:65vh;object-fit:contain;transition:transform .2s ease">
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-outline-secondary" id="staffPhotoViewZoomOut"><i class="ti ti-zoom-out"></i></button>
                <input type="range" id="staffPhotoViewZoom" min="1" max="3" step=".05" value="1" style="width:180px" aria-label="Zoom staff photo">
                <button type="button" class="btn btn-outline-secondary" id="staffPhotoViewZoomIn"><i class="ti ti-zoom-in"></i></button>
                <button type="button" class="btn btn-outline-secondary" id="staffPhotoViewZoomReset">Reset</button>
            </div>
        </div>
    </div>
</div>
<script>document.addEventListener('DOMContentLoaded',()=>{const convert=()=>{document.querySelectorAll('.card table .badge').forEach(badge=>{if(!/^(active|inactive)$/i.test(badge.textContent.trim())||badge.closest('[data-status-toggle]'))return;const row=badge.closest('tr');const edit=row?.querySelector('a[href*="edit="]');const id=edit?.href.match(/[?&]edit=(\d+)/)?.[1];if(!id||!window.statusToggleMarkup)return;badge.outerHTML=window.statusToggleMarkup('user',id,/^active$/i.test(badge.textContent.trim()));});};convert();});</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('userModal');
    const userId = modal?.querySelector('input[name="user_id"]')?.value;
    const status = modal?.querySelector('select[name="status"]');
    if (!userId && status) status.value = '1';
});
</script>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="card"><div class="card-header"><h3 class="card-title">User Lists</h3></div><form method="GET"><div class="card-body border-bottom py-3"><div class="row g-2 align-items-center justify-content-between"><div class="col-auto text-secondary">Show <div class="mx-2 d-inline-block"><select class="form-control form-control-sm" name="per_page" onchange="this.form.submit()"><option value="10" @selected(request('per_page', 10)==10)>10 / page</option><option value="25" @selected(request('per_page')==25)>25 / page</option><option value="50" @selected(request('per_page')==50)>50 / page</option><option value="100" @selected(request('per_page')==100)>100 / page</option></select></div> entries</div><div class="col-auto"><div class="input-icon"><span class="input-icon-addon"><i class="ti ti-search icon"></i></span><input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Search users"></div></div></div></div></form><div class="table-responsive table-vcenter text-nowrap"><table class="table card-table"><thead><tr><th>No.</th><th>Staff Name</th><th>Username</th><th>Email</th><th>Department</th><th>Role</th><th>Login</th><th>Status</th><th class="text-center">Actions</th></tr></thead><tbody>@forelse($users as $user)<tr><td>{{ $users->firstItem() + $loop->index }}</td><td>{{ $user->name }}</td><td>{{ $user->username }}</td><td>{{ $user->email }}</td><td>{{ $user->department?->name ?? '—' }}</td><td>{{ $user->roles->pluck('name')->join(', ') ?: '—' }}</td><td>{{ $user->login_identifier === 'both' ? 'Username / Email' : ucfirst($user->login_identifier) }}</td><td><span class="badge bg-{{ $user->status ? 'success' : 'secondary' }}">{{ $user->status ? 'Active' : 'Inactive' }}</span></td><td class="text-center"><a class="btn btn-sm btn-outline-primary" href="{{ route('users.index', ['edit' => $user->id]) }}"><i class="ti ti-edit"></i></a> <form class="d-inline" method="POST" action="{{ route('users.delete', $user) }}" onsubmit="return confirm('Delete this user?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="ti ti-trash"></i></button></form></td></tr>@empty<tr><td colspan="9" class="text-center text-secondary py-4">No users found.</td></tr>@endforelse</tbody></table></div><div class="card-footer"><div class="d-flex justify-content-center">@include('partials.user-pagination')</div></div></div>
<div class="modal modal-blur fade" id="userModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">{{ $editUser ? 'Edit User' : 'Create User' }}</h5><a class="btn-close" href="{{ route('users.index') }}"></a></div><form method="POST" action="{{ route('users.save') }}" enctype="multipart/form-data">@csrf<input type="hidden" name="user_id" value="{{ $editUser?->id }}"><div class="modal-body"><div class="row g-3"><div class="col-12"><label class="form-label">Photo</label><div class="student-photo-upload-row"><div class="logo-dropzone" id="staffPhotoDropzone" tabindex="0"><i class="ti ti-cloud-upload logo-dropzone-icon"></i><div><strong>Drag and drop staff photo here</strong></div><div class="text-secondary">or click to upload a file</div><input type="file" class="d-none" name="photo" id="staff_photo" accept="image/jpeg,image/png,image/webp"></div><div class="d-none staff-photo-preview-wrap" id="staffPhotoPreviewContainer"><img id="staffPhotoPreview" src="#" alt="Staff photo preview" class="student-photo-preview"></div></div><small class="form-hint">JPG, PNG, or WEBP. Maximum size: 2 MB.</small></div><div class="col-md-3"><label class="form-label">Staff Name</label><input class="form-control" name="name" value="{{ old('name', $editUser?->name) }}" required></div><div class="col-md-3"><label class="form-label">Username</label><input class="form-control" name="username" value="{{ old('username', $editUser?->username) }}" required></div><div class="col-md-3"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="{{ old('email', $editUser?->email) }}" required></div><div class="col-md-3"><label class="form-label">Password</label><input class="form-control" type="password" name="password" minlength="8"><small class="text-secondary">Blank = 1234567890</small></div><div class="col-md-3"><label class="form-label">Confirm Password</label><input class="form-control" type="password" name="password_confirmation" minlength="8"></div><div class="col-md-3"><label class="form-label">Department</label><select class="form-select" name="department_id"><option value="">Select</option>@foreach($departments as $d)<option value="{{ $d->id }}" @selected(old('department_id', $editUser?->department_id)==$d->id)>{{ $d->name }}</option>@endforeach</select></div><div class="col-md-3"><label class="form-label">Role</label><select class="form-select" name="role_id" required><option value="">Select</option>@foreach($roles as $r)<option value="{{ $r->id }}" @selected(old('role_id', $editUser?->roles->first()?->id)==$r->id)>{{ $r->name }}</option>@endforeach</select></div><div class="col-md-4"><label class="form-label">Campus Assignments</label><select class="form-select" name="campuses[]" multiple size="4">@foreach($campuses as $c)<option value="{{ $c->id }}" @selected($editUser?->campuses->contains($c->id))>{{ $c->campus_name_en }}</option>@endforeach</select></div><div class="col-md-4"><label class="form-label">Allowed Login Method</label><select class="form-select" name="login_identifier"><option value="username" @selected(old('login_identifier', $editUser?->login_identifier ?? 'username')==='username')>Username only</option><option value="email" @selected(old('login_identifier', $editUser?->login_identifier)==='email')>Email only</option><option value="both" @selected(old('login_identifier', $editUser?->login_identifier)==='both')>Username or Email</option></select></div><div class="col-md-4"><label class="form-label">Account Status</label><select class="form-select" name="status"><option value="1" @selected(old('status', $editUser?->status ?? 1)==1)>Active</option><option value="0" @selected(old('status', $editUser?->status)==0)>Inactive</option></select><label class="form-check mt-2"><input class="form-check-input" type="checkbox" name="is_global" value="1" @checked(old('is_global', $editUser?->is_global))><span class="form-check-label">Central Office / global access</span></label></div></div><details class="mt-4"><summary>Staff-specific permissions</summary><div class="row g-2 mt-2">@foreach($permissions as $module => $items)<div class="col-md-3"><div class="text-uppercase small text-secondary">{{ $module }}</div>@foreach($items as $permission)<label class="form-check"><input class="form-check-input" type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" @checked($editUser?->permissionOverrides->contains($permission->id))><span class="form-check-label">{{ $permission->name }}</span></label>@endforeach</div>@endforeach</div></details></div><div class="modal-footer"><a class="btn me-auto" href="{{ route('users.index') }}">Cancel</a><button class="btn btn-primary">{{ $editUser ? 'Update User' : 'Create User' }}</button></div></form></div></div></div>
<script>
function openUserModalSafely() {
    const modal = document.getElementById('userModal');
    if (!modal) return;
    if (window.bootstrap?.Modal) {
        window.bootstrap.Modal.getOrCreateInstance(modal).show();
        return;
    }
    modal.classList.add('show');
    modal.style.display = 'block';
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');
}
function openUserModal() { openUserModalSafely(); }
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('userModal');
    document.addEventListener('click', event => {
        if (event.target.closest('#btnNewUser')) openUserModalSafely();
    }, true);
    @if($editUser)
        openUserModalSafely();
    @endif
});
</script>
@vite('resources/js/userManagement.js')
@endsection
