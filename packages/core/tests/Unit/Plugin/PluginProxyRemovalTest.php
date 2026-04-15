<?php

declare(strict_types=1);

it('does not reference PluginProxy anywhere in the codebase after removal', function (): void {
    $packagesDir = dirname(__DIR__, 5) . '/packages';
    $pattern = 'PluginProxy';
    $output = shell_exec(
        'grep -r ' . escapeshellarg($pattern) . ' ' . escapeshellarg($packagesDir) .
        ' --include="*.php" --exclude-dir=vendor 2>/dev/null',
    ) ?? '';
    // Exclude this test file itself from results
    $lines = array_filter(
        explode("\n", trim($output)),
        fn (string $line) => $line !== '' && !str_contains($line, 'PluginProxyRemovalTest.php'),
    );
    expect($lines)->toBeEmpty();
});

it('asserts interceptor implements PluginInterceptedInterface instead of checking for PluginProxy', function (): void {
    $routerContent = file_get_contents(dirname(__DIR__, 5) . '/packages/routing/src/Router.php');
    expect($routerContent)->not->toContain('PluginProxy')
        ->and($routerContent)->toContain('PluginInterceptedInterface');
});

it('verifies PluginProxy class file no longer exists', function (): void {
    $path = dirname(__DIR__, 5) . '/packages/core/src/Plugin/PluginProxy.php';
    expect(file_exists($path))->toBeFalse();
});

it('updates Router.php to use instanceof PluginInterceptedInterface', function (): void {
    $routerContent = file_get_contents(dirname(__DIR__, 5) . '/packages/routing/src/Router.php');
    expect($routerContent)->toContain('instanceof PluginInterceptedInterface');
});

it('updates ContainerTest.php assertions from PluginProxy to PluginInterceptedInterface', function (): void {
    $containerTestContent = file_get_contents(dirname(__DIR__, 2) . '/Unit/Container/ContainerTest.php');
    expect($containerTestContent)->not->toContain('PluginProxy')
        ->and($containerTestContent)->toContain('PluginInterceptedInterface');
});
