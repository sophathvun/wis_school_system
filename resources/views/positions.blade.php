@extends('layouts.app')
@section('title', 'Positions')
@section('page-header')
    <div class="container-fluid">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">Administrator</div>
                <h2 class="page-title">Positions</h2>
            </div>
            <div class="col-auto">
                <div class="btn-list">
                    <a class="btn btn-outline-primary" href="{{ route('positions.print') }}" target="_blank"
                        rel="noopener">
                        <i class="ti ti-printer icon"></i> Print
                    </a>
                    <a class="btn btn-outline-success" href="{{ route('positions.excel') }}">
                        <i class="ti ti-file-spreadsheet icon"></i> Excel
                    </a>
                    <button id="btnNewPosition" class="btn btn-primary">
                        <i class="ti ti-plus icon"></i> New Position
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('content')

    @php
        $positionSortBy = $sortBy ?? request('sortBy', 'name');
        $positionSortDir = $sortDir ?? request('sortDir', 'asc');
        $sortUrl = function (string $field) use ($positionSortBy, $positionSortDir) {
            $nextDir = $positionSortBy === $field && $positionSortDir === 'asc' ? 'desc' : 'asc';

            return request()->fullUrlWithQuery([
                'sortBy' => $field,
                'sortDir' => $nextDir,
                'page' => 1,
            ]);
        };
        $sortIcon = function (string $field) use ($positionSortBy, $positionSortDir) {
            if ($positionSortBy !== $field) {
                return '↕';
            }

            return $positionSortDir === 'asc' ? '↑' : '↓';
        };
    @endphp

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Position Lists</h3>
        </div>
        <form method="GET">
            <div class="card-body border-bottom py-3">
                <input type="hidden" name="per_page" value="{{ request('per_page', 10) }}">
                <input type="hidden" name="sortBy" value="{{ $positionSortBy }}">
                <input type="hidden" name="sortDir" value="{{ $positionSortDir }}">
                <div class="row justify-content-end">
                    <div class="col-auto input-icon">
                        <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                        <input class="form-control form-control-sm" name="search" value="{{ request('search') }}"
                            placeholder="Search positions">
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
                                Position {{ $sortIcon('name') }}
                            </a>
                        </th>
                        <th>
                            <a class="table-sort-button text-uppercase" href="{{ $sortUrl('department') }}">
                                Department {{ $sortIcon('department') }}
                            </a>
                        </th>
                        <th>
                            <a class="table-sort-button text-uppercase" href="{{ $sortUrl('code') }}">
                                Code {{ $sortIcon('code') }}
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
                    @forelse($positions as $position)
                        @php
                            $cannotDelete = $position->users_count > 0;
                            $deleteTooltip = $cannotDelete
                                ? "Cannot delete: linked to {$position->users_count} user(s)."
                                : 'Delete this position';
                        @endphp
                        <tr>
                            <td>{{ $positions->firstItem() + $loop->index }}</td>
                            <td>{{ $position->name }}</td>
                            <td>{{ $position->department?->name ?? '—' }}</td>
                            <td>{{ $position->code }}</td>
                            <td><button type="button" class="status-toggle {{ $position->status ? 'is-active' : '' }}"
                                    data-status-toggle data-status-entity="position" data-status-id="{{ $position->id }}"
                                    data-status="{{ $position->status ? 1 : 0 }}"
                                    aria-label="Set status {{ $position->status ? 'inactive' : 'active' }}"
                                    aria-pressed="{{ $position->status ? 'true' : 'false' }}"><span
                                        class="status-toggle-label">{{ $position->status ? 'ON' : 'OFF' }}</span><span
                                        class="status-toggle-knob"></span></button></td>
                            <td class="text-center"><a class="btn btn-sm btn-outline-primary"
                                    href="{{ route('positions.index', ['edit' => $position->id]) }}"><i
                                        class="ti ti-edit"></i></a>
                                <span data-bs-toggle="tooltip" title="{{ $deleteTooltip }}">
                                    <button type="button" class="btn btn-sm btn-outline-danger" @disabled($cannotDelete)
                                        onclick="this.closest('span').querySelector('form')?.requestSubmit()"><i
                                            class="ti ti-trash"></i></button>
                                    <form class="d-none" method="POST" action="{{ route('positions.delete', $position) }}"
                                        onsubmit="return confirm('Delete this position?')">@csrf @method('DELETE')</form>
                                </span>
                            </td>
                    </tr>@empty<tr>
                            <td colspan="6" class="text-center text-secondary py-4">No positions found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">@include('partials.admin-pagination', ['paginator' => $positions])</div>
    </div>
    <div class="modal modal-blur fade" id="positionModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('positions.save') }}">@csrf<div class="modal-header">
                        <h5 class="modal-title">{{ $editPosition ? 'Edit Position' : 'Create Position' }}</h5><a
                            class="btn-close" href="{{ route('positions.index') }}"></a>
                    </div>
                    <div class="modal-body"><input type="hidden" name="position_id" value="{{ $editPosition?->id }}">
                        <div class="position-field"><label class="form-label">Position Name</label><input
                                class="form-control" name="name" value="{{ old('name', $editPosition?->name) }}"
                                required></div>
                        <div class="position-field"><label class="form-label">Code</label><input class="form-control"
                                name="code" value="{{ old('code', $editPosition?->code) }}" required></div>
                        <div class="position-field"><label class="form-label">Department</label><select class="form-select"
                                name="department_id" required>
                                <option value=""> </option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}" @selected(old('department_id', $editPosition?->department_id) == $department->id)>
                                        {{ $department->name }}</option>
                                @endforeach
                            </select></div>
                        <div class="position-status-row"><select class="d-none" name="status">
                                <option value="1" @selected((string) old('status', $editPosition?->status ?? 1) === '1')>Active</option>
                                <option value="0" @selected((string) old('status', $editPosition?->status) === '0')>Inactive</option>
                            </select><button type="button"
                                class="status-toggle {{ old('status', $editPosition?->status ?? 1) ? 'is-active' : '' }}"
                                id="positionStatusToggle"
                                aria-pressed="{{ old('status', $editPosition?->status ?? 1) ? 'true' : 'false' }}"><span
                                    class="status-toggle-label">{{ old('status', $editPosition?->status ?? 1) ? 'ON' : 'OFF' }}</span><span
                                    class="status-toggle-knob"></span></button></div>
                    </div>
                    <div class="modal-footer"><a class="btn me-auto"
                            href="{{ route('positions.index') }}">Cancel</a><button
                            class="btn btn-primary">{{ $editPosition ? 'Update' : 'Create' }}</button></div>
                </form>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.getElementById('positionModal');
            const name = modal?.querySelector('input[name="name"]');
            const code = modal?.querySelector('input[name="code"]');
            const department = modal?.querySelector('select[name="department_id"]');
            const fields = [name, code, department].map(control => control?.closest('.position-field')).filter(
                Boolean);
            fields.forEach(field => {
                const control = field.querySelector('input,select');
                const sync = () => field.classList.toggle('has-value', Boolean(control.value));
                control.addEventListener('input', sync);
                control.addEventListener('change', sync);
                sync();
            });
            if (department) {
                const field = department.closest('.position-field');
                const combo = document.createElement('div');
                combo.className = 'position-combobox';
                combo.innerHTML =
                    '<button type="button" class="position-combobox-toggle"><span></span><i class="ti ti-chevron-down"></i></button><div class="position-combobox-menu d-none"><input type="search" class="form-control" placeholder="Search Department"><div class="position-combobox-results"></div></div>';
                department.classList.add('d-none');
                department.after(combo);
                const button = combo.querySelector('button'),
                    menu = combo.querySelector('.position-combobox-menu'),
                    search = combo.querySelector('input'),
                    selected = combo.querySelector('span'),
                    results = combo.querySelector('.position-combobox-results');
                const sync = () => {
                    selected.textContent = department.value ? department.selectedOptions[0].textContent : '';
                    field.classList.toggle('has-value', Boolean(department.value));
                };
                const render = () => {
                    const term = search.value.toLowerCase().trim();
                    const options = [...department.options].filter(option => option.value && (!term || option
                        .textContent.toLowerCase().includes(term)));
                    results.innerHTML = options.length ? options.map(option =>
                        `<button type="button" class="position-combobox-option" data-value="${option.value}">${option.textContent}</button>`
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
                    department.value = option.dataset.value;
                    department.dispatchEvent(new Event('change', {
                        bubbles: true
                    }));
                    menu.classList.add('d-none');
                    sync();
                });
                document.addEventListener('click', event => {
                    if (!combo.contains(event.target)) menu.classList.add('d-none');
                });
                sync();
                render();
            }
            const status = modal?.querySelector('select[name="status"]');
            const toggle = modal?.querySelector('#positionStatusToggle');
            toggle?.addEventListener('click', () => {
                status.value = status.value === '1' ? '0' : '1';
                const active = status.value === '1';
                toggle.classList.toggle('is-active', active);
                toggle.querySelector('.status-toggle-label').textContent = active ? 'ON' : 'OFF';
                toggle.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            document.getElementById('btnNewPosition')?.addEventListener('click', () => new bootstrap.Modal(modal)
                .show());
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(element => new bootstrap.Tooltip(element));
            @if ($editPosition)
                new bootstrap.Modal(modal).show();
            @endif
        });
    </script>
    @vite('resources/css/pages/positions.css')
@endsection
