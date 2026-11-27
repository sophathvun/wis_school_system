<form action="{{ route('gep.dashboard') }}" method="get" class="gep-dash-filters gep-dash-panel" data-gep-dashboard-filters>
    <div class="scholarship-field"><label for="gep-dash-year">Academic Year</label><select id="gep-dash-year" name="academic_year_id" class="form-select" data-placeholder="All academic years"><option value="">All academic years</option>@foreach($years as $year)<option value="{{ $year->id }}" @selected((int)$yearId===$year->id)>{{ $year->academic_year }}</option>@endforeach</select></div>
    <div class="scholarship-field"><label for="gep-dash-term">Term</label><select id="gep-dash-term" name="term_assignment_id" class="form-select" data-placeholder="All terms"><option value="">All terms</option>@foreach($terms as $item)<option value="{{ $item->id }}" @selected((int)$termId===$item->id)>{{ $item->term->term_name }} · {{ ucfirst($item->lifecycle_status) }}</option>@endforeach</select></div>
    <div class="scholarship-field"><label for="gep-dash-campus">Campus</label><select id="gep-dash-campus" name="campus_id" class="form-select" data-placeholder="All permitted campuses"><option value="">All permitted campuses</option>@foreach($campuses as $campus)<option value="{{ $campus->id }}" @selected((int)$campusId===$campus->id)>{{ $campus->campus_name_en }}</option>@endforeach</select></div>
</form>
@php($mainStatus=$term?->lifecycle_status==='finished'?'completed':'active')
<div class="gep-dash-metrics">
    @foreach([
        ['students','Students','users','blue','Unique students in this selection'],
        [$mainStatus,ucfirst($mainStatus).' students',$mainStatus==='active'?'user-check':'certificate','green','Current enrollment status'],
        ['new','New students','user-plus','violet','Marked New in enrollment'],
        ['withdrawn','Withdrawn students','user-minus','rose','Withdrawn in this selection'],
    ] as [$key,$label,$icon,$color,$note])
    <section class="gep-dash-metric" style="--metric-color:var(--dash-{{ $color }})"><div class="gep-dash-metric-top"><span>{{ $label }}</span><i class="ti ti-{{ $icon }}"></i></div><strong>{{ number_format($metrics[$key]) }}</strong>
        @if(in_array($key,['students','new']))<div class="gep-dash-genders">M: <b>{{ number_format($metrics[$key==='new'?'new_male':'male']) }}</b><span>|</span> F: <b>{{ number_format($metrics[$key==='new'?'new_female':'female']) }}</b></div>@else<div class="gep-dash-metric-note">{{ $note }}</div>@endif
    </section>
    @endforeach
</div>
<div class="gep-dash-caption"><i class="ti ti-info-circle"></i> {{ number_format($metrics['enrollments']) }} enrollment records · Students are counted once in each card. Status counts may overlap across multiple terms.</div>
<div class="gep-dash-main-grid">
    <section class="gep-dash-panel"><div class="gep-dash-heading"><div><span class="gep-dash-kicker">LEARNING JOURNEY</span><h2>Students by term</h2></div><span class="gep-dash-tag">{{ $yearLabel }}</span></div><p class="gep-dash-subtitle">Unique students in each term, across all statuses. Campus filter applies.</p>
        @php($peak=max(1,$trend->max('total')??0))
        @if($trend->sum('total'))<div class="gep-dash-chart" role="img" aria-label="Students by term: {{ $trend->map(fn($r)=>$r['label'].': '.$r['total'])->join(', ') }}">@foreach($trend as $point)<div class="gep-dash-chart-column"><strong>{{ number_format($point['total']) }}</strong><div class="gep-dash-chart-track"><span @class(['is-selected'=>$point['selected']]) style="height:{{ $point['total']*100/$peak }}%"></span></div><span>{{ $point['label'] }}</span></div>@endforeach</div>@else<div class="gep-dash-empty"><i class="ti ti-chart-bar"></i>No enrollment data for this selection.</div>@endif
    </section>
    <section class="gep-dash-panel"><div class="gep-dash-heading"><div><span class="gep-dash-kicker">STUDENT COMMUNITY</span><h2>Gender overview</h2></div><i class="ti ti-users text-secondary"></i></div>
        @php($total=max(1,$metrics['students']))
        <div class="gep-dash-gender-chart"><div class="gep-dash-donut" style="background:conic-gradient(var(--dash-blue) 0 {{ $metrics['male']*100/$total }}%,var(--dash-violet) {{ $metrics['male']*100/$total }}% {{ ($metrics['male']+$metrics['female'])*100/$total }}%,var(--tblr-border-color,#e5eaf2) 0)" role="img" aria-label="Male {{ $metrics['male'] }}, female {{ $metrics['female'] }}, unspecified {{ $metrics['unknown_gender'] }}"><div><strong>{{ number_format($metrics['students']) }}</strong><span>students</span></div></div></div>
        <div class="gep-dash-legend">@foreach(['male'=>['Male','blue'],'female'=>['Female','violet'],'unknown_gender'=>['Unspecified','gray']] as $key=>[$label,$color])<div><span class="gep-dash-dot" style="background:var(--dash-{{ $color }})"></span><span>{{ $label }}</span><strong>{{ number_format($metrics[$key]) }}</strong><small>{{ round($metrics[$key]*100/$total,1) }}%</small></div>@endforeach</div>
    </section>
</div>
<div class="gep-dash-main-grid">
    <section class="gep-dash-panel"><div class="gep-dash-heading"><div><span class="gep-dash-kicker">CAMPUS SNAPSHOT</span><h2>Enrollment by campus</h2></div><i class="ti ti-building text-secondary"></i></div>
        <div class="table-responsive"><table class="table gep-dash-table"><thead><tr><th>Campus</th><th>Students</th><th>{{ ucfirst($mainStatus) }}</th><th>New</th><th>Withdrawn</th></tr></thead><tbody>@forelse($campusRows as $row)<tr><td><span class="gep-dash-campus-icon"><i class="ti ti-school"></i></span><strong>{{ $row['label'] }}</strong></td><td>{{ number_format($row['total']) }}</td><td>{{ number_format($row[$mainStatus]) }}</td><td>{{ number_format($row['new']) }}</td><td>{{ number_format($row['withdrawn']) }}</td></tr>@empty<tr><td colspan="5" class="text-center text-secondary py-4">No campus enrollments to display.</td></tr>@endforelse</tbody></table></div>
    </section>
    <section class="gep-dash-panel"><div class="gep-dash-heading"><div><span class="gep-dash-kicker">PROGRAM MIX</span><h2>Students by level</h2></div><i class="ti ti-stairs-up text-secondary"></i></div>
        @php($levelPeak=max(1,$levelRows->max('total')??0))
        <div class="gep-dash-levels">@forelse($levelRows as $row)<div><div class="d-flex justify-content-between mb-2"><span>{{ $row['label'] }}</span><strong>{{ number_format($row['total']) }}</strong></div><div class="gep-dash-level-track"><span style="width:{{ $row['total']*100/$levelPeak }}%"></span></div></div>@empty<div class="gep-dash-empty">No level data for this selection.</div>@endforelse</div>
    </section>
</div>
<section class="gep-dash-panel"><div class="gep-dash-heading"><div><span class="gep-dash-kicker">ENROLLMENT ACTIVITY</span><h2>Recent enrollments</h2></div><a class="btn btn-sm btn-outline-primary" href="{{ route('gep.students.section',['studentSection'=>'enrollment']) }}">View enrollments<i class="ti ti-arrow-up-right ms-2"></i></a></div><p class="gep-dash-subtitle">Latest enrollment dates within your selection.</p>
    <div class="table-responsive"><table class="table gep-dash-table"><thead><tr><th>Student</th><th>Campus</th><th>Level</th><th>Term</th><th>Type</th><th>Status</th><th>Enrolled on</th></tr></thead><tbody>@forelse($recent as $row)<tr><td><strong>{{ $row->gepStudent->profile['full_name_en']??$row->gepStudent->profile['full_name_kh']??'Student' }}</strong><div class="small text-secondary">{{ $row->gepStudent->student_identifier }}</div></td><td>{{ $row->campus?->campus_name_en }}</td><td>{{ $row->level?->level_name }}</td><td>{{ $row->termAssignment?->term?->term_name }}</td><td>{{ ucfirst($row->student_type) }}</td><td><span class="gep-dash-status gep-dash-status-{{ $row->enrollment_status }}">{{ ucfirst($row->enrollment_status) }}</span></td><td class="text-nowrap">{{ $row->enrolled_on?->format('d-M-Y')??'—' }}</td></tr>@empty<tr><td colspan="7" class="text-center text-secondary py-4">No enrollments yet. Choose another term or start from GEP Student Enrollment.</td></tr>@endforelse</tbody></table></div>
</section>
