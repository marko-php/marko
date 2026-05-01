<?php

declare(strict_types=1);

use Marko\Inertia\Inertia;
use Marko\Inertia\Ssr\CurlSsrTransport;
use Marko\Inertia\Ssr\SsrClient;
use Marko\Inertia\Ssr\SsrTransportInterface;

test('module binds ssr transport and registers inertia singletons', function () {
    $module = require dirname(__DIR__).'/module.php';

    expect($module)->toHaveKey('bindings')
        ->and($module['bindings'])->toHaveKey(SsrTransportInterface::class)
        ->and($module['bindings'][SsrTransportInterface::class])->toBe(CurlSsrTransport::class)
        ->and($module)->toHaveKey('singletons')
        ->and($module['singletons'])->toContain(Inertia::class)
        ->and($module['singletons'])->toContain(SsrClient::class);
});

test('module stays minimal and avoids framework defaults', function () {
    $module = require dirname(__DIR__).'/module.php';

    expect($module)->not->toHaveKey('enabled')
        ->and($module)->not->toHaveKey('sequence');
});

test('inertia config declares frontend overlay slots', function () {
    $config = require dirname(__DIR__).'/config/inertia.php';

    expect($config['version'])->toBeNull()
        ->and($config['assetEntry'])->toBeNull()
        ->and($config['ssr']['enabled'])->toBeFalse()
        ->and($config['ssr']['url'])->toBe('http://localhost:13714')
        ->and($config['ssr']['bundle'])->toBeNull();
});
