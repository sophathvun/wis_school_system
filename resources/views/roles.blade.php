@extends('layouts.app')
@section('title', 'Roles')
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
                return '<span class="table-sort-icon" aria-hidden="true">&varr;</span>';
            }

            return $roleSortDir === 'asc' ? '<span class="table-sort-icon" aria-hidden="true">&uarr;</span>' : '<span class="table-sort-icon" aria-hidden="true">&darr;</span>';
        };
    @endphp
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
        @endif @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif
        <div class="card roles-list-card">
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
                                    Role {!! $sortIcon('name') !!}
                                </a>
                            </th>
                            <th>
                                <a class="table-sort-button text-uppercase" href="{{ $sortUrl('code') }}">
                                    Code {!! $sortIcon('code') !!}
                                </a>
                            </th>
                            <th>
                                <a class="table-sort-button text-uppercase" href="{{ $sortUrl('department') }}">
                                    Department {!! $sortIcon('department') !!}
                                </a>
                            </th>
                            <th>
                                <a class="table-sort-button text-uppercase" href="{{ $sortUrl('scope') }}">
                                    Scope {!! $sortIcon('scope') !!}
                                </a>
                            </th>
                            <th>
                                <a class="table-sort-button text-uppercase" href="{{ $sortUrl('status') }}">
                                    Status {!! $sortIcon('status') !!}
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
                                            @disabled($cannotDelete) data-role-delete-trigger><i
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
        <div class="modal modal-blur fade" id="roleModal" tabindex="-1" data-open-on-load="{{ $editRole ? '1' : '0' }}">
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
        @vite('resources/js/roles.js')
    @vite('resources/css/pages/roles.css')
@endsection


