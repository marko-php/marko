<?php

declare(strict_types=1);

use Marko\Health\Value\HealthStatus;

it('defines HealthStatus enum cases with correct string values', function () {
    expect(HealthStatus::Healthy->value)->toBe('healthy')
        ->and(HealthStatus::Degraded->value)->toBe('degraded')
        ->and(HealthStatus::Unhealthy->value)->toBe('unhealthy');
});
