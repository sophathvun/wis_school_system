<?php

namespace App\Http\Controllers;

use App\Services\DatabaseBackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DatabaseBackupController
{
    public function __construct(private readonly DatabaseBackupService $backups) {}

    public function index(Request $request)
    {
        $this->authorize($request, 'database-backups.view');
        $disk = Storage::disk('local');
        $files = collect($disk->files('backups'))
            ->filter(fn ($path) => str_ends_with(strtolower($path), '.sql'))
            ->map(fn ($path) => ['path' => $path, 'filename' => basename($path), 'size' => $disk->size($path), 'modified' => $disk->lastModified($path)])
            ->sortByDesc('modified')->values();
        return view('database-backups', ['backups' => $files]);
    }

    public function create(Request $request)
    {
        $this->authorize($request, 'database-backups.create');
        try {
            $backup = $this->backups->create();
            return back()->with('success', "Backup created successfully: {$backup['filename']}");
        } catch (\Throwable $exception) {
            report($exception);
            return back()->withErrors(['backup' => 'Backup failed: '.$exception->getMessage()]);
        }
    }

    public function download(Request $request, string $filename)
    {
        $this->authorize($request, 'database-backups.download');
        abort_unless(basename($filename) === $filename && preg_match('/^[A-Za-z0-9_.-]+\.sql$/', $filename), 404);
        $path = 'backups/'.$filename;
        abort_unless(Storage::disk('local')->exists($path), 404);
        return Storage::disk('local')->download($path, $filename, ['Content-Type' => 'application/sql']);
    }

    public function delete(Request $request, string $filename)
    {
        $this->authorize($request, 'database-backups.delete');
        abort_unless(basename($filename) === $filename && preg_match('/^[A-Za-z0-9_.-]+\.sql$/', $filename), 404);
        $path = 'backups/'.$filename;
        abort_unless(Storage::disk('local')->exists($path), 404);
        Storage::disk('local')->delete($path);
        return back()->with('success', 'Backup deleted successfully.');
    }

    private function authorize(Request $request, string $permission): void
    {
        $user = $request->user();
        abort_unless($user && ($user->isSuperAdmin() || $user->hasPermission('settings.manage') || $user->hasPermission($permission)), 403);
    }
}
