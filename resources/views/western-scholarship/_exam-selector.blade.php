<section class="card mb-3">
    <div class="card-body">
        <div class="d-flex flex-wrap gap-3 align-items-center">
            <form method="GET" class="scholarship-exam-select" data-exam-selector>
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="scholarship-field"><label for="scholarshipExam">Scholarship Academic Year</label><select id="scholarshipExam" class="form-select" name="exam_id" data-scholarship-select><option value="">Choose academic year</option>@foreach($exams ?? [] as $session)<option value="{{ $session->id }}" @selected(($exam->id ?? null)===$session->id)>{{ $session->academicYear?->academic_year }}</option>@endforeach</select></div>
            </form>
            @if(\App\Support\ScholarshipPermissions::allows(auth()->user(),'settings'))<a href="{{ route('western-scholarship.index',['tab'=>'settings','new_exam'=>1]) }}" class="btn btn-outline-primary"><i class="ti ti-plus me-1"></i>Set Up Academic Year</a>@endif
        </div>
        @if($exam ?? null)
            <div class="d-flex flex-wrap gap-2 mt-3 align-items-center"><span class="badge bg-primary-lt">{{ $exam->academicYear?->academic_year }}</span><span class="badge bg-secondary-lt">{{ $exam->exam_date->format('d-M-Y') }}</span><span class="badge {{ $exam->published_at ? 'bg-green-lt' : ($exam->seats_locked_at ? 'bg-orange-lt':'bg-blue-lt') }}">{{ $exam->published_at ? 'Results Published' : ($exam->seats_locked_at ? 'Seats Confirmed':'Draft Session') }}</span><span class="text-secondary small">{{ number_format($candidateCount) }} candidate(s) · {{ $exam->room_capacity }} seats per room</span></div>
        @else<p class="small text-secondary mb-0 mt-3">Only regular academic years participate. Assign class ranks before or after setting up the year; all ranked students automatically become candidates.</p>@endif
    </div>
</section>
