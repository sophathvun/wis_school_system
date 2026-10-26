<?php

use App\Services\DatabaseBackupService;
use Illuminate\Support\Facades\Cache;

it('reports server capacity and application file usage for the backup page', function () {
    Cache::forget('database-backups:storage-usage:v1');

    $usage = app(DatabaseBackupService::class)->storageUsage();

    expect($usage)
        ->toHaveKeys(['total', 'used', 'available', 'used_percent', 'application_bytes', 'application_files'])
        ->and($usage['total'])->toBeGreaterThan(0)
        ->and($usage['available'])->toBeGreaterThanOrEqual(0)
        ->and($usage['used'])->toBe($usage['total'] - $usage['available'])
        ->and($usage['used_percent'])->toBeGreaterThanOrEqual(0)
        ->and($usage['used_percent'])->toBeLessThanOrEqual(100)
        ->and($usage['application_bytes'])->toBeGreaterThanOrEqual(0)
        ->and($usage['application_files'])->toBeGreaterThanOrEqual(0);
});
