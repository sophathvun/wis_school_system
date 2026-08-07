@extends('layouts.app')

@section('title', 'Roles and Permissions')

@section('content')
<style>
[data-bs-theme="dark"] .card-tabs .nav-tabs .nav-link{color:#94a3b8}
[data-bs-theme="dark"] .card-tabs .nav-tabs .nav-link.active{background:#1e293b;color:#f8fafc;border-color:#475569}
[data-bs-theme="dark"] .card-tabs .form-control,[data-bs-theme="dark"] .card-tabs .form-select,[data-bs-theme="dark"] .card.mb-3 .form-control,[data-bs-theme="dark"] .card.mb-3 .form-select{background:#1e293b!important;color:#f8fafc!important;border-color:#475569!important}
[data-bs-theme="dark"] .card-tabs select option,[data-bs-theme="dark"] .card.mb-3 select option{background:#1e293b;color:#f8fafc}
[data-bs-theme="dark"] .card-tabs .form-label,[data-bs-theme="dark"] .card.mb-3 .form-label{color:#f8fafc}
[data-bs-theme="dark"] .card-tabs .card-header,[data-bs-theme="dark"] .card-tabs .card-footer,[data-bs-theme="dark"] .card-tabs .border-bottom{border-color:#334155!important}
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const nav = document.querySelector('.card-tabs .nav-tabs');
    const content = document.querySelector('.card-tabs .tab-content');
    const departmentLink = nav?.querySelector('a[href="#department-permissions-tab"]')?.closest('.nav-item');
    const roleLink = nav?.querySelector('a[href="#role-permissions-tab"]')?.closest('.nav-item');
    const departmentPane = document.getElementById('department-permissions-tab');
    const rolePane = document.getElementById('role-permissions-tab');
    if (nav && departmentLink && roleLink) nav.insertBefore(departmentLink, roleLink);
    if (content && departmentPane && rolePane) content.insertBefore(departmentPane, rolePane);

    const setToggle = (button, active) => {
        button.classList.toggle('is-active', active);
        button.dataset.status = active ? '1' : '0';
        button.setAttribute('aria-pressed', String(active));
        button.querySelector('.status-toggle-label').textContent = active ? 'ON' : 'OFF';
        const input = button.querySelector('input[type="checkbox"]') || button.closest('[data-permission-group]')?.querySelector(`[data-permission-input="${button.dataset.permissionId}"]`);
        if (input) input.checked = active;
    };

    const refreshHierarchy = form => {
        const fullAccess = form.querySelector('[data-full-access]');
        if (fullAccess?.dataset.status === '1') {
            form.querySelectorAll('[data-permission-toggle]:not([data-full-access])').forEach(button => setToggle(button, true));
            form.querySelectorAll('[data-permission-toggle]:not([data-full-access])').forEach(button => { button.disabled = true; });
            return;
        }
        form.querySelectorAll('[data-permission-toggle][data-permission-level="main"]').forEach(main => {
            const mainOn = main.dataset.status === '1';
            form.querySelectorAll(`[data-parent-permission="${main.dataset.permissionId}"]`).forEach(child => {
                const childButton = child.matches('button') ? child : child.querySelector('[data-permission-toggle]');
                if (!childButton) return;
                childButton.disabled = !mainOn;
                if (!mainOn) setToggle(childButton, false);
            });
        });
        form.querySelectorAll('[data-permission-toggle][data-permission-level="submenu"]').forEach(submenu => {
            const submenuOn = submenu.dataset.status === '1';
            form.querySelectorAll(`[data-action-parent="${submenu.dataset.permissionId}"]`).forEach(action => {
                const actionButton = action.querySelector('[data-permission-toggle]');
                actionButton.disabled = !submenuOn || submenu.disabled;
                if (!submenuOn || submenu.disabled) setToggle(actionButton, false);
            });
        });
    };

    document.querySelectorAll('[data-permission-form]').forEach(form => {
        form.querySelectorAll('[data-permission-toggle]:not([data-full-access])').forEach(button => button.addEventListener('click', event => {
            event.preventDefault();
            if (button.disabled) return;
            setToggle(button, button.dataset.status !== '1');
            refreshHierarchy(form);
        }));
        refreshHierarchy(form);
        const fullAccess = form.querySelector('[data-full-access]');
        fullAccess?.addEventListener('click', event => {
            event.preventDefault();
            const enabled = fullAccess.dataset.status !== '1';
            setToggle(fullAccess, enabled);
            form.querySelectorAll('[data-permission-toggle]:not([data-full-access])').forEach(button => setToggle(button, enabled));
            refreshHierarchy(form);
        });
    });

    document.querySelectorAll('[data-user-permissions-toggle]').forEach(button => button.addEventListener('click', () => {
        const row = document.getElementById(button.dataset.userPermissionsToggle);
        const hidden = row.classList.toggle('d-none');
        button.querySelector('.user-permissions-toggle-label').textContent = hidden ? 'Show Permissions' : 'Hide Permissions';
        button.querySelector('i').className = hidden ? 'ti ti-chevron-down me-1' : 'ti ti-chevron-up me-1';
    }));

});
    const usersTable = document.querySelector('#users-list-tab table');
    if (usersTable && !usersTable.dataset.emailColumn && !usersTable.dataset.emailColumnRendered) {
        usersTable.dataset.emailColumn = '1';
        const header = usersTable.querySelector('thead tr');
        const usernameHeader = header?.children[2];
        if (usernameHeader) {
            const emailHeader = document.createElement('th');
            emailHeader.textContent = 'Email';
            header.insertBefore(emailHeader, usernameHeader.nextSibling);
        }
        const emails = @json($userList->pluck('email')->values());
        let userIndex = 0;
        usersTable.querySelectorAll('tbody tr').forEach(row => {
            if (row.id) {
                const detailCell = row.querySelector('td[colspan]');
                if (detailCell) detailCell.colSpan = 9;
                return;
            }
            if (row.children.length === 1) {
                row.children[0].colSpan = 9;
                return;
            }
            const usernameCell = row.children[2];
            if (!usernameCell) return;
            const emailCell = document.createElement('td');
            emailCell.textContent = emails[userIndex++] || '-';
            row.insertBefore(emailCell, usernameCell.nextSibling);
        });
    }
</script>

<div class="page-header"><div class="row align-items-center"><div class="col"><h2>Administrator Access</h2><div class="text-secondary">Main menus control submenus, and submenus control their actions.</div></div><div class="col-auto"><a class="btn btn-outline-primary me-2" href="{{ route('departments.index') }}">Departments</a><a class="btn btn-outline-primary" href="{{ route('roles.index') }}">Roles</a></div></div></div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="card mb-3"><div class="card-header"><h3 class="card-title">Create Permission</h3></div><form method="POST" action="{{ route('access-management.permissions.save') }}">@csrf<div class="card-body"><div class="row g-3"><div class="col-md-3"><label class="form-label">Permission Name</label><input class="form-control" name="name" placeholder="Approve enrollment" required></div><div class="col-md-3"><label class="form-label">Code</label><input class="form-control" name="code" placeholder="enrollment.approve" required></div><div class="col-md-3"><label class="form-label">Module</label><input class="form-control" name="module" placeholder="enrollment" required></div><div class="col-md-3"><label class="form-label">Action</label><input class="form-control" name="action" placeholder="approve" required></div></div></div><div class="card-footer text-end"><button class="btn btn-primary">Create Permission</button></div></form></div>

<div class="card card-tabs"><div class="card-header"><ul class="nav nav-tabs nav-tabs-lifted card-header-tabs" data-bs-toggle="tabs"><li class="nav-item"><a href="#users-list-tab" class="nav-link active" data-bs-toggle="tab"><i class="ti ti-users me-2"></i>Users</a></li><li class="nav-item"><a href="#role-permissions-tab" class="nav-link" data-bs-toggle="tab"><i class="ti ti-shield me-2"></i>Permissions by Role</a></li><li class="nav-item"><a href="#department-permissions-tab" class="nav-link" data-bs-toggle="tab"><i class="ti ti-building-community me-2"></i>Permissions by Department</a></li><li class="nav-item"><a href="#user-permissions-tab" class="nav-link" data-bs-toggle="tab"><i class="ti ti-user me-2"></i>Permissions by User</a></li></ul></div><div class="tab-content">

<div class="tab-pane active show" id="users-list-tab"><div class="card-body border-bottom py-3"><form method="GET" class="row g-2 align-items-center"><input type="hidden" name="users_page" value="1"><div class="col-auto text-secondary">User List</div><div class="col-auto ms-auto"><div class="input-icon"><span class="input-icon-addon"><i class="ti ti-search icon"></i></span><input type="search" name="user_search" value="{{ $userSearch }}" class="form-control form-control-sm" placeholder="Search users" aria-label="Search users"></div></div><div class="col-auto"><button class="btn btn-sm btn-primary">Search</button></div></form></div><div class="table-responsive table-vcenter text-nowrap"><table class="table card-table"><thead><tr><th>No.</th><th>Staff Full Name</th><th>Username</th><th>Position</th><th>Campus Assign</th><th>Department</th><th>Roles</th><th>Status</th></tr></thead><tbody>@forelse($userList as $index => $listedUser)@php($permissionRowId = 'user-permissions-row-'.$listedUser->id)<tr><td>{{ $userList->firstItem() + $index }}</td><td>{{ $listedUser->name ?: '-' }}</td><td><div>{{ $listedUser->username }}</div><button type="button" class="btn btn-sm btn-link p-0" data-user-permissions-toggle="{{ $permissionRowId }}"><i class="ti ti-chevron-down me-1"></i><span class="user-permissions-toggle-label">Show Permissions</span></button></td><td>{{ $listedUser->is_global ? 'Global Administrator' : '-' }}</td><td>{{ $listedUser->is_global ? 'All Campuses' : ($listedUser->campuses->pluck('campus_name_en')->join(', ') ?: '-') }}</td><td>{{ $listedUser->department?->name ?: '-' }}</td><td>{{ $listedUser->roles->pluck('name')->join(', ') ?: '-' }}</td><td><button type="button" class="status-toggle {{ $listedUser->status ? 'is-active' : '' }}" data-status-toggle data-status-entity="user" data-status-id="{{ $listedUser->id }}" data-status="{{ $listedUser->status ? 1 : 0 }}" aria-pressed="{{ $listedUser->status ? 'true' : 'false' }}"><span class="status-toggle-label">{{ $listedUser->status ? 'ON' : 'OFF' }}</span><span class="status-toggle-knob"></span></button></td></tr><tr id="{{ $permissionRowId }}" class="d-none"><td colspan="8"><div class="card border shadow-sm my-2"><div class="card-header py-2"><span class="fw-semibold">Assign Permissions for {{ $listedUser->username }}</span></div><form method="POST" action="{{ route('access-management.staff.save') }}" data-permission-form>@csrf<input type="hidden" name="user_id" value="{{ $listedUser->id }}"><div class="card-body py-3"><div class="text-secondary small mb-3">Turn on the main menu, then its submenu, before enabling actions.</div>@include('partials.permission-tree', ['assignedPermissions' => $listedUser->permissionOverrides, 'permissionPrefix' => 'inline-user-'.$listedUser->id, 'fullAccess' => $listedUser->isSuperAdmin(), 'fullAccessLocked' => $listedUser->isSuperAdmin()])</div><div class="card-footer text-end"><button class="btn btn-primary">Save User Permissions</button></div></form></div></td></tr>@empty<tr><td colspan="8" class="text-center text-secondary py-4">No users found.</td></tr>@endforelse</tbody></table></div><div class="card-footer">@include('partials.permission-user-pagination')</div></div>

<div class="tab-pane" id="role-permissions-tab"><form method="GET"><div class="card-body border-bottom"><label class="form-label">Select Role</label><select class="form-select" name="role_id" onchange="this.form.submit()">@foreach($roles as $item)<option value="{{ $item->id }}" @selected($role?->id === $item->id)>{{ $item->name }}</option>@endforeach</select></div></form><form method="POST" action="{{ route('access-management.roles.save') }}" data-permission-form>@csrf<input type="hidden" name="role_id" value="{{ $role?->id }}"><div class="card-body"><div class="row g-3 mb-3"><div class="col-md-6"><label class="form-label">Role Department</label><select name="department_id" class="form-select"><option value="">No Department</option>@foreach($departments as $item)<option value="{{ $item->id }}" @selected($role?->department_id === $item->id)>{{ $item->name }}</option>@endforeach</select></div><div class="col-md-6 d-flex align-items-end text-secondary small">Turn on a main menu first. Then turn on its submenu before enabling actions.</div></div>@include('partials.permission-tree', ['assignedPermissions' => $role?->permissions ?? collect(), 'permissionPrefix' => 'role', 'fullAccess' => $role?->code === 'super-admin', 'fullAccessLocked' => $role?->code === 'super-admin'])</div><div class="card-footer text-end"><button class="btn btn-primary">Save Role Permissions</button></div></form></div>

<div class="tab-pane" id="department-permissions-tab"><form method="GET"><div class="card-body border-bottom"><label class="form-label">Select Department</label><select class="form-select" name="department_id" onchange="this.form.submit()">@foreach($departments as $item)<option value="{{ $item->id }}" @selected($department?->id === $item->id)>{{ $item->name }}</option>@endforeach</select></div></form><form method="POST" action="{{ route('access-management.departments.permissions.save') }}" data-permission-form>@csrf<input type="hidden" name="department_id" value="{{ $department?->id }}"><div class="card-body"><div class="text-secondary small mb-3">Departments receive only the permissions enabled below.</div>@include('partials.permission-tree', ['assignedPermissions' => $department?->permissions ?? collect(), 'permissionPrefix' => 'department', 'fullAccess' => $departmentFullAccess])</div><div class="card-footer text-end"><button class="btn btn-primary">Save Department Permissions</button></div></form></div>

<div class="tab-pane" id="user-permissions-tab"><form method="GET"><div class="card-body border-bottom"><label class="form-label">Select User</label><select class="form-select" name="user_id" onchange="this.form.submit()">@foreach($users as $item)<option value="{{ $item->id }}" @selected($selectedUser?->id === $item->id)>{{ $item->name }} — {{ $item->username }}</option>@endforeach</select></div></form><form method="POST" action="{{ route('access-management.staff.save') }}" data-permission-form>@csrf<input type="hidden" name="user_id" value="{{ $selectedUser?->id }}"><div class="card-body"><div class="text-secondary small mb-3">User permissions are additional grants on top of role and department permissions.</div>@include('partials.permission-tree', ['assignedPermissions' => $selectedUser?->permissionOverrides ?? collect(), 'permissionPrefix' => 'user', 'fullAccess' => $userFullAccess, 'fullAccessLocked' => $selectedUser?->isSuperAdmin()])</div><div class="card-footer text-end"><button class="btn btn-primary">Save User Permissions</button></div></form></div>

</div></div>
<script>
(function () {
    const table = document.querySelector('#users-list-tab table');
    if (!table || table.dataset.emailColumnRendered) return;
    table.dataset.emailColumnRendered = '1';
    const header = table.querySelector('thead tr');
    const usernameHeader = header?.children[2];
    if (usernameHeader) {
        const emailHeader = document.createElement('th');
        emailHeader.textContent = 'Email';
        header.insertBefore(emailHeader, usernameHeader.nextSibling);
    }
    const emails = @json($userList->pluck('email')->values());
    let index = 0;
    table.querySelectorAll('tbody tr').forEach(row => {
        if (row.id) {
            row.querySelector('td[colspan]')?.setAttribute('colspan', '9');
            return;
        }
        if (row.children.length === 1) {
            row.children[0].setAttribute('colspan', '9');
            return;
        }
        const emailCell = document.createElement('td');
        emailCell.textContent = emails[index++] || '-';
        row.insertBefore(emailCell, row.children[3]);
    });
})();
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const table = document.querySelector('#users-list-tab table');
    const header = table?.querySelector('thead tr');
    const roleHeader = [...(header?.children || [])].find(cell => cell.textContent.trim() === 'Roles');
    if (!table || !roleHeader) return;
    const roleIndex = [...header.children].indexOf(roleHeader);
    table.querySelectorAll('tbody tr').forEach(row => {
        if (row.children.length === 1 || row.id) return;
        const cell = row.children[roleIndex];
        if (cell) cell.textContent = [...new Set(cell.textContent.split(',').map(role => role.trim()).filter(Boolean))].join(', ') || '-';
    });
});
</script>
@endsection
