<?php

return [
    'pdf_compression' => [
        'enabled' => env('GRADE_SKIPPING_PDF_COMPRESSION', true),
        'binary' => env('GHOSTSCRIPT_BINARY'),
        'minimum_bytes' => 512 * 1024,
        'timeout_seconds' => 90,
        'image_resolution' => 180,
        'jpeg_quality' => 78,
    ],
    'image_optimization' => [
        'maximum_bytes' => 100_000,
        'maximum_width' => 1800,
        'maximum_height' => 2400,
        'maximum_pixels' => 40_000_000,
    ],
];
