@extends('layouts.app')

@section('title', 'Withdraw Student')
@section('page-header')
    <div class="container-fluid">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">Students</div>
                <h2 class="page-title">Student Withdrawal</h2>
            </div>
            <div class="col-auto"><button type="button" class="btn btn-primary" id="newWithdrawal" data-bs-toggle="modal"
                    data-bs-target="#withdrawalModal"><i class="ti ti-user-minus me-1"></i> New Withdrawal</button></div>
        </div>
    </div>
@endsection

@section('content')

    <div id="studentWithdrawalPage" data-fetch-url="{{ route('student-withdrawals.fetch') }}"
        data-history-options-url="{{ route('student-withdrawals.history-options') }}"
        data-options-url="{{ route('student-withdrawals.options') }}"
        data-students-url="{{ route('student-withdrawals.students') }}"
        data-enrollment-family-url="{{ url('/student-withdrawals/enrollments') }}"
        data-base-url="{{ url('/student-withdrawals') }}" data-csrf="{{ csrf_token() }}"
        data-reasons='@json(\App\Http\Controllers\StudentWithdrawalController::REASONS)'>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Withdrawal History</h3>
        </div>
        <div class="card-body border-bottom py-3">
            <div class="row g-2 align-items-center">
                <div class="col-sm-6 col-lg">
                    <div id="withdrawal-history-year-filter"></div>
                </div>
                <div class="col-sm-6 col-lg">
                    <div id="withdrawal-history-campus-filter"></div>
                </div>
                <div class="col-sm-6 col-lg">
                    <div id="withdrawal-history-grade-filter"></div>
                </div>
                <div class="col-sm-6 col-lg">
                    <div id="withdrawal-history-group-filter"></div>
                </div>
                <div class="col-sm-6 col-lg">
                    <div id="withdrawal-history-student-filter"></div>
                </div>
                <div class="col-sm-6 col-lg-auto"><input id="withdrawal-search" class="form-control form-control-sm"
                        placeholder="Search withdrawal history"></div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead>
                    <tr>
                        <th class="text-secondary">#</th>
                        <th>Student Photo</th>
                        <th><button type="button" class="table-sort-btn" data-withdrawal-sort="student_id">Student
                                ID</button></th>
                        <th><button type="button" class="table-sort-btn" data-withdrawal-sort="student_name">Student
                                Name</button></th>
                        <th><button type="button" class="table-sort-btn" data-withdrawal-sort="academic_year">Academic
                                Year</button></th>
                        <th><button type="button" class="table-sort-btn" data-withdrawal-sort="grade">Grade</button></th>
                        <th><button type="button" class="table-sort-btn" data-withdrawal-sort="group">Group</button></th>
                        <th><button type="button" class="table-sort-btn" data-withdrawal-sort="date">Withdrawal
                                Date</button></th>
                        <th><button type="button" class="table-sort-btn" data-withdrawal-sort="requested_by">Requested
                                By</button></th>
                        <th><button type="button" class="table-sort-btn" data-withdrawal-sort="withdrawn_by">Withdrawn
                                By</button></th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="withdrawal-table">
                    <tr>
                        <td colspan="12" class="text-center">Loading...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            <div id="withdrawal-pagination"></div>
        </div>
    </div>
    <div class="modal modal-blur fade" id="withdrawalModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <form id="withdrawalForm">
                    <div class="modal-header">
                        <h3 class="modal-title">Withdraw Student</h3><button type="button" class="btn-close"
                            data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger d-none" id="withdrawal-error"></div>
                        <div class="row g-3">
                            <div class="col-12"><label class="form-label">Withdrawal Type *</label><select
                                    id="withdrawal-type" class="form-select">
                                    <option value="student">Withdraw Student</option>
                                    <option value="selected">Withdraw Selected Students</option>
                                    <option value="class">Withdraw Entire Class</option>
                                </select></div>
                            <div class="row g-3 withdrawal-filter-fields">
                                <div class="col-md-6"><label class="form-label">Academic Year *</label><input
                                        id="withdrawal-year-search" class="form-control form-control-sm mb-1"
                                        placeholder="Search academic year"><select id="withdrawal-year"
                                        class="form-select"></select></div>
                                <div class="col-md-6"><label class="form-label">Campus *</label><input
                                        id="withdrawal-campus-search" class="form-control form-control-sm mb-1"
                                        placeholder="Search campus"><select id="withdrawal-campus"
                                        class="form-select"></select></div>
                                <div class="col-md-6"><label class="form-label">Grade + Class *</label><input
                                        id="withdrawal-grade-class-search" class="form-control form-control-sm mb-1"
                                        placeholder="Search Grade 1A"><select id="withdrawal-grade-class"
                                        class="form-select"></select><select id="withdrawal-grade"
                                        class="d-none"></select><select id="withdrawal-class" class="d-none"></select>
                                </div>
                            </div>
                            <div class="col-12 student-withdrawal-field"><label class="form-label">Student Name
                                    *</label><input id="withdrawal-student-search" class="form-control mb-2"
                                    placeholder="Search Student ID or name"><select id="withdrawal-enrollment-id"
                                    class="form-select"></select></div>
                            <div class="col-12 selected-withdrawal-list d-none"><label class="form-label">Select
                                    Students</label>
                                <div id="withdrawal-students" class="border rounded p-2"
                                    style="max-height:220px;overflow:auto"></div>
                            </div>
                            <div class="col-md-6"><label class="form-label">Withdrawal Date *</label>
                                <div class="withdrawal-date-picker date-picker" id="withdrawal_date_picker">
                                    <div class="date-picker-input-row"><input type="text" id="withdrawal_date_direct"
                                            class="form-control" inputmode="numeric" placeholder="DD-MM-YYYY"
                                            required><button type="button" id="withdrawal_date_trigger"
                                            class="date-picker-trigger date-picker-calendar-button"><i
                                                class="ti ti-calendar"></i></button></div><input type="hidden"
                                        id="withdrawal-date" required>
                                    <div id="withdrawal_date_popup" class="date-picker-popup d-none">
                                        <div class="date-picker-header"><button type="button" id="withdrawal_date_prev"
                                                class="date-picker-nav"><i class="ti ti-chevron-left"></i></button><button
                                                type="button" id="withdrawal_date_year_toggle"
                                                class="date-picker-year-toggle"><span
                                                    id="withdrawal_date_month_label"></span></button><button
                                                type="button" id="withdrawal_date_next" class="date-picker-nav"><i
                                                    class="ti ti-chevron-right"></i></button></div>
                                        <div id="withdrawal_date_year_popup" class="date-picker-year-popup d-none">
                                            <div id="withdrawal_date_years" class="date-picker-years"></div>
                                        </div>
                                        <div class="date-picker-grid">
                                            <div class="date-picker-weekdays">
                                                <span>SU</span><span>MO</span><span>TU</span><span>WE</span><span>TH</span><span>FR</span><span>SA</span>
                                            </div>
                                            <div id="withdrawal_date_days" class="date-picker-days"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6"><label class="form-label">Reason *</label><input id="withdrawal-reason"
                                    class="form-control" required></div>
                            <div class="col-12"><label class="form-label">Notes</label>
                                <textarea id="withdrawal-notes" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn me-auto"
                            data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Confirm
                            Withdrawal</button></div>
                </form>
            </div>
        </div>
    </div>
    </div>
    <div class="modal modal-blur fade" id="editWithdrawalModal" tabindex="-1" data-bs-focus="false">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <form id="editWithdrawalForm">
                    <input type="hidden" id="edit-withdrawal-id">
                    <div class="modal-header">
                        <h3 class="modal-title">Edit Withdrawal Form</h3>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger d-none" id="edit-withdrawal-error"></div>
                        <div id="edit-student-summary" class="edit-student-summary d-none mb-4">
                            <div class="edit-student-summary-photo-wrap">
                                <img id="edit-student-photo" class="edit-student-summary-photo d-none"
                                    alt="Student photo">
                                <span id="edit-student-photo-placeholder"
                                    class="edit-student-summary-photo edit-student-summary-photo-placeholder"><i
                                        class="ti ti-user"></i></span>
                            </div>
                            <div class="edit-student-summary-details">
                                <div id="edit-student-id" class="edit-student-summary-id"></div>
                                <div id="edit-student-name-kh" class="school-profile-khmer text-secondary"></div>
                                <div id="edit-student-name-en" class="edit-student-summary-name"></div>
                                <div id="edit-student-context" class="edit-student-summary-context"></div>
                            </div>
                        </div>
                        <div class="withdrawal-reason-panel mb-3">
                            <h4 class="mb-3 fw-bold">REASONS FOR WITHDRAWAL: <small
                                    class="text-secondary fw-normal">(Select all that apply)</small></h4>
                            <div class="row g-2" id="edit-withdrawal-reasons">
                                @foreach ($reasons as $reason)
                                    <div class="col-md-4">
                                        <label class="form-check">
                                            <input class="form-check-input edit-withdrawal-reason-check" type="checkbox"
                                                value="{{ $reason->key }}" data-en="{{ e($reason->en) }}"
                                                data-kh="{{ e($reason->kh) }}">
                                            <span class="form-check-label">
                                                <span class="d-block school-profile-khmer">{{ $reason->kh }}</span>
                                                {{ $reason->en }}
                                            </span>
                                        </label>
                                    </div>
                                @endforeach
                                <div class="col-md-4">
                                    <label class="form-check">
                                        <input class="form-check-input" type="checkbox" id="edit-other-check">
                                        <span class="form-check-label">Other</span>
                                    </label>
                                </div>
                            </div>
                            <div class="row g-2 mt-1">
                                <div class="col-md-6 edit-other-field d-none">
                                    <label class="form-label">Other Reason English</label>
                                    <input id="edit-other-en" class="form-control">
                                </div>
                                <div class="col-md-6 edit-other-field d-none">
                                    <label class="form-label school-profile-khmer">áž˜áž¼áž›áž áŸážáž»áž•áŸ’ážŸáŸáž„áž‘áŸ€áž</label>
                                    <input id="edit-other-kh" class="form-control school-profile-khmer">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Reason (English)</label>
                                    <input id="edit-reason-en" class="form-control" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label school-profile-khmer">áž˜áž¼áž›áž áŸážáž» (ážáŸ’áž˜áŸ‚ážš)</label>
                                    <input id="edit-reason-kh" class="form-control school-profile-khmer" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">New School</label>
                                <input id="edit-new-school" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">New School Address</label>
                                <input id="edit-new-school-address" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Withdrawal Status * <small
                                        class="text-secondary fw-normal">(select one only)</small></label>
                                <select id="edit-dropout-type" class="form-select" required>
                                    <option value="official_leave">Will officially leave</option>
                                    <option value="dropped_out">Has dropped out</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Additional Comments</label>
                                <input id="edit-additional-comments" class="form-control">
                            </div>
                            <div class="col-md-6 withdrawal-date-column">
                                <label class="form-label">Withdrawal Date *</label>
                                <div class="withdrawal-date-picker date-picker" id="edit_withdrawal_date_picker">
                                    <div class="date-picker-input-row">
                                        <input type="text" id="edit_withdrawal_date_direct" class="form-control"
                                            inputmode="numeric" placeholder="DD-MM-YYYY" required>
                                        <button type="button" id="edit_withdrawal_date_trigger"
                                            class="date-picker-trigger date-picker-calendar-button"><i
                                                class="ti ti-calendar"></i></button>
                                    </div>
                                    <input type="hidden" id="edit-withdrawal-date" required>
                                    <div id="edit_withdrawal_date_popup" class="date-picker-popup d-none">
                                        <div class="date-picker-header"><button type="button"
                                                id="edit_withdrawal_date_prev" class="date-picker-nav"><i
                                                    class="ti ti-chevron-left"></i></button><button type="button"
                                                id="edit_withdrawal_date_year_toggle"
                                                class="date-picker-year-toggle"><span
                                                    id="edit_withdrawal_date_month_label"></span></button><button
                                                type="button" id="edit_withdrawal_date_next" class="date-picker-nav"><i
                                                    class="ti ti-chevron-right"></i></button></div>
                                        <div id="edit_withdrawal_date_year_popup" class="date-picker-year-popup d-none">
                                            <div id="edit_withdrawal_date_years" class="date-picker-years"></div>
                                        </div>
                                        <div class="date-picker-grid">
                                            <div class="date-picker-weekdays">
                                                <span>SU</span><span>MO</span><span>TU</span><span>WE</span><span>TH</span><span>FR</span><span>SA</span>
                                            </div>
                                            <div id="edit_withdrawal_date_days" class="date-picker-days"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Notes</label>
                                <input id="edit-notes" class="form-control">
                            </div>
                            <div class="col-12 withdrawal-requested-by">
                                <h4 class="mb-2 fw-bold">REQUESTED BY</h4>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label class="form-label">Requested By *</label>
                                        <select id="edit-requested-by-type" class="form-select" required>
                                            <option value=""></option>
                                            <option value="mother">Mother</option>
                                            <option value="father">Father</option>
                                            <option value="guardian">Guardian</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Requested By Name *</label>
                                        <input id="edit-requested-by-name" class="form-control" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Requested By Phone *</label>
                                        <input id="edit-requested-by-phone" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn me-auto" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-outline-warning d-none" id="mark-principal-approved"><i
                                class="ti ti-file-check me-1"></i>Principal Approved on Paper</button>
                        <button type="button" class="btn btn-success d-none" id="approve-withdrawal"><i
                                class="ti ti-circle-check me-1"></i>Approve Withdrawal</button>
                        <button type="button" class="btn btn-outline-danger d-none" id="reject-withdrawal"><i
                                class="ti ti-ban me-1"></i>Reject Request</button>
                        <button type="button" class="btn btn-outline-secondary d-none" id="cancel-withdrawal"><i
                                class="ti ti-x me-1"></i>Cancel Request</button>
                        <button class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@vite('resources/css/pages/student-withdrawal.css')
@vite('resources/js/studentWithdrawal.js')
