@extends('layouts.app')
@section('title','Student Skipping Grade')
@section('page-header')
<div class="container-fluid"><div class="page-pretitle">Students</div><h2 class="page-title">Student Skipping Grade</h2></div>
@endsection
@section('content')
@include('student-skipping-grade._workspace-start')
<div class="skipping-page" data-skipping-page data-success="{{ session('success') }}">
    <div class="card mb-3 skipping-filter-card" data-skipping-filter-card>
        <div class="card-header skipping-header"><h3 class="card-title">Data Filters</h3><button type="button" class="btn btn-icon btn-outline-light" data-skipping-filters-toggle aria-label="Collapse data filters" aria-expanded="true" aria-controls="skippingListFilterBody"><i class="ti ti-chevron-up" aria-hidden="true"></i></button></div>
        <div class="card-body skipping-filter-body" id="skippingListFilterBody"><form method="get" class="skipping-filter-grid skipping-request-report-filters" data-skipping-list-filters>
        <input type="hidden" name="per_page" value="{{ $filters['per_page']??'20' }}">
        <div @class(['premium-form-field','has-value'=>filled($filters['academic_year_id']??null)])><label class="form-label" for="skippingListYear">Academic Year</label><select id="skippingListYear" class="form-select" name="academic_year_id" data-skipping-searchable="Academic Year"><option value="">All Years</option>@foreach($academicYears as $year)<option value="{{ $year->id }}" @selected(($filters['academic_year_id']??'')==$year->id)>{{ $year->academic_year }}</option>@endforeach</select></div>
        <div @class(['premium-form-field','has-value'=>filled($filters['campus_id']??null)])><label class="form-label" for="skippingListCampus">Campus</label><select id="skippingListCampus" class="form-select" name="campus_id" data-skipping-searchable="Campus"><option value="">All Campuses</option>@foreach($campuses as $campus)<option value="{{ $campus->id }}" @selected(($filters['campus_id']??'')==$campus->id)>{{ $campus->campus_name_en }}</option>@endforeach</select></div>
        <div @class(['premium-form-field','has-value'=>filled($filters['status']??null)])><label class="form-label" for="skippingListStatus">Status</label><select id="skippingListStatus" class="form-select" name="status" data-skipping-searchable="Status"><option value="">All Statuses</option>@foreach($statusOptions as $value=>$label)<option value="{{ $value }}" @selected(($filters['status']??'')===$value)>{{ $label }}</option>@endforeach</select></div>
        <div @class(['premium-form-field','has-value'=>filled($filters['grade_class']??null)])><label class="form-label" for="skippingListGrade">Current Grade</label><select id="skippingListGrade" class="form-select" name="grade_class" data-skipping-searchable="Current Grade"><option value="">All Current Grades</option>@foreach($gradeOptions as $grade)<option value="{{ $grade['value'] }}" @selected(($filters['grade_class']??'')===$grade['value'])>{{ $grade['label'] }}</option>@endforeach</select></div>
        <div @class(['premium-form-field','has-value'=>filled($filters['approval_number']??null)])><label class="form-label" for="skippingListApproval">Approved Number</label><select id="skippingListApproval" class="form-select" name="approval_number" data-skipping-searchable="Approved Number"><option value="">All Approved Numbers</option>@foreach($approvalNumbers as $number)<option value="{{ $number }}" @selected(($filters['approval_number']??'')===$number)>{{ $number }}</option>@endforeach</select></div>
        <div @class(['premium-form-field skipping-list-search-field','has-value'=>filled($filters['search']??null)])><label class="form-label" for="skippingListSearch">Search</label><input id="skippingListSearch" type="search" class="form-control" name="search" maxlength="120" value="{{ $filters['search']??'' }}" placeholder="Student ID, name, approved number, parent / guardian, phone" aria-label="Search student name, student ID, approved number, parent or guardian name, or phone number"></div>
        <button type="submit" class="visually-hidden">Apply filters</button>
    </form><div class="small text-secondary mt-2">A4 reports include all requests matching the selected filters, across every page. Academic year and current grade use the original placement recorded in the request.</div></div></div>
    <div class="card"><div class="card-header skipping-header skipping-list-header"><h3 class="card-title">Grade Skipping Requests</h3>
        <div class="skipping-list-toolbar"><div class="skipping-total"><small>Total Requests</small><strong>{{ $records->total() }}</strong></div>
        <div class="skipping-list-header-actions">
            @if($permissions['print-reports'])
                @php
                    $reportFilters=array_filter(\Illuminate\Support\Arr::except($filters,['per_page']),fn($value)=>filled($value));
                    $statisticsFilters=\Illuminate\Support\Arr::except($reportFilters,['status'])+['status'=>'approved'];
                @endphp
                <div class="dropdown">
                    <button class="btn btn-outline-light dropdown-toggle skipping-report-print-action" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="ti ti-printer me-1"></i>Print</button>
                    <div class="dropdown-menu dropdown-menu-end">
                        @if($records->total()>0)<a class="dropdown-item" target="_blank" rel="noopener" href="{{ route('student-skipping-grade.report.print',$reportFilters+['autoprint'=>1]) }}" data-skipping-report-print>Print List</a>
                        @else<button class="dropdown-item" type="button" data-skipping-report-help>Print List</button>@endif
                        <a class="dropdown-item" target="_blank" rel="noopener" href="{{ route('student-skipping-grade.statistics.print',$statisticsFilters+['autoprint'=>1]) }}" data-skipping-statistics-print>Print Statistics</a>
                    </div>
                </div>
                @foreach(['xlsx'=>['Excel','file-spreadsheet'],'pdf'=>['PDF','file-type-pdf']] as $format=>[$label,$icon])
                    <div class="dropdown">
                        <button class="btn btn-outline-light dropdown-toggle skipping-report-print-action" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="ti ti-{{ $icon }} me-1"></i>{{ $label }}</button>
                        <div class="dropdown-menu dropdown-menu-end">
                            @if($records->total()>0)<a class="dropdown-item" href="{{ route('student-skipping-grade.report.export',['kind'=>'list','format'=>$format]+$reportFilters) }}" data-skipping-report-export="list">Student List</a>
                            @else<button class="dropdown-item" type="button" data-skipping-report-help>Student List</button>@endif
                            <a class="dropdown-item" href="{{ route('student-skipping-grade.report.export',['kind'=>'statistics','format'=>$format]+$statisticsFilters) }}" data-skipping-report-export="statistics">Statistics</a>
                        </div>
                    </div>
                @endforeach
                <button class="btn btn-outline-light skipping-report-print-action" type="button" data-skipping-share-statistics="{{ route('student-skipping-grade.statistics.print',$statisticsFilters) }}"><i class="ti ti-share me-1"></i>Share Statistics</button>
            @endif
            @if($permissions['create'])<a class="btn btn-primary skipping-new-request" href="{{ route('student-skipping-grade.create') }}"><i class="ti ti-plus me-1"></i>New Request</a>@endif
        </div>
        </div>
    </div>
    <div class="table-responsive skipping-requests-table-wrap"><table class="table table-vcenter skipping-table skipping-requests-table" role="table"><thead><tr><th scope="col">No.</th><th scope="col">Photo</th><th scope="col">Student</th><th scope="col">Current Grade</th><th scope="col">Skipped Grade</th><th scope="col">Approvals</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead><tbody role="rowgroup">
        @forelse($records as $record)
            @php($s=$record->student_snapshot)
            <tr class="skipping-request-card" role="row"><td class="skipping-cell-number" role="cell">{{ $records->firstItem()+$loop->index }}</td>
            <td class="skipping-cell-photo" role="cell"><div class="skipping-list-photo">
                @if($photoUrls[$record->id])<img src="{{ $photoUrls[$record->id] }}" alt="Student photo: {{ $s['name_en'] }}" loading="lazy" width="60" height="80">
                @else<span><i class="ti ti-user" aria-hidden="true"></i>No photo</span>@endif
            </div></td>
            <td class="skipping-cell-student" role="cell"><div class="fw-semibold skipping-card-student-id">{{ $s['student_id'] }}</div><div class="skipping-kh skipping-card-student-kh">{{ $s['name_kh'] }}</div><div class="skipping-card-student-en">{{ $s['name_en'] }}</div><div class="text-secondary skipping-card-personal"><div>{{ ucfirst($s['gender']??'—') }}</div><div>{{ $reportRows[$record->id]['dob'] }}</div></div></td>
            <td class="skipping-cell-detail skipping-cell-grade skipping-cell-current" data-label="Current Grade" role="cell"><div class="skipping-cell-value"><div>{{ $s['academic_year'] }}</div><div>{{ $s['campus_en'] }}-{{ $reportRows[$record->id]['source_grade'] }}</div></div></td>
            <td class="skipping-cell-detail skipping-cell-grade skipping-cell-skipped" data-label="Skipped Grade" role="cell"><div class="skipping-cell-value"><div>{{ $s['target_academic_year']??$s['academic_year'] }}</div><div>{{ $s['campus_en'] }}-{{ $reportRows[$record->id]['target_grade'] }}</div></div></td>
            <td class="skipping-cell-detail skipping-cell-approvals" data-label="Approvals" role="cell"><div class="skipping-cell-value">@include('student-skipping-grade._workflow-progress')</div></td>
            <td class="skipping-cell-detail skipping-cell-status" data-label="Status" role="cell"><div class="skipping-cell-value"><span class="badge skipping-status-{{ $record->status }}">{{ $record->status==='pending'?'Submitted':ucfirst($record->status) }}</span>@if($record->reference_number)<div class="text-secondary small mt-1">{{ $record->reference_number }}</div>@endif</div></td>
            <td class="skipping-cell-actions" role="cell"><div class="skipping-actions"><a class="btn btn-sm btn-outline-primary" href="{{ route('student-skipping-grade.show',$record) }}"><i class="ti ti-eye me-1" aria-hidden="true"></i>View</a>
            @if(\App\Support\StudentSkippingGradePermissions::canEdit(auth()->user(),$record))<a class="btn btn-sm btn-outline-primary" data-action-normalized="true" href="{{ route('student-skipping-grade.edit',$record) }}"><i class="ti ti-edit me-1"></i>Edit</a>@endif
            @if($permissions['print'] && $record->status!=='rejected')<a class="btn btn-sm btn-outline-secondary" target="_blank" href="{{ route('student-skipping-grade.print',['skipping'=>$record,'form'=>$record->status==='approved'?'approval':'request']) }}"><i class="ti ti-printer me-1"></i>{{ $record->status==='approved'?'Approval':'Request' }}</a>@endif
            @if($permissions['print'] && $record->status==='approved')<button type="button" class="btn btn-sm btn-outline-primary" data-skipping-share-approval="{{ route('student-skipping-grade.share-approval',$record) }}"><i class="ti ti-share me-1"></i>Share</button>@endif</div></td></tr>
        @empty<tr class="skipping-request-empty" role="row"><td colspan="8" class="text-center text-secondary py-5" role="cell">No grade skipping requests found.</td></tr>@endforelse
    </tbody></table></div>
    @include('student-skipping-grade._pagination')
    </div>
</div>
@include('student-skipping-grade._workspace-end')
@endsection
@push('styles') @vite(['resources/css/pages/student-skipping-grade.css','resources/css/pages/student-skipping-grade-report.css']) @endpush
@push('scripts') @vite('resources/js/studentSkippingGrade.js') @endpush
