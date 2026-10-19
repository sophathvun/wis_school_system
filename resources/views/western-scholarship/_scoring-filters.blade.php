<section class="card mb-3 scholarship-scoring-filter-card" data-scoring-filters-card>
    <div class="card-header scholarship-card-header">
        <h3 class="card-title"><i class="ti ti-filter me-2" aria-hidden="true"></i>Datas Filter</h3>
        <button type="button" class="btn btn-icon btn-outline-primary scholarship-filter-toggle ms-auto" data-scoring-filters-toggle aria-expanded="true" aria-controls="scholarshipScoringFilters" aria-label="Collapse scoring filters"><i class="ti ti-chevron-up" aria-hidden="true"></i></button>
    </div>
    <div class="card-body" id="scholarshipScoringFilters">
        <form method="GET" action="{{ route('western-scholarship.index') }}" data-scoring-filters>
            <input type="hidden" name="tab" value="scoring">
            <div class="scholarship-scoring-filters">
                <div class="scholarship-field">
                    <label for="scoreFilterYear">Academic Year</label>
                    <select id="scoreFilterYear" name="academic_year_id" class="form-select" data-score-picker data-placeholder="Select Academic Year" @disabled($academicYears->isEmpty())>
                        @if(!$academicYears->contains('id',$filters['academic_year_id']??null))<option value="">Select Academic Year</option>@endif
                        @foreach($academicYears as $year)<option value="{{ $year->id }}" @selected(($filters['academic_year_id']??null)==$year->id)>{{ $year->academic_year }}</option>@endforeach
                    </select>
                </div>
                <div class="scholarship-field"><label for="scoreFilterCenter">Exam Center</label><select id="scoreFilterCenter" name="score_center" class="form-select" data-score-picker data-placeholder="All Exam Centers" @disabled(!$exam)><option value="">All Exam Centers</option>@foreach($scoreFilterOptions['centers'] as $option)<option value="{{ $option['id'] }}" @selected($scoreFilters['center']===$option['id'])>{{ $option['label'] }}</option>@endforeach</select></div>
                <div class="scholarship-field"><label for="scoreFilterRoom">Room</label><select id="scoreFilterRoom" name="score_room" class="form-select" data-score-picker data-placeholder="All Rooms" @disabled($scoreFilters['center']===null)><option value="">All Rooms</option>@foreach($scoreFilterOptions['rooms'] as $option)<option value="{{ $option['id'] }}" @selected($scoreFilters['room']===$option['id'])>{{ $option['label'] }}</option>@endforeach</select></div>
                <div class="scholarship-field"><label for="scoreFilterStudent">Student Name</label><select id="scoreFilterStudent" name="score_student" class="form-select" data-score-picker data-placeholder="All Students" @disabled($scoreFilters['room']===null)><option value="">All Students</option>@foreach($scoreFilterOptions['students'] as $option)<option value="{{ $option['id'] }}" @selected($scoreFilters['student']===$option['id'])>{{ $option['label'] }}</option>@endforeach</select></div>
            </div>
            <noscript><button class="btn btn-primary mt-3">Apply Filters</button></noscript>
        </form>
        @include('western-scholarship._scoring-mode')
    </div>
</section>
