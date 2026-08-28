<?php

declare(strict_types=1);

use Marko\Tests\Support\Psr7Containment\Psr7ContainmentDetector;
use Marko\Tests\Support\Psr7Containment\Psr7SymbolDiscovery;

require_once __DIR__ . '/Support/Psr7Containment/Psr7ContainmentDetector.php';
require_once __DIR__ . '/Support/Psr7Containment/Psr7SymbolDiscovery.php';

$packagesRoot = dirname(__DIR__) . '/packages';
$fixturesRoot = __DIR__ . '/Fixtures/Psr7Containment';

it('confines psr7 and roadrunner symbols to the roadrunner package', function () use ($packagesRoot): void {
    $files = (new Psr7SymbolDiscovery())->discover($packagesRoot, excludingPackage: 'roadrunner');
    $violations = (new Psr7ContainmentDetector())->scan($files);

    // filesystem-s3 type-hints the RequestInterface returned by aws-sdk-php's
    // presigned-URL builder — a pre-existing, unrelated PSR-7 touchpoint from the
    // AWS SDK's own dependency graph, not a leak of the roadrunner/routing boundary
    // this test guards. Every other confined reference must still be zero.
    $violations = array_filter(
        $violations,
        fn (array $violation): bool => $violation['file'] !== $packagesRoot
            . '/filesystem-s3/src/Filesystem/S3Filesystem.php',
    );

    expect($files)->not->toBeEmpty()
        ->and($violations)->toBeEmpty();
});

it('excludes the roadrunner package from discovery', function () use ($packagesRoot): void {
    $files = (new Psr7SymbolDiscovery())->discover($packagesRoot, excludingPackage: 'roadrunner');

    $roadrunnerFiles = array_filter(
        $files,
        fn (string $file): bool => str_starts_with($file, $packagesRoot . '/roadrunner/'),
    );

    expect($roadrunnerFiles)->toBeEmpty();
});

it('flags a Psr\Http\Message symbol used outside the roadrunner package', function () use ($fixturesRoot): void {
    $violations = (new Psr7ContainmentDetector())->scan([$fixturesRoot . '/ViolatingPsr7Usage.php']);

    expect($violations)->not->toBeEmpty()
        ->and($violations[0]['symbol'])->toContain('Psr\\Http\\Message');
});

it('does not flag a docblock or string mention of a confined namespace', function () use ($fixturesRoot): void {
    $violations = (new Psr7ContainmentDetector())->scan([$fixturesRoot . '/MentionsPsr7InDocblockOnly.php']);

    expect($violations)->toBeEmpty();
});
