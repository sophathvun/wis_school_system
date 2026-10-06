<form id="k3CertificateSettings" method="post" action="{{ route('reports.k3-certificates.save') }}" class="card-body pt-0 k3-certificate-settings">
    @csrf
    <input type="hidden" name="academic_year_id" value="{{ $filters['academic_year_id']??'' }}">
    @foreach(['campus_id','grade_class','certificate_student_id'] as $key)<input type="hidden" name="{{ $key }}" value="{{ $filters[$key]??'' }}">@endforeach
    @if(session('success'))
        @php
            $feedbackTitle = match(session('k3_action')) {
                'save_date' => 'Given Date Saved',
                'assign' => 'Certificate Numbers Assigned',
                'update_prefix' => 'Certificate Prefix Updated',
                'save_template' => 'Template Saved',
                default => 'Certificate Settings Saved',
            };
        @endphp
        <div class="alert alert-success" data-k3-action-feedback="success" data-k3-action-title="{{ $feedbackTitle }}">{{ session('success') }}</div>
    @endif
    @if($errors->any())<div class="alert alert-danger" data-k3-action-feedback="error" data-k3-action-title="Action Not Completed">{{ $errors->first() }}</div>@endif
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-end">
        @if($canSaveGivenDate ?? false)<button class="btn btn-primary" type="submit" name="action" value="save_date">Save Given Date</button>@endif
        @if($canAssignCertificateNumbers ?? false)<button class="btn btn-outline-primary" type="submit" name="action" value="assign"><i class="ti ti-list-numbers me-1"></i>Assign Certificate Numbers</button>@endif
        @if($canEditCertificatePrefix ?? false)<button class="btn btn-outline-primary" type="submit" name="action" value="update_prefix">Update Prefix</button>@endif
    </div>
    @if(!$canManageCertificates && !empty($filters['academic_year_id']))<p class="k3-certificate-note mt-1 mb-0">Your permissions do not allow changing K3 certificate settings.</p>@endif
</form>
