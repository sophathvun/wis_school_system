@extends('layouts.app')
@section('title','Supporting Document Archive')
@section('content')
@include('student-skipping-grade._workspace-start',['activeTab'=>'document-archive'])
<div class="skipping-page" data-skipping-page data-success="{{ session('success') }}">
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <div class="card mb-3">
        <div class="card-header skipping-header"><h3 class="card-title"><i class="ti ti-archive me-2"></i>Supporting Document Archive</h3></div>
        <div class="card-body">
            <p>Back up supporting documents by requested academic year and campus. Student records and approvals remain available after documents are archived.</p>
            <div class="alert alert-info">Keep the current and previous academic years online. Current academic year: <strong>{{ $retention['current']??'Not configured — deletion is disabled' }}</strong>. Draft and awaiting-approval requests are excluded. Archive older years annually, or whenever your backup schedule requires.</div>
            <form method="get" action="{{ route('student-skipping-grade.archive.index') }}" class="row g-3">
                <div class="col-12 col-md-6"><label class="form-label" for="archiveYear">Requested Academic Year</label><select id="archiveYear" name="academic_year_id" class="form-select" onchange="this.form.submit()">@foreach($years as $year)<option value="{{ $year->id }}" @selected((int)$yearId===(int)$year->id)>{{ $year->academic_year }}</option>@endforeach</select></div>
                <div class="col-12 col-md-6"><label class="form-label" for="archiveCampus">Campus</label><select id="archiveCampus" name="campus_id" class="form-select" onchange="this.form.submit()"><option value="">All Campuses</option>@foreach($campuses as $campus)<option value="{{ $campus->id }}" @selected((int)$campusId===(int)$campus->id)>{{ $campus->campus_name_en }}</option>@endforeach</select></div>
            </form>
            <div class="row g-3 mt-1">
                @foreach(['Completed requests'=>$summary['requests'],'Online documents'=>$summary['documents'],'Online size'=>number_format($summary['bytes']/1048576,2).' MB','Archived documents'=>$summary['archived']] as $label=>$value)
                    <div class="col-6 col-lg-3"><div class="border rounded p-3"><small class="text-secondary">{{ $label }}</small><strong class="d-block fs-2">{{ $value }}</strong></div></div>
                @endforeach
            </div>
            @if($summary['unfinished'])<p class="text-secondary mt-3 mb-0">{{ $summary['unfinished'] }} unfinished request(s) are excluded from backup and deletion.</p>@endif
            @if(\App\Support\StudentSkippingGradePermissions::allows(auth()->user(),'archive-backup'))
                <form method="post" action="{{ route('student-skipping-grade.archive.create') }}" data-skipping-form class="mt-3">@csrf
                    <input type="hidden" name="academic_year_id" value="{{ $yearId }}"><input type="hidden" name="campus_id" value="{{ $campusId }}">
                    <button type="submit" class="btn btn-primary" @disabled(!$summary['documents'])><i class="ti ti-file-zip me-1"></i>Create Backup</button>
                </form>
            @endif
        </div>
    </div>
    <div class="card">
        <div class="card-header skipping-header"><h3 class="card-title">Backup History</h3><a class="btn btn-outline-light ms-auto" href="{{ request()->fullUrl() }}"><i class="ti ti-refresh me-1"></i>Refresh</a></div>
        <div class="table-responsive"><table class="table table-vcenter mb-0"><thead><tr><th>Created</th><th>Academic Year / Campus</th><th>Documents</th><th>ZIP Parts</th><th>Status</th><th></th></tr></thead><tbody>
            @forelse($records as $archive)<tr>
                <td>{{ $archive->created_at->format('d-M-Y H:i') }}<small class="d-block text-secondary">{{ $archive->creator?->name??'Unknown user' }}</small></td>
                <td>{{ $archive->academic_year }}<small class="d-block text-secondary">{{ $archive->campus?->campus_name_en??'All Campuses' }}</small></td>
                <td>{{ $archive->files_count }}</td><td>{{ $archive->parts_count }}</td>
                <td><span class="badge {{ in_array($archive->status,['ready','restored'])?'bg-success-lt':(str_contains($archive->status,'failed')?'bg-danger-lt':'bg-secondary-lt') }}">{{ ucwords(str_replace('_',' ',$archive->status)) }}</span></td>
                <td><a class="btn btn-outline-primary btn-sm" href="{{ route('student-skipping-grade.archive.show',$archive) }}"><i class="ti ti-eye me-1"></i>View</a></td>
            </tr>@empty<tr><td colspan="6" class="text-center text-secondary py-4">No backups for the selected filters.</td></tr>@endforelse
        </tbody></table></div>
        <div class="card-footer">{{ $records->links() }}</div>
    </div>
</div>
@include('student-skipping-grade._workspace-end')
@endsection
@push('styles') @vite('resources/css/pages/student-skipping-grade.css') @endpush
@push('scripts') @vite('resources/js/studentSkippingGrade.js') @endpush
