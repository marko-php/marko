<?php

declare(strict_types=1);

use Marko\Forms\FlashedFormStateWriter;

it('keeps the flashed form state storage keys inside marko/forms', function (): void {
    $packagesDir = dirname(__DIR__, 3);
    $keys = [FlashedFormStateWriter::ERRORS_KEY, FlashedFormStateWriter::OLD_INPUT_KEY];
    $offenders = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($packagesDir, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($files as $file) {
        $path = $file->getPathname();

        if (
            $file->getExtension() !== 'php'
            || str_starts_with($path, $packagesDir . '/forms/')
            || str_contains($path, '/vendor/')
            || str_contains($path, '/node_modules/')
        ) {
            continue;
        }

        $source = (string) file_get_contents($path);

        foreach ($keys as $key) {
            if (str_contains($source, "'$key'") || str_contains($source, "\"$key\"")) {
                $offenders[] = substr($path, strlen($packagesDir) + 1) . " ($key)";
            }
        }
    }

    expect($offenders)->toBe([]);
});
