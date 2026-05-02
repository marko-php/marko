<?php

declare(strict_types=1);

use Marko\DevServer\Detection\FrontendDetector;

it('detects dev script in package.json', function (): void {
    $tmpDir = sys_get_temp_dir() . '/frontend-detector-test-' . bin2hex(random_bytes(8));
    mkdir($tmpDir, 0755, true);
    file_put_contents($tmpDir . '/package.json', json_encode(['scripts' => ['dev' => 'node build.js']]));

    $detector = new FrontendDetector($tmpDir);
    $result = $detector->detect();

    expect($result)->not->toBeNull();

    unlink($tmpDir . '/package.json');
    rmdir($tmpDir);
});

it('returns null when package.json does not exist', function (): void {
    $tmpDir = sys_get_temp_dir() . '/frontend-detector-test-' . bin2hex(random_bytes(8));
    mkdir($tmpDir, 0755, true);

    $detector = new FrontendDetector($tmpDir);
    $result = $detector->detect();

    expect($result)->toBeNull();

    rmdir($tmpDir);
});

it('returns null when package.json has no dev script', function (): void {
    $tmpDir = sys_get_temp_dir() . '/frontend-detector-test-' . bin2hex(random_bytes(8));
    mkdir($tmpDir, 0755, true);
    file_put_contents($tmpDir . '/package.json', json_encode(['scripts' => ['build' => 'node build.js']]));

    $detector = new FrontendDetector($tmpDir);
    $result = $detector->detect();

    expect($result)->toBeNull();

    unlink($tmpDir . '/package.json');
    rmdir($tmpDir);
});

it('uses bun when bun.lockb exists', function (): void {
    $tmpDir = sys_get_temp_dir() . '/frontend-detector-test-' . bin2hex(random_bytes(8));
    mkdir($tmpDir, 0755, true);
    file_put_contents($tmpDir . '/package.json', json_encode(['scripts' => ['dev' => 'node build.js']]));
    file_put_contents($tmpDir . '/bun.lockb', '');

    $detector = new FrontendDetector($tmpDir);
    $result = $detector->detect();

    expect($result)->toBe('bun run dev');

    unlink($tmpDir . '/package.json');
    unlink($tmpDir . '/bun.lockb');
    rmdir($tmpDir);
});

it('uses pnpm when pnpm-lock.yaml exists', function (): void {
    $tmpDir = sys_get_temp_dir() . '/frontend-detector-test-' . bin2hex(random_bytes(8));
    mkdir($tmpDir, 0755, true);
    file_put_contents($tmpDir . '/package.json', json_encode(['scripts' => ['dev' => 'node build.js']]));
    file_put_contents($tmpDir . '/pnpm-lock.yaml', '');

    $detector = new FrontendDetector($tmpDir);
    $result = $detector->detect();

    expect($result)->toBe('pnpm run dev');

    unlink($tmpDir . '/package.json');
    unlink($tmpDir . '/pnpm-lock.yaml');
    rmdir($tmpDir);
});

it('uses yarn when yarn.lock exists', function (): void {
    $tmpDir = sys_get_temp_dir() . '/frontend-detector-test-' . bin2hex(random_bytes(8));
    mkdir($tmpDir, 0755, true);
    file_put_contents($tmpDir . '/package.json', json_encode(['scripts' => ['dev' => 'node build.js']]));
    file_put_contents($tmpDir . '/yarn.lock', '');

    $detector = new FrontendDetector($tmpDir);
    $result = $detector->detect();

    expect($result)->toBe('yarn dev');

    unlink($tmpDir . '/package.json');
    unlink($tmpDir . '/yarn.lock');
    rmdir($tmpDir);
});

it('defaults to npm when no lockfile found', function (): void {
    $tmpDir = sys_get_temp_dir() . '/frontend-detector-test-' . bin2hex(random_bytes(8));
    mkdir($tmpDir, 0755, true);
    file_put_contents($tmpDir . '/package.json', json_encode(['scripts' => ['dev' => 'node build.js']]));

    $detector = new FrontendDetector($tmpDir);
    $result = $detector->detect();

    expect($result)->toBe('npm run dev');

    unlink($tmpDir . '/package.json');
    rmdir($tmpDir);
});

it('defaults to npm when only package-lock.json exists', function (): void {
    $tmpDir = sys_get_temp_dir() . '/frontend-detector-test-' . bin2hex(random_bytes(8));
    mkdir($tmpDir, 0755, true);
    file_put_contents($tmpDir . '/package.json', json_encode(['scripts' => ['dev' => 'node build.js']]));
    file_put_contents($tmpDir . '/package-lock.json', '{}');

    $detector = new FrontendDetector($tmpDir);
    $result = $detector->detect();

    expect($result)->toBe('npm run dev');

    unlink($tmpDir . '/package.json');
    unlink($tmpDir . '/package-lock.json');
    rmdir($tmpDir);
});
