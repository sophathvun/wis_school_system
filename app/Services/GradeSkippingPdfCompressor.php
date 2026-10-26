<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class GradeSkippingPdfCompressor
{
    public function prepare(UploadedFile $file): array
    {
        $source = $file->getRealPath();
        $originalSize = max(0, (int) ($file->getSize() ?: ($source && is_file($source) ? filesize($source) : 0)));
        $result = ['path' => $source, 'temporary_path' => null, 'original_size' => $originalSize, 'size' => $originalSize, 'compressed' => false];

        if (!config('student_skipping_grade.pdf_compression.enabled', true)
            || strtolower($file->getClientOriginalExtension()) !== 'pdf'
            || $originalSize < (int) config('student_skipping_grade.pdf_compression.minimum_bytes', 524288)
            || !$source || !is_file($source) || !$this->isPdf($source) || $this->isDigitallySigned($source)) {
            return $result;
        }

        $binary = $this->binary();
        if (!$binary) return $result;

        $disk = Storage::disk('local');
        $temporaryPath = 'tmp/grade-skipping-pdf/'.Str::uuid().'.pdf';
        $disk->makeDirectory(dirname($temporaryPath));
        $output = $disk->path($temporaryPath);
        $keepOutput = false;

        try {
            if (!$this->run($binary, $source, $output) || !$this->isCompletePdf($output)) return $result;
            $compressedSize = (int) filesize($output);
            if ($compressedSize >= (int) floor($originalSize * .95)) return $result;

            $keepOutput = true;
            return ['path' => $output, 'temporary_path' => $temporaryPath, 'original_size' => $originalSize, 'size' => $compressedSize, 'compressed' => true];
        } catch (\Throwable $exception) {
            Log::warning('Grade Skipping PDF compression failed; the original upload will be stored.', ['error' => $exception->getMessage()]);
            return $result;
        } finally {
            if (!$keepOutput && $disk->exists($temporaryPath)) $disk->delete($temporaryPath);
        }
    }

    public function cleanup(array $prepared): void
    {
        if (!empty($prepared['temporary_path'])) Storage::disk('local')->delete($prepared['temporary_path']);
    }

    protected function binary(): ?string
    {
        $configured = trim((string) config('student_skipping_grade.pdf_compression.binary'));
        $finder = new ExecutableFinder();
        if ($configured !== '') {
            if (is_file($configured)) return $configured;
            return $finder->find($configured);
        }
        foreach (['gs', 'gswin64c', 'gswin32c'] as $candidate) if ($binary = $finder->find($candidate)) return $binary;
        foreach (glob('C:\\Program Files\\gs\\*\\bin\\gswin64c.exe') ?: [] as $candidate) if (is_file($candidate)) return $candidate;
        return null;
    }

    protected function run(string $binary, string $source, string $output): bool
    {
        $resolution = max(120, min(240, (int) config('student_skipping_grade.pdf_compression.image_resolution', 180)));
        $quality = max(60, min(90, (int) config('student_skipping_grade.pdf_compression.jpeg_quality', 78)));
        $process = new Process([
            $binary, '-sDEVICE=pdfwrite', '-dCompatibilityLevel=1.4', '-dPDFSETTINGS=/ebook', '-dNOPAUSE', '-dQUIET', '-dBATCH', '-dSAFER',
            '-dDetectDuplicateImages=true', '-dCompressFonts=true', '-dSubsetFonts=true', '-dNumRenderingThreads=1',
            '-dDownsampleColorImages=true', '-dColorImageDownsampleType=/Bicubic', '-dColorImageResolution='.$resolution,
            '-dDownsampleGrayImages=true', '-dGrayImageDownsampleType=/Bicubic', '-dGrayImageResolution='.$resolution,
            '-dDownsampleMonoImages=true', '-dMonoImageResolution=300', '-dJPEGQ='.$quality,
            '-sOutputFile='.$output, $source,
        ]);
        $process->setTimeout((int) config('student_skipping_grade.pdf_compression.timeout_seconds', 90));
        $process->run();
        return $process->isSuccessful();
    }

    private function isPdf(string $path): bool
    {
        $stream = @fopen($path, 'rb');
        if (!$stream) return false;
        try { return str_starts_with((string) fread($stream, 5), '%PDF-'); }
        finally { fclose($stream); }
    }

    private function isCompletePdf(string $path): bool
    {
        if (!is_file($path) || filesize($path) < 1024 || !$this->isPdf($path)) return false;
        $stream = @fopen($path, 'rb');
        if (!$stream) return false;
        try {
            fseek($stream, -min(4096, filesize($path)), SEEK_END);
            return str_contains((string) stream_get_contents($stream), '%%EOF');
        } finally { fclose($stream); }
    }

    private function isDigitallySigned(string $path): bool
    {
        $stream = @fopen($path, 'rb');
        if (!$stream) return true;
        $carry = '';
        try {
            while (!feof($stream)) {
                $chunk = $carry.(string) fread($stream, 65536);
                if (str_contains($chunk, '/ByteRange') || preg_match('/\/Type\s*\/Sig\b/', $chunk)) return true;
                $carry = substr($chunk, -64);
            }
            return false;
        } finally { fclose($stream); }
    }
}
