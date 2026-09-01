@extends('layouts.app')
@section('title', 'Student Documents')
@section('page-header')<div class="container-fluid">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">Students</div>
                <h2 class="page-title">Student Documents</h2>
            </div>
            <div class="col-auto"><button class="btn btn-primary" id="newStudentDocument"><i class="ti ti-upload me-1"></i>
                    Upload Document</button></div>
        </div>
</div>@endsection
@section('content')

    <div class="card" data-student-documents-page data-options-url="{{ route('student-documents.options') }}"
        data-save-url="{{ route('student-documents.save') }}" data-csrf="{{ csrf_token() }}">
        <div class="card-header">
            <h3 class="card-title">Student Document Records</h3>
        </div>
        <div class="card-body border-bottom"><label class="form-label">Student *</label><select
                id="student-document-student" class="form-select"></select></div>
        <div class="table-responsive">
            <table class="table card-table">
                <thead>
                    <tr>
                        <th>Document Type</th>
                        <th>Title</th>
                        <th>Number</th>
                        <th>File</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody id="student-documents-table">
                    <tr>
                        <td colspan="5" class="text-center">Select a student.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="modal modal-blur fade" id="studentDocumentModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <form id="studentDocumentForm">
                    <div class="modal-header">
                        <h3 class="modal-title">Upload Student Document</h3><button type="button" class="btn-close"
                            data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger d-none" id="student-document-error"></div><input type="hidden"
                            id="document-student-id">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Document Type *</label><select
                                    id="document-type-id" class="form-select" required></select></div>
                            <div class="col-md-6"><label class="form-label">Title</label><input id="document-title"
                                    class="form-control"></div>
                            <div class="col-md-6"><label class="form-label">Document Number</label><input
                                    id="document-number" class="form-control"></div>
                            <div class="col-12"><label class="form-label">File * (PDF, JPG, PNG, DOC, DOCX)</label><input
                                    type="file" id="document-file" class="form-control"
                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required></div>
                            <div class="col-12"><label class="form-label">Description</label>
                                <textarea id="document-description" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn me-auto"
                            data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Upload</button></div>
                </form>
            </div>
        </div>
    </div>
    @vite('resources/js/studentDocuments.js')
    @vite('resources/css/pages/student-documents.css')
@endsection
