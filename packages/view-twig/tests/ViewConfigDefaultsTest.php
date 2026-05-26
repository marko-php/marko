<?php

declare(strict_types=1);

it('ships a config/view.php file', function (): void {
    $configPath = dirname(__DIR__) . '/config/view.php';

    expect(file_exists($configPath))->toBeTrue();
});

it('defines extension as .twig', function (): void {
    $config = require dirname(__DIR__) . '/config/view.php';

    expect($config['extension'])->toBe('.twig');
});

it('defines strict_variables as true', function (): void {
    $config = require dirname(__DIR__) . '/config/view.php';

    expect($config['strict_variables'])->toBeTrue();
});

it('defines autoescape as html', function (): void {
    $config = require dirname(__DIR__) . '/config/view.php';

    expect($config['autoescape'])->toBe('html');
});

it('defines debug as false', function (): void {
    $config = require dirname(__DIR__) . '/config/view.php';

    expect($config['debug'])->toBeFalse();
});

it('defines charset as UTF-8', function (): void {
    $config = require dirname(__DIR__) . '/config/view.php';

    expect($config['charset'])->toBe('UTF-8');
});

it('does not redeclare cache_directory (inherited from marko/view shared config)', function (): void {
    $config = require dirname(__DIR__) . '/config/view.php';

    expect(array_key_exists('cache_directory', $config))->toBeFalse();
});

it('does not redeclare auto_refresh (inherited from marko/view shared config)', function (): void {
    $config = require dirname(__DIR__) . '/config/view.php';

    expect(array_key_exists('auto_refresh', $config))->toBeFalse();
});

it('returns a flat array (not nested under a view key)', function (): void {
    $config = require dirname(__DIR__) . '/config/view.php';

    expect(array_key_exists('view', $config))->toBeFalse();
});
