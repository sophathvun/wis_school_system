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
    public function normalizeMisplacedBackups(): void
    {
        $privateDirectory = storage_path('app/private');
        if (!is_dir($privateDirectory)) {
            return;
        }

        foreach (scandir($privateDirectory) ?: [] as $entry) {
            if (!str_starts_with($entry, 'backups\\') || !str_ends_with(strtolower($entry), '.sql')) {
                continue;
            }

            $source = $privateDirectory.DIRECTORY_SEPARATOR.$entry;
            if (!is_file($source)) {
                continue;
            }

            $filename = basename(str_replace('\\', '/', $entry));
            if (!preg_match('/^[A-Za-z0-9_.-]+\.sql$/', $filename)) {
                continue;
            }

            $target = $this->directory().DIRECTORY_SEPARATOR.$filename;
            if (is_file($target)) {
                $info = pathinfo($filename);
                $target = $this->directory().DIRECTORY_SEPARATOR.$info['filename'].'_moved_'.now('Asia/Phnom_Penh')->format('Ymd_His').'.'.$info['extension'];
            }

            @rename($source, $target);
        }
    }

    public function create(): array
    {
        $connection = config('database.default');
        $settings = config("database.connections.{$connection}");
        if (!in_array($settings['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            throw new \RuntimeException('Database backups currently support MySQL and MariaDB only.');
        }

        $filename = ($settings['database'] ?? 'school_system').'_'.now('Asia/Phnom_Penh')->format('Ymd_His').'.sql';
        $safeFilename = preg_replace('/[^A-Za-z0-9_.-]/', '_', $filename);
        $path = $this->directory().DIRECTORY_SEPARATOR.$safeFilename;
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

        return ['path' => 'backups/'.basename($path), 'filename' => basename($path), 'size' => filesize($path), 'created_at' => now('Asia/Phnom_Penh')];
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

