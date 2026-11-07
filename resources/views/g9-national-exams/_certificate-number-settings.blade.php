<div class="modal modal-blur fade g9-number-modal" id="g9CertificateNumberModal" tabindex="-1" aria-labelledby="g9CertificateNumberTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><form class="modal-content" data-number-form>
        <div class="modal-header g12-certificate-modal-header"><div><div class="g12-certificate-modal-kicker">G9 Certificate Tracking</div><h3 class="modal-title" id="g9CertificateNumberTitle">Certificate Number Settings</h3><div class="g12-certificate-modal-student">Preview passed students before assigning permanent numbers.</div></div><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close number settings"></button></div>
        <div class="modal-body">
            <div class="alert alert-danger" role="alert" data-number-error hidden></div>
            <div class="alert alert-success" role="status" data-number-success hidden></div>
            <div class="g9-number-format-card">
                <div><span class="g9-number-eyebrow">Certificate number format</span><div class="g9-number-example"><span>៩</span><span data-number-year-code>២៦</span><span data-number-example>០០០១</span></div><div class="g9-number-format-labels"><span>Grade 9</span><span>Year ending</span><span>4-digit sequence</span></div></div>
                <span class="g9-number-eligible"><i class="ti ti-circle-check" aria-hidden="true"></i>Passed students only</span>
            </div>
            <section class="g9-number-section">
                <div class="g9-number-section-heading"><span class="g9-number-section-icon"><i class="ti ti-adjustments" aria-hidden="true"></i></span><div><h4>Academic Year & Sequence</h4><p>One continuous sequence across all exam centers in the selected year.</p></div></div>
                <div class="g9-number-fields">
                    <div class="premium-form-field"><label class="form-label" for="g9NumberYear">Academic Year</label><select class="form-select" name="academic_year" id="g9NumberYear" required><option value="">Select academic year</option>@foreach($years as $year)<option value="{{ $year->id }}">{{ $year->academic_year }}</option>@endforeach</select></div>
                    <div class="premium-form-field"><label class="form-label" for="g9NumberStart">Starting Sequence</label><input class="form-control" name="starting_sequence" type="number" min="1" max="9999" step="1" value="1" id="g9NumberStart" required><small class="g9-number-field-hint">New numbers continue after the highest number already assigned.</small></div>
                    <div class="g9-number-fixed"><i class="ti ti-lock" aria-hidden="true"></i><div><strong>៩ + year + ០០០១</strong><small>Grade prefix and 4-digit length stay fixed.</small></div></div>
                </div>
            </section>
            <section class="g9-number-section" data-number-config hidden>
                <div class="g9-number-section-heading"><span class="g9-number-section-icon"><i class="ti ti-sort-ascending" aria-hidden="true"></i></span><div><h4>Location & Exam Language Order</h4><p>Move groups up or down. Center priority applies within each group.</p></div></div>
                <div class="g9-number-order" data-number-group-order></div>
                <div class="g9-number-center-heading"><h4>Exam Center Priority</h4><span>Khmer name order within each center</span></div>
                <div class="table-responsive g9-number-center-wrap"><table class="table table-vcenter mb-0 g9-number-centers"><thead><tr><th>Priority</th><th>Exam Center</th><th>Location</th></tr></thead><tbody data-number-centers></tbody></table></div>
                <p class="g9-number-note"><i class="ti ti-info-circle" aria-hidden="true"></i>Set a location for each center with passed students. Provincial Khmer groups are included last by default and can be moved.</p>
            </section>
            <div class="g9-number-preserve"><i class="ti ti-lock-check" aria-hidden="true"></i><div><strong>Existing certificate numbers stay fixed.</strong><span>Import and review historical numbers before generating missing numbers for an old academic year.</span></div></div>
            <section class="g9-number-preview" data-number-preview hidden aria-label="Certificate number preview">
                <div class="g9-number-section-heading"><span class="g9-number-section-icon"><i class="ti ti-list-check" aria-hidden="true"></i></span><div><h4>Review Certificate Numbers</h4><p>Only passed students appear below. Existing numbers are kept; only new rows will be assigned.</p></div></div>
                <div class="g9-number-preview-totals" data-number-preview-totals></div>
                <div class="g9-number-preview-table-wrap"><table class="table table-vcenter mb-0"><thead><tr><th>No.</th><th>Student</th><th>Exam Center / Language</th><th>Certificate Number</th><th>Assignment</th></tr></thead><tbody data-number-preview-rows></tbody></table></div>
            </section>
        </div>
        <div class="modal-footer g9-number-footer"><span class="g9-number-status" data-number-status role="status">Select an academic year to get started.</span><div class="g9-number-actions"><button type="button" class="btn btn-outline-secondary" data-number-save disabled><i class="ti ti-device-floppy me-1" aria-hidden="true"></i>Save Settings</button><button type="button" class="btn btn-outline-primary" data-number-preview-button disabled><i class="ti ti-eye me-1" aria-hidden="true"></i>Preview Numbers</button><button type="button" class="btn btn-primary" data-number-generate disabled><i class="ti ti-certificate me-1" aria-hidden="true"></i><span>Generate Numbers</span></button></div></div>
    </form></div>
</div>
