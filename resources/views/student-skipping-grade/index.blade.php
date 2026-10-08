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
        <div class="card-body skipping-filter-body" id="skippingListFilterBody"><form method="get" class="skipping-filter-grid" data-skipping-list-filters>
        <input type="hidden" name="per_page" value="{{ $filters['per_page']??'20' }}">
        <div @class(['premium-form-field','has-value'=>filled($filters['academic_year_id']??null)])><label class="form-label" for="skippingListYear">Academic Year</label><select id="skippingListYear" class="form-select" name="academic_year_id" data-skipping-searchable="Academic Year"><option value="">All Years</option>@foreach($academicYears as $year)<option value="{{ $year->id }}" @selected(($filters['academic_year_id']??'')==$year->id)>{{ $year->academic_year }}</option>@endforeach</select></div>
        <div @class(['premium-form-field','has-value'=>filled($filters['campus_id']??null)])><label class="form-label" for="skippingListCampus">Campus</label><select id="skippingListCampus" class="form-select" name="campus_id" data-skipping-searchable="Campus"><option value="">All Campuses</option>@foreach($campuses as $campus)<option value="{{ $campus->id }}" @selected(($filters['campus_id']??'')==$campus->id)>{{ $campus->campus_name_en }}</option>@endforeach</select></div>
        <div @class(['premium-form-field','has-value'=>filled($filters['status']??null)])><label class="form-label" for="skippingListStatus">Status</label><select id="skippingListStatus" class="form-select" name="status" data-skipping-searchable="Status"><option value="">All Statuses</option>@foreach($statusOptions as $value=>$label)<option value="{{ $value }}" @selected(($filters['status']??'')===$value)>{{ $label }}</option>@endforeach</select></div>
        <div @class(['premium-form-field skipping-list-search-field','has-value'=>filled($filters['search']??null)])><label class="form-label" for="skippingListSearch">Search</label><input id="skippingListSearch" type="search" class="form-control" name="search" maxlength="120" value="{{ $filters['search']??'' }}" placeholder="Student ID, name, parent, reference"></div>
    </form></div></div>
    <div class="card"><div class="card-header skipping-header skipping-list-header"><h3 class="card-title">Grade Skipping Requests</h3><div class="skipping-total"><small>Total Requests</small><strong>{{ $records->total() }}</strong></div>
        @if($permissions['create'])<a class="btn btn-primary skipping-new-request" href="{{ route('student-skipping-grade.create') }}"><i class="ti ti-plus me-1"></i>New Request</a>@endif
    </div>
    <div class="table-responsive"><table class="table table-vcenter skipping-table"><thead><tr><th>No.</th><th>Photo</th><th>Student</th><th>Current Grade</th><th>Requested Grade</th><th>Parent / Guardian</th><th>Status</th><th>Actions</th></tr></thead><tbody>
        @forelse($records as $record)
            @php($s=$record->student_snapshot)
            <tr><td>{{ $records->firstItem()+$loop->index }}</td>
            <td><div class="skipping-list-photo">
                @if($photoUrls[$record->id])<img src="{{ $photoUrls[$record->id] }}" alt="Student photo: {{ $s['name_en'] }}" loading="lazy" width="60" height="80">
                @else<span><i class="ti ti-user" aria-hidden="true"></i>No photo</span>@endif
            </div></td>
            <td><div class="skipping-kh">{{ $s['name_kh'] }}</div><div>{{ $s['name_en'] }}</div><small class="text-secondary">{{ $s['student_id'] }}</small></td>
            <td><div>{{ $s['source_grade'] }}{{ $s['source_class'] }}</div><div class="text-secondary">{{ $s['campus_en'] }}</div><div class="text-secondary">{{ $s['academic_year'] }}</div></td>
            <td><div>{{ $s['target_grade'] }}{{ $s['target_class'] }}</div><div class="text-secondary">{{ $s['target_academic_year']??$s['academic_year'] }}</div><div class="text-secondary">{{ $record->application_date->format('d-M-Y') }}</div></td>
            <td>{{ $record->parent_name }}<div class="text-secondary">{{ $record->parent_phone }}</div></td>
            <td><span class="badge skipping-status-{{ $record->status }}">{{ $record->status==='pending'?'Submitted':ucfirst($record->status) }}</span>@if($record->reference_number)<div class="text-secondary small mt-1">{{ $record->reference_number }}</div>@endif</td>
            <td><div class="skipping-actions"><a class="btn btn-sm btn-outline-primary" href="{{ route('student-skipping-grade.show',$record) }}">View</a>
            @if(\App\Support\StudentSkippingGradePermissions::canEdit(auth()->user(),$record))<a class="btn btn-sm btn-outline-primary" data-action-normalized="true" href="{{ route('student-skipping-grade.edit',$record) }}"><i class="ti ti-edit me-1"></i>Edit</a>@endif
            @if($permissions['print'] && $record->status!=='rejected')<a class="btn btn-sm btn-outline-secondary" target="_blank" href="{{ route('student-skipping-grade.print',['skipping'=>$record,'form'=>$record->status==='approved'?'approval':'request']) }}"><i class="ti ti-printer me-1"></i>{{ $record->status==='approved'?'Approval':'Request' }}</a>@endif
            @if($permissions['print'] && $record->status==='approved')<button type="button" class="btn btn-sm btn-outline-primary" data-skipping-share-approval="{{ route('student-skipping-grade.share-approval',$record) }}"><i class="ti ti-share me-1"></i>Share</button>@endif</div></td></tr>
        @empty<tr><td colspan="8" class="text-center text-secondary py-5">No grade skipping requests found.</td></tr>@endforelse
    </tbody></table></div>
    @include('student-skipping-grade._pagination')
    </div>
</div>
@include('student-skipping-grade._workspace-end')
@endsection
@push('styles') @vite('resources/css/pages/student-skipping-grade.css') @endpush
@push('scripts') @vite('resources/js/studentSkippingGrade.js') @endpush
