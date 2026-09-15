@extends('layouts.app')

@php
    $isPromotion = $mode === 'promotion';
    $label = $isPromotion ? 'Promotion' : 'Transfer';
@endphp

@section('title', 'Student ' . $label)

@section('page-header')
    <div class="container-fluid">
        <div class="row g-2 align-items-center workflow-page-header">
            <div class="col">
                <div class="page-pretitle">Students</div>
                <h2 class="page-title">Student {{ $label }}</h2>
            </div>
            <div class="col-auto workflow-page-actions">
                <a class="btn workflow-desktop-action" href="{{ route('studentEnrollment.index') }}">Back to Enrollment</a>
                @if ($isPromotion)
                    <a class="btn btn-outline-primary ms-2 workflow-desktop-action" href="{{ route('studentGraduation.index') }}">Graduation</a>
                @endif
                <button class="btn btn-primary ms-2" id="newWorkflow">
                    <i class="ti ti-plus icon"></i> New {{ $label }}
                </button>
            </div>
        </div>
    </div>
@endsection

@section('content')


    <div class="card enrollment-workflows-card" data-enrollment-workflows data-workflow-mode="{{ $mode }}">
        <div class="card-header">
            <h3 class="card-title">{{ strtoupper($label) }} HISTORY</h3>
        </div>
        <div class="card-body border-bottom py-3 d-flex flex-wrap justify-content-between workflow-filter-bar">
            <div class="d-flex align-items-center flex-wrap workflow-history-filters">
                <select id="workflow-history-academic-year" class="form-select form-select-sm">
                    <option value=""></option>
                </select>
                <select id="workflow-history-campus" class="form-select form-select-sm">
                    <option value=""></option>
                </select>
                <select id="workflow-history-grade-class" class="form-select form-select-sm">
                    <option value=""></option>
                </select>
                <select id="workflow-history-status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="reversed">Reversed</option>
                </select>
                <select id="workflow-per-page" class="form-control form-control-sm d-none">
                    <option selected>10</option>
                    <option>25</option>
                    <option>50</option>
                    <option>100</option>
                </select>
            </div>
            <div class="input-icon workflow-search">
                <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                <input id="workflow-search" class="form-control form-control-sm" placeholder="Search student">
            </div>
        </div>
        <div class="table-responsive">
            <table class="table card-table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>PHOTO</th>
                        <th><button type="button" class="table-sort" data-workflow-sort="student_id">Student ID <span
                                    data-workflow-sort-icon="student_id"></span></button></th>
                        <th><button type="button" class="table-sort" data-workflow-sort="student_name">Student Name <span
                                    data-workflow-sort-icon="student_name"></span></button></th>
                        @if ($isPromotion)
                            <th><button type="button" class="table-sort" data-workflow-sort="academic_year">Academic Year <span
                                        data-workflow-sort-icon="academic_year"></span></button></th>
                            <th><button type="button" class="table-sort" data-workflow-sort="grade">Grade <span
                                        data-workflow-sort-icon="grade"></span></button></th>
                            <th><button type="button" class="table-sort" data-workflow-sort="group">Group <span
                                        data-workflow-sort-icon="group"></span></button></th>
                            <th><button type="button" class="table-sort" data-workflow-sort="campus">Campus <span
                                        data-workflow-sort-icon="campus"></span></button></th>
                        @else
                            <th><button type="button" class="table-sort" data-workflow-sort="old_information">Transfer From <span
                                        data-workflow-sort-icon="old_information"></span></button></th>
                            <th><button type="button" class="table-sort" data-workflow-sort="transfer_information">Transfer To <span
                                        data-workflow-sort-icon="transfer_information"></span></button></th>
                        @endif
                        <th><button type="button" class="table-sort" data-workflow-sort="action">Action <span
                                    data-workflow-sort-icon="action"></span></button></th>
                        <th><button type="button" class="table-sort" data-workflow-sort="status">Status <span
                                    data-workflow-sort-icon="status"></span></button></th>
                        <th><button type="button" class="table-sort"
                                data-workflow-sort="promoted_date">{{ $isPromotion ? 'Promoted Date' : 'Transferred Date' }}
                                <span data-workflow-sort-icon="promoted_date"></span></button></th>
                        <th><button type="button" class="table-sort"
                                data-workflow-sort="promoted_by">{{ $isPromotion ? 'Promoted By' : 'Transferred By' }} <span
                                    data-workflow-sort-icon="promoted_by"></span></button></th>
                        @if ($isPromotion)
                            <th class="text-nowrap">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody id="workflowTable">
                    <tr>
                        <td colspan="{{ $isPromotion ? 13 : 10 }}" class="text-center">Loading...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            <div id="workflow-pagination-container"></div>
        </div>
    </div>

    <div class="modal modal-blur fade" id="workflowModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered workflow-dialog">
            <div class="modal-content">
                <form id="workflowForm" novalidate>
                    <div class="modal-header">
                        <h3 class="modal-title">Student {{ $label }}</h3>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger d-none" id="workflowError"></div>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Action <span class="required-star">*</span></label>
                                <select id="action_type" class="form-select">
                                    @if ($isPromotion)
                                        <option value="promotion">Promotion - Student</option>
                                        <option value="class_promotion">Promotion - Entire Class</option>
                                        <option value="selected_promotion">Promotion - Selected Students</option>
                                    @else
                                        <option value="transfer">Transfer - Student</option>
                                        <option value="class_transfer">Transfer - Entire Class</option>
                                        <option value="selected_transfer">Transfer - Selected Students</option>
                                    @endif
                                </select>
                            </div>

                            <div class="col-12 workflow-section-title student-source-title">Current Enrollment</div>
                            <div class="col-md-6 student-action premium-form-field">
                                <label class="form-label">Current Academic Year <span class="required-star">*</span></label>
                                <select id="student_from_academic_year_id" class="form-select"></select>
                            </div>
                            <div class="col-12 student-action">
                                <label class="form-label">Student Enrollment <span class="required-star">*</span></label>
                                <select id="enrollment_id" class="form-select"></select>
                            </div>

                            <div class="col-12 class-action d-none">
                                <div class="row g-3">
                                    <div class="col-12 workflow-section-title">Current Class</div>
                                    <div class="col-md-3 premium-form-field">
                                        <label class="form-label">Source Academic Year <span class="required-star">*</span></label>
                                        <select id="from_academic_year_id" class="form-select"></select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Source Campus <span class="required-star">*</span></label>
                                        <select id="from_campus_id" class="form-select"></select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Source Grade <span class="required-star">*</span></label>
                                        <select id="from_grade_id" class="form-select"></select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Source Class <span class="required-star">*</span></label>
                                        <select id="from_class_id" class="form-select"></select>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 selected-students-action d-none">
                                <div class="workflow-section-title selected-students-title">Select Students</div>
                                <div class="d-flex justify-content-between align-items-center mt-2 mb-2">
                                    <span class="text-secondary small selected-students-help"></span>
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                        id="select-all-class-students">Select All</button>
                                </div>
                                <div id="selected-students-list" class="border rounded p-2 workflow-selected-list"></div>
                            </div>

                            <div class="col-12 workflow-section-title target-section-title">
                                {{ $isPromotion ? 'Promote To' : 'Transfer To' }}</div>
                            <div class="{{ $isPromotion ? 'col-md-6' : 'col-12' }} target-year premium-form-field">
                                <label class="form-label">Target Academic Year <span class="required-star">*</span></label>
                                <select id="to_academic_year_id" class="form-select"></select>
                            </div>
                            <div class="{{ $isPromotion ? 'col-md-6' : 'col-md-3' }}">
                                <label class="form-label">Target Campus <span class="required-star">*</span></label>
                                <select id="to_campus_id" class="form-select"></select>
                            </div>
                            <div class="{{ $isPromotion ? 'col-md-4' : 'col-md-3' }}">
                                <label class="form-label">Target Grade @if (!$isPromotion)<span class="required-star">*</span>@endif</label>
                                <select id="to_grade_id" class="form-select" @if (!$isPromotion) required @endif></select>
                            </div>
                            <div class="{{ $isPromotion ? 'col-md-4' : 'col-md-3' }}">
                                <label class="form-label">Target Class @if (!$isPromotion)<span class="required-star">*</span>@endif</label>
                                <select id="to_class_id" class="form-select" @if (!$isPromotion) required @endif></select>
                            </div>
                            <div class="{{ $isPromotion ? 'col-md-4' : 'col-md-3' }}">
                                <label class="form-label">Target Group @if (!$isPromotion)<span class="required-star">*</span>@endif</label>
                                <select id="to_session_id" class="form-select" @if (!$isPromotion) required @endif></select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Effective Date <span class="required-star">*</span></label>
                                <input type="date" id="effective_on" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Reason</label>
                                <input id="reason" class="form-control">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Notes</label>
                                <textarea id="notes" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn me-auto" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-primary">Save {{ $label }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @vite('resources/js/enrollmentWorkflows.js')
    @vite('resources/css/pages/enrollment-workflows.css')
@endsection
