@if($canReset??false)
<button type="button" class="btn btn-sm {{ $resetClass??'btn-outline-danger' }}" data-exam-reseating-reset
    data-preview-url="{{ route('western-scholarship.seats.reset-preview',$exam) }}" data-reset-url="{{ route('western-scholarship.seats.reset',$exam) }}"
    @disabled($exam->published_at||$exam->isHistorical())
    title="{{ $exam->published_at||$exam->isHistorical()?'Published or historical exams cannot be reset.':'Clear all test work for this exam and reopen rank entry and seating.' }}">
    <i class="ti ti-rotate-clockwise-2 me-1" aria-hidden="true"></i>Reset Exam for Reseating
</button>
@endif
