<?php

declare(strict_types=1);

use Marko\Health\Contracts\HealthCheckInterface;
use Marko\Health\Registry\HealthCheckRegistry;
use Marko\Health\Value\HealthResult;
use Marko\Health\Value\HealthStatus;

it('registers health checks in HealthCheckRegistry and retrieves them', function () {
    $registry = new HealthCheckRegistry();

    $check = new class () implements HealthCheckInterface
    {
        public function getName(): string
        {
            return 'test-check';
        }

        public function check(): HealthResult
        {
            return new HealthResult(
                name: 'test-check',
                status: HealthStatus::Healthy,
                message: 'All good',
                metadata: [],
                duration: 1.0,
            );
        }
    };

    $registry->register($check);

    expect($registry->all())->toHaveCount(1)
        ->and($registry->all()[0])->toBeInstanceOf(HealthCheckInterface::class);
});

it('runs all registered checks and returns HealthResult array', function () {
    $registry = new HealthCheckRegistry();

    $check = new class () implements HealthCheckInterface
    {
        public function getName(): string
        {
            return 'db-check';
        }

        public function check(): HealthResult
        {
            return new HealthResult(
                name: 'db-check',
                status: HealthStatus::Healthy,
                message: 'Database OK',
                metadata: ['query_time_ms' => 2.5],
                duration: 2.5,
            );
        }
    };

    $registry->register($check);
    $results = $registry->run();

    expect($results)->toHaveCount(1)
        ->and($results[0])->toBeInstanceOf(HealthResult::class)
        ->and($results[0]->name)->toBe('db-check')
        ->and($results[0]->status)->toBe(HealthStatus::Healthy);
});
