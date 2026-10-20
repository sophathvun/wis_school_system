<script type="application/json" data-score-report-options>@json($scoreReportOptions??[])</script>
<div class="modal fade" id="scholarshipScoreReportScope" tabindex="-1" aria-labelledby="scoreReportScopeTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header scholarship-card-header"><h5 class="modal-title text-white" id="scoreReportScopeTitle"><i class="ti ti-file-text me-2" aria-hidden="true"></i>Score List Report</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <form data-score-report-scope novalidate>
            <div class="modal-body">
                <div class="scholarship-field mb-3"><label for="scoreReportCenter">Exam Center <span class="text-danger">*</span></label><select id="scoreReportCenter" name="report_center" class="form-select" data-placeholder="Select Exam Center"></select><div class="small text-danger mt-1" data-score-report-error="report_center" hidden>Select an exam center.</div></div>
                <div class="scholarship-field mb-3"><label for="scoreReportScope">Print / Export Scope</label><select id="scoreReportScope" name="report_scope" class="form-select"><option value="single-room">One Room</option><option value="selected-rooms">Selected Rooms</option><option value="all-rooms">All Rooms in This Exam Center</option></select></div>
                <div class="scholarship-field mb-3" data-score-report-single><label for="scoreReportRoom">Room <span class="text-danger">*</span></label><select id="scoreReportRoom" name="report_room" class="form-select" data-placeholder="Select Room"></select><div class="small text-danger mt-1" data-score-report-error="report_room" hidden>Select a room.</div></div>
                <div class="scholarship-field mb-3" data-score-report-multiple hidden><label for="scoreReportRooms">Rooms <span class="text-danger">*</span></label><select id="scoreReportRooms" name="report_rooms[]" multiple class="form-select" data-placeholder="Select Rooms"></select><div class="small text-danger mt-1" data-score-report-error="report_rooms" hidden>Select at least one room.</div></div>
                <div class="scholarship-field mb-3" data-score-report-pdf hidden><label for="scoreReportPdfMode">PDF Files</label><select id="scoreReportPdfMode" name="pdf_mode" class="form-select"><option value="combined">Combine Rooms in One PDF</option><option value="separate">Separate PDF for Each Room (ZIP)</option></select></div>
                <p class="small text-secondary mb-0" data-score-report-help>Every student in the chosen rooms is included, using saved scores. The Student Name filter does not limit this report.</p>
                <p class="small text-secondary mt-2 mb-0" data-score-report-format-help></p>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit" data-score-report-open>Print</button></div>
        </form>
    </div></div>
</div>
