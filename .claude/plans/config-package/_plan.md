# Plan: Configuration Package (marko/config)

## Created
2026-01-21

## Status
in_progress

## Objective
Create the `marko/config` package providing a centralized, type-safe configuration system for Marko applications. The package will load PHP configuration files from modules and application directories, merge them with proper priority ordering, support environment variable integration, and provide scoped configuration for multi-tenant applications.

## Scope

### In Scope
- `ConfigRepositoryInterface` - Contract for configuration access
- `ConfigRepository` - Central registry holding merged configuration
- `ConfigLoader` - Loads PHP config files and validates structure
- `ConfigMerger` - Deep array merging with proper type handling
- `ConfigDiscovery` - Discovers config/ directories from all modules
- Config file merging with priority: vendor < modules < app
- Environment variable access via config files (`$_ENV`, `getenv()`)
- Scoped configuration support (default -> tenant -> specific)
- Type-safe configuration access with dot notation (`config('key.nested.value')`)
- Application integration (discover and merge config during boot)
- Exception classes with helpful error messages

### Out of Scope
- `.env` file parsing (use `vlucas/phpdotenv` or similar before boot)
- Config caching (optimization for future version)
- Runtime config modification (config is immutable after boot)
- Config validation schema (packages validate their own config like DatabaseConfig)
- Hot reloading of config files

## Success Criteria
- [ ] Config files discovered from module `config/` directories
- [ ] Config merges with correct priority (app wins over modules wins over vendor)
- [ ] `config('key.nested.value')` provides type-safe access
- [ ] `config('key.nested.value', 'default')` returns default when not set
- [ ] Environment variables work in config files via `$_ENV` and `getenv()`
- [ ] Scoped config accessible via `config('key', scope: 'tenant-123')`
- [ ] ConfigNotFoundException thrown with helpful message when accessing missing required config
- [ ] Integration with Application boot process
- [ ] All tests passing
- [ ] Code follows project standards (strict types, no final, etc.)

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Package scaffolding (composer.json, module.php) | - | completed |
| 002 | Exception classes | 001 | completed |
| 003 | ConfigMerger (deep array merge logic) | 001 | completed |
| 004 | ConfigLoader (load and validate PHP config files) | 002 | completed |
| 005 | ConfigRepositoryInterface | 001 | completed |
| 006 | ConfigRepository implementation | 003, 004, 005 | completed |
| 007 | ConfigDiscovery (scan module config/ directories) | 004 | completed |
| 008 | Scoped configuration support | 006 | completed |
| 009 | Application integration | 006, 007 | completed |
| 010 | Integration tests | 009 | pending |
| 011 | Package README | 010 | pending |

## Architecture Notes

### Config File Format
Config files are PHP files that return arrays:

```php
// config/database.php
return [
    'driver' => $_ENV['DB_DRIVER'] ?? 'mysql',
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
    'connections' => [
        'default' => [...],
        'readonly' => [...],
    ],
];
```

### Config Discovery Order
Configuration is discovered from module `config/` directories in the same priority order as bindings:

1. `vendor/*/config/` - Package defaults (lowest priority)
2. `modules/*/config/` - Third-party modules (middle priority)
3. `app/*/config/` - Application modules (high priority)
4. `config/` (project root) - Application-wide config (highest priority)

Later sources override earlier sources via deep merge.

### Deep Merge Rules
- Scalar values: later wins
- Indexed arrays: later replaces entirely
- Associative arrays: recursively merged
- Null values: removes the key (allows unsetting)

```php
// vendor/acme/blog/config/blog.php
return [
    'posts_per_page' => 10,
    'cache' => ['driver' => 'file', 'ttl' => 3600],
];

// config/blog.php (app)
return [
    'posts_per_page' => 25,
    'cache' => ['ttl' => 7200], // Only overrides ttl, keeps driver
];

// Result:
[
    'posts_per_page' => 25,     // App wins
    'cache' => ['driver' => 'file', 'ttl' => 7200],  // Merged
]
```

### Accessing Configuration
```php
// Via ConfigRepository
$config = $container->get(ConfigRepositoryInterface::class);
$host = $config->get('database.host');
$host = $config->get('database.host', 'localhost'); // with default

// Type-safe methods
$port = $config->getInt('database.port');
$debug = $config->getBool('app.debug');
$hosts = $config->getArray('cache.hosts');

// Check existence
if ($config->has('feature.experimental')) {
    // ...
}
```

### Scoped Configuration (Multi-tenant)
For multi-tenant applications, configuration can cascade through scopes:

```php
// config/pricing.php
return [
    'default' => [
        'currency' => 'USD',
        'tax_rate' => 0.10,
    ],
    'scopes' => [
        'tenant-acme' => [
            'currency' => 'EUR',
            'tax_rate' => 0.20,
        ],
    ],
];

// Access
$currency = $config->get('pricing.currency'); // 'USD' (default)
$currency = $config->get('pricing.currency', scope: 'tenant-acme'); // 'EUR'
```

### Integration with Existing Config Objects
Packages like `marko/database` define their own injectable config objects (`DatabaseConfig`). These can use `ConfigRepository` internally:

```php
// Current pattern (loads file directly):
readonly class DatabaseConfig
{
    public function __construct(ProjectPaths $paths) {
        $configPath = $paths->config . '/database.php';
        $config = require $configPath;
        // ...
    }
}

// Future pattern (uses ConfigRepository):
readonly class DatabaseConfig
{
    public function __construct(ConfigRepositoryInterface $config) {
        $this->driver = $config->getString('database.driver');
        $this->host = $config->getString('database.host');
        // ...
    }
}
```

This is a non-breaking migration path. Existing packages continue working; they can adopt ConfigRepository when ready.

### Boot Sequence Integration
During Application boot (after module discovery, before bindings):

1. Create ConfigDiscovery
2. Scan all module `config/` directories
3. Load and merge all config files
4. Create ConfigRepository with merged config
5. Register ConfigRepository in container
6. Continue with binding registration

### Package Structure
```
packages/config/
  src/
    ConfigRepositoryInterface.php  # Contract
    ConfigRepository.php           # Implementation with dot notation access
    ConfigLoader.php               # Loads PHP files, validates arrays
    ConfigMerger.php               # Deep merge logic
    ConfigDiscovery.php            # Discovers config dirs from modules
    Exceptions/
      ConfigException.php          # Base exception
      ConfigNotFoundException.php  # Required config missing
  tests/
    Unit/
      ConfigMergerTest.php
      ConfigLoaderTest.php
      ConfigRepositoryTest.php
    Feature/
      ConfigDiscoveryTest.php
      ScopedConfigTest.php
  composer.json
  module.php
  README.md
```

### No Default Config in Package
Unlike other packages that might ship default config, `marko/config` itself has no config files. It only provides the infrastructure for loading config from other sources.

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| Config file syntax errors | ConfigLoader catches ParseError, throws ConfigException with file path and line number |
| Circular config references | Not supported; config files cannot reference other config values (keep it simple) |
| Large config causing memory issues | Config is loaded once at boot; for very large configs, use multiple smaller files |
| Performance of scanning all modules | Config discovery happens once at boot; could add caching layer in future |
| Environment variables not loaded | Document that .env parsing must happen before Application boot (in index.php) |
| Breaking existing DatabaseConfig pattern | Existing pattern continues working; ConfigRepository is additive |
