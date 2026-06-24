<?php

declare(strict_types=1);

use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Session\Config\SessionConfig;
use Marko\Session\Contracts\SessionInterface;
use Marko\Session\File\Handler\FileSessionHandler;
use Marko\Session\Middleware\SessionMiddleware;
use Marko\Session\Session;
use Marko\Testing\Fake\FakeConfigRepository;

it('binds SessionInterface to Session when the file session driver is installed', function (): void {
    $module = require dirname(__DIR__) . '/module.php';

    expect($module['singletons'])->toHaveKey(SessionInterface::class)
        ->and($module['singletons'][SessionInterface::class])->toBe(Session::class);
});

it('registers the session global middleware when the file session driver is installed', function (): void {
    $module = require dirname(__DIR__) . '/module.php';

    expect($module['globalMiddleware'])->toContain(SessionMiddleware::class);
});

it('starts a session and sets a session cookie end-to-end with the file driver installed', function (): void {
    $sessionPath = sys_get_temp_dir() . '/marko-e2e-test-' . bin2hex(random_bytes(8));
    mkdir($sessionPath, 0700, true);

    try {
        $config = new FakeConfigRepository([
            'session.driver' => 'file',
            'session.lifetime' => 120,
            'session.expire_on_close' => false,
            'session.path' => $sessionPath,
            'session.cookie.name' => 'MARKO_SESSION',
            'session.cookie.path' => '/',
            'session.cookie.domain' => '',
            'session.cookie.secure' => false,
            'session.cookie.httponly' => true,
            'session.cookie.samesite' => 'lax',
            'session.gc_probability' => 1,
            'session.gc_divisor' => 100,
        ]);

        $handler = new FileSessionHandler(new SessionConfig($config));
        $session = new Session($handler, new SessionConfig($config));
        $middleware = new SessionMiddleware($session);

        $request = new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
        ]);

        $response = $middleware->handle($request, function (Request $r) use ($session): Response {
            $session->set('user', 'alice');

            return new Response('OK');
        });

        expect($response->body())->toBe('OK')
            ->and($session->started)->toBeFalse() // saved and closed by middleware
            ->and(glob($sessionPath . '/sess_*'))->not->toBeEmpty();
    } finally {
        $files = glob($sessionPath . '/*') ?: [];
        foreach ($files as $file) {
            unlink($file);
        }
        rmdir($sessionPath);
    }
});

it('orders the session middleware after page-cache when the file driver and page-cache are both installed', function (): void {
    $module = require dirname(__DIR__) . '/module.php';

    expect($module['sequence'])->toHaveKey('after')
        ->and($module['sequence']['after'])->toContain('marko/page-cache');
});
