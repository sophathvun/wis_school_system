@extends('layouts.app')
@section('title', 'Student Document Types')
@vite('resources/css/pages/student-document-types.css')
@section('page-header')<div class="container-fluid">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">Settings</div>
                <h2 class="page-title">Student Document Types</h2>
            </div>
            <div class="col-auto document-type-new-action"><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#documentTypeModal"><i
                        class="ti ti-plus me-1"></i> New Document Type</button></div>
        </div>
</div>@endsection
@section('content')@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
<div class="card document-types-list-card">
    <div class="card-header">
        <h3 class="card-title">Document Type List</h3>
    </div>
    <div class="card-body border-bottom py-3">
        <form class="input-icon"><span class="input-icon-addon"><i class="ti ti-search"></i></span><input
                class="form-control form-control-sm" name="search" value="{{ request('search') }}"
                placeholder="Search document types"><input type="hidden" name="sortBy" value="{{ request('sortBy', 'sort_order') }}"><input type="hidden" name="sortDir" value="{{ request('sortDir', 'asc') }}"></form>
    </div>
    @php
        $sortUrl = function (string $field) {
            $currentSort = request('sortBy', 'sort_order');
            $currentDir = request('sortDir', 'asc');
            $nextDir = $currentSort === $field && $currentDir === 'asc' ? 'desc' : 'asc';
            return request()->fullUrlWithQuery([
                'sortBy' => $field,
                'sortDir' => $nextDir,
                'page' => 1,
            ]);
        };
        $sortIcon = function (string $field) {
            if (request('sortBy', 'sort_order') !== $field) {
                return '&varr;';
            }
            return request('sortDir', 'asc') === 'asc' ? '&uarr;' : '&darr;';
        };
    @endphp
    <div class="table-responsive document-types-table-wrap">
        <table class="table card-table">
            <thead>
                <tr>
                    <th><a class="table-sort-button text-uppercase" href="{{ $sortUrl('sort_order') }}">ORDER {!! $sortIcon('sort_order') !!}</a></th>
                    <th><a class="table-sort-button text-uppercase" href="{{ $sortUrl('name_en') }}">TYPE (ENGLISH) {!! $sortIcon('name_en') !!}</a></th>
                    <th><a class="table-sort-button text-uppercase school-profile-khmer" href="{{ $sortUrl('name_kh') }}">TYPE (KHMER) {!! $sortIcon('name_kh') !!}</a></th>
                    <th><a class="table-sort-button text-uppercase" href="{{ $sortUrl('type_key') }}">SYSTEM KEY {!! $sortIcon('type_key') !!}</a></th>
                    <th><a class="table-sort-button text-uppercase" href="{{ $sortUrl('status') }}">STATUS {!! $sortIcon('status') !!}</a></th>
                    <th class="text-center text-uppercase">ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                @forelse($types as $type)
                    <tr>
                        <td>{{ $type->sort_order }}</td>
                        <td>{{ $type->name_en }}</td>
                        <td class="school-profile-khmer">{{ $type->name_kh ?: '-' }}</td>
                        <td>{{ $type->type_key }}</td>
                        <td><button type="button" class="status-toggle {{ $type->status ? 'is-active' : '' }}" data-status-toggle data-status-entity="student-document-type" data-status-id="{{ $type->id }}" data-status="{{ $type->status ? 1 : 0 }}" aria-pressed="{{ $type->status ? 'true' : 'false' }}"><span class="status-toggle-label">{{ $type->status ? 'ON' : 'OFF' }}</span><span class="status-toggle-knob"></span></button></td>
                        <td class="text-center"><button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                data-bs-target="#documentTypeModal" data-type='@json($type)' aria-label="Edit document type"><i
                                    class="ti ti-edit"></i></button>
                            @if ($type->status)
                                <form class="d-inline" method="POST"
                                    action="{{ route('student-document-types.delete', $type) }}">@csrf
                                    @method('DELETE')<button type="submit" class="btn btn-danger btn-sm" aria-label="Delete document type"><i
                                            class="ti ti-trash"></i></button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center">No document types found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="document-type-mobile-cards">
        @forelse($types as $type)
            <div class="document-type-card">
                <div class="document-type-card-top">
                    <div class="document-type-card-number">{{ str_pad(($types->firstItem() ?? 1) + $loop->index, 2, '0', STR_PAD_LEFT) }}</div>
                    <button type="button" class="status-toggle {{ $type->status ? 'is-active' : '' }}" data-status-toggle data-status-entity="student-document-type" data-status-id="{{ $type->id }}" data-status="{{ $type->status ? 1 : 0 }}" aria-pressed="{{ $type->status ? 'true' : 'false' }}"><span class="status-toggle-label">{{ $type->status ? 'ON' : 'OFF' }}</span><span class="status-toggle-knob"></span></button>
                </div>
                <div class="document-type-card-grid">
                    <div class="document-type-card-field document-type-card-field-full">
                        <div class="document-type-card-label school-profile-khmer">Type (Khmer)</div>
                        <div class="document-type-card-value school-profile-khmer document-type-card-khmer">{{ $type->name_kh ?: '-' }}</div>
                    </div>
                    <div class="document-type-card-field document-type-card-field-full">
                        <div class="document-type-card-label">Type (English)</div>
                        <div class="document-type-card-value">{{ $type->name_en }}</div>
                    </div>
                    <div class="document-type-card-field">
                        <div class="document-type-card-label">System Key</div>
                        <div class="document-type-card-value">{{ $type->type_key }}</div>
                    </div>
                    <div class="document-type-card-field">
                        <div class="document-type-card-label">Order</div>
                        <div class="document-type-card-value">{{ $type->sort_order }}</div>
                    </div>
                </div>
                <div class="document-type-card-actions">
                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#documentTypeModal" data-type='@json($type)' aria-label="Edit document type"><i class="ti ti-edit"></i></button>
                    @if ($type->status)
                        <form method="POST" action="{{ route('student-document-types.delete', $type) }}">@csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger" aria-label="Delete document type"><i class="ti ti-trash"></i></button>
                        </form>
                    @else
                        <button type="button" class="btn btn-outline-danger" disabled aria-label="Delete document type"><i class="ti ti-trash"></i></button>
                    @endif
                </div>
            </div>
        @empty
            <div class="document-type-empty-card">No document types found.</div>
        @endforelse
    </div>
    <div class="card-footer">@include('partials.admin-pagination', ['paginator' => $types])</div>
</div>
<div class="modal modal-blur fade" id="documentTypeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('student-document-types.save') }}">@csrf<input type="hidden"
                    name="type_id" id="document_type_id">
                <div class="modal-header">
                    <h3 class="modal-title">Student Document Type</h3><button type="button" class="btn-close"
                        data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="premium-form-field mb-3">
                        <label class="form-label" for="document_type_key">System Key *</label>
                        <input class="form-control" name="type_key" id="document_type_key" required>
                    </div>
                    <div class="premium-form-field mb-3">
                        <label class="form-label" for="document_type_en">Name (English) *</label>
                        <input class="form-control" name="name_en" id="document_type_en" required>
                    </div>
                    <div class="premium-form-field mb-3">
                        <label class="form-label" for="document_type_kh">Name (Khmer)</label>
                        <input class="form-control school-profile-khmer" name="name_kh" id="document_type_kh">
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="premium-form-field">
                                <label class="form-label" for="document_type_order">Display Order</label>
                                <input type="number" min="0" class="form-control" name="sort_order"
                                    id="document_type_order" value="0">
                            </div>
                        </div>
                        <div class="col-6 document-type-status-field"><label class="form-label">Status</label><input
                                type="hidden" name="status" id="document_type_status" value="1"><button type="button"
                                    class="status-toggle is-active" id="document_type_status_toggle"
                                    data-document-type-status-toggle aria-pressed="true"><span
                                        class="status-toggle-label">ON</span><span class="status-toggle-knob"></span></button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn me-auto"
                        data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
            </form>
        </div>
    </div>
</div>
@vite('resources/js/studentDocumentTypes.js')
@endsection
