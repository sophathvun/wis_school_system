@if(!$hasDataFilter)
    <div class="text-center text-muted py-4">Select an Academic Year and Campus to load student photos.</div>
@elseif($photoStudents->isEmpty())
    <div class="text-center text-muted py-4">No students found.</div>
@else
    @if($photoMissingCount)<div class="alert alert-info">{{ number_format($photoMissingCount) }} student(s) have no available photo and will be omitted from printing.</div>@endif
    <div class="student-photo-preview">
        @foreach($photoStudents->groupBy(fn ($row) => $row->campus_id.':'.$row->grade_id.':'.$row->class_id) as $students)
            <section class="student-photo-preview-group">
                <h4>{{ $students->first()->campus_name_en }} · {{ $students->first()->grade_label }}</h4>
                <div class="student-photo-preview-grid">
                    @foreach($students as $student)
                        <figure class="student-photo-preview-item">
                            @if($student->photo_url)<img src="{{ $student->photo_url }}" alt="{{ $student->full_name_en }}" loading="lazy">@else<div class="student-photo-missing">No photo</div>@endif
                            <figcaption>{{ $student->full_name_en ?: $student->full_name_kh }}<small>{{ $student->student_code }}</small></figcaption>
                        </figure>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
    @if($photoPagination ?? null)
        @include('reports._preview-pagination', ['pagination' => $photoPagination])
    @endif
@endif
