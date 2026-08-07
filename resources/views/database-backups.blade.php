@extends('layouts.app')

@section('title', 'Database Backups')
@section('page-header')
<div class="container-fluid"><div class="row g-2 align-items-center"><div class="col"><div class="page-pretitle">System</div><h2 class="page-title">Database Backups</h2></div><div class="col-auto"><form method="POST" action="{{ route('database-backups.create') }}">@csrf<button class="btn btn-primary"><i class="ti ti-database-export me-1"></i>Backup Now</button></form></div></div></div>
@endsection

@section('content')
<div class="alert alert-info"><i class="ti ti-info-circle me-2"></i>Backups contain sensitive school data and are stored privately in <code>storage/app/private/backups</code>. Download a copy to another secure computer or drive.</div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="card"><div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr><th>Backup File</th><th>Created</th><th>Size</th><th class="text-end">Actions</th></tr></thead><tbody>
@forelse($backups as $backup)<tr><td><i class="ti ti-file-database me-2"></i>{{ $backup['filename'] }}</td><td>{{ date('Y-m-d H:i:s', $backup['modified']) }}</td><td>{{ number_format($backup['size'] / 1048576, 2) }} MB</td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('database-backups.download', $backup['filename']) }}"><i class="ti ti-download"></i> Download</a> <form class="d-inline" method="POST" action="{{ route('database-backups.delete', $backup['filename']) }}" onsubmit="return confirm('Delete this database backup?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="ti ti-trash"></i></button></form></td></tr>
@empty<tr><td colspan="4" class="text-center text-secondary py-4">No database backups created yet.</td></tr>@endforelse
</tbody></table></div></div>
<div class="card mt-3"><div class="card-body"><h3 class="card-title">Backup reminder</h3><p class="text-secondary mb-0">A database backup does not include uploaded logos, photos, or documents. Back up <code>storage/app/public</code> separately for a complete system backup.</p></div></div>
@endsection
