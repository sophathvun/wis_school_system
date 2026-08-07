@extends('layouts.app')
@section('title','Roles')
<style>
#roleModal .modal-dialog{max-width:480px}
#roleModal .role-floating-field{position:relative;margin-bottom:1rem}
#roleModal .role-floating-field>.form-label{position:absolute;z-index:2;top:.42rem;left:1rem;margin:0;padding:0 .4rem;background:#fff;color:#5b4bd1;font-size:.72rem;font-weight:700;line-height:1.1;pointer-events:none}
#roleModal .role-floating-field>.form-control,#roleModal .role-floating-field>.form-select{height:52px;min-height:52px;padding-top:1.25rem;padding-bottom:.35rem;border:1.5px solid #dfe3ea;border-radius:14px;background:#fff;box-shadow:0 2px 7px rgba(31,41,55,.04);transition:border-color .18s ease,box-shadow .18s ease}
#roleModal .role-floating-field>textarea.form-control{height:72px;min-height:72px}
#roleModal .role-floating-field>.form-control:focus,#roleModal .role-floating-field>.form-select:focus{border-color:#6b5bd6;box-shadow:0 0 0 3px rgba(107,91,214,.14)}
#roleModal .role-floating-field>.form-label{top:1rem;left:1rem;padding:0 .35rem;background:transparent;color:var(--tblr-secondary);font-size:.95rem;font-weight:400;transition:top .16s ease,left .16s ease,font-size .16s ease,color .16s ease,background-color .16s ease}
#roleModal .role-floating-field.has-value>.form-label,#roleModal .role-floating-field:focus-within>.form-label{top:.42rem;left:1rem;padding:0 .45rem;background:#fff;color:#5b4bd1;font-size:.72rem;font-weight:700}
#roleModal .role-location-combobox{position:relative}
#roleModal .role-location-combobox-toggle{width:100%;height:52px;padding:1.25rem 2.75rem .35rem 1rem;border:1.5px solid #dfe3ea;border-radius:14px;background:#fff;box-shadow:0 2px 7px rgba(31,41,55,.04);text-align:left;color:var(--tblr-body-color);font:inherit;position:relative}
#roleModal .role-location-combobox-toggle:focus{outline:0;border-color:#6b5bd6;box-shadow:0 0 0 3px rgba(107,91,214,.14)}
#roleModal .role-location-combobox-toggle>i{position:absolute;right:1rem;top:50%;transform:translateY(-50%);color:var(--tblr-secondary)}
#roleModal .role-location-combobox-menu{position:absolute;z-index:20;left:0;right:0;top:calc(100% + .35rem);padding:.65rem;background:#fff;border:1px solid #dfe3ea;border-radius:14px;box-shadow:0 12px 28px rgba(31,41,55,.16)}
#roleModal .role-location-combobox-menu .form-control{height:44px;border-radius:10px}
#roleModal .role-location-combobox-results{max-height:220px;overflow-y:auto;margin-top:.5rem}
#roleModal .role-location-combobox-option{display:block;width:100%;border:0;background:transparent;text-align:left;padding:.65rem .75rem;border-radius:9px;color:var(--tblr-body-color)}
#roleModal .role-location-combobox-option:hover{background:#f1f3f5}
[data-bs-theme="dark"] #roleModal .role-floating-field>.form-label{background:transparent;color:var(--tblr-secondary-color,var(--tblr-secondary))}
[data-bs-theme="dark"] #roleModal .role-floating-field.has-value>.form-label,[data-bs-theme="dark"] #roleModal .role-floating-field:focus-within>.form-label{background:var(--tblr-bg-surface,var(--tblr-bg-forms));color:#a99cff}
[data-bs-theme="dark"] #roleModal .role-floating-field>.form-control,[data-bs-theme="dark"] #roleModal .role-floating-field>.form-select,[data-bs-theme="dark"] #roleModal .role-location-combobox-toggle{background:var(--tblr-bg-forms,#1e293b)!important;color:var(--tblr-body-color)!important;border-color:var(--tblr-border-color)!important}
[data-bs-theme="dark"] #roleModal .role-floating-field>.form-control:focus,[data-bs-theme="dark"] #roleModal .role-floating-field>.form-select:focus,[data-bs-theme="dark"] #roleModal .role-location-combobox-toggle:focus{border-color:#8b7cf6!important;box-shadow:0 0 0 3px rgba(139,124,246,.2)!important}
[data-bs-theme="dark"] #roleModal .role-location-combobox-menu{background:var(--tblr-bg-surface,#1e293b);border-color:var(--tblr-border-color);box-shadow:0 12px 28px rgba(0,0,0,.4)}
[data-bs-theme="dark"] #roleModal .role-location-combobox-option{color:var(--tblr-body-color)}
[data-bs-theme="dark"] #roleModal .role-location-combobox-option:hover{background:rgba(255,255,255,.08)}
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const body = document.querySelector('#roleModal .modal-body');
    if (!body || body.dataset.premiumFieldsReady) return;
    body.dataset.premiumFieldsReady = '1';
    [...body.querySelectorAll(':scope > .form-label')].forEach(label => {
        const control = label.nextElementSibling;
        if (!control || !/^(INPUT|TEXTAREA|SELECT)$/.test(control.tagName)) return;
        const field = document.createElement('div');
        field.className = 'role-floating-field';
        label.parentNode.insertBefore(field, label);
        field.append(label, control);
        control.classList.remove('mb-3');
    });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('#roleModal .role-floating-field').forEach(field => {
        const control = field.querySelector('.form-control, .form-select');
        if (!control) return;
        const sync = () => field.classList.toggle('has-value', String(control.value || '').trim() !== '');
        control.addEventListener('input', sync);
        control.addEventListener('change', sync);
        sync();
    });
});
</script>
@section('page-header')<div class="container-fluid"><div class="row g-2 align-items-center"><div class="col"><div class="page-pretitle">Administrator</div><h2 class="page-title">Roles</h2></div><div class="col-auto"><button class="btn btn-primary" id="btnNewRole"><i class="ti ti-plus icon"></i> New Role</button></div></div></div>@endsection
@section('content')
<style>#roleModal .modal-dialog{max-width:480px}</style>
<style>
#roleModal .role-floating-field{position:relative;margin-bottom:1rem}
#roleModal .role-floating-field>.form-label{position:absolute;z-index:2;top:.42rem;left:1rem;margin:0;padding:0 .4rem;background:#fff;color:#5b4bd1;font-size:.72rem;font-weight:700;line-height:1.1;pointer-events:none}
#roleModal .role-floating-field>.form-control,#roleModal .role-floating-field>.form-select{height:52px;min-height:52px;padding-top:1.25rem;padding-bottom:.35rem;border:1.5px solid #dfe3ea;border-radius:14px;background:#fff;box-shadow:0 2px 7px rgba(31,41,55,.04);transition:border-color .18s ease,box-shadow .18s ease}
#roleModal .role-floating-field>textarea.form-control{height:72px;min-height:72px}
#roleModal .role-floating-field>.form-control:focus,#roleModal .role-floating-field>.form-select:focus{border-color:#6b5bd6;box-shadow:0 0 0 3px rgba(107,91,214,.14)}
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const body = document.querySelector('#roleModal .modal-body');
    if (!body || body.dataset.premiumFieldsReady) return;
    body.dataset.premiumFieldsReady = '1';
    [...body.querySelectorAll(':scope > .form-label')].forEach(label => {
        const control = label.nextElementSibling;
        if (!control || !/^(INPUT|TEXTAREA|SELECT)$/.test(control.tagName)) return;
        const field = document.createElement('div');
        field.className = 'role-floating-field';
        label.parentNode.insertBefore(field, label);
        field.append(label, control);
        control.classList.remove('mb-3');
    });
});
</script>
<style>
#roleModal .role-floating-field>.form-label{top:1rem;left:1rem;padding:0 .35rem;background:transparent;color:var(--tblr-secondary);font-size:.95rem;font-weight:400;transition:top .16s ease,left .16s ease,font-size .16s ease,color .16s ease,background-color .16s ease}
#roleModal .role-floating-field.has-value>.form-label,#roleModal .role-floating-field:focus-within>.form-label{top:.42rem;left:1rem;padding:0 .45rem;background:#fff;color:#5b4bd1;font-size:.72rem;font-weight:700}
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('#roleModal .role-floating-field').forEach(field => {
        const control = field.querySelector('.form-control, .form-select');
        if (!control) return;
        const sync = () => field.classList.toggle('has-value', String(control.value || '').trim() !== '');
        control.addEventListener('input', sync);
        control.addEventListener('change', sync);
        sync();
    });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const select = document.querySelector('#roleModal select[name="department_id"]');
    const field = select?.closest('.role-floating-field');
    if (!select || !field || field.dataset.departmentComboboxReady) return;
    field.dataset.departmentComboboxReady = '1';
    select.classList.add('d-none');
    const combo = document.createElement('div');
    combo.className = 'role-location-combobox';
    combo.innerHTML = '<button type="button" class="role-location-combobox-toggle"><span class="role-location-combobox-selected"></span><i class="ti ti-chevron-down"></i></button><div class="role-location-combobox-menu d-none"><input type="search" class="form-control" placeholder="Search Department"><div class="role-location-combobox-results"></div></div>';
    select.after(combo);
    const button = combo.querySelector('button');
    const menu = combo.querySelector('.role-location-combobox-menu');
    const search = combo.querySelector('input');
    const selected = combo.querySelector('.role-location-combobox-selected');
    const results = combo.querySelector('.role-location-combobox-results');
    const sync = () => {
        selected.textContent = select.value ? (select.selectedOptions[0]?.textContent || '') : '';
        field.classList.toggle('has-value', Boolean(select.value));
    };
    const render = () => {
        const term = search.value.toLowerCase().trim();
        const options = [...select.options].filter(option => option.value && (!term || option.textContent.toLowerCase().includes(term)));
        results.innerHTML = options.length ? options.map(option => `<button type="button" class="role-location-combobox-option" data-value="${option.value}">${option.textContent}</button>`).join('') : '<div class="text-secondary px-2 py-2">No departments found</div>';
    };
    button.addEventListener('click', () => { menu.classList.toggle('d-none'); if (!menu.classList.contains('d-none')) { search.value = ''; render(); search.focus(); } });
    search.addEventListener('input', render);
    results.addEventListener('click', event => { const option = event.target.closest('[data-value]'); if (!option) return; select.value = option.dataset.value; select.dispatchEvent(new Event('change', {bubbles:true})); menu.classList.add('d-none'); sync(); });
    document.addEventListener('click', event => { if (!combo.contains(event.target)) menu.classList.add('d-none'); });
    sync();
});
</script>
<style>
#roleModal .role-location-combobox{position:relative}
#roleModal .role-location-combobox-toggle{width:100%;height:52px;padding:1.25rem 2.75rem .35rem 1rem;border:1.5px solid #dfe3ea;border-radius:14px;background:#fff;box-shadow:0 2px 7px rgba(31,41,55,.04);text-align:left;color:var(--tblr-body-color);font:inherit;position:relative}
#roleModal .role-location-combobox-toggle:focus{outline:0;border-color:#6b5bd6;box-shadow:0 0 0 3px rgba(107,91,214,.14)}
#roleModal .role-location-combobox-toggle>i{position:absolute;right:1rem;top:50%;transform:translateY(-50%);color:var(--tblr-secondary)}
#roleModal .role-location-combobox-menu{position:absolute;z-index:20;left:0;right:0;top:calc(100% + .35rem);padding:.65rem;background:#fff;border:1px solid #dfe3ea;border-radius:14px;box-shadow:0 12px 28px rgba(31,41,55,.16)}
#roleModal .role-location-combobox-menu .form-control{height:44px;border-radius:10px}
#roleModal .role-location-combobox-results{max-height:220px;overflow-y:auto;margin-top:.5rem}
#roleModal .role-location-combobox-option{display:block;width:100%;border:0;background:transparent;text-align:left;padding:.65rem .75rem;border-radius:9px;color:var(--tblr-body-color)}
#roleModal .role-location-combobox-option:hover{background:#f1f3f5}
</style>
<style>
[data-bs-theme="dark"] #roleModal .role-floating-field>.form-label{background:transparent;color:var(--tblr-secondary-color,var(--tblr-secondary))}
[data-bs-theme="dark"] #roleModal .role-floating-field.has-value>.form-label,[data-bs-theme="dark"] #roleModal .role-floating-field:focus-within>.form-label{background:var(--tblr-bg-surface,var(--tblr-bg-forms));color:#a99cff}
[data-bs-theme="dark"] #roleModal .role-floating-field>.form-control,[data-bs-theme="dark"] #roleModal .role-floating-field>.form-select,[data-bs-theme="dark"] #roleModal .role-location-combobox-toggle{background:var(--tblr-bg-forms,#1e293b)!important;color:var(--tblr-body-color)!important;border-color:var(--tblr-border-color)!important}
[data-bs-theme="dark"] #roleModal .role-floating-field>.form-control:focus,[data-bs-theme="dark"] #roleModal .role-floating-field>.form-select:focus,[data-bs-theme="dark"] #roleModal .role-location-combobox-toggle:focus{border-color:#8b7cf6!important;box-shadow:0 0 0 3px rgba(139,124,246,.2)!important}
[data-bs-theme="dark"] #roleModal .role-location-combobox-menu{background:var(--tblr-bg-surface,#1e293b);border-color:var(--tblr-border-color);box-shadow:0 12px 28px rgba(0,0,0,.4)}
[data-bs-theme="dark"] #roleModal .role-location-combobox-option{color:var(--tblr-body-color)}
[data-bs-theme="dark"] #roleModal .role-location-combobox-option:hover{background:rgba(255,255,255,.08)}
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const select = document.querySelector('#roleModal select[name="status"]');
    const field = select?.closest('.role-floating-field');
    if (!select || !field || field.dataset.statusToggleReady) return;
    field.dataset.statusToggleReady = '1';
    select.classList.add('d-none');
    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'status-toggle';
    toggle.innerHTML = '<span class="status-toggle-label"></span><span class="status-toggle-knob"></span>';
    select.after(toggle);
    field.querySelector('.form-label')?.remove();
    const globalCheck = document.querySelector('#roleModal input[name="is_global"]')?.closest('.form-check');
    if (globalCheck) {
        const settingsRow = document.createElement('div');
        settingsRow.className = 'role-settings-row';
        field.parentNode.insertBefore(settingsRow, globalCheck);
        settingsRow.append(globalCheck, toggle, select);
        field.remove();
    }
    const sync = () => {
        const active = select.value === '1';
        toggle.classList.toggle('is-active', active);
        toggle.querySelector('.status-toggle-label').textContent = active ? 'ON' : 'OFF';
        toggle.setAttribute('aria-pressed', active ? 'true' : 'false');
        field.classList.toggle('has-value', true);
    };
    toggle.addEventListener('click', () => { select.value = select.value === '1' ? '0' : '1'; sync(); });
    sync();
});
</script>
<style>
#roleModal .role-settings-row{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1rem;min-height:52px}
#roleModal .role-settings-row .form-check{margin:0!important}
</style>
@php($roleStatus = old('status', $editRole?->status ?? 1))
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="card"><div class="card-header"><h3 class="card-title">Role Lists</h3></div><form method="GET"><div class="card-body border-bottom py-3"><div class="row justify-content-between"><div class="col-auto text-secondary">Show <select class="form-select form-select-sm d-inline-block w-auto mx-2" name="per_page" onchange="this.form.submit()"><option value="10" @selected(request('per_page',10)==10)>10 / page</option><option value="25" @selected(request('per_page')==25)>25 / page</option><option value="50" @selected(request('per_page')==50)>50 / page</option></select> entries</div><div class="col-auto input-icon"><span class="input-icon-addon"><i class="ti ti-search"></i></span><input class="form-control form-control-sm" name="search" value="{{ request('search') }}" placeholder="Search roles"></div></div></div></form><div class="table-responsive table-vcenter text-nowrap"><table class="table card-table"><thead><tr><th>No.</th><th>Role</th><th>Code</th><th>Department</th><th>Scope</th><th>Status</th><th class="text-center">Actions</th></tr></thead><tbody>@forelse($roles as $role)<tr><td>{{ $roles->firstItem()+$loop->index }}</td><td>{{ $role->name }}</td><td>{{ $role->code }}</td><td>{{ $role->department?->name??'—' }}</td><td>{{ $role->is_global?'Global':'Campus' }}</td><td><span class="badge bg-{{ $role->status?'success':'secondary' }}">{{ $role->status?'Active':'Inactive' }}</span></td><td class="text-center"><a class="btn btn-sm btn-outline-primary" href="{{ route('roles.index',['edit'=>$role->id]) }}"><i class="ti ti-edit"></i></a><form class="d-inline" method="POST" action="{{ route('roles.delete',$role) }}" onsubmit="return confirm('Delete this role?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" @disabled($role->code==='super-admin')><i class="ti ti-trash"></i></button></form></td></tr>@empty<tr><td colspan="7" class="text-center text-secondary py-4">No roles found.</td></tr>@endforelse</tbody></table></div><div class="card-footer">@include('partials.admin-pagination',['paginator'=>$roles])</div></div>
<div class="modal modal-blur fade" id="roleModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="POST" action="{{ route('roles.save') }}">@csrf<div class="modal-header"><h5 class="modal-title">{{ $editRole?'Edit Role':'Create Role' }}</h5><a class="btn-close" href="{{ route('roles.index') }}"></a></div><div class="modal-body"><input type="hidden" name="role_id" value="{{ $editRole?->id }}"><label class="form-label">Role Name</label><input class="form-control mb-3" name="name" value="{{ old('name',$editRole?->name) }}" required><label class="form-label">Code</label><input class="form-control mb-3" name="code" value="{{ old('code',$editRole?->code) }}" required><label class="form-label">Description</label><textarea class="form-control mb-3" name="description">{{ old('description',$editRole?->description) }}</textarea><label class="form-label">Department</label><select class="form-select mb-3" name="department_id"><option value="">No Department</option>@foreach($departments as $d)<option value="{{ $d->id }}" @selected(old('department_id',$editRole?->department_id)==$d->id)>{{ $d->name }}</option>@endforeach</select><label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_global" value="1" @checked(old('is_global',$editRole?->is_global))><span class="form-check-label">Global role</span></label><label class="form-label">Status</label><select class="form-select" name="status"><option value="1" @selected((string)$roleStatus==='1')>Active</option><option value="0" @selected((string)$roleStatus==='0')>Inactive</option></select></div><div class="modal-footer"><a class="btn me-auto" href="{{ route('roles.index') }}">Cancel</a><button class="btn btn-primary">{{ $editRole?'Update':'Create' }}</button></div></form></div></div></div><script>document.addEventListener('DOMContentLoaded',()=>{const m=document.getElementById('roleModal');document.getElementById('btnNewRole')?.addEventListener('click',()=>new bootstrap.Modal(m).show());@if($editRole)new bootstrap.Modal(m).show();@endif});</script>
@endsection
