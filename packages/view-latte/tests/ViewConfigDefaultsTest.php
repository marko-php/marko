<?php

declare(strict_types=1);

it('ships a config/view.php file in the view-latte package', function (): void {
    $configPath = dirname(__DIR__) . '/config/view.php';

    expect(file_exists($configPath))->toBeTrue();
});

it('sets extension to .latte by default', function (): void {
    $config = require dirname(__DIR__) . '/config/view.php';

    expect($config['extension'])->toBe('.latte');
});

it('sets strict_types to true by default', function (): void {
    $config = require dirname(__DIR__) . '/config/view.php';

    expect($config['strict_types'])->toBeTrue();
});

it('does not redeclare cache_directory (lives in marko/view shared config)', function (): void {
    $config = require dirname(__DIR__) . '/config/view.php';

    expect(array_key_exists('cache_directory', $config))->toBeFalse();
});

it('does not redeclare auto_refresh (lives in marko/view shared config)', function (): void {
    $config = require dirname(__DIR__) . '/config/view.php';

    expect(array_key_exists('auto_refresh', $config))->toBeFalse();
});
