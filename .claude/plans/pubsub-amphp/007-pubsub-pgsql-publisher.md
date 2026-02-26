# Task 007: Create marko/pubsub-pgsql Connection and Publisher

**Status**: completed
**Depends on**: 001, 002, 003
**Retry count**: 0

## Description
Create the `marko/pubsub-pgsql` package with the PostgreSQL connection wrapper and publisher. The publisher uses `amphp/postgres` `PostgresConnection::notify()` for non-blocking NOTIFY. Zero additional infrastructure for Postgres users — just uses the existing database.

## Context
- New package at `packages/pubsub-pgsql/`
- Must follow identical sibling patterns as `pubsub-redis` (task 005)
- amphp/postgres uses `PostgresConfig` for connection config
- `PostgresConnectionPool` manages connections and has `notify(channel, payload)` method
- `Amp\Postgres\connect($config)` creates a single connection
- Publishing uses the pool (grab connection, NOTIFY, return to pool)
- Subscribing uses a dedicated persistent connection for LISTEN
- Channel names must be valid PostgreSQL identifiers (63-byte limit, case-folded to lowercase)
- Channel names are prefixed using `pubsub.prefix` config — but Postgres channels can't contain colons, so use underscores: `marko_` prefix
- Namespace: `Marko\PubSub\PgSql\`
- Class naming: `PgSql{Component}` per sibling conventions

## Requirements (Test Descriptions)
- [ ] `it has valid composer.json with name marko/pubsub-pgsql and required dependencies`
- [ ] `it creates PgSqlPubSubConnection with host, port, user, password, database properties`
- [ ] `it creates PostgresConfig lazily via protected createConfig hook`
- [ ] `it creates connection lazily via protected createConnection hook`
- [ ] `it provides disconnect and isConnected methods`
- [ ] `it creates PgSqlPublisher implementing PublisherInterface`
- [ ] `it publishes message via NOTIFY with prefixed channel name`
- [ ] `it provides default config file with pgsql connection settings`

## Acceptance Criteria
- All requirements have passing tests
- PgSqlPubSubConnection follows identical pattern to RedisPubSubConnection
- Protected hooks for testability (createConfig, createConnection)
- Config at `packages/pubsub-pgsql/config/pubsub-pgsql.php`
- Sibling consistency with pubsub-redis (identical method names, visibility, structure)
- @throws tags where applicable

## Implementation Notes

### File Structure
```
packages/pubsub-pgsql/
  composer.json
  module.php
  config/
    pubsub-pgsql.php
  src/
    PgSqlPubSubConnection.php
    Driver/
      PgSqlPublisher.php
  tests/
    Pest.php
    PackageScaffoldingTest.php
    PgSqlPubSubConnectionTest.php
    Driver/
      PgSqlPublisherTest.php
```

### Connection Design
```php
class PgSqlPubSubConnection
{
    private ?PostgresConnection $connection = null;

    public function __construct(
        public readonly string $host = '127.0.0.1',
        public readonly int $port = 5432,
        public readonly ?string $user = null,
        public readonly ?string $password = null,
        public readonly ?string $database = null,
        public readonly string $prefix = 'marko_',
    ) {}

    public function connection(): PostgresConnection { /* lazy create */ }
    protected function createConnection(): PostgresConnection { /* testability hook */ }
    protected function createConfig(): PostgresConfig { /* testability hook */ }
    public function disconnect(): void
    public function isConnected(): bool
}
```

### amphp/postgres API for Publishing
```php
$config = new PostgresConfig($this->host, $this->port, $this->user, $this->password, $this->database);
$connection = Amp\Postgres\connect($config);
$connection->notify($prefixedChannel, $message->payload);
```

### Config
```php
// config/pubsub-pgsql.php
return [
    'host' => $_ENV['PUBSUB_PGSQL_HOST'] ?? '127.0.0.1',
    'port' => (int) ($_ENV['PUBSUB_PGSQL_PORT'] ?? 5432),
    'user' => $_ENV['PUBSUB_PGSQL_USER'] ?? null,
    'password' => $_ENV['PUBSUB_PGSQL_PASSWORD'] ?? null,
    'database' => $_ENV['PUBSUB_PGSQL_DATABASE'] ?? null,
];
```
