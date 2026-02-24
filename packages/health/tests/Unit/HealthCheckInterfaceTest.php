<?php

declare(strict_types=1);

use Marko\Health\Contracts\HealthCheckInterface;
use Marko\Health\Value\HealthResult;
use Marko\Health\Value\HealthStatus;

it('defines HealthCheckInterface with name and check methods returning HealthResult', function () {
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

    expect($check)->toBeInstanceOf(HealthCheckInterface::class)
        ->and($check->getName())->toBe('test-check')
        ->and($check->check())->toBeInstanceOf(HealthResult::class);
});
