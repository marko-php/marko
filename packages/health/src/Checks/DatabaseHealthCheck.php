<?php

declare(strict_types=1);

namespace Marko\Health\Checks;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Health\Contracts\HealthCheckInterface;
use Marko\Health\Value\HealthResult;
use Marko\Health\Value\HealthStatus;
use Throwable;

readonly class DatabaseHealthCheck implements HealthCheckInterface
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function getName(): string
    {
        return 'database';
    }

    public function check(): HealthResult
    {
        $start = microtime(true);

        try {
            $this->connection->query('SELECT 1');
            $duration = microtime(true) - $start;

            return new HealthResult(
                name: $this->getName(),
                status: HealthStatus::Healthy,
                message: 'Database connection successful',
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
