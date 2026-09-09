@extends('layouts.app')

@section('title', 'Roles and Permissions')

@section('content')
    @vite('resources/css/pages/access-management.css')
    @vite('resources/css/pages/partials-permission-tree.css')

    <div class="page-header">
        <div class="row align-items-center">
            <div class="col">
                <h2>Administrator Access</h2>
                <div class="text-secondary">Main menus control submenus, and submenus control their actions.</div>
            </div>
            <div class="col-auto">
                <div class="btn-list">
                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" type="button">
                            <i class="ti ti-printer icon"></i> Print
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="{{ route('access-management.reports.print', 'permissions') }}" target="_blank" rel="noopener"><i class="ti ti-list-check me-2"></i>Permission List</a>
                            <a class="dropdown-item" href="{{ route('access-management.reports.print', 'departments') }}" target="_blank" rel="noopener"><i class="ti ti-building-community me-2"></i>Permission by Department</a>
                            <a class="dropdown-item" href="{{ route('access-management.reports.print', 'roles') }}" target="_blank" rel="noopener"><i class="ti ti-shield-check me-2"></i>Permission by Role</a>
                            <a class="dropdown-item" href="{{ route('access-management.reports.print', 'users') }}" target="_blank" rel="noopener"><i class="ti ti-user-check me-2"></i>Permission by User</a>
                        </div>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-outline-success dropdown-toggle" data-bs-toggle="dropdown" type="button">
                            <i class="ti ti-file-spreadsheet icon"></i> Excel
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="{{ route('access-management.reports.excel', 'permissions') }}"><i class="ti ti-list-check me-2"></i>Permission List</a>
                            <a class="dropdown-item" href="{{ route('access-management.reports.excel', 'departments') }}"><i class="ti ti-building-community me-2"></i>Permission by Department</a>
                            <a class="dropdown-item" href="{{ route('access-management.reports.excel', 'roles') }}"><i class="ti ti-shield-check me-2"></i>Permission by Role</a>
                            <a class="dropdown-item" href="{{ route('access-management.reports.excel', 'users') }}"><i class="ti ti-user-check me-2"></i>Permission by User</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @php
        $permissionTableSortBy = $permissionSortBy ?? request('permissionSortBy', 'module');
        $permissionTableSortDir = $permissionSortDir ?? request('permissionSortDir', 'asc');
        $permissionSortUrl = function (string $field) use ($permissionTableSortBy, $permissionTableSortDir) {
            $nextDir = $permissionTableSortBy === $field && $permissionTableSortDir === 'asc' ? 'desc' : 'asc';

            return request()->fullUrlWithQuery([
                'permissionSortBy' => $field,
                'permissionSortDir' => $nextDir,
                'permission_page' => 1,
            ]) . '#permission-list-tab';
        };
        $permissionSortIcon = function (string $field) use ($permissionTableSortBy, $permissionTableSortDir) {
            if ($permissionTableSortBy !== $field) {
                return '<span class="table-sort-icon" aria-hidden="true">&varr;</span>';
            }

            return $permissionTableSortDir === 'asc' ? '<span class="table-sort-icon" aria-hidden="true">&uarr;</span>' : '<span class="table-sort-icon" aria-hidden="true">&darr;</span>';
        };
    @endphp

    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">Create Permission</h3>
        </div>
        <form method="POST" action="{{ route('access-management.permissions.save') }}">@csrf<div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3"><label class="form-label">Permission Name</label><input class="form-control"
                            name="name" placeholder="Approve enrollment" required></div>
                    <div class="col-md-3"><label class="form-label">Code</label><input class="form-control" name="code"
                            placeholder="enrollment.approve" required></div>
                    <div class="col-md-3"><label class="form-label">Module</label><input class="form-control" name="module"
                            placeholder="enrollment" required></div>
                    <div class="col-md-3"><label class="form-label">Action</label><input class="form-control" name="action"
                            placeholder="approve" required></div>
                </div>
            </div>
            <div class="card-footer text-end"><button class="btn btn-primary">Create Permission</button></div>
        </form>
    </div>

    <div class="card card-tabs access-management-tabs-card">
        <div class="card-header">
            <ul class="nav nav-tabs nav-tabs-lifted card-header-tabs" data-bs-toggle="tabs">
                <li class="nav-item"><a href="#users-list-tab" class="nav-link active" data-bs-toggle="tab"><i
                            class="ti ti-users me-2"></i>Users</a></li>
                <li class="nav-item"><a href="#permission-list-tab" class="nav-link" data-bs-toggle="tab"><i
                            class="ti ti-list-check me-2"></i>Permission List</a></li>
                <li class="nav-item"><a href="#role-permissions-tab" class="nav-link" data-bs-toggle="tab"><i
                            class="ti ti-shield me-2"></i>Permissions by Role</a></li>
                <li class="nav-item"><a href="#department-permissions-tab" class="nav-link" data-bs-toggle="tab"><i
                            class="ti ti-building-community me-2"></i>Permissions by Dept.</a></li>
            </ul>
        </div>
        <div class="tab-content">

            <div class="tab-pane" id="permission-list-tab">
                <div class="card-body border-bottom py-3">
                    <form method="GET" class="row g-2 align-items-center">
                        <input type="hidden" name="permission_page" value="1">
                        <input type="hidden" name="permissionSortBy" value="{{ $permissionTableSortBy }}">
                        <input type="hidden" name="permissionSortDir" value="{{ $permissionTableSortDir }}">
                        <div class="col-auto text-secondary">Permission List</div>
                        <div class="col-md-3 col-12 ms-md-auto">
                            <div class="input-icon">
                                <span class="input-icon-addon"><i class="ti ti-search icon"></i></span>
                                <input type="search" name="permission_search" value="{{ $permissionSearch }}"
                                    class="form-control" placeholder="Search permissions"
                                    aria-label="Search permissions" data-access-live-search>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="table-responsive table-vcenter text-nowrap">
                    <table class="table card-table" data-user-emails='@json($userList->pluck("email")->values())'>
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>
                                    <a class="table-sort-button text-uppercase" href="{{ $permissionSortUrl('module') }}">
                                        Module {!! $permissionSortIcon('module') !!}
                                    </a>
                                </th>
                                <th>
                                    <a class="table-sort-button text-uppercase" href="{{ $permissionSortUrl('code') }}">
                                        Permission Code {!! $permissionSortIcon('code') !!}
                                    </a>
                                </th>
                                <th>
                                    <a class="table-sort-button text-uppercase" href="{{ $permissionSortUrl('name') }}">
                                        Permission Name {!! $permissionSortIcon('name') !!}
                                    </a>
                                </th>
                                <th>
                                    <a class="table-sort-button text-uppercase" href="{{ $permissionSortUrl('action') }}">
                                        Action {!! $permissionSortIcon('action') !!}
                                    </a>
                                </th>
                                <th>Linked Data</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($permissionRows as $permission)
                                @php
                                    $linkedCount = $permission->roles_count + $permission->departments_count + $permission->users_count;
                                    $cannotDelete = $linkedCount > 0;
                                    $deleteTooltip = $cannotDelete
                                        ? "Cannot delete: linked to {$permission->roles_count} role(s), {$permission->departments_count} department(s), and {$permission->users_count} user override(s)."
                                        : 'Delete this permission';
                                @endphp
                                <tr>
                                    <td>{{ $permissionRows->firstItem() + $loop->index }}</td>
                                    <td>{{ $permission->module ?: '-' }}</td>
                                    <td>{{ $permission->code ?: '-' }}</td>
                                    <td>{{ $permission->name ?: '-' }}</td>
                                    <td>{{ $permission->action ?: '-' }}</td>
                                    <td>
                                        <span class="badge permission-list-linked bg-{{ $cannotDelete ? 'primary' : 'secondary' }}">
                                            {{ $linkedCount }} linked
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span data-bs-toggle="tooltip" title="{{ $deleteTooltip }}">
                                            <button class="btn btn-sm btn-outline-danger" type="button"
                                                @disabled($cannotDelete)
                                                data-access-delete-trigger>
                                                <i class="ti ti-trash"></i>
                                            </button>
                                            <form class="d-none" method="POST"
                                                action="{{ route('access-management.permissions.delete', $permission) }}"
                                                onsubmit="return confirm('Delete this permission?')">
                                                @csrf
                                                @method('DELETE')
                                            </form>
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-secondary py-4">No permissions found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">@include('partials.admin-pagination', ['paginator' => $permissionRows])</div>
            </div>

            <div class="tab-pane active show" id="users-list-tab">
                <div class="card-body border-bottom py-3">
                    <form method="GET" class="row g-2 align-items-center"><input type="hidden" name="users_page"
                            value="1">
                        <div class="col-auto text-secondary">User List</div>
                        <div class="col-md-3 col-12 ms-md-auto">
                            <div class="input-icon"><span class="input-icon-addon"><i
                                        class="ti ti-search icon"></i></span><input type="search" name="user_search"
                                    value="{{ $userSearch }}" class="form-control"
                                    placeholder="Search users" aria-label="Search users" data-access-live-search></div>
                        </div>
                    </form>
                </div>
                <div class="table-responsive table-vcenter text-nowrap">
                    <table class="table card-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Staff Full Name</th>
                                <th>Username</th>
                                <th>Position</th>
                                <th>Campus Assign</th>
                                <th>Department</th>
                                <th>Roles</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($userList as $index => $listedUser)
                                @php($permissionRowId = 'user-permissions-row-' . $listedUser->id)
                                <tr>
                                    <td>{{ $userList->firstItem() + $index }}</td>
                                    <td>{{ $listedUser->name ?: '-' }}</td>
                                    <td>
                                        <div>{{ $listedUser->username }}</div><button type="button"
                                            class="btn btn-sm btn-link p-0"
                                            data-user-permissions-toggle="{{ $permissionRowId }}"><i
                                                class="ti ti-chevron-down me-1"></i><span
                                                class="user-permissions-toggle-label">Show Permissions</span></button>
                                    </td>
                                    <td>{{ $listedUser->is_global ? 'Global Administrator' : '-' }}</td>
                                    <td>{{ $listedUser->is_global ? 'All Campuses' : ($listedUser->campuses->pluck('campus_name_en')->join(', ') ?: '-') }}
                                    </td>
                                    <td>{{ $listedUser->department?->name ?: '-' }}</td>
                                    <td>{{ $listedUser->roles->pluck('name')->join(', ') ?: '-' }}</td>
                                    <td><button type="button"
                                            class="status-toggle {{ $listedUser->status ? 'is-active' : '' }}"
                                            data-status-toggle data-status-entity="user"
                                            data-status-id="{{ $listedUser->id }}"
                                            data-status="{{ $listedUser->status ? 1 : 0 }}"
                                            aria-pressed="{{ $listedUser->status ? 'true' : 'false' }}"><span
                                                class="status-toggle-label">{{ $listedUser->status ? 'ON' : 'OFF' }}</span><span
                                                class="status-toggle-knob"></span></button></td>
                                </tr>
                                <tr id="{{ $permissionRowId }}" class="d-none">
                                    <td colspan="8">
                                        <div class="card border shadow-sm my-2">
                                            <div class="card-header py-2"><span class="fw-semibold">Assign Permissions for
                                                    {{ $listedUser->username }}</span></div>
                                            <form method="POST" action="{{ route('access-management.staff.save') }}"
                                                data-permission-form>@csrf<input type="hidden" name="user_id"
                                                    value="{{ $listedUser->id }}">
                                                <div class="card-body py-3">
                                                    <div class="text-secondary small mb-3">Turn on the main menu, then its
                                                        submenu, before enabling actions.</div>@include('partials.permission-tree', [
                                                            'assignedPermissions' =>
                                                                $listedUser->permissionOverrides,
                                                            'permissionPrefix' => 'inline-user-' . $listedUser->id,
                                                            'fullAccess' => $listedUser->isSuperAdmin(),
                                                            'fullAccessLocked' => $listedUser->isSuperAdmin(),
                                                            'showCampusAssignment' => true,
                                                        ])
                                                </div>
                                                <div class="card-footer text-end"><button class="btn btn-primary">Save
                                                        User Permissions</button></div>
                                            </form>
                                        </div>
                                    </td>
                            </tr>@empty<tr>
                                    <td colspan="8" class="text-center text-secondary py-4">No users found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">@include('partials.permission-user-pagination')</div>
            </div>

            <div class="tab-pane" id="role-permissions-tab">
                <form method="GET">
                    <div class="card-body border-bottom"><label class="form-label">Select Role</label><select
                            class="form-select" name="role_id" data-access-auto-submit>
                            @foreach ($roles as $item)
                                <option value="{{ $item->id }}" @selected($role?->id === $item->id)>{{ $item->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </form>
                <form method="POST" action="{{ route('access-management.roles.save') }}" data-permission-form>
                    @csrf<input type="hidden" name="role_id" value="{{ $role?->id }}">
                    <div class="card-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6"><label class="form-label">Role Department</label><select
                                    name="department_id" class="form-select">
                                    <option value="">No Department</option>
                                    @foreach ($departments as $item)
                                        <option value="{{ $item->id }}" @selected($role?->department_id === $item->id)>
                                            {{ $item->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 d-flex align-items-end text-secondary small">Turn on a main menu first.
                                Then turn on its submenu before enabling actions.</div>
                        </div>@include('partials.permission-tree', [
                            'assignedPermissions' => $role?->permissions ?? collect(),
                            'permissionPrefix' => 'role',
                            'fullAccess' => $role?->code === 'super-admin',
                            'fullAccessLocked' => $role?->code === 'super-admin',
                        ])
                    </div>
                    <div class="card-footer text-end"><button class="btn btn-primary">Save Role Permissions</button></div>
                </form>
            </div>

            <div class="tab-pane" id="department-permissions-tab">
                <form method="GET">
                    <div class="card-body border-bottom">
                        <div class="permission-campus-picker permission-department-picker has-value" data-department-picker>
                            <button type="button" class="permission-campus-picker-toggle" data-department-picker-toggle aria-expanded="false">
                                <span class="permission-campus-picker-field-label">Select Department</span>
                                <span data-department-picker-label>{{ $department?->name }}</span><i class="ti ti-chevron-down"></i>
                            </button>
                            <div class="permission-campus-picker-menu d-none" data-department-picker-menu>
                                <div class="input-icon"><span class="input-icon-addon"><i class="ti ti-search"></i></span>
                                    <input type="search" class="form-control" placeholder="Search departments" data-department-picker-search>
                                </div>
                                <div class="permission-campus-picker-results" data-department-picker-results>
                                    @foreach ($departments as $item)
                                        <button type="button" class="permission-department-option" data-department-id="{{ $item->id }}">{{ $item->name }}</button>
                                    @endforeach
                                </div>
                            </div>
                            <input type="hidden" name="department_id" value="{{ $department?->id }}" data-department-picker-value>
                        </div>
                    </div>
                </form>
                <form method="POST" action="{{ route('access-management.departments.permissions.save') }}"
                    data-permission-form>@csrf<input type="hidden" name="department_id" value="{{ $department?->id }}">
                    <div class="card-body">
                        <div class="text-secondary small mb-3">Departments receive only the permissions enabled below.
                        </div>@include('partials.permission-tree', [
                            'assignedPermissions' => $department?->permissions ?? collect(),
                            'permissionPrefix' => 'department',
                            'fullAccess' => $departmentFullAccess,
                        ])
                    </div>
                    <div class="card-footer text-end"><button class="btn btn-primary">Save Department Permissions</button>
                    </div>
                </form>
            </div>

            {{-- User-specific permissions are managed from the Users tab. --}}
            @if (false)
            <div class="tab-pane" id="user-permissions-tab">
                <form method="GET">
                    <div class="card-body border-bottom"><label class="form-label">Select User</label><select
                            class="form-select" name="user_id" data-access-auto-submit>
                            @foreach ($users as $item)
                                <option value="{{ $item->id }}" @selected($selectedUser?->id === $item->id)>{{ $item->name }} â€”
                                    {{ $item->username }}</option>
                            @endforeach
                        </select></div>
                </form>
                <form method="POST" action="{{ route('access-management.staff.save') }}" data-permission-form>
                    @csrf<input type="hidden" name="user_id" value="{{ $selectedUser?->id }}">
                    <div class="card-body">
                        <div class="text-secondary small mb-3">User permissions are additional grants on top of role and
                            department permissions.</div>@include('partials.permission-tree', [
                                'assignedPermissions' => $selectedUser?->permissionOverrides ?? collect(),
                                'permissionPrefix' => 'user',
                                'fullAccess' => $userFullAccess,
                                'fullAccessLocked' => $selectedUser?->isSuperAdmin(),
                                'showCampusAssignment' => true,
                            ])
                    </div>
                    <div class="card-footer text-end"><button class="btn btn-primary">Save User Permissions</button></div>
                </form>
            </div>
            @endif

        </div>
    </div>
    @vite('resources/js/accessManagement.js')
@endsection



