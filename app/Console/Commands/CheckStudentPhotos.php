<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class CheckStudentPhotos extends Command
{
    protected $signature = 'student-photos:check {--verify : Also run Python imports, model integrity/load, and private-storage checks offline}';

    protected $description = 'Diagnose the local student-photo runtime on this application server.';

    public function handle(): int
    {
        $python = (string) config('student_photos.python');
        $model = (string) config('student_photos.model');
        $checks = [
            ['PHP WebP encoder', function_exists('imagewebp'), 'PHP GD with WebP support'],
            ['PHP process execution', function_exists('proc_open'), 'proc_open must be enabled for the PHP application'],
            ['Python executable', is_file($python) && is_readable($python) && (PHP_OS_FAMILY === 'Windows' || is_executable($python)), $python ?: 'STUDENT_PHOTO_PYTHON is not configured'],
            ['Portrait model', is_file($model) && is_readable($model), $model ?: 'student_photos.model is not configured'],
        ];
        foreach (['matte.py', 'foreground.py', 'refine.py', 'fabric.py', 'check.py', 'requirements.txt'] as $file) {
            $path = base_path('scripts/student-photo-ai/'.$file);
            $checks[] = ['Photo processing file', is_file($path) && is_readable($path), $path];
        }
        $this->table(['Check', 'Status', 'Details'], array_map(fn ($check) => [$check[0], $check[1] ? 'OK' : 'MISSING', $check[2]], $checks));
        if (in_array(false, array_column($checks, 1), true)) {
            $this->error('Photo background removal is not ready on this server.');
            $this->line('Install the runtime on this server from the application folder:');
            $this->line(PHP_OS_FAMILY === 'Windows' ? '  python scripts/student-photo-ai/setup.py' : '  python3 scripts/student-photo-ai/setup.py');
            $this->line('Then run: php artisan config:clear');
            $this->line('Then run: php artisan student-photos:check --verify');
            $this->line('Use a full server-local executable path for STUDENT_PHOTO_PYTHON if overriding the default.');
            $this->line('Do not copy a Windows virtual environment to Linux; create it on the destination server.');

            return self::FAILURE;
        }
        if (! $this->option('verify')) {
            $this->info('Required files found. Run php artisan student-photos:check --verify to test the Python runtime and model.');

            return self::SUCCESS;
        }
        $process = new Process([
            $python, base_path('scripts/student-photo-ai/check.py'), $model,
            storage_path('app/private/student-photo-ai'),
        ], base_path(), [
            'OMP_NUM_THREADS' => '2', 'NUMBA_NUM_THREADS' => '2',
            'NUMBA_CACHE_DIR' => storage_path('app/private/student-photo-ai/numba-cache'),
        ]);
        $process->setTimeout(90);
        try {
            $process->run();
            $report = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            if (! isset($report['checks']) || ! is_array($report['checks'])) {
                throw new \RuntimeException('Python did not return a valid diagnostic report.');
            }
            $this->table(['Runtime check', 'Status', 'Details'], array_map(
                fn ($check) => [$check['name'], $check['ok'] ? 'OK' : 'FAILED', $check['detail']],
                $report['checks'],
            ));
            if (! $process->isSuccessful() || ! ($report['ok'] ?? false)) {
                $this->error('Correct the failed checks, re-run setup if packages/model are missing, and verify again.');

                return self::FAILURE;
            }
        } catch (\Throwable $exception) {
            $this->error('Unable to run the configured Python runtime: '.$exception->getMessage());
            $this->line(mb_substr(trim($process->getErrorOutput()), 0, 1200));
            $this->line('Check the Python path, process permissions, and local setup installation.');

            return self::FAILURE;
        }
        $this->info('Local photo runtime verified for this command user. The PHP web-service user needs the same executable and storage access.');

        return self::SUCCESS;
    }
}
