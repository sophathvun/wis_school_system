<div class="staff-detail-grid mb-3">
    @foreach(['joined_on'=>'Hire Date','position'=>'Position','department'=>'Department','primary_campus'=>'Primary Campus','supervisor'=>'Reports To / Supervisor','employment_status'=>'Employment Status','employment_type'=>'Employment Type','staff_category'=>'Staff Category','ended_on'=>'Leaving Date','employment_end_reason'=>'Leaving Reason'] as $key=>$label)
        @php($display=$snapshot[$key]??null)
        @php($display=match($key){'employment_status'=>$statusLabels[$display]??$display,'employment_type'=>$typeLabels[$display]??$display,'staff_category'=>$categoryLabels[$display]??$display,default=>$display})
        @php($display=$display && in_array($key,['joined_on','ended_on'])?\Carbon\Carbon::parse($display)->format('d-M-Y'):$display)
        <div class="staff-detail-item"><div class="staff-detail-label">{{ $label }}</div><div class="staff-detail-value">{{ $display?:'—' }}</div></div>
    @endforeach
    <div class="staff-detail-item"><div class="staff-detail-label">Working Campuses</div><div>{{ collect($snapshot['campuses']??[])->pluck('name')->join(', ')?:'—' }}</div></div>
    <div class="staff-detail-item"><div class="staff-detail-label">Profile Status</div><div>{{ ($snapshot['status']??true)?'Active':'Inactive' }}</div></div>
</div>
<h4 class="staff-subsection-title"><i class="ti ti-file-description me-2" aria-hidden="true"></i>Contract & Probation</h4>
<div class="staff-detail-grid mb-3">
    @foreach(['contract_type'=>'Contract Type','contract_number'=>'Contract Number','contract_started_on'=>'Contract Start Date','contract_ends_on'=>'Contract End Date','probation_ends_on'=>'Probation End Date'] as $key=>$label)
        @php($display=$snapshot[$key]??null)
        @php($display=$key==='contract_type'?($contractLabels[$display]??$display):($display && str_ends_with($key,'_on')?\Carbon\Carbon::parse($display)->format('d-M-Y'):$display))
        <div class="staff-detail-item"><div class="staff-detail-label">{{ $label }}</div><div class="staff-detail-value">{{ $display?:'—' }}</div></div>
    @endforeach
</div>
<h4 class="staff-subsection-title"><i class="ti ti-calendar-time me-2" aria-hidden="true"></i>Working Schedule</h4>
<div class="staff-detail-grid mb-3">@foreach(['session_1_in'=>'Session 1 — Time In','session_1_out'=>'Session 1 — Time Out','session_2_in'=>'Session 2 — Time In','session_2_out'=>'Session 2 — Time Out'] as $key=>$label)<div class="staff-detail-item"><div class="staff-detail-label">{{ $label }}</div><div>{{ $snapshot[$key]??'—' }}</div></div>@endforeach</div>
<div class="table-responsive staff-snapshot-schedule"><table class="table table-sm mb-0"><thead><tr><th>Working Day</th><th>Period</th><th>Session 1</th><th>Session 2</th></tr></thead><tbody>
    @forelse($snapshot['work_schedule']??[] as $day=>$times)<tr><td>{{ $days[$day]??$day }}</td><td>{{ $periods[$times['period']]??$times['period'] }}</td><td>{{ $times['session_1_in']??'—' }} – {{ $times['session_1_out']??'—' }}</td><td>{{ $times['session_2_in']??'—' }} – {{ $times['session_2_out']??'—' }}</td></tr>
    @empty<tr><td colspan="4" class="text-secondary">Working days have not been configured.</td></tr>@endforelse
</tbody></table></div>
