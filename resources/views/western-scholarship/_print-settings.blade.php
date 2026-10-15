<div class="modal fade" id="scholarshipPrintSettingsModal" tabindex="-1" aria-labelledby="scholarshipPrintSettingsTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content" method="POST" action="{{ route('western-scholarship.ranks.print-settings.save') }}" data-rank-print-settings-form novalidate>
            @csrf
            <input type="hidden" name="academic_year_id">
            <div class="modal-header scholarship-card-header">
                <div class="scholarship-print-heading"><span class="scholarship-print-icon" aria-hidden="true"><i class="ti ti-printer"></i></span><div><h3 class="modal-title" id="scholarshipPrintSettingsTitle">Print Settings</h3><p>Outstanding student report</p></div></div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="scholarship-print-scope"><p class="small text-secondary" data-print-scope-description>Choose HRM staff; positions fill automatically.</p><button type="button" class="btn btn-sm btn-outline-primary" data-print-central-shortcut hidden>Central Print Settings</button></div>
                <div class="alert alert-danger" data-print-settings-error role="alert" hidden></div>
                <div class="scholarship-print-context">
                    <div class="scholarship-print-field"><label class="form-label" for="rankPrintYear">Academic Year</label><input class="form-control" id="rankPrintYear" data-print-year-label readonly></div>
                    <div class="scholarship-print-field"><label class="form-label" for="rankPrintCampus"><span data-print-scope-label>Campus</span> <span class="text-danger">*</span></label><select class="form-select" name="campus_id" id="rankPrintCampus" required></select></div>
                </div>
                <div class="scholarship-print-signatures">
                    @foreach([
                        ['role'=>'prepared','inputId'=>'rankPrintPreparedBy','dateId'=>'rankPrintPreparedDate','label'=>'Prepared by','dateLabel'=>'Preparation Date','icon'=>'ti-pencil'],
                        ['role'=>'checked','inputId'=>'rankPrintCheckedBy','dateId'=>'rankPrintCheckedDate','label'=>'Checked and Verified by','dateLabel'=>'Verification Date','icon'=>'ti-checklist'],
                        ['role'=>'approved','inputId'=>'rankPrintApprovedBy','dateId'=>'rankPrintApprovedDate','label'=>'Approved by','dateLabel'=>'Approval Date','icon'=>'ti-rosette-discount-check'],
                    ] as $signature)
                        <section class="scholarship-signature-card" data-print-signature-role="{{ $signature['role'] }}" aria-labelledby="{{ $signature['role'] }}SignatureTitle" @if($signature['role']==='checked') hidden @endif>
                            <h4 id="{{ $signature['role'] }}SignatureTitle"><i class="ti {{ $signature['icon'] }}" aria-hidden="true"></i>{{ $signature['label'] }}</h4>
                            <div class="scholarship-print-fields">
                                <div>@include('western-scholarship._print-staff-picker',$signature)</div>
                                @include('western-scholarship._print-date-picker',$signature)
                            </div>
                        </section>
                    @endforeach
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" data-save-print-settings><i class="ti ti-device-floppy me-2" aria-hidden="true"></i>Save Settings</button></div>
        </form>
    </div>
</div>
