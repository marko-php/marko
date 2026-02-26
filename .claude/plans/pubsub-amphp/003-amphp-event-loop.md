# Task 003: Create marko/amphp Event Loop Lifecycle and Config

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the `marko/amphp` package that provides Marko-specific integration for the Revolt event loop. This is the async foundation that both pub/sub drivers depend on. Manages event loop lifecycle (boot/shutdown), provides config conventions for async services, and integrates with the DI container.

## Context
- New package at `packages/amphp/`
- Depends on `amphp/amp ^3`, `revolt/event-loop ^1`, `marko/core`, `marko/config`
- Revolt's `EventLoop` is a static API — this package wraps it for Marko-aware lifecycle
- The `EventLoopRunner` runs the event loop with proper signal handling and shutdown
- `AmphpConfig` wraps config for shared settings
- This package does NOT abstract over Revolt — it integrates Revolt into Marko's DI and lifecycle
- composer.json type is `marko-module`

## Requirements (Test Descriptions)
- [ ] `it has valid composer.json with name marko/amphp and amphp/amp dependency`
- [ ] `it creates EventLoopRunner with run method that starts the Revolt event loop`
- [ ] `it creates EventLoopRunner with stop method that stops the event loop`
- [ ] `it tracks running state via isRunning method`
- [ ] `it creates AmphpConfig wrapping ConfigRepositoryInterface`
- [ ] `it reads shutdown timeout from amphp.shutdown_timeout config key`
- [ ] `it provides default config file with shutdown_timeout value`

## Acceptance Criteria
- All requirements have passing tests
- EventLoopRunner manages Revolt lifecycle without leaking implementation details
- Config file at `packages/amphp/config/amphp.php`
- All files have `declare(strict_types=1)` and proper type declarations
- Protected hooks for testability where appropriate

## Implementation Notes

### File Structure
```
packages/amphp/
  composer.json
  module.php
  config/
    amphp.php
  src/
    EventLoopRunner.php
    AmphpConfig.php
    Exceptions/
      AmphpException.php
  tests/
    Pest.php
    PackageScaffoldingTest.php
    EventLoopRunnerTest.php
    AmphpConfigTest.php
```

### EventLoopRunner Design
```php
class EventLoopRunner
{
    private bool $running = false;

    public function __construct(
        private AmphpConfig $config,
    ) {}

    public function run(): void
    // Calls EventLoop::run(), sets running = true, handles shutdown

    public function stop(): void
    // Stops the event loop gracefully

    public function isRunning(): bool
}
```

### Config
```php
// config/amphp.php
return [
    'shutdown_timeout' => (int) ($_ENV['AMPHP_SHUTDOWN_TIMEOUT'] ?? 30),
];
```
