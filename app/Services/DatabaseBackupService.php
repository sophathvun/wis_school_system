<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class DatabaseBackupService
{
    public function directory(): string
    {
        $directory = storage_path('app/private/backups');
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create the private backup directory.');
        }
        return $directory;
    }

    public function create(): array
    {
        $connection = config('database.default');
        $settings = config("database.connections.{$connection}");
        if (!in_array($settings['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            throw new \RuntimeException('Database backups currently support MySQL and MariaDB only.');
        }

        $filename = ($settings['database'] ?? 'school_system').'_'.now()->format('Ymd_His').'.sql';
        $path = $this->directory().'\\'.preg_replace('/[^A-Za-z0-9_.-]/', '_', $filename);
        $binary = env('DB_DUMP_BINARY') ?: $this->findBinary();
        $arguments = [
            $binary,
            '--host='.($settings['host'] ?? '127.0.0.1'),
            '--port='.($settings['port'] ?? 3306),
            '--user='.($settings['username'] ?? 'root'),
            '--single-transaction', '--routines', '--triggers', '--events',
            '--result-file='.$path,
            $settings['database'],
        ];
        $process = new Process($arguments, base_path(), ['MYSQL_PWD' => (string) ($settings['password'] ?? '')], 300);
        $process->run();
        if (!$process->isSuccessful() || !is_file($path) || filesize($path) === 0) {
            if (is_file($path)) unlink($path);
            throw new \RuntimeException(trim($process->getErrorOutput()) ?: 'The database backup command failed.');
        }

        return ['path' => 'backups/'.basename($path), 'filename' => basename($path), 'size' => filesize($path), 'created_at' => now()];
    }

    private function findBinary(): string
    {
        $candidates = array_merge(
            glob('C:\\laragon\\bin\\mysql\\*\\bin\\mysqldump.exe') ?: [],
            glob('C:\\xampp\\mysql\\bin\\mysqldump.exe') ?: []
        );
        return $candidates[0] ?? 'mysqldump';
    }
}
