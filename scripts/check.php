<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$directories = [
    $root . '/public',
    $root . '/scripts',
    $root . '/src',
];
$phpFiles = [];

foreach ($directories as $directory) {
    if (!is_dir($directory)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $phpFiles[] = $file->getPathname();
        }
    }
}

sort($phpFiles);

if ($phpFiles === []) {
    fwrite(STDERR, "No PHP source files were found.\n");
    exit(1);
}

foreach ($phpFiles as $file) {
    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file);
    passthru($command, $status);
    if ($status !== 0) {
        exit($status);
    }
}

$index = file_get_contents($root . '/public/index.php');
if ($index === false || !str_contains($index, 'Automated VPS deployment is working.')) {
    fwrite(STDERR, "The public deployment marker is missing.\n");
    exit(1);
}

echo "Silex Web checks passed.\n";
