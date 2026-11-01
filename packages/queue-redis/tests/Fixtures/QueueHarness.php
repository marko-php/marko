<?php

declare(strict_types=1);

namespace Marko\Queue\Redis\Tests\Fixtures;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\QueueInterface;
use Marko\Redis\RedisConnectionFactoryInterface;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeEventDispatcher;
use Predis\ClientInterface;
use Psr\Clock\ClockInterface;

/**
 * A container wired the way an app with only marko/queue and marko/queue-redis is: the two modules' bindings,
 * the live integration Redis connection, a fake clock and a key prefix unique to this harness, so parallel
 * test processes never see each other's keys.
 */
class QueueHarness
{
    public const string ENCRYPTION_KEY = 'queue-redis-integration-key';

    public readonly Container $container;

    public readonly FakeClock $clock;

    public readonly string $prefix;

    /**
     * @param array<string, mixed> $config Overrides for the queue.* and queue-redis.* config
     */
    public function __construct(
        array $config = [],
        ?string $prefix = null,
    ) {
        $this->prefix = $prefix ?? 'marko-queue-test-' . bin2hex(random_bytes(6)) . ':';
        $this->clock = new FakeClock('2026-10-31 12:00:00');
        $this->container = new Container();
        $this->container->instance(ContainerInterface::class, $this->container);
        $this->container->instance(EventDispatcherInterface::class, new FakeEventDispatcher());
        $this->container->instance(ClockInterface::class, $this->clock);
        $this->container->instance(RedisConnectionFactoryInterface::class, IntegrationRedis::connectionFactory());
        $this->container->instance(ConfigRepositoryInterface::class, new FakeConfigRepository([
            'encryption.key' => self::ENCRYPTION_KEY,
            'queue.driver' => 'redis',
            'queue.connection' => 'default',
            'queue.queue' => 'default',
            'queue.retry_after' => 90,
            'queue.timeout' => 0,
            'queue.max_attempts' => 3,
            'queue-redis.connection' => IntegrationRedis::CONNECTION,
            'queue-redis.prefix' => $this->prefix,
            'queue-redis.block_for' => 0,
            ...$config,
        ]));

        $packages = dirname(__DIR__, 3);

        foreach (['queue', 'queue-redis'] as $package) {
            $module = require "$packages/$package/module.php";

            foreach ($module['bindings'] as $interface => $implementation) {
                $this->container->bind($interface, $implementation);
            }
        }
    }

    public function queue(): QueueInterface
    {
        return $this->container->get(QueueInterface::class);
    }

    public function failedJobs(): FailedJobRepositoryInterface
    {
        return $this->container->get(FailedJobRepositoryInterface::class);
    }

    /**
     * A raw client on the integration connection, for inspecting the keys the driver wrote.
     */
    public function client(): ClientInterface
    {
        return IntegrationRedis::connectionFactory()->connection(IntegrationRedis::CONNECTION)->client();
    }

    /**
     * Delete every key under this harness's prefix.
     */
    public function flush(): void
    {
        $client = $this->client();
        $cursor = '0';

        do {
            [$cursor, $keys] = $client->scan($cursor, ['MATCH' => $this->prefix . '*', 'COUNT' => 1000]);

            if ($keys !== []) {
                $client->del($keys);
            }
        } while ($cursor !== '0');
    }
}
