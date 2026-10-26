@extends('layouts.app')

@section('title', 'Database Backups')
@section('page-header')
    <div class="container-fluid">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">Administrator</div>
                <h2 class="page-title">Database Backups</h2>
            </div>
            <div class="col-auto">
                <form method="POST" action="{{ route('database-backups.create') }}">@csrf<button class="btn btn-primary"><i
                            class="ti ti-database-export me-1"></i>Backup Now</button></form>
            </div>
        </div>
    </div>
@endsection

@section('content')
    @php
        $formatStorageSize = static function (int $bytes): string {
            $units = ['B', 'KB', 'MB', 'GB', 'TB'];
            $value = max(0, $bytes);
            $unit = 0;
            while ($value >= 1024 && $unit < count($units) - 1) {
                $value /= 1024;
                $unit++;
            }
            return number_format($value, $unit === 0 ? 0 : 2).' '.$units[$unit];
        };
    @endphp
    <section class="card database-storage-card mb-3" aria-labelledby="databaseStorageTitle">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
                <div>
                    <h3 class="card-title mb-1" id="databaseStorageTitle"><i class="ti ti-server-2 me-2"></i>Server Storage</h3>
                    <p class="text-secondary mb-0">Storage available on the server disk containing this system.</p>
                </div>
                <span class="badge bg-blue-lt">Updates every 5 minutes</span>
            </div>
            @if($storageUsage['total'] > 0)
                <div class="database-storage-progress mb-3">
                    <div class="progress" role="progressbar" aria-label="Server storage used" aria-valuenow="{{ $storageUsage['used_percent'] }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar" style="width: {{ min(100, $storageUsage['used_percent']) }}%"></div>
                    </div>
                    <div class="d-flex justify-content-between gap-3 mt-2 text-secondary small">
                        <span>{{ $storageUsage['used_percent'] }}% used</span>
                        <span>{{ $formatStorageSize($storageUsage['used']) }} of {{ $formatStorageSize($storageUsage['total']) }}</span>
                    </div>
                </div>
            @endif
            <div class="database-storage-stats">
                <div><span>Available</span><strong>{{ $formatStorageSize($storageUsage['available']) }}</strong><small>Space that can still be used</small></div>
                <div><span>Server Used</span><strong>{{ $formatStorageSize($storageUsage['used']) }}</strong><small>System, database, logs and files</small></div>
                <div><span>Application Files</span><strong>{{ $formatStorageSize($storageUsage['application_bytes']) }}</strong><small>{{ number_format($storageUsage['application_files']) }} files in storage/app</small></div>
                <div><span>Database Backups</span><strong>{{ $formatStorageSize($storageUsage['backup_bytes']) }}</strong><small>{{ number_format($storageUsage['backup_files']) }} backup file(s)</small></div>
            </div>
        </div>
    </section>
    <div class="alert alert-info database-backup-notice"><i class="ti ti-info-circle me-2"></i><span>Backups contain sensitive school data and are stored
        privately in <code>storage/app/private/backups</code>. Download a copy to another secure computer or drive.</span></div>
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif
    <div class="card database-backup-table-card database-backup-list-card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table database-backup-table">
                <thead>
                    <tr>
                        <th>Backup File</th>
                        <th>Created</th>
                        <th>Size</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($backups as $backup)
                        <tr>
                            <td><i class="ti ti-file-database me-2"></i>{{ $backup['filename'] }}</td>
                            <td>{{ \Carbon\Carbon::createFromTimestamp($backup['modified'])->timezone('Asia/Phnom_Penh')->format('Y-m-d H:i:s') }}</td>
                            <td>{{ number_format($backup['size'] / 1048576, 2) }} MB</td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-primary"
                                    href="{{ route('database-backups.download', $backup['filename']) }}"><i
                                        class="ti ti-download"></i> Download</a>
                                <form class="d-inline" method="POST"
                                    action="{{ route('database-backups.delete', $backup['filename']) }}"
                                    data-database-backup-delete-form>@csrf @method('DELETE')<button
                                        class="btn btn-sm btn-outline-danger"><i class="ti ti-trash me-1"></i><span>Delete</span></button></form>
                            </td>
                        </tr>
                    @empty<tr>
                            <td colspan="4" class="text-center text-secondary py-4">No database backups created yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="database-backup-mobile-list">
        @forelse($backups as $backup)
            <article class="card database-backup-mobile-card">
                <div class="database-backup-mobile-title"><i class="ti ti-file-database"></i><span>{{ $backup['filename'] }}</span></div>
                <div class="database-backup-mobile-meta">
                    <div><span>Created</span><strong>{{ \Carbon\Carbon::createFromTimestamp($backup['modified'])->timezone('Asia/Phnom_Penh')->format('Y-m-d H:i:s') }}</strong></div>
                    <div><span>Size</span><strong>{{ number_format($backup['size'] / 1048576, 2) }} MB</strong></div>
                </div>
                <div class="database-backup-mobile-actions">
                    <a class="btn btn-primary" href="{{ route('database-backups.download', $backup['filename']) }}"><i class="ti ti-download me-1"></i>Download</a>
                                    <form method="POST" action="{{ route('database-backups.delete', $backup['filename']) }}" data-database-backup-delete-form>@csrf @method('DELETE')<button class="btn btn-outline-danger" type="submit"><i class="ti ti-trash me-1"></i>Delete</button></form>
                </div>
            </article>
        @empty
            <div class="card database-backup-mobile-card text-center text-secondary">No database backups created yet.</div>
        @endforelse
    </div>
    <div class="card mt-3">
        <div class="card-body">
            <h3 class="card-title">Backup reminder</h3>
            <p class="text-secondary mb-0">A database backup does not include uploaded logos, photos, or documents. Back up
                <code>storage/app/public</code> separately for a complete system backup.</p>
        </div>
    </div>
@endsection
@vite('resources/js/databaseBackups.js')
@vite('resources/css/pages/database-backups.css')


