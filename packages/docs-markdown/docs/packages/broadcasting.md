---
title: marko/broadcasting
description: Realtime broadcasting contracts --- send events to everyone watching a channel, with pluggable Mercure and Pusher drivers.
---

Realtime broadcasting contracts --- send an event to everyone watching a channel without coupling your app to how the connections are held. App code calls `BroadcasterInterface::broadcast()`; the installed driver delivers the event through a hub that holds the browser connections, so no PHP worker is tied up per viewer. This package defines the interfaces, the `Channel` / `PrivateChannel` / `PresenceChannel` value objects, and private and presence channel authorization via `#[BroadcastChannel]` authorizers. It ships no driver.

## Installation

```bash
composer require marko/broadcasting
```

You also need a driver:

| Driver | Holds connections in | Best for |
|---|---|---|
| [`marko/broadcasting-amphp`](/docs/packages/broadcasting-amphp/) | A self-hosted PHP process (`marko broadcasting:serve`) on the amphp event loop | PHP-only, no third-party service; fans out through `marko/pubsub` (Redis or Postgres) |
| [`marko/broadcasting-mercure`](/docs/packages/broadcasting-mercure/) | A Mercure hub (built into FrankenPHP/Caddy, or standalone) | Zero extra infrastructure on FrankenPHP; Server-Sent Events in the browser |
| [`marko/broadcasting-pusher`](/docs/packages/broadcasting-pusher/) | Hosted Pusher, Soketi, or Laravel Reverb | WebSockets, Laravel Echo / pusher-js clients |

Without a driver, resolving `BroadcasterInterface` throws `NoDriverException` listing the packages above.

:::tip
For a handful of viewers (admin dashboards, job progress), [`marko/sse`](/docs/packages/sse/) streams directly from PHP with no hub. See [When to Use SSE vs. Broadcasting](/docs/packages/sse/#when-to-use-sse-vs-broadcasting).
:::

## Usage

### Broadcasting an Event

Inject `BroadcasterInterface` and broadcast to a channel. A plain string is a public channel.

```php
use Marko\Broadcasting\BroadcasterInterface;
use Marko\Broadcasting\PrivateChannel;

class SeatService
{
    public function __construct(
        private BroadcasterInterface $broadcaster,
    ) {}

    public function sell(int $showId, string $seat): void
    {
        // ... persist the sale ...

        // Public: anyone watching the show sees the seat map update
        $this->broadcaster->broadcast("shows.$showId", 'seat.sold', ['seat' => $seat]);

        // Private: only subscribers authorized for this channel receive it
        $this->broadcaster->broadcast(
            new PrivateChannel("orders.$showId"),
            'order.updated',
            ['seat' => $seat],
            id: 'evt-1001',
        );
    }
}
```

`$data` must be JSON-serializable. The optional `$id` is passed to drivers that support event ids and replay (Mercure); the Pusher protocol has no event ids, so the Pusher driver does not transmit it.

### Broadcastable Event Objects

Implement `BroadcastableInterface` to describe an event once and broadcast it with `dispatch()`. It is sent to every listed channel. Nothing is broadcast implicitly --- implementing the interface does not hook into the event dispatcher.

```php
use Marko\Broadcasting\BroadcastableInterface;
use Marko\Broadcasting\PrivateChannel;

readonly class OrderShipped implements BroadcastableInterface
{
    public function __construct(
        public int $orderId,
        public int $customerId,
    ) {}

    public function channels(): array
    {
        return ['orders', new PrivateChannel("customers.$this->customerId")];
    }

    public function event(): string
    {
        return 'order.shipped';
    }

    public function payload(): array
    {
        return ['orderId' => $this->orderId];
    }
}

$broadcaster->dispatch(new OrderShipped(orderId: 7, customerId: 42));
```

### Authorizing Private Channels

Subscribers to a `PrivateChannel` must be authorized. Create a class implementing `ChannelAuthorizerInterface` and mark it with `#[BroadcastChannel]`. Classes anywhere in a module's `src/` directory are discovered automatically.

```php title="app/shop/src/Broadcasting/CustomerChannelAuthorizer.php"
use Marko\Authentication\AuthenticatableInterface;
use Marko\Broadcasting\Attributes\BroadcastChannel;
use Marko\Broadcasting\ChannelAuthorizerInterface;

#[BroadcastChannel('customers.{customerId}')]
class CustomerChannelAuthorizer implements ChannelAuthorizerInterface
{
    public function authorize(
        ?AuthenticatableInterface $user,
        array $params,
    ): bool {
        return $user !== null && (string) $user->getAuthIdentifier() === $params['customerId'];
    }
}
```

Patterns match dot-separated channel names. Each `{name}` placeholder matches exactly one segment and is passed to the authorizer as `$params['name']`, so `customers.{customerId}` matches `customers.42` but not `customers.4.2`. A placeholder captures only letters, digits and `_ - = @` (`ChannelDefinition::PLACEHOLDER_PATTERN`), and the whole name must match, so `customers.1{x}`, `customers.1,2` or a name with a trailing newline matches no pattern and is refused as having no authorizer. `$user` is `null` for guests. Authorizers are resolved from the container, so they can inject dependencies through their constructors.

Drivers call `ChannelRegistry::authorize($channelName, $user)` when they issue subscriber credentials (the Mercure subscriber token, the Pusher `/broadcasting/auth` endpoint). A private channel with no matching authorizer is **denied loudly** with a `ChannelAuthorizationException` --- private channels are never open by default.

### Presence Channels

A `PresenceChannel` is a private channel whose subscribers also know who else is subscribed: "who's online in this room", typing indicators, collaborative cursors. Subscribers must be authorized like a private channel, and the authorizer also returns the member's identity and the public info other members see.

```php
use Marko\Broadcasting\PresenceChannel;

$broadcaster->broadcast(new PresenceChannel("rooms.$roomId"), 'message.posted', ['text' => $text]);
```

Authorize presence channels with a class implementing `PresenceChannelAuthorizerInterface`. Return a `PresenceMember` to admit the user, or `null` to deny:

```php title="app/chat/src/Broadcasting/RoomPresenceAuthorizer.php"
use Marko\Authentication\AuthenticatableInterface;
use Marko\Broadcasting\Attributes\BroadcastChannel;
use Marko\Broadcasting\PresenceChannelAuthorizerInterface;
use Marko\Broadcasting\PresenceMember;

#[BroadcastChannel('rooms.{roomId}')]
class RoomPresenceAuthorizer implements PresenceChannelAuthorizerInterface
{
    public function __construct(
        private RoomMemberRepository $roomMemberRepository,
    ) {}

    public function authorize(
        ?AuthenticatableInterface $user,
        array $params,
    ): ?PresenceMember {
        $roomMember = $user === null
            ? null
            : $this->roomMemberRepository->find((int) $params['roomId'], $user->getAuthIdentifier());

        if ($roomMember === null) {
            return null;
        }

        return new PresenceMember(
            id: $user->getAuthIdentifier(),
            info: ['name' => $roomMember->displayName],
        );
    }
}
```

`PresenceMember::$info` is shared with every member of the channel, so put only public, scalar values in it (`array<string, scalar|null>`). An empty-string id throws a `BroadcastException`.

Drivers call `ChannelRegistry::authorizePresence($channelName, $user)` for presence subscriptions. The rules match private channels: a presence channel with no matching authorizer is denied loudly with a `ChannelAuthorizationException`.

One pattern serves exactly one kind of channel. A presence channel whose pattern matches a `ChannelAuthorizerInterface` (or a private channel whose pattern matches a `PresenceChannelAuthorizerInterface`) throws a `ChannelAuthorizationException` naming the authorizer, and registering both kinds of authorizer for the same pattern is a duplicate-pattern error. Use distinct patterns, e.g. `rooms.{roomId}` for presence and `rooms.{roomId}.admin` for private.

Presence channels need a driver that can track members. The [Pusher driver](/docs/packages/broadcasting-pusher/#presence-channels) supports them. The Mercure and amphp drivers throw a `BroadcastException` when given a `PresenceChannel`; they never fall back to treating it as private or public.

### Testing

Use `FakeBroadcaster` from [`marko/testing`](/docs/packages/testing/#fakebroadcaster):

```php
use Marko\Testing\Fake\FakeBroadcaster;

$broadcaster = new FakeBroadcaster();
$service = new SeatService($broadcaster);

$service->sell(42, 'A1');

$broadcaster->assertBroadcast('shows.42', 'seat.sold', fn (array $data): bool => $data['seat'] === 'A1');
```

## API Reference

### BroadcasterInterface

```php
use Marko\Broadcasting\BroadcasterInterface;

/** @throws BroadcastException */
public function broadcast(string|Channel $channel, string $event, array $data, ?string $id = null): void;

/** @throws BroadcastException */
public function dispatch(BroadcastableInterface $broadcastable): void;
```

### Channel, PrivateChannel and PresenceChannel

```php
use Marko\Broadcasting\Channel;
use Marko\Broadcasting\PresenceChannel;
use Marko\Broadcasting\PrivateChannel;

readonly class Channel
{
    public function __construct(public string $name); // throws BroadcastException when empty or unsafe
    public static function from(string|Channel $channel): Channel;
    public function isPrivate(): bool; // false
    public function isPresence(): bool; // false
}

readonly class PrivateChannel extends Channel {} // isPrivate(): true
readonly class PresenceChannel extends Channel {} // isPrivate(): true, isPresence(): true
```

Channel names must not contain `{ } * ,`, whitespace or control characters (`Channel::FORBIDDEN_CHARACTERS_PATTERN`). Drivers use these as URI-template or topic-selector syntax, so a name like `customers.1{x}` could otherwise grant access to many channels. The constructor throws `BroadcastException::unsafeChannelName()` for them.

Drivers check `isPresence()` before `isPrivate()`, because a presence channel also requires authorization.

### PresenceMember

```php
use Marko\Broadcasting\PresenceMember;

readonly class PresenceMember
{
    /** @param array<string, scalar|null> $info Public info shared with other members */
    public function __construct(public string|int $id, public array $info = []); // throws BroadcastException when $id is ''
}
```

### BroadcastableInterface

```php
use Marko\Broadcasting\BroadcastableInterface;

/** @return list<string|Channel> */
public function channels(): array;
public function event(): string;
/** @return array<string, mixed> */
public function payload(): array;
```

### #[BroadcastChannel] and ChannelAuthorizerInterface

```php
use Marko\Broadcasting\Attributes\BroadcastChannel;
use Marko\Broadcasting\ChannelAuthorizerInterface;

#[BroadcastChannel(pattern: 'shows.{showId}')]

/** @param array<string, string> $params */
public function authorize(?AuthenticatableInterface $user, array $params): bool;
```

### PresenceChannelAuthorizerInterface

```php
use Marko\Broadcasting\PresenceChannelAuthorizerInterface;

/**
 * @param array<string, string> $params
 * @return PresenceMember|null The member to announce, or null to deny
 */
public function authorize(?AuthenticatableInterface $user, array $params): ?PresenceMember;
```

Registered with the same `#[BroadcastChannel]` attribute. A class implements one authorizer interface or the other.

### ChannelRegistry

```php
use Marko\Broadcasting\ChannelRegistry;

/** @throws ChannelAuthorizationException when no authorizer matches, or the match is a presence authorizer */
public function authorize(string $channelName, ?AuthenticatableInterface $user): bool;

/** @throws ChannelAuthorizationException when no authorizer matches, or the match is a private-channel authorizer */
public function authorizePresence(string $channelName, ?AuthenticatableInterface $user): ?PresenceMember;

/** @return list<string> */
public function patterns(): array;
```

A shared singleton; authorizers are discovered on first use.

### Exceptions

| Exception | Thrown when |
|---|---|
| `BroadcastException` | Empty or unsafe channel name (`unsafeChannelName()`), empty event name, empty presence member id, unencodable payload, a driver request fails, a channel name is invalid for the driver, or a driver without presence support is given a `PresenceChannel` (`presenceChannelsUnsupported()`) |
| `ChannelAuthorizationException` | A private or presence channel has no authorizer, the matching authorizer serves the other channel kind, an authorizer is registered twice for one pattern, or a `#[BroadcastChannel]` class implements neither authorizer interface |
| `NoDriverException` | `BroadcasterInterface` is resolved with no driver installed |

`presenceChannelsUnsupported()` returns an `UnsupportedBroadcastFeatureException`. It extends `BroadcastException` and implements `Marko\Core\Exceptions\UnsupportedFeatureExceptionInterface`, so existing `catch (BroadcastException)` blocks keep working.

## Related Packages

- [`marko/broadcasting-amphp`](/docs/packages/broadcasting-amphp/) --- self-hosted async SSE server driver
- [`marko/broadcasting-mercure`](/docs/packages/broadcasting-mercure/) --- Mercure hub driver
- [`marko/broadcasting-pusher`](/docs/packages/broadcasting-pusher/) --- Pusher-protocol driver (Pusher, Soketi, Laravel Reverb)
- [`marko/sse`](/docs/packages/sse/) --- in-process Server-Sent Events for low-concurrency streams
- [`marko/testing`](/docs/packages/testing/) --- `FakeBroadcaster`
