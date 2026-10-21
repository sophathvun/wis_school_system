<?php

// Real shared views; no production records or login sessions.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo view('photos._workspace')->render();
echo view('photos._editor', ['editorId' => 'photoEditorModal', 'photoTitle' => 'Photo Editor',
    'backgroundUrl' => route('photo-editor.background', [], false), 'allowPhotoSizes' => true,
    'sizeUrl' => route('photo-editor.size', [], false)])->render();
