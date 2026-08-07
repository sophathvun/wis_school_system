@extends('layouts.app')
@section('title', 'Grades')

@section('page-header')
    <div class="container-fluid">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">Overview</div>
                <h2 class="page-title">Grades</h2>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <div class="btn-list">
                    <a href="{{ route('grades.pdf') }}" target="_blank" class="btn btn-secondary btn-5 d-none d-sm-inline-block">
                        <i class="ti ti-file-text icon"></i> Export PDF
                    </a>
                    <button id="btnNewGrade" type="button" class="btn btn-primary btn-5 d-none d-sm-inline-block">
                        <i class="ti ti-plus icon"></i> New Grade
                    </button>
                    <button id="btnNewGradeMobile" type="button" class="btn btn-primary btn-6 d-sm-none btn-icon">
                        <i class="ti ti-plus icon"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <div class="col-12">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Grade Lists</h3></div>
            <div class="card-body border-bottom py-3">
                <div class="row col-12 g-2 align-items-center justify-content-between">
                    <div class="col-auto text-secondary">
                        Show
                        <div class="mx-2 d-inline-block">
                            <select class="form-control form-control-sm" id="grades-per-page">
                                <option value="10" selected>10 / page</option>
                                <option value="25">25 / page</option>
                                <option value="50">50 / page</option>
                                <option value="100">100 / page</option>
                            </select>
                        </div>
                        entries
                    </div>
                    <div class="col-auto">
                        <div class="input-icon">
                            <span class="input-icon-addon"><i class="ti ti-search icon"></i></span>
                            <input type="text" id="grades-search" class="form-control form-control-sm" placeholder="Search grades">
                        </div>
                    </div>
                </div>
            </div>
            <div class="table-responsive table-vcenter text-nowrap">
                <table class="table card-table">
                    <thead><tr><th>No.</th><th><button type="button" class="table-sort" data-sort="grade">Grade</button></th><th><button type="button" class="table-sort" data-sort="grade_short_name">Short Name</button></th><th><button type="button" class="table-sort" data-sort="grade_order">Order</button></th><th><button type="button" class="table-sort" data-sort="description">Description</button></th><th><button type="button" class="table-sort" data-sort="status">Status</button></th><th class="text-center">Actions</th></tr></thead>
                    <tbody id="gradesTable"><tr><td colspan="7" class="text-center">Loading grades...</td></tr></tbody>
                </table>
            </div>
            <div class="card-footer"><div class="row g-2"><div class="col-12 d-flex justify-content-center" id="grades-pagination-container"></div></div></div>
        </div>
    </div>

    <div class="modal modal-blur fade" id="gradeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <form id="gradeForm" novalidate>
                    @csrf
                    <div class="modal-header"><h5 class="modal-title" id="gradeModalTitle">Create Grade</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">@include('partials.grade-form')</div>
                    <div class="modal-footer"><button type="button" class="btn me-auto" data-bs-dismiss="modal">Close</button><button type="submit" id="gradeSubmitBtn" class="btn btn-primary">Create</button></div>
                </form>
            </div>
        </div>
    </div>
    @vite('resources/js/grade.js')
@endsection
