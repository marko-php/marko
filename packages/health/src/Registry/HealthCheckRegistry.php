<?php

declare(strict_types=1);

namespace Marko\Health\Registry;

use Marko\Health\Contracts\HealthCheckInterface;
use Marko\Health\Value\HealthResult;

class HealthCheckRegistry
{
    /** @var array<HealthCheckInterface> */
    private array $checks = [];

    public function register(
        HealthCheckInterface $check,
    ): void {
        $this->checks[] = $check;
    }

    /**
     * @return array<HealthCheckInterface>
     */
    public function all(): array
    {
        return $this->checks;
    }

    /**
     * Run all registered health checks.
     *
     * @return array<HealthResult>
     */
    public function run(): array
    {
        $results = [];

        foreach ($this->checks as $check) {
            $results[] = $check->check();
        }

        return $results;
    }
}
