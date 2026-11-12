---
title: marko/clock
description: PSR-20 system clock --- inject ClockInterface instead of calling time() so time-dependent code can be tested deterministically.
---

PSR-20 system clock --- inject `ClockInterface` instead of calling `time()` so time-dependent code can be tested deterministically. The package binds the standard `Psr\Clock\ClockInterface` to `SystemClock`, which reads the system time. There is no Marko-specific clock interface: PSR-20 is already a single explicit method, and depending on it keeps your code compatible with any other PSR-20 clock (Symfony's or Lcobucci's, for example). In tests, swap in [`FakeClock`](/docs/packages/testing/#fakeclock) from `marko/testing`.

## Installation

```bash
composer require marko/clock
```

The module binds `ClockInterface` to `SystemClock` as a singleton. There is no configuration file.

## Usage

### Injecting the Clock

Type-hint `Psr\Clock\ClockInterface` in a constructor and call `now()` wherever you would have called `time()` or `new DateTimeImmutable()`:

```php
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

class InvitationService
{
    public function __construct(
        private ClockInterface $clock,
    ) {}

    public function expiresAt(): DateTimeImmutable
    {
        return $this->clock->now()->modify('+7 days');
    }

    public function isExpired(Invitation $invitation): bool
    {
        return $invitation->expiresAt < $this->clock->now();
    }
}
```

For a Unix timestamp, use `$this->clock->now()->getTimestamp()`.

### Timezone

By default `SystemClock` returns times in PHP's default timezone (`date_default_timezone_get()`), read each time `now()` is called. To pin a timezone, set the `timezone` constructor argument of `SystemClock` in your module. The parameter is a union type (`DateTimeZone|string|null`), so the value must be a closure:

```php title="app/core/module.php"
use Marko\Clock\SystemClock;
use Marko\Core\Container\ContainerInterface;

return [
    'arguments' => [
        SystemClock::class => [
            'timezone' => fn (ContainerInterface $container): string => 'UTC',
        ],
    ],
];
```

The constructor accepts a `DateTimeZone` or a timezone name. An unknown name throws `DateInvalidTimeZoneException`.

You don't need to pin the clock to get consistent stored times. Entity datetimes and the timestamps Marko packages write to the database (queue jobs, failed jobs, token expiry, notifications, webhook attempts) are converted to the [database timezone](/docs/packages/database/#datetimes-and-timezones) before they are stored, whatever zone the clock returns.

### Testing

Pass a `FakeClock` wherever the code under test expects a `ClockInterface`. It is frozen until you move it, so boundaries can be checked exactly:

```php
use Marko\Testing\Fake\FakeClock;

it('expires an invitation after seven days', function (): void {
    $clock = new FakeClock('2026-01-01 12:00:00 UTC');
    $service = new InvitationService($clock);
    $invitation = new Invitation(expiresAt: $service->expiresAt());

    $clock->travel('+7 days');
    expect($service->isExpired($invitation))->toBeFalse();

    $clock->travel('+1 second');
    expect($service->isExpired($invitation))->toBeTrue();
});
```

### Packages Using the Clock

These packages take an injected `ClockInterface` and so require `marko/clock`:

- [`marko/session`](/docs/packages/session/) --- session cookie expiry
- [`marko/session-file`](/docs/packages/session-file/) and [`marko/session-database`](/docs/packages/session-database/) --- `last_activity` and garbage collection
- [`marko/authentication-token`](/docs/packages/authentication-token/) --- personal access token expiry checks, and the default expiry and `created_at` of new tokens
- [`marko/authentication`](/docs/packages/authentication/) --- remember-token expiry and remember-cookie expiry
- [`marko/errors-simple`](/docs/packages/errors-simple/) and [`marko/errors-advanced`](/docs/packages/errors-advanced/) --- `ErrorReport` timestamps (`ErrorReport::fromThrowable()` in [`marko/errors`](/docs/packages/errors/) takes the timestamp as an argument)
- [`marko/log`](/docs/packages/log/) --- the `log:clear` age cutoff
- [`marko/log-file`](/docs/packages/log-file/) --- record times and the daily file name
- [`marko/testing`](/docs/packages/testing/) --- `TestClient`'s `REQUEST_TIME` and cookie expiry (falls back to `SystemClock` when no clock is bound)

[`marko/admin-auth`](/docs/packages/admin-auth/) event timestamps come from the `marko/database` repository's current instant rather than a clock of its own.
- [`marko/webhook`](/docs/packages/webhook/) --- signature timestamps, the freshness window, and attempt times
- [`marko/cache-array`](/docs/packages/cache-array/) and [`marko/cache-file`](/docs/packages/cache-file/) --- cache entry expiry and `created_at`
- [`marko/cache-redis`](/docs/packages/cache-redis/) --- the reported `expiresAt()` of a cache item (Redis itself enforces the TTL)
- [`marko/ratelimiter`](/docs/packages/ratelimiter/) --- `Retry-After`
- [`marko/page-cache-file`](/docs/packages/page-cache-file/) --- cached page expiry
- [`marko/notification`](/docs/packages/notification/) and [`marko/notification-database`](/docs/packages/notification-database/) --- `created_at` and `read_at` timestamps
- [`marko/broadcasting-amphp`](/docs/packages/broadcasting-amphp/) --- subscriber token expiry, event id timestamps, and replay buffer eviction
- [`marko/broadcasting-mercure`](/docs/packages/broadcasting-mercure/) --- subscriber JWT and cookie expiry
- [`marko/broadcasting-pusher`](/docs/packages/broadcasting-pusher/) --- request signature timestamps
- [`marko/media`](/docs/packages/media/) --- the `YYYY/MM` upload path
- [`marko/sse`](/docs/packages/sse/) --- stream heartbeats and timeouts (`SseStream` defaults to `SystemClock` when you construct it without one)
- [`marko/database`](/docs/packages/database/) --- `#[Timestamps]` values and generated migration file names
- [`marko/queue`](/docs/packages/queue/) --- the worker's failed-job time
- [`marko/queue-database`](/docs/packages/queue-database/) --- job availability, delays, reservation expiry and failed-job time
- [`marko/scheduler`](/docs/packages/scheduler/) --- which tasks are due, the `schedule:work` loop, and overlap-mutex expiry

## API Reference

### SystemClock

```php
use Marko\Clock\SystemClock;

public function __construct(DateTimeZone|string|null $timezone = null);
public function now(): DateTimeImmutable;
```

### ClockInterface

```php
use Psr\Clock\ClockInterface;

public function now(): DateTimeImmutable;
```

## Related Packages

- [`marko/testing`](/docs/packages/testing/) --- `FakeClock` for deterministic tests
