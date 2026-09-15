<?php

$roots = [
    'app',
    'resources',
    'routes',
    'database/seeders',
];

$excluded = [
    DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR,
];

$extensions = ['php', 'blade.php', 'js', 'css', 'json', 'md'];
$badSequences = [
    "\u{00C3}",
    "\u{00C2}",
    "\u{00E1}",
    "\u{00E2}",
    "\u{FFFD}",
];

$matches = [];

$hasAllowedExtension = static function (string $path) use ($extensions): bool {
    foreach ($extensions as $extension) {
        if (str_ends_with($path, '.' . $extension)) {
            return true;
        }
    }

    return false;
};

foreach ($roots as $root) {
    if (!is_dir($root)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $path = $file->getPathname();
        if (!$hasAllowedExtension($path)) {
            continue;
        }

        foreach ($excluded as $excludedPart) {
            if (str_contains($path, $excludedPart)) {
                continue 2;
            }
        }

        $contents = file_get_contents($path);
        foreach ($badSequences as $sequence) {
            if (str_contains($contents, $sequence)) {
                $matches[] = $path;
                continue 2;
            }
        }
    }
}

$matches = array_values(array_unique($matches));
sort($matches);

if ($matches !== []) {
    fwrite(STDERR, "Possible mojibake/encoding corruption found:\n");
    foreach ($matches as $path) {
        fwrite(STDERR, " - {$path}\n");
    }
    exit(1);
}

echo "Encoding check passed. No mojibake markers found.\n";