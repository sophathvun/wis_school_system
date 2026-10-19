<section class="card mb-3 scholarship-seat-filter-card" data-seat-filters-card>
    <div class="card-header scholarship-card-header"><h3 class="card-title">Data Filters</h3><button type="button" class="btn btn-sm btn-outline-light ms-auto" data-clear-seat-filters @disabled(!$exam)><i class="ti ti-filter-off me-1" aria-hidden="true"></i>Clear</button></div>
    <div class="card-body">
        <form method="GET" action="{{ route('western-scholarship.index') }}" data-seat-filters>
            <input type="hidden" name="tab" value="seat-plan">
            <div class="scholarship-seat-filters">
                <div class="scholarship-field">
                    <label for="seatFilterYear">Academic Year</label>
                    <select id="seatFilterYear" name="academic_year_id" class="form-select" data-seat-picker data-placeholder="Select Academic Year" @disabled($academicYears->isEmpty())>
                        @if(!$academicYears->contains('id',$filters['academic_year_id']??null))
                            <option value="">Select Academic Year</option>
                        @endif
                        @foreach($academicYears as $year)
                            <option value="{{ $year->id }}" @selected(($filters['academic_year_id']??null)==$year->id)>{{ $year->academic_year }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="scholarship-field"><label for="seatFilterCenter">Exam Center</label><select id="seatFilterCenter" name="seat_center" class="form-select" data-seat-picker data-placeholder="All Exam Centers" @disabled(!$exam)><option value="">All Exam Centers</option>@foreach($seatFilterOptions['centers'] as $option)<option value="{{ $option['id'] }}" @selected($seatFilters['center']!==null && $seatFilters['center']===$option['id'])>{{ $option['label'] }}</option>@endforeach</select></div>
                <div class="scholarship-field"><label for="seatFilterRoom">Room</label><select id="seatFilterRoom" name="seat_room[]" multiple class="form-select" data-seat-picker data-placeholder="All Rooms" @disabled($seatFilters['center']===null)>@foreach($seatFilterOptions['rooms'] as $option)<option value="{{ $option['id'] }}" @selected(in_array($option['id'],$seatFilters['rooms'],true))>{{ $option['label'] }}</option>@endforeach</select></div>
                <div class="scholarship-field"><label for="seatFilterStudent">Student Name</label><select id="seatFilterStudent" name="seat_student" class="form-select" data-seat-picker data-placeholder="All Students" @disabled(!$seatFilters['rooms'])><option value="">All Students</option>@foreach($seatFilterOptions['students'] as $option)<option value="{{ $option['id'] }}" @selected($seatFilters['student']===$option['id'])>{{ $option['label'] }}</option>@endforeach</select></div>
                <div class="scholarship-field"><label for="seatFilterSearch">Search</label><input id="seatFilterSearch" name="seat_search" type="search" class="form-control" maxlength="150" value="{{ $seatFilters['search'] }}" placeholder="Center, room, seat, name or campus" data-search-normalized="true" @disabled(!$exam)></div>
            </div>
            <noscript><button class="btn btn-primary mt-3">Apply Filters</button></noscript>
        </form>
    </div>
    <script type="application/json" data-seat-filter-state>@json(['filters'=>$seatFilters,'options'=>$seatFilterOptions])</script>
</section>
