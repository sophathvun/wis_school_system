@extends('layouts.app')
@section('title', 'Student Document Types')
@vite('resources/css/pages/student-document-types.css')
@section('page-header')<div class="container-fluid">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">Settings</div>
                <h2 class="page-title">Student Document Types</h2>
            </div>
            <div class="col-auto"><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#documentTypeModal"><i
                        class="ti ti-plus me-1"></i> New Document Type</button></div>
        </div>
</div>@endsection
@section('content')@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Document Type List</h3>
    </div>
    <div class="card-body border-bottom py-3">
        <form class="input-icon"><span class="input-icon-addon"><i class="ti ti-search"></i></span><input
                class="form-control form-control-sm" name="search" value="{{ request('search') }}"
                placeholder="Search document types"></form>
    </div>
    <div class="table-responsive">
        <table class="table card-table">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Type (English)</th>
                    <th>Type (Khmer)</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($types as $type)
                    <tr>
                        <td>{{ $type->sort_order }}</td>
                        <td>{{ $type->name_en }}<div class="small text-secondary">{{ $type->type_key }}</div>
                        </td>
                        <td class="school-profile-khmer">{{ $type->name_kh ?: '—' }}</td>
                        <td><span
                                class="badge bg-{{ $type->status ? 'success' : 'secondary' }}">{{ $type->status ? 'Active' : 'Inactive' }}</span>
                        </td>
                        <td class="text-center"><button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                data-bs-target="#documentTypeModal" data-type='@json($type)'><i
                                    class="ti ti-edit"></i></button>
                            @if ($type->status)
                                <form class="d-inline" method="POST"
                                    action="{{ route('student-document-types.delete', $type) }}">@csrf
                                    @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i
                                            class="ti ti-ban"></i></button></form>
                            @endif
                        </td>
                </tr>@empty<tr>
                        <td colspan="5" class="text-center">No document types found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
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
