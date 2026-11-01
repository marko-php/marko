<?php

declare(strict_types=1);

use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Redis\Driver\RedisCacheDriver;
use Marko\Core\Plugin\PluginInterceptedInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\PgSql\Connection\PgSqlConnection;
use Marko\Queue\Database\DatabaseQueue;
use Marko\Queue\QueueInterface;
use Marko\Session\Contracts\SessionHandlerInterface;
use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Database\Handler\DatabaseSessionHandler;
use Marko\Session\Session;

pest()->group('integration-services');

beforeEach(fn () => setUpIntegrationTest($this));

afterEach(fn () => tearDownIntegrationTest($this));

it('boots the fixture application with every installed module', function (): void {
    $names = array_map(fn ($module): string => $module->name, $this->app->modules);

    foreach (INTEGRATION_MODULES as $module) {
        expect($names)->toContain("marko/$module");
    }

    expect($names)->toContain('app/integration');
});

it('resolves the real driver bindings from module wiring', function (): void {
    $container = $this->app->container;
    $queue = $container->get(QueueInterface::class);

    // marko/queue's push plugins (RetryDeadlinePlugin) wrap every queue driver in an interceptor
    expect($queue)->toBeInstanceOf(PluginInterceptedInterface::class)
        ->and($queue->getPluginTarget())->toBeInstanceOf(DatabaseQueue::class);

    expect($container->get(ConnectionInterface::class))->toBeInstanceOf(PgSqlConnection::class)
        ->and($container->get(CacheInterface::class))->toBeInstanceOf(RedisCacheDriver::class)
        ->and($container->get(SessionInterface::class))->toBeInstanceOf(Session::class)
        ->and($container->get(SessionHandlerInterface::class))->toBeInstanceOf(DatabaseSessionHandler::class);
});

it('discovers the fixture routes', function (): void {
    $response = $this->app->router->handle(integrationRequest('GET', '/health'));

    expect($response->statusCode())->toBe(200)
        ->and($response->body())->toBe('ok');
});
