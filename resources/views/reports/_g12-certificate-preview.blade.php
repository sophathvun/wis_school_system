@if($certificates->isEmpty())
    <div class="text-center text-muted py-4">{{ $hasDataFilter ? 'No eligible G12 students found.' : 'Select an Academic Year to load G12 students.' }}</div>
@else
    @include('reports._g12-certificate-editor')
    <div class="g12-screen-preview" data-g12-certificate-preview>
        @include('reports._g12-certificate-page', ['certificate'=>$certificates->first(), 'showFrame'=>true])
    </div>
    <div class="table-responsive"><table class="table table-vcenter g12-certificate-list">
        <thead><tr><th>No.</th><th>Certificate Number</th><th>Campus</th><th>Grade</th><th>Student Name English</th><th>Student ID</th><th>Status</th><th>Printed</th><th>Released</th><th>Actions</th></tr></thead>
        <tbody>@foreach(($certificatePreviewRows ?? $certificates) as $certificate)
            <tr>
                <td>{{ ($certificatePagination['from'] ?? 1) + $loop->index }}</td><td>{{ $certificate->certificate_number ?: 'Not assigned' }}</td><td>{{ $certificate->campus_name_en }}</td><td>12{{ ltrim($certificate->class_name, '-') }}</td><td>{{ $certificate->full_name_en }}</td><td>{{ $certificate->student_code }}</td>
                <td><span class="badge bg-{{ $certificate->certificate_status === 'voided' ? 'danger' : ($certificate->certificate_status === 'valid' ? 'success' : 'warning') }}-lt" title="{{ $certificate->certificate_void_reason }}">{{ match($certificate->certificate_status) { 'voided'=>'Voided', 'valid'=>'Valid / Ready to Release', 'printed_pending'=>'Printed — Pending Release', 'pending_result'=>'Pending Exam Result', default=>'Not Assigned' } }}</span></td>
                <td>{{ $certificate->certificate_printed_at?->format('d-M-Y') ?: '—' }}</td>
                <td>{{ $certificate->certificate_released_at?->format('d-M-Y') ?: '—' }}</td>
                <td>
                    @if(($canTrackG12Certificates ?? false) && $certificate->certificate_id && $certificate->certificate_status !== 'voided')
                        @foreach(['mark_printed'=>'Mark Printed','release'=>'Record Student Collection'] as $trackingAction=>$trackingLabel)
                            @if(($trackingAction === 'mark_printed' && !$certificate->certificate_printed_at) || ($trackingAction === 'release' && $certificate->certificate_can_release && $certificate->certificate_printed_at && !$certificate->certificate_released_at))
                                <form class="d-inline" method="post" action="{{ route('reports.g12-certificates.save') }}">
                                    @csrf
                                    <input type="hidden" name="academic_year_id" value="{{ $filters['academic_year_id'] }}"><input type="hidden" name="certificate_id" value="{{ $certificate->certificate_id }}"><input type="hidden" name="action" value="{{ $trackingAction }}">
                                    <button type="submit" class="btn btn-sm btn-outline-primary">{{ $trackingLabel }}</button>
                                </form>
                            @endif
                        @endforeach
                    @endif
                </td>
            </tr>
        @endforeach</tbody>
    </table></div>
    @if($certificatePagination ?? null)
        @include('reports._preview-pagination', ['pagination' => $certificatePagination])
    @endif
@endif
