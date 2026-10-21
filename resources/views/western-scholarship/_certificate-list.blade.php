<div class="card-body">
    <div class="table-responsive"><table class="table table-vcenter cert-list"><thead><tr><th>No.</th><th>Student</th><th>Campus / Grade</th><th>Certificate</th><th>Certificate No.</th><th>Status</th>@if($permissions['certificate-cancel'])<th>Action</th>@endif</tr></thead><tbody>
        @forelse($certificateRows as $row)
        @php
            $issued=$row['certificate'];
        @endphp
        <tr data-cert-row><td>{{ $certificateRows->firstItem()+$loop->index }}</td><td><strong>{{ $row['student_name'] }}</strong></td><td>{{ $row['campus'] }}<div class="text-secondary small">Grade {{ $row['class'] }}</div></td><td>{{ \App\Support\ScholarshipCertificateLayout::TYPES[$row['type']] }}<div class="small text-secondary">{{ $row['type']==='subject'?'Best in '.$row['subject']:$row['place'] }}</div></td><td>{{ $issued?->certificate_number??'Not assigned' }}</td><td><span class="badge {{ !$issued?'bg-yellow-lt':($issued->status==='cancelled'||$row['stale']?'bg-red-lt':'bg-green-lt') }}">{{ !$issued?'Pending':($issued->status==='cancelled'?'Cancelled':($row['stale']?'Source changed':'Issued')) }}</span>@if($issued?->status==='cancelled')<div class="small text-secondary">{{ $issued->cancel_reason }}</div>@endif</td>
            @if($permissions['certificate-cancel'])<td>@if($issued?->status==='active')<button type="button" class="btn btn-sm btn-outline-danger" data-cert-cancel="{{ route('western-scholarship.certificates.cancel',$issued) }}">Cancel</button>@endif</td>@endif
        </tr>@empty<tr><td colspan="{{ $permissions['certificate-cancel']?7:6 }}" class="text-center text-secondary py-4">No eligible students match this list.</td></tr>@endforelse
    </tbody></table></div>
    <p class="small text-secondary mb-0">One certificate per student and academic year. Existing numbers remain unchanged on reprints. Cancelled numbers are retained.</p>
</div>
<div class="card-footer"><div id="certPagination" aria-label="Certificate list pagination"></div></div>
<script type="application/json" data-cert-list-data>{!! json_encode($pageData,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
