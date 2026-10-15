@php
    $seatCandidates=$candidates->keyBy('id');
@endphp
<section data-scholarship-seat-list data-page-size="{{ $seatPageSize }}" aria-busy="false">
<div class="table-responsive scholarship-seat-overflow">
    <table class="table table-vcenter scholarship-seat-table">
        <thead><tr><th class="text-center">No.</th><th class="text-center">Photo</th><th>Student</th><th>Grade</th><th>Exam Code</th><th>Exam Center</th><th class="text-center">Room No.</th><th class="text-center">Seat No.</th></tr></thead>
        <tbody>
        @forelse($seatRows as $row)
            @php
                $candidate=$seatCandidates->get($row['id']??null);
                $enrollment=$candidate?->enrollment;
                $classParts=explode(' / ',(string)($row['snapshot']['class']??''));
                $grade=trim((string)($enrollment?->grade?->grade_short_name ?: $enrollment?->grade?->grade ?: $row['grade_number']));
                $grade=preg_replace('/^(?:grade\s*|g)(?=\d)/i','',$grade);
                $class=trim((string)($enrollment?->schoolClass?->class_name ?? $classParts[0]));
                $gradeClass=$grade!=='' && str_starts_with(strtolower($class),strtolower($grade))?$class:$grade.$class;
                $group=$enrollment?->session?->session_short_name ?: $enrollment?->schoolGroup?->group_name ?: ($classParts[1]??null);
                $placement=collect([$row['snapshot']['campus']??null,$gradeClass,$group])->filter(fn($part)=>filled($part))->implode('-');
                $photo=$candidate?->student?->photo_path;
                $name=$row['snapshot']['name_en'] ?: $row['snapshot']['name_kh'];
            @endphp
            <tr>
                <td class="text-center scholarship-seat-sequence" data-label="No.">{{ $seatRows->firstItem()+$loop->index }}</td>
                <td class="scholarship-seat-photo" data-label="Photo">
                    @if($photo)
                        <button type="button" class="scholarship-rank-photo scholarship-photo-trigger mx-auto" data-scholarship-photo="{{ asset('storage/'.$photo) }}" data-photo-name="{{ $name }}" aria-label="View photo of {{ $name }}">
                            <img src="{{ asset('storage/'.$photo) }}" alt="Photo of {{ $name }}" width="600" height="800" loading="lazy">
                        </button>
                    @else
                        <div class="scholarship-rank-photo mx-auto"><i class="ti ti-user" aria-hidden="true"></i><span class="visually-hidden">No student photo</span></div>
                    @endif
                </td>
                <td class="scholarship-seat-student" data-label="Student"><strong>{{ $row['snapshot']['student_code'] }}</strong><div class="scholarship-khmer">{{ $row['snapshot']['name_kh'] }}</div><div>{{ $row['snapshot']['name_en'] }}</div></td>
                <td class="scholarship-seat-grade" data-label="Grade"><div><div>{{ $exam->academicYear?->academic_year ?? '—' }}</div><div class="text-secondary">{{ $placement ?: '—' }}</div></div></td>
                <td class="text-nowrap" data-label="Exam Code">{{ $row['candidate_code'] ?? '—' }}</td>
                <td data-label="Exam Center">{{ $row['exam_center_name'] ?? '—' }}</td>
                <td class="text-center" data-label="Room No.">{{ $row['room_number'] ?? '—' }}</td>
                <td class="text-center" data-label="Seat No.">{{ $row['room_seat'] ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center text-secondary py-4">No seat assignments available.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="card-footer px-0 pb-0">
    <input type="hidden" id="scholarshipSeatPageSize" value="{{ $seatPageSize }}">
    <div id="scholarshipSeatsPagination" data-seat-pagination="{{ json_encode(['current_page'=>$seatRows->currentPage(),'last_page'=>$seatRows->lastPage(),'from'=>$seatRows->firstItem(),'to'=>$seatRows->lastItem(),'total'=>$seatRows->total()]) }}">{{ $seatRows->links() }}</div>
</div>
</section>
