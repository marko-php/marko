<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Tests\Binary;

use Marko\Core\Path\ProjectPaths;
use Marko\Roadrunner\Binary\BinaryLocator;

describe('BinaryLocator', function (): void {
    it('locates an executable rr binary in vendor bin', function (): void {
        $tempDir = sys_get_temp_dir() . '/marko_rr_binary_' . bin2hex(random_bytes(8));
        mkdir($tempDir . '/vendor/bin', 0755, true);
        $binaryPath = $tempDir . '/vendor/bin/rr';
        file_put_contents($binaryPath, "#!/bin/sh\necho rr\n");
        chmod($binaryPath, 0755);

        $locator = new BinaryLocator(new ProjectPaths($tempDir));

        expect($locator->locate())->toBe($binaryPath);

        unlink($binaryPath);
        rmdir($tempDir . '/vendor/bin');
        rmdir($tempDir . '/vendor');
        rmdir($tempDir);
    });

    it('ignores a non executable file in vendor bin', function (): void {
        $tempDir = sys_get_temp_dir() . '/marko_rr_binary_' . bin2hex(random_bytes(8));
        mkdir($tempDir . '/vendor/bin', 0755, true);
        $binaryPath = $tempDir . '/vendor/bin/rr';
        file_put_contents($binaryPath, 'not executable');
        chmod($binaryPath, 0644);

        $locator = new BinaryLocator(new ProjectPaths($tempDir));

        expect($locator->locate())->not->toBe($binaryPath);

        unlink($binaryPath);
        rmdir($tempDir . '/vendor/bin');
        rmdir($tempDir . '/vendor');
        rmdir($tempDir);
    });
});
