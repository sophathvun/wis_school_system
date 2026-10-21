<div class="modal modal-blur fade" id="certSettings" tabindex="-1" aria-labelledby="certSettingsTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header scholarship-card-header"><h3 class="modal-title" id="certSettingsTitle"><i class="ti ti-settings me-2" aria-hidden="true"></i>Certificate Settings</h3><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close Certificate Settings"></button></div>
        <div class="modal-body">
            <form id="certSettingsForm" data-cert-settings><div class="row g-3">
                <div class="col-md-4"><label class="form-label" for="certDate">Issue Date</label><input class="form-control" type="date" id="certDate" name="issue_date" value="{{ $settings['issue_date'] }}" required></div>
                <div class="col-md-4"><label class="form-label" for="certLocation">Location</label><input class="form-control" id="certLocation" name="location" value="{{ $settings['location'] }}" maxlength="150" required></div>
                <div class="col-md-4"><label class="form-label" for="certScholarshipYear">Scholarship Academic Year</label><input class="form-control" id="certScholarshipYear" name="scholarship_year" value="{{ $settings['scholarship_year'] }}" pattern="[0-9]{4}-[0-9]{4}" required></div>
                @foreach([0=>'Left Signer',1=>'Right Signer'] as $i=>$label)
                <div class="col-md-6"><fieldset class="border rounded p-3"><legend class="float-none w-auto h4 px-2">{{ $label }}</legend>
                    <div class="mb-3"><label class="form-label" for="certStaff{{ $i }}">Choose Staff (optional)</label><select class="form-select" id="certStaff{{ $i }}" data-cert-staff="{{ $i }}"><option value="manual">Enter signer manually</option>@foreach($staff as $s)<option value="{{ $loop->index }}" data-name="{{ $s['name'] }}" data-position="{{ $s['position'] }}">{{ $s['name'] }} — {{ $s['position'] }}</option>@endforeach</select></div>
                    <div class="mb-3"><label class="form-label" for="certSigner{{ $i }}">Name</label><input class="form-control" id="certSigner{{ $i }}" data-signer-name="{{ $i }}" value="{{ $settings['signers'][$i]['name']??'' }}" maxlength="150" required></div>
                    <div><label class="form-label" for="certPosition{{ $i }}">Position</label><input class="form-control" id="certPosition{{ $i }}" data-signer-position="{{ $i }}" value="{{ $settings['signers'][$i]['position']??'' }}" maxlength="150" required></div>
                </fieldset></div>
                @endforeach
            </div></form>
            <div class="row g-3 mt-2">@foreach(['frame'=>'Certificate Frame','logo'=>'Custom School Logo'] as $kind=>$label)<div class="col-md-6"><label class="form-label" for="certUpload{{ $kind }}">{{ $label }} (JPG, PNG, WebP; up to 10 MB)</label><input class="form-control" type="file" accept="image/png,image/jpeg,image/webp" id="certUpload{{ $kind }}" data-cert-upload="{{ $kind }}"></div>@endforeach</div>
            <p class="small text-secondary mt-3 mb-0">Use a portrait A4 frame. The frame’s printed logo remains on preprinted paper; replacing that logo applies to framed previews and PDFs. Staff names and positions are saved as certificate settings.</p>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button><button class="btn btn-primary" type="submit" form="certSettingsForm" data-cert-save-settings>Save Settings</button></div>
    </div></div>
</div>
