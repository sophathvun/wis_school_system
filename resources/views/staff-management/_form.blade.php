@php($showForm=$editStaff || $createStaff || old('educations_present'))
<div class="modal modal-blur fade" id="staffModal" tabindex="-1" aria-hidden="true" data-open-on-load="{{ $showForm?'1':'0' }}">
<div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content staff-modal-content">
    <div class="modal-header staff-modal-header"><div class="d-flex align-items-center gap-3"><span class="staff-header-icon"><i class="ti ti-user-edit" aria-hidden="true"></i></span><div><h5 class="modal-title">{{ $editStaff || old('staff_id')?'Edit Staff':'Create Staff' }}</h5><p>Personal details, work assignments, and professional background.</p></div></div><a class="btn-close btn-close-white" href="{{ route('staff-management.index') }}" aria-label="Close"></a></div>
    <form method="post" action="{{ route('staff-management.save') }}" enctype="multipart/form-data" data-staff-profile-form>
        @csrf<input type="hidden" name="staff_id" value="{{ old('staff_id',$editStaff?->id) }}">
        <input type="hidden" name="experiences_present" value="1"><input type="hidden" name="educations_present" value="1">
        <div class="modal-body">
            @if($errors->any() && old('educations_present'))<div class="alert alert-danger" role="alert"><strong>Please review the highlighted fields.</strong><ul class="mb-0 mt-2">@foreach($errors->messages() as $key=>$messages)<li><button class="staff-error-link" type="button" data-staff-error-name="{{ $key }}">{{ $messages[0] }}</button></li>@endforeach</ul></div>@endif
            <ul class="nav nav-tabs mb-3 staff-form-tabs" role="tablist">
                @foreach(['info'=>['Staff Information','ti-user-circle'],'work'=>['Work Information','ti-briefcase'],'education'=>['Education','ti-school'],'experience'=>['Previous Employment','ti-building']] as $key=>[$title,$icon])
                    <li class="nav-item" role="presentation"><button class="nav-link {{ $loop->first?'active':'' }}" id="staff-{{ $key }}-nav" data-bs-toggle="tab" data-bs-target="#staff-{{ $key }}-tab" type="button" role="tab" aria-controls="staff-{{ $key }}-tab" aria-selected="{{ $loop->first?'true':'false' }}"><i class="ti {{ $icon }} me-2" aria-hidden="true"></i>{{ $title }}</button></li>
                @endforeach
            </ul>
            <div class="tab-content">
                <div class="tab-pane active" id="staff-info-tab" role="tabpanel" aria-labelledby="staff-info-nav"><div class="row g-3">
                    <div class="col-12"><section class="card staff-form-section-card" aria-labelledby="staffPersonalInformationTitle">
                        <div class="card-header staff-form-section-header"><h3 class="card-title" id="staffPersonalInformationTitle"><i class="ti ti-user-circle me-2" aria-hidden="true"></i>Personal Information</h3></div>
                        <div class="card-body"><div class="staff-personal-information-layout">
                    <div class="staff-personal-photo-column">
                        <div class="logo-dropzone staff-photo-dropzone" tabindex="0" role="button" aria-label="Upload staff photo. Click, drag and drop, or paste an image." data-staff-photo-dropzone>
                            <div class="staff-photo-dropzone-content"><span class="staff-photo-dropzone-label">Staff Photo</span><i class="ti ti-cloud-upload logo-dropzone-icon" aria-hidden="true"></i><strong>Drag and drop staff photo here</strong><span class="text-secondary">or click, paste, or upload a file</span><span class="form-hint">JPG, PNG, or WEBP · Maximum 2 MB.<br>Crop to 400 × 400 px before uploading.</span></div>
                            <input id="staff-photo" class="d-none" type="file" name="photo" accept="image/jpeg,image/png,image/webp">
                            <img class="staff-photo-preview {{ $editStaff?->photo_path?'':'d-none' }}" src="{{ $editStaff?->photo_path?asset('storage/'.$editStaff->photo_path):'' }}" alt="Staff photo preview" data-staff-photo-preview>
                        </div>
                        @error('photo')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="staff-personal-fields-column"><div class="staff-personal-fields-grid">
                    @include('staff-management._field',['name'=>'staff_code','label'=>'Staff ID','width'=>'col-12','required'=>true])
                    @include('staff-management._field',['name'=>'name_en','label'=>'Staff Name (English)','required'=>true])
                    @include('staff-management._field',['name'=>'name_kh','label'=>'Staff Name (Khmer)'])
                    @include('staff-management._field',['name'=>'gender','label'=>'Gender (Khmer / English)','bilingualGender'=>true,'options'=>['Male'=>'ប្រុស / Male','Female'=>'ស្រី / Female','Other'=>'ផ្សេងៗ / Other']])
                    @include('staff-management._birth-date')
                    @include('staff-management._marital-status')
                    @include('staff-management._birthplace')
                    @include('staff-management._nationality')
                    @include('staff-management._phone-field',['name'=>'phone','label'=>'Phone Number'])
                    @include('staff-management._phone-field',['name'=>'secondary_phone','label'=>'Alternative Phone Number'])
                    @include('staff-management._field',['name'=>'email','label'=>'Email','type'=>'email'])
                    </div></div>
                        </div></div>
                    </section></div>
                    {{-- Work Email is reserved for later use.
                    <div class="col-12">@include('staff-management._section',['title'=>'Contact Details','icon'=>'ti-address-book','hint'=>'Personal and work contact information.'])</div>
                    @include('staff-management._field',['name'=>'work_email','label'=>'Work Email','type'=>'email'])
                    --}}
                    @include('staff-management._address')
                    @include('staff-management._identification')
                    @include('staff-management._emergency-contact')
                    {{-- Remarks are reserved for later use.
                    @include('staff-management._field',['name'=>'notes','label'=>'Remarks','type'=>'textarea','width'=>'col-12'])
                    --}}
                </div></div>
                <div class="tab-pane" id="staff-work-tab" role="tabpanel" aria-labelledby="staff-work-nav" data-employment-status="{{ old('employment_status',$editStaff?->employment_status??'active') }}"><div class="row g-3">
                    <div class="col-12"><section class="card staff-form-section-card" aria-labelledby="staffWorkAssignmentTitle">
                        <div class="card-header staff-form-section-header"><h3 class="card-title" id="staffWorkAssignmentTitle"><i class="ti ti-briefcase me-2" aria-hidden="true"></i>Work Assignment</h3></div>
                        <div class="card-body"><div class="row g-3">
                    <div class="col-12"><div class="staff-work-assignment-grid">
                        @include('staff-management._date-field',['name'=>'joined_on','label'=>'Hire Date','width'=>'staff-work-assignment-field'])
                        @include('staff-management._field',['name'=>'position_id','label'=>'Position','options'=>$positions->pluck('name','id')->all(),'optionDepartments'=>$positions->pluck('department_id','id')->all(),'searchable'=>true,'width'=>'staff-work-assignment-field'])
                        @include('staff-management._field',['name'=>'department_id','label'=>'Department','options'=>$departments->pluck('hierarchy_name','id')->all(),'searchable'=>true,'width'=>'staff-work-assignment-field'])
                        @include('staff-management._field',['name'=>'reports_to_staff_id','label'=>'Reports To / Supervisor','options'=>$supervisors->mapWithKeys(fn($person)=>[$person->id=>$person->name_en.' · '.$person->staff_code])->all(),'searchable'=>true,'width'=>'staff-work-assignment-field'])
                        @include('staff-management._field',['name'=>'primary_campus_id','label'=>'Primary Campus','options'=>$campuses->pluck('campus_name_en','id')->all(),'searchable'=>true,'width'=>'staff-work-assignment-field'])
                    </div></div>
                    <div class="col-12"><div class="staff-work-details-grid">
                        @include('staff-management._field',['name'=>'staff_category','label'=>'Staff Category','options'=>$categoryLabels,'showArrow'=>true,'required'=>true,'value'=>$editStaff?->staff_category??'staff','width'=>'staff-work-details-field'])
                        @include('staff-management._field',['name'=>'employment_type','label'=>'Employment Type','options'=>$typeLabels,'showArrow'=>true,'required'=>true,'value'=>$editStaff?->employment_type??'FT','width'=>'staff-work-details-field'])
                        @include('staff-management._working-campuses',['width'=>'staff-work-details-field'])
                        @include('staff-management._field',['name'=>'employment_status','label'=>'Employment Status','options'=>$statusLabels,'showArrow'=>true,'required'=>true,'value'=>$editStaff?->employment_status??'active','width'=>'staff-work-details-field'])
                        @include('staff-management._date-field',['name'=>'ended_on','label'=>'Resignation / Termination / Retirement Date','width'=>'staff-work-details-field staff-employment-end-date-field'])
                    </div></div>
                    <div class="col-12"><div class="staff-work-follow-up-grid">
                        @include('staff-management._profile-status')
                        @include('staff-management._field',['name'=>'employment_end_reason','label'=>'Leaving Reason','type'=>'textarea','width'=>'staff-work-follow-up-field staff-employment-end-reason-field'])
                    </div></div>
                        </div></div>
                    </section></div>
                    <div class="col-12"><section class="card staff-form-section-card" aria-labelledby="staffContractProbationTitle">
                        <div class="card-header staff-form-section-header"><h3 class="card-title" id="staffContractProbationTitle"><i class="ti ti-file-description me-2" aria-hidden="true"></i>Contract &amp; Probation</h3></div>
                        <div class="card-body"><div class="staff-contract-grid">
                            @include('staff-management._field',['name'=>'contract_type','label'=>'Contract Type','options'=>$contractLabels,'width'=>'staff-contract-field'])
                            @include('staff-management._field',['name'=>'contract_number','label'=>'Contract Number','width'=>'staff-contract-field'])
                            @include('staff-management._date-field',['name'=>'contract_started_on','label'=>'Contract Start Date','width'=>'staff-contract-field'])
                            @include('staff-management._date-field',['name'=>'contract_ends_on','label'=>'Contract End Date','width'=>'staff-contract-field'])
                            @include('staff-management._date-field',['name'=>'probation_ends_on','label'=>'Probation End Date','width'=>'staff-contract-field'])
                        </div></div>
                    </section></div>
                    <div class="col-12"><section class="card staff-form-section-card" aria-labelledby="staffWorkingScheduleTitle">
                        <div class="card-header staff-form-section-header"><h3 class="card-title" id="staffWorkingScheduleTitle"><i class="ti ti-calendar-time me-2" aria-hidden="true"></i>Working Schedule</h3></div>
                        <div class="card-body"><div class="row g-3">
                    @foreach([1,2] as $session)
                        @include('staff-management._field',['name'=>'session_'.$session.'_in','label'=>'Session '.$session.' — Time In','type'=>'time','width'=>'col-md-3'])
                        @include('staff-management._field',['name'=>'session_'.$session.'_out','label'=>'Session '.$session.' — Time Out','type'=>'time','width'=>'col-md-3'])
                    @endforeach
                    <div class="col-12"><h4>Working Days</h4><p class="text-secondary">Select each working day. Blank times use the default session times above; enter a time to override it for that day.</p>
                        <div class="d-flex flex-wrap gap-2 mb-3"><button type="button" class="btn btn-sm btn-outline-secondary" data-staff-schedule-preset="mon,tue,wed,thu,fri">Mon–Fri</button><button type="button" class="btn btn-sm btn-outline-secondary" data-staff-schedule-preset="mon,tue,wed,thu,fri,sat">Mon–Sat</button><button type="button" class="btn btn-sm btn-outline-secondary" data-staff-schedule-preset="">Clear Days</button></div>
                        <div class="staff-schedule"><div class="staff-schedule-head"><span>Day</span><span>Working Period</span><span>Session 1 In</span><span>Session 1 Out</span><span>Session 2 In</span><span>Session 2 Out</span></div>
                        @foreach($days as $day=>$label)<div class="staff-schedule-row" data-staff-day="{{ $day }}"><strong>{{ $label }}</strong>
                            <select class="form-select" name="work_schedule[{{ $day }}][period]" aria-label="{{ $label }} working period">@foreach($periods as $key=>$title)<option value="{{ $key }}" @selected(($schedule[$day]['period']??'off')===$key)>{{ $title }}</option>@endforeach</select>
                            @foreach(['session_1_in','session_1_out','session_2_in','session_2_out'] as $key)<label class="staff-schedule-time"><span>{{ ucwords(str_replace('_',' ',$key)) }}</span><input class="form-control" type="time" step="60" name="work_schedule[{{ $day }}][{{ $key }}]" value="{{ $schedule[$day][$key]??'' }}" aria-label="{{ $label }} {{ str_replace('_',' ',$key) }}"><small class="staff-schedule-hint" data-staff-time-hint></small></label>@endforeach
                        </div>@endforeach</div>
                    </div>
                        </div></div>
                    </section></div>
                    @if($editStaff || old('staff_id'))
                        <div class="col-12">@include('staff-management._section',['title'=>'Work Change Details','icon'=>'ti-history','hint'=>'Record the effective date and reason for a promotion or transfer.'])</div>
                        @include('staff-management._field',['name'=>'work_change_type','label'=>'Change Type','options'=>\App\Support\StaffProfile::CHANGES,'value'=>'work_update'])
                        @include('staff-management._field',['name'=>'work_effective_on','label'=>'Effective Date','type'=>'date','value'=>now()->toDateString()])
                        @include('staff-management._field',['name'=>'work_change_reason','label'=>'Reason / Remarks for Change','type'=>'textarea','width'=>'col-12'])
                        <div class="form-hint">Changes to position, department, campus, employment status, or schedule are recorded in Work History.</div>
                    @endif
                </div></div>
                <div class="tab-pane" id="staff-education-tab" role="tabpanel" aria-labelledby="staff-education-nav">@include('staff-management._section',['title'=>'Education','icon'=>'ti-school','hint'=>'Add qualifications, institutions, and graduation details.'])<div id="staffEducationRows" class="vstack gap-3" data-staff-repeat="educations">
                    @foreach($educationRows as $index=>$row) @include('staff-management._education-row') @endforeach
                </div><button class="btn btn-outline-primary mt-3" type="button" data-staff-add="educations"><i class="ti ti-plus me-1"></i>Add Education</button></div>
                <div class="tab-pane" id="staff-experience-tab" role="tabpanel" aria-labelledby="staff-experience-nav">@include('staff-management._section',['title'=>'Previous Employment','icon'=>'ti-building','hint'=>'Keep a record of previous employers and responsibilities.'])<div id="staffExperienceRows" class="vstack gap-3" data-staff-repeat="experiences">
                    @foreach($experienceRows as $index=>$row) @include('staff-management._experience-row') @endforeach
                </div><button class="btn btn-outline-primary mt-3" type="button" data-staff-add="experiences"><i class="ti ti-plus me-1"></i>Add Previous Employment</button></div>
            </div>
            <template data-staff-template="educations">@include('staff-management._education-row',['index'=>'__INDEX__','row'=>[]])</template>
            <template data-staff-template="experiences">@include('staff-management._experience-row',['index'=>'__INDEX__','row'=>[]])</template>
        </div>
        <div class="modal-footer staff-modal-footer"><a class="btn me-auto" href="{{ route('staff-management.index') }}">Cancel</a><button class="btn btn-primary" type="submit"><i class="ti ti-device-floppy me-2"></i>{{ $editStaff || old('staff_id')?'Save Changes':'Create Staff' }}</button></div>
    </form>
</div></div></div>
