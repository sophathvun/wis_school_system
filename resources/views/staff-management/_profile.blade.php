<div class="modal modal-blur fade" id="staffViewModal" tabindex="-1" aria-hidden="true" data-open-on-load="{{ $viewStaff?'1':'0' }}">
<div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content staff-modal-content">
    <div class="modal-header staff-modal-header"><div class="d-flex align-items-center gap-3"><span class="staff-header-icon"><i class="ti ti-id-badge-2" aria-hidden="true"></i></span><div><h5 class="modal-title">Staff Profile</h5><p>Personal information and employment journey.</p></div></div><a class="btn-close btn-close-white" href="{{ route('staff-management.index') }}" aria-label="Close"></a></div>
    @if($viewStaff)
    <div class="modal-body staff-profile-body">
        <div class="staff-profile-hero">
            <span class="avatar staff-profile-avatar" @if($viewStaff->photo_path) style="background-image:url('{{ asset('storage/'.$viewStaff->photo_path) }}')" @endif>@unless($viewStaff->photo_path){{ mb_strtoupper(mb_substr($viewStaff->name_en,0,1)) }}@endunless</span>
            <div class="staff-profile-identity"><div class="staff-profile-eyebrow">{{ $viewStaff->staff_code }} · {{ $categoryLabels[$viewStaff->staff_category]??$viewStaff->staff_category }}</div><h2>{{ $viewStaff->name_en }}</h2>@if($viewStaff->name_kh)<div class="staff-khmer staff-profile-khmer">{{ $viewStaff->name_kh }}</div>@endif<div class="staff-profile-role">{{ $viewStaff->position?->name??'Position not assigned' }}@if($viewStaff->department) · {{ $viewStaff->department->hierarchy_name }}@endif</div>
                <div class="staff-profile-badges"><span class="badge {{ $viewStaff->employment_status==='active'?'bg-success-lt':'bg-warning-lt' }}">{{ $statusLabels[$viewStaff->employment_status]??$viewStaff->employment_status }}</span><span class="staff-hero-chip">{{ $typeLabels[$viewStaff->employment_type]??$viewStaff->employment_type }}</span>@if($viewStaff->nationality_note)<span class="staff-hero-chip">{{ $viewStaff->nationality_note }}</span>@endif @unless($viewStaff->status)<span class="staff-hero-chip">Profile Inactive</span>@endunless</div>
            </div>
            @if($canEditStaff)<a class="btn btn-light staff-profile-edit" href="{{ route('staff-management.index',['edit'=>$viewStaff->id]) }}"><i class="ti ti-edit me-2"></i>Edit Profile</a>@endif
        </div>
        <div class="staff-profile-summary">
            @foreach(['Primary Campus'=>$viewStaff->primaryCampus?->campus_name_en??'—','Hire Date'=>$viewStaff->joined_on?->format('d-M-Y')??'—','Working Days'=>count($viewStaff->effective_work_schedule)?count($viewStaff->effective_work_schedule).' days / week':'Not configured','User Account'=>$viewStaff->user?->username??'No login linked'] as $label=>$display)<div><span>{{ $label }}</span><strong>{{ $display }}</strong></div>@endforeach
        </div>
        <ul class="nav nav-tabs staff-form-tabs staff-profile-tabs" role="tablist">
            @foreach(['personal'=>['Staff Information','ti-user-circle'],'work'=>['Work Information','ti-briefcase'],'qualifications'=>['Qualifications','ti-school'],'history'=>['Work History','ti-history']] as $key=>[$label,$icon])
                <li class="nav-item" role="presentation"><button class="nav-link {{ $loop->first?'active':'' }}" id="profile-{{ $key }}-nav" data-bs-toggle="tab" data-bs-target="#profile-{{ $key }}-tab" type="button" role="tab" aria-controls="profile-{{ $key }}-tab" aria-selected="{{ $loop->first?'true':'false' }}"><i class="ti {{ $icon }} me-2" aria-hidden="true"></i>{{ $label }}@if($key==='history')<span class="badge bg-secondary-lt ms-2">{{ $viewStaff->workHistories->count() }}</span>@endif</button></li>
            @endforeach
        </ul>
        <div class="tab-content staff-profile-panels">
            <div class="tab-pane active" id="profile-personal-tab" role="tabpanel" aria-labelledby="profile-personal-nav">
                @include('staff-management._section',['title'=>'Personal Information','icon'=>'ti-user-circle'])
                @include('staff-management._details',['fields'=>['staff_code'=>'Staff ID','name_en'=>'Staff Name (English)','name_kh'=>'Staff Name (Khmer)','gender'=>'Gender (English)','gender_kh'=>'Gender (Khmer)','date_of_birth'=>'Date of Birth (English)','date_of_birth_kh'=>'Date of Birth (Khmer)','birthCountry'=>'Country of Birth','place_of_birth_en'=>'Place of Birth (English)','place_of_birth_kh'=>'Place of Birth (Khmer)','nationality'=>'Nationality (English)','nationality_kh'=>'Nationality (Khmer)','nationality_note'=>'Nationality Note','marital_status'=>'Marital Status (English)','marital_status_kh'=>'Marital Status (Khmer)']])
                @include('staff-management._section',['title'=>'Contact Details','icon'=>'ti-address-book'])
                @include('staff-management._details',['fields'=>['phone'=>'Phone Number','secondary_phone'=>'Alternative Phone Number','email'=>'Email','work_email'=>'Work Email']])
                @include('staff-management._section',['title'=>'Current Address','icon'=>'ti-map-pin'])
                @include('staff-management._details',['fields'=>['addressCountry'=>'Country','house_number_en'=>'House Number (English)','house_number_kh'=>'House Number (Khmer)','street_en'=>'Street (English)','street_kh'=>'Street (Khmer)','village_en'=>'Village (English)','village_kh'=>'Village (Khmer)','commune_en'=>'Commune (English)','commune_kh'=>'Commune (Khmer)','district_en'=>'District (English)','district_kh'=>'District (Khmer)','province_en'=>'Province (English)','province_kh'=>'Province (Khmer)','current_address'=>'Current Address (English)','current_address_kh'=>'Current Address (Khmer)']])
                @include('staff-management._section',['title'=>'Identification','icon'=>'ti-id'])
                @include('staff-management._details',['fields'=>['identity_document_type'=>'Document Type','identity_document_name'=>'Document Name','identity_document_number'=>match($viewStaff->identity_document_type){'national_id'=>'ID Number','passport'=>'Passport Number',default=>'Document Number'},'identity_document_issued_on'=>'Issue Date','identity_document_expires_on'=>'Expiry Date']])
                @include('staff-management._section',['title'=>'Emergency Contact','icon'=>'ti-phone-call'])
                @include('staff-management._details',['fields'=>['emergency_contact_name'=>'Contact Name','emergency_contact_phone'=>'Contact Phone','emergency_contact_relationship'=>'Relationship','emergency_contact_address'=>'Emergency Contact Address','notes'=>'Remarks']])
            </div>
            <div class="tab-pane" id="profile-work-tab" role="tabpanel" aria-labelledby="profile-work-nav">
                @if($viewStaff->employment_status==='active' && $viewStaff->contract_ends_on && $viewStaff->contract_ends_on->lte(today()->addDays(30)))<div class="staff-notice"><i class="ti ti-calendar-clock" aria-hidden="true"></i><div><strong>{{ $viewStaff->contract_ends_on->lt(today())?'Contract End Date Passed':'Contract Ending Soon' }}</strong><div>Contract end date: {{ $viewStaff->contract_ends_on->format('d-M-Y') }}</div></div></div>@endif
                @include('staff-management._section',['title'=>'Work Information','icon'=>'ti-briefcase'])
                @include('staff-management._work-snapshot',['snapshot'=>array_replace(app(\App\Services\StaffWorkInformation::class)->snapshot($viewStaff),['department'=>$viewStaff->department?->hierarchy_name])])
            </div>
            <div class="tab-pane" id="profile-qualifications-tab" role="tabpanel" aria-labelledby="profile-qualifications-nav">
                @include('staff-management._section',['title'=>'Education','icon'=>'ti-school','hint'=>'Qualifications and academic background.'])
                <div class="staff-qualification-list">
                    @forelse($viewStaff->educations as $education)<article class="staff-qualification"><span class="staff-section-icon"><i class="ti ti-school" aria-hidden="true"></i></span><div class="flex-fill"><div class="d-flex flex-wrap justify-content-between gap-2"><h4>{{ $education->degree?:'Education' }}@if($education->field_of_study) · {{ $education->field_of_study }}@endif</h4><span class="badge bg-primary-lt">Year of Graduation: {{ $education->graduated_year??$education->ended_on?->year??'—' }}</span></div><p>{{ $education->institution_name }}@if($education->country) · {{ $education->country }}@endif</p>@if($education->notes)<div class="staff-detail-value">{{ $education->notes }}</div>@endif @if($education->grade_or_result)<div class="text-secondary small mt-1">Result: {{ $education->grade_or_result }}</div>@endif @if($education->started_on || $education->ended_on)<div class="text-secondary small mt-1">{{ $education->started_on?->format('d-M-Y')??'—' }} – {{ $education->ended_on?->format('d-M-Y')??'—' }}</div>@endif</div></article>
                    @empty<div class="staff-empty"><i class="ti ti-school" aria-hidden="true"></i><p>No education recorded.</p></div>@endforelse
                </div>
                @include('staff-management._section',['title'=>'Previous Employment','icon'=>'ti-building','hint'=>'Experience before the current appointment.'])
                <div class="staff-qualification-list">
                    @forelse($viewStaff->experiences as $experience)<article class="staff-qualification"><span class="staff-section-icon"><i class="ti ti-building" aria-hidden="true"></i></span><div class="flex-fill"><div class="d-flex flex-wrap justify-content-between gap-2"><h4>{{ $experience->position?:'Previous Employment' }}</h4><span class="text-secondary small">{{ $experience->started_on?->format('d-M-Y')?:'—' }} – {{ $experience->is_current?'Present':($experience->ended_on?->format('d-M-Y')?:'—') }}</span></div><p>{{ $experience->company_name }}@if($experience->employment_type) · {{ $experience->employment_type }}@endif</p>@if($experience->responsibilities)<div class="staff-detail-value">{{ $experience->responsibilities }}</div>@endif</div></article>
                    @empty<div class="staff-empty"><i class="ti ti-building" aria-hidden="true"></i><p>No previous employment recorded.</p></div>@endforelse
                </div>
            </div>
            <div class="tab-pane" id="profile-history-tab" role="tabpanel" aria-labelledby="profile-history-nav">
                @include('staff-management._section',['title'=>'Work History','icon'=>'ti-history','hint'=>'Dated work changes with the details before and after each update.'])
                <div class="staff-history">
                    @forelse($viewStaff->workHistories as $history)
                        <article class="staff-history-entry"><span class="staff-history-dot"><i class="ti {{ $history->change_type==='promotion'?'ti-trending-up':'ti-briefcase' }}" aria-hidden="true"></i></span><div class="staff-history-card"><div class="d-flex flex-wrap gap-2 justify-content-between"><strong>{{ $changeLabels[$history->change_type]??ucwords(str_replace('_',' ',$history->change_type)) }}</strong><span class="badge bg-primary-lt">{{ $history->effective_on->format('d-M-Y') }}</span></div>
                            <p class="my-2">{{ $history->before_snapshot['position']??'—' }} <i class="ti ti-arrow-right mx-1" aria-hidden="true"></i> {{ $history->after_snapshot['position']??'—' }}</p><div class="text-secondary small">{{ $history->after_snapshot['department']??'—' }} · {{ $history->after_snapshot['primary_campus']??'—' }}</div>
                            @if($history->reason)<p class="staff-detail-value mt-2 mb-2">{{ $history->reason }}</p>@endif
                            <div class="staff-history-audit"><i class="ti ti-user-check me-1" aria-hidden="true"></i>{{ $history->changed_by_name??'—' }} · {{ $history->created_at->format('d-M-Y H:i') }}</div>
                            <details class="staff-history-details"><summary>View Work Details</summary><div class="row g-3 mt-1">@if($history->before_snapshot)<div class="col-lg-6"><h4>Before</h4>@include('staff-management._work-snapshot',['snapshot'=>$history->before_snapshot])</div>@endif<div class="{{ $history->before_snapshot?'col-lg-6':'col-12' }}"><h4>{{ $history->before_snapshot?'After':'Recorded Work Information' }}</h4>@include('staff-management._work-snapshot',['snapshot'=>$history->after_snapshot])</div></div></details>
                        </div></article>
                    @empty<div class="staff-empty"><i class="ti ti-history" aria-hidden="true"></i><h4>No work changes recorded yet</h4><p>Existing work information will be preserved when this profile is updated.</p></div>@endforelse
                </div>
            </div>
        </div>
    </div>
    <div class="modal-footer staff-modal-footer"><span class="text-secondary small me-auto">Staff ID: {{ $viewStaff->staff_code }}</span><a class="btn" href="{{ route('staff-management.index') }}">Close</a></div>
    @endif
</div></div></div>
