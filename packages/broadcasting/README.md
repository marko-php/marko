# marko/broadcasting

Realtime broadcasting contracts --- send events to everyone watching a channel, with pluggable drivers that hold the connections outside PHP.

## Installation

```bash
composer require marko/broadcasting
```

Install a driver as well: `marko/broadcasting-mercure` or `marko/broadcasting-pusher`.

## Quick Example

```php
use Marko\Authentication\AuthenticatableInterface;
use Marko\Broadcasting\Attributes\BroadcastChannel;
use Marko\Broadcasting\ChannelAuthorizerInterface;
use Marko\Broadcasting\PrivateChannel;

$broadcaster->broadcast('shows.42', 'seat.sold', ['seat' => 'A1']);
$broadcaster->broadcast(new PrivateChannel('customers.7'), 'order.shipped', ['orderId' => 9]);

#[BroadcastChannel('customers.{customerId}')]
class CustomerChannelAuthorizer implements ChannelAuthorizerInterface
{
    public function authorize(
        ?AuthenticatableInterface $user,
        array $params,
    ): bool {
        return (string) $user?->getAuthIdentifier() === $params['customerId'];
    }
}
```

Presence channels ("who's online") use `PresenceChannel` with a `PresenceChannelAuthorizerInterface` that returns a `PresenceMember`; the Pusher driver supports them.

## Documentation

Full usage, API reference, and examples: [marko/broadcasting](https://marko.build/docs/packages/broadcasting/)
