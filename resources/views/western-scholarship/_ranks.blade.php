<section class="card mt-3" data-scholarship-ranks-panel aria-busy="false">
    <div class="card-header scholarship-card-header scholarship-ranks-header">
        <h3 class="card-title"><i class="ti ti-list-numbers me-2" aria-hidden="true"></i>Assigned Student Ranks</h3>
        <div class="scholarship-rank-summary" aria-label="Assigned rank totals">
            <span>Total: <strong data-rank-count="total">{{ number_format($rankSummary['total']) }}</strong></span><span>1st Place: <strong data-rank-count="1">{{ number_format($rankSummary[1]) }}</strong></span><span>2nd Place: <strong data-rank-count="2">{{ number_format($rankSummary[2]) }}</strong></span><span>3rd Place: <strong data-rank-count="3">{{ number_format($rankSummary[3]) }}</strong></span>
        </div>
        @if($canExportRanks || $canManageRankPrintSettings)
            <div class="scholarship-rank-exports">
                @if($canExportRanks)
                @foreach(['print'=>['Print','ti-printer'],'xlsx'=>['Excel','ti-file-spreadsheet'],'pdf'=>['PDF','ti-file-type-pdf']] as $format=>[$label,$icon])
                    <a class="btn btn-sm btn-outline-light" href="{{ route('western-scholarship.ranks.report',['format'=>$format]+array_filter(\Illuminate\Support\Arr::only($filters,['academic_year_id','campus_id','grade_class','grade_id','class_id','enrollment_id']))) }}" @if($format==='print') target="_blank" rel="noopener" @endif><i class="ti {{ $icon }} me-1" aria-hidden="true"></i>{{ $label }}</a>
                @endforeach
                @endif
                @if($canManageRankPrintSettings)<button type="button" class="btn btn-sm btn-outline-light btn-icon" data-rank-print-settings aria-label="Print Settings" title="Print Settings"><i class="ti ti-settings" aria-hidden="true"></i></button>@endif
            </div>
        @endif
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter scholarship-rank-table">
            <thead><tr><th class="text-center">No.</th><th class="text-center">Photo</th><th class="text-start">Student Info</th><th class="text-start">Grade</th><th class="text-center">Class Rank</th><th class="text-start">Assigned By</th><th class="text-start">Actions</th></tr></thead>
            <tbody>
                @forelse($records as $record)
                    @php
                        $enrollment = $record->enrollment;
                        $student = $enrollment->student;
                        $grade = trim((string)($enrollment->grade?->grade_short_name ?: $enrollment->grade?->grade));
                        $grade = preg_replace('/^(?:grade\s*|g)(?=\d)/i', '', $grade);
                        $class = trim((string)($enrollment->schoolClass?->class_name ?? ''));
                        $gradeClass = $grade !== '' && str_starts_with(strtolower($class), strtolower($grade)) ? $class : $grade.$class;
                        $group = $enrollment->session?->session_short_name ?: $enrollment->schoolGroup?->group_name;
                        $placement = collect([$enrollment->campus?->campus_name_en, $gradeClass, $group])->filter(fn($part)=>filled($part))->implode('-');
                    @endphp
                    <tr>
                        <td class="scholarship-rank-sequence text-center justify-content-center" data-label="No.">{{ $records->firstItem() + $loop->index }}</td>
                        <td class="scholarship-rank-photo-cell" data-label="Photo">
                            @if($student?->photo_path)
                                <button type="button" class="scholarship-rank-photo scholarship-photo-trigger mx-auto" data-scholarship-photo="{{ asset('storage/'.$student->photo_path) }}" data-photo-name="{{ $student->full_name_en ?: $student->full_name_kh }}" aria-label="View photo of {{ $student->full_name_en ?: $student->full_name_kh }}">
                                    <img src="{{ asset('storage/'.$student->photo_path) }}" alt="Photo of {{ $student->full_name_en ?: $student->full_name_kh }}" width="600" height="800" loading="lazy">
                                </button>
                            @else
                                <div class="scholarship-rank-photo mx-auto">
                                    <i class="ti ti-user" aria-hidden="true"></i><span class="visually-hidden">No student photo</span>
                                </div>
                            @endif
                        </td>
                        <td class="scholarship-rank-student" data-label="Student Info">
                            <strong>{{ $student?->student_id ?: $student?->student_no }}</strong>
                            <div class="scholarship-khmer">{{ $student?->full_name_kh }}</div>
                            <div>{{ $student?->full_name_en }}</div>
                        </td>
                        <td data-label="Grade">
                            <div>
                                <div>{{ $enrollment->academicYear?->academic_year }}</div>
                                <div class="text-secondary">{{ $placement ?: '—' }}</div>
                            </div>
                        </td>
                        <td data-label="Class Rank">
                            <div class="text-center">
                                <div><span class="badge bg-success-lt text-success fs-3 text-center">{{ $record->rank }}</span></div>
                                <div class="mt-1"><span class="text-secondary" title="End-of-Year Average Score">EOY Avg.:</span> {{ ($record->eoy_average??null)===null ? '—' : number_format($record->eoy_average,2) }}</div>
                            </div>
                        </td>
                        <td data-label="Assigned By">
                            <div><div>{{ $record->assignedBy?->name ?: '—' }}</div><div class="text-secondary small mt-1">{{ $record->created_at?->format('d-m-Y H:i') ?? '—' }}</div></div>
                        </td>
                        <td data-label="Actions">
                            @if(!$ranksLocked && \App\Support\ScholarshipPermissions::allows($rankUser,'assign-rank',(int)$enrollment->campus_id))
                                <div class="d-flex gap-2 flex-wrap">
                                    <a class="btn btn-outline-primary btn-sm" href="{{ route('western-scholarship.index', array_merge(array_filter($filters), ['campus_id'=>$enrollment->campus_id,'grade_id'=>$enrollment->grade_id,'class_id'=>$enrollment->class_id,'grade_class'=>$enrollment->grade_id.':'.$enrollment->class_id,'enrollment_id'=>$record->student_enrollment_id])) }}"><i class="ti ti-edit me-1" aria-hidden="true"></i>Edit Rank</a>
                                    <form method="POST" action="{{ route('western-scholarship.ranks.remove',$record->id) }}" data-scholarship-confirm="Remove this class rank and withdraw the student from this scholarship exam?">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-danger btn-sm btn-icon scholarship-rank-delete" aria-label="Delete rank for {{ $student?->full_name_en ?: $student?->student_id }}" title="Delete rank"><i class="ti ti-trash" aria-hidden="true"></i></button>
                                    </form>
                                </div>
                            @else
                                <span class="text-secondary">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-secondary py-5">{{ empty($filters['academic_year_id']) ? 'Select an academic year to display assigned student ranks.' : 'No student ranks assigned for these filters.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        <div id="scholarshipRanksPagination" data-rank-pagination="{{ json_encode(['current_page'=>$records->currentPage(),'last_page'=>$records->lastPage(),'from'=>$records->firstItem(),'to'=>$records->lastItem(),'total'=>$records->total()]) }}">
            {{ $records->links() }}
        </div>
    </div>
</section>
