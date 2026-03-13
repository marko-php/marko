# marko/log

Logging contracts and formatters -- define how your application logs messages without coupling to a storage backend.

## Installation

```bash
composer require marko/log
```

Note: You also need an implementation package such as `marko/log-file`.

## Quick Example

```php
use Marko\Log\Contracts\LoggerInterface;

class OrderService
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function placeOrder(int $orderId): void
    {
        $this->logger->info('Order placed', ['order_id' => $orderId]);
    }
}
```

## Documentation

Full usage, API reference, and examples: [marko/log](https://marko.build/docs/packages/log/)
