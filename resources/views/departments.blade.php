@extends('layouts.app')
@section('title', 'Departments')
@section('page-header')
    <div class="container-fluid">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">Administrator</div>
                <h2 class="page-title">Departments</h2>
            </div>
            <div class="col-auto">
                <div class="btn-list">
                    <a class="btn btn-outline-primary" href="{{ route('departments.print') }}" target="_blank"
                        rel="noopener">
                        <i class="ti ti-printer icon"></i> Print
                    </a>
                    <a class="btn btn-outline-success" href="{{ route('departments.excel') }}">
                        <i class="ti ti-file-spreadsheet icon"></i> Excel
                    </a>
                    <button class="btn btn-primary" id="btnNewDepartment">
                        <i class="ti ti-plus icon"></i> New Department
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('content')

    @php
        $departmentStatus = old('status', $editDepartment?->status ?? 1);
        $departmentSortBy = $sortBy ?? request('sortBy', 'name');
        $departmentSortDir = $sortDir ?? request('sortDir', 'asc');
        $sortUrl = function (string $field) use ($departmentSortBy, $departmentSortDir) {
            $nextDir = $departmentSortBy === $field && $departmentSortDir === 'asc' ? 'desc' : 'asc';

            return request()->fullUrlWithQuery([
                'sortBy' => $field,
                'sortDir' => $nextDir,
                'page' => 1,
            ]);
        };
        $sortIcon = function (string $field) use ($departmentSortBy, $departmentSortDir) {
            if ($departmentSortBy !== $field) {
                return '<span class="table-sort-icon" aria-hidden="true">&varr;</span>';
            }

            return $departmentSortDir === 'asc' ? '<span class="table-sort-icon" aria-hidden="true">&uarr;</span>' : '<span class="table-sort-icon" aria-hidden="true">&darr;</span>';
        };
    @endphp

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

        <div class="card departments-list-card">
            <div class="card-header">
                <h3 class="card-title">Department Lists</h3>
            </div>
            <form method="GET">
                <div class="card-body border-bottom py-3">
                    <input type="hidden" name="per_page" value="{{ request('per_page', 10) }}">
                    <input type="hidden" name="sortBy" value="{{ $departmentSortBy }}">
                    <input type="hidden" name="sortDir" value="{{ $departmentSortDir }}">
                    <div class="row justify-content-end">
                        <div class="col-auto input-icon">
                            <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                            <input class="form-control form-control-sm" name="search" value="{{ request('search') }}"
                                placeholder="Search departments">
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
                                    Department {!! $sortIcon('name') !!}
                                </a>
                            </th>
                            <th>
                                <a class="table-sort-button text-uppercase" href="{{ $sortUrl('code') }}">
                                    Code {!! $sortIcon('code') !!}
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
                        @forelse($departments as $department)
                            @php
                                $linkedCount = $department->permissions_count + $department->roles_count + $department->users_count;
                                $cannotDelete = $linkedCount > 0;
                                $deleteTooltip = $cannotDelete
                                    ? "Cannot delete: linked to {$department->permissions_count} permission(s), {$department->roles_count} role(s), and {$department->users_count} user(s)."
                                    : 'Delete this department';
                            @endphp
                            <tr>
                                <td>{{ $departments->firstItem() + $loop->index }}</td>
                                <td>{{ $department->name }}</td>
                                <td>{{ $department->code }}</td>
                                <td><span
                                        class="badge bg-{{ $department->status ? 'success' : 'secondary' }}">{{ $department->status ? 'Active' : 'Inactive' }}</span>
                                </td>
                                <td class="text-center"><a class="btn btn-sm btn-outline-primary"
                                        href="{{ route('departments.index', ['edit' => $department->id]) }}"><i
                                            class="ti ti-edit"></i></a>
                                    <span data-bs-toggle="tooltip" title="{{ $deleteTooltip }}">
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                            @disabled($cannotDelete) data-department-delete-trigger><i
                                                class="ti ti-trash"></i></button>
                                        <form class="d-none" method="POST"
                                            action="{{ route('departments.delete', $department) }}"
                                            onsubmit="return confirm('Delete this department?')">@csrf @method('DELETE')
                                        </form>
                                    </span>
                                </td>
                        </tr>@empty<tr>
                                <td colspan="5" class="text-center text-secondary py-4">No departments found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">@include('partials.admin-pagination', ['paginator' => $departments])</div>
        </div>
        <div class="modal modal-blur fade" id="departmentModal" tabindex="-1" data-auto-open="{{ $editDepartment ? 'true' : 'false' }}">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('departments.save') }}">@csrf<div class="modal-header">
                            <h5 class="modal-title">{{ $editDepartment ? 'Edit Department' : 'Create Department' }}</h5><a
                                class="btn-close" href="{{ route('departments.index') }}"></a>
                        </div>
                        <div class="modal-body"><input type="hidden" name="department_id"
                                value="{{ $editDepartment?->id }}">
                            <div class="department-field"><label class="form-label">Department Name</label><input
                                    class="form-control" name="name" value="{{ old('name', $editDepartment?->name) }}"
                                    required></div>
                            <div class="department-field"><label class="form-label">Code</label><input class="form-control"
                                    name="code" value="{{ old('code', $editDepartment?->code) }}" required></div>
                            <div class="department-status-row"><select class="d-none" name="status">
                                    <option value="1" @selected((string) $departmentStatus === '1')>Active</option>
                                    <option value="0" @selected((string) $departmentStatus === '0')>Inactive</option>
                                </select><button type="button"
                                    class="status-toggle {{ $departmentStatus ? 'is-active' : '' }}"
                                    id="departmentStatusToggle"
                                    aria-pressed="{{ $departmentStatus ? 'true' : 'false' }}"><span
                                        class="status-toggle-label">{{ $departmentStatus ? 'ON' : 'OFF' }}</span><span
                                        class="status-toggle-knob"></span></button></div>
                        </div>
                        <div class="modal-footer"><a class="btn me-auto"
                                href="{{ route('departments.index') }}">Cancel</a><button
                                class="btn btn-primary">{{ $editDepartment ? 'Update' : 'Create' }}</button></div>
                    </form>
                </div>
            </div>
        </div>
        @vite('resources/js/departments.js')
    @vite('resources/css/pages/departments.css')
@endsection


