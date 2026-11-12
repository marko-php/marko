---
title: marko/webhook
description: Send and receive webhooks with HMAC-SHA256 signature verification, automatic retry with exponential backoff, and delivery attempt tracking.
---

Send and receive webhooks with HMAC-SHA256 signature verification, automatic retry with exponential backoff, and delivery attempt tracking. Outgoing webhooks are signed with a shared secret and delivered over HTTP. Incoming webhooks are verified against the same signature before the payload is parsed, either in a controller or automatically for actions marked `#[WebhookEndpoint]`. Failed deliveries are automatically retried via the queue with exponential backoff, with the signing secret stored encrypted. Every delivery attempt --- success or failure --- is recorded to the `webhook_attempts` table.

## Installation

```bash
composer require marko/webhook
```

Requires [marko/http](/docs/packages/http/) for the HTTP client, [marko/queue](/docs/packages/queue/) for async dispatch, and [marko/encryption](/docs/packages/encryption/) to encrypt signing secrets in queued jobs. To queue webhooks, also install an encryption driver such as `marko/encryption-openssl` and set `encryption.key`. To reject replayed deliveries, install [marko/cache](/docs/packages/cache/) with a driver shared by all your web servers and turn on `replay_protection`.

## Configuration

Override defaults in your config file:

```php title="config/webhook.php"
return [
    'timeout'             => 30,  // seconds before an outgoing webhook request is abandoned; must be > 0
    'max_retries'         => 3,   // maximum delivery attempts (including the first)
    'retry_delay'         => 60,  // base delay in seconds; multiplied exponentially per attempt
    'timestamp_tolerance' => 300, // seconds a webhook timestamp may differ from server time
    'allow_http'          => false, // allow plain http:// webhook URLs (https only by default)
    'max_body_bytes'      => 1048576, // largest incoming webhook body (1 MiB); must be > 0
    'replay_protection'   => false, // reject an X-Webhook-Id already received (needs marko/cache)
];
```

With the defaults, a job that fails on every attempt retries at 120 s, 240 s, and 480 s.

`WebhookDispatcher` passes `timeout` to the HTTP client on every delivery, so a receiver that accepts the connection and never answers fails the attempt after that many seconds instead of blocking the queue worker. The timed-out attempt is recorded as a failure and retried like any other transport error. `timeout` must be a positive integer: `0` would mean "wait forever" to the HTTP client, so `WebhookConfig` throws a `ConfigException` naming `webhook.timeout` for `0` or a negative value. That check runs wherever `WebhookConfig` is resolved, so a bad value fails loudly both when sending and when receiving webhooks. A missing `timeout` throws `ConfigNotFoundException`.

The `timestamp_tolerance` limits how long a captured request can be replayed. Requests whose `X-Webhook-Timestamp` header is more than this many seconds in the past or future are rejected. Within that window, the timestamp alone does not stop a replay; turn on `replay_protection` for that (see [Replay protection](#replay-protection)).

`max_body_bytes` caps the size of an incoming webhook body. `WebhookReceiver` checks it before computing the HMAC or decoding JSON, so an oversized request costs no hashing or parsing.

`WebhookConfig` also validates the other numeric keys: a negative `max_retries`, a negative `retry_delay`, or a `timestamp_tolerance` or `max_body_bytes` of `0` or less throws a `ConfigException` naming the key.

`allow_http` is read by the [webhook URL policy](#outgoing-url-policy). Leave it `false` in production so payloads and signatures are never sent in clear text; set it to `true` only for local development against a plain-HTTP receiver.

### Shared secrets

The shared secret must be at least `WebhookSecret::MIN_LENGTH` (16) bytes. `WebhookSignature::sign()`, `WebhookVerifier::verify()` and `WebhookReceiver::receive()` throw an `InvalidWebhookSecretException` for an empty or shorter secret instead of signing or verifying with it, because an HMAC keyed with an empty secret can be computed by anyone. This catches the common case of a secret read from an environment variable that is not set. Generate secrets with something like `bin2hex(random_bytes(32))`.

## Usage

### Sending Webhooks

Build a `WebhookPayload` and call `WebhookDispatcher::dispatch()` to send synchronously:

```php
use Marko\Webhook\Sending\WebhookDispatcher;
use Marko\Webhook\Value\WebhookPayload;

public function __construct(
    private readonly WebhookDispatcher $webhookDispatcher,
) {}

public function notifySubscriber(): void
{
    $payload = new WebhookPayload(
        url: 'https://example.com/webhooks',
        event: 'order.created',
        data: ['order_id' => 42, 'total' => '99.99'],
        secret: 'your-shared-secret',
    );

    $response = $this->webhookDispatcher->dispatch($payload);

    if (!$response->successful) {
        // The receiver answered with a non-2xx status:
        // $response->statusCode and $response->body say why.
    }
}
```

`dispatch()` returns a `WebhookResponse` for every HTTP response, 4xx and 5xx included, so check `$response->successful` (true only for 2xx). It throws when the receiver cannot be reached at all (`ConnectionException`/`HttpException` from [`marko/http`](/docs/packages/http/)), and before sending anything when the URL is [rejected](#outgoing-url-policy) or `data` cannot be encoded as JSON (for example, a string that is not valid UTF-8 throws `InvalidWebhookPayloadException`). `$response->isRetryable()` tells you whether sending again could help: true for `408`, `429` and any `5xx`.

Every request carries three headers:

| Header | Value |
|---|---|
| `X-Webhook-Id` | The payload's delivery ID: a random UUID v4 unless you pass `id:` to `WebhookPayload`. Queued retries of the same delivery keep the same ID. |
| `X-Webhook-Timestamp` | The Unix timestamp of this attempt. |
| `X-Webhook-Signature` | `sha256={hash}`, the HMAC-SHA256 of `"{id}.{timestamp}.{body}"` keyed with the shared secret. |

Because the ID and the timestamp are signed, neither can be changed without invalidating the signature. The receiver verifies the timestamp freshness and the signature before accepting the payload. Receivers that are not built with Marko should deduplicate on `X-Webhook-Id`, since a retry after a timeout can deliver a webhook the receiver already processed.

### Outgoing URL Policy

Webhook URLs usually come from your users, so a URL like `http://169.254.169.254/latest/meta-data/` would otherwise turn every delivery into a request against your own network, with the response saved to the delivery log. Before every request, `WebhookDispatcher` passes the URL to `WebhookUrlPolicyInterface::validate()`, and it never follows redirects, so a receiver cannot answer with a `302` to an internal address. A redirect response is returned like any other non-2xx status.

The default `WebhookUrlPolicy` throws `UnsafeWebhookUrlException` unless:

- the scheme is `https` (or `http` when `allow_http` is `true`),
- the host is a hostname or a canonical IP address (numeric shorthand such as `2130706433`, `0x7f.1` or `127.1`, and URLs containing backslashes or whitespace, are rejected because HTTP clients may read them differently),
- the host resolves, and **every** address it resolves to is public.

These ranges are rejected, including when an IPv4 address is embedded in IPv6 (IPv4-mapped `::ffff:0:0/96`, NAT64 `64:ff9b::/96`, 6to4 `2002::/16`):

| IPv4 | IPv6 |
|---|---|
| `0.0.0.0/8`, `127.0.0.0/8` (loopback) | `::1`, `::/96` (unspecified, IPv4-compatible) |
| `10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16` (RFC 1918) | `fc00::/7` (unique local) |
| `169.254.0.0/16` (link-local, cloud metadata) | `fe80::/10` (link-local), `fec0::/10` (site-local) |
| `100.64.0.0/10` (carrier-grade NAT) | `ff00::/8` (multicast) |
| `192.0.0.0/24`, `198.18.0.0/15`, `224.0.0.0/4`, `240.0.0.0/4` | |

Validate the URL when a user registers it as well, so an unsafe destination is rejected with a helpful message instead of failing at delivery time:

```php
use Marko\Webhook\Contracts\WebhookUrlPolicyInterface;
use Marko\Webhook\Exceptions\UnsafeWebhookUrlException;

public function __construct(
    private readonly WebhookUrlPolicyInterface $webhookUrlPolicy,
) {}

public function register(string $url): void
{
    try {
        $this->webhookUrlPolicy->validate($url);
    } catch (UnsafeWebhookUrlException $e) {
        // show $e->getMessage() to the user
        return;
    }

    // store the webhook URL...
}
```

Hostnames are resolved through `HostResolverInterface` (bound to `DnsHostResolver`, which uses the system resolver). Bind your own resolver to use a different DNS source, or a fixed map in tests. To allow destinations the default policy rejects, for example a receiver on your private network, bind your own `WebhookUrlPolicyInterface` implementation.

`validate()` returns the address the policy approved (the first one the host resolved to), and `WebhookDispatcher` pins the connection to it with the `marko/http` [`resolve_to`](/docs/packages/http/#pinning-the-connection-to-an-ip) option. The HTTP client never resolves the host a second time, so a hostname whose DNS answer flips to an internal address after the check (DNS rebinding) cannot redirect the delivery. The `Host` header and TLS certificate check still use the hostname. A custom `WebhookUrlPolicyInterface` implementation must return the IP address the request should connect to.

:::note[HTTP driver support]
Pinning needs an HTTP client driver that supports `resolve_to`, such as [`marko/http-guzzle`](/docs/packages/http-guzzle/#pinning-to-an-ip) with the PHP `curl` extension. A driver that cannot pin throws `InvalidRequestOptionException`, so a webhook is never sent unpinned. Deliveries are pinned to a direct connection; if your servers must reach the internet through an egress proxy, the proxy resolves the host itself, so enforce the internal-range block on the proxy too.
:::

### Sending Asynchronously with Retry

Queue a webhook with `WebhookQueue::push()` to send it in the background with automatic retry on failure:

```php
use Marko\Webhook\Sending\WebhookQueue;
use Marko\Webhook\Value\WebhookPayload;

public function __construct(
    private readonly WebhookQueue $webhookQueue,
) {}

public function scheduleWebhook(): void
{
    $payload = new WebhookPayload(
        url: 'https://example.com/webhooks',
        event: 'order.shipped',
        data: ['order_id' => 42, 'tracking' => 'ABC123'],
        secret: $subscription->secret,
    );

    $this->webhookQueue->push($payload);
}
```

Queued jobs are serialized into the queue backend (database, Redis, RabbitMQ), where anyone who can read the store or its backups could read them. So `WebhookQueue` never queues the plain signing secret: it wraps the payload in a `SealedWebhookPayload`, whose secret is encrypted with the bound `EncryptorInterface`, and pushes a `DispatchWebhookJob` carrying that. The job decrypts the secret only when it sends. The ciphertext is bound to the delivery ID and URL (as encryption associated data), so an encrypted secret copied into another job fails to decrypt instead of signing a webhook for a different URL. This needs an encryption driver (such as `marko/encryption-openssl`) and `encryption.key`; rotating the key makes jobs queued under the old key fail with a `DecryptionException`. `push()` also checks the secret length up front, so a missing secret fails when you queue the webhook rather than in the worker.

The job decides what to do from the outcome of each attempt:

| Outcome | Recorded as | Retried |
|---|---|---|
| `2xx` response | Success: status code and response body (capped at 500 bytes) | No |
| `408`, `429` or `5xx` response | Failure: status code, response body (capped at 500 bytes) and `Webhook receiver responded with HTTP {status}.` | Yes |
| Any other non-2xx response (`3xx`, other `4xx`) | Failure, same fields | No. The receiver will answer the same way again |
| Receiver unreachable or too slow (connection error, transport error, or no answer within `timeout` seconds) | Failure: the error message | Yes |
| URL rejected by the [URL policy](#outgoing-url-policy) (nothing is sent) | Failure: the `UnsafeWebhookUrlException` message | No. The URL would be rejected again |
| `data` cannot be encoded as JSON (nothing is sent) | Failure: the `InvalidWebhookPayloadException` message | No. The data would fail again |

Retries go back onto the queue with `retry_delay * 2^attempt` seconds of delay until `max_retries` attempts have been made. Each retry carries the same sealed payload, so it is sent with the same `X-Webhook-Id`. When `max_retries` or `retry_delay` is missing from config, the attempt is still recorded but not retried. Only the send is retried: if recording a delivered webhook fails (for example, the database is down), the exception propagates instead of sending the webhook again.

### Receiving Webhooks

The simplest way to receive webhooks is to mark a routed controller action with `#[WebhookEndpoint]`, naming the config key that holds the shared secret:

```php title="config/webhook.php"
use Marko\Config\Env;

return [
    'secrets' => [
        'orders' => Env::string('ORDERS_WEBHOOK_SECRET', ''),
    ],
];
```

```php
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Webhook\Attributes\WebhookEndpoint;

class OrderWebhookController
{
    #[Post('/webhooks/orders')]
    #[WebhookEndpoint(secretKey: 'webhook.secrets.orders')]
    public function handle(
        Request $request,
    ): Response {
        // Only verified requests get here.
        $event = $request->json('event');
        $payload = $request->json('data');

        // process event...

        return new Response(body: 'ok');
    }
}
```

`marko/webhook` registers `WebhookEndpointMiddleware` as global middleware. For every request routed to an action marked `#[WebhookEndpoint]` (or to any action of a controller marked with it), the middleware reads the secret from the config key and checks the request with `WebhookReceiverInterface` before the action runs. Requests that fail never reach the action:

| Problem | Response |
|---|---|
| Missing or wrong signature, missing `X-Webhook-Id` or `X-Webhook-Timestamp`, stale timestamp, or (with `replay_protection`) a delivery ID already received | `401` `{"message": "Invalid webhook signature"}` |
| Body larger than `max_body_bytes` | `413` `{"message": "Webhook payload too large"}` |
| Body is not a JSON object or array | `400` `{"message": "Invalid webhook payload"}` |

A config key that is not set throws `ConfigNotFoundException`, and a secret shorter than 16 bytes throws `InvalidWebhookSecretException`: both are configuration errors, so they fail loudly instead of answering `401`. The secret is never written in source code. The attribute does not register a route, so pair it with `#[Post]` or another routing attribute. A method-level attribute wins over a class-level one, and a [Preference](/docs/concepts/preferences/) that overrides a webhook action stays verified without repeating the attribute. A route that excludes the middleware with `#[WithoutMiddleware(WebhookEndpointMiddleware::class)]` is not verified.

To verify in your own code instead, call `WebhookReceiver::receive()` in the controller. It returns the decoded body:

```php
use Marko\Config\ConfigRepositoryInterface;
use Marko\Routing\Http\Request;
use Marko\Webhook\Exceptions\InvalidSignatureException;
use Marko\Webhook\Exceptions\InvalidWebhookPayloadException;
use Marko\Webhook\Receiving\WebhookReceiver;

public function __construct(
    private readonly WebhookReceiver $webhookReceiver,
    private readonly ConfigRepositoryInterface $config,
) {}

public function handle(
    Request $request,
): void {
    try {
        $data = $this->webhookReceiver->receive(
            request: $request,
            secret: $this->config->getString('webhook.secrets.orders'),
        );

        $event = $data['event'];
        $payload = $data['data'];

        // process event...
    } catch (InvalidSignatureException|InvalidWebhookPayloadException) {
        // reject the request
    }
}
```

`receive()` checks, in this order:

1. The body is at most `max_body_bytes` long, before any hashing or decoding. Otherwise it throws `WebhookPayloadTooLargeException` (a subclass of `InvalidWebhookPayloadException`).
2. The `X-Webhook-Timestamp` and `X-Webhook-Id` headers are present, the timestamp is within `timestamp_tolerance`, and the signature over `"{id}.{timestamp}.{body}"` matches. Otherwise it throws `InvalidSignatureException`.
3. The body decodes as a JSON object or array. Invalid JSON, an empty body, or a scalar such as `"text"`, `1` or `null` throws `InvalidWebhookPayloadException`.
4. With `replay_protection` on, the `X-Webhook-Id` was not received before. Otherwise it throws `InvalidSignatureException`.

### Replay Protection

A signed request stays valid for `timestamp_tolerance` seconds, so anyone who captures one can send it again within that window. Set `replay_protection` to `true` to reject a delivery ID the receiver has already accepted:

```php title="config/webhook.php"
return [
    'replay_protection' => true,
];
```

The receiver then records every accepted `X-Webhook-Id` through `WebhookReplayGuardInterface`. The default `CacheReplayGuard` uses [marko/cache](/docs/packages/cache/)'s atomic `increment()`, so two concurrent requests with the same ID cannot both be accepted, and keeps each ID for twice `timestamp_tolerance` (the whole window in which its timestamp is accepted). Use a cache driver shared by every web server, such as `marko/cache-redis`; a per-process or per-server cache only catches replays that reach the same process or server. Only requests that pass every other check are recorded, so a forged request cannot block a real delivery ID.

`marko/cache` is optional: with `replay_protection` on and no `marko/cache` installed, resolving the receiver throws a `ConfigException` that says so. `replay_protection` applies to every receiver the container builds, including one resolved through `WebhookReceiverInterface`. To store seen IDs elsewhere, bind your own `WebhookReplayGuardInterface`. A sender that retries a delivery you already accepted gets a `401` for the retry, which Marko senders record as a final rejection.

### Delivery Tracking

Every attempt is saved to the `webhook_attempts` table via `WebhookDeliveryService`. Successful attempts store the HTTP status code and response body. Attempts the receiver rejected with a non-2xx status store the status code, the response body and an error message. Attempts that never reached the receiver, or got no answer within `timeout` seconds, store only the error message.

Every recorded response body, successful or rejected, is trimmed and capped at 500 bytes. A longer body is cut at a UTF-8 character boundary and ends with `... [truncated N bytes]`, so a receiver that returns a large page cannot overflow the `response_body` column after the webhook was already delivered. Use `WebhookAttemptRepositoryInterface` to query the records:

```php
use Marko\Webhook\Contracts\WebhookAttemptRepositoryInterface;

public function __construct(
    private readonly WebhookAttemptRepositoryInterface $webhookAttemptRepository,
) {}
```

## API Reference

### WebhookPayload

```php
use Marko\Webhook\Value\WebhookPayload;

public string $id;   // delivery ID, sent as X-Webhook-Id

// @throws InvalidWebhookPayloadException when $id is an empty string
public function __construct(
    string $url,
    string $event,
    array $data,
    string $secret,
    ?string $id = null,   // a random UUID v4 when omitted
);
```

### SealedWebhookPayload

```php
use Marko\Encryption\Contracts\EncryptorInterface;
use Marko\Webhook\Value\SealedWebhookPayload;
use Marko\Webhook\Value\WebhookPayload;

public function __construct(
    string $url,
    string $event,
    array $data,
    string $id,
    string $encryptedSecret,
);

// Encrypts the secret; @throws InvalidWebhookSecretException|EncryptionException
public static function seal(WebhookPayload $payload, EncryptorInterface $encryptor): SealedWebhookPayload;

// Decrypts the secret; @throws DecryptionException
public function unseal(EncryptorInterface $encryptor): WebhookPayload;
```

### WebhookQueue

```php
use Marko\Encryption\Contracts\EncryptorInterface;
use Marko\Queue\QueueInterface;
use Marko\Webhook\Sending\WebhookQueue;
use Marko\Webhook\Value\WebhookPayload;

public function __construct(
    QueueInterface $queue,
    EncryptorInterface $encryptor,
);

// Seals the payload and pushes a DispatchWebhookJob; returns the queue's PushResult (the job ID, or why it was skipped)
// @throws InvalidWebhookSecretException|EncryptionException
public function push(WebhookPayload $payload, ?string $queue = null): PushResult;
```

### WebhookResponse

```php
use Marko\Webhook\Value\WebhookResponse;

public function __construct(
    int $statusCode,
    string $body,
    bool $successful,   // true only for 2xx
);
public function isRetryable(): bool;  // true for 408, 429 and 5xx
```

### WebhookDispatcher

```php
use Marko\Http\Contracts\HttpClientInterface;
use Marko\Webhook\Config\WebhookConfig;
use Marko\Webhook\Contracts\WebhookUrlPolicyInterface;
use Marko\Webhook\Sending\WebhookDispatcher;
use Marko\Webhook\Value\WebhookPayload;
use Marko\Webhook\Value\WebhookResponse;
use Psr\Clock\ClockInterface;

public function __construct(
    HttpClientInterface $httpClient,
    ClockInterface $clock,
    WebhookConfig $config,
    WebhookUrlPolicyInterface $urlPolicy,
);

// Returns a WebhookResponse for every HTTP response (4xx/5xx and 3xx included; redirects are not followed).
// Validates the URL with the URL policy first, pins the connection to the address it approved (resolve_to),
// and sends webhook.timeout as the request timeout.
// Sends X-Webhook-Id, X-Webhook-Timestamp and X-Webhook-Signature headers.
// @throws UnsafeWebhookUrlException when the URL policy rejects the URL (nothing is sent)
// @throws InvalidWebhookPayloadException when the data cannot be encoded as JSON (nothing is sent)
// @throws HttpException|ConnectionException when the receiver cannot be reached or does not answer in time
public function dispatch(WebhookPayload $payload): WebhookResponse;
```

### WebhookUrlPolicy

```php
use Marko\Config\ConfigRepositoryInterface;
use Marko\Webhook\Contracts\HostResolverInterface;
use Marko\Webhook\Sending\WebhookUrlPolicy;

public function __construct(
    ConfigRepositoryInterface $config,   // reads webhook.allow_http
    HostResolverInterface $resolver,
);

// Returns the approved IP address to pin the connection to (the first address the host resolved to).
// @throws UnsafeWebhookUrlException
public function validate(string $url): string;
```

### DnsHostResolver

```php
use Marko\Webhook\Sending\DnsHostResolver;

// IP literals resolve to themselves; hostnames via gethostbynamel() (IPv4) and AAAA records (IPv6).
// Returns an empty list when the host does not resolve.
public function resolve(string $host): array;
```

### UnsafeWebhookUrlException

```php
use Marko\Webhook\Exceptions\UnsafeWebhookUrlException;

UnsafeWebhookUrlException::malformed(string $url);
UnsafeWebhookUrlException::disallowedScheme(string $url, string $scheme, bool $allowHttp);
UnsafeWebhookUrlException::ambiguousNumericHost(string $url, string $host);
UnsafeWebhookUrlException::unresolvableHost(string $url, string $host);
UnsafeWebhookUrlException::disallowedAddress(string $url, string $host, string $address, string $range);
```

### WebhookReceiver

```php
use Marko\Config\Exceptions\ConfigException;
use Marko\Routing\Http\Request;
use Marko\Webhook\Config\WebhookConfig;
use Marko\Webhook\Contracts\WebhookReplayGuardInterface;
use Marko\Webhook\Receiving\WebhookReceiver;
use Marko\Webhook\Receiving\WebhookVerifier;
use Psr\Clock\ClockInterface;

// The container passes the bound replay guard only when webhook.replay_protection is true
public function __construct(
    WebhookVerifier $verifier,
    WebhookConfig $webhookConfig,
    ClockInterface $clock,
    ?WebhookReplayGuardInterface $replayGuard = null,
);

// Returns the decoded JSON object or array
// @throws InvalidSignatureException|InvalidWebhookSecretException|InvalidWebhookPayloadException
// @throws ConfigException when webhook.replay_protection is true but no replay guard was given
public function receive(Request $request, string $secret): array;
```

### WebhookVerifier

```php
use Marko\Webhook\Receiving\WebhookVerifier;

// Checks the signature over "{webhookId}.{timestamp}.{body}" and the timestamp freshness
// @throws InvalidWebhookSecretException when $secret is shorter than WebhookSecret::MIN_LENGTH
public function verify(string $body, string $timestamp, string $signature, string $secret, int $tolerance, string $webhookId): bool;
```

### WebhookSignature

```php
use Marko\Webhook\Sending\WebhookSignature;

// Returns "sha256={hash}"; message is "{webhookId}.{timestamp}.{payload}"
// @throws InvalidWebhookSecretException when $secret is shorter than WebhookSecret::MIN_LENGTH
public static function sign(string $payload, string $secret, int $timestamp, string $webhookId): string;
```

### WebhookEndpoint (Attribute)

```php
use Marko\Webhook\Attributes\WebhookEndpoint;

// On a controller method or class. $secretKey is the config key holding the signing secret.
// @throws InvalidWebhookSecretException when $secretKey is empty
#[WebhookEndpoint(secretKey: 'webhook.secrets.orders')]
```

### WebhookEndpointMiddleware

```php
use Marko\Config\ConfigRepositoryInterface;
use Marko\Webhook\Contracts\WebhookReceiverInterface;
use Marko\Webhook\Middleware\WebhookEndpointMiddleware;

// Registered as global middleware by marko/webhook; verifies #[WebhookEndpoint] actions
public function __construct(
    WebhookReceiverInterface $receiver,
    ConfigRepositoryInterface $config,
);
```

### CacheReplayGuard

```php
use Marko\Cache\Contracts\CacheInterface;
use Marko\Webhook\Receiving\CacheReplayGuard;

public function __construct(
    CacheInterface $cache,
);

// True the first time $webhookId is claimed within $ttl seconds; uses CacheInterface::increment()
public function claim(string $webhookId, int $ttl): bool;
```

### DispatchWebhookJob

```php
use Marko\Webhook\Jobs\DispatchWebhookJob;
use Marko\Webhook\Value\SealedWebhookPayload;

public function __construct(
    SealedWebhookPayload $payload,
    int $attemptNumber = 1,
);
public function handle(): void;
```

Implements `ContainerAwareJobInterface` from [`marko/queue`](/docs/packages/queue/). Create it with `WebhookQueue::push()`. The job stores only the `SealedWebhookPayload` value object (signing secret encrypted) and the attempt number; services (`EncryptorInterface`, `WebhookDispatcherInterface`, `WebhookDeliveryService`, `QueueInterface`, config) are resolved from the container at `handle()` time by the queue `Worker`, which releases the container (`releaseContainer()`) once `handle()` returns or throws, so a job that fails for the last time can still be serialized into the failed-job store. Do not inject services via the constructor.

### WebhookDeliveryService

```php
use Marko\Webhook\Sending\WebhookDeliveryService;
use Marko\Webhook\Value\WebhookPayload;
use Marko\Webhook\Value\WebhookResponse;

// Both store the response body trimmed and capped at 500 bytes (HttpResponse::bodyExcerpt())
public function recordSuccess(WebhookPayload $payload, WebhookResponse $response, int $attempt): void;   // 2xx
public function recordRejection(WebhookPayload $payload, WebhookResponse $response, int $attempt): void; // non-2xx
public function recordFailure(WebhookPayload $payload, string $error, int $attempt): void;
```

### WebhookAttempt (Entity)

| Column           | Type   | Description                        |
|------------------|--------|------------------------------------|
| `id`             | int    | Auto-increment primary key         |
| `webhook_url`    | string | Destination URL                    |
| `event`          | string | Event name                         |
| `attempt_number` | int    | Which attempt this record covers   |
| `status_code`    | int    | HTTP status code (null when the receiver was unreachable) |
| `response_body`  | string | Response body, trimmed and capped at 500 bytes (null when unreachable or timed out) |
| `error_message`  | string | Error message (failures and rejections only) |
| `attempted_at`   | string | Timestamp in `Y-m-d H:i:s` format, in the [database timezone](/docs/packages/database/#datetimes-and-timezones) (UTC by default) |

### WebhookConfig

```php
use Marko\Webhook\Config\WebhookConfig;

public int $timeout;             // from webhook.timeout; ConfigException when not positive
public int $maxRetries;          // from webhook.max_retries; ConfigException when negative
public int $retryDelay;          // from webhook.retry_delay; ConfigException when negative
public int $timestampTolerance;  // from webhook.timestamp_tolerance; ConfigException when 0 or less
public int $maxBodyBytes;        // from webhook.max_body_bytes; ConfigException when 0 or less
public bool $replayProtection;   // from webhook.replay_protection
```

### InvalidSignatureException

```php
use Marko\Webhook\Exceptions\InvalidSignatureException;

// Thrown when X-Webhook-Timestamp header is absent
InvalidSignatureException::missingTimestamp();

// Thrown when X-Webhook-Id header is absent or empty
InvalidSignatureException::missingWebhookId();

// Thrown when the timestamp is outside the tolerance window
InvalidSignatureException::staleTimestamp(int $timestamp, int $tolerance, int $now);

// Thrown when the HMAC signature does not match
InvalidSignatureException::forRequest();

// Thrown when webhook.replay_protection is on and the X-Webhook-Id was already received
InvalidSignatureException::replayed(string $webhookId);
```

### InvalidWebhookPayloadException

```php
use Marko\Webhook\Exceptions\InvalidWebhookPayloadException;
use Marko\Webhook\Exceptions\WebhookPayloadTooLargeException;

// Receiving: the body is not valid JSON, or decodes to a scalar
InvalidWebhookPayloadException::malformedJson(JsonException $previous);
InvalidWebhookPayloadException::notAnArray(string $type);

// Receiving: the body is over webhook.max_body_bytes (subclass of InvalidWebhookPayloadException)
WebhookPayloadTooLargeException::forBody(int $size, int $maxBodyBytes);

// Sending: the data cannot be encoded as JSON
InvalidWebhookPayloadException::unencodable(string $event, JsonException $previous);

// WebhookPayload was given an empty id
InvalidWebhookPayloadException::emptyId();
```

### InvalidWebhookSecretException

```php
use Marko\Webhook\Exceptions\InvalidWebhookSecretException;
use Marko\Webhook\WebhookSecret;

WebhookSecret::MIN_LENGTH; // 16 bytes

// Thrown when signing or verifying with an empty secret
InvalidWebhookSecretException::empty();

// Thrown when the secret is shorter than WebhookSecret::MIN_LENGTH bytes
InvalidWebhookSecretException::tooShort(int $length);

// Thrown when #[WebhookEndpoint] is given an empty config key
InvalidWebhookSecretException::emptyConfigKey();
```

### Interfaces

```php
use Marko\Webhook\Contracts\WebhookDispatcherInterface;
use Marko\Webhook\Value\WebhookPayload;
use Marko\Webhook\Value\WebhookResponse;

interface WebhookDispatcherInterface {
    public function dispatch(WebhookPayload $payload): WebhookResponse;
}
```

```php
use Marko\Webhook\Contracts\WebhookUrlPolicyInterface;

interface WebhookUrlPolicyInterface {
    // @throws UnsafeWebhookUrlException
    public function validate(string $url): void;
}
```

```php
use Marko\Webhook\Contracts\HostResolverInterface;

interface HostResolverInterface {
    // Every IPv4 and IPv6 address the host resolves to; empty when it does not resolve
    public function resolve(string $host): array;
}
```

```php
use Marko\Routing\Http\Request;
use Marko\Webhook\Contracts\WebhookReceiverInterface;

interface WebhookReceiverInterface {
    public function receive(Request $request, string $secret): array;
}
```

```php
use Marko\Webhook\Contracts\WebhookReplayGuardInterface;

interface WebhookReplayGuardInterface {
    // Atomically record $webhookId for $ttl seconds; false when it was already recorded
    public function claim(string $webhookId, int $ttl): bool;
}
```

```php
use Marko\Webhook\Contracts\WebhookAttemptRepositoryInterface;
use Marko\Webhook\Entity\WebhookAttempt;

interface WebhookAttemptRepositoryInterface {
    public function save(WebhookAttempt $attempt): WebhookAttempt;
}
```
