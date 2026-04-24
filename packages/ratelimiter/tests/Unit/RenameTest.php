<?php

declare(strict_types=1);

it('has package at packages/ratelimiter/ with correct directory structure', function (): void {
    $base = dirname(__DIR__, 2);

    expect(is_dir($base))->toBeTrue()
        ->and(is_dir($base . '/src'))->toBeTrue()
        ->and(is_dir($base . '/src/Contracts'))->toBeTrue()
        ->and(is_dir($base . '/src/Middleware'))->toBeTrue()
        ->and(is_dir($base . '/tests'))->toBeTrue()
        ->and(file_exists($base . '/composer.json'))->toBeTrue()
        ->and(file_exists($base . '/module.php'))->toBeTrue();
});

it('declares composer.json name as marko/ratelimiter', function (): void {
    $composerJson = json_decode(
        file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
        true,
    );

    expect($composerJson['name'])->toBe('marko/ratelimiter');
});

it('uses PSR-4 namespace Marko\\RateLimiter\\ pointing to src/', function (): void {
    $composerJson = json_decode(
        file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
        true,
    );

    expect($composerJson['autoload']['psr-4'])->toHaveKey('Marko\\RateLimiter\\')
        ->and($composerJson['autoload']['psr-4']['Marko\\RateLimiter\\'])->toBe('src/');
});

it('has no remaining references to marko/rate-limiting in its own composer.json', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2) . '/composer.json');

    expect($contents)->not->toContain('marko/rate-limiting');
});

it('has no remaining Marko\\RateLimiting namespace declarations in src/ or tests/', function (): void {
    $base = dirname(__DIR__, 2);
    $files = array_merge(
        glob($base . '/src/*.php') ?: [],
        glob($base . '/src/**/*.php') ?: [],
        glob($base . '/tests/*.php') ?: [],
        glob($base . '/tests/**/*.php') ?: [],
    );

    foreach ($files as $file) {
        $contents = file_get_contents($file);
        expect($contents)
            ->not->toContain('Marko\\RateLimiting', "File $file still contains Marko\\RateLimiting");
    }

    expect(count($files))->toBeGreaterThan(0);
});

it('passes its existing Pest test suite after rename', function (): void {
    $base = dirname(__DIR__, 2);

    expect(file_exists($base . '/src/RateLimiter.php'))->toBeTrue()
        ->and(file_exists($base . '/src/RateLimitResult.php'))->toBeTrue()
        ->and(file_exists($base . '/src/Contracts/RateLimiterInterface.php'))->toBeTrue()
        ->and(file_exists($base . '/src/Middleware/RateLimitMiddleware.php'))->toBeTrue();
});
