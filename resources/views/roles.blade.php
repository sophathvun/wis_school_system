@extends('layouts.app')
@section('title', 'Roles')

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
            const sync = () => field.classList.toggle('has-value', String(control.value || '')
            .trim() !== '');
            control.addEventListener('input', sync);
            control.addEventListener('change', sync);
            sync();
        });
    });
</script>
@section('page-header')<div class="container-fluid">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">Administrator</div>
                <h2 class="page-title">Roles</h2>
            </div>
            <div class="col-auto">
                <div class="btn-list">
                    <a class="btn btn-outline-primary" href="{{ route('roles.print') }}" target="_blank" rel="noopener">
                        <i class="ti ti-printer icon"></i> Print
                    </a>
                    <a class="btn btn-outline-success" href="{{ route('roles.excel') }}">
                        <i class="ti ti-file-spreadsheet icon"></i> Excel
                    </a>
                    <button class="btn btn-primary" id="btnNewRole">
                        <i class="ti ti-plus icon"></i> New Role
                    </button>
                </div>
            </div>
        </div>
</div>@endsection
@section('content')


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
                const sync = () => field.classList.toggle('has-value', String(control.value || '')
                .trim() !== '');
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
            combo.innerHTML =
                '<button type="button" class="role-location-combobox-toggle"><span class="role-location-combobox-selected"></span><i class="ti ti-chevron-down"></i></button><div class="role-location-combobox-menu d-none"><input type="search" class="form-control" placeholder="Search Department"><div class="role-location-combobox-results"></div></div>';
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
                const options = [...select.options].filter(option => option.value && (!term || option
                    .textContent.toLowerCase().includes(term)));
                results.innerHTML = options.length ? options.map(option =>
                    `<button type="button" class="role-location-combobox-option" data-value="${option.value}">${option.textContent}</button>`
                    ).join('') : '<div class="text-secondary px-2 py-2">No departments found</div>';
            };
            button.addEventListener('click', () => {
                menu.classList.toggle('d-none');
                if (!menu.classList.contains('d-none')) {
                    search.value = '';
                    render();
                    search.focus();
                }
            });
            search.addEventListener('input', render);
            results.addEventListener('click', event => {
                const option = event.target.closest('[data-value]');
                if (!option) return;
                select.value = option.dataset.value;
                select.dispatchEvent(new Event('change', {
                    bubbles: true
                }));
                menu.classList.add('d-none');
                sync();
            });
            document.addEventListener('click', event => {
                if (!combo.contains(event.target)) menu.classList.add('d-none');
            });
            sync();
        });
    </script>


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
            const globalCheck = document.querySelector('#roleModal input[name="is_global"]')?.closest(
            '.form-check');
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
            toggle.addEventListener('click', () => {
                select.value = select.value === '1' ? '0' : '1';
                sync();
            });
            sync();
        });
    </script>

    @php
        $roleStatus = old('status', $editRole?->status ?? 1);
        $roleSortBy = $sortBy ?? request('sortBy', 'name');
        $roleSortDir = $sortDir ?? request('sortDir', 'asc');
        $sortUrl = function (string $field) use ($roleSortBy, $roleSortDir) {
            $nextDir = $roleSortBy === $field && $roleSortDir === 'asc' ? 'desc' : 'asc';

            return request()->fullUrlWithQuery([
                'sortBy' => $field,
                'sortDir' => $nextDir,
                'page' => 1,
            ]);
        };
        $sortIcon = function (string $field) use ($roleSortBy, $roleSortDir) {
            if ($roleSortBy !== $field) {
                return '↕';
            }

            return $roleSortDir === 'asc' ? '↑' : '↓';
        };
    @endphp
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
        @endif @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Role Lists</h3>
            </div>
            <form method="GET">
                <div class="card-body border-bottom py-3">
                    <input type="hidden" name="per_page" value="{{ request('per_page', 10) }}">
                    <input type="hidden" name="sortBy" value="{{ $roleSortBy }}">
                    <input type="hidden" name="sortDir" value="{{ $roleSortDir }}">
                    <div class="row justify-content-end">
                        <div class="col-auto input-icon">
                            <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                            <input class="form-control form-control-sm" name="search" value="{{ request('search') }}"
                                placeholder="Search roles">
                        </div>
                    </div>
                </div>
            </form>
            <div class="table-responsive table-vcenter text-nowrap">
                <table class="table card-table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>
                                <a class="table-sort-button text-uppercase" href="{{ $sortUrl('name') }}">
                                    Role {{ $sortIcon('name') }}
                                </a>
                            </th>
                            <th>
                                <a class="table-sort-button text-uppercase" href="{{ $sortUrl('code') }}">
                                    Code {{ $sortIcon('code') }}
                                </a>
                            </th>
                            <th>
                                <a class="table-sort-button text-uppercase" href="{{ $sortUrl('department') }}">
                                    Department {{ $sortIcon('department') }}
                                </a>
                            </th>
                            <th>
                                <a class="table-sort-button text-uppercase" href="{{ $sortUrl('scope') }}">
                                    Scope {{ $sortIcon('scope') }}
                                </a>
                            </th>
                            <th>
                                <a class="table-sort-button text-uppercase" href="{{ $sortUrl('status') }}">
                                    Status {{ $sortIcon('status') }}
                                </a>
                            </th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($roles as $role)
                            @php
                                $cannotDelete = $role->code === 'super-admin' || $role->permissions_count > 0 || $role->users_count > 0;
                                $deleteTooltip = $role->code === 'super-admin'
                                    ? 'Cannot delete: Super Administrator role is protected.'
                                    : ($cannotDelete
                                        ? "Cannot delete: linked to {$role->permissions_count} permission(s) and {$role->users_count} user(s)."
                                        : 'Delete this role');
                            @endphp
                            <tr>
                                <td>{{ $roles->firstItem() + $loop->index }}</td>
                                <td>{{ $role->name }}</td>
                                <td>{{ $role->code }}</td>
                                <td>{{ $role->department?->name ?? '—' }}</td>
                                <td>{{ $role->is_global ? 'Global' : 'Campus' }}</td>
                                <td><span
                                        class="badge bg-{{ $role->status ? 'success' : 'secondary' }}">{{ $role->status ? 'Active' : 'Inactive' }}</span>
                                </td>
                                <td class="text-center"><a class="btn btn-sm btn-outline-primary"
                                        href="{{ route('roles.index', ['edit' => $role->id]) }}"><i
                                            class="ti ti-edit"></i></a>
                                    <span data-bs-toggle="tooltip" title="{{ $deleteTooltip }}">
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                            @disabled($cannotDelete)
                                            onclick="this.closest('span').querySelector('form')?.requestSubmit()"><i
                                                class="ti ti-trash"></i></button>
                                        <form class="d-none" method="POST" action="{{ route('roles.delete', $role) }}"
                                            onsubmit="return confirm('Delete this role?')">@csrf @method('DELETE')</form>
                                    </span>
                                </td>
                        </tr>@empty<tr>
                                <td colspan="7" class="text-center text-secondary py-4">No roles found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">@include('partials.admin-pagination', ['paginator' => $roles])</div>
        </div>
        <div class="modal modal-blur fade" id="roleModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('roles.save') }}">@csrf<div class="modal-header">
                            <h5 class="modal-title">{{ $editRole ? 'Edit Role' : 'Create Role' }}</h5><a class="btn-close"
                                href="{{ route('roles.index') }}"></a>
                        </div>
                        <div class="modal-body"><input type="hidden" name="role_id" value="{{ $editRole?->id }}"><label
                                class="form-label">Role Name</label><input class="form-control mb-3" name="name"
                                value="{{ old('name', $editRole?->name) }}" required><label
                                class="form-label">Code</label><input class="form-control mb-3" name="code"
                                value="{{ old('code', $editRole?->code) }}" required><label
                                class="form-label">Description</label>
                            <textarea class="form-control mb-3" name="description">{{ old('description', $editRole?->description) }}</textarea><label class="form-label">Department</label><select
                                class="form-select mb-3" name="department_id">
                                <option value="">No Department</option>
                                @foreach ($departments as $d)
                                    <option value="{{ $d->id }}" @selected(old('department_id', $editRole?->department_id) == $d->id)>{{ $d->name }}
                                    </option>
                                @endforeach
                            </select><label class="form-check mb-3"><input class="form-check-input" type="checkbox"
                                    name="is_global" value="1" @checked(old('is_global', $editRole?->is_global))><span
                                    class="form-check-label">Global role</span></label><label
                                class="form-label">Status</label><select class="form-select" name="status">
                                <option value="1" @selected((string) $roleStatus === '1')>Active</option>
                                <option value="0" @selected((string) $roleStatus === '0')>Inactive</option>
                            </select>
                        </div>
                        <div class="modal-footer"><a class="btn me-auto"
                                href="{{ route('roles.index') }}">Cancel</a><button
                                class="btn btn-primary">{{ $editRole ? 'Update' : 'Create' }}</button></div>
                    </form>
                </div>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const m = document.getElementById('roleModal');
                document.getElementById('btnNewRole')?.addEventListener('click', () => new bootstrap.Modal(m).show());
                document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(element => new bootstrap.Tooltip(element));
                @if ($editRole)
                    new bootstrap.Modal(m).show();
                @endif
            });
        </script>
    @vite('resources/css/pages/roles.css')
@endsection
