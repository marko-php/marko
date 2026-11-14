---
title: marko/broadcasting-pusher
description: Pusher-protocol broadcasting driver --- realtime events through hosted Pusher, Soketi, or Laravel Reverb.
---

Pusher-protocol broadcasting driver --- trigger realtime events through hosted Pusher or any server that speaks the Pusher protocol, such as [Soketi](https://soketi.app) or [Laravel Reverb](https://reverb.laravel.com). The server holds the WebSocket connections; PHP makes one signed HTTP request per event. Installing this package binds `BroadcasterInterface` from [`marko/broadcasting`](/docs/packages/broadcasting/) to `PusherBroadcaster` and registers the `POST /broadcasting/auth` endpoint that authorizes private channels. Requests are signed directly per the Pusher protocol, so `pusher/pusher-php-server` is not required.

## Installation

```bash
composer require marko/broadcasting-pusher
```

This installs `marko/broadcasting` and `marko/http`. You also need an HTTP client driver such as [`marko/http-guzzle`](/docs/packages/http-guzzle/).

## Configuration

```php title="config/broadcasting-pusher.php"
use Marko\Config\Env;

return [
    'app_id' => Env::string('PUSHER_APP_ID', ''),
    'key' => Env::string('PUSHER_APP_KEY', ''),
    'secret' => Env::string('PUSHER_APP_SECRET', ''),
    'cluster' => Env::string('PUSHER_APP_CLUSTER', 'mt1'),
    'host' => Env::string('PUSHER_HOST', ''),
    'port' => Env::int('PUSHER_PORT', 443, min: 1, max: 65535),
    'scheme' => Env::string('PUSHER_SCHEME', 'https'),
    'timeout' => 5,
];
```

When `host` is empty, the API host is derived from the cluster: `api-{cluster}.pusher.com`.

### Hosted Pusher

```bash title=".env"
PUSHER_APP_ID=123456
PUSHER_APP_KEY=your-key
PUSHER_APP_SECRET=your-secret
PUSHER_APP_CLUSTER=eu
```

### Soketi

```bash title=".env"
PUSHER_APP_ID=app-id
PUSHER_APP_KEY=app-key
PUSHER_APP_SECRET=app-secret
PUSHER_HOST=127.0.0.1
PUSHER_PORT=6001
PUSHER_SCHEME=http
```

### Laravel Reverb

Reverb speaks the same protocol. Point the driver at the Reverb server with the app credentials Reverb is configured with:

```bash title=".env"
PUSHER_APP_ID=my-app-id
PUSHER_APP_KEY=my-app-key
PUSHER_APP_SECRET=my-app-secret
PUSHER_HOST=127.0.0.1
PUSHER_PORT=8080
PUSHER_SCHEME=http
```

## Usage

### Publishing

Use `BroadcasterInterface` as described in [`marko/broadcasting`](/docs/packages/broadcasting/). Each broadcast is a signed `POST {scheme}://{host}:{port}/apps/{app_id}/events` with the body `{"name": event, "channels": [channel], "data": "<JSON data>"}`. A `PrivateChannel` is sent with the `private-` prefix (`orders.7` → `private-orders.7`) and a `PresenceChannel` with the `presence-` prefix (`rooms.3` → `presence-rooms.3`).

Channel names may only contain letters, digits and `_ - = @ , . ;`, up to 164 characters including the `private-` or `presence-` prefix; other names throw `BroadcastException` before any request is made. The Pusher protocol has no event ids, so the `$id` argument is not transmitted.

### Failed Publishes

A broadcast that does not reach the server, or that the server rejects, throws `BroadcastException` with the message `Failed to broadcast to channel '{channel}' via Pusher.`

- **Rejected (non-2xx response):** thrown by `BroadcastException::rejected()`. The context holds the status and the server's own error text, capped at 500 bytes: `The Pusher server responded with HTTP 413: Payload too large`. The suggestion depends on the status. For 401/403 it points at the credentials in `config/broadcasting-pusher.php`. For 413 it points at the payload size (hosted Pusher allows 10 KB per event). For any other 4xx it points at the event name, channel name and payload. For 5xx it reports a failure on the server's side.
- **Unreachable (connection or transport error):** thrown by `BroadcastException::publishFailed()`. The context holds the HTTP client's error message.

Neither exception contains the signed request query (`auth_key`, `auth_timestamp`, `body_md5`, `auth_signature`) or the app secret. The transport error is redacted, and the client's exception is not attached as the previous exception, because its message embeds the full signed URL.

```php
use Marko\Broadcasting\Exceptions\BroadcastException;

try {
    $this->broadcaster->broadcast('orders.42', 'order.shipped', $data);
} catch (BroadcastException $e) {
    $this->logger->error($e->getMessage(), ['context' => $e->getContext()]);
}
```

### Client Setup

Use [pusher-js](https://github.com/pusher/pusher-js) directly or through Laravel Echo:

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const echo = new Echo({
    broadcaster: 'pusher',
    key: 'your-key',
    cluster: 'eu',
    // For Soketi or Reverb, instead of cluster:
    // wsHost: '127.0.0.1', wsPort: 6001, forceTLS: false, enabledTransports: ['ws', 'wss'],
    authEndpoint: '/broadcasting/auth',
});

echo.channel('shows.42').listen('.seat.sold', (data) => markSeatSold(data.seat));
echo.private('orders.7').listen('.order.shipped', (data) => showShipped(data));
```

Echo prefixes event names with a namespace unless they start with `.`, so listen with a leading dot for the plain event names Marko sends.

With [`marko/security`](/docs/packages/security/) installed, its global `CsrfMiddleware` also guards `POST /broadcasting/auth`. Send the token: pass Echo the `csrfToken` option (or render `<meta name="csrf-token" content="...">`, which Echo reads), or give pusher-js `auth: { headers: { 'X-CSRF-TOKEN': token } }`. The value is `CsrfTokenManagerInterface::get()`, also readable from the `XSRF-TOKEN` cookie.

### Channel Authorization

When a client subscribes to `private-*` or `presence-*`, pusher-js `POST`s `socket_id` and `channel_name` to `/broadcasting/auth`. `PusherAuthController` strips the prefix, asks the [`ChannelRegistry`](/docs/packages/broadcasting/#authorizing-private-channels) whether the current user (from `GuardInterface`) may subscribe, and responds:

| Situation | Response |
|---|---|
| Private channel authorized | `200 {"auth": "key:HMAC-SHA256(secret, socket_id:channel_name)"}` |
| Presence channel authorized | `200 {"auth": "key:HMAC-SHA256(secret, socket_id:channel_name:channel_data)", "channel_data": "{\"user_id\":\"7\",\"user_info\":{...}}"}` |
| The authorizer denies the user (guests included) | `HttpException` --- `403` |
| No authorizer matches the channel (including names with characters no pattern accepts), or it serves the other channel kind | `HttpException` --- `403` |
| Missing/malformed `socket_id`, missing `channel_name`, or a public channel (no `private-`/`presence-` prefix) | `HttpException::badRequest()` --- `400` |

Errors are thrown as `HttpException` and rendered by the routing pipeline's [exception renderer](/docs/packages/routing/#errors-and-http-exceptions), so the body format follows the request (pusher-js only reads the status).

Every `403` carries the same generic `Forbidden.` message, so a client cannot tell an unknown channel from a refused one, and the authorizer-setup hint never reaches the response. When the registry cannot authorize the channel, the original `ChannelAuthorizationException` is kept as the `HttpException`'s previous exception for server-side debugging. A `PusherException` for missing credentials is not converted and still surfaces as a server error.

### Presence Channels

Broadcast to a `PresenceChannel` and register a [`PresenceChannelAuthorizerInterface`](/docs/packages/broadcasting/#presence-channels) for its pattern. The authorizer's `PresenceMember` becomes the `channel_data` the server shares with other members: `user_id` is the member id as a string, and `user_info` is the member's info (omitted when empty).

```javascript
// pusher-js
const room = pusher.subscribe('presence-rooms.3');

room.bind('pusher:subscription_succeeded', (members) => {
    members.each((member) => addToRoster(member.id, member.info.name));
});
room.bind('pusher:member_added', (member) => addToRoster(member.id, member.info.name));
room.bind('pusher:member_removed', (member) => removeFromRoster(member.id));
room.bind('message.posted', (data) => showMessage(data.text));

// or Laravel Echo
echo.join('rooms.3')
    .here((members) => setRoster(members))
    .joining((member) => addToRoster(member.id, member.info.name))
    .leaving((member) => removeFromRoster(member.id))
    .listen('.message.posted', (data) => showMessage(data.text));
```

```php
$broadcaster->broadcast(new PresenceChannel('rooms.3'), 'message.posted', ['text' => $text]); // sent as presence-rooms.3
```

Package routes are always discovered. To change the path or add middleware, replace the controller with a [`#[Preference]`](/docs/concepts/preferences/) subclass:

```php title="app/shop/src/Controller/BroadcastAuthController.php"
use Marko\Broadcasting\Pusher\Controller\PusherAuthController;
use Marko\Core\Attributes\Preference;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Authentication\Middleware\AuthMiddleware;

#[Preference(replaces: PusherAuthController::class)]
readonly class BroadcastAuthController extends PusherAuthController
{
    #[Post('/api/broadcasting/auth')]
    #[Middleware(AuthMiddleware::class)]
    public function authorize(
        Request $request,
    ): Response {
        return parent::authorize($request);
    }
}
```

## API Reference

### PusherBroadcaster

Implements `BroadcasterInterface`.

```php
use Marko\Broadcasting\Pusher\Driver\PusherBroadcaster;

public function broadcast(
    string|Channel $channel,
    string $event,
    array $data,
    ?string $id = null,
): void;
public function dispatch(BroadcastableInterface $broadcastable): void;
```

Each request is signed with `auth_timestamp` read from the injected PSR-20 [`ClockInterface`](/docs/packages/clock/). In tests, a [`FakeClock`](/docs/packages/testing/#fakeclock) makes the signed query string predictable.

### PusherSignature

```php
use Marko\Broadcasting\Pusher\Auth\PusherSignature;

/** @param array<string, string> $query */
public function sign(
    string $method,
    string $path,
    array $query,
): string;

/** @return array<string, string> auth_key, auth_timestamp, auth_version, body_md5, auth_signature */
public function signedQuery(
    string $method,
    string $path,
    string $body,
    int $timestamp,
): array;

public function channelAuth(
    string $socketId,
    string $channelName,
): string;

/** $channelData is the already-encoded JSON string returned to the client as channel_data */
public function presenceChannelAuth(
    string $socketId,
    string $channelName,
    string $channelData,
): string;
```

### PusherAuthController

```php
use Marko\Broadcasting\Pusher\Controller\PusherAuthController;

#[Post('/broadcasting/auth')]
/** @throws HttpException|PusherException */
public function authorize(Request $request): Response;
```

### PusherConfig

Readonly value object built from `config/broadcasting-pusher.php` by the module binding; `apiHost()` and `baseUrl()` derive the API endpoint.

### Exceptions

| Exception | Thrown when |
|---|---|
| `PusherException::missingCredentials()` | `app_id`, `key` or `secret` is empty when signing |
| `HttpException` (`marko/routing`) | `/broadcasting/auth` rejects a request (`400`), or the authorizer denies the user or no authorizer matches the channel (`403`) |
| `BroadcastException::invalidChannelName()` | A channel name contains characters the Pusher protocol does not allow |
| `BroadcastException::rejected()` | The server answers with a non-2xx status; the context holds the status and the capped response body |
| `BroadcastException::publishFailed()` | The server cannot be reached (connection or transport failure); the signed query is redacted from the context |

## Related Packages

- [`marko/broadcasting`](/docs/packages/broadcasting/) --- the interfaces and channel authorization
- [`marko/broadcasting-amphp`](/docs/packages/broadcasting-amphp/) --- self-hosted async SSE server driver
- [`marko/broadcasting-mercure`](/docs/packages/broadcasting-mercure/) --- Mercure hub driver
- [`marko/http-guzzle`](/docs/packages/http-guzzle/) --- HTTP client driver used to reach the API
