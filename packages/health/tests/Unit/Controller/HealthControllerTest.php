<?php

declare(strict_types=1);

use Marko\Health\Contracts\HealthCheckInterface;
use Marko\Health\Controller\HealthController;
use Marko\Health\Registry\HealthCheckRegistry;
use Marko\Health\Value\HealthResult;
use Marko\Health\Value\HealthStatus;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;

it('aggregates all check results in HealthController', function () {
    $registry = new HealthCheckRegistry();

    $registry->register(new class () implements HealthCheckInterface
    {
        public function getName(): string
        {
            return 'database';
        }

        public function check(): HealthResult
        {
            return new HealthResult(
                name: 'database',
                status: HealthStatus::Healthy,
                message: 'Database OK',
                metadata: [],
                duration: 1.0,
            );
        }
    });

    $registry->register(new class () implements HealthCheckInterface
    {
        public function getName(): string
        {
            return 'cache';
        }

        public function check(): HealthResult
        {
            return new HealthResult(
                name: 'cache',
                status: HealthStatus::Healthy,
                message: 'Cache OK',
                metadata: [],
                duration: 0.5,
            );
        }
    });

    $controller = new HealthController($registry);
    $response = $controller->index();

    expect($response)->toBeInstanceOf(Response::class);

    $data = json_decode($response->body(), true);

    expect($data['checks'])->toHaveCount(2)
        ->and($data['checks'][0]['name'])->toBe('database')
        ->and($data['checks'][1]['name'])->toBe('cache');
});

it('returns 200 with healthy status when all checks pass', function () {
    $registry = new HealthCheckRegistry();

    $registry->register(new class () implements HealthCheckInterface
    {
        public function getName(): string
        {
            return 'database';
        }

        public function check(): HealthResult
        {
            return new HealthResult(
                name: 'database',
                status: HealthStatus::Healthy,
                message: 'Database OK',
                metadata: [],
                duration: 1.0,
            );
        }
    });

    $controller = new HealthController($registry);
    $response = $controller->index();

    expect($response->statusCode())->toBe(200);

    $data = json_decode($response->body(), true);

    expect($data['status'])->toBe('healthy');
});

it('returns 503 with unhealthy status when any critical check fails', function () {
    $registry = new HealthCheckRegistry();

    $registry->register(new class () implements HealthCheckInterface
    {
        public function getName(): string
        {
            return 'database';
        }

        public function check(): HealthResult
        {
            return new HealthResult(
                name: 'database',
                status: HealthStatus::Unhealthy,
                message: 'Connection refused',
                metadata: [],
                duration: 0.1,
            );
        }
    });

    $controller = new HealthController($registry);
    $response = $controller->index();

    expect($response->statusCode())->toBe(503);

    $data = json_decode($response->body(), true);

    expect($data['status'])->toBe('unhealthy');
});

it('exposes GET /health route via HealthController', function () {
    $reflection = new ReflectionMethod(HealthController::class, 'index');
    $attributes = $reflection->getAttributes(Get::class);

    expect($attributes)->toHaveCount(1);

    $route = $attributes[0]->newInstance();

    expect($route->path)->toBe('/health');
});
