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

The path is fixed at `/health` by the route attribute; there is no config key for it. To serve the report somewhere else, replace the controller with a [Preference](/docs/concepts/preferences/) that overrides `index()` with its own route attribute:

```php title="app/ops/src/Controller/OpsHealthController.php"
use Marko\Core\Attributes\Preference;
use Marko\Health\Controller\HealthController;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

#[Preference(replaces: HealthController::class)]
readonly class OpsHealthController extends HealthController
{
    #[Get('/_ops/health')]
    public function index(
        Request $request,
    ): Response {
        return parent::index($request);
    }
}
```

### Protecting the Endpoint

The endpoint is public by default. Set the `HEALTH_SECRET` environment variable (read by the shipped `health.secret` config key) to require a shared secret:

```bash title=".env"
HEALTH_SECRET=your-secret
```

With a secret set, callers must send it in the `X-Health-Secret` header or the `?secret=` query parameter (for load balancers that cannot send custom headers). The value is compared with `hash_equals()`. A request without a matching secret gets a `404`, the same response as an unknown URL, and no check runs, so an anonymous caller cannot trigger the cache and filesystem writes the built-in checks perform. `null` or an empty string leaves the endpoint public; any other non-string value throws a `HealthException`.

```bash
curl -H 'X-Health-Secret: your-secret' https://example.com/health
```

### Failure Messages

The response is served to whoever can reach the endpoint, so check messages are generic (`Database connection failed`, `Cache read/write failed`, `Filesystem write/delete failed`). The underlying exception, which can name hosts, users, and paths, is kept on `HealthResult::$exception` and never serialized into the response. To log it, run the registry yourself and pass each `$result->exception` to your logger.

### Built-in Checks

Each built-in check needs a package that `marko/health` only suggests, so install the ones you use, plus a driver for each:

| Check | Needs |
|---|---|
| `DatabaseHealthCheck` | [`marko/database`](/docs/packages/database/) (`ConnectionInterface`) |
| `CacheHealthCheck` | [`marko/cache`](/docs/packages/cache/) (`CacheInterface`) |
| `FilesystemHealthCheck` | [`marko/filesystem`](/docs/packages/filesystem/) (`FilesystemInterface`) |

```bash
composer require marko/cache marko/cache-redis
```

Resolving a check whose package is missing throws a `BindingException` from the container that names the missing interface and the `composer require` command to fix it. Register the checks you want from your module's `boot` callback:

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
                message: 'Redis connection failed',
                metadata: [],
                duration: $duration,
                exception: $e,
            );
        }
    }
}
```

Keep `message` generic: it is served in the response. Pass the exception as `exception` instead; it stays on the result and is never serialized.

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
public ?Throwable $exception; // optional, defaults to null; never serialized

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
