<?php

declare(strict_types=1);

use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Redis\Config\RedisCacheConfig;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Integration\Fixture\Entity\Author;
use Marko\Integration\Fixture\Event\BookPublished;
use Marko\Integration\Fixture\Job\RecordingJob;
use Marko\Integration\Fixture\Repository\AuthorRepository;
use Marko\Integration\Fixture\Repository\BookRepository;
use Marko\Queue\QueueInterface;
use Marko\Queue\Worker;
use Marko\Queue\WorkerInterface;
use Marko\Redis\RedisConnection;
use Marko\Redis\RedisConnectionFactoryInterface;
use Predis\Client;

/*
 * Cross-package behaviour that works on develop today, exercised through
 * the real module wiring against real Postgres and Redis.
 */

pest()->group('integration-services');

beforeEach(fn () => setUpIntegrationTest($this));

afterEach(fn () => tearDownIntegrationTest($this));

it('applies the fixture migrations with db:migrate', function (): void {
    // setUpIntegrationTest already migrated once; a fresh database proves the
    // command against an empty schema. Resetting drops the database with
    // FORCE, which kills the app's shared connection (#159), so boot a fresh
    // app on the empty database instead of reusing the old one.
    $this->app = bootIntegrationApp($this->project, migrate: false);

    $result = runIntegrationCommand($this->app, 'db:migrate', ['--no-generate']);
    $tables = array_column(
        $this->app->container->get(ConnectionInterface::class)->query(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name",
        ),
        'table_name',
    );

    expect($result['exitCode'])->toBe(0, $result['output'])
        ->and($result['output'])->toContain('Applied 6 schema migration(s).')
        ->and($tables)->toBe(['authors', 'books', 'failed_jobs', 'jobs', 'migrations', 'sessions']);
});

it('round-trips an entity through its repository on postgres', function (): void {
    $repository = $this->app->container->get(AuthorRepository::class);

    $author = new Author();
    $author->name = 'Ursula K. Le Guin';
    $repository->save($author);

    $found = $this->app->container->get(AuthorRepository::class)->find((int) $author->id);

    expect($author->id)->toBeInt()
        ->and($found)->toBeInstanceOf(Author::class)
        ->and($found?->name)->toBe('Ursula K. Le Guin');
});

it('runs the fixture seeder with db:seed', function (): void {
    // Seeders refuse to run in production, which is what an unset APP_ENV means.
    $result = withIntegrationAppEnv('testing', fn (): array => runIntegrationCommand($this->app, 'db:seed'));
    $container = $this->app->container;

    expect($result['exitCode'])->toBe(0, $result['output'])
        ->and($container->get(AuthorRepository::class)->findOneBy(['name' => 'Seeded Author']))->not->toBeNull()
        ->and($container->get(BookRepository::class)->findOneBy(['title' => 'Seeded Book']))->not->toBeNull();
});

it('stores and reads a value through the redis cache driver', function (): void {
    // Unique per test: Redis is shared by every parallel worker and by the
    // developer's own data, so the suite never flushes it.
    $key = 'integration.' . bin2hex(random_bytes(8));
    $cache = $this->app->container->get(CacheInterface::class);

    try {
        $cache->set($key, ['answer' => 42], 60);

        expect($this->app->container->get(CacheInterface::class)->get($key))->toBe(['answer' => 42]);
    } finally {
        $cache->delete($key);
    }
});

it('routes a request through the global session middleware and persists the session row', function (): void {
    $first = $this->app->router->handle(integrationRequest('GET', '/visits'));
    $sessionId = integrationCookieValue($first, 'marko_session');

    $second = $this->app->router->handle(
        integrationRequest('GET', '/visits', cookies: ['marko_session' => (string) $sessionId]),
    );
    $rows = $this->app->container->get(ConnectionInterface::class)->query(
        'SELECT id FROM sessions WHERE id = ?',
        [(string) $sessionId],
    );

    expect($first->body())->toBe('1')
        ->and($sessionId)->not->toBeNull()
        ->and($second->body())->toBe('2')
        ->and($rows)->toHaveCount(1);
});

it('processes a successful job with queue:work --once', function (): void {
    $marker = $this->project . '/storage/recording-job.txt';
    $queue = $this->app->container->get(QueueInterface::class);
    $queue->push(new RecordingJob($marker, 'processed by the worker'));

    expect(file_exists($marker))->toBeFalse();

    $result = runIntegrationCommand($this->app, 'queue:work', ['--once']);

    expect($result['exitCode'])->toBe(0, $result['output'])
        ->and(file_get_contents($marker))->toBe('processed by the worker')
        ->and($queue->size())->toBe(0);
});

it('pushes a job for an async observer instead of running it inline', function (): void {
    $marker = $this->project . '/storage/book-published.txt';
    $queue = $this->app->container->get(QueueInterface::class);

    $this->app->container->get(EventDispatcherInterface::class)->dispatch(new BookPublished($marker));

    expect(file_exists($marker))->toBeFalse()
        ->and($queue->size())->toBe(1);

    $result = runIntegrationCommand($this->app, 'queue:work', ['--once']);

    expect($result['exitCode'])->toBe(0, $result['output'])
        ->and(file_get_contents($marker))->toBe('observer ran')
        ->and($queue->size())->toBe(0);
})->issue(163);

it('resolves WorkerInterface from the shipped queue wiring', function (): void {
    // The fixture module no longer binds WorkerInterface itself.
    expect($this->app->container->get(WorkerInterface::class))->toBeInstanceOf(Worker::class);
})->issue(161);

it('connects cache-redis to the host, port and database of its named redis connection', function (): void {
    $env = getenv();
    $connection = $this->app->container->get(RedisConnectionFactoryInterface::class)->connection('cache');
    $cache = $this->app->container->get(CacheInterface::class);
    $prefix = $this->app->container->get(RedisCacheConfig::class)->prefix();
    $key = 'integration.' . bin2hex(random_bytes(8));

    // Fixture/config/cache-redis.php selects the 'cache' connection, which
    // Fixture/config/redis.php puts on database 2; probe it directly.
    $probe = new Client(array_filter([
        'host' => $env['REDIS_HOST'],
        'port' => (int) ($env['REDIS_PORT'] ?? 6379),
        'database' => 2,
        'password' => ($env['REDIS_PASSWORD'] ?? '') !== '' ? $env['REDIS_PASSWORD'] : null,
    ], fn (mixed $value): bool => $value !== null));

    try {
        $cache->set($key, 'stored', 60);

        expect($connection)->toBeInstanceOf(RedisConnection::class)
            ->and($connection->host)->toBe($env['REDIS_HOST'])
            ->and($connection->port)->toBe((int) ($env['REDIS_PORT'] ?? 6379))
            ->and($connection->database)->toBe(2)
            ->and($probe->exists($prefix . $key))->toBe(1);
    } finally {
        $cache->delete($key);
        $probe->disconnect();
    }
})->issue(166);

it('enforces #[Can] globally: 401 for a guest, 403 for a user without the ability', function (): void {
    $guest = $this->app->router->handle(integrationRequest('GET', '/admin'));

    $login = $this->app->router->handle(integrationRequest('GET', '/login'));
    $sessionId = (string) integrationCookieValue($login, 'marko_session');
    $denied = $this->app->router->handle(
        integrationRequest('GET', '/admin', cookies: ['marko_session' => $sessionId]),
    );

    expect($guest->statusCode())->toBe(401)
        ->and($login->body())->toBe('logged in')
        ->and($denied->statusCode())->toBe(403)
        ->and($denied->body())->not->toContain('admin area');
})->issue(167);

it('returns 403 when a controller calls Gate::authorize() for a denied ability', function (): void {
    $login = $this->app->router->handle(integrationRequest('GET', '/login'));
    $sessionId = (string) integrationCookieValue($login, 'marko_session');

    $response = $this->app->router->handle(integrationRequest(
        'GET',
        '/admin/report',
        cookies: ['marko_session' => $sessionId],
        server: ['HTTP_ACCEPT' => 'application/json'],
    ));

    expect($response->statusCode())->toBe(403)
        ->and(json_decode($response->body(), true))->toBe(['message' => 'Forbidden.'])
        ->and($response->body())->not->toContain('view-admin')
        ->not->toContain('admin report');
})->issue(222);

it('answers an OPTIONS preflight through the CORS middleware', function (): void {
    $response = $this->app->router->handle(integrationRequest('OPTIONS', '/health', server: [
        'HTTP_ORIGIN' => 'https://app.example.test',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
    ]));

    expect($response->statusCode())->toBe(204)
        ->and($response->headers()['Access-Control-Allow-Origin'])->toBe('https://app.example.test')
        ->and($response->headers()['Access-Control-Allow-Methods'])->toBe('GET, POST')
        ->and($response->headers()['Access-Control-Max-Age'])->toBe('600');
})->issue(171);

it('answers a wrong method with 405 and an Allow header', function (): void {
    $response = $this->app->router->handle(integrationRequest('POST', '/health', server: [
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_ORIGIN' => 'https://app.example.test',
    ]));

    expect($response->statusCode())->toBe(405)
        ->and($response->headers()['Allow'])->toBe('GET, HEAD, OPTIONS')
        ->and($response->headers()['Access-Control-Allow-Origin'])->toBe('https://app.example.test')
        ->and(json_decode($response->body(), true))->toBe(['message' => 'Method Not Allowed']);
})->issue(171);

it('answers HEAD with the GET headers and no body', function (): void {
    $get = $this->app->router->handle(integrationRequest('GET', '/health'));
    $head = $this->app->router->handle(integrationRequest('HEAD', '/health'));

    expect($head->statusCode())->toBe($get->statusCode())
        ->and($head->body())->toBe('')
        ->and($get->body())->not->toBe('');
})->issue(171);
