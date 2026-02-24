<?php

declare(strict_types=1);

namespace Marko\Health\Checks;

use Marko\Cache\Contracts\CacheInterface;
use Marko\Health\Contracts\HealthCheckInterface;
use Marko\Health\Value\HealthResult;
use Marko\Health\Value\HealthStatus;
use RuntimeException;
use Throwable;

readonly class CacheHealthCheck implements HealthCheckInterface
{
    private const TEST_KEY = 'marko_health_check';

    private const TEST_VALUE = 'ok';

    public function __construct(
        private CacheInterface $cache,
    ) {}

    public function getName(): string
    {
        return 'cache';
    }

    public function check(): HealthResult
    {
        $start = microtime(true);

        try {
            $this->cache->set(self::TEST_KEY, self::TEST_VALUE);
            $value = $this->cache->get(self::TEST_KEY);
            $this->cache->delete(self::TEST_KEY);

            if ($value !== self::TEST_VALUE) {
                throw new RuntimeException('Cache read/write verification failed');
            }

            $duration = microtime(true) - $start;

            return new HealthResult(
                name: $this->getName(),
                status: HealthStatus::Healthy,
                message: 'Cache read/write successful',
                metadata: [],
                duration: $duration,
            );
        } catch (Throwable $e) {
            $duration = microtime(true) - $start;

            return new HealthResult(
                name: $this->getName(),
                status: HealthStatus::Unhealthy,
                message: $e->getMessage(),
                metadata: [],
                duration: $duration,
            );
        }
    }
}
