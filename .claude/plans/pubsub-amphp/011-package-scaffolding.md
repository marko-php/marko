# Task 011: Package Scaffolding and module.php Bindings

**Status**: completed
**Depends on**: 006, 008
**Retry count**: 0

## Description
Finalize all package scaffolding: ensure every new package has correct `composer.json`, `module.php` with bindings, `Pest.php` test config, and package structure tests. Wire the DI bindings so installing a driver package automatically binds the pub/sub interfaces.

## Context
- Each driver package's `module.php` binds `PublisherInterface` and `SubscriberInterface` to its implementations
- Follow existing patterns from `packages/cache-redis/module.php` and `packages/queue-rabbitmq/module.php`
- All packages need a `PackageScaffoldingTest.php` (or equivalent) that verifies composer.json structure
- `marko/pubsub` has `bindings => []` (interface package, no implementations)
- `marko/amphp` has `bindings => []` (foundation, not implementing pubsub interfaces)
- `marko/pubsub-redis` binds both interfaces to Redis implementations
- `marko/pubsub-pgsql` binds both interfaces to PgSql implementations
- Installing both drivers would create a binding conflict — this is expected Marko behavior (user picks one via app module)

## Requirements (Test Descriptions)
- [ ] `it has valid module.php for marko/pubsub with empty bindings`
- [ ] `it has valid module.php for marko/amphp with empty bindings`
- [ ] `it has valid module.php for marko/pubsub-redis binding PublisherInterface and SubscriberInterface`
- [ ] `it has valid module.php for marko/pubsub-pgsql binding PublisherInterface and SubscriberInterface`
- [ ] `it has Pest.php config file in each package test directory`
- [ ] `it has PackageScaffoldingTest or equivalent in each package`

## Acceptance Criteria
- All requirements have passing tests
- All four packages have complete, correct module.php files
- Bindings follow exact same pattern as existing drivers
- All composer.json files have correct `extra.marko.module = true`
- Test directories have Pest.php configs

## Implementation Notes

### module.php Files

```php
// packages/pubsub/module.php
return ['bindings' => []];

// packages/amphp/module.php
return ['bindings' => []];

// packages/pubsub-redis/module.php
return [
    'bindings' => [
        PublisherInterface::class => RedisPublisher::class,
        SubscriberInterface::class => RedisSubscriber::class,
    ],
];

// packages/pubsub-pgsql/module.php
return [
    'bindings' => [
        PublisherInterface::class => PgSqlPublisher::class,
        SubscriberInterface::class => PgSqlSubscriber::class,
    ],
];
```

### Composer.json Repositories (for dev)
Each driver needs path repositories to its dependencies:
```json
{
    "repositories": [
        {"type": "path", "url": "../core"},
        {"type": "path", "url": "../config"},
        {"type": "path", "url": "../pubsub"},
        {"type": "path", "url": "../amphp"}
    ]
}
```
