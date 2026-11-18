<div data-wmi-registrations data-save-url="{{ route('wmi-management.registrations.store',[],false) }}" data-search-url="{{ route('wmi-management.registrations.enrollments',[],false) }}" data-import-url="{{ route('wmi-management.registrations.import-ids',[],false) }}" data-allocate-url="{{ route('wmi-management.registrations.allocate',[],false) }}">
    @include('wmi._registration-filters')
    <div class="wmi-registration-summary mb-3">
        @foreach([['Registered Candidates',$registrationSummary->total,'ti-users'],['Committee IDs Assigned',$registrationSummary->assigned_ids,'ti-id'],['Rooms & Seats Assigned',$registrationSummary->seated,'ti-armchair']] as [$label,$value,$icon])
            <div class="card"><div class="card-body d-flex align-items-center gap-3"><span class="wmi-content-icon"><i class="ti {{ $icon }}" aria-hidden="true"></i></span><div><div class="text-secondary small">{{ $label }}</div><strong class="h2 mb-0">{{ number_format($value) }}</strong></div></div></div>
        @endforeach
    </div>
    <section class="card wmi-years-card wmi-registration-list">
        <div class="card-header gap-3 flex-wrap"><span class="wmi-content-icon"><i class="ti ti-user-plus" aria-hidden="true"></i></span><div><div class="page-pretitle">Preliminary Round</div><h2 class="card-title mb-0">{{ $tab==='candidates'?'List of Candidates':'Pre Registration' }}</h2></div>
            <button type="button" class="btn btn-primary ms-auto" data-wmi-registration-new @disabled($registrationYears->isEmpty()||$registrationCenters->isEmpty())><i class="ti ti-plus me-2" aria-hidden="true"></i>Register Candidate</button>
        </div>
        @if($registrationYears->isEmpty()||$registrationCenters->isEmpty())<div class="card-body border-bottom"><div class="alert alert-info mb-0">Create a WMI Year and assign an exam center in Settings to start registration.</div></div>@endif
        <div class="table-responsive wmi-registration-table-wrap"><table class="table table-vcenter mb-0 wmi-registration-table"><thead><tr><th class="wmi-list-number">No.</th><th>Student Name</th><th>Contact Info</th><th>Study Information</th><th>Registration</th><th class="wmi-list-action">Action</th></tr></thead><tbody>
            @forelse($registrations as $record)
                @php($payload=array_merge($record->only(['id','wmi_year_id','wmi_exam_center_id','student_source','enrollment_id','student_code','student_name','student_name_kh','gender','current_grade','grade_contest','campus_study','registrar_campus_id','wmi_school_id','phone_number','telegram_number','email_address','room_no','seat_no','remark']),['date_of_birth'=>$record->date_of_birth?->format('Y-m-d'),'registration_channel'=>$record->registrar_campus_id?'campus':'online']))
                <tr>
                    <td data-label="No."><span class="wmi-year-number">{{ $registrations->firstItem()+$loop->index }}</span></td>
                    <td data-label="Student Name">
                        <strong class="wmi-list-student-name">{{ $record->student_name }}</strong>
                        <div class="wmi-list-line"><span class="wmi-list-label">DOB.:</span> {{ $record->date_of_birth?->format('d-M-Y')?:'?' }}</div>
                        <div class="wmi-list-line"><span class="wmi-list-label">Gender:</span> {{ $record->gender==='M'?'Male':($record->gender==='F'?'Female':'?') }}</div>
                        <div class="wmi-list-line"><span class="wmi-list-label">Student ID:</span> {{ $record->student_code?:'?' }}</div>
                    </td>
                    <td data-label="Contact Info">
                        <div class="wmi-list-contact"><i class="ti ti-phone" aria-hidden="true"></i><span class="visually-hidden">Phone Number: </span>@if($record->phone_number)<a href="tel:{{ $record->phone_number }}">{{ $record->phone_number }}</a>@else<span>?</span>@endif</div>
                        <div class="wmi-list-contact"><i class="ti ti-brand-telegram" aria-hidden="true"></i><span class="visually-hidden">Telegram: </span><span>{{ $record->telegram_number?:'?' }}</span></div>
                        <div class="wmi-list-contact"><i class="ti ti-mail" aria-hidden="true"></i><span class="visually-hidden">Email: </span>@if($record->email_address)<a href="mailto:{{ $record->email_address }}">{{ $record->email_address }}</a>@else<span>?</span>@endif</div>
                    </td>
                    <td data-label="Study Information">
                        <strong>{{ $record->school?->name_en??'Western International School' }}</strong>
                        <div class="wmi-list-line"><span class="wmi-list-label">School Type:</span> {{ $record->school?->school_type??'Private' }}</div>
                        <div class="wmi-list-inline"><span><span class="wmi-list-label">Campus:</span> {{ $record->campus_study }}</span><span><span class="wmi-list-label">Grade:</span> {{ $record->current_grade }}</span></div>
                    </td>
                    <td data-label="Registration">
                        <div class="wmi-list-line"><span class="wmi-list-label">Exam Center:</span> {{ $record->center->name }}</div>
                        <div class="wmi-list-inline"><span><span class="wmi-list-label">Grade Contest:</span> {{ $record->grade_contest==='K'?'Kindergarten':$record->grade_contest }}</span><span><span class="wmi-list-label">Campus Reg.:</span> {{ $record->campus_registered }}</span></div>
                        <div class="wmi-list-line"><span class="wmi-list-label">Registered by:</span> {{ $record->registeredBy?->name?:'?' }}</div>
                        <div class="wmi-list-line"><span class="wmi-list-label">Registered Date:</span> {{ $record->created_at?->format('d-M-Y')?:'?' }}</div>
                    </td>
                    <td data-label="Action"><div class="wmi-list-actions">
                        <button type="button" class="btn btn-sm btn-outline-primary" data-wmi-registration-edit data-registration='@json($payload)' aria-label="Edit {{ $record->student_name }}"><i class="ti ti-edit me-1" aria-hidden="true"></i>Edit</button>
                        <button type="button" class="btn btn-sm btn-outline-danger" data-wmi-registration-delete data-id="{{ $record->id }}" data-name="{{ $record->student_name }}" aria-label="Delete {{ $record->student_name }}"><i class="ti ti-trash me-1" aria-hidden="true"></i>Delete</button>
                    </div></td>
                </tr>
            @empty<tr class="wmi-registration-empty"><td colspan="6" class="text-center py-5"><span class="wmi-empty-icon"><i class="ti ti-users" aria-hidden="true"></i></span><h3>No candidates found</h3><p class="text-secondary mb-0">Register a Western student or a student from another school.</p></td></tr>@endforelse
        </tbody></table></div>
        <div class="card-footer">@include('wmi._center-pagination',['records'=>$registrations])</div>
    </section>
</div>
