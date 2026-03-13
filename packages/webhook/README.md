# marko/webhook

Send and receive webhooks with HMAC-SHA256 signature verification, automatic retry with exponential backoff, and delivery attempt tracking.

## Installation

```bash
composer require marko/webhook
```

## Quick Example

```php
use Marko\Webhook\Sending\WebhookDispatcher;
use Marko\Webhook\Value\WebhookPayload;

$payload = new WebhookPayload(
    url: 'https://example.com/webhooks',
    event: 'order.created',
    data: ['order_id' => 42, 'total' => '99.99'],
    secret: 'your-shared-secret',
);

$response = $webhookDispatcher->dispatch($payload);
```

## Documentation

Full usage, API reference, and examples: [marko/webhook](https://marko.build/docs/packages/webhook/)
