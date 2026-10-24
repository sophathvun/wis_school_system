<div data-answer-scan-history-list data-pagination="{{ json_encode(['current_page'=>$scans->currentPage(),'last_page'=>$scans->lastPage(),'from'=>$scans->firstItem(),'to'=>$scans->lastItem(),'total'=>$scans->total(),'per_page'=>$scans->perPage()]) }}" aria-busy="false">
    <div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>No.</th><th>Student</th><th>Paper / Key</th><th>Status</th><th>Flagged</th><th>Actions</th></tr></thead><tbody>
        @forelse($scans as $scan)
            <tr data-answer-scan-row="{{ $scan->id }}">
                <td>{{ $scans->firstItem()+$loop->index }}</td>
                <td>{{ $scan->sheet->candidate->snapshot['name_en']??'' }}<div class="small text-secondary">{{ $scan->sheet->candidate->candidate_code }} · Room {{ $scan->sheet->candidate->room_number }} · Seat {{ $scan->sheet->candidate->room_seat }}</div></td>
                <td>{{ $scan->sheet->paper->subject->name }} · {{ $scan->sheet->paper->paper_version }}<div class="small">Key revision {{ $scan->key->revision }}</div></td>
                <td>
                    <span class="badge {{ $scan->status==='accepted'?'bg-green-lt':'bg-yellow-lt' }}">{{ ucfirst($scan->status) }}</span>
                    @if($scan->subjectAbsent())
                        <div class="small fw-semibold text-warning">{{ $scan->detectedAbsent()?'Auto Absent':'Absent' }} · 0</div>
                    @elseif($scan->absenceNeedsReview()&&$scan->status==='pending')
                        <div class="small text-danger">Attendance needs review</div>
                    @endif
                    @if($permissions['answer-verify']&&$scan->status==='accepted')
                        <div>{{ $scan->score }} / {{ $scan->sheet->snapshot['max_score'] }}</div>
                    @endif
                </td>
                <td>{{ $scan->flaggedCount() }}</td>
                <td><div class="d-flex flex-wrap gap-2">@if($permissions['answer-marked-view'])<button class="btn btn-sm btn-outline-success" type="button" data-answer-marked="{{ $scan->id }}"><i class="ti ti-eye-check me-1"></i>View Marked Sheet</button>@endif @if($permissions['answer-verify'])<button class="btn btn-sm btn-outline-primary" type="button" data-answer-review="{{ $scan->id }}"><i class="ti ti-checklist me-1"></i>Review</button>@elseif(!$permissions['answer-marked-view'])<span class="text-secondary">Awaiting Central verification</span>@endif</div></td>
            </tr>
        @empty<tr><td colspan="6" class="text-center text-secondary py-4">No scans for these filters.</td></tr>@endforelse
    </tbody></table></div>
    <div class="card-footer"><div id="answerScansPagination" aria-label="Scan History pagination"></div><noscript>{{ $scans->links() }}</noscript></div>
</div>
