@vite(['resources/css/pages/g12-grade-a-editor.css','resources/js/g12GradeACertificate.js'])
<div class="card-body" data-g12-certificate-editor>
    <div class="alert alert-info" data-cert-message>Select an Academic Year above to edit certificates. Campus is optional.</div>
    <div data-cert-workspace hidden>
        <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
            <button type="button" class="btn btn-primary" data-cert-save>Save Layout</button>
            <button type="button" class="btn btn-outline-primary" data-cert-assign>Assign Certificate Numbers</button>
            <button type="button" class="btn" data-cert-reset>Reset Layout</button>
            <button type="button" class="btn btn-outline-primary" data-cert-pdf>Print / Download PDF</button>
            <label class="form-check ms-2 mb-0"><input type="checkbox" class="form-check-input" data-cert-frame checked><span class="form-check-label">Include frame</span></label>
        </div>
        <div class="g12-certificate-editor-grid">
            <div class="g12-certificate-controls">
                <div class="g12-certificate-control-heading"><i class="ti ti-adjustments-horizontal" aria-hidden="true"></i><div><strong>Certificate Designer</strong><small>Choose a student and customize each text block.</small></div></div>
                <div class="g12-certificate-section-label">Academic Year Settings</div>
                <label class="form-label" for="g12CertIssueDate">Certificate Issue Date</label>
                <div class="date-picker g12-certificate-date-picker mb-3" data-cert-date-picker data-premium-date-format="DD-MMM-YYYY" data-premium-date-placement="auto" data-premium-date-portal="true">
                    <input type="text" id="g12CertIssueDate" class="form-control" placeholder="DD-MMM-YYYY" autocomplete="off" data-premium-date-input aria-describedby="g12CertDateHint">
                    <input type="hidden" data-cert-issue-date data-premium-date-value>
                    <button type="button" class="date-picker-trigger date-picker-calendar-button" data-premium-date-toggle aria-label="Open issue date calendar" aria-controls="g12CertDateCalendar" aria-expanded="false"><i class="ti ti-calendar" aria-hidden="true"></i></button>
                    <div id="g12CertDateCalendar" class="date-picker-popup d-none" role="dialog" aria-label="Choose certificate issue date">
                        <div class="date-picker-header">
                            <button type="button" class="date-picker-nav" data-premium-date-prev aria-label="Previous month"><i class="ti ti-chevron-left" aria-hidden="true"></i></button>
                            <button type="button" class="date-picker-year-toggle" aria-label="Choose year"></button>
                            <button type="button" class="date-picker-nav" data-premium-date-next aria-label="Next month"><i class="ti ti-chevron-right" aria-hidden="true"></i></button>
                        </div>
                        <div class="date-picker-year-popup d-none"><div class="date-picker-years"></div></div>
                        <div class="date-picker-grid"><div class="date-picker-weekdays"><span>SU</span><span>MO</span><span>TU</span><span>WE</span><span>TH</span><span>FR</span><span>SA</span></div><div class="date-picker-days"></div></div>
                        <div class="g12-certificate-calendar-footer"><button type="button" class="btn btn-sm btn-primary" data-premium-date-today>Today</button></div>
                    </div>
                </div>
                <div class="form-hint mb-3" id="g12CertDateHint">Type DD-MMM-YYYY or select a date.</div>
                <label class="form-label" for="g12CertSignerName">Signer Name</label>
                <input type="text" id="g12CertSignerName" class="form-control mb-3" maxlength="150" data-cert-signer-name>
                <label class="form-label" for="g12CertSignerPosition">Signer Position</label>
                <input type="text" id="g12CertSignerPosition" class="form-control mb-2" maxlength="150" data-cert-signer-position>
                <p class="small text-secondary">Saved with this academic year's layout. Other years keep their own issue date and signer.</p>
                <label class="form-label" for="g12CertStudent">Preview Student</label>
                <select class="form-select mb-3" id="g12CertStudent" data-cert-student></select>
                <label class="form-label" for="g12CertField">Text Block</label>
                <select class="form-select mb-3" id="g12CertField" data-cert-field></select>
                <form data-cert-controls>
                    <div class="g12-certificate-section-label">Text &amp; appearance</div>
                    <label class="form-label" for="g12CertText">Content</label>
                    <textarea id="g12CertText" name="text" class="form-control mb-2" rows="5"></textarea>
                    <details class="mb-3"><summary>Automatic values</summary><p class="small text-secondary mt-2">@verbatim{{student_name}}, {{student_name_kh}}, {{certificate_number}}, {{academic_year}}, {{exam_date}}, {{campus}}, {{score_rank}}@endverbatim</p><p class="small text-secondary">Edit the issue date and location directly in the content if needed.</p></details>
                    <label class="form-label" for="g12CertFont">System Font</label>
                    <select id="g12CertFont" name="font" class="form-select mb-3"></select>
                    <div class="g12-certificate-section-label">Size &amp; position</div>
                    <div class="g12-certificate-input-grid">
                        @foreach(['size'=>['Font Size (pt)',6,60],'x'=>['Left (mm)',0,209],'y'=>['Top (mm)',0,296],'width'=>['Width (mm)',1,210],'height'=>['Height (mm)',1,297]] as $name=>[$label,$min,$max])
                        <div><label class="form-label" for="g12Cert-{{ $name }}">{{ $label }}</label><input type="number" step="0.1" min="{{ $min }}" max="{{ $max }}" class="form-control" id="g12Cert-{{ $name }}" name="{{ $name }}"></div>
                        @endforeach
                        <div><label class="form-label" for="g12CertColor">Text Color</label><input type="color" name="color" id="g12CertColor" class="form-control form-control-color"></div>
                    </div>
                    <label class="form-label mt-3" for="g12CertAlign">Alignment</label>
                    <select name="align" id="g12CertAlign" class="form-select mb-3"><option value="left">Left</option><option value="center">Center</option><option value="right">Right</option></select>
                    <label class="form-check"><input name="bold" type="checkbox" class="form-check-input"><span class="form-check-label">Bold</span></label>
                    <label class="form-check"><input name="visible" type="checkbox" class="form-check-input"><span class="form-check-label">Show this block</span></label>
                </form>
                <p class="small text-secondary mt-3">Drag a text block to move it. Arrow keys move the selected block by 0.5 mm.</p>
            </div>
            <div class="g12-certificate-preview-scroll"><div class="g12-certificate-preview-stage"><div class="g12-certificate-sheet" data-cert-sheet aria-label="A4 certificate preview"></div></div></div>
        </div>
        <div class="mt-4"><div class="d-flex align-items-center gap-2 mb-2"><h3 class="mb-0">Grade A Students</h3><label class="form-check mb-0 ms-auto"><input type="checkbox" class="form-check-input" data-cert-select-all checked><span class="form-check-label">Select all</span></label></div><div class="g12-certificate-students" data-cert-students></div></div>
    </div>
</div>
