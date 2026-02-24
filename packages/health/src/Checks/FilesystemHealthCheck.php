<?php

declare(strict_types=1);

namespace Marko\Health\Checks;

use Marko\Filesystem\Contracts\FilesystemInterface;
use Marko\Health\Contracts\HealthCheckInterface;
use Marko\Health\Value\HealthResult;
use Marko\Health\Value\HealthStatus;
use Throwable;

readonly class FilesystemHealthCheck implements HealthCheckInterface
{
    private const string TEST_PATH = '.health_check_temp';

    private const string TEST_CONTENTS = 'marko_health_check';

    public function __construct(
        private FilesystemInterface $filesystem,
    ) {}

    public function getName(): string
    {
        return 'filesystem';
    }

    public function check(): HealthResult
    {
        $start = microtime(true);

        try {
            $this->filesystem->write(self::TEST_PATH, self::TEST_CONTENTS);
            $this->filesystem->delete(self::TEST_PATH);

            $duration = microtime(true) - $start;

            return new HealthResult(
                name: $this->getName(),
                status: HealthStatus::Healthy,
                message: 'Filesystem write/delete successful',
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
