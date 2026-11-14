---
title: marko/broadcasting-mercure
description: Mercure broadcasting driver --- publish realtime updates through a Mercure hub, including the one built into FrankenPHP.
---

Mercure broadcasting driver --- publish realtime updates through a [Mercure](https://mercure.rocks) hub. The hub holds the browsers' Server-Sent Events connections; PHP makes one short, authenticated HTTP request per event, so viewers never occupy PHP workers. Installing this package binds `BroadcasterInterface` from [`marko/broadcasting`](/docs/packages/broadcasting/) to `MercureBroadcaster` and provides `MercureSubscriberToken` for authorizing private channels in the browser.

## Installation

```bash
composer require marko/broadcasting-mercure
```

This installs `marko/broadcasting` and `marko/http`. You also need an HTTP client driver such as [`marko/http-guzzle`](/docs/packages/http-guzzle/).

### Zero Extra Infrastructure with FrankenPHP

[FrankenPHP](https://frankenphp.dev) ships a Mercure hub inside its Caddy server, so there is nothing else to run. Enable it in your `Caddyfile`:

```text title="Caddyfile"
{
    frankenphp
}

example.com {
    mercure {
        publisher_jwt {env.MERCURE_PUBLISHER_JWT_KEY}
        subscriber_jwt {env.MERCURE_SUBSCRIBER_JWT_KEY}
    }
    php_server
}
```

The hub is then served at `https://example.com/.well-known/mercure`. Any standalone Mercure hub works the same way.

## Configuration

```bash title=".env"
MERCURE_URL=https://example.com/.well-known/mercure
MERCURE_PUBLIC_URL=https://example.com/.well-known/mercure
MERCURE_PUBLISHER_JWT_KEY=change-me-publisher-key
MERCURE_SUBSCRIBER_JWT_KEY=change-me-subscriber-key
MERCURE_TOPIC_PREFIX=https://example.com/
```

```php title="config/broadcasting-mercure.php"
use Marko\Config\Env;

return [
    'hub_url' => Env::string('MERCURE_URL', 'http://localhost/.well-known/mercure'),
    'public_url' => Env::string('MERCURE_PUBLIC_URL', 'http://localhost/.well-known/mercure'),
    'publisher_jwt_key' => Env::string('MERCURE_PUBLISHER_JWT_KEY', ''),
    'publisher_jwt' => Env::string('MERCURE_PUBLISHER_JWT', ''),
    'subscriber_jwt_key' => Env::string('MERCURE_SUBSCRIBER_JWT_KEY', ''),
    'subscriber_jwt_ttl' => Env::int('MERCURE_SUBSCRIBER_JWT_TTL', 3600, min: 0),
    'topic_prefix' => Env::string('MERCURE_TOPIC_PREFIX', ''),
    'cookie_domain' => Env::string('MERCURE_COOKIE_DOMAIN', ''),
    'cookie_secure' => Env::bool('MERCURE_COOKIE_SECURE', true),
    'timeout' => 5,
];
```

| Key | Purpose |
|---|---|
| `hub_url` | URL PHP publishes to (may be an internal address) |
| `public_url` | URL browsers connect to with `EventSource` |
| `publisher_jwt_key` | HS256 key the hub uses to verify publisher tokens; the driver signs a token with a `mercure.publish: ["*"]` claim |
| `publisher_jwt` | A pre-generated publisher token, used instead of signing one when set |
| `subscriber_jwt_key` | HS256 key the hub uses to verify subscriber tokens |
| `subscriber_jwt_ttl` | Lifetime of subscriber tokens and the cookie, in seconds |
| `topic_prefix` | Prepended to each channel name to form the Mercure topic (Mercure recommends IRIs, e.g. `https://example.com/`) |
| `cookie_domain` | Domain for the `mercureAuthorization` cookie; empty means the current host only |
| `cookie_secure` | Whether the cookie is HTTPS-only |
| `timeout` | Publish request timeout, in seconds |

## Usage

### Publishing

Use `BroadcasterInterface` as described in [`marko/broadcasting`](/docs/packages/broadcasting/). Each broadcast is a `POST` to the hub with these form fields:

| Field | Value |
|---|---|
| `topic` | `topic_prefix` + channel name |
| `data` | The JSON-encoded `$data` |
| `type` | The event name (the SSE event type) |
| `id` | The `$id` argument, when given (used by the hub for `Last-Event-ID` replay) |
| `private` | `on` for a `PrivateChannel` |

### Failed Publishes

A publish that does not reach the hub, or that the hub rejects, throws `BroadcastException` with the message `Failed to broadcast to channel '{channel}' via Mercure.`

- **Rejected (non-2xx response):** thrown by `BroadcastException::rejected()`. The context holds the status and the hub's own error text, capped at 500 bytes: `The Mercure server responded with HTTP 401: Unauthorized`. The suggestion depends on the status. For 401/403 it points at the publisher JWT settings in `config/broadcasting-mercure.php`. For 413 it points at the payload size limit configured on the hub. For any other 4xx it points at the event name, channel name and payload. For 5xx it reports a failure on the hub's side.
- **Unreachable (connection or transport error):** thrown by `BroadcastException::publishFailed()`. The context holds the HTTP client's error message, and the client's exception is attached as the previous exception.

The publisher JWT travels in the `Authorization` header, so it never appears in either exception.

### Subscribing in the Browser

Mercure delivers updates as Server-Sent Events. Listen by event name, because `type` is set to the event name:

```javascript
const source = new EventSource(hubUrl, { withCredentials: true });

source.addEventListener('seat.sold', (event) => {
    const data = JSON.parse(event.data);
    markSeatSold(data.seat);
});
```

Build `hubUrl` on the server with `MercureSubscriberToken::subscribeUrl()`, which adds one `topic` parameter per channel:

```php
use Marko\Broadcasting\Mercure\Subscriber\MercureSubscriberToken;
use Marko\Broadcasting\PrivateChannel;

$hubUrl = $this->mercureSubscriberToken->subscribeUrl([
    'shows.42',
    new PrivateChannel('customers.7'),
]);
// https://example.com/.well-known/mercure?topic=https%3A%2F%2Fexample.com%2Fshows.42&topic=...
```

### Authorizing Private Channels

Private updates are delivered only to subscribers holding a JWT whose `mercure.subscribe` claim lists the topic. `MercureSubscriberToken` builds that token, including **only** the private channels the [`ChannelRegistry`](/docs/packages/broadcasting/#authorizing-private-channels) authorizes for the user. Public channels need no token and are left out of the claim. A private channel with no registered authorizer throws `ChannelAuthorizationException`. Mercure reads each claim entry as a topic selector, which can be a URI template. So a topic (`topic_prefix` + channel name) containing `{ } * ,`, whitespace or control characters throws `MercureException::unsafeTopic()` and is never signed. `subscribeUrl()` refuses such topics the same way.

:::note
Mercure has no presence protocol, so this driver does not support [presence channels](/docs/packages/broadcasting/#presence-channels). `MercureBroadcaster::broadcast()`, `MercureSubscriberToken::for()` and `subscribeUrl()` throw `BroadcastException::presenceChannelsUnsupported()` for a `PresenceChannel` instead of treating it as private or public. Use the [Pusher driver](/docs/packages/broadcasting-pusher/#presence-channels) for presence.
:::

The usual way to hand the token to the browser is the `mercureAuthorization` cookie, which the hub reads automatically:

```php
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Broadcasting\Mercure\Subscriber\MercureSubscriberToken;
use Marko\Broadcasting\PrivateChannel;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;

class ShowController
{
    public function __construct(
        private ViewInterface $view,
        private GuardInterface $guard,
        private MercureSubscriberToken $mercureSubscriberToken,
    ) {}

    #[Get('/shows/{showId}')]
    public function show(int $showId): Response
    {
        $user = $this->guard->user();
        $channels = ["shows.$showId", new PrivateChannel("customers.{$user?->getAuthIdentifier()}")];

        $response = $this->view->render('shop::show', [
            'hubUrl' => $this->mercureSubscriberToken->subscribeUrl($channels),
        ]);

        return $this->mercureSubscriberToken->withAuthorizationCookie($response, $channels, $user);
    }
}
```

The cookie is `HttpOnly`, `SameSite=Strict`, `Secure` (unless `cookie_secure` is false), scoped to the hub's path, and expires with the token. The hub must be on the same site as your app --- set `cookie_domain` when it lives on a subdomain. To send the token another way (for example an `Authorization` header from a native client), call `for($channels, $user)` to get the raw JWT.

## API Reference

### MercureBroadcaster

Implements `BroadcasterInterface`.

```php
use Marko\Broadcasting\Mercure\Driver\MercureBroadcaster;

public function broadcast(
    string|Channel $channel,
    string $event,
    array $data,
    ?string $id = null,
): void;
public function dispatch(BroadcastableInterface $broadcastable): void;
```

### MercureSubscriberToken

```php
use Marko\Broadcasting\Mercure\Subscriber\MercureSubscriberToken;

public const string COOKIE_NAME = 'mercureAuthorization';

/** @param list<string|Channel> $channels */
public function for(
    array $channels,
    ?AuthenticatableInterface $user,
): string;

/** @param list<string|Channel> $channels */
public function withAuthorizationCookie(
    Response $response,
    array $channels,
    ?AuthenticatableInterface $user,
): Response;

/** @param list<string|Channel> $channels */
public function subscribeUrl(array $channels): string;
```

The JWT `exp` claim and the cookie expiry are `subscriber_jwt_ttl` seconds after the injected PSR-20 [`ClockInterface`](/docs/packages/clock/). In tests, construct `MercureSubscriberToken` with a [`FakeClock`](/docs/packages/testing/#fakeclock) to assert an exact expiry.

### MercureJwt

A minimal HS256 encoder used for publisher and subscriber tokens --- no JWT library dependency.

```php
use Marko\Broadcasting\Mercure\Jwt\MercureJwt;

/** @param array<string, mixed> $claims */
public function encode(
    array $claims,
    string $key,
): string;
```

### MercureConfig

Readonly value object built from `config/broadcasting-mercure.php` by the module binding. Replace the `MercureConfig` binding in your own module to build it differently.

### Exceptions

| Exception | Thrown when |
|---|---|
| `MercureException::emptySigningKey()` | A JWT is signed with an empty key |
| `MercureException::missingPublisherCredentials()` | Neither `publisher_jwt` nor `publisher_jwt_key` is set |
| `MercureException::missingSubscriberKey()` | A subscriber token is requested without `subscriber_jwt_key` |
| `MercureException::unsafeTopic()` | A subscriber token or subscribe URL is built for a topic containing `{ } * ,`, whitespace or control characters |
| `BroadcastException::rejected()` | The hub answers with a non-2xx status; the context holds the status and the capped response body |
| `BroadcastException::publishFailed()` | The hub cannot be reached (connection or transport failure) |
| `BroadcastException::presenceChannelsUnsupported()` | A `PresenceChannel` is broadcast to or included in a subscriber token or URL |

## Related Packages

- [`marko/broadcasting`](/docs/packages/broadcasting/) --- the interfaces and channel authorization
- [`marko/broadcasting-amphp`](/docs/packages/broadcasting-amphp/) --- self-hosted async SSE server driver
- [`marko/broadcasting-pusher`](/docs/packages/broadcasting-pusher/) --- Pusher-protocol driver
- [`marko/http-guzzle`](/docs/packages/http-guzzle/) --- HTTP client driver used to reach the hub
