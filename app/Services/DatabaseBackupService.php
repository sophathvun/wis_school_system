<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

class DatabaseBackupService
{
    private const STORAGE_USAGE_CACHE_KEY = 'database-backups:storage-usage:v1';

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

    public function storageUsage(): array
    {
        return Cache::remember(self::STORAGE_USAGE_CACHE_KEY, now()->addMinutes(5), function (): array {
            $path = storage_path('app');
            $total = @disk_total_space($path);
            $available = @disk_free_space($path);
            $applicationBytes = 0;
            $applicationFiles = 0;

            if (is_dir($path)) {
                try {
                    $files = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                        \RecursiveIteratorIterator::LEAVES_ONLY,
                        \RecursiveIteratorIterator::CATCH_GET_CHILD,
                    );
                    foreach ($files as $file) {
                        if (!$file->isFile() || $file->isLink()) continue;
                        $applicationBytes += $file->getSize();
                        $applicationFiles++;
                    }
                } catch (\Throwable $exception) {
                    report($exception);
                }
            }

            $total = is_numeric($total) ? (int) $total : 0;
            $available = is_numeric($available) ? (int) $available : 0;
            $used = max(0, $total - $available);

            return [
                'total' => $total,
                'used' => $used,
                'available' => $available,
                'used_percent' => $total > 0 ? round(($used / $total) * 100, 1) : 0,
                'application_bytes' => $applicationBytes,
                'application_files' => $applicationFiles,
            ];
        });
    }

    public function clearStorageUsageCache(): void
    {
        Cache::forget(self::STORAGE_USAGE_CACHE_KEY);
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

        $this->clearStorageUsageCache();

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

