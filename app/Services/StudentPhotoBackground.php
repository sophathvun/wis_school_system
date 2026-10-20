<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class StudentPhotoBackground
{
    public function available(): bool
    {
        return is_file(config('student_photos.python')) && is_file(config('student_photos.model'));
    }

    public function remove(UploadedFile $file): string
    {
        abort_unless($this->available(), 503, 'Background removal is not installed on this server. Please contact your administrator.');
        $lock = Cache::lock('student-photo-background', 100);
        abort_unless($lock->get(), 429, 'Another photo is being processed. Please try again in a moment.');
        $directory = storage_path('app/private/student-photo-ai/tmp/'.Str::uuid());
        try {
            File::ensureDirectoryExists($directory);
            File::copy($file->getRealPath(), $directory.'/input');
            $process = new Process([
                config('student_photos.python'), base_path('scripts/student-photo-ai/matte.py'),
                config('student_photos.model'), $directory.'/input', $directory.'/output.png',
            ], base_path(), [
                'OMP_NUM_THREADS' => '2', 'NUMBA_NUM_THREADS' => '2',
                'NUMBA_CACHE_DIR' => storage_path('app/private/student-photo-ai/numba-cache'),
            ]);
            $process->setTimeout(75);
            try {
                $process->mustRun();
            } catch (\Throwable $exception) {
                report($exception);
                abort(503, 'The background could not be removed. Please try again or keep the original background.');
            }
            abort_unless(is_file($directory.'/output.png'), 503, 'No photo was returned. Please try another portrait.');

            return File::get($directory.'/output.png');
        } finally {
            File::deleteDirectory($directory);
            $lock->release();
        }
    }
}
