@extends('layouts.app')
@section('title','Scholarship Result')
@section('page-header')
<div class="container-fluid"><div class="page-pretitle">WIS Scholarship</div><h2 class="page-title"><i class="ti ti-trophy me-2" aria-hidden="true"></i>Scholarship Result</h2></div>
@endsection
@section('content')
<div class="scholarship-workspace scholarship-tabs-collapsed" data-scholarship-page>
    <aside class="card scholarship-navigation">
        <div class="card-header scholarship-card-header scholarship-tabs-header"><h3 class="card-title"><i class="ti ti-award me-2" aria-hidden="true"></i>WIS Scholarship</h3><button type="button" class="btn btn-icon btn-outline-primary scholarship-tabs-toggle" aria-expanded="false" aria-controls="scholarshipTabList" aria-label="Expand scholarship tabs"><i class="ti ti-layout-sidebar-left-expand" aria-hidden="true"></i></button></div>
        <nav class="list-group list-group-flush" id="scholarshipTabList" aria-label="WIS Scholarship sections">
            @foreach($tabs as $key=>[$label,$icon])<a href="{{ route('western-scholarship.index',array_filter($filters)+['tab'=>$key]) }}" class="list-group-item list-group-item-action {{ $key===$tab?'active':'' }}" aria-label="{{ $label }}" data-scholarship-tab-label="{{ $label }}" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-custom-class="scholarship-tabs-tooltip" data-bs-title="{{ $label }}" @if($key===$tab)aria-current="page"@endif><i class="ti {{ $icon }}" aria-hidden="true"></i><span class="scholarship-tab-label">{{ $label }}</span></a>@endforeach
        </nav>
    </aside>
    <main class="scholarship-forms">
        @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
        @include('western-scholarship._result-filters')
        <section class="card" data-result-card>
            <div class="card-header scholarship-card-header scholarship-form-header">
                <h3 class="card-title"><i class="ti ti-trophy me-2" aria-hidden="true"></i>Scholarship Result</h3>
                <div class="d-flex flex-wrap gap-2 ms-auto" role="group" aria-label="Scholarship Result actions">
                    @if($canExport)
                        @foreach(['print'=>['Print Results','ti-printer'],'xlsx'=>['Excel','ti-file-spreadsheet'],'pdf'=>['PDF','ti-file-type-pdf']] as $format=>[$label,$icon])<a class="btn btn-sm btn-outline-light" data-result-report-link data-result-report-format="{{ $format }}" href="{{ route('western-scholarship.published-results.report',[$exam,$format]) }}" @if($format==='print')target="_blank" rel="noopener"@endif><i class="ti {{ $icon }} me-1" aria-hidden="true"></i>{{ $label }}</a>@endforeach
                    @endif
                    <button class="btn btn-sm btn-outline-light" type="button" data-result-fullscreen aria-pressed="false"><i class="ti ti-maximize me-2" aria-hidden="true"></i><span>Full Screen</span></button>
                </div>
            </div>
            <div class="card-body">
                @forelse($results->groupBy(fn($row)=>$row['candidate']->grade_number) as $grade=>$gradeRows)
                <h4 class="mb-3">Grade {{ $grade }}</h4>
                <div class="table-responsive mb-4"><table class="table table-vcenter scholarship-published-table">
                    <thead><tr><th>No.</th><th>Photo</th><th>Student</th><th>Examination</th><th>Result</th><th>Scholarship Award</th></tr></thead>
                    <tbody>@foreach($gradeRows as $row)
                        @php
                            $candidate=$row['candidate'];$snapshot=$candidate->snapshot;$name=$snapshot['name_en']??'';
                            $photo=$candidate->student?->photo_path;
                            $class=trim(explode(' / ',(string)($snapshot['class']??''))[0]);
                            $gradeClass=$snapshot['grade_class']??(preg_match('/^\d/',$class)?$class:$candidate->grade_number.$class);
                            $placement=collect([$exam->academicYear?->academic_year,$snapshot['campus']??null,$gradeClass,explode(' / ',(string)($snapshot['class']??''))[1]??null])->filter()->implode('-');
                        @endphp
                        <tr data-published-winner="{{ $candidate->id }}">
                            <td>{{ $loop->iteration }}</td>
                            <td>@if($photo)<button type="button" class="scholarship-rank-photo scholarship-photo-trigger" data-scholarship-photo="{{ asset('storage/'.$photo) }}" data-photo-name="{{ $name }}" aria-label="View photo of {{ $name }}"><img src="{{ asset('storage/'.$photo) }}" alt="Photo of {{ $name }}" width="600" height="800" loading="lazy"></button>@else<span class="text-secondary">No photo</span>@endif</td>
                            <td><strong>{{ $snapshot['student_code']??'' }}</strong><div class="scholarship-khmer">{{ $snapshot['name_kh']??'' }}</div><div>{{ $name }}</div><small class="text-secondary">{{ $placement }}</small></td>
                            <td><div>{{ $candidate->exam_center_name?:'Main Exam Center' }}</div><small>Room: {{ $candidate->room_number??'—' }} | Seat: {{ $candidate->room_seat??'—' }}</small><div class="small text-secondary">Code: {{ $candidate->candidate_code }}</div></td>
                            <td><span class="badge bg-green-lt">{{ $row['award_label'] }}</span></td><td><span class="badge bg-blue-lt">{{ rtrim(rtrim(number_format($row['award'],2,'.',''),'0'),'.') }}%</span></td>
                        </tr>
                    @endforeach</tbody>
                </table></div>
                @empty<div class="text-secondary py-4 text-center">{{ $exam?'No awarded students match these filters.':'No published scholarship results are available.' }}</div>@endforelse
            </div>
        </section>
    </main>
    @include('western-scholarship._photo-viewer')
    @if($canExport)@include('western-scholarship._result-report-scope')@endif
</div>
@vite(['resources/css/pages/western-scholarship.css','resources/js/westernScholarship.js'])
@endsection
