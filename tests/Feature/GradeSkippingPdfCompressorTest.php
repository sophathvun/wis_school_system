<?php

use App\Services\GradeSkippingPdfCompressor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('uses a smaller valid optimized PDF and removes its temporary file after storage', function () {
    Storage::fake('local');
    config()->set('student_skipping_grade.pdf_compression.minimum_bytes', 1024);
    $source = tempnam(sys_get_temp_dir(), 'grade-skip-pdf-');
    file_put_contents($source, "%PDF-1.4\n".str_repeat('original scan ', 5000)."\n%%EOF");
    $file = new UploadedFile($source, 'supporting-document.pdf', 'application/pdf', null, true);
    $compressor = new class extends GradeSkippingPdfCompressor {
        protected function binary(): ?string { return 'test-ghostscript'; }
        protected function run(string $binary, string $source, string $output): bool
        {
            file_put_contents($output, "%PDF-1.4\n".str_repeat('optimized ', 200)."\n%%EOF");
            return true;
        }
    };

    try {
        $prepared = $compressor->prepare($file);
        expect($prepared['compressed'])->toBeTrue()
            ->and($prepared['size'])->toBeLessThan($prepared['original_size'])
            ->and(Storage::disk('local')->exists($prepared['temporary_path']))->toBeTrue();
        $compressor->cleanup($prepared);
        Storage::disk('local')->assertMissing($prepared['temporary_path']);
    } finally { @unlink($source); }
});

it('does not modify a digitally signed PDF', function () {
    Storage::fake('local');
    config()->set('student_skipping_grade.pdf_compression.minimum_bytes', 1024);
    $source = tempnam(sys_get_temp_dir(), 'grade-skip-signed-pdf-');
    file_put_contents($source, "%PDF-1.7\n/Type /Sig /ByteRange [0 10 20 30]\n".str_repeat('signed ', 1000)."\n%%EOF");
    $file = new UploadedFile($source, 'signed-request.pdf', 'application/pdf', null, true);
    $compressor = new class extends GradeSkippingPdfCompressor {
        public bool $ran = false;
        protected function binary(): ?string { return 'test-ghostscript'; }
        protected function run(string $binary, string $source, string $output): bool { $this->ran = true; return true; }
    };

    try {
        $prepared = $compressor->prepare($file);
        expect($prepared['compressed'])->toBeFalse()->and($compressor->ran)->toBeFalse();
    } finally { @unlink($source); }
});
