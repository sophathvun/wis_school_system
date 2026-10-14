@extends('layouts.app')
@section('title','Supporting Document Backup')
@section('content')
@include('student-skipping-grade._workspace-start',['activeTab'=>'document-archive'])
@php
    $permissions=\App\Support\StudentSkippingGradePermissions::class;
    $busy=in_array($archive->status,['building','deleting','restoring']);
    $allDownloaded=$archive->parts->every(fn($p)=>$p->downloaded_at && $p->verified_at && $p->status==='verified');
@endphp
<div class="skipping-page" data-skipping-page data-success="{{ session('success') }}">
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    @if($archive->error)<div class="alert alert-danger">{{ $archive->error }}</div>@endif
    <div class="card mb-3">
        <div class="card-header skipping-header flex-wrap gap-2"><h3 class="card-title"><i class="ti ti-archive me-2"></i>Document Backup · {{ $archive->academic_year }}</h3><div class="ms-auto d-flex gap-2"><a class="btn btn-outline-light" href="{{ route('student-skipping-grade.archive.index',['academic_year_id'=>$archive->academic_year_id,'campus_id'=>$archive->campus_id]) }}">Back</a><a class="btn btn-outline-light" href="{{ request()->fullUrl() }}"><i class="ti ti-refresh me-1"></i>Refresh Status</a></div></div>
        <div class="card-body">
            <div class="row g-3"><div class="col-12 col-lg-6"><small class="text-secondary">Backup Reference</small><strong class="d-block text-break">{{ $archive->reference }}</strong></div><div class="col-6 col-lg-3"><small class="text-secondary">Campus</small><strong class="d-block">{{ $archive->campus?->campus_name_en??'All Campuses' }}</strong></div><div class="col-6 col-lg-3"><small class="text-secondary">Status</small><strong class="d-block">{{ ucwords(str_replace('_',' ',$archive->status)) }}</strong></div></div>
            <p class="mt-3 mb-0">Folders use the requested academic year and Approval Number. Each ZIP includes a student list and document checksums in its manifest.</p>
            @if($busy)<div class="alert alert-info mt-3 mb-0">Your {{ $archive->status==='deleting'?'document deletion':($archive->status==='restoring'?'document restore':'backup / verification') }} is running in the background. Refresh Status to check progress.</div>@endif
        </div>
        <div class="table-responsive"><table class="table table-vcenter mb-0"><thead><tr><th>ZIP Part</th><th>Documents</th><th>Size</th><th>Verification</th><th>Download / Restore</th></tr></thead><tbody>
            @foreach($archive->parts as $part)<tr><td>Part {{ $part->number }}<small class="d-block text-secondary text-break" style="max-width:22rem">SHA-256: {{ $part->sha256??'Preparing…' }}</small></td><td>{{ $part->files_count }}</td><td>{{ number_format($part->bytes/1048576,2) }} MB</td><td>{{ $part->verified_at?->format('d-M-Y H:i')??ucfirst($part->status) }}@if($part->error)<small class="d-block text-danger">{{ $part->error }}</small>@endif</td><td>
                @if($archive->status==='ready' && $part->status==='verified' && $permissions::allows(auth()->user(),'archive-backup'))
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('student-skipping-grade.archive.download',[$archive,$part]) }}"><i class="ti ti-download me-1"></i>Download ZIP</a>
                    @if($part->downloaded_at)<small class="d-block text-success mt-1">Download started {{ $part->downloaded_at->format('d-M-Y H:i') }}</small>@endif
                @endif
                @if(!$busy && in_array($archive->status,['deleted','partially_restored','restored','delete_failed','restore_failed']) && $permissions::allows(auth()->user(),'archive-restore'))
                    @if($part->restored_at)<span class="badge bg-success-lt">Restored {{ $part->restored_at->format('d-M-Y') }}</span>
                    @else<form method="post" enctype="multipart/form-data" action="{{ route('student-skipping-grade.archive.restore',[$archive,$part]) }}" data-skipping-form>@csrf<label class="form-label" for="archivePart{{ $part->id }}">Original ZIP for Part {{ $part->number }}</label><input id="archivePart{{ $part->id }}" class="form-control mb-2" type="file" name="backup" accept=".zip,application/zip" required><button class="btn btn-sm btn-outline-primary" type="submit"><i class="ti ti-restore me-1"></i>Restore Part</button></form>@endif
                @endif
            </td></tr>@endforeach
        </tbody></table></div>
        @if(!$busy && in_array($archive->status,['ready','failed']) && $permissions::allows(auth()->user(),'archive-backup'))<div class="card-footer"><form method="post" action="{{ route('student-skipping-grade.archive.verify',$archive) }}" data-skipping-form>@csrf<button type="submit" class="btn btn-outline-primary"><i class="ti ti-shield-check me-1"></i>Verify / Retry Backup</button></form></div>@endif
    </div>
    @if(in_array($archive->status,['ready','delete_failed']) && $permissions::allows(auth()->user(),'archive-delete'))
    <div class="card mb-3"><div class="card-header skipping-header"><h3 class="card-title">Delete Backed-up Documents</h3></div><div class="card-body">
        @if(!$canDeleteYear)<div class="alert alert-info mb-0">Deletion is disabled for the current and previous academic years. A started regular academic year must be configured.</div>
        @else
            <p>Download <strong>every ZIP part</strong> and save a complete copy outside this server. After deletion, the online documents <strong>and temporary server ZIPs</strong> are removed to free space. Student records, approvals, file names, and archive history remain.</p>
            @if(!$allDownloaded)<div class="alert alert-warning">Verify and download every part, then refresh this page to enable deletion.</div>@endif
            <form method="post" action="{{ route('student-skipping-grade.archive.delete',$archive) }}" data-skipping-form data-confirm="Delete the backed-up online documents and server ZIPs? You must retain every downloaded ZIP part outside this server to restore them later.">@csrf
                <fieldset @disabled(!$allDownloaded)><label class="form-check mb-3"><input type="checkbox" class="form-check-input" name="external_backup_saved" value="1" required><span class="form-check-label">I have downloaded every ZIP part and safely saved the complete backup outside this server.</span></label>
                <label class="form-label" for="archiveConfirmation">Type the backup reference to confirm</label><input id="archiveConfirmation" class="form-control mb-3" name="confirmation" autocomplete="off" required placeholder="{{ $archive->reference }}"><button type="submit" class="btn btn-danger"><i class="ti ti-trash me-1"></i>Delete Archived Documents</button></fieldset>
            </form>
        @endif
    </div></div>
    @endif
    <div class="card mb-3"><div class="card-header skipping-header"><h3 class="card-title">Document List</h3></div><div class="table-responsive"><table class="table table-vcenter mb-0"><thead><tr><th>Student</th><th>Approval / Request</th><th>Document</th><th>Status</th></tr></thead><tbody>
        @foreach($files as $file)<tr><td><span class="skipping-kh d-block">{{ $file->student['name_kh']??'' }}</span>{{ $file->student['name_en']??'' }}<small class="d-block text-secondary">{{ $file->student['student_id']??'' }} · {{ $file->student['campus']??'' }}</small></td><td>{{ $file->student['approval_number']?:'Request-'.$file->request_id }}</td><td class="text-break">{{ $file->original_name }}</td><td>{{ $file->restored_at?'Restored':($file->deleted_at?'Archived':'Online') }}</td></tr>@endforeach
    </tbody></table></div><div class="card-footer">{{ $files->withQueryString()->links() }}</div></div>
    <div class="card"><div class="card-header skipping-header"><h3 class="card-title">Activity History</h3></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Date</th><th>User</th><th>Action</th><th>Details</th></tr></thead><tbody>@foreach($events as $event)<tr><td>{{ $event->created_at }}</td><td>{{ $event->user_name??'System' }}</td><td>{{ ucwords(str_replace('_',' ',$event->action)) }}</td><td>{{ $event->details }}</td></tr>@endforeach</tbody></table></div><div class="card-footer">{{ $events->withQueryString()->links() }}</div></div>
</div>
@include('student-skipping-grade._workspace-end')
@endsection
@push('styles') @vite('resources/css/pages/student-skipping-grade.css') @endpush
@push('scripts') @vite('resources/js/studentSkippingGrade.js') @endpush
