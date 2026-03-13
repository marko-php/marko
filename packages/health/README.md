# marko/health

Production health monitoring --- exposes a `/health` endpoint with configurable checks for your load balancer and monitoring tools.

## Installation

```bash
composer require marko/health
```

## Quick Example

```php
use Marko\Health\Contracts\HealthCheckInterface;
use Marko\Health\Value\HealthResult;
use Marko\Health\Value\HealthStatus;

class RedisHealthCheck implements HealthCheckInterface
{
    public function getName(): string
    {
        return 'redis';
    }

    public function check(): HealthResult
    {
        return new HealthResult(
            name: $this->getName(),
            status: HealthStatus::Healthy,
            message: 'Redis connection successful',
            metadata: [],
            duration: 0.001,
        );
    }
}
```

## Documentation

Full usage, API reference, and examples: [marko/health](https://marko.build/docs/packages/health/)
