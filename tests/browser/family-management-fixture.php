<?php

// Render the real Family Management forms without production data or sessions.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$mode = $argv[1] ?? 'campus';
$flags = array_fill_keys(array_keys(\App\Support\FamilyPermissions::catalog()), true);
if ($mode === 'campus') { $flags['status'] = false; $flags['delete'] = false; }
if ($mode === 'readonly') foreach (array_keys($flags) as $action) $flags[$action] = in_array($action, ['view','search','history'], true);
$source = file_get_contents(resource_path('views/families.blade.php'));
$source = preg_replace('/@extends\([^\n]+\)|@section\([^\n]+\)|@endsection|@vite\([^\n]+\)/', '', $source);
echo \Illuminate\Support\Facades\Blade::render($source, [
    'familyPermissions' => $flags,
    'families' => collect([(object) ['id'=>1,'family_number'=>'F1'], (object) ['id'=>2,'family_number'=>'F2']]),
    'countries' => collect(), 'occupations' => collect(),
]);
