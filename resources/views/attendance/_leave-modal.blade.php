<div class="modal fade" id="attendanceLeaveModal" tabindex="-1" aria-labelledby="attendanceLeaveTitle" aria-hidden="true" data-attendance-leave data-today="{{ now('Asia/Bangkok')->toDateString() }}">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg"><form class="modal-content" data-leave-form enctype="multipart/form-data">
        <div class="modal-header att-header"><h3 class="modal-title" id="attendanceLeaveTitle">Record Approved Leave</h3><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
            <p class="text-secondary">School days only, using Attendance Settings. Existing attendance is never replaced. Future leave appears as E when due.</p>
            <div class="att-leave-fields">
                <div class="premium-form-field staff-form-field"><label class="form-label" for="leaveStudent">Student</label><select class="form-select" id="leaveStudent" name="student_id" required></select></div>
                <div class="premium-form-field att-leave-type"><label class="form-label" for="leaveMode">Leave Type</label><select class="form-select" id="leaveMode" name="mode"><option value="full">Full day — all applicable periods</option><option value="partial">Partial day — selected periods</option></select><i class="ti ti-chevron-down att-leave-type-arrow" aria-hidden="true"></i></div>
                <div class="premium-form-field"><label class="form-label" for="leaveFrom">Start Date</label><input type="date" class="form-control" id="leaveFrom" name="starts_on" required></div>
                <div class="premium-form-field"><label class="form-label" for="leaveUntil">End Date</label><input type="date" class="form-control" id="leaveUntil" name="ends_on" required></div>
                <div class="premium-form-field"><label class="form-label" for="leaveApprover">Approved By</label><input class="form-control" id="leaveApprover" name="approved_by" maxlength="150" placeholder="Approver name" required></div>
                <div><label class="form-label" for="leaveAttachment">Permission Letter (optional)</label><input type="file" class="form-control" id="leaveAttachment" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.webp"><small class="text-secondary">PDF or image, maximum 5 MB. Staff access only.</small></div>
                <div class="att-leave-wide"><label class="form-label" for="leaveReason">Reason</label><textarea class="form-control" id="leaveReason" name="reason" rows="2" maxlength="500" required></textarea></div>
                <fieldset class="att-leave-wide att-leave-periods" data-leave-periods hidden><legend>Select Absent Periods</legend><div data-leave-period-options></div></fieldset>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" data-leave-submit>Review &amp; Record Leave</button></div>
    </form></div>
</div>
