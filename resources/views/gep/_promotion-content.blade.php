<div class="gep-promotion" data-success="{{ session('promotion_success') }}">
    <header class="promotion-hero"><div><span class="promotion-eyebrow">GENERAL ENGLISH PROGRAM · STUDENT JOURNEY</span><h1>Student promotion</h1><p>A confident next step, from one term to the next.</p></div><span class="promotion-hero-icon"><i class="ti ti-trending-up"></i></span></header>
    <div class="promotion-steps"><span><b>1</b> Find students</span><i class="ti ti-chevron-right"></i><span><b>2</b> Choose destination</span><i class="ti ti-chevron-right"></i><span><b>3</b> Review & promote</span></div>
    @if($errors->any())<div class="alert alert-danger" role="alert"><strong>Promotion could not be saved.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if(session('promotion_success'))<div class="alert alert-success" role="status">{{ session('promotion_success') }}</div>@endif
    <section class="promotion-panel">
        <div class="promotion-heading"><div><h2><span>01</span> Find your students</h2><p>Select a source term to see active and completed enrollments.</p></div><span class="promotion-chip"><span data-match-count>{{ number_format($records->total()) }}</span> eligible enrollments</span></div>
        <form method="GET" data-source-filters>
            <div class="promotion-grid">
                <div class="scholarship-field"><label for="sourceTerm">Source year / term</label><select id="sourceTerm" name="source_term" class="form-select" data-placeholder="Choose source term"><option value="">Choose source term</option>@foreach($terms as $term)<option value="{{ $term->id }}" @selected(($filters['source_term']??'')==$term->id)>{{ $term->academicYear->academic_year }} · {{ $term->term->term_name }}</option>@endforeach</select></div>
                @foreach([['campus_id','Campus',$sourceOptions['campus_id'],'campus_name_en'],['level_id','Level',$sourceOptions['level_id'],'level_name'],['class_id','Class',$sourceOptions['class_id'],'class_name'],['group_id','Group',$sourceOptions['group_id'],'session_name']] as [$key,$label,$items,$name])
                    <div class="scholarship-field"><label for="filter-{{ $key }}">{{ $label }}</label><select id="filter-{{ $key }}" class="form-select" name="{{ $key }}" data-placeholder="All {{ strtolower($label) }}"><option value="">All {{ strtolower($label) }}</option>@foreach($items as $item)<option value="{{ $item->id }}" @selected(($filters[$key]??'')==$item->id)>{{ $item->$name }}</option>@endforeach</select></div>
                @endforeach
            </div>
            <div class="promotion-search"><div class="promotion-autocomplete"><i class="ti ti-search"></i><input class="form-control" type="search" name="search" maxlength="100" value="{{ $filters['search']??'' }}" placeholder="Search student name or ID…" aria-label="Search student name or ID" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="promotionSearchResults" autocomplete="off"><span class="promotion-search-spinner" hidden aria-hidden="true"></span><div id="promotionSearchResults" class="promotion-search-results" role="listbox" aria-label="Matching students" hidden></div></div><a class="btn btn-outline-secondary" href="{{ route('gep.students.section',['studentSection'=>'promotion']) }}">Reset</a></div>
            <div class="promotion-search-status" role="status" aria-live="polite" data-search-status></div>
        </form>
    </section>
    <form method="POST" action="{{ route('gep.promotions.store') }}" data-promotion-form>
        @csrf<input type="hidden" name="source_term" value="{{ $filters['source_term']??'' }}">
        <section class="promotion-panel">
            <div class="promotion-heading"><div><h2><span>02</span> Destination enrollment</h2><p>Apply these settings to the students you select below.</p></div><i class="ti ti-arrow-up-right text-primary fs-3"></i></div>
            <div class="promotion-grid">
                <div class="scholarship-field"><label for="destinationTerm">Destination year / term *</label><select id="destinationTerm" name="term_assignment_id" class="form-select" required data-placeholder="Choose destination"><option value="">Choose destination</option>@foreach($targetTerms as $term)<option value="{{ $term->id }}" data-state="{{ $term->lifecycle_status }}" @selected(old('term_assignment_id')==$term->id)>{{ $term->academicYear->academic_year }} · {{ $term->term->term_name }} · {{ ucfirst($term->lifecycle_status) }}</option>@endforeach</select>@if($targetTerms->isEmpty())<small class="text-secondary">Choose a source term first. A later Started or Pending term must be available in GEP Settings.</small>@endif</div>
                @foreach([['campus_id','Campus',$targetCampuses,'campus_name_en'],['level_id','Level',$levels,'level_name'],['class_id','Class',$classes,'class_name'],['class_type_id','Class type',$types,'type_name'],['group_id','Group',$groups,'session_name']] as [$key,$label,$items,$name])
                    <div class="scholarship-field"><label for="target-{{ $key }}">{{ $label }} *</label><select id="target-{{ $key }}" class="form-select" name="{{ $key }}" required data-placeholder="Choose {{ strtolower($label) }}"><option value="">Choose {{ strtolower($label) }}</option>@foreach($items as $item)<option value="{{ $item->id }}" @selected(old($key)==$item->id)>{{ $item->$name }}</option>@endforeach</select></div>
                @endforeach
                <div class="scholarship-field"><label for="promotionDate">Enrollment date *</label><input type="date" class="form-control" id="promotionDate" name="enrolled_on" required value="{{ old('enrolled_on',now('Asia/Phnom_Penh')->toDateString()) }}"></div>
                <div class="scholarship-field promotion-note"><label for="promotionNotes">Promotion notes</label><input class="form-control" id="promotionNotes" name="notes" maxlength="2000" value="{{ old('notes') }}" placeholder="Optional note for this promotion"></div>
            </div>
            <div class="promotion-info"><i class="ti ti-info-circle"></i><span data-destination-note>Started term: new enrollment is Active and the source becomes Completed. Upcoming term: new enrollment is Pending and the source status stays as saved.</span></div>
        </section>
        <section class="promotion-panel promotion-students">
            <div class="promotion-heading"><div><h2><span>03</span> Select students</h2><p>Select up to 50 students per promotion. Selection applies to this page.</p></div><span class="promotion-chip"><strong data-selection-count>0</strong> selected</span></div>
            <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th><input type="checkbox" class="form-check-input" data-select-all aria-label="Select all students on this page" @disabled(!$canPromote || $records->isEmpty())></th><th>Student</th><th>Campus</th><th>Current level / class</th><th>Group</th><th>Status</th></tr></thead><tbody>
            @include('gep._promotion-rows')
            </tbody></table></div>
            <div class="p-3" data-promotion-pagination>{{ $records->links() }}</div>
            <footer class="promotion-action"><div><strong><span data-selection-count>0</span> students ready for review</strong><small>Student IDs and family information stay linked.</small></div><button class="btn btn-primary" type="submit" data-review @disabled(!$canPromote)><i class="ti ti-checklist me-2"></i>Review promotion</button></footer>
            <p class="text-secondary small px-4">Promotion is limited to students in your permitted campus and keeps them in the same campus. The GEP Stu. Promotion → Promote permission is required.</p>
            @unless($canPromote)<p class="text-secondary small px-4">You have view access only. Ask your administrator to enable Promote for your campus.</p>@endunless
        </section>
    </form>
</div>
