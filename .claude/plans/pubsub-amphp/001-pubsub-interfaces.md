# Task 001: Create marko/pubsub Interfaces and Value Objects

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the `marko/pubsub` interface package that defines the pub/sub contracts. This is the foundation that both Redis and PostgreSQL drivers will implement. Contains `PublisherInterface`, `SubscriberInterface`, `Subscription`, and `Message`.

## Context
- New package at `packages/pubsub/`
- Follow existing interface packages like `marko/queue` (see `packages/queue/src/QueueInterface.php`) and `marko/cache` (see `packages/cache/src/Contracts/CacheInterface.php`)
- `Subscription` implements `IteratorAggregate<int, Message>` so it works with `foreach ($subscription as $message)`
- `Message` is a readonly value object
- No dependencies beyond `marko/core`
- `composer.json` type is `marko-module`, needs `extra.marko.module = true`

## Requirements (Test Descriptions)
- [ ] `it has valid composer.json with name marko/pubsub and required fields`
- [ ] `it defines PublisherInterface with publish method accepting channel and Message`
- [ ] `it defines SubscriberInterface with subscribe method accepting variadic channels returning Subscription`
- [ ] `it defines SubscriberInterface with psubscribe method accepting variadic patterns returning Subscription`
- [ ] `it defines Subscription interface extending IteratorAggregate with cancel method`
- [ ] `it creates readonly Message value object with channel, payload, and optional pattern properties`
- [ ] `it creates Message with all properties accessible`

## Acceptance Criteria
- All requirements have passing tests
- Interfaces have proper PHPDoc with @throws where applicable
- All files have `declare(strict_types=1)`
- Type declarations on all parameters and returns
- composer.json has correct dependencies, autoload, and module config

## Implementation Notes

### File Structure
```
packages/pubsub/
  composer.json
  module.php
  src/
    PublisherInterface.php
    SubscriberInterface.php
    Subscription.php          (interface)
    Message.php               (readonly class)
  tests/
    Pest.php
    PackageScaffoldingTest.php
    MessageTest.php
```

### Interface Signatures
```php
// PublisherInterface
public function publish(string $channel, Message $message): void;

// SubscriberInterface
public function subscribe(string ...$channels): Subscription;
public function psubscribe(string ...$patterns): Subscription;

// Subscription (interface extending IteratorAggregate)
/** @extends IteratorAggregate<int, Message> */
public function getIterator(): Generator;
public function cancel(): void;

// Message (readonly value object)
public readonly string $channel;
public readonly string $payload;
public readonly ?string $pattern;
```
