<?php

// Render the real studio without querying student/staff records or login sessions.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$mode = $argv[1] ?? 'staff';
if ($mode === 'viewer') { echo view('staff-management._photo-viewer')->render(); return; }
$options = match ($mode) {
    'staff' => ['hrmStaffPhotoCropModal', 'Staff Photo Studio', 'staff.photo.background'],
    'user' => ['staffPhotoCropModal', 'User Photo Studio', 'users.photo.background'],
    'profile' => ['profilePhotoCropModal', 'Profile Photo Studio', 'profile.photo.background'],
};
echo view('photos._editor', [
    'editorId' => $options[0], 'photoTitle' => $options[1],
    'backgroundUrl' => route($options[2], [], false), 'photoWidth' => 400, 'photoHeight' => 400,
])->render();
