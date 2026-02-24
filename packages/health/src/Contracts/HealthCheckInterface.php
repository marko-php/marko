<?php

declare(strict_types=1);

namespace Marko\Health\Contracts;

use Marko\Health\Value\HealthResult;

interface HealthCheckInterface
{
    /**
     * Get the name of this health check.
     */
    public function getName(): string;

    /**
     * Execute the health check and return a result.
     */
    public function check(): HealthResult;
}
