<?php

namespace App\Console\Commands;

use App\Models\Student;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ImportStudentPhotos extends Command
{
    protected $signature = 'legacy:import-student-photos {--path=storage/app/imports/stu_photos}';
    protected $description = 'Resize, compress, and import legacy student photos by Student ID';

    public function handle(): int
    {
        if (!function_exists('imagecreatetruecolor')) { $this->error('PHP GD extension is required.'); return self::FAILURE; }
        $source = base_path($this->option('path'));
        if (!is_dir($source)) { $this->error("Photo folder not found: {$source}"); return self::FAILURE; }
        $destination = storage_path('app/public/students');
        if (!is_dir($destination)) mkdir($destination, 0775, true);
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
        $processed = $matched = $unmatched = $failed = 0;
        $unmatchedIds = [];
        foreach ($files as $file) {
            if (!$file->isFile() || !in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'webp'], true)) continue;
            $processed++;
            $studentId = '';
            preg_match('/(?:^|_)([0-9]{5,})(?:\s*\(\d+\))?(?:\.[^.]+)?$/', $file->getFilename(), $match);
            if (empty($match[1]) && preg_match('/([0-9]{5,})/', $file->getFilename(), $embeddedMatch)) $match[1] = $embeddedMatch[1];
            if (!empty($match[1])) $studentId = ltrim($match[1], '0') === '' ? '0' : $match[1];
            $student = Student::where('student_id', $studentId)->first();
            if (!$student && ctype_digit($studentId)) $student = Student::whereRaw('CAST(student_id AS UNSIGNED) = ?', [(int) $studentId])->first();
            if (!$student) {
                $photoName = preg_replace('/_[0-9]{5,}\.[^.]+$/', '', $file->getFilename());
                $photoName = preg_replace('/^[A-Z]?\d+_OR\s+/i', '', (string) $photoName);
                $photoName = preg_replace('/\s+/u', ' ', trim((string) $photoName));
                if ($photoName !== '') $student = Student::whereRaw('UPPER(first_name_en) = ?', [strtoupper($photoName)])->first();
            }
            if (!$student) { $unmatched++; $unmatchedIds[] = $studentId; continue; }
            $storedId = (string) $student->student_id;
            $relative = 'students/'.$storedId.'.jpg';
            $targetPath = $destination.DIRECTORY_SEPARATOR.$storedId.'.jpg';
            if (is_file($targetPath) && filesize($targetPath) > 0) {
                if ($student->photo_path !== $relative) $student->update(['photo_path' => $relative]);
                $matched++;
                continue;
            }
            try {
                $output = $this->resizeToCanvas($file->getPathname());
                if (!$output) throw new \RuntimeException('Unsupported or unreadable image');
                imagejpeg($output, $targetPath, 82);
                imagedestroy($output);
                $student->update(['photo_path' => $relative]);
                $matched++;
            } catch (\Throwable $exception) { $failed++; }
        }
        file_put_contents(storage_path('app/imports/student-photo-unmatched.txt'), implode(PHP_EOL, array_unique($unmatchedIds)));
        $this->info(json_encode(compact('processed', 'matched', 'unmatched', 'failed'), JSON_UNESCAPED_UNICODE));
        $this->line('Unmatched Student IDs: storage/app/imports/student-photo-unmatched.txt');
        return self::SUCCESS;
    }

    private function resizeToCanvas(string $path)
    {
        $info = getimagesize($path); if (!$info) return null;
        $source = match ($info['mime']) { 'image/jpeg' => imagecreatefromjpeg($path), 'image/png' => imagecreatefrompng($path), 'image/webp' => imagecreatefromwebp($path), default => null };
        if (!$source) return null;
        $width = imagesx($source); $height = imagesy($source); if ($width < 1 || $height < 1) { imagedestroy($source); return null; }
        $canvas = imagecreatetruecolor(600, 800); $white = imagecolorallocate($canvas, 255, 255, 255); imagefill($canvas, 0, 0, $white);
        $scale = min(600 / $width, 800 / $height); $newWidth = max(1, (int) round($width * $scale)); $newHeight = max(1, (int) round($height * $scale));
        $x = (600 - $newWidth) / 2; $y = (800 - $newHeight) / 2; imagecopyresampled($canvas, $source, $x, $y, 0, 0, $newWidth, $newHeight, $width, $height); imagedestroy($source);
        return $canvas;
    }
}
