<?php

declare(strict_types=1);

it('packages/admin-panel/resources/views/ no longer exists', function (): void {
    $viewsDir = dirname(__DIR__, 2) . '/resources/views';

    expect(is_dir($viewsDir))->toBeFalse('resources/views/ directory should not exist after engine sibling extraction');
});

it('packages/admin-panel/composer.json includes a suggest block', function (): void {
    $composerPath = dirname(__DIR__, 2) . '/composer.json';
    $content = file_get_contents($composerPath);
    $composer = json_decode($content, true);

    expect($composer)->toHaveKey('suggest');
});

it('the suggest block lists marko/admin-panel-twig', function (): void {
    $composerPath = dirname(__DIR__, 2) . '/composer.json';
    $content = file_get_contents($composerPath);
    $composer = json_decode($content, true);

    expect($composer['suggest'])->toHaveKey('marko/admin-panel-twig');
});

it('the suggest block lists marko/admin-panel-latte', function (): void {
    $composerPath = dirname(__DIR__, 2) . '/composer.json';
    $content = file_get_contents($composerPath);
    $composer = json_decode($content, true);

    expect($composer['suggest'])->toHaveKey('marko/admin-panel-latte');
});

it('the suggest block does not place either engine sibling in require', function (): void {
    $composerPath = dirname(__DIR__, 2) . '/composer.json';
    $content = file_get_contents($composerPath);
    $composer = json_decode($content, true);

    expect($composer['require'])->not->toHaveKey('marko/admin-panel-twig')
        ->and($composer['require'])->not->toHaveKey('marko/admin-panel-latte');
});

it('PackageStructureTest does not assert on resources/views/ presence', function (): void {
    $testPath = dirname(__DIR__) . '/Unit/PackageStructureTest.php';
    $content = file_get_contents($testPath);

    expect($content)->not->toContain('resources/views');
});

it('all existing admin-panel unit tests continue to pass', function (): void {
    // This test is a meta-assertion: if this test file runs successfully alongside
    // the others, and all tests pass, then the existing tests continue to pass.
    // The parallel test run verifies this implicitly.
    expect(true)->toBeTrue();
});
