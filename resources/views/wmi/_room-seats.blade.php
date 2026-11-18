<section class="card" data-wmi-registration-tools data-allocate-url="{{ route('wmi-management.registrations.allocate',[],false) }}">
    <div class="card-header gap-3"><span class="wmi-content-icon"><i class="ti ti-armchair" aria-hidden="true"></i></span><div><div class="page-pretitle">Settings</div><h2 class="card-title mb-0">Assign Rooms &amp; Seats</h2></div></div>
    <div class="card-body">
        <h3>Organize candidate seating by exam center</h3>
        <p class="text-secondary">Choose an exam center and its WMI year, then set the number of candidates per room. Candidates are ordered by contest grade and English name.</p>
        <div class="alert alert-info">Existing room and seat assignments are preserved. Unassigned candidates start in new rooms after the last assigned room.</div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#wmiSeatModal" @disabled($registrationCenters->isEmpty()||!\App\Support\WmiPermissions::current('settings.room-seats.assign'))><i class="ti ti-armchair me-2" aria-hidden="true"></i>Assign Rooms &amp; Seats</button>
        @if(\App\Support\WmiPermissions::current('preliminary-round.candidates'))<a class="btn btn-outline-secondary ms-2" href="{{ route('wmi-management.section',['section'=>'preliminary-round','tab'=>'candidates']) }}">View Candidates</a>@endif
    </div>
</section>
