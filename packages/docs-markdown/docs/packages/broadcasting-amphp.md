---
title: marko/broadcasting-amphp
description: Native async SSE broadcasting server --- a self-hosted, PHP-only realtime server on amphp that fans events out through marko/pubsub.
---

Native async SSE broadcasting server --- hold thousands of Server-Sent Events connections in one PHP process, with no third-party service and no extra runtime. Installing this package binds `BroadcasterInterface` from [`marko/broadcasting`](/docs/packages/broadcasting/) to `AmphpBroadcaster`, which publishes each event as one [`marko/pubsub`](/docs/packages/pubsub/) message (a Redis `PUBLISH` or a Postgres `NOTIFY`). The `marko broadcasting:serve` command runs an [amphp/http-server](https://amphp.org/http-server) process on the [Revolt](https://revolt.run) event loop. It subscribes to the same channels and streams events to browsers. Each channel has one shared pub/sub subscription per server process, however many viewers it has. This is the SSE-only equivalent of Laravel Reverb: one-way, server to browser.

## Installation

```bash
composer require marko/broadcasting-amphp
```

This installs `marko/broadcasting`, `marko/amphp`, `marko/pubsub` and `amphp/http-server`. You also need:

- A pub/sub driver: [`marko/pubsub-redis`](/docs/packages/pubsub-redis/) (recommended) or [`marko/pubsub-pgsql`](/docs/packages/pubsub-pgsql/).
- A log driver such as [`marko/log-file`](/docs/packages/log-file/). The server logs through `LoggerInterface`.
- `ext-pcntl` for the PHP CLI, so the server can handle `SIGINT`/`SIGTERM` for graceful shutdown.
- **For more than about 1,000 connections, install `ext-ev`, `ext-uv` or `ext-event`.** Without one of them, Revolt falls back to `stream_select()`, which cannot watch more than 1,024 sockets. The server then caps `max_connections` at 1,000 and logs a warning at startup.

## Configuration

```bash title=".env"
BROADCASTING_AMPHP_PUBLIC_URL=https://realtime.example.com
BROADCASTING_AMPHP_APP_KEY=change-me-to-a-long-random-secret
BROADCASTING_AMPHP_ALLOWED_ORIGINS=https://example.com
```

```php title="config/broadcasting-amphp.php"
use Marko\Config\Env;

return [
    'host' => Env::string('BROADCASTING_AMPHP_HOST', '0.0.0.0'),
    'port' => Env::int('BROADCASTING_AMPHP_PORT', 8085, min: 1, max: 65535),
    'path' => Env::string('BROADCASTING_AMPHP_PATH', '/stream'),
    'health_path' => Env::string('BROADCASTING_AMPHP_HEALTH_PATH', '/health'),
    'health_detail' => Env::bool('BROADCASTING_AMPHP_HEALTH_DETAIL', false),
    'health_secret' => Env::string('BROADCASTING_AMPHP_HEALTH_SECRET', ''),
    'public_url' => Env::string('BROADCASTING_AMPHP_PUBLIC_URL', 'http://localhost:8085'),
    'channel_prefix' => Env::string('BROADCASTING_AMPHP_CHANNEL_PREFIX', 'broadcast.'),
    'app_key' => Env::string('BROADCASTING_AMPHP_APP_KEY', ''),
    'heartbeat' => Env::int('BROADCASTING_AMPHP_HEARTBEAT', 15, min: 0),
    'replay_buffer' => Env::int('BROADCASTING_AMPHP_REPLAY_BUFFER', 100, min: 0),
    'replay_ttl' => Env::int('BROADCASTING_AMPHP_REPLAY_TTL', 300, min: 0),
    'max_connections' => Env::int('BROADCASTING_AMPHP_MAX_CONNECTIONS', 10000, min: 0),
    'max_connections_per_ip' => Env::int('BROADCASTING_AMPHP_MAX_CONNECTIONS_PER_IP', 100, min: 0),
    'allowed_origins' => Env::list('BROADCASTING_AMPHP_ALLOWED_ORIGINS', ['*']),
    'trusted_proxies' => Env::list('BROADCASTING_AMPHP_TRUSTED_PROXIES', []),
    'token_ttl' => Env::int('BROADCASTING_AMPHP_TOKEN_TTL', 3600, min: 0),
    'log_interval' => Env::int('BROADCASTING_AMPHP_LOG_INTERVAL', 60, min: 0),
];
```

| Key | Purpose |
|---|---|
| `host`, `port` | Address `broadcasting:serve` listens on (override with `--host` / `--port`) |
| `path` | Stream endpoint browsers open with `EventSource` |
| `health_path` | JSON health check with aggregate counts: open streams and active channels, no channel names |
| `health_detail`, `health_secret` | Set `health_detail` to `true` to add per-channel counts to `health_path` for requests that send `health_secret` in an `X-Health-Secret` header. Building the config throws when `health_detail` is on and `health_secret` is empty |
| `public_url` | Base URL browsers reach the server on, used by `AmphpSubscriberToken::streamUrl()` |
| `channel_prefix` | Prepended to every channel name to form the pub/sub channel |
| `app_key` | Secret that signs subscriber tokens. **Both the app and the server process need the same value.** If it is empty, private channels are refused with `403` |
| `heartbeat` | Seconds between `:` comment frames on every stream |
| `replay_buffer`, `replay_ttl` | Events kept per channel, and for how many seconds, for `Last-Event-ID` replay (`0` disables replay) |
| `max_connections`, `max_connections_per_ip` | Open streams per process and per client IP. Requests over a limit get `503` with `Retry-After`. Set `max_connections_per_ip` to `0` to disable the per-IP limit |
| `allowed_origins` | Origins allowed to open streams (`*` allows any). Other origins get `403` |
| `trusted_proxies` | Proxy addresses or CIDRs whose `X-Forwarded-For` header sets the client IP for the per-IP limit |
| `token_ttl` | Lifetime of subscriber tokens, in seconds |
| `log_interval` | Seconds between connection-count log lines (`0` disables them) |

`amphp.shutdown_timeout` from [`marko/amphp`](/docs/packages/amphp/) bounds graceful shutdown.

## Usage

### Running the Server

```bash
marko broadcasting:serve
marko broadcasting:serve --host=127.0.0.1 --port=8085
```

The command runs until `SIGINT` (Ctrl+C) or `SIGTERM`. On shutdown it stops accepting connections and sends every stream `retry: 1000` followed by `event: reconnect`. It then closes the streams and cancels all pub/sub subscriptions within `amphp.shutdown_timeout` seconds. Run it under a process manager such as systemd or supervisord, or as its own container.

Each open stream uses about **70 KB of PHP memory** (measured: 2,000 streams on 10 channels took 139 MB of PHP memory and 227 MB RSS). Raise `memory_limit` for the CLI accordingly, for example `php -d memory_limit=1G vendor/bin/marko broadcasting:serve`. Raise the open-file limit too (`ulimit -n`): every stream is one socket.

### Publishing

Application code does not change. Keep calling `BroadcasterInterface`:

```php
use Marko\Broadcasting\BroadcasterInterface;
use Marko\Broadcasting\PrivateChannel;

public function __construct(
    private BroadcasterInterface $broadcaster,
) {}

public function sellSeat(): void
{
    $this->broadcaster->broadcast('shows.42', 'seat.sold', ['seat' => 'A1']);
    $this->broadcaster->broadcast(new PrivateChannel('orders.7'), 'order.shipped', ['orderId' => 7]);
}
```

`AmphpBroadcaster` publishes `{"event": ..., "data": ..., "id": ...}` to the pub/sub channel `channel_prefix + name`. Private channels are published as `channel_prefix + "private-" + name`, so a public subscriber can never receive a private event. When you pass no `id`, a sortable one is generated (unix milliseconds plus random hex, e.g. `1759651200000-3f9c0b1a2d4e5f60`). Public channel names may not start with `private-`, and names may only use letters, digits and `_ - = @ . ; :`.

:::note
The SSE server does not track channel members, so this driver does not support [presence channels](/docs/packages/broadcasting/#presence-channels). `AmphpBroadcaster::broadcast()`, `AmphpSubscriberToken::for()` and `streamUrl()` throw `BroadcastException::presenceChannelsUnsupported()` for a `PresenceChannel` instead of treating it as private or public. Use the [Pusher driver](/docs/packages/broadcasting-pusher/#presence-channels) for presence.
:::

:::caution
`marko/pubsub-pgsql` sends messages with `NOTIFY`, whose payload must be shorter than 8,000 bytes. `AmphpBroadcaster` checks the encoded size and throws `AmphpBroadcastException::payloadTooLarge()` instead of letting Postgres reject it. Broadcast identifiers and let the client fetch the full record, or use `marko/pubsub-redis`.
:::

### Subscribing from the Browser

No JavaScript package is needed; use the browser's `EventSource`. Public channels need only a URL:

```js
const source = new EventSource('https://realtime.example.com/stream?channels=shows.42');

source.addEventListener('seat.sold', (e) => {
    const data = JSON.parse(e.data);
});
```

Private channels need a short-lived signed token. Issue it from a controller with `AmphpSubscriberToken`. The token covers only the private channels that the `ChannelRegistry` authorizes for the user (see [Authorizing Private Channels](/docs/packages/broadcasting/#authorizing-private-channels)):

```php
use Marko\Authentication\AuthManager;
use Marko\Broadcasting\Amphp\Subscriber\AmphpSubscriberToken;
use Marko\Broadcasting\PrivateChannel;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;

public function __construct(
    private AmphpSubscriberToken $amphpSubscriberToken,
    private AuthManager $authManager,
) {}

#[Get('/orders/7/stream-url')]
public function streamUrl(): Response
{
    return Response::json([
        'url' => $this->amphpSubscriberToken->streamUrl(
            ['shows.42', new PrivateChannel('orders.7')],
            $this->authManager->user(),
        ),
    ]);
}
```

The resulting URL looks like `https://realtime.example.com/stream?channels=shows.42%2Cprivate-orders.7&token=...`:

```js
const { url } = await (await fetch('/orders/7/stream-url')).json();
const source = new EventSource(url);

source.addEventListener('order.shipped', (e) => console.log(JSON.parse(e.data)));
```

The server checks the token's HMAC signature, expiry and channel list using `app_key` alone, without calling back into the app. A request for a `private-` channel that the token does not list, or with a missing, expired or forged token, gets `403`. `EventSource` cannot set headers, so the token goes in the `token` query parameter. Clients that can set headers may send `Authorization: Bearer <token>` instead. Keep query strings out of your proxy's access logs, because they carry tokens.

### Reconnection and Replay

The server keeps the last `replay_buffer` events per channel, for up to `replay_ttl` seconds. When `EventSource` reconnects, it sends `Last-Event-ID` and the server replays the events after that id, in order, before streaming live ones. A client can also pass `lastEventId` as a query parameter on its first connection.

If the id cannot be replayed completely, the server sends a `reset` event instead. That happens when the id is unknown or already evicted, or when a requested channel had no subscription at the time. Refetch state when you see it:

```js
source.addEventListener('reset', () => reloadSeatMap());
source.addEventListener('reconnect', () => { /* the server is restarting; EventSource reconnects by itself */ });
```

Replay is **per process** and in memory: a restart, or a reconnect that lands on a different node, gets `reset`. Durable or cross-node replay would need a shared store (for example a Redis stream) and is not built in.

### Heartbeats, Disconnects and Limits

- Every `heartbeat` seconds, each stream receives a `:` comment. This keeps proxies from closing idle streams.
- HTTP/1 does not read from a socket while its response is streaming, so a client that goes away is noticed on the next write: an event or, at the latest, the next heartbeat. Its slot is then freed, and its channel's pub/sub subscription is cancelled when the last viewer leaves.
- A client that falls more than 500 frames behind is disconnected, so one slow reader cannot stall delivery or grow memory without bound.
- A stream may request at most 50 channels (`400` otherwise).
- Over `max_connections` or `max_connections_per_ip`, the server answers `503` with `Retry-After: 5`. It also answers `503` when the pub/sub backend cannot be reached.

### Health and Logs

`GET /health` returns aggregate counts only: open streams and active channels.

```json
{"status": "ok", "connections": 2000, "channels": 2}
```

The endpoint needs no authentication, so it never lists channel names by default. Names such as `private-users.7` would reveal which users are online. To see open streams per channel, enable `health_detail`, set `health_secret`, and send the secret in an `X-Health-Secret` header:

```bash title=".env"
BROADCASTING_AMPHP_HEALTH_DETAIL=true
BROADCASTING_AMPHP_HEALTH_SECRET=change-me-to-a-long-random-secret
```

```bash
curl -H "X-Health-Secret: $BROADCASTING_AMPHP_HEALTH_SECRET" http://127.0.0.1:8085/health
```

```json
{"status": "ok", "connections": 2000, "channels": 2, "channel_counts": {"shows.42": 1200, "shows.43": 800}}
```

A missing or wrong secret gets the aggregate response, not an error.

Every `log_interval` seconds the server logs `Broadcasting server: N open streams across M channels`, with memory usage in the context. amphp/http-server's own log records go to the same logger.

### Deploying Behind nginx

Run the server on its own port or subdomain and proxy it with buffering off and a read timeout well above `heartbeat`:

```nginx title="nginx.conf"
location /stream {
    proxy_pass http://127.0.0.1:8085;
    proxy_http_version 1.1;
    proxy_set_header Connection "";
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_buffering off;
    proxy_cache off;
    proxy_read_timeout 1h;
}
```

The server also sends `X-Accel-Buffering: no`. If you rely on `max_connections_per_ip`, set `trusted_proxies` to your proxy's address. Otherwise every viewer appears to come from the proxy. Set `allowed_origins` to your site's origin when the server runs on a different host or port.

### Scaling Out

Run several `broadcasting:serve` processes behind a load balancer. Each one subscribes to pub/sub, so `marko/pubsub-redis` or `marko/pubsub-pgsql` fans every event out to all nodes, the same way Laravel Reverb uses Redis.

With `marko/pubsub-redis`, each SSE process holds **one Redis connection** for all its subscriptions. The server keeps one subscription per distinct active channel, and all of them share that connection, so a process with 5,000 per-user private channels still uses one Redis subscriber connection. Size Redis `maxclients` by the number of processes, not the number of channels. See [Connections](/docs/packages/pubsub-redis/#connections) for how the shared connection unsubscribes and reconnects.

### RoadRunner

The server is its own process and does not run inside a RoadRunner worker. On [`marko/roadrunner`](/docs/packages/roadrunner/), use this package (or the [Mercure](/docs/packages/broadcasting-mercure/) or [Pusher](/docs/packages/broadcasting-pusher/) drivers) for realtime updates instead of [`marko/sse`](/docs/packages/sse/), which RoadRunner refuses.

## API Reference

### AmphpBroadcaster

```php
public function broadcast(
    string|Channel $channel,
    string $event,
    array $data,
    ?string $id = null,
): void;
public function dispatch(BroadcastableInterface $broadcastable): void;
```

Bound to `BroadcasterInterface`. The constants `PRIVATE_PREFIX`, `CHANNEL_NAME_PATTERN`, `CHANNEL_NAME_ALLOWED` and `PGSQL_PAYLOAD_LIMIT` describe the wire format. Override the protected `generateId()` through a Preference to change id generation.

### AmphpSubscriberToken

```php
public function for(
    array $channels,
    ?AuthenticatableInterface $user,
): string;
public function streamUrl(
    array $channels,
    ?AuthenticatableInterface $user,
): string;
```

`for()` signs a token listing the authorized private channels. Both methods check every channel name against `AmphpBroadcaster::CHANNEL_NAME_PATTERN` before authorizing it and throw `BroadcastException::invalidChannelName()` for anything else, including a name with a comma (the stream's channel separator). `streamUrl()` builds the `EventSource` URL and adds a token when any private channel is requested.

### AmphpSignature

```php
public function sign(
    int|string|null $userId,
    array $channels,
    int $expiresAt,
): string;
public function verify(string $token): ?AmphpTokenClaims;
```

Tokens are JWT-style: `payload . "." . base64url(HMAC-SHA256(app_key, payload))`, where `payload = base64url(JSON {u, c, e})`. The MAC covers the exact encoded payload bytes and `verify()` checks it before decoding anything, so no edit to the claims keeps a valid signature. `verify()` returns `null` for forged, expired or malformed tokens.

### Time

The package reads time through the PSR-20 [`ClockInterface`](/docs/packages/clock/): token expiry in `AmphpSubscriberToken` and `AmphpSignature`, the millisecond prefix of generated event ids, and `replay_ttl` eviction in the server's replay buffer. In tests, construct these classes with a [`FakeClock`](/docs/packages/testing/#fakeclock) to check expiry without sleeping:

```php
use Marko\Broadcasting\Amphp\AmphpBroadcastingConfig;
use Marko\Broadcasting\Amphp\Auth\AmphpSignature;
use Marko\Testing\Fake\FakeClock;

$clock = new FakeClock('2026-01-01 12:00:00 UTC');
$signature = new AmphpSignature(new AmphpBroadcastingConfig(appKey: 'secret'), $clock);
$token = $signature->sign(7, ['private-orders.7'], $clock->now()->getTimestamp() + 60);

$clock->travel('+61 seconds');
$signature->verify($token); // null: expired
```

### ServeCommand

`marko broadcasting:serve [--host=HOST] [--port=PORT]` starts `AmphpSseServer` and runs the event loop until `SIGINT` or `SIGTERM`.

### AmphpBroadcastingConfig

A readonly value object built from `config/broadcasting-amphp.php` by the module binding.

### Exceptions

| Exception | Thrown when |
|---|---|
| `AmphpBroadcastException::missingAppKey()` | A subscriber token is signed without `app_key` |
| `AmphpBroadcastException::reservedPrivatePrefix()` | A public channel name starts with `private-` |
| `AmphpBroadcastException::lineBreakInFrameField()` | An event name or id contains CR or LF |
| `AmphpBroadcastException::payloadTooLarge()` | An event is too large for a Postgres `NOTIFY` |
| `AmphpBroadcastException::serverStartFailed()` | The server cannot bind its address |
| `AmphpBroadcastException::invalidPort()` | `--port` is not a number from 0 to 65535 |
| `AmphpBroadcastException::signalsUnsupported()` | The event loop cannot handle `SIGINT`/`SIGTERM` (install `ext-pcntl`) |
| `BroadcastException::publishFailed()` | The pub/sub driver fails to publish |
| `BroadcastException::invalidChannelName()` | A channel name uses characters outside the allowed set |
| `BroadcastException::presenceChannelsUnsupported()` | A `PresenceChannel` is broadcast to or included in a subscriber token or stream URL |

## Related Packages

- [`marko/broadcasting`](/docs/packages/broadcasting/) --- the interfaces and channel authorization
- [`marko/pubsub-redis`](/docs/packages/pubsub-redis/) --- Redis pub/sub driver (recommended)
- [`marko/pubsub-pgsql`](/docs/packages/pubsub-pgsql/) --- PostgreSQL LISTEN/NOTIFY driver
- [`marko/amphp`](/docs/packages/amphp/) --- event loop lifecycle and `shutdown_timeout`
- [`marko/broadcasting-mercure`](/docs/packages/broadcasting-mercure/) --- Mercure hub driver
- [`marko/broadcasting-pusher`](/docs/packages/broadcasting-pusher/) --- Pusher-protocol driver
- [`marko/sse`](/docs/packages/sse/) --- in-process Server-Sent Events for low-concurrency streams
