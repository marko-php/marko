<?php

declare(strict_types=1);

use Marko\Tests\Support\MiddlewareDecoration\MiddlewareDecorationDetector;
use Marko\Tests\Support\MiddlewareDecoration\MiddlewareDiscovery;

require_once __DIR__ . '/Support/MiddlewareDecoration/MiddlewareDecorationDetector.php';
require_once __DIR__ . '/Support/MiddlewareDecoration/MiddlewareDiscovery.php';

$packagesRoot = dirname(__DIR__) . '/packages';
$fixturesRoot = __DIR__ . '/Fixtures/MiddlewareDecoration';

it('passes for the current middleware in the repository', function () use ($packagesRoot): void {
    $files = (new MiddlewareDiscovery())->discover($packagesRoot);
    $violations = (new MiddlewareDecorationDetector())->scan($files);

    expect($files)->not->toBeEmpty()
        ->and($violations)->toBeEmpty();
});

it(
    'discovers middleware that live directly under src slash middleware',
    function () use ($packagesRoot, $fixturesRoot): void {
        $files = (new MiddlewareDiscovery())->discover($packagesRoot);

        // packages/security/src/Middleware/SecurityHeadersMiddleware.php has zero
        // intermediate directories between src/ and Middleware/. A naive
        // glob('packages/*/src/**/Middleware/*.php') silently misses it because
        // glob() has no ** support; RecursiveDirectoryIterator must not.
        expect($files)->toContain($packagesRoot . '/security/src/Middleware/SecurityHeadersMiddleware.php')
            ->and(array_filter($files, fn (string $file): bool => str_starts_with($file, $fixturesRoot)))
            ->toBeEmpty();
    },
);

it('fails when a middleware constructs a response after calling next', function () use ($fixturesRoot): void {
    $violations = (new MiddlewareDecorationDetector())->scan([$fixturesRoot . '/RebuildAfterNextMiddleware.php']);

    expect($violations)->not->toBeEmpty();
});

it(
    'fails when a middleware rebuilds a response from headers held in a local variable',
    function () use ($fixturesRoot): void {
        $violations = (new MiddlewareDecorationDetector())->scan(
            [$fixturesRoot . '/RebuildFromLocalVariableMiddleware.php'],
        );

        expect($violations)->not->toBeEmpty();
    },
);

it(
    'allows a middleware to construct a genuinely new response before calling next',
    function () use ($fixturesRoot): void {
        $violations = (new MiddlewareDecorationDetector())->scan(
            [$fixturesRoot . '/FreshResponseBeforeNextMiddleware.php'],
        );

        expect($violations)->toBeEmpty();
    },
);

it('allows a middleware to construct a response in a helper method', function () use ($fixturesRoot): void {
    $violations = (new MiddlewareDecorationDetector())->scan([$fixturesRoot . '/HelperMethodResponseMiddleware.php']);

    expect($violations)->toBeEmpty();
});

it('reports the offending file and line and a suggested fix when it fails', function () use ($fixturesRoot): void {
    $file = $fixturesRoot . '/RebuildAfterNextMiddleware.php';
    $violations = (new MiddlewareDecorationDetector())->scan([$file]);

    expect($violations)->toHaveCount(1)
        ->and($violations[0]['file'])->toBe($file)
        ->and($violations[0]['line'])->toBe(27)
        ->and($violations[0]['message'])
        ->toContain($file)
        ->toContain('27')
        ->toContain('withHeader')
        ->toContain('withHeaders')
        ->toContain('withStatus');
});
