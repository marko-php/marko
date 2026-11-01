<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Path\ProjectPaths;
use Marko\Inertia\Inertia;
use Marko\Inertia\Ssr\CurlSsrTransport;
use Marko\Inertia\Ssr\SsrClient;
use Marko\Inertia\Ssr\SsrTransportInterface;
use Marko\Inertia\Tests\FlashedErrors;
use Marko\Session\Contracts\SessionInterface;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeSession;
use Marko\Validation\Contracts\FlashedFormStateInterface;
use Marko\Vite\Vite;

test('module binds ssr transport and registers inertia singletons', function () {
    $module = require dirname(__DIR__).'/module.php';

    expect($module)->toHaveKey('bindings')
        ->and($module['bindings'])->toHaveKey(SsrTransportInterface::class)
        ->and($module['bindings'][SsrTransportInterface::class])->toBe(CurlSsrTransport::class)
        ->and($module)->toHaveKey('singletons')
        ->and($module['singletons'])->toHaveKey(Inertia::class)
        ->and($module['singletons'])->toContain(SsrClient::class);
});

/**
 * A container with everything the Inertia singleton closure resolves.
 */
function inertiaModuleContainer(): Container
{
    $config = new FakeConfigRepository([
        'inertia.ssr.enabled' => false,
        'inertia.ssr.url' => 'http://localhost:13714',
    ]);
    $container = new Container();
    $container->instance(ContainerInterface::class, $container);
    $container->instance(ConfigRepositoryInterface::class, $config);
    $container->instance(SessionInterface::class, new FakeSession());
    $container->instance(Vite::class, new Vite($config, new ProjectPaths(dirname(__DIR__))));
    $container->instance(SsrClient::class, new SsrClient($config, new CurlSsrTransport()));

    return $container;
}

test('module builds Inertia without flashed form state when nothing binds the reader', function () {
    $module = require dirname(__DIR__).'/module.php';
    $inertia = inertiaModuleContainer()->call($module['singletons'][Inertia::class]);

    expect(new ReflectionProperty(Inertia::class, 'formState')->getValue($inertia))->toBeNull();
});

test('module gives Inertia the bound flashed form state reader', function () {
    $module = require dirname(__DIR__).'/module.php';
    $container = inertiaModuleContainer();
    $formState = new FlashedErrors(['email' => ['Required.']]);
    $container->instance(FlashedFormStateInterface::class, $formState);

    $inertia = $container->call($module['singletons'][Inertia::class]);

    expect(new ReflectionProperty(Inertia::class, 'formState')->getValue($inertia))->toBe($formState);
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
        ->and($config['ssr']['url'])->toBe('http://localhost:13714');
});
