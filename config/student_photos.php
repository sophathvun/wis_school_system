<?php

return [
    // Install once with: python scripts/student-photo-ai/setup.py
    'python' => env('STUDENT_PHOTO_PYTHON') ?: storage_path('app/private/student-photo-ai/venv/'.(PHP_OS_FAMILY === 'Windows' ? 'Scripts/python.exe' : 'bin/python')),
    'model' => storage_path('app/private/student-photo-ai/modnet.onnx'),
];
