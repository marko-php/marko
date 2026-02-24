# Marko Webhook

Send and receive webhooks with HMAC-SHA256 signature verification, automatic retry with exponential backoff, and delivery attempt tracking.

## Overview

Outgoing webhooks are signed with a shared secret and delivered over HTTP. Incoming webhooks are verified against the same signature before the payload is parsed. Failed deliveries are automatically retried via the queue with exponential backoff. Every delivery attempt—success or failure—is recorded to the `webhook_attempts` table.

## Installation

```bash
composer require marko/webhook
```

Requires `marko/http` for the HTTP client and `marko/queue` for async dispatch.

## Usage

### Sending Webhooks

Build a `WebhookPayload` and call `WebhookDispatcher::dispatch()` to send synchronously:

```php
use Marko\Webhook\Sending\WebhookDispatcher;
use Marko\Webhook\Value\WebhookPayload;

public function __construct(
    private readonly WebhookDispatcher $dispatcher,
) {}

public function notifySubscriber(): void
{
    $payload = new WebhookPayload(
        url: 'https://example.com/webhooks',
        event: 'order.created',
        data: ['order_id' => 42, 'total' => '99.99'],
        secret: 'your-shared-secret',
    );

    $response = $this->dispatcher->dispatch($payload);

    if (!$response->successful) {
        // handle failure
    }
}
```

The dispatcher automatically signs the request body and sends it as an `X-Webhook-Signature: sha256={hash}` header.

### Sending Asynchronously with Retry

Push a `DispatchWebhookJob` onto the queue to send in the background with automatic retry on failure:

```php
use Marko\Queue\QueueInterface;
use Marko\Webhook\Jobs\DispatchWebhookJob;
use Marko\Webhook\Value\WebhookPayload;

public function __construct(
    private readonly QueueInterface $queue,
) {}

public function scheduleWebhook(): void
{
    $payload = new WebhookPayload(
        url: 'https://example.com/webhooks',
        event: 'order.shipped',
        data: ['order_id' => 42, 'tracking' => 'ABC123'],
        secret: 'your-shared-secret',
    );

    $this->queue->push(new DispatchWebhookJob($payload));
}
```

Failed deliveries retry up to `max_retries` times with delays calculated as `retry_delay * 2^attempt` seconds.

### Receiving Webhooks

Use `WebhookReceiver::receive()` in a controller to verify the signature and parse the payload. An `InvalidSignatureException` is thrown if the signature does not match:

```php
use Marko\Routing\Http\Request;
use Marko\Webhook\Exceptions\InvalidSignatureException;
use Marko\Webhook\Receiving\WebhookReceiver;

public function __construct(
    private readonly WebhookReceiver $receiver,
) {}

public function handle(
    Request $request,
): void {
    try {
        $data = $this->receiver->receive(
            request: $request,
            secret: 'your-shared-secret',
        );

        $event = $data['event'];
        $payload = $data['data'];

        // process event...
    } catch (InvalidSignatureException) {
        // reject the request
    }
}
```

### Using the WebhookEndpoint Attribute

Mark a controller method with `#[WebhookEndpoint]` to declare its path and secret inline:

```php
use Marko\Webhook\Attributes\WebhookEndpoint;

class StripeWebhookController
{
    #[WebhookEndpoint(path: '/webhooks/stripe', secret: 'whsec_...')]
    public function handle(
        Request $request,
    ): void {
        // $request is already routed here; verify with WebhookReceiver
    }
}
```

### Retry Configuration

Override defaults in `config/webhook.php`:

```php
return [
    'timeout'     => 30,   // seconds before the HTTP request times out
    'max_retries' => 3,    // maximum delivery attempts (including the first)
    'retry_delay' => 60,   // base delay in seconds; multiplied exponentially per attempt
];
```

With the defaults, a job that fails on every attempt retries at 120 s, 240 s, and 480 s.

### Delivery Tracking

Every attempt is saved to the `webhook_attempts` table via `WebhookDeliveryService`. Successful attempts store the HTTP status code and response body. Failed attempts store the error message. Use `WebhookAttemptRepositoryInterface` to query the records:

```php
use Marko\Webhook\Contracts\WebhookAttemptRepositoryInterface;

public function __construct(
    private readonly WebhookAttemptRepositoryInterface $attempts,
) {}
```

## API Reference

### WebhookPayload

```php
public function __construct(string $url, string $event, array $data, string $secret);
```

### WebhookDispatcher

```php
public function dispatch(WebhookPayload $payload): WebhookResponse;
```

### WebhookResponse

```php
public function __construct(int $statusCode, string $body, bool $successful);
```

### WebhookReceiver

```php
// @throws InvalidSignatureException
public function receive(Request $request, string $secret): array;
```

### WebhookVerifier

```php
public function verify(string $body, string $signature, string $secret): bool;
```

### WebhookSignature

```php
public static function sign(string $payload, string $secret): string;  // returns "sha256={hash}"
```

### DispatchWebhookJob

```php
public function __construct(WebhookPayload $payload, /* injected dependencies */);
public function handle(): void;
```

### WebhookDeliveryService

```php
public function recordSuccess(WebhookPayload $payload, WebhookResponse $response, int $attempt): void;
public function recordFailure(WebhookPayload $payload, string $error, int $attempt): void;
```

### WebhookAttempt (Entity)

| Column          | Type    | Description                          |
|-----------------|---------|--------------------------------------|
| `id`            | int     | Auto-increment primary key           |
| `webhook_url`   | string  | Destination URL                      |
| `event`         | string  | Event name                           |
| `attempt_number`| int     | Which attempt this record covers     |
| `status_code`   | int     | HTTP status code (success only)      |
| `response_body` | string  | Response body (success only)         |
| `error_message` | string  | Error message (failure only)         |
| `attempted_at`  | string  | Timestamp in `Y-m-d H:i:s` format   |

### WebhookConfig

```php
public int $timeout;      // from webhook.timeout
public int $maxRetries;   // from webhook.max_retries
public int $retryDelay;   // from webhook.retry_delay
```

### Interfaces

```php
interface WebhookDispatcherInterface {
    public function dispatch(WebhookPayload $payload): WebhookResponse;
}

interface WebhookReceiverInterface {
    public function receive(Request $request, string $secret): array;
}

interface WebhookAttemptRepositoryInterface {
    public function save(WebhookAttempt $attempt): WebhookAttempt;
}
```
