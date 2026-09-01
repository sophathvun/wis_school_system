@extends('layouts.app')

@section('title', 'Student Graduation')

@section('content')


    <div class="card">
        <div class="card-header d-flex align-items-center">
            <h3 class="card-title mb-0">Graduated Students</h3>
            <button class="btn btn-primary ms-auto" id="newGraduation">
                <i class="ti ti-school icon"></i> Graduate Grade 12 Student
            </button>
        </div>

        <div class="card-body border-bottom py-3 d-flex flex-wrap justify-content-between graduation-filter-bar">
            <div class="d-flex align-items-center flex-wrap graduation-history-filters">
                <select id="graduation-year" class="form-select form-select-sm">
                    <option value=""></option>
                </select>
                <select id="graduation-campus" class="form-select form-select-sm">
                    <option value=""></option>
                </select>
                <select id="graduation-class" class="form-select form-select-sm">
                    <option value=""></option>
                </select>
                <select id="graduation-per-page" class="form-control form-control-sm d-none">
                    <option selected>10</option>
                    <option>25</option>
                    <option>50</option>
                    <option>100</option>
                </select>
            </div>
            <div class="input-icon graduation-search">
                <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                <input id="graduation-search" class="form-control form-control-sm" placeholder="Search student">
            </div>
        </div>

        <div class="table-responsive">
            <table class="table card-table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Student Photo</th>
                        <th><button type="button" class="table-sort" data-graduation-sort="student_id">Student ID <span
                                    data-graduation-sort-icon="student_id"></span></button></th>
                        <th><button type="button" class="table-sort" data-graduation-sort="student_name">Student Name <span
                                    data-graduation-sort-icon="student_name"></span></button></th>
                        <th><button type="button" class="table-sort" data-graduation-sort="academic_year">Academic Year
                                <span data-graduation-sort-icon="academic_year"></span></button></th>
                        <th><button type="button" class="table-sort" data-graduation-sort="class">Grade <span
                                    data-graduation-sort-icon="class"></span></button></th>
                        <th><button type="button" class="table-sort" data-graduation-sort="group">Group <span
                                    data-graduation-sort-icon="group"></span></button></th>
                        <th><button type="button" class="table-sort" data-graduation-sort="campus">Campus <span
                                    data-graduation-sort-icon="campus"></span></button></th>
                        <th><button type="button" class="table-sort" data-graduation-sort="graduation_date">Graduation Date
                                <span data-graduation-sort-icon="graduation_date"></span></button></th>
                        <th><button type="button" class="table-sort" data-graduation-sort="certificate">Certificate <span
                                    data-graduation-sort-icon="certificate"></span></button></th>
                        <th><button type="button" class="table-sort" data-graduation-sort="alumni">Alumni <span
                                    data-graduation-sort-icon="alumni"></span></button></th>
                        <th><button type="button" class="table-sort" data-graduation-sort="graduated_by">Graduated By <span
                                    data-graduation-sort-icon="graduated_by"></span></button></th>
                    </tr>
                </thead>
                <tbody id="graduationTable">
                    <tr>
                        <td colspan="12" class="text-center">Loading...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            <div id="graduation-pagination-container"></div>
        </div>
    </div>

    <div class="modal modal-blur fade" id="graduationModal" tabindex="-1" data-csrf="{{ csrf_token() }}">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="graduationForm">
                    <div class="modal-header">
                        <h3 class="modal-title">Graduate Grade 12 Student</h3>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger d-none" id="graduationError"></div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Graduation Scope *</label>
                                <select id="graduation-scope" class="form-select">
                                    <option value="student">Individual Student</option>
                                    <option value="class">Entire Class</option>
                                    <option value="campus">Entire Campus</option>
                                    <option value="all_campuses">All Campuses</option>
                                </select>
                            </div>
                            <div class="col-md-8 graduation-student-field">
                                <label class="form-label">Grade 12 Student *</label>
                                <select id="graduation-enrollment" class="form-select" multiple></select>
                                <div class="small text-secondary mt-2" id="graduation-current"></div>
                            </div>

                            <div class="col-12 d-none" id="graduation-batch-block">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Academic Year *</label>
                                        <select id="batch-year" class="form-select"></select>
                                    </div>
                                    <div class="col-md-4" id="batch-campus-block">
                                        <label class="form-label">Campus *</label>
                                        <select id="batch-campus" class="form-select"></select>
                                    </div>
                                    <div class="col-md-4" id="batch-class-block">
                                        <label class="form-label">Class *</label>
                                        <select id="batch-class" class="form-select"></select>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Graduation Date *</label>
                                <input type="date" id="graduation-date" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Certificate Prefix *</label>
                                <input id="certificate-number" class="form-control" inputmode="numeric" maxlength="4"
                                    pattern="\d{4}" placeholder="Ex: 2026" required>
                                <div class="form-hint">Final number: 4 digits + auto 3 digits, e.g. 2026001.</div>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <label class="form-check mb-2">
                                    <input type="checkbox" id="is-alumni" class="form-check-input">
                                    <span class="form-check-label">Mark as Alumni</span>
                                </label>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Notes</label>
                                <textarea id="graduation-notes" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn me-auto" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-primary">Graduate Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @vite('resources/js/studentGraduation.js')
    @vite('resources/css/pages/student-graduation.css')
@endsection
