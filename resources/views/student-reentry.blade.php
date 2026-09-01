@extends('layouts.app')
@section('title', 'Student Re-entry')
@section('page-header')
    <div class="container-fluid">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">Students</div>
                <h2 class="page-title">Student Re-entry</h2>
            </div>
            <div class="col-auto"><button type="button" class="btn btn-primary" id="newReentry"><i
                        class="ti ti-user-check me-1"></i> New Re-entry</button></div>
        </div>
    </div>
@endsection
@section('content')

    <div class="card mb-3">
        <div class="card-body">
            <div class="reentry-hero">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="reentry-hero-icon"><i class="ti ti-user-check"></i></div>
                    <div class="flex-fill">
                        <div class="text-secondary text-uppercase small fw-bold">Return to school workflow</div>
                        <h3 class="mb-1">Students eligible for re-entry</h3>
                        <div class="text-secondary">Only officially withdrawn students are shown. Pending, rejected, and
                            cancelled requests remain active.</div>
                    </div>
                    <div>
                        <div class="text-secondary small">Eligible students</div>
                        <div class="h2 mb-0" id="reentry-count">â€”</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title mb-1">Re-entry History</h3>
                <div class="text-secondary small">Review the previous withdrawal before creating a new enrollment.</div>
            </div>
            <div class="card-actions">
                <div class="input-icon" style="width:360px;max-width:100%"><span class="input-icon-addon"><i
                            class="ti ti-search"></i></span><input id="reentry-search" class="form-control"
                        placeholder="Search Student ID or Full Name"></div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Withdrawn Academic Year</th>
                        <th>Campus</th>
                        <th>Grade</th>
                        <th>Group</th>
                        <th>Withdrawal Date</th>
                        <th>Status</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody id="reentry-table">
                    <tr>
                        <td colspan="8">
                            <div class="reentry-empty">Loading eligible students...</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="modal modal-blur fade reentry-modal" id="reentryModal" tabindex="-1"
        data-options-url="{{ route('student-reentry.options') }}" data-save-url="{{ route('student-reentry.save') }}"
        data-csrf="{{ csrf_token() }}">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <form id="reentryForm">
                    <div class="modal-header">
                        <div>
                            <h3 class="modal-title">Create Student Re-entry</h3>
                            <div class="text-secondary small">Create a new active enrollment while preserving the previous
                                withdrawal history.</div>
                        </div><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger d-none" id="reentry-error"></div>
                        <div class="card bg-transparent mb-3">
                            <div class="card-header">
                                <h3 class="card-title"><i class="ti ti-user me-2"></i>Withdrawn Student</h3>
                            </div>
                            <div class="card-body"><label class="form-label">Select Withdrawn Student <span
                                        class="text-danger">*</span></label><select id="reentry-source" class="form-select"
                                    required></select>
                                <div class="reentry-source-card mt-3 d-none" id="reentry-source-card">
                                    <div class="d-flex gap-3 align-items-center"><img id="reentry-source-photo"
                                            class="reentry-table-photo" alt="Student photo">
                                        <div>
                                            <div class="text-secondary small" id="reentry-source-id"></div>
                                            <div class="fw-bold fs-3" id="reentry-source-name"></div>
                                            <div class="text-secondary" id="reentry-source-info"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card bg-transparent">
                            <div class="card-header">
                                <h3 class="card-title"><i class="ti ti-school me-2"></i>New Enrollment Information</h3>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-4"><label class="form-label">Academic Year <span
                                                class="text-danger">*</span></label><select id="reentry-year"
                                            class="form-select" required></select></div>
                                    <div class="col-md-4"><label class="form-label">Campus <span
                                                class="text-danger">*</span></label><select id="reentry-campus"
                                            class="form-select" required></select></div>
                                    <div class="col-md-4"><label class="form-label">Grade <span
                                                class="text-danger">*</span></label><select id="reentry-grade"
                                            class="form-select" required></select></div>
                                    <div class="col-md-4"><label class="form-label">Class <span
                                                class="text-danger">*</span></label><select id="reentry-class"
                                            class="form-select" required></select></div>
                                    <div class="col-md-4"><label class="form-label">Group <span
                                                class="text-danger">*</span></label><select id="reentry-group"
                                            class="form-select" required></select></div>
                                    <div class="col-md-4"><label class="form-label">Re-entry Date <span
                                                class="text-danger">*</span></label><input type="date"
                                            id="reentry-date" class="form-control" required></div>
                                    <div class="col-12"><label class="form-label">Notes</label>
                                        <textarea id="reentry-notes" class="form-control" rows="3"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn me-auto"
                            data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"
                            id="saveReentry"><i class="ti ti-check me-1"></i> Confirm Re-entry</button></div>
                </form>
            </div>
        </div>
    </div>
    @vite('resources/js/studentReentry.js')
    @vite('resources/css/pages/student-reentry.css')
@endsection
