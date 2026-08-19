@extends('layouts.app')

@section('title', 'Withdraw Student')
@section('page-header')
<div class="container-fluid"><div class="row g-2 align-items-center"><div class="col"><div class="page-pretitle">Students</div><h2 class="page-title">Student Withdrawal</h2></div><div class="col-auto"><button type="button" class="btn btn-primary" id="newWithdrawal" data-bs-toggle="modal" data-bs-target="#withdrawalModal"><i class="ti ti-user-minus me-1"></i> New Withdrawal</button></div></div></div>
@endsection

@section('content')
<style>.withdrawal-filter-fields input[id="withdrawal-year-search"],.withdrawal-filter-fields input[id="withdrawal-campus-search"],.withdrawal-filter-fields input[id="withdrawal-grade-class-search"],.withdrawal-filter-fields>.col-md-6>.input-icon,.student-withdrawal-field>#withdrawal-student-search,.student-withdrawal-field>.input-icon{display:none!important}.table-sort-btn{border:0;background:transparent;padding:0;color:inherit;font:inherit;font-weight:600;text-align:left;cursor:pointer}.table-sort-btn:hover{color:var(--tblr-primary)}.withdrawal-history-photo{width:52px;height:69px;object-fit:cover;border-radius:15%;display:block}.withdrawal-history-photo-placeholder{width:52px;height:69px;border-radius:15%;display:inline-flex;align-items:center;justify-content:center}.withdrawal-history-name{min-width:180px}.withdrawal-history-track{font-size:.78rem}.withdrawal-history-phone{font-size:.78rem}.withdrawal-history-date{white-space:nowrap}#withdrawalModal .modal-dialog,#editWithdrawalModal .modal-dialog{width:calc(100% - 2rem);max-width:1100px}@media (min-width:768px){#withdrawalForm .withdrawal-filter-fields>.col-md-6{width:33.333333%;flex:0 0 auto}#withdrawalForm .student-withdrawal-field{width:66.666667%;flex:0 0 auto}#withdrawalForm .withdrawal-date-column{width:33.333333%;flex:0 0 auto}#withdrawalForm .withdrawal-reason-panel>.row.g-2:first-of-type>.col-md-6{width:33.333333%;flex:0 0 auto}}</style>
<div class="card"><div class="card-header"><h3 class="card-title">Withdrawal History</h3></div><div class="card-body border-bottom py-3"><div class="row g-2 align-items-center"><div class="col-sm-6 col-lg"><div id="withdrawal-history-year-filter"></div></div><div class="col-sm-6 col-lg"><div id="withdrawal-history-campus-filter"></div></div><div class="col-sm-6 col-lg"><div id="withdrawal-history-grade-filter"></div></div><div class="col-sm-6 col-lg"><div id="withdrawal-history-group-filter"></div></div><div class="col-sm-6 col-lg"><div id="withdrawal-history-student-filter"></div></div><div class="col-sm-6 col-lg-auto"><input id="withdrawal-search" class="form-control form-control-sm" placeholder="Search withdrawal history"></div></div></div><div class="table-responsive"><table class="table card-table table-vcenter"><thead><tr><th class="text-secondary">#</th><th>Student Photo</th><th><button type="button" class="table-sort-btn" data-withdrawal-sort="student_id">Student ID</button></th><th><button type="button" class="table-sort-btn" data-withdrawal-sort="student_name">Student Name</button></th><th><button type="button" class="table-sort-btn" data-withdrawal-sort="academic_year">Academic Year</button></th><th><button type="button" class="table-sort-btn" data-withdrawal-sort="grade">Grade</button></th><th><button type="button" class="table-sort-btn" data-withdrawal-sort="group">Group</button></th><th><button type="button" class="table-sort-btn" data-withdrawal-sort="date">Withdrawal Date</button></th><th><button type="button" class="table-sort-btn" data-withdrawal-sort="requested_by">Requested By</button></th><th><button type="button" class="table-sort-btn" data-withdrawal-sort="withdrawn_by">Withdrawn By</button></th><th>Status</th><th>Actions</th></tr></thead><tbody id="withdrawal-table"><tr><td colspan="12" class="text-center">Loading...</td></tr></tbody></table></div><div class="card-footer"><div id="withdrawal-pagination"></div></div></div>
<div class="modal modal-blur fade" id="withdrawalModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content"><form id="withdrawalForm"><div class="modal-header"><h3 class="modal-title">Withdraw Student</h3><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="alert alert-danger d-none" id="withdrawal-error"></div><div class="row g-3"><div class="col-12"><label class="form-label">Withdrawal Type *</label><select id="withdrawal-type" class="form-select"><option value="student">Withdraw Student</option><option value="selected">Withdraw Selected Students</option><option value="class">Withdraw Entire Class</option></select></div><div class="row g-3 withdrawal-filter-fields"><div class="col-md-6"><label class="form-label">Academic Year *</label><input id="withdrawal-year-search" class="form-control form-control-sm mb-1" placeholder="Search academic year"><select id="withdrawal-year" class="form-select"></select></div><div class="col-md-6"><label class="form-label">Campus *</label><input id="withdrawal-campus-search" class="form-control form-control-sm mb-1" placeholder="Search campus"><select id="withdrawal-campus" class="form-select"></select></div><div class="col-md-6"><label class="form-label">Grade + Class *</label><input id="withdrawal-grade-class-search" class="form-control form-control-sm mb-1" placeholder="Search Grade 1A"><select id="withdrawal-grade-class" class="form-select"></select><select id="withdrawal-grade" class="d-none"></select><select id="withdrawal-class" class="d-none"></select></div></div><div class="col-12 student-withdrawal-field"><label class="form-label">Student Name *</label><input id="withdrawal-student-search" class="form-control mb-2" placeholder="Search Student ID or name"><select id="withdrawal-enrollment-id" class="form-select"></select></div><div class="col-12 selected-withdrawal-list d-none"><label class="form-label">Select Students</label><div id="withdrawal-students" class="border rounded p-2" style="max-height:220px;overflow:auto"></div></div><div class="col-md-6"><label class="form-label">Withdrawal Date *</label><div class="withdrawal-date-picker date-picker" id="withdrawal_date_picker"><div class="date-picker-input-row"><input type="text" id="withdrawal_date_direct" class="form-control" inputmode="numeric" placeholder="DD-MM-YYYY" required><button type="button" id="withdrawal_date_trigger" class="date-picker-trigger date-picker-calendar-button"><i class="ti ti-calendar"></i></button></div><input type="hidden" id="withdrawal-date" required><div id="withdrawal_date_popup" class="date-picker-popup d-none"><div class="date-picker-header"><button type="button" id="withdrawal_date_prev" class="date-picker-nav"><i class="ti ti-chevron-left"></i></button><button type="button" id="withdrawal_date_year_toggle" class="date-picker-year-toggle"><span id="withdrawal_date_month_label"></span></button><button type="button" id="withdrawal_date_next" class="date-picker-nav"><i class="ti ti-chevron-right"></i></button></div><div id="withdrawal_date_year_popup" class="date-picker-year-popup d-none"><div id="withdrawal_date_years" class="date-picker-years"></div></div><div class="date-picker-grid"><div class="date-picker-weekdays"><span>SU</span><span>MO</span><span>TU</span><span>WE</span><span>TH</span><span>FR</span><span>SA</span></div><div id="withdrawal_date_days" class="date-picker-days"></div></div></div></div></div><div class="col-md-6"><label class="form-label">Reason *</label><input id="withdrawal-reason" class="form-control" required></div><div class="col-12"><label class="form-label">Notes</label><textarea id="withdrawal-notes" class="form-control" rows="3"></textarea></div></div></div><div class="modal-footer"><button type="button" class="btn me-auto" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Confirm Withdrawal</button></div></form></div></div></div>
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
                            <img id="edit-student-photo" class="edit-student-summary-photo d-none" alt="Student photo">
                            <span id="edit-student-photo-placeholder" class="edit-student-summary-photo edit-student-summary-photo-placeholder"><i class="ti ti-user"></i></span>
                        </div>
                        <div class="edit-student-summary-details">
                            <div id="edit-student-id" class="edit-student-summary-id"></div>
                            <div id="edit-student-name-kh" class="school-profile-khmer text-secondary"></div>
                            <div id="edit-student-name-en" class="edit-student-summary-name"></div>
                            <div id="edit-student-context" class="edit-student-summary-context"></div>
                        </div>
                    </div>
                    <div class="withdrawal-reason-panel mb-3">
                        <h4 class="mb-3 fw-bold">REASONS FOR WITHDRAWAL: <small class="text-secondary fw-normal">(Select all that apply)</small></h4>
                        <div class="row g-2" id="edit-withdrawal-reasons">
                            @foreach($reasons as $reason)
                                <div class="col-md-4">
                                    <label class="form-check">
                                        <input class="form-check-input edit-withdrawal-reason-check" type="checkbox" value="{{ $reason->key }}" data-en="{{ e($reason->en) }}" data-kh="{{ e($reason->kh) }}">
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
                                <label class="form-label school-profile-khmer">មូលហេតុផ្សេងទៀត</label>
                                <input id="edit-other-kh" class="form-control school-profile-khmer">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Reason (English)</label>
                                <input id="edit-reason-en" class="form-control" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label school-profile-khmer">មូលហេតុ (ខ្មែរ)</label>
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
                            <label class="form-label">Withdrawal Status * <small class="text-secondary fw-normal">(select one only)</small></label>
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
                                    <input type="text" id="edit_withdrawal_date_direct" class="form-control" inputmode="numeric" placeholder="DD-MM-YYYY" required>
                                    <button type="button" id="edit_withdrawal_date_trigger" class="date-picker-trigger date-picker-calendar-button"><i class="ti ti-calendar"></i></button>
                                </div>
                                <input type="hidden" id="edit-withdrawal-date" required>
                                <div id="edit_withdrawal_date_popup" class="date-picker-popup d-none">
                                    <div class="date-picker-header"><button type="button" id="edit_withdrawal_date_prev" class="date-picker-nav"><i class="ti ti-chevron-left"></i></button><button type="button" id="edit_withdrawal_date_year_toggle" class="date-picker-year-toggle"><span id="edit_withdrawal_date_month_label"></span></button><button type="button" id="edit_withdrawal_date_next" class="date-picker-nav"><i class="ti ti-chevron-right"></i></button></div>
                                    <div id="edit_withdrawal_date_year_popup" class="date-picker-year-popup d-none"><div id="edit_withdrawal_date_years" class="date-picker-years"></div></div>
                                    <div class="date-picker-grid"><div class="date-picker-weekdays"><span>SU</span><span>MO</span><span>TU</span><span>WE</span><span>TH</span><span>FR</span><span>SA</span></div><div id="edit_withdrawal_date_days" class="date-picker-days"></div></div>
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
                    <button type="button" class="btn btn-outline-warning d-none" id="mark-principal-approved"><i class="ti ti-file-check me-1"></i>Principal Approved on Paper</button>
                    <button type="button" class="btn btn-success d-none" id="approve-withdrawal"><i class="ti ti-circle-check me-1"></i>Approve Withdrawal</button>
                    <button type="button" class="btn btn-outline-danger d-none" id="reject-withdrawal"><i class="ti ti-ban me-1"></i>Reject Request</button>
                    <button type="button" class="btn btn-outline-secondary d-none" id="cancel-withdrawal"><i class="ti ti-x me-1"></i>Cancel Request</button>
                    <button class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<style>#withdrawal-table td:nth-child(11) .bg-secondary-lt { background-color: #fff3cd !important; color: #b86b00 !important; }.workflow-status-button { border: 0; cursor: pointer; font: inherit; }.workflow-status-button:hover { filter: brightness(.96); }</style>
<script>document.addEventListener('DOMContentLoaded', () => { const table = document.getElementById('withdrawal-table'); if (!table) return; const normalize = () => table.querySelectorAll('td:nth-child(11) .badge:not(.workflow-status-button)').forEach(badge => { if (!badge.textContent.includes('Pending')) return; badge.textContent = 'Pending'; const row = badge.closest('tr'); const editButton = row?.querySelector('.edit-withdrawal'); const button = document.createElement('button'); button.type = 'button'; button.className = 'badge workflow-status-button bg-warning-lt text-warning'; button.textContent = 'Pending'; button.dataset.updateUrl = editButton?.dataset.updateUrl || ''; badge.replaceWith(button); }); new MutationObserver(normalize).observe(table, { childList: true, subtree: true }); normalize(); table.addEventListener('click', async event => { const button = event.target.closest('.workflow-status-button'); if (!button || !button.dataset.updateUrl) return; const confirmation = window.schoolShowConfirm ? await window.schoolShowConfirm('Confirm Principal Approval', 'Confirm that the School Principal has signed the printed withdrawal form? This will officially withdraw the student.', 'Approve Withdrawal', 'Cancel') : { isConfirmed: window.confirm('Confirm that the School Principal has signed the printed withdrawal form? This will officially withdraw the student.') }; if (!confirmation.isConfirmed) return; button.disabled = true; const headers = { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }; const paperResponse = await fetch(button.dataset.updateUrl + '/principal-approved', { method: 'POST', headers, body: JSON.stringify({ paper_signed: true }) }); if (!paperResponse.ok) { button.disabled = false; return; } const approveResponse = await fetch(button.dataset.updateUrl + '/approve', { method: 'POST', headers }); if (!approveResponse.ok) { button.disabled = false; return; } window.location.reload(); }); });</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('withdrawal-table');
    if (!table) return;
    const convertPrincipalApproved = () => table.querySelectorAll('td:nth-child(11) .badge:not(.workflow-status-button)').forEach(badge => {
        if (!badge.textContent.includes('Principal Approved')) return;
        const editButton = badge.closest('tr')?.querySelector('.edit-withdrawal');
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'badge workflow-status-button bg-warning-lt text-warning';
        button.textContent = 'Principal Approved';
        button.dataset.status = 'principal_approved';
        button.dataset.updateUrl = editButton?.dataset.updateUrl || '';
        badge.replaceWith(button);
    });
    new MutationObserver(convertPrincipalApproved).observe(table, { childList: true, subtree: true });
    convertPrincipalApproved();
    table.addEventListener('click', async event => {
        const button = event.target.closest('.workflow-status-button');
        if (!button || !button.dataset.updateUrl) return;
        event.stopImmediatePropagation();
        const liveResponse = await fetch(button.dataset.updateUrl, { headers: { Accept: 'application/json' } });
        const liveResult = await liveResponse.json().catch(() => ({}));
        const liveStatus = liveResult.data?.withdrawal_status;
        if (!['pending', 'principal_approved'].includes(liveStatus)) {
            if (window.Swal) await window.Swal.fire({ title: liveStatus === 'cancelled' ? 'Request Cancelled' : liveStatus === 'rejected' ? 'Request Rejected' : 'Withdrawal Completed', text: liveStatus === 'cancelled' ? 'This request has already been cancelled.' : liveStatus === 'rejected' ? 'This request has already been rejected.' : 'This withdrawal is already completed.', icon: 'info', confirmButtonText: 'Close' });
            return;
        }
        const status = button.dataset.status || (button.textContent.includes('Pending') ? 'pending' : 'principal_approved');
        const choice = await window.Swal.fire({
            title: 'Withdrawal Request',
            text: 'Choose an action for this request.',
            input: 'select',
            inputOptions: { approve: 'Approve Withdrawal', reject: 'Reject Request', cancel: 'Cancel Request' },
            inputValue: 'approve',
            showCancelButton: true,
            confirmButtonText: 'Continue',
            cancelButtonText: 'Close',
            reverseButtons: true,
            icon: 'warning'
        });
        if (!choice.isConfirmed) return;
        let reason = '';
        if (choice.value === 'reject' || choice.value === 'cancel') {
            const reasonResult = await window.Swal.fire({
                title: choice.value === 'reject' ? 'Reject Withdrawal Request' : 'Cancel Withdrawal Request',
                input: 'textarea',
                inputLabel: choice.value === 'reject' ? 'Rejection reason' : 'Cancellation reason',
                inputValidator: value => !value?.trim() ? 'A reason is required.' : undefined,
                showCancelButton: true,
                confirmButtonText: choice.value === 'reject' ? 'Reject Request' : 'Cancel Request',
                cancelButtonText: 'Back',
                reverseButtons: true,
                icon: 'warning'
            });
            if (!reasonResult.isConfirmed) return;
            reason = reasonResult.value;
        }
        button.disabled = true;
        const headers = { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' };
        const endpoint = choice.value === 'approve' ? '/approve' : choice.value === 'reject' ? '/reject' : '/cancel';
        if (choice.value === 'approve' && status === 'pending') {
            const paperResponse = await fetch(button.dataset.updateUrl + '/principal-approved', { method: 'POST', headers, body: JSON.stringify({ paper_signed: true }) });
            if (!paperResponse.ok) { button.disabled = false; return; }
        }
        const response = await fetch(button.dataset.updateUrl + endpoint, { method: 'POST', headers, body: JSON.stringify(choice.value === 'reject' ? { rejection_reason: reason } : choice.value === 'cancel' ? { cancellation_reason: reason } : {}) });
        if (!response.ok) { button.disabled = false; return; }
        window.location.reload();
    }, true);
});
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('withdrawal-table');
    if (!table) return;
    const syncWorkflowLabels = () => table.querySelectorAll('tr').forEach(async row => {
        if (row.dataset.statusLoaded) return;
        const editButton = row.querySelector('.edit-withdrawal');
        const badge = row.querySelector('td:nth-child(11) .badge');
        if (!editButton || !badge) return;
        row.dataset.statusLoaded = 'true';
        const response = await fetch(editButton.dataset.editUrl, { headers: { Accept: 'application/json' } });
        const result = await response.json().catch(() => ({}));
        const status = result.data?.withdrawal_status;
        if (!status) return;
        const labels = { pending: 'Pending', principal_approved: 'Principal Approved', rejected: 'Rejected', cancelled: 'Cancelled', approved: 'Withdrawn' };
        if (!['pending', 'principal_approved'].includes(status) && badge.classList.contains('workflow-status-button')) {
            const replacement = document.createElement('span');
            replacement.className = 'badge';
            replacement.textContent = labels[status] || status;
            replacement.classList.add(...(status === 'approved' ? ['bg-success-lt', 'text-success'] : status === 'rejected' ? ['bg-danger-lt', 'text-danger'] : ['bg-secondary-lt', 'text-secondary']));
            badge.replaceWith(replacement);
            return;
        }
        badge.textContent = labels[status] || status;
        badge.classList.remove('bg-warning-lt', 'text-warning', 'bg-success-lt', 'text-success', 'bg-danger-lt', 'text-danger', 'bg-secondary-lt', 'text-secondary');
        const classes = status === 'approved' ? ['bg-success-lt', 'text-success'] : status === 'rejected' ? ['bg-danger-lt', 'text-danger'] : status === 'cancelled' ? ['bg-secondary-lt', 'text-secondary'] : ['bg-warning-lt', 'text-warning'];
        badge.classList.add(...classes);
    });
    new MutationObserver(syncWorkflowLabels).observe(table, { childList: true, subtree: true });
    syncWorkflowLabels();
});
</script>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal=bootstrap.Modal.getOrCreateInstance(document.getElementById('withdrawalModal')), form=document.getElementById('withdrawalForm'), field=id=>document.getElementById(id), esc=value=>String(value??'').replace(/[&<>"']/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch])); let options={};
    const reasonPanel=document.createElement('div');reasonPanel.className='withdrawal-reason-panel mb-3';reasonPanel.innerHTML='<label class="form-label">Reason for Withdrawal * <small class="text-secondary">(select all that apply)</small></label><div class="row g-2">'+(@json(\App\Http\Controllers\StudentWithdrawalController::REASONS)).map(reason=>'<div class="col-md-6"><label class="form-check"><input class="form-check-input withdrawal-reason-check" type="checkbox" value="'+reason.key+'" data-en="'+esc(reason.en)+'" data-kh="'+esc(reason.kh)+'"><span class="form-check-label"><span class="d-block school-profile-khmer">'+esc(reason.kh)+'</span>'+esc(reason.en)+'</span></label></div>').join('')+'<div class="col-md-6"><label class="form-check"><input class="form-check-input" type="checkbox" id="withdrawal-other-check"><span class="form-check-label">Other</span></label></div></div><div class="row g-2 mt-1 d-none" id="withdrawal-other-fields"><div class="col-md-6"><input id="withdrawal-other-en" class="form-control" placeholder="Other reason (English)"></div><div class="col-md-6"><input id="withdrawal-other-kh" class="form-control school-profile-khmer" placeholder="មូលហេតុផ្សេងទៀត (ខ្មែរ)"></div></div><div class="row g-2 mt-2"><div class="col-md-6"><label class="form-label">Reason (English)</label><input id="withdrawal-reason-en" class="form-control" readonly></div><div class="col-md-6"><label class="form-label school-profile-khmer">មូលហេតុ (ខ្មែរ)</label><input id="withdrawal-reason-kh" class="form-control school-profile-khmer" readonly></div></div>';document.querySelector('#withdrawalForm .modal-body')?.prepend(reasonPanel);field('withdrawal-reason')?.classList.add('d-none');
    const syncReasons=()=>{const selected=Array.from(document.querySelectorAll('.withdrawal-reason-check:checked'));const otherEn=field('withdrawal-other-en')?.value.trim()||'',otherKh=field('withdrawal-other-kh')?.value.trim()||'';const en=selected.map(item=>item.dataset.en).concat(otherEn?[otherEn]:[]).filter(Boolean),kh=selected.map(item=>item.dataset.kh).concat(otherKh?[otherKh]:[]).filter(Boolean);field('withdrawal-reason-en').value=en.join('; ');field('withdrawal-reason-kh').value=kh.join('; ');if(field('withdrawal-reason'))field('withdrawal-reason').value=en.join('; ');};document.addEventListener('change',event=>{if(event.target.matches('.withdrawal-reason-check,#withdrawal-other-check')){field('withdrawal-other-fields')?.classList.toggle('d-none',!field('withdrawal-other-check')?.checked);syncReasons();}});document.addEventListener('input',event=>{if(event.target.matches('#withdrawal-other-en,#withdrawal-other-kh'))syncReasons();});
    ['withdrawal-year-search','withdrawal-campus-search','withdrawal-grade-class-search','withdrawal-student-search'].forEach(id=>{const input=field(id);if(input){input.dataset.searchNormalized='true';input.classList.add('d-none');}});
    const fill=(id,items,label,empty)=>{field(id).innerHTML='<option value="">'+empty+'</option>'+items.map(item=>'<option value="'+item.id+'">'+esc(item[label])+'</option>').join('');};
    const filterSelect=(selectId,searchId)=>{const select=field(selectId),term=(field(searchId)?.value||'').toLowerCase();Array.from(select.options).forEach((option,index)=>{option.hidden=index>0&&!option.text.toLowerCase().includes(term);});};
    const setupSearchableSelect=(id,label)=>{const select=field(id);if(!select||field(id+'-combobox'))return;select.classList.add('d-none');select.insertAdjacentHTML('afterend','<div id="'+id+'-combobox" class="location-combobox withdrawal-search-combobox"><button type="button" id="'+id+'-toggle" class="location-combobox-toggle"><span id="'+id+'-selected" class="location-combobox-selected">Select '+label+'</span><i class="ti ti-chevron-down"></i></button><div id="'+id+'-menu" class="location-combobox-menu d-none"><input id="'+id+'-menu-search" type="search" class="form-control location-combobox-search" placeholder="Search '+label+'"><div id="'+id+'-results" class="location-combobox-results"></div></div></div>');const menu=field(id+'-menu'),searchInput=field(id+'-menu-search'),results=field(id+'-results'),selected=field(id+'-selected');const sync=()=>{selected.textContent=select.value?select.selectedOptions[0]?.textContent||'':'';};const render=()=>{const term=searchInput.value.toLowerCase();const opts=Array.from(select.options).slice(1).filter(option=>!term||option.textContent.toLowerCase().includes(term));results.innerHTML=opts.length?opts.map(option=>'<button type="button" class="location-combobox-option" data-withdrawal-select="'+id+'" data-value="'+option.value+'">'+esc(option.textContent)+'</button>').join(''):'<div class="text-secondary px-2 py-2">No options found</div>';};field(id+'-toggle').onclick=()=>{document.querySelectorAll('.withdrawal-search-combobox .location-combobox-menu').forEach(other=>{if(other!==menu)other.classList.add('d-none');});menu.classList.toggle('d-none');if(!menu.classList.contains('d-none')){searchInput.value='';render();searchInput.focus();}};searchInput.oninput=render;select.addEventListener('change',()=>{sync();render();});results.onclick=event=>{const option=event.target.closest('[data-withdrawal-select]');if(!option)return;select.value=option.dataset.value;select.dispatchEvent(new Event('change',{bubbles:true}));menu.classList.add('d-none');};sync();render();};
    document.addEventListener('click',event=>{if(!event.target.closest('.withdrawal-search-combobox'))document.querySelectorAll('.withdrawal-search-combobox .location-combobox-menu').forEach(menu=>menu.classList.add('d-none'));});
    const studentName=item=>`${item.student?.student_id||item.student?.student_no||''} - ${item.student?.full_name_en||''}`;
    const historyStudentKh = student => student?.full_name_kh || '';
    const historyStudentEn = student => student?.full_name_en || '';
    const historyStudentId = student => student?.student_id || student?.student_no || '-';
    const historyPhoto = student => student?.photo_path ? '<img src="/storage/' + esc(student.photo_path) + '" alt="Student photo" class="withdrawal-history-photo">' : '<span class="withdrawal-history-photo withdrawal-history-photo-placeholder bg-secondary-lt"><i class="ti ti-user"></i></span>';
    const historyDate = value => { const raw = String(value || '').slice(0, 10); const parts = raw.split('-'); if (parts.length !== 3) return value || '-'; const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']; return `${parts[2]}-${months[Math.max(0, Number(parts[1]) - 1)] || parts[1]}-${parts[0]}`; };
    const studentQuery=()=>{const params=new URLSearchParams({campus_id:field('withdrawal-campus').value,academic_year_id:field('withdrawal-year').value,grade_id:field('withdrawal-grade').value,class_id:field('withdrawal-class').value,search:field('withdrawal-student-search').value});return '{{ route('student-withdrawals.students') }}?'+params.toString();};
    const updateGradeClasses=(items)=>{field('withdrawal-grade-class').innerHTML='<option value="">Select Grade + Class</option>'+(items||[]).map(item=>'<option value="'+item.grade_id+'|'+item.class_id+'">'+esc(gradeCode(item.grade)+item.class_name)+'</option>').join('');field('withdrawal-grade').value='';field('withdrawal-class').value='';field('withdrawal-grade-class').dispatchEvent(new Event('change'));};
    const refreshDependentOptions=async()=>{if(!field('withdrawal-year').value){fill('withdrawal-campus',[],'campus_name_en','Select Campus');updateGradeClasses([]);clearStudentResults();return;}const params=new URLSearchParams({academic_year_id:field('withdrawal-year').value,campus_id:field('withdrawal-campus').value});const result=await fetch('{{ route('student-withdrawals.options') }}?'+params.toString()).then(response=>response.json());const currentCampus=field('withdrawal-campus').value;fill('withdrawal-campus',result.campuses||[],'campus_name_en','Select Campus');if((result.campuses||[]).some(item=>String(item.id)===String(currentCampus)))field('withdrawal-campus').value=currentCampus;else field('withdrawal-campus').value='';updateGradeClasses(field('withdrawal-campus').value?result.gradeClasses||[]:[]);renderStudentSelect();};
    const hasAllStudentFilters=()=>Boolean(field('withdrawal-year').value&&field('withdrawal-campus').value&&field('withdrawal-grade').value&&field('withdrawal-class').value);
    const clearStudentResults=()=>{field('withdrawal-enrollment-id').innerHTML='<option value="">Select Student</option>';field('withdrawal-enrollment-id').dispatchEvent(new Event('change'));field('withdrawal-students').innerHTML='<div class="text-secondary small">Select Academic Year, Campus, and Grade + Class first.</div>';};
    const renderStudents=async()=>{if(!hasAllStudentFilters()){clearStudentResults();return;}const rows=(await fetch(studentQuery()).then(response=>response.json())).enrollments||[];const list=field('withdrawal-students');list.innerHTML=rows.length?rows.map(item=>'<label class="form-check mb-1"><input class="form-check-input withdrawal-student" type="checkbox" value="'+item.id+'"><span class="form-check-label">'+esc(studentName(item))+'</span></label>').join(''):'<div class="text-secondary small">No active students found.</div>';};
    const renderStudentSelect=async()=>{if(!hasAllStudentFilters()){clearStudentResults();return;}const result=await fetch(studentQuery()).then(response=>response.json());const enrollments=result.enrollments||[];window.withdrawalEnrollmentFamilies=Object.fromEntries(enrollments.map(item=>[String(item.id),item.family_members||[]]));field('withdrawal-enrollment-id').innerHTML='<option value="">Select Student</option>'+enrollments.map(item=>'<option value="'+item.id+'" data-family-members="'+esc(JSON.stringify(item.family_members||[]))+'">'+esc(studentName(item))+'</option>').join('');field('withdrawal-enrollment-id').dispatchEvent(new Event('change'));};
    const updateType=()=>{const type=field('withdrawal-type').value, bulk=type!=='student';document.querySelector('.student-withdrawal-field').classList.toggle('d-none',bulk);document.querySelector('.selected-withdrawal-list').classList.toggle('d-none',type!=='selected');if(type!=='student')renderStudents();};
    const gradeCode=grade=>{const value=String(grade||'').trim();if(value.toLowerCase()==='nursery')return 'N';return value.replace(/^Grade\s+/i,'');};
    const loadOptions=async()=>{options=await fetch('{{ route('student-withdrawals.options') }}').then(r=>r.json());fill('withdrawal-campus',[],'campus_name_en','Select Campus');fill('withdrawal-year',options.academicYears||[],'academic_year','Select Academic Year');fill('withdrawal-grade',options.grades||[],'grade','Select Grade');fill('withdrawal-class',options.classes||[],'class_name','Select Class');field('withdrawal-grade-class').innerHTML='<option value="">Select Grade + Class</option>';setupSearchableSelect('withdrawal-year','Academic Year');setupSearchableSelect('withdrawal-campus','Campus');setupSearchableSelect('withdrawal-grade-class','Grade + Class');setupSearchableSelect('withdrawal-enrollment-id','Student Name');field('withdrawal-grade-class').onchange=()=>{const [gradeId,classId]=field('withdrawal-grade-class').value.split('|');field('withdrawal-grade').value=gradeId||'';field('withdrawal-class').value=classId||'';renderStudentSelect();renderStudents();};clearStudentResults();};
    field('withdrawal-type').onchange=updateType;field('withdrawal-year').onchange=refreshDependentOptions;field('withdrawal-campus').onchange=refreshDependentOptions;field('withdrawal-student-search').classList.add('d-none');field('withdrawal-student-search').oninput=renderStudentSelect;[['withdrawal-year','withdrawal-year-search'],['withdrawal-campus','withdrawal-campus-search'],['withdrawal-grade-class','withdrawal-grade-class-search']].forEach(([selectId,searchId])=>field(searchId).oninput=()=>filterSelect(selectId,searchId));
    document.getElementById('newWithdrawal').onclick=async()=>{form.reset();(window.withdrawalDatePicker?.setValue||((value)=>field('withdrawal-date').value=value))(new Date().toISOString().slice(0,10));field('withdrawal-error').classList.add('d-none');modal.show();updateType();await loadOptions();updateType();};
    form.onsubmit=async event=>{event.preventDefault();const type=field('withdrawal-type').value;const common={withdrawal_date:field('withdrawal-date').value,reason:field('withdrawal-reason').value,notes:field('withdrawal-notes').value};let endpoint,payload;if(type==='student'){endpoint='withdraw';payload={...common,enrollment_id:field('withdrawal-enrollment-id').value};}else{payload={...common,from_campus_id:field('withdrawal-campus').value,from_academic_year_id:field('withdrawal-year').value,from_grade_id:field('withdrawal-grade').value,from_class_id:field('withdrawal-class').value};if(type==='selected'){endpoint='withdraw-selected';payload.enrollment_ids=Array.from(document.querySelectorAll('.withdrawal-student:checked')).map(input=>Number(input.value));}else endpoint='withdraw-class';}const response=await fetch('{{ url('/student-withdrawals') }}/'+endpoint,{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},body:JSON.stringify(payload)});const result=await response.json();if(!response.ok){field('withdrawal-error').textContent=result.message||Object.values(result.errors||{})[0]?.[0]||'Unable to withdraw student.';field('withdrawal-error').classList.remove('d-none');return;}modal.hide();loadHistory();};
    const historyFilters = [
        { key: 'academic_year_id', label: 'Academic Year', container: 'withdrawal-history-year-filter', value: item => item.id, text: item => item.academic_year },
        { key: 'campus_id', label: 'Campus', container: 'withdrawal-history-campus-filter', value: item => item.id, text: item => item.campus_name_en },
        { key: 'grade_class', label: 'Grade + Class', container: 'withdrawal-history-grade-filter', value: item => item.grade_id + '|' + item.class_id, text: item => gradeCode(item.grade) + item.class_name },
        { key: 'session_id', label: 'Group', container: 'withdrawal-history-group-filter', value: item => item.id, text: item => item.session_short_name || item.session_name },
        { key: 'student_id', label: 'Student Name', container: 'withdrawal-history-student-filter', value: item => item.id, text: item => item.full_name_en || '' },
    ];
    let historySort = 'date', historyDirection = 'desc', historyPerPage = 10;
    const historySelects = {};
    const setupHistoryFilter = (config, items) => {
        const container = field(config.container); if (!container) return;
        const selectId = config.container + '-select';
        container.innerHTML = '<select id="' + selectId + '" class="d-none"><option value="">All ' + config.label + '</option>' + (items || []).map(item => '<option value="' + esc(config.value(item)) + '">' + esc(config.text(item)) + '</option>').join('') + '</select><div class="location-combobox withdrawal-history-combobox"><button type="button" class="location-combobox-toggle"><span class="location-combobox-selected">All ' + config.label + '</span><i class="ti ti-chevron-down"></i></button><div class="location-combobox-menu d-none"><input type="search" class="form-control location-combobox-search" placeholder="Search ' + config.label + '"><div class="location-combobox-results"></div></div></div>';
        const select = field(selectId), combo = container.querySelector('.withdrawal-history-combobox'), toggle = combo.querySelector('.location-combobox-toggle'), menu = combo.querySelector('.location-combobox-menu'), search = combo.querySelector('.location-combobox-search'), results = combo.querySelector('.location-combobox-results'), selected = combo.querySelector('.location-combobox-selected');
        historySelects[config.key] = select;
        const render = () => { const term = search.value.toLowerCase().trim(); const options = Array.from(select.options).slice(1).filter(option => !term || option.textContent.toLowerCase().includes(term)); const allOption = '<button type="button" class="location-combobox-option" data-value="">All ' + config.label + '</button>'; results.innerHTML = allOption + (options.length ? options.map(option => '<button type="button" class="location-combobox-option" data-value="' + esc(option.value) + '">' + esc(option.textContent) + '</button>').join('') : '<div class="text-secondary px-2 py-2">No options found</div>'); };
        toggle.onclick = event => { event.stopPropagation(); document.querySelectorAll('.withdrawal-history-combobox .location-combobox-menu').forEach(other => { if (other !== menu) other.classList.add('d-none'); }); menu.classList.toggle('d-none'); if (!menu.classList.contains('d-none')) { search.value = ''; render(); search.focus(); } };
        search.oninput = render;
        results.onclick = event => { const option = event.target.closest('[data-value]'); if (!option) return; select.value = option.dataset.value; selected.textContent = select.value ? select.selectedOptions[0].textContent : 'All ' + config.label; menu.classList.add('d-none'); refreshHistoryOptions(config.key).then(() => loadHistory(1)); };
    };
    const historyOptionKey = key => key === 'academic_year_id' ? 'academicYears' : key === 'campus_id' ? 'campuses' : key === 'grade_class' ? 'gradeClasses' : key === 'session_id' ? 'groups' : 'students';
    const refreshHistoryOptions = async changedKey => {
        const order = ['academic_year_id', 'campus_id', 'grade_class', 'session_id', 'student_id'];
        const changedIndex = order.indexOf(changedKey);
        const currentValues = Object.fromEntries(order.map(key => [key, historySelects[key]?.value || '']));
        order.slice(changedIndex + 1).forEach(key => { if (historySelects[key]) historySelects[key].value = ''; currentValues[key] = ''; });
        const params = new URLSearchParams();
        if (currentValues.academic_year_id) params.set('academic_year_id', currentValues.academic_year_id);
        if (currentValues.campus_id) params.set('campus_id', currentValues.campus_id);
        if (currentValues.grade_class) { const [gradeId, classId] = currentValues.grade_class.split('|'); params.set('grade_id', gradeId); params.set('class_id', classId); }
        if (currentValues.session_id) params.set('session_id', currentValues.session_id);
        const data = await fetch('{{ route('student-withdrawals.history-options') }}?' + params.toString()).then(response => response.json());
        historyFilters.forEach(config => { const value = currentValues[config.key] || ''; setupHistoryFilter(config, data[historyOptionKey(config.key)] || []); const select = historySelects[config.key]; if (value && Array.from(select.options).some(option => option.value === value)) { select.value = value; const display = field(config.container)?.querySelector('.location-combobox-selected'); if (display) display.textContent = select.selectedOptions[0].textContent; } });
    };
    document.addEventListener('click', event => { if (!event.target.closest('.withdrawal-history-combobox')) document.querySelectorAll('.withdrawal-history-combobox .location-combobox-menu').forEach(menu => menu.classList.add('d-none')); });
    const historyParams = page => { const params = new URLSearchParams({ page, perPage: historyPerPage, search: field('withdrawal-search').value, sortBy: historySort, sortDir: historyDirection }); Object.entries(historySelects).forEach(([key, select]) => { if (!select?.value) return; if (key === 'grade_class') { const [gradeId, classId] = select.value.split('|'); params.set('grade_id', gradeId); params.set('class_id', classId); } else params.set(key, select.value); }); return params; };
    const renderHistoryPagination = result => { const pagination = field('withdrawal-pagination'); if (!pagination) return; const current = result.current_page || 1, last = result.last_page || 1; const pages = last <= 5 ? Array.from({ length: last }, (_, index) => index + 1) : current <= 3 ? [1, 2, 3, '…', last] : current >= last - 2 ? [1, '…', last - 2, last - 1, last] : [1, '…', current - 1, current, current + 1, '…', last]; pagination.innerHTML = '<div class="premium-pagination"><ul class="pagination premium-pagination-list m-0"><li class="page-item ' + (current === 1 ? 'disabled' : '') + '"><a class="page-link withdrawal-page" href="#" data-page="' + (current - 1) + '"><i class="ti ti-chevron-left"></i></a></li>' + pages.map(page => page === '…' ? '<li class="premium-pagination-ellipsis">…</li>' : '<li class="page-item ' + (page === current ? 'active' : '') + '"><a class="page-link withdrawal-page" href="#" data-page="' + page + '">' + page + '</a></li>').join('') + '<li class="page-item ' + (current === last ? 'disabled' : '') + '"><a class="page-link withdrawal-page" href="#" data-page="' + (current + 1) + '"><i class="ti ti-chevron-right"></i></a></li></ul><p class="premium-pagination-info m-0">Showing <strong>' + (result.from || 0) + ' to ' + (result.to || 0) + '</strong> of <strong>' + (result.total || 0) + ' entries</strong></p><div class="premium-pagination-controls"><label class="premium-pagination-select"><select class="form-select form-select-sm"><option value="10" ' + (historyPerPage === 10 ? 'selected' : '') + '>10 / page</option><option value="25" ' + (historyPerPage === 25 ? 'selected' : '') + '>25 / page</option><option value="50" ' + (historyPerPage === 50 ? 'selected' : '') + '>50 / page</option><option value="100" ' + (historyPerPage === 100 ? 'selected' : '') + '>100 / page</option></select></label></div></div>'; pagination.querySelectorAll('.withdrawal-page').forEach(button => button.onclick = event => { event.preventDefault(); const page = Number(button.dataset.page); if (page >= 1 && page <= last && page !== current) loadHistory(page); }); pagination.querySelector('select')?.addEventListener('change', event => { historyPerPage = Number(event.target.value); loadHistory(1); }); };
const loadHistory = async (page = 1) => { const result = await fetch('{{ route('student-withdrawals.fetch') }}?' + historyParams(page).toString()).then(response => response.json()); field('withdrawal-table').innerHTML = result.data?.length ? result.data.map((item, index) => { const student = item.student || {}; const nameKh = historyStudentKh(student); const nameEn = historyStudentEn(student); const gradeClass = gradeCode(item.grade?.grade) + (item.school_class?.class_name || ''); const track = item.academic_track?.name_en || item.academic_track?.code || ''; const requestedName = item.requested_by_name || '-'; const requestedPhone = item.requested_by_phone || ''; const withdrawalStatus = item.withdrawal_status || (item.enrollment_status === 'withdrawn' ? 'approved' : 'pending'); const statusLabel = withdrawalStatus === 'principal_approved' ? 'Principal Approved' : withdrawalStatus === 'approved' ? 'Withdrawn' : 'Pending · Active'; const statusClass = withdrawalStatus === 'approved' ? 'bg-success-lt text-success' : withdrawalStatus === 'principal_approved' ? 'bg-warning-lt text-warning' : 'bg-secondary-lt text-secondary'; return '<tr><td>' + esc((result.from || 1) + index) + '</td><td>' + historyPhoto(student) + '</td><td class="text-nowrap">' + esc(historyStudentId(student)) + '</td><td class="withdrawal-history-name"><div class="school-profile-khmer text-secondary small">' + esc(nameKh || '-') + '</div><div>' + esc(nameEn || '-') + '</div><div class="withdrawal-history-phone text-secondary">' + esc(student.home_phone || '-') + '</div></td><td>' + esc(item.academic_year?.academic_year || '-') + '</td><td><div class="fw-semibold">' + esc(gradeClass || '-') + '</div><div class="withdrawal-history-track text-secondary">' + esc(track || '-') + '</div></td><td>' + esc(item.session?.session_short_name || item.session?.session_name || '-') + '</td><td class="withdrawal-history-date">' + esc(historyDate(item.effective_on)) + '</td><td><div class="fw-semibold">' + esc(requestedName) + '</div><div class="text-secondary small">' + esc(requestedPhone || '-') + '</div></td><td>' + esc(item.changed_by?.name || 'System') + '</td><td><span class="badge ' + statusClass + '">' + esc(statusLabel) + '</span></td><td class="text-nowrap"><div class="d-inline-flex gap-1"><button type="button" class="btn btn-sm btn-outline-secondary edit-withdrawal" data-edit-url="' + esc(item.edit_url || '#') + '" data-update-url="' + esc(item.update_url || '#') + '" title="Edit"><i class="ti ti-edit"></i></button><a class="btn btn-sm btn-outline-primary" href="' + esc(item.form_url || '#') + '" target="_blank" rel="noopener" title="Print"><i class="ti ti-printer"></i></a></div></td></tr>'; }).join('') : '<tr><td colspan="12" class="text-center">No withdrawal history found.</td></tr>'; renderHistoryPagination(result); };
    document.querySelectorAll('[data-withdrawal-sort]').forEach(button => button.onclick = () => { const selected = button.dataset.withdrawalSort; historyDirection = historySort === selected && historyDirection === 'asc' ? 'desc' : 'asc'; historySort = selected; loadHistory(1); });
    let searchTimer; field('withdrawal-search').oninput = () => { clearTimeout(searchTimer); searchTimer = setTimeout(() => loadHistory(1), 300); };
    fetch('{{ route('student-withdrawals.history-options') }}').then(response => response.json()).then(data => { historyFilters.forEach(config => setupHistoryFilter(config, data[config.key === 'academic_year_id' ? 'academicYears' : config.key === 'campus_id' ? 'campuses' : config.key === 'grade_class' ? 'gradeClasses' : config.key === 'session_id' ? 'groups' : 'students'] || [])); loadHistory(); }).catch(() => loadHistory());
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('withdrawalForm');
    if (!form) return;
    const field = id => document.getElementById(id);
    const reasonPanel = document.querySelector('.withdrawal-reason-panel');
    const reasonHeading = reasonPanel?.querySelector('.form-label');
    if (reasonHeading) {
        reasonHeading.outerHTML = '<h5 class="mb-3">REASONS FOR WITHDRAWAL: <small class="text-secondary fw-normal">(Select all that apply)</small></h5>';
    }
    const legacyReason = document.getElementById('withdrawal-reason');
    const legacyReasonColumn = legacyReason?.closest('.col-md-6');
    if (reasonPanel && legacyReasonColumn) {
        legacyReasonColumn.classList.add('d-none');
        legacyReasonColumn.before(reasonPanel);
    }
    document.getElementById('withdrawal-date')?.closest('.col-md-6')?.classList.add('withdrawal-date-column');
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[character]));
    const reinforceSearchable = (id, label) => {
        const select = document.getElementById(id);
        if (!select) return;
        let combo = document.getElementById(id + '-combobox');
        if (!combo) {
            select.classList.add('d-none');
            select.insertAdjacentHTML('afterend', '<div id="' + id + '-combobox" class="location-combobox withdrawal-search-combobox"><button type="button" id="' + id + '-toggle" class="location-combobox-toggle"><span id="' + id + '-selected" class="location-combobox-selected">Select ' + label + '</span><i class="ti ti-chevron-down"></i></button><div id="' + id + '-menu" class="location-combobox-menu d-none"><input id="' + id + '-menu-search" type="search" class="form-control location-combobox-search" placeholder="Search ' + label + '"><div id="' + id + '-results" class="location-combobox-results"></div></div></div>');
            combo = document.getElementById(id + '-combobox');
        }
        const search = document.getElementById(id + '-menu-search');
        const results = document.getElementById(id + '-results');
        const selectedDisplay = document.getElementById(id + '-selected');
        if (selectedDisplay && !select.value) selectedDisplay.textContent = '';
        const render = () => {
            const term = (search?.value || '').toLowerCase().trim();
            const items = Array.from(select.options).slice(1).filter(option => !term || option.textContent.toLowerCase().includes(term));
            if (results) results.innerHTML = items.length ? items.map(option => '<button type="button" class="location-combobox-option" data-withdrawal-select="' + id + '" data-value="' + escapeHtml(option.value) + '">' + escapeHtml(option.textContent) + '</button>').join('') : '<div class="text-secondary px-2 py-2">No options found</div>';
        };
        if (search) search.oninput = render;
        document.getElementById(id + '-toggle')?.addEventListener('click', () => { document.querySelectorAll('.withdrawal-search-combobox .location-combobox-menu').forEach(menu => { if (menu.id !== id + '-menu') menu.classList.add('d-none'); }); document.getElementById(id + '-menu')?.classList.toggle('d-none'); render(); search?.focus(); });
        results?.addEventListener('click', event => { const option = event.target.closest('[data-withdrawal-select]'); if (!option) return; select.value = option.dataset.value; select.dispatchEvent(new Event('change', { bubbles: true })); document.getElementById(id + '-menu')?.classList.add('d-none'); });
            select.addEventListener('change', () => { const selected = document.getElementById(id + '-selected'); if (selected) selected.textContent = select.value ? select.selectedOptions[0]?.textContent || '' : ''; render(); });
        render();
    };
    ['withdrawal-year', 'withdrawal-campus', 'withdrawal-grade-class'].forEach(id => reinforceSearchable(id, id));
    fetch('{{ route('student-withdrawals.options') }}').then(response => response.json()).then(data => {
        const activeReasons = data.reasons || [];
        const reasonRow = reasonPanel?.querySelector('.row.g-2');
        if (!reasonRow || !activeReasons.length) return;
        reasonRow.innerHTML = activeReasons.map(reason => '<div class="col-md-6"><label class="form-check"><input class="form-check-input withdrawal-reason-check" type="checkbox" value="' + escapeHtml(reason.key) + '" data-en="' + escapeHtml(reason.en) + '" data-kh="' + escapeHtml(reason.kh) + '"><span class="form-check-label"><span class="d-block school-profile-khmer">' + escapeHtml(reason.kh) + '</span>' + escapeHtml(reason.en) + '</span></label></div>').join('') + '<div class="col-md-6"><label class="form-check"><input class="form-check-input" type="checkbox" id="withdrawal-other-check"><span class="form-check-label">Other</span></label></div>';
    });
    if (reasonPanel && !field('withdrawal-new-school')) {
        reasonPanel.insertAdjacentHTML('beforeend', '<div class="row g-2 mt-3"><div class="col-md-6"><label class="form-label">New School</label><input id="withdrawal-new-school" class="form-control"></div><div class="col-md-6"><label class="form-label">New School Address</label><input id="withdrawal-new-school-address" class="form-control"></div><div class="col-md-6"><label class="form-label">Withdrawal Status * <small class="text-secondary fw-normal">(select one only)</small></label><select id="withdrawal-dropout-type" class="form-select" required><option value="official_leave">Will officially leave</option><option value="dropped_out">Has dropped out</option></select></div><div class="col-md-6"><label class="form-label">Additional Comments</label><input id="withdrawal-additional-comments" class="form-control"></div></div>');
    }
    if (reasonPanel && !field('withdrawal-requested-by-type')) {
        reasonPanel.insertAdjacentHTML('beforeend', '<div class="withdrawal-requested-by mt-3"><h4 class="mb-2 fw-bold">REQUESTED BY</h4><div class="row g-2"><div class="col-md-4"><label class="form-label">Requested By *</label><select id="withdrawal-requested-by-type" class="form-select" required><option value=""></option><option value="mother">Mother</option><option value="father">Father</option><option value="guardian">Guardian</option></select></div><div class="col-md-4"><label class="form-label">Requested By Name *</label><input id="withdrawal-requested-by-name" class="form-control" required></div><div class="col-md-4"><label class="form-label">Requested By Phone *</label><input id="withdrawal-requested-by-phone" class="form-control" required></div></div></div>');
    }
    const cleanPhone = value => String(value || '').replace(/\s+/g, '');
    const memberName = member => String(member?.name || '').trim();
    const getRequestedMembers = () => {
        const select = field('withdrawal-enrollment-id');
        const selectedId = String(select?.value || window.currentWithdrawalEnrollmentId || '');
        const mapped = window.withdrawalEnrollmentFamilies?.[selectedId];
        if (mapped?.length) return mapped;
        try {
            return JSON.parse(select?.selectedOptions?.[0]?.dataset?.familyMembers || '[]');
        } catch (error) {
            return [];
        }
    };
    const ensureRequestedMembers = async () => {
        const select = field('withdrawal-enrollment-id');
        const selectedId = String(select?.value || window.currentWithdrawalEnrollmentId || '');
        if (!selectedId) return [];
        const existing = getRequestedMembers();
        if (existing.length) return existing;
        const result = await fetch('{{ url('/student-withdrawals/enrollments') }}/' + selectedId + '/family', { headers: { Accept: 'application/json' } }).then(response => response.json()).catch(() => ({ family_members: [] }));
        window.withdrawalEnrollmentFamilies = window.withdrawalEnrollmentFamilies || {};
        window.withdrawalEnrollmentFamilies[selectedId] = result.family_members || [];
        return window.withdrawalEnrollmentFamilies[selectedId];
    };
    const getRequestedMember = type => getRequestedMembers().find(member => member.relationship_type === type) || null;
    const setRequestedCardData = () => {
        ['mother', 'father', 'guardian'].forEach(type => {
            const member = getRequestedMember(type);
            const name = memberName(member);
            const phone = cleanPhone(member?.phone || '');
            if (field('withdrawal-' + type + '-name')) field('withdrawal-' + type + '-name').textContent = name || 'No ' + type + ' information';
            if (field('withdrawal-' + type + '-phone')) field('withdrawal-' + type + '-phone').textContent = phone || '-';
            if (type === 'guardian') {
                if (field('withdrawal-guardian-preview-name')) field('withdrawal-guardian-preview-name').textContent = name || 'Manual / existing guardian';
                if (field('withdrawal-guardian-preview-phone')) field('withdrawal-guardian-preview-phone').textContent = phone || 'Editable';
            }
        });
    };
    const applyRequestedBy = async type => {
        await ensureRequestedMembers();
        const member = getRequestedMember(type);
        const nameInput = field('withdrawal-requested-by-name');
        const phoneInput = field('withdrawal-requested-by-phone');
        if (field('withdrawal-requested-by-type')) field('withdrawal-requested-by-type').value = type || '';
        if (!nameInput || !phoneInput) return;
        nameInput.readOnly = type !== 'guardian';
        phoneInput.readOnly = type !== 'guardian';
        if (type === 'guardian') {
            nameInput.readOnly = false;
            phoneInput.readOnly = false;
            nameInput.value = '';
            phoneInput.value = '';
        } else {
            nameInput.value = memberName(member);
            phoneInput.value = cleanPhone(member?.phone || '');
        }
    };
    document.addEventListener('change', event => {
        if (event.target.matches('#withdrawal-requested-by-type')) void applyRequestedBy(event.target.value);
        if (event.target.matches('#withdrawal-enrollment-id')) {
            window.currentWithdrawalEnrollmentId = event.target.value || '';
            setRequestedCardData();
            void applyRequestedBy(field('withdrawal-requested-by-type')?.value || '');
            setTimeout(() => void applyRequestedBy(field('withdrawal-requested-by-type')?.value || ''), 0);
        }
    });
    document.addEventListener('click', event => {
        const option = event.target.closest('[data-withdrawal-select="withdrawal-enrollment-id"]');
        if (!option) return;
        window.currentWithdrawalEnrollmentId = option.dataset.value || '';
        setTimeout(() => void applyRequestedBy(field('withdrawal-requested-by-type')?.value || ''), 50);
    });
    document.addEventListener('input', event => {
        if (event.target.matches('#withdrawal-requested-by-phone')) event.target.value = cleanPhone(event.target.value);
    });
    const syncReasons = () => {
        const selected = Array.from(document.querySelectorAll('.withdrawal-reason-check:checked'));
        const otherEn = field('withdrawal-other-en')?.value.trim() || '';
        const otherKh = field('withdrawal-other-kh')?.value.trim() || '';
        const en = selected.map(item => item.dataset.en).concat(otherEn ? [otherEn] : []).filter(Boolean);
        const kh = selected.map(item => item.dataset.kh).concat(otherKh ? [otherKh] : []).filter(Boolean);
        if (field('withdrawal-reason-en')) field('withdrawal-reason-en').value = en.join('; ');
        if (field('withdrawal-reason-kh')) field('withdrawal-reason-kh').value = kh.join('; ');
        if (field('withdrawal-reason')) field('withdrawal-reason').value = en.join('; ');
    };
    document.addEventListener('change', event => {
        if (event.target.matches('.withdrawal-reason-check, #withdrawal-other-check')) {
            field('withdrawal-other-fields')?.classList.toggle('d-none', !field('withdrawal-other-check')?.checked);
            syncReasons();
        }
    });
    document.addEventListener('input', event => {
        if (event.target.matches('#withdrawal-other-en, #withdrawal-other-kh')) syncReasons();
    });
    const originalNew = document.getElementById('newWithdrawal');
    originalNew?.addEventListener('click', () => setTimeout(syncReasons, 0));
    form.onsubmit = async event => {
        event.preventDefault();
        syncReasons();
        const value = id => field(id)?.value || '';
        const type = value('withdrawal-type');
        const common = { withdrawal_date: value('withdrawal-date'), reason: value('withdrawal-reason'), reasons: Array.from(document.querySelectorAll('.withdrawal-reason-check:checked')).map(input => input.value), reason_kh: value('withdrawal-reason-kh'), other_reason_en: value('withdrawal-other-en'), other_reason_kh: value('withdrawal-other-kh'), new_school: value('withdrawal-new-school'), new_school_address: value('withdrawal-new-school-address'), dropout_type: value('withdrawal-dropout-type'), requested_by_type: value('withdrawal-requested-by-type'), requested_by_name: value('withdrawal-requested-by-name'), requested_by_phone: cleanPhone(value('withdrawal-requested-by-phone')), additional_comments: value('withdrawal-additional-comments'), notes: value('withdrawal-notes') };
        let endpoint, payload;
        if (type === 'student') { endpoint = 'withdraw'; payload = { ...common, enrollment_id: value('withdrawal-enrollment-id') }; }
        else { payload = { ...common, from_campus_id: value('withdrawal-campus'), from_academic_year_id: value('withdrawal-year'), from_grade_id: value('withdrawal-grade'), from_class_id: value('withdrawal-class') }; if (type === 'selected') { endpoint = 'withdraw-selected'; payload.enrollment_ids = Array.from(document.querySelectorAll('.withdrawal-student:checked')).map(input => Number(input.value)); } else endpoint = 'withdraw-class'; }
        const response = await fetch('{{ url('/student-withdrawals') }}/' + endpoint, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, body: JSON.stringify(payload) });
        const result = await response.json();
        if (!response.ok) { const error = field('withdrawal-error'); error.textContent = result.message || Object.values(result.errors || {})[0]?.[0] || 'Unable to withdraw student.'; error.classList.remove('d-none'); return; }
        bootstrap.Modal.getOrCreateInstance(document.getElementById('withdrawalModal')).hide();
        if (result.form_url && type === 'student') window.open(result.form_url, '_blank');
        window.location.reload();
    };
});
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const parseWithdrawalDate = value => {
        const raw = String(value || '').trim();
        if (!raw) return null;
        let match = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (match) return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
        match = raw.match(/^(\d{1,2})[-/](\d{1,2})[-/](\d{4})$/);
        if (match) return new Date(Number(match[3]), Number(match[2]) - 1, Number(match[1]));
        return null;
    };
    const formatWithdrawalIso = date => date ? `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}` : '';
    const formatWithdrawalDisplay = value => {
        const date = parseWithdrawalDate(value);
        return date ? `${String(date.getDate()).padStart(2, '0')}-${String(date.getMonth() + 1).padStart(2, '0')}-${date.getFullYear()}` : '';
    };
    const setupWithdrawalDatePicker = prefix => {
        const picker = document.getElementById(prefix + '_picker');
        const hidden = document.getElementById(prefix.replace(/_/g, '-') === 'withdrawal-date' ? 'withdrawal-date' : 'edit-withdrawal-date') || document.getElementById(prefix);
        const direct = document.getElementById(prefix + '_direct');
        const trigger = document.getElementById(prefix + '_trigger');
        const popup = document.getElementById(prefix + '_popup');
        const days = document.getElementById(prefix + '_days');
        const label = document.getElementById(prefix + '_month_label');
        const yearToggle = document.getElementById(prefix + '_year_toggle');
        const yearPopup = document.getElementById(prefix + '_year_popup');
        const years = document.getElementById(prefix + '_years');
        const prev = document.getElementById(prefix + '_prev');
        const next = document.getElementById(prefix + '_next');
        if (!picker || !hidden || !direct || !trigger || !popup || !days) return null;
        let cursor = new Date();
        const setValue = value => {
            const date = parseWithdrawalDate(value);
            const iso = date ? formatWithdrawalIso(date) : '';
            hidden.value = iso;
            direct.value = formatWithdrawalDisplay(iso);
        };
        const renderYears = () => {
            if (!years) return;
            const current = cursor.getFullYear();
            const start = 1990;
            const end = new Date().getFullYear() + 10;
            years.innerHTML = Array.from({ length: end - start + 1 }, (_, index) => {
                const year = start + index;
                return `<button type="button" class="date-picker-year${year === current ? ' is-selected' : ''}" data-withdrawal-year="${year}">${year}</button>`;
            }).join('');
        };
        const renderCalendar = () => {
            const year = cursor.getFullYear();
            const month = cursor.getMonth();
            if (label) label.textContent = cursor.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
            renderYears();
            const first = new Date(year, month, 1);
            const start = first.getDay();
            const count = new Date(year, month + 1, 0).getDate();
            const selected = parseWithdrawalDate(hidden.value);
            const cells = [];
            for (let i = 0; i < start; i += 1) cells.push(new Date(year, month, i - start + 1));
            for (let day = 1; day <= count; day += 1) cells.push(new Date(year, month, day));
            while (cells.length < 42) cells.push(new Date(year, month + 1, cells.length - start - count + 1));
            days.innerHTML = cells.map(date => {
                const iso = formatWithdrawalIso(date);
                const outside = date.getMonth() !== month ? ' is-outside' : '';
                const active = selected && iso === formatWithdrawalIso(selected) ? ' is-selected' : '';
                return `<button type="button" class="date-picker-day${outside}${active}" data-withdrawal-date="${iso}">${date.getDate()}</button>`;
            }).join('');
        };
        trigger.addEventListener('click', event => {
            event.stopPropagation();
            const selected = parseWithdrawalDate(hidden.value);
            cursor = selected ? new Date(selected.getFullYear(), selected.getMonth(), 1) : new Date(new Date().getFullYear(), new Date().getMonth(), 1);
            popup.classList.toggle('d-none');
            yearPopup?.classList.add('d-none');
            if (!popup.classList.contains('d-none')) renderCalendar();
        });
        yearToggle?.addEventListener('click', event => { event.stopPropagation(); yearPopup?.classList.toggle('d-none'); renderYears(); });
        years?.addEventListener('click', event => {
            event.preventDefault();
            event.stopPropagation();
            const button = event.target.closest('[data-withdrawal-year]');
            if (!button) return;
            cursor = new Date(Number(button.dataset.withdrawalYear), cursor.getMonth(), 1);
            yearPopup?.classList.add('d-none');
            popup.classList.remove('d-none');
            renderCalendar();
        });
        prev?.addEventListener('click', () => { cursor = new Date(cursor.getFullYear(), cursor.getMonth() - 1, 1); yearPopup?.classList.add('d-none'); renderCalendar(); });
        next?.addEventListener('click', () => { cursor = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 1); yearPopup?.classList.add('d-none'); renderCalendar(); });
        days.addEventListener('click', event => {
            const button = event.target.closest('[data-withdrawal-date]');
            if (!button) return;
            setValue(button.dataset.withdrawalDate);
            popup.classList.add('d-none');
            yearPopup?.classList.add('d-none');
        });
        direct.addEventListener('change', event => setValue(event.target.value));
        document.addEventListener('click', event => { if (!picker.contains(event.target)) popup.classList.add('d-none'); });
        return { setValue };
    };
    window.withdrawalDatePicker = setupWithdrawalDatePicker('withdrawal_date');
    window.editWithdrawalDatePicker = setupWithdrawalDatePicker('edit_withdrawal_date');

    const heading = document.querySelector('.withdrawal-reason-panel > .form-label, .withdrawal-reason-panel > h5');
    if (heading) heading.outerHTML = '<h4 class="mb-3 fw-bold">REASONS FOR WITHDRAWAL: <small class="text-secondary fw-normal">(Select all that apply)</small></h4>';
});
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modalElement = document.getElementById('editWithdrawalModal');
    const form = document.getElementById('editWithdrawalForm');
    if (!modalElement || !form) return;

    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const field = id => document.getElementById(id);
    const esc = value => String(value ?? '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[character]));
    const cleanPhone = value => String(value || '').replace(/\s+/g, '');
    let editFamilyMembers = [];
    const getEditMember = type => editFamilyMembers.find(member => member.relationship_type === type) || null;
    const applyEditRequestedBy = type => {
        const member = getEditMember(type);
        const nameInput = field('edit-requested-by-name');
        const phoneInput = field('edit-requested-by-phone');
        if (!nameInput || !phoneInput) return;
        nameInput.readOnly = type !== 'guardian';
        phoneInput.readOnly = type !== 'guardian';
        if (type === 'mother' || type === 'father') {
            nameInput.value = member?.name || '';
            phoneInput.value = cleanPhone(member?.phone || '');
        } else {
            nameInput.readOnly = false;
            phoneInput.readOnly = false;
            nameInput.value = '';
            phoneInput.value = '';
        }
    };
    const checkedReasons = () => Array.from(document.querySelectorAll('.edit-withdrawal-reason-check:checked'));
    const syncEditReasons = () => {
        const selected = checkedReasons();
        const otherEn = field('edit-other-en')?.value.trim() || '';
        const otherKh = field('edit-other-kh')?.value.trim() || '';
        const data = {
            reason: selected.map(input => input.dataset.en).concat(otherEn ? [otherEn] : []).filter(Boolean).join('; '),
            reason_kh: selected.map(input => input.dataset.kh).concat(otherKh ? [otherKh] : []).filter(Boolean).join('; '),
        };
        if (field('edit-reason-en')) field('edit-reason-en').value = data.reason;
        if (field('edit-reason-kh')) field('edit-reason-kh').value = data.reason_kh;
        return data;
    };

    document.addEventListener('click', async event => {
        const button = event.target.closest('.edit-withdrawal');
        if (!button) return;

        modal.show();
        const error = field('edit-withdrawal-error');
        error.classList.add('d-none');
        form.dataset.updateUrl = button.dataset.updateUrl;
        form.reset();
        document.querySelectorAll('.edit-other-field').forEach(item => item.classList.add('d-none'));

        const response = await fetch(button.dataset.editUrl, { headers: { Accept: 'application/json' } });
        const result = await response.json().catch(() => ({}));
        if (!response.ok || !result.data) {
            error.textContent = result.message || 'Unable to load the withdrawal form.';
            error.classList.remove('d-none');
            modal.show();
            return;
        }
        const data = result.data || {};
        const studentSummary = result.student_summary || {};
        editFamilyMembers = result.family_members || [];
        const principalApprovalButton = field('mark-principal-approved');
        const approveButton = field('approve-withdrawal');
        const rejectButton = field('reject-withdrawal');
        const cancelButton = field('cancel-withdrawal');
        principalApprovalButton.classList.toggle('d-none', data.withdrawal_status !== 'pending');
        approveButton.classList.toggle('d-none', data.withdrawal_status !== 'principal_approved');
        rejectButton.classList.toggle('d-none', !['pending', 'principal_approved'].includes(data.withdrawal_status));
        cancelButton.classList.toggle('d-none', !['pending', 'principal_approved', 'rejected'].includes(data.withdrawal_status));

        const summary = field('edit-student-summary');
        const photo = field('edit-student-photo');
        const photoPlaceholder = field('edit-student-photo-placeholder');
        if (summary) summary.classList.remove('d-none');
        field('edit-student-id').textContent = studentSummary.student_id ? 'Student ID: ' + studentSummary.student_id : '';
        field('edit-student-name-kh').textContent = studentSummary.name_kh || '';
        field('edit-student-name-en').textContent = studentSummary.name_en || 'Student';
        const gradeLabel = String(studentSummary.grade || '').replace(/^Grade\s+/i, '');
        const gradeClass = [gradeLabel, studentSummary.class].filter(Boolean).join('');
        const isGrade12 = String(studentSummary.grade || '').replace(/^Grade\s*/i, '').trim() === '12';
        const contextParts = [
            studentSummary.academic_year,
            studentSummary.campus,
            [gradeClass, studentSummary.group].filter(Boolean).join(' - '),
            isGrade12 ? studentSummary.track : '',
        ].filter(Boolean);
        field('edit-student-context').textContent = contextParts.join(' | ');
        if (studentSummary.photo_path) {
            photo.src = '/storage/' + encodeURI(studentSummary.photo_path).replace(/#/g, '%23');
            photo.classList.remove('d-none');
            photoPlaceholder.classList.add('d-none');
        } else {
            photo.removeAttribute('src');
            photo.classList.add('d-none');
            photoPlaceholder.classList.remove('d-none');
        }

        field('edit-withdrawal-id').value = data.id || '';
        (window.editWithdrawalDatePicker?.setValue || ((value) => field('edit-withdrawal-date').value = value))(String(data.effective_on || '').slice(0, 10));
        field('edit-dropout-type').value = data.dropout_type || 'official_leave';
        field('edit-other-en').value = data.other_reason_en || '';
        field('edit-other-kh').value = data.other_reason_kh || '';
        field('edit-new-school').value = data.new_school || '';
        field('edit-new-school-address').value = data.new_school_address || '';
        field('edit-requested-by-name').value = data.requested_by_name || '';
        field('edit-requested-by-phone').value = cleanPhone(data.requested_by_phone || '');
        field('edit-additional-comments').value = data.additional_comments || '';
        field('edit-notes').value = data.notes || '';

        document.querySelectorAll('.edit-withdrawal-reason-check').forEach(input => input.checked = (data.reasons || []).includes(input.value));
        field('edit-other-check').checked = Boolean(data.other_reason_en || data.other_reason_kh);
        document.querySelectorAll('.edit-other-field').forEach(item => item.classList.toggle('d-none', !field('edit-other-check').checked));
        field('edit-requested-by-type').value = data.requested_by_type || '';
        syncEditReasons();

        modal.show();
    });

    document.getElementById('mark-principal-approved')?.addEventListener('click', async () => {
        const confirmation = window.schoolShowConfirm ? await window.schoolShowConfirm('Confirm Principal Approval', 'Confirm that the School Principal has signed the printed withdrawal form?', 'Record Approval', 'Cancel') : { isConfirmed: window.confirm('Confirm that the School Principal has signed the printed withdrawal form?') }; if (!confirmation.isConfirmed) return;
        const response = await fetch(form.dataset.updateUrl + '/principal-approved', { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, body: JSON.stringify({ paper_signed: true }) });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) { const error = field('edit-withdrawal-error'); error.textContent = result.message || 'Unable to record paper approval.'; error.classList.remove('d-none'); return; }
        window.location.reload();
    });

    document.getElementById('approve-withdrawal')?.addEventListener('click', async () => {
        const confirmation = window.schoolShowConfirm ? await window.schoolShowConfirm('Approve Withdrawal', 'The student will become officially withdrawn.', 'Approve Withdrawal', 'Cancel') : { isConfirmed: window.confirm('Approve this withdrawal? The student will become officially withdrawn.') }; if (!confirmation.isConfirmed) return;
        const response = await fetch(form.dataset.updateUrl + '/approve', { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) { const error = field('edit-withdrawal-error'); error.textContent = result.message || 'Unable to approve withdrawal.'; error.classList.remove('d-none'); return; }
        window.location.reload();
    });

    document.getElementById('reject-withdrawal')?.addEventListener('click', async () => {
        const result = window.Swal ? await window.Swal.fire({ title: 'Reject Withdrawal Request', input: 'textarea', inputLabel: 'Rejection reason', inputPlaceholder: 'Enter the reason for rejection...', inputValidator: value => !value?.trim() ? 'A rejection reason is required.' : undefined, didOpen: popup => popup.querySelector('textarea')?.focus(), icon: 'warning', showCancelButton: true, confirmButtonText: 'Reject Request', confirmButtonColor: '#d63939', cancelButtonText: 'Cancel', reverseButtons: true }) : { isConfirmed: window.confirm('Reject this withdrawal request?'), value: 'Rejected by administrator' };
        if (!result.isConfirmed) return;
        const response = await fetch(form.dataset.updateUrl + '/reject', { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, body: JSON.stringify({ rejection_reason: result.value }) });
        if (!response.ok) return;
        window.location.reload();
    });

    document.getElementById('cancel-withdrawal')?.addEventListener('click', async () => {
        const result = window.Swal ? await window.Swal.fire({ title: 'Cancel Withdrawal Request', input: 'textarea', inputLabel: 'Cancellation reason', inputPlaceholder: 'Enter the reason for cancellation...', inputValidator: value => !value?.trim() ? 'A cancellation reason is required.' : undefined, didOpen: popup => popup.querySelector('textarea')?.focus(), icon: 'question', showCancelButton: true, confirmButtonText: 'Cancel Request', confirmButtonColor: '#6c7a91', cancelButtonText: 'Keep Request', reverseButtons: true }) : { isConfirmed: window.confirm('Cancel this withdrawal request?'), value: 'Cancelled by administrator' };
        if (!result.isConfirmed) return;
        const response = await fetch(form.dataset.updateUrl + '/cancel', { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, body: JSON.stringify({ cancellation_reason: result.value }) });
        if (!response.ok) return;
        window.location.reload();
    });

    document.addEventListener('change', event => {
        if (event.target.matches('#edit-other-check')) {
            document.querySelectorAll('.edit-other-field').forEach(item => item.classList.toggle('d-none', !event.target.checked));
            syncEditReasons();
        }
        if (event.target.matches('#edit-requested-by-type')) applyEditRequestedBy(event.target.value);
        if (event.target.matches('.edit-withdrawal-reason-check')) {
            syncEditReasons();
        }
    });
    document.addEventListener('input', event => {
        if (event.target.matches('#edit-requested-by-phone')) event.target.value = cleanPhone(event.target.value);
        if (event.target.matches('#edit-other-en, #edit-other-kh')) syncEditReasons();
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        const reasonData = syncEditReasons();
        const requestedType = field('edit-requested-by-type').value || '';
        const payload = {
            withdrawal_date: field('edit-withdrawal-date').value,
            reasons: checkedReasons().map(input => input.value),
            reason: reasonData.reason,
            reason_kh: reasonData.reason_kh,
            other_reason_en: field('edit-other-check').checked ? field('edit-other-en').value : '',
            other_reason_kh: field('edit-other-check').checked ? field('edit-other-kh').value : '',
            new_school: field('edit-new-school').value,
            new_school_address: field('edit-new-school-address').value,
            dropout_type: field('edit-dropout-type').value,
            requested_by_type: requestedType,
            requested_by_name: field('edit-requested-by-name').value,
            requested_by_phone: cleanPhone(field('edit-requested-by-phone').value),
            additional_comments: field('edit-additional-comments').value,
            notes: field('edit-notes').value,
        };

        const response = await fetch(form.dataset.updateUrl, {
            method: 'PATCH',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify(payload),
        });
        const result = await response.json();
        if (!response.ok) {
            const error = field('edit-withdrawal-error');
            error.textContent = result.message || Object.values(result.errors || {})[0]?.[0] || 'Unable to update withdrawal form.';
            error.classList.remove('d-none');
            return;
        }
        modal.hide();
        window.location.reload();
    });
});
</script>
<style>
#withdrawalModal .modal-content { overflow: hidden; }
#withdrawalModal .modal-body { max-height: calc(100vh - 10rem); overflow-y: auto; }
#editWithdrawalModal .modal-body { max-height: calc(100vh - 10rem); overflow-y: auto; }
.edit-student-summary {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.1rem;
    border: 1px solid rgba(var(--tblr-primary-rgb), .18);
    border-radius: 1rem;
    background: var(--tblr-bg-surface-secondary);
}
.edit-student-summary-photo,
.edit-student-summary-photo-placeholder {
    width: 72px;
    height: 96px;
    flex: 0 0 72px;
    object-fit: cover;
    border-radius: 15%;
}
.edit-student-summary-photo-placeholder {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--tblr-secondary-lt);
    color: var(--tblr-secondary);
    font-size: 2rem;
}
.edit-student-summary-details { min-width: 0; }
.edit-student-summary-id { color: var(--tblr-secondary); font-size: .85rem; font-weight: 600; margin-bottom: .1rem; }
.edit-student-summary-details > .school-profile-khmer,
.edit-student-summary-name { font-size: 1.2rem !important; line-height: 1.35; }
.edit-student-summary-details > .school-profile-khmer { font-weight: 500; }
.edit-student-summary-name { font-size: 1rem !important; font-weight: 500; }
.edit-student-summary-context {
    display: flex;
    flex-wrap: wrap;
    gap: .35rem .9rem;
    margin-top: .35rem;
    color: var(--tblr-secondary);
    font-size: .9rem;
}
.edit-student-summary-context span { white-space: nowrap; }
@media (max-width: 575.98px) {
    .edit-student-summary { align-items: flex-start; }
    .edit-student-summary-context { display: grid; grid-template-columns: 1fr 1fr; }
}
.withdrawal-date-picker {
    position: relative;
    display: block;
    width: 100%;
    height: 52px;
    min-height: 52px;
    overflow: visible;
    border: 1.5px solid #dfe3ea !important;
    border-radius: 14px !important;
    background: var(--tblr-bg-surface, #fff) !important;
    box-shadow: 0 2px 7px rgba(31, 41, 55, .04) !important;
}
.withdrawal-date-picker:focus-within {
    border-color: #6b5bd6 !important;
    box-shadow: 0 0 0 3px rgba(107, 91, 214, .14) !important;
}
.withdrawal-date-picker .date-picker-input-row {
    position: relative;
    display: block;
    width: 100%;
    background: transparent !important;
}
.withdrawal-date-picker .date-picker-input-row .form-control {
    width: 100%;
    height: 52px !important;
    min-height: 52px !important;
    padding-top: .75rem !important;
    padding-right: 3.25rem !important;
    padding-bottom: .75rem !important;
    border: 0 !important;
    border-radius: 14px !important;
    background: transparent !important;
    box-shadow: none !important;
    text-align: left;
}
.withdrawal-date-picker .date-picker-calendar-button {
    position: absolute !important;
    z-index: 3;
    top: .25rem !important;
    right: .25rem !important;
    width: 42px !important;
    min-width: 42px !important;
    height: 44px !important;
    padding: 0 !important;
    justify-content: center;
    border: 0 !important;
    border-radius: 10px;
    background: var(--tblr-bg-surface, #fff) !important;
    box-shadow: none !important;
}
.withdrawal-date-picker .date-picker-popup {
    z-index: 2050;
    left: auto;
    right: 0;
    width: 21rem;
    max-width: min(21rem, calc(100vw - 2rem));
}
.requested-by-card {
    border: 1px solid var(--tblr-border-color);
    border-radius: 14px;
    cursor: pointer;
    display: block;
    min-height: 160px;
    padding: 22px 22px;
    transition: border-color .18s ease, box-shadow .18s ease, background-color .18s ease;
}
.requested-by-card:hover,
.requested-by-card.selected {
    background: rgba(var(--tblr-primary-rgb), .06);
    border-color: var(--tblr-primary);
    box-shadow: 0 10px 24px rgba(var(--tblr-primary-rgb), .12);
}
.requested-by-radio {
    float: right;
    margin-top: 2px;
}
.requested-by-title,
.requested-by-name,
.requested-by-phone {
    display: block;
}
.requested-by-title {
    color: var(--tblr-primary);
    font-size: 1.32rem;
    font-weight: 700;
    margin-bottom: 12px;
}
.requested-by-name {
    font-size: 1.68rem;
    font-weight: 700;
    line-height: 1.25;
}
.requested-by-phone {
    color: var(--tblr-secondary);
    font-size: 1.5rem;
    font-weight: 600;
    line-height: 1.25;
    margin-top: 8px;
}
</style>
@endpush
