<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;

class CreateDatabaseBackup extends Command
{
    protected $signature = 'database:backup';

    protected $description = 'Create a private SQL backup of the application database';

    public function handle(DatabaseBackupService $backups): int
    {
        try {
            $backup = $backups->create();
            $this->info("Backup created: {$backup['filename']} ({$backup['size']} bytes)");
            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }
}
