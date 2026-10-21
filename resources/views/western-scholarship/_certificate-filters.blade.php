<section class="card mb-3 scholarship-result-filter-card" data-cert-filters-card>
    <div class="card-header scholarship-card-header">
        <h3 class="card-title"><i class="ti ti-filter me-2" aria-hidden="true"></i>Datas Filter</h3>
        <button type="button" class="btn btn-icon btn-outline-primary scholarship-filter-toggle ms-auto" data-cert-filters-toggle aria-expanded="true" aria-controls="certFilters" aria-label="Collapse certificate filters"><i class="ti ti-chevron-up" aria-hidden="true"></i></button>
    </div>
    <div class="card-body" id="certFilters">
        <form method="GET" action="{{ route('western-scholarship.index') }}" data-cert-filters>
            <input type="hidden" name="tab" value="certificates">
            <input type="hidden" name="per_page" id="certPageSize" value="{{ $pageSize }}">
            <div class="scholarship-result-filters scholarship-certificate-filters">
                <div class="scholarship-field"><label for="certYear">Academic Year</label><select class="form-select" id="certYear" name="academic_year_id" data-cert-picker data-placeholder="Select Academic Year" @disabled($years->isEmpty())>@foreach($years as $y)<option value="{{ $y->id }}" @selected($year?->id===$y->id)>{{ $y->academic_year }}</option>@endforeach</select></div>
                <div class="scholarship-field"><label for="certCampus">Campus</label><select class="form-select" id="certCampus" name="campus" data-cert-picker data-placeholder="All Campuses" @disabled(!$year)><option value="">All Campuses</option>@foreach($campuses as $c)<option value="{{ $c->id }}" @selected(($input['campus']??'')==$c->id)>{{ $c->campus_name_en }}</option>@endforeach</select></div>
                <div class="scholarship-field"><label for="certGrade">Grade</label><select class="form-select" id="certGrade" name="grade" data-cert-picker data-placeholder="All Grades" @disabled(!$year)><option value="">All Grades</option>@foreach(range(1,12) as $g)<option value="{{ $g }}" @selected(($input['grade']??'')==$g)>{{ $g }}</option>@endforeach</select></div>
                <div class="scholarship-field"><label for="certTypeFilter">Certificate</label><select class="form-select" id="certTypeFilter" name="type" data-cert-picker data-placeholder="All Types" @disabled(!$year)><option value="">All Types</option>@foreach(\App\Support\ScholarshipCertificateLayout::TYPES as $key=>$label)<option value="{{ $key }}" @selected(($input['type']??'')===$key)>{{ $label }}</option>@endforeach</select></div>
                <div class="scholarship-field"><label for="certSearch">Student Name English</label><select class="form-select" id="certSearch" name="q" data-cert-picker data-placeholder="All Students" @disabled(!$year || $studentOptions->isEmpty())><option value="">All Students</option>@if(filled($input['q']??null)&&!$studentOptions->contains($input['q']))<option value="{{ $input['q'] }}" selected>{{ $input['q'] }}</option>@endif @foreach($studentOptions as $name)<option value="{{ $name }}" @selected(($input['q']??'')===$name)>{{ $name }}</option>@endforeach</select></div>
            </div>
            <noscript><button class="btn btn-primary mt-3">Apply Filters</button></noscript>
        </form>
    </div>
</section>
