@extends('layouts.app')
@section('title','My Grade Skipping Approvals')
@section('page-header')
<div class="container-fluid"><div class="page-pretitle">Student Skipping Grade</div><h2 class="page-title">My Approvals</h2></div>
@endsection
@section('content')
@include('student-skipping-grade._workspace-start',['activeTab'=>'my-approvals'])
<div class="skipping-page skipping-workflow-fields skipping-inbox-page" data-skipping-page>
    <div class="card mb-3">
        <button type="button" class="card-header skipping-header skipping-inbox-filter-toggle collapsed" data-bs-toggle="collapse" data-bs-target="#myApprovalFilters" aria-expanded="false" aria-controls="myApprovalFilters">
            <span class="card-title">Data Filters</span><i class="ti ti-chevron-down" aria-hidden="true"></i>
        </button>
        <div id="myApprovalFilters" class="collapse" data-skipping-inbox-filter-body><div class="card-body"><form method="get" class="skipping-form-grid skipping-inbox-filters" data-skipping-inbox-filters>
        <div class="premium-form-field has-value"><label for="myApprovalScope" class="form-label">Request View</label><select id="myApprovalScope" name="scope" class="form-select" data-skipping-searchable="Request View">
            @foreach($scopeOptions as $value=>$label)<option value="{{ $value }}" @selected($scope===$value)>{{ $label }}</option>@endforeach
        </select></div>
        <div class="premium-form-field has-value"><label for="myApprovalStatus" class="form-label">Status</label><select id="myApprovalStatus" name="status" class="form-select" data-skipping-searchable="Status">
            @foreach($statusOptions as $value=>$label)<option value="{{ $value }}" @selected($status===$value)>{{ $label }}</option>@endforeach
        </select></div>
        <div class="premium-form-field skipping-inbox-search-field {{ filled($filters['search']??null)?'has-value':'' }}"><label for="myApprovalSearch" class="form-label">Search</label><input id="myApprovalSearch" name="search" class="form-control" type="search" maxlength="120" value="{{ $filters['search']??'' }}" placeholder="Student ID, student or parent name"></div>
        </form></div></div>
    </div>
    <div class="card skipping-inbox-list"><div class="card-header skipping-header"><h3 class="card-title">{{ ['campus'=>'Campus Grade Skipping Requests','approvals'=>'My Grade Skipping Approvals','rejected'=>'My Rejected Grade Skipping Requests','requests'=>'My Grade Skipping Requests'][$scope] }} ({{ $records->total() }})</h3></div><div class="table-responsive"><table class="table table-vcenter skipping-inbox-table" role="table"><thead role="rowgroup"><tr role="row"><th scope="col">No.</th><th scope="col">Student Photo</th><th scope="col">Student</th><th scope="col">Current Grade</th><th scope="col">Skipped Grade</th><th scope="col">Approvals</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead><tbody role="rowgroup">
        @forelse($records as $record)
            @php($s=$record->student_snapshot)
            <tr role="row"><td role="cell" class="skipping-inbox-request" data-label="No.">{{ $records->firstItem()+$loop->index }}</td>
                <td role="cell" class="skipping-inbox-photo"><div class="skipping-list-photo">
                    @if($photoUrls[$record->id])<img src="{{ $photoUrls[$record->id] }}" alt="Student photo: {{ $s['name_en'] }}" loading="lazy" width="60" height="80">
                    @else<span><i class="ti ti-user" aria-hidden="true"></i>No photo</span>@endif
                </div></td>
                <td role="cell" class="skipping-inbox-student" data-label="Student">{{ $s['student_id'] }}<div class="skipping-kh">{{ $s['name_kh'] }}</div><div>{{ $s['name_en'] }}</div></td>
                <td role="cell" class="skipping-inbox-current" data-label="Current Grade"><div>{{ $s['academic_year'] }}</div><div>{{ $s['campus_en'] }}-{{ $reportRows[$record->id]['source_grade'] }}</div></td>
                <td role="cell" class="skipping-inbox-skipped" data-label="Skipped Grade"><div>{{ $s['target_academic_year']??$s['academic_year'] }}</div><div>{{ $s['campus_en'] }}-{{ $reportRows[$record->id]['target_grade'] }}</div></td>
                <td role="cell" class="skipping-inbox-approvals" data-label="Approvals">@include('student-skipping-grade._workflow-progress')</td>
                <td role="cell" class="skipping-inbox-status" data-label="Status"><span class="badge skipping-status-{{ $record->status }}">{{ ucfirst($record->status) }}</span></td>
                <td role="cell" class="skipping-inbox-actions"><a class="btn btn-outline-primary btn-sm" href="{{ route('student-skipping-grade.show',$record) }}">View Request</a></td>
            </tr>
        @empty<tr class="skipping-inbox-empty" role="row"><td role="cell" colspan="8" class="text-center py-4 text-secondary">No grade skipping requests match these filters.</td></tr>
        @endforelse
    </tbody></table></div><div class="card-footer">{{ $records->links() }}</div></div>
</div>
@include('student-skipping-grade._workspace-end')
@endsection
@push('styles') @vite('resources/css/pages/student-skipping-grade.css') @endpush
@push('scripts') @vite('resources/js/studentSkippingGrade.js') @endpush
