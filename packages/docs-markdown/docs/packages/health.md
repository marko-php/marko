---
title: marko/health
description: Production health monitoring --- exposes a /health endpoint with configurable checks for your load balancer and monitoring tools.
---

Production health monitoring --- exposes a `/health` endpoint with configurable checks for your load balancer and monitoring tools. The package registers a `GET /health` route that runs a set of checks and returns a JSON report. Each check reports healthy, degraded, or unhealthy status along with a message and execution time. The overall response uses HTTP 200 for healthy or degraded and 503 for unhealthy, so load balancers can act on it without parsing JSON.

Three built-in checks are provided: database connectivity, cache read/write, and filesystem write/delete. Add your own by implementing `HealthCheckInterface` and registering it in your module.

## Installation

```bash
composer require marko/health
```

## Usage

### Endpoint

Once installed, `GET /health` is available automatically. The route is registered via the `#[Get('/health')]` attribute on `HealthController`. Example response:

```json
{
    "status": "healthy",
    "checks": [
        {
            "name": "database",
            "status": "healthy",
            "message": "Database connection successful",
            "duration": 0.0023
        },
        {
            "name": "cache",
            "status": "healthy",
            "message": "Cache read/write successful",
            "duration": 0.0011
        },
        {
            "name": "filesystem",
            "status": "healthy",
            "message": "Filesystem write/delete successful",
            "duration": 0.0008
        }
    ]
}
```

Status values: `healthy`, `degraded`, `unhealthy`. HTTP 200 for healthy/degraded, 503 for unhealthy.

### Built-in Checks

Register built-in checks in your `module.php` bindings. Each check requires its corresponding interface:

```php
use Marko\Health\Checks\CacheHealthCheck;
use Marko\Health\Checks\DatabaseHealthCheck;
use Marko\Health\Checks\FilesystemHealthCheck;
use Marko\Health\Registry\HealthCheckRegistry;

// In your module.php boot or a service provider:
$registry = $container->get(HealthCheckRegistry::class);
$registry->register($container->get(DatabaseHealthCheck::class));
$registry->register($container->get(CacheHealthCheck::class));
$registry->register($container->get(FilesystemHealthCheck::class));
```

### Custom Health Checks

Implement `HealthCheckInterface` to add your own checks:

```php
use Marko\Health\Contracts\HealthCheckInterface;
use Marko\Health\Value\HealthResult;
use Marko\Health\Value\HealthStatus;

readonly class RedisHealthCheck implements HealthCheckInterface
{
    public function __construct(
        private RedisClient $redisClient,
    ) {}

    public function getName(): string
    {
        return 'redis';
    }

    public function check(): HealthResult
    {
        $start = microtime(true);

        try {
            $this->redisClient->ping();
            $duration = microtime(true) - $start;

            return new HealthResult(
                name: $this->getName(),
                status: HealthStatus::Healthy,
                message: 'Redis connection successful',
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
```

Register it in your `module.php`:

```php title="module.php"
use Marko\Health\Registry\HealthCheckRegistry;

return [
    'bindings' => [
        HealthCheckRegistry::class => HealthCheckRegistry::class,
    ],
    'boot' => function (HealthCheckRegistry $healthCheckRegistry, RedisHealthCheck $redisHealthCheck): void {
        $healthCheckRegistry->register($redisHealthCheck);
    },
];
```

### Status Values

| Status | Meaning | HTTP Code |
|--------|---------|-----------|
| `healthy` | All checks passed | 200 |
| `degraded` | At least one check degraded, none unhealthy | 200 |
| `unhealthy` | At least one check failed | 503 |

The overall status is aggregated from individual check results --- if any check is unhealthy, the overall status is unhealthy. If any check is degraded but none are unhealthy, the overall status is degraded. Otherwise, the overall status is healthy.

## API Reference

### HealthCheckInterface

```php
use Marko\Health\Contracts\HealthCheckInterface;

public function getName(): string;
public function check(): HealthResult;
```

### HealthResult

A `readonly` value object representing the outcome of a single health check:

```php
use Marko\Health\Value\HealthResult;

public string $name;
public HealthStatus $status;
public string $message;
public array $metadata;
public float $duration;

public function isHealthy(): bool;
public function isDegraded(): bool;
public function isUnhealthy(): bool;
```

### HealthCheckRegistry

```php
use Marko\Health\Registry\HealthCheckRegistry;

public function register(HealthCheckInterface $check): void;
public function all(): array;   // Returns array<HealthCheckInterface>
public function run(): array;   // Returns array<HealthResult>
```

### HealthStatus (enum)

A backed string enum representing the three possible states:

```php
use Marko\Health\Value\HealthStatus;

HealthStatus::Healthy   // 'healthy'
HealthStatus::Degraded  // 'degraded'
HealthStatus::Unhealthy // 'unhealthy'
```
