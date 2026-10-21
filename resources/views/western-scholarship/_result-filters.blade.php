<section class="card mb-3 scholarship-result-filter-card" data-result-filters-card>
    <div class="card-header scholarship-card-header">
        <h3 class="card-title"><i class="ti ti-filter me-2" aria-hidden="true"></i>Datas Filter</h3>
        <button type="button" class="btn btn-icon btn-outline-primary scholarship-filter-toggle ms-auto" data-result-filters-toggle aria-expanded="true" aria-controls="scholarshipResultFilters" aria-label="Collapse result filters"><i class="ti ti-chevron-up" aria-hidden="true"></i></button>
    </div>
    <div class="card-body" id="scholarshipResultFilters">
        <form method="GET" action="{{ route('western-scholarship.index') }}" data-result-filters>
            <input type="hidden" name="tab" value="{{ $resultTab??'result' }}">
            <div class="scholarship-result-filters">
                <div class="scholarship-field">
                    <label for="resultFilterYear">Academic Year</label>
                    <select id="resultFilterYear" name="academic_year_id" class="form-select" data-result-picker data-placeholder="Select Academic Year" @disabled($academicYears->isEmpty())>
                        @if(!$academicYears->contains('id',$filters['academic_year_id']??null))<option value="">Select Academic Year</option>@endif
                        @foreach($academicYears as $year)<option value="{{ $year->id }}" @selected(($filters['academic_year_id']??null)==$year->id)>{{ $year->academic_year }}</option>@endforeach
                    </select>
                </div>
                <div class="scholarship-field"><label for="resultFilterCenter">Exam Center</label><select id="resultFilterCenter" name="result_center" class="form-select" data-result-picker data-placeholder="All Exam Centers" @disabled(!$exam)><option value="">All Exam Centers</option>@foreach($resultFilterOptions['centers'] as $option)<option value="{{ $option['id'] }}" @selected($resultFilters['center']===$option['id'])>{{ $option['label'] }}</option>@endforeach</select></div>
                <div class="scholarship-field"><label for="resultFilterGrade">Grade</label><select id="resultFilterGrade" name="result_grade" class="form-select" data-result-picker data-placeholder="All Grades" @disabled(!$exam || $resultFilterOptions['grades']->isEmpty())><option value="">All Grades</option>@foreach($resultFilterOptions['grades'] as $option)<option value="{{ $option['id'] }}" @selected($resultFilters['grade']===$option['id'])>{{ $option['label'] }}</option>@endforeach</select></div>
                <div class="scholarship-field"><label for="resultFilterStudent">Seat &amp; Student Name English</label><select id="resultFilterStudent" name="result_student" class="form-select" data-result-picker data-placeholder="All Students" @disabled($resultFilters['grade']===null)><option value="">All Students</option>@foreach($resultFilterOptions['students'] as $option)<option value="{{ $option['id'] }}" @selected($resultFilters['student']===$option['id'])>{{ $option['label'] }}</option>@endforeach</select></div>
            </div>
            <noscript><button class="btn btn-primary mt-3">Apply Filters</button></noscript>
        </form>
    </div>
</section>
