<?php

declare(strict_types=1);

use function Amp\delay;

use Marko\Broadcasting\Amphp\AmphpBroadcastingConfig;

use Marko\Broadcasting\Amphp\Auth\AmphpSignature;
use Marko\Broadcasting\Amphp\Driver\AmphpBroadcaster;
use Marko\Broadcasting\Amphp\Server\AmphpSseServer;
use Marko\Broadcasting\Amphp\Tests\Support\SseTestClient;
use Marko\Clock\SystemClock;
use Marko\PubSub\PubSubConfig;
use Marko\PubSub\Redis\Driver\RedisPublisher;
use Marko\PubSub\Redis\Driver\RedisSubscriber;
use Marko\PubSub\Redis\RedisPubSubConnection;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeLogger;

/*
 * Runs against a real Redis server (the integration-services group). Skipped,
 * with the reason, unless REDIS_HOST is set and reachable; with
 * MARKO_INTEGRATION_REQUIRED set (CI) an unusable Redis is a failure instead.
 *
 *   docker compose -f tests/Integration/compose.yml up -d
 *   REDIS_HOST=127.0.0.1 composer test:integration
 */

pest()->group('integration-services');

/**
 * Null when Redis is reachable, otherwise why the live test is skipped.
 *
 * @throws RuntimeException When MARKO_INTEGRATION_REQUIRED is set and Redis is unusable
 */
function redisLiveSkipReason(): ?string
{
    $host = $_ENV['REDIS_HOST'] ?? getenv('REDIS_HOST');
    $reason = null;

    if (
        !is_string($host)
        || $host === ''
    ) {
        $reason = 'REDIS_HOST is not set. Start Redis with `docker compose -f tests/Integration/compose.yml up -d` and '
            . 'run with REDIS_HOST=127.0.0.1.';
    } else {
        $port = (int) ($_ENV['REDIS_PORT'] ?? getenv('REDIS_PORT') ?: 6379);
        $socket = @fsockopen($host, $port, $errorCode, $errorMessage, 0.5);

        if ($socket === false) {
            $reason = "Redis is not reachable at $host:$port ($errorMessage).";
        } else {
            fclose($socket);
        }
    }

    $required = $_ENV['MARKO_INTEGRATION_REQUIRED'] ?? getenv('MARKO_INTEGRATION_REQUIRED');

    if (
        $reason !== null
        && in_array(strtolower((string) $required), ['1', 'true', 'yes'], true)
    ) {
        throw new RuntimeException("MARKO_INTEGRATION_REQUIRED is set but Redis is unusable: $reason");
    }

    return $reason;
}

it('delivers events through pubsub-redis', function (): void {
    $reason = redisLiveSkipReason();

    if ($reason !== null) {
        $this->markTestSkipped($reason);
    }

    $connection = new RedisPubSubConnection(
        host: (string) ($_ENV['REDIS_HOST'] ?? getenv('REDIS_HOST')),
        port: (int) ($_ENV['REDIS_PORT'] ?? getenv('REDIS_PORT') ?: 6379),
    );
    $prefix = 'marko-test-' . bin2hex(random_bytes(4)) . ':';
    $pubSubConfig = new PubSubConfig(new FakeConfigRepository([
        'pubsub.driver' => 'redis',
        'pubsub.prefix' => $prefix,
    ]));
    $config = new AmphpBroadcastingConfig(host: '127.0.0.1', port: 0, logInterval: 0);
    $server = new AmphpSseServer(
        $config,
        new RedisSubscriber($connection, $pubSubConfig),
        new AmphpSignature($config, new SystemClock()),
        new FakeLogger(),
        new SystemClock(),
    );
    $server->start();

    try {
        $client = SseTestClient::get((int) $server->port(), '/stream?channels=shows.42');
        $client->waitFor(":ok\n\n");

        // SUBSCRIBE is sent asynchronously; a message published before Redis
        // registers it is dropped, so wait until Redis reports the subscriber.
        $redisChannel = $prefix . $config->channelPrefix . 'shows.42';
        $deadline = microtime(true) + 5.0;

        while ($connection->client()->execute('PUBSUB', 'NUMSUB', $redisChannel)[1] < 1) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException("Redis never registered a subscriber on '$redisChannel'.");
            }

            delay(0.01);
        }

        new AmphpBroadcaster(new RedisPublisher($connection, $pubSubConfig), $config, new SystemClock())
            ->broadcast('shows.42', 'seat.sold', ['seat' => 'A1'], 'evt-redis');

        expect($client->waitFor('id: evt-redis'))->toContain("event: seat.sold\ndata: {\"seat\":\"A1\"}");
    } finally {
        $server->stop(1.0);
    }
});

it('fails instead of skipping when MARKO_INTEGRATION_REQUIRED is set and redis is unusable', function (): void {
    $saved = [];

    foreach (['REDIS_HOST', 'MARKO_INTEGRATION_REQUIRED'] as $name) {
        $saved[$name] = ['env' => $_ENV[$name] ?? null, 'process' => getenv($name)];
    }

    $_ENV['REDIS_HOST'] = '';
    putenv('REDIS_HOST=');
    $_ENV['MARKO_INTEGRATION_REQUIRED'] = '1';
    putenv('MARKO_INTEGRATION_REQUIRED=1');

    try {
        expect(fn () => redisLiveSkipReason())
            ->toThrow(RuntimeException::class, 'MARKO_INTEGRATION_REQUIRED is set but Redis is unusable');
    } finally {
        foreach ($saved as $name => $values) {
            unset($_ENV[$name]);
            putenv($name);

            if ($values['env'] !== null) {
                $_ENV[$name] = $values['env'];
            }

            if ($values['process'] !== false) {
                putenv("$name={$values['process']}");
            }
        }
    }
});
