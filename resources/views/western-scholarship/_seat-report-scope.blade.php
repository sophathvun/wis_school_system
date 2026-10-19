<div class="modal fade" id="scholarshipSeatReportScope" tabindex="-1" aria-labelledby="seatReportScopeTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header scholarship-card-header"><h5 class="modal-title" id="seatReportScopeTitle">Report Scope</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <form data-seat-report-scope novalidate>
            <div class="modal-body">
                <p class="text-secondary" data-seat-report-document></p>
                <div class="scholarship-field mb-3"><label for="seatReportCenter">Exam Center</label><input class="form-control" id="seatReportCenter" readonly><input type="hidden" name="report_center"></div>
                <div class="scholarship-field mb-3"><label for="seatReportScope">Print / Export Scope</label><select id="seatReportScope" name="report_scope" class="form-select"><option value="all-rooms">All Rooms in This Exam Center</option><option value="selected-rooms">Selected Rooms</option></select></div>
                <div class="scholarship-field" data-report-room-field hidden><label for="seatReportRooms">Rooms <span class="text-danger">*</span></label><select id="seatReportRooms" name="report_rooms[]" multiple class="form-select" data-seat-picker data-placeholder="Select Rooms"></select><div class="invalid-feedback" data-report-room-error>Select at least one room.</div></div>
                <p class="small text-secondary mt-3 mb-0">The report includes every candidate in the chosen rooms. Student name and search filters do not limit the report.</p>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit" data-seat-report-open>Print</button></div>
        </form>
    </div></div>
</div>
