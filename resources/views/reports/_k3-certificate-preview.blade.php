@if($certificates->isEmpty())
    <div class="text-center text-muted py-4">{{ $hasDataFilter ? 'No eligible K3 students found.' : 'Select an Academic Year to load K3 students.' }}</div>
@else
    @include('reports._k3-certificate-editor')
    <div class="k3-screen-preview" data-k3-certificate-preview>
        @include('reports._k3-certificate-page', ['certificate'=>$certificates->first(), 'showFrame'=>true])
    </div>
    <table class="table table-vcenter k3-certificate-list">
        <thead><tr><th>No.</th><th>Certificate Number</th><th>Campus</th><th>Grade</th><th>Student Name English</th><th>Student ID</th></tr></thead>
        <tbody>@foreach(($certificatePreviewRows ?? $certificates) as $certificate)<tr><td>{{ ($certificatePagination['from'] ?? 1) + $loop->index }}</td><td>{{ $certificate->certificate_number ?: 'Not assigned' }}</td><td>{{ $certificate->campus_name_en }}</td><td>K3-{{ ltrim($certificate->class_name, '-') }}</td><td>{{ $certificate->full_name_en }}</td><td>{{ $certificate->student_code }}</td></tr>@endforeach</tbody>
    </table>
    @if($certificatePagination ?? null)
        @include('reports._preview-pagination', ['pagination' => $certificatePagination])
    @endif
@endif
