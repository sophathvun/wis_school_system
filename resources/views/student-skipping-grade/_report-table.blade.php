<div class="skipping-report-table-wrap @unless($printReport) table-responsive @endunless">
<table class="table table-vcenter skipping-report-table">
    <colgroup><col class="report-number"><col class="report-photo"><col class="report-student"><col class="report-grade"><col class="report-grade"><col class="report-parent"><col class="report-status"></colgroup>
    <thead><tr><th class="report-number">No.</th><th class="report-photo">Student Photo</th><th class="report-student">Student Name</th><th class="report-grade">Current Grade</th><th class="report-grade">Skipped To</th><th class="report-parent">Requested By</th><th class="report-status">Status</th></tr></thead>
    <tbody>
    @forelse($records as $record)
        @php($s=$record->student_snapshot??[])
        @php($row=$reportRows[$record->id])
        <tr>
            <td class="report-number-cell">{{ $loop->iteration }}</td>
            <td class="report-center"><div class="skipping-report-photo">@if($photoUrls[$record->id]??null)<img src="{{ $photoUrls[$record->id] }}" alt="Student photo: {{ $s['name_en']??$s['student_id']??'' }}" width="54" height="72">@else<span>No photo</span>@endif</div></td>
            <td><div class="report-student-id">Stu. ID: {{ $s['student_id']??'—' }}</div><div class="skipping-kh">{{ $s['name_kh']??'—' }}</div><div>{{ $s['name_en']??'—' }}</div>@unless($printReport)<div class="report-secondary">{{ ucfirst($s['gender']??'—') }}</div>@endunless<div class="report-secondary">DOB: {{ $row['dob'] }}</div></td>
            <td class="report-center"><div>{{ $s['academic_year']??'—' }}</div><div class="report-grade-name">{{ $s['campus_en']??'—' }}-{{ $row['source_grade'] }}</div></td>
            <td class="report-center"><div>{{ $s['target_academic_year']??$s['academic_year']??'—' }}</div><div class="report-grade-name">{{ $s['campus_en']??'—' }}-{{ $row['target_grade'] }}</div></td>
            <td class="report-center"><div>{{ $record->parent_name?:'—' }}</div><div class="report-secondary">{{ $record->parent_phone?:'—' }}</div><div class="report-secondary">{{ $record->application_date?->format('d-M-Y')??'—' }}</div></td>
            <td class="report-center"><div><span class="skipping-report-status-badge skipping-report-status-{{ $record->status }}" aria-label="Status: {{ $reportStatusLabels[$record->status]??ucfirst($record->status) }}">{{ $reportStatusLabels[$record->status]??ucfirst($record->status) }}</span></div>@if($record->status === 'approved')<div class="report-secondary">{{ $record->approval_date?->format('d-M-Y')??'—' }}</div>@endif<div class="report-secondary">No.: {{ $record->reference_number?:'—' }}</div></td>
        </tr>
    @empty
        <tr><td colspan="7" class="text-center py-5">No grade skipping requests match these filters.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
