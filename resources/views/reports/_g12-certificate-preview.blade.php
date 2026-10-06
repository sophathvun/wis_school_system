@if($certificates->isEmpty())
    <div class="text-center text-muted py-4">{{ $hasDataFilter ? 'No eligible G12 students found.' : 'Select an Academic Year to load G12 students.' }}</div>
@else
    @include('reports._g12-certificate-editor')
    <div class="g12-screen-preview" data-g12-certificate-preview>
        @include('reports._g12-certificate-page', ['certificate'=>$certificates->first(), 'showFrame'=>true])
    </div>
    <table class="table table-vcenter g12-certificate-list">
        <thead><tr><th>No.</th><th>Certificate Number</th><th>Campus</th><th>Grade</th><th>Student Name English</th><th>Student ID</th></tr></thead>
        <tbody>@foreach(($certificatePreviewRows ?? $certificates) as $certificate)<tr><td>{{ ($certificatePagination['from'] ?? 1) + $loop->index }}</td><td>{{ $certificate->certificate_number ?: 'Not assigned' }}</td><td>{{ $certificate->campus_name_en }}</td><td>12{{ ltrim($certificate->class_name, '-') }}</td><td>{{ $certificate->full_name_en }}</td><td>{{ $certificate->student_code }}</td></tr>@endforeach</tbody>
    </table>
    @if($certificatePagination ?? null)
        @include('reports._preview-pagination', ['pagination' => $certificatePagination])
    @endif
@endif
