# Task 005: Create marko/pubsub-redis Connection and Publisher

**Status**: completed
**Depends on**: 001, 002, 003
**Retry count**: 0

## Description
Create the `marko/pubsub-redis` package with the Redis connection wrapper and publisher. The publisher uses `amphp/redis` `RedisClient::publish()` for non-blocking message publishing. The connection wraps `RedisConfig` and provides lazy-connected clients.

## Context
- New package at `packages/pubsub-redis/`
- Follow sibling conventions from `packages/cache-redis/` and `packages/queue-rabbitmq/`
- amphp/redis uses URI-based config: `RedisConfig::fromUri('tcp://host:port')`
- `RedisClient::publish(channel, message)` sends PUBLISH command
- Publishing uses `RedisClient` (general-purpose connection), subscribing uses `RedisSubscriber` (separate, dedicated connection)
- Channel names are prefixed using `pubsub.prefix` config value
- Namespace: `Marko\PubSub\Redis\`
- Class naming: `Redis{Component}` per sibling conventions

## Requirements (Test Descriptions)
- [x] `it has valid composer.json with name marko/pubsub-redis and required dependencies`
- [x] `it creates RedisPubSubConnection with host, port, password, database properties`
- [x] `it creates RedisClient lazily on first use via protected createClient hook`
- [x] `it creates RedisConnector lazily on first use via protected createConnector hook`
- [x] `it provides disconnect and isConnected methods`
- [x] `it creates RedisPublisher implementing PublisherInterface`
- [x] `it publishes message to prefixed channel via RedisClient`
- [x] `it provides default config file with redis connection settings`

## Acceptance Criteria
- All requirements have passing tests
- RedisPubSubConnection follows the same pattern as cache-redis RedisConnection
- Protected `createClient()` and `createConnector()` for testability (anonymous class override)
- Config at `packages/pubsub-redis/config/pubsub-redis.php` with connection settings
- All sibling conventions followed (naming, visibility, structure)
- @throws tags where applicable

## Implementation Notes

### File Structure
```
packages/pubsub-redis/
  composer.json
  module.php
  config/
    pubsub-redis.php
  src/
    RedisPubSubConnection.php
    Driver/
      RedisPublisher.php
  tests/
    Pest.php
    PackageScaffoldingTest.php
    RedisPubSubConnectionTest.php
    Driver/
      RedisPublisherTest.php
```

### Connection Design
```php
class RedisPubSubConnection
{
    private ?RedisClient $client = null;
    private ?RedisConnector $connector = null;

    public function __construct(
        public readonly string $host = '127.0.0.1',
        public readonly int $port = 6379,
        public readonly ?string $password = null,
        public readonly int $database = 0,
        public readonly string $prefix = 'marko:',
    ) {}

    public function client(): RedisClient { /* lazy create */ }
    public function connector(): RedisConnector { /* lazy create, for subscriber */ }
    protected function createClient(): RedisClient { /* testability hook */ }
    protected function createConnector(): RedisConnector { /* testability hook */ }
    public function disconnect(): void
    public function isConnected(): bool
}
```

### amphp/redis API for Publishing
```php
// Config from URI
$config = RedisConfig::fromUri("tcp://{$this->host}:{$this->port}");
// Create client (auto-reconnecting)
$client = createRedisClient($config);
// Publish
$client->publish($prefixedChannel, $message->payload);
```

### Config
```php
// config/pubsub-redis.php
return [
    'host' => $_ENV['PUBSUB_REDIS_HOST'] ?? '127.0.0.1',
    'port' => (int) ($_ENV['PUBSUB_REDIS_PORT'] ?? 6379),
    'password' => $_ENV['PUBSUB_REDIS_PASSWORD'] ?? null,
    'database' => (int) ($_ENV['PUBSUB_REDIS_DATABASE'] ?? 0),
];
```
