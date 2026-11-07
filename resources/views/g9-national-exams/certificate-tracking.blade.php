@extends('layouts.app')
@section('title','G9 National Exams — Certificate Tracking')
@section('page-header','')
@section('content')
@vite(['resources/css/pages/g9-national-exams.css','resources/css/pages/g9-results.css','resources/css/pages/g12-national-exams.css','resources/css/pages/g9-certificate-tracking.css','resources/js/g9CertificateTracking.js'])
<div data-g9-certificate-tracking data-base="{{ url('/g9-national-exams/certificates') }}">
    <section class="card mb-3">
        <div class="card-header g9-header"><h3 class="card-title"><i class="ti ti-filter me-2" aria-hidden="true"></i>Data Filters</h3><button type="button" class="btn btn-sm btn-outline-light ms-auto" data-certificate-filter-toggle aria-expanded="true" aria-controls="g9CertificateFilters" aria-label="Collapse filters"><i class="ti ti-chevron-up" aria-hidden="true"></i></button></div>
        <form class="card-body g9-candidate-filters g9-result-filters" id="g9CertificateFilters">
            @foreach(['academic_year'=>'Academic Year','exam_center'=>'Exam Center','campus'=>'Campus','room'=>'Room No.','grade'=>'Grade','student'=>'Student Name','status'=>'Certificate Status'] as $name=>$label)
            <div class="premium-form-field staff-form-field"><label class="form-label" for="g9Certificate-{{ $name }}">{{ $label }}</label><select class="form-select" id="g9Certificate-{{ $name }}" name="{{ $name }}" aria-label="{{ $label }}"><option value="">{{ $name==='academic_year'?'Select academic year':'All '.strtolower($label) }}</option>
                @if($name==='academic_year')@foreach($years as $year)<option value="{{ $year->id }}">{{ $year->academic_year }}</option>@endforeach @endif
                @if($name==='status')@foreach(['awaiting'=>'Awaiting Printing','printed_signed'=>'Printed & Signed','received'=>'Received by Student','not_issued'=>'Not Issued','review'=>'Imported Record Needs Review'] as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach @endif
            </select></div>
            @endforeach
            <div class="premium-form-field staff-form-field g9-result-search-field"><label class="form-label" for="g9CertificateSearch">Search</label><input type="search" class="form-control" id="g9CertificateSearch" placeholder="Student ID or name" data-search-normalized="true"><i class="ti ti-search g9-result-search-icon" aria-hidden="true"></i></div>
        </form>
    </section>
    <div class="alert alert-danger" data-certificate-error role="alert" hidden></div>
    <div class="alert alert-success" data-certificate-success role="status" hidden></div>
    <div class="g9-results-summary mb-3" data-certificate-summary hidden aria-label="Certificate tracking totals" aria-live="polite">
        @foreach(['total'=>['Candidates','ti-users','blue'],'printed_signed'=>['Printed & Signed','ti-certificate','green'],'received'=>['Received by Student','ti-signature','green'],'awaiting'=>['Awaiting Printing','ti-clock','orange']] as $key=>[$label,$icon,$color])
        <article class="card g9-result-metric g9-result-metric-{{ $color }}"><div class="g9-result-metric-top"><span>{{ $label }}</span><i class="ti {{ $icon }}" aria-hidden="true"></i></div><strong data-certificate-count="{{ $key }}">0</strong>
            @if($key==='total')
            <div class="d-flex flex-wrap gap-2 small fw-semibold mt-2" aria-label="Candidate examination results"><span class="text-success">Pass: <span data-certificate-count="passed">0</span></span><span class="text-secondary" aria-hidden="true">|</span><span class="text-danger">Failed: <span data-certificate-count="failed">0</span></span></div>
            @endif
        </article>
        @endforeach
    </div>
    <section class="card">
        <div class="card-header g9-header flex-wrap gap-2"><h3 class="card-title"><i class="ti ti-certificate me-2" aria-hidden="true"></i>Certificate Tracking</h3><span class="ms-auto small">G9 Provisional Certificate</span>
            @if($canNumberSettings)<button type="button" class="btn btn-sm btn-outline-light" data-certificate-number-settings><i class="ti ti-settings me-1" aria-hidden="true"></i>Certificate Number Settings</button>@endif
        </div>
        <div class="table-responsive g12-certificate-table-wrap"><table class="table table-vcenter card-table g12-certificate-table"><thead><tr><th>No.</th><th>Photo</th><th>Student</th><th>Grade</th><th>Examination</th><th>Provisional Certificate</th><th>Certificate Received by Student</th><th>Actions</th></tr></thead><tbody data-certificate-rows><tr><td colspan="8" class="text-center text-secondary py-5">Select an academic year to display certificate tracking.</td></tr></tbody></table></div>
        <div class="card-footer"><input type="hidden" id="g9-certificate-per-page" value="25"><div id="g9-certificate-pagination"></div></div>
    </section>
    <div class="modal modal-blur fade g12-certificate-modal" id="g9CertificateModal" tabindex="-1" aria-hidden="true" aria-labelledby="g9CertificateModalTitle">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><form class="modal-content" data-certificate-form>
            <div class="modal-header g12-certificate-modal-header"><div><div class="g12-certificate-modal-kicker">G9 Certificate Tracking</div><h3 class="modal-title" id="g9CertificateModalTitle">Provisional Certificate</h3><div class="g12-certificate-modal-student" data-certificate-student></div></div><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <input type="hidden" name="candidate_id"><input type="hidden" name="version">
                <div class="alert alert-danger" data-certificate-modal-error role="alert" hidden></div>
                <div class="alert alert-warning" data-certificate-modal-note hidden></div>
                <div class="g12-certificate-upload-grid">
                    @foreach(['provisional'=>['Provisional Certificate','Certificate printed and signed by the school','ti-certificate'],'receipt'=>['Certificate Received by Student','Student-signed copy confirming receipt','ti-signature']] as $kind=>[$label,$description,$icon])
                    <section class="g12-certificate-upload-card">
                        <div class="g12-certificate-upload-heading"><span class="g12-certificate-upload-icon {{ $kind==='receipt'?'is-receipt':'' }}"><i class="ti {{ $icon }}" aria-hidden="true"></i></span><div><strong>{{ $label }}</strong><small>{{ $description }}</small></div></div>
                        <div data-certificate-current="{{ $kind }}"></div>
                        <label class="g12-certificate-file-control" data-certificate-file-label="{{ $kind }}"><i class="ti ti-cloud-upload" aria-hidden="true"></i><span><strong>Choose {{ $kind==='receipt'?'student-signed copy':'certificate' }} image or PDF</strong><small>JPG, PNG, WebP or PDF · Up to 20 MB</small></span><input type="file" name="{{ $kind }}_file" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" aria-label="Upload {{ strtolower($label) }}"></label>
                    </section>
                    @endforeach
                </div>
                <section class="g12-certificate-tracking-panel">
                    <div class="g12-certificate-panel-title"><i class="ti ti-progress-check" aria-hidden="true"></i><span>Printing & Student Receipt</span></div>
                    <div class="g12-certificate-fields">
                        <div><label class="form-label" for="g9CertificateStatus">Status</label><select class="form-select" name="status" id="g9CertificateStatus" required><option value="awaiting">Awaiting Printing</option><option value="printed_signed">Printed & Signed</option><option value="received">Received by Student</option></select></div>
                        <div><label class="form-label" for="g9CertificateNumber">Certificate Number</label><input class="form-control" name="number" id="g9CertificateNumber" maxlength="100" data-certificate-number-field></div>
                        <div><label class="form-label" for="g9CertificatePrintedDate">Printed & Signed Date</label><input class="form-control" name="printed_signed_date" id="g9CertificatePrintedDate" type="date"></div>
                        <div>
                            <label class="form-label" for="g9CertificateReceivedDate-direct">Student Received Date</label>
                            <div class="date-picker" data-certificate-received-picker data-premium-date-format="DD-MMM-YYYY" data-premium-date-portal="true">
                                <input class="form-control" id="g9CertificateReceivedDate-direct" type="text" placeholder="DD-MMM-YYYY" autocomplete="off" data-premium-date-input>
                                <input type="hidden" name="received_date" id="g9CertificateReceivedDate" data-premium-date-value>
                                <button class="date-picker-trigger date-picker-calendar-button" type="button" data-premium-date-toggle aria-label="Open student received date calendar" aria-expanded="false" aria-controls="g9CertificateReceivedDate-calendar"><i class="ti ti-calendar" aria-hidden="true"></i></button>
                                <div id="g9CertificateReceivedDate-calendar" class="date-picker-popup d-none" role="dialog" aria-label="Choose student received date">
                                    <div class="date-picker-header">
                                        <button class="date-picker-nav" type="button" data-premium-date-prev aria-label="Previous month"><i class="ti ti-chevron-left" aria-hidden="true"></i></button>
                                        <button class="date-picker-year-toggle" type="button" aria-label="Choose year"></button>
                                        <button class="date-picker-nav" type="button" data-premium-date-next aria-label="Next month"><i class="ti ti-chevron-right" aria-hidden="true"></i></button>
                                    </div>
                                    <div class="date-picker-year-popup d-none"><div class="date-picker-years"></div></div>
                                    <div class="date-picker-grid"><div class="date-picker-weekdays"><span>SU</span><span>MO</span><span>TU</span><span>WE</span><span>TH</span><span>FR</span><span>SA</span></div><div class="date-picker-days"></div></div>
                                </div>
                            </div>
                        </div>
                        <div class="g12-certificate-field-wide"><label class="form-label" for="g9CertificateRecipient">Received By</label><input class="form-control" name="recipient" id="g9CertificateRecipient" maxlength="255" placeholder="Student or authorized recipient name"></div>
                        <div class="g12-certificate-field-wide" data-certificate-reason-field hidden><label class="form-label" for="g9CertificateReason">Correction Reason</label><textarea class="form-control" name="reason" id="g9CertificateReason" rows="2" maxlength="1000" placeholder="Required when correcting a received certificate"></textarea></div>
                    </div>
                </section>
                <details class="g9-certificate-history mt-3" data-certificate-history hidden><summary>Previous uploads</summary><div data-certificate-history-files></div></details>
                <p class="text-secondary small mt-3 mb-0">Upload the student-signed copy before marking the certificate Received by Student. Previous uploads are retained.</p>
            </div>
            <div class="modal-footer"><button class="btn" type="button" data-bs-dismiss="modal">Close</button><button class="btn btn-primary" type="submit" data-certificate-save><i class="ti ti-device-floppy me-1" aria-hidden="true"></i>Save Certificate</button></div>
        </form></div>
    </div>
    @if($canNumberSettings)@include('g9-national-exams._certificate-number-settings')@endif
    @include('g9-national-exams.photo-viewer')
</div>
@endsection
