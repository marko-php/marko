<?php

declare(strict_types=1);

use Marko\Core\Application;
use Marko\Core\Container\Container;
use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Exceptions\NoDriverException;
use Marko\Session\Middleware\SessionMiddleware;

it('boots an application that has marko/session installed but no session driver without throwing', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-session-test-' . bin2hex(random_bytes(8));
    $vendorDir = $baseDir . '/vendor';

    // Create a minimal marko/session module directory (passive — no driver)
    $sessionPath = $vendorDir . '/marko/session';
    mkdir($sessionPath, 0755, true);

    file_put_contents($sessionPath . '/composer.json', json_encode([
        'name' => 'marko/session',
        'version' => '1.0.0',
        'extra' => ['marko' => ['module' => true]],
    ], JSON_PRETTY_PRINT));

    // Copy the real (now passive) module.php
    copy(dirname(__DIR__) . '/module.php', $sessionPath . '/module.php');

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    // Should not throw — a passive session package with no driver is inert
    $app->initialize();

    expect($app)->toBeInstanceOf(Application::class);

    // Clean up
    array_map('unlink', glob($sessionPath . '/*'));
    rmdir($sessionPath);
    rmdir($vendorDir . '/marko');
    rmdir($vendorDir);
    rmdir($baseDir);
});

it('serves a stateless request with marko/session installed and no driver without a 500', function (): void {
    $module = require dirname(__DIR__) . '/module.php';

    // A passive module has no globalMiddleware — so SessionMiddleware never runs on any request
    $globalMiddleware = $module['globalMiddleware'] ?? [];

    expect($globalMiddleware)->not->toContain(SessionMiddleware::class);
});

it('throws a loud BindingException when SessionInterface is resolved with no driver installed', function (): void {
    $container = new Container();

    // No driver bound — resolving SessionInterface must throw loudly.
    // The Container dispatches to NoDriverException (extends MarkoException, implements
    // ContainerExceptionInterface) which is a more helpful "loud" error with driver install instructions.
    expect(fn () => $container->get(SessionInterface::class))
        ->toThrow(NoDriverException::class);
});
