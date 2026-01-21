# Plan: Cache Package

## Created
2026-01-21

## Status
pending

## Objective
Implement the cache layer for Marko framework with a clean interface/implementation split pattern, providing `marko/cache` (interfaces and cache infrastructure) and `marko/cache-file` (file-based driver implementation).

## Scope

### In Scope
- `marko/cache` package with interfaces, cache item value objects, and cache exceptions
  - `CacheInterface` - primary cache contract (get, set, has, delete, clear)
  - `CacheItemInterface` - cache item value object with value, key, TTL, metadata
  - `CacheConfig` - configuration loaded from config/cache.php
  - `CacheException` hierarchy (CacheException, InvalidKeyException, ItemNotFoundException)
  - CLI commands: `cache:clear`, `cache:status`
- `marko/cache-file` package with file-based cache driver implementation
  - `FileCacheDriver` - implements CacheInterface using filesystem storage
  - `FileCacheFactory` - factory for creating cache driver with config
  - Configurable cache directory (default: `storage/cache`)
  - File serialization with metadata (expiration, tags)
  - Atomic write operations for safety
  - Automatic garbage collection of expired items

### Out of Scope
- Redis/Memcached drivers (future packages: `marko/cache-redis`, `marko/cache-memcached`)
- Cache tagging/invalidation groups (future enhancement)
- Distributed cache locking
- Cache warming strategies
- Route caching integration (routing package responsibility)
- Attribute caching integration (core package responsibility)

## Success Criteria
- [ ] `CacheInterface` provides clean get/set/has/delete/clear contract
- [ ] `CacheItemInterface` encapsulates cached value with metadata (TTL, key, hit status)
- [ ] `CacheConfig` loads configuration from `config/cache.php`
- [ ] `FileCacheDriver` implements all cache operations using filesystem
- [ ] `cache:clear` clears all cached items
- [ ] `cache:status` shows cache statistics (item count, size, driver info)
- [ ] Loud error when no cache driver is installed
- [ ] Driver conflict handling if multiple drivers installed
- [ ] Expired items automatically not returned (lazy expiration)
- [ ] All tests passing
- [ ] Code follows project standards

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Package scaffolding (composer.json files for both packages) | - | pending |
| 002 | CacheException hierarchy | 001 | pending |
| 003 | CacheItemInterface and CacheItem value object | 001 | pending |
| 004 | CacheInterface contract | 003 | pending |
| 005 | CacheConfig class | 001 | pending |
| 006 | cache package module.php with CacheConfig binding | 005 | pending |
| 007 | FileCacheDriver implementation | 004 | pending |
| 008 | FileCacheFactory | 005, 007 | pending |
| 009 | cache-file module.php with bindings | 008 | pending |
| 010 | CLI: cache:clear command | 004 | pending |
| 011 | CLI: cache:status command | 004 | pending |
| 012 | Unit tests for cache package | 002, 003, 004 | pending |
| 013 | Unit tests for cache-file package | 007, 008 | pending |
| 014 | Integration tests | 010, 011 | pending |

## Architecture Notes

### Package Structure
```
packages/
  cache/                    # Interfaces + shared code
    src/
      Contracts/
        CacheInterface.php
        CacheItemInterface.php
      Config/
        CacheConfig.php
      Exceptions/
        CacheException.php
        InvalidKeyException.php
        ItemNotFoundException.php
      CacheItem.php
      Command/
        ClearCommand.php
        StatusCommand.php
    tests/
    composer.json
    module.php
  cache-file/               # File-based implementation
    src/
      Driver/
        FileCacheDriver.php
      Factory/
        FileCacheFactory.php
    tests/
    composer.json
    module.php
```

### Config Location
```php
// config/cache.php
return [
    'driver' => 'file',
    'path' => $_ENV['CACHE_PATH'] ?? 'storage/cache',
    'default_ttl' => 3600, // 1 hour
];
```

### Interface Design
```php
// CacheInterface - simple, focused contract
interface CacheInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    public function has(string $key): bool;

    public function delete(string $key): bool;

    public function clear(): bool;

    public function getItem(string $key): CacheItemInterface;

    public function getMultiple(array $keys, mixed $default = null): iterable;

    public function setMultiple(array $values, ?int $ttl = null): bool;

    public function deleteMultiple(array $keys): bool;
}
```

```php
// CacheItemInterface - value object for cache entries
interface CacheItemInterface
{
    public function getKey(): string;

    public function get(): mixed;

    public function isHit(): bool;

    public function expiresAt(): ?DateTimeInterface;
}
```

### File Cache Storage Format
```php
// Storage: storage/cache/{hashed_key}.cache
// File format: serialized array with metadata
[
    'value' => mixed,           // The cached value
    'expires_at' => ?int,       // Unix timestamp or null for no expiration
    'created_at' => int,        // Unix timestamp when cached
]
```

### Key Hashing
Cache keys are hashed to filesystem-safe names:
```php
private function hashKey(string $key): string
{
    return hash('xxh128', $key);
}
```

### Driver Conflict Handling
Only one driver package can be installed. If both `marko/cache-file` and `marko/cache-redis` are installed, the framework throws a loud error during boot:

```
BindingConflictException: Multiple implementations bound for CacheInterface.

Context: Both FileCacheDriver and RedisCacheDriver are attempting to bind.

Suggestion: Install only one cache driver package. Remove one with:
  composer remove marko/cache-file
  or
  composer remove marko/cache-redis
```

### No Driver Installed Handling
If `marko/cache` is installed without a driver, attempting to use cache features throws:

```
CacheException: No cache driver installed.

Context: Attempted to resolve CacheInterface but no implementation is bound.

Suggestion: Install a cache driver package:
  composer require marko/cache-file
  or
  composer require marko/cache-redis
```

### CLI Commands

**cache:clear**
```
$ marko cache:clear
Cache cleared successfully.
```

**cache:status**
```
$ marko cache:status
Cache Driver: file
Cache Path: /project/storage/cache
Items: 42
Total Size: 1.2 MB
```

### Module Bindings

**cache/module.php**
```php
return [
    'enabled' => true,
    'bindings' => [
        CacheConfig::class => CacheConfig::class,
    ],
];
```

**cache-file/module.php**
```php
return [
    'enabled' => true,
    'bindings' => [
        CacheInterface::class => function (ContainerInterface $container): CacheInterface {
            return $container->get(FileCacheFactory::class)->create();
        },
    ],
];
```

### Atomic File Writes
To prevent corruption from concurrent writes:
```php
private function write(string $path, string $data): bool
{
    $tempPath = $path . '.tmp.' . uniqid();

    if (file_put_contents($tempPath, $data, LOCK_EX) === false) {
        return false;
    }

    return rename($tempPath, $path);
}
```

### Garbage Collection Strategy
Expired items are handled lazily:
1. On `get()`: If item is expired, delete it and return default
2. On `cache:clear`: Delete all cache files
3. No background garbage collection (keeps implementation simple)

## Risks & Mitigations
- **Filesystem permissions**: Clear error messages when cache directory is not writable, with suggestion to check permissions
- **Disk space**: Status command shows total size; clear command available; applications can implement rotation policies
- **Concurrent access**: Atomic writes with temp files and rename; file locking for reads
- **Large values**: Serialize/unserialize handles most PHP types; document that resources cannot be cached
- **Key validation**: Reject keys with invalid characters (/, \, :, *, ?, ", <, >, |) with clear InvalidKeyException
