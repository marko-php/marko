<?php

declare(strict_types=1);

use Marko\Health\Value\HealthResult;
use Marko\Health\Value\HealthStatus;

it('defines HealthResult value object properties', function () {
    $result = new HealthResult(
        name: 'database',
        status: HealthStatus::Healthy,
        message: 'Connection OK',
        metadata: ['connection_time_ms' => 5.2],
        duration: 5.2,
    );

    expect($result->name)->toBe('database')
        ->and($result->status)->toBe(HealthStatus::Healthy)
        ->and($result->message)->toBe('Connection OK')
        ->and($result->metadata)->toBe(['connection_time_ms' => 5.2])
        ->and($result->duration)->toBe(5.2);
});

it('returns isHealthy true when status is Healthy', function () {
    $result = new HealthResult(
        name: 'test',
        status: HealthStatus::Healthy,
        message: 'OK',
        metadata: [],
        duration: 1.0,
    );

    expect($result->isHealthy())->toBeTrue()
        ->and($result->isDegraded())->toBeFalse()
        ->and($result->isUnhealthy())->toBeFalse();
});

it('returns isDegraded true when status is Degraded', function () {
    $result = new HealthResult(
        name: 'test',
        status: HealthStatus::Degraded,
        message: 'Slow response',
        metadata: [],
        duration: 500.0,
    );

    expect($result->isDegraded())->toBeTrue()
        ->and($result->isHealthy())->toBeFalse()
        ->and($result->isUnhealthy())->toBeFalse();
});

it('returns isUnhealthy true when status is Unhealthy', function () {
    $result = new HealthResult(
        name: 'test',
        status: HealthStatus::Unhealthy,
        message: 'Connection failed',
        metadata: [],
        duration: 0.0,
    );

    expect($result->isUnhealthy())->toBeTrue()
        ->and($result->isHealthy())->toBeFalse()
        ->and($result->isDegraded())->toBeFalse();
});
