<?php

declare(strict_types=1);

it('ships a known-drivers.php file listing marko/http-guzzle', function (): void {
    $knownDriversPath = __DIR__ . '/../known-drivers.php';

    expect(file_exists($knownDriversPath))->toBeTrue()
        ->and(require $knownDriversPath)->toHaveKey('marko/http-guzzle');
});
