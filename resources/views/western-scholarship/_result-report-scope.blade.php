@php
    $resultReportOptions??=$workflow->candidates(auth()->user(),$exam)->get()->groupBy(fn($candidate)=>(int)($candidate->exam_center_id??0))->map(fn($group,$id)=>['id'=>(int)$id,'label'=>$group->first()->exam_center_name?:'Main Exam Center','grades'=>$group->pluck('grade_number')->unique()->sort()->values()->all()])->sortBy('label')->values();
@endphp
<script type="application/json" data-result-report-options>@json($resultReportOptions)</script>
<div class="modal fade" id="scholarshipResultReportScope" tabindex="-1" aria-labelledby="resultReportScopeTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header scholarship-card-header"><h5 class="modal-title text-white" id="resultReportScopeTitle"><i class="ti ti-award me-2" aria-hidden="true"></i>Result Report</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <form data-result-report-scope novalidate><div class="modal-body">
            <div class="scholarship-field mb-3"><label for="resultReportCenter">Exam Center</label><select class="form-select" name="report_center" id="resultReportCenter" data-placeholder="All Exam Centers"></select></div>
            <div class="scholarship-field mb-3"><label for="resultReportScope">Print / Export Scope</label><select class="form-select" name="report_scope" id="resultReportScope"><option value="single-grade">One Grade</option><option value="selected-grades">Selected Grades</option><option value="all-grades">All Grades</option></select></div>
            <div class="scholarship-field mb-3" data-result-report-single><label for="resultReportGrade">Grade <span class="text-danger">*</span></label><select class="form-select" name="report_grade" id="resultReportGrade" data-placeholder="Select Grade"></select><div class="small text-danger mt-1" data-result-report-error="single" hidden>Select a grade.</div></div>
            <div class="scholarship-field mb-3" data-result-report-multiple hidden><label for="resultReportGrades">Grades <span class="text-danger">*</span></label><select class="form-select" name="report_grades[]" id="resultReportGrades" multiple data-placeholder="Select Grades"></select><div class="small text-danger mt-1" data-result-report-error="multiple" hidden>Select at least one grade.</div></div>
            @if($publishedOnly??false)<input type="hidden" name="report_students" value="winners">@else<div class="scholarship-field mb-3"><label for="resultReportStudents">Students</label><select class="form-select" name="report_students" id="resultReportStudents"><option value="winners">Award Winners</option><option value="all">All Students</option></select></div>@endif
            <div class="scholarship-field mb-3" data-result-report-pdf hidden><label for="resultReportPdfMode">PDF Files</label><select class="form-select" name="pdf_mode" id="resultReportPdfMode"><option value="combined">Combine Grades in One PDF</option><option value="separate">Separate PDF for Each Grade (ZIP)</option></select></div>
            <p class="small text-secondary mb-0" data-result-report-help>Each grade starts on a new A4 page. Winners appear first: 1st, 2nd, 3rd, then Best in Subjects. The Student Name filter does not limit the report.</p>
        </div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit" data-result-report-open>Print</button></div></form>
    </div></div>
</div>
