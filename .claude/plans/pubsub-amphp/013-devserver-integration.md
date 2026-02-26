# Task 013: Dev-Server Integration — Auto-start pubsub:listen

**Status**: completed
**Depends on**: 004, 011
**Retry count**: 0

## Description
Integrate pub/sub with the dev-server so `marko dev:up` auto-discovers and starts `pubsub:listen` as a managed process, and `marko dev:down` stops it. Detection is simple: check if `marko/pubsub` is installed via Composer. No config needed for the default case.

## Context
- Modify `packages/dev-server/` — this is an existing package
- Follow existing detection pattern: `DockerDetector` checks for compose files, `FrontendDetector` checks for package.json
- New `PubSubDetector` checks if `marko/pubsub` package is installed (class exists or composer.lock check)
- `DevUpCommand` already iterates detectors and starts processes via `ProcessManager`
- `dev.php` config has a boolean toggle for each service (docker, frontend) — add `pubsub` toggle
- The pubsub listener process command is: `php vendor/bin/marko pubsub:listen` (or however the CLI invokes commands)
- dev:down already stops all managed processes via PidFile — pubsub:listen gets stopped automatically

## Requirements (Test Descriptions)
- [ ] `it creates PubSubDetector that checks if marko/pubsub is installed`
- [ ] `it returns pubsub:listen command string when package is detected`
- [ ] `it returns null when marko/pubsub is not installed`
- [ ] `it adds pubsub toggle to dev config with default true`
- [ ] `it starts pubsub:listen as managed process in DevUpCommand when detected`
- [ ] `it skips pubsub process when pubsub config is false`

## Acceptance Criteria
- All requirements have passing tests
- Existing dev-server tests still pass (no regression)
- PubSubDetector follows identical pattern to DockerDetector/FrontendDetector
- pubsub:listen appears in dev:up output when detected
- No pubsub process when package is not installed
- Config override at `dev.pubsub = false` disables auto-start

## Implementation Notes

### File Structure
```
packages/dev-server/
  src/
    Detection/
      DockerDetector.php      (existing)
      FrontendDetector.php    (existing)
      PubSubDetector.php      (new)
    Command/
      DevUpCommand.php        (modify)
```

### PubSubDetector
```php
readonly class PubSubDetector
{
    public function __construct(
        private string $projectRoot,
    ) {}

    public function detect(): ?string
    {
        // Check if the pubsub package is installed
        // Simple: check if the interface class exists (autoloaded)
        if (!class_exists(\Marko\PubSub\PublisherInterface::class)) {
            return null;
        }

        return 'marko pubsub:listen';
    }
}
```

Note: Using `class_exists()` is clean — if the package is installed via Composer, its classes are autoloaded. No need to parse composer.lock.

### DevUpCommand Changes
Add pubsub detection after frontend detection, before custom processes:

```php
// Pub/Sub listener
if ($pubsubConfig !== false) {
    $pubsubCommand = is_string($pubsubConfig)
        ? $pubsubConfig
        : $this->pubsubDetector->detect();

    if ($pubsubCommand !== null) {
        $output->writeLine("  Starting pub/sub listener: $pubsubCommand");
        $pid = $this->processManager->start('pubsub', $pubsubCommand);
        $entries[] = new ProcessEntry(
            name: 'pubsub',
            pid: $pid,
            command: $pubsubCommand,
            port: 0,
            startedAt: date('c'),
        );
    }
}
```

### Config Change
```php
// config/dev.php
return [
    'port' => 8000,
    'detach' => false,
    'docker' => true,
    'frontend' => true,
    'pubsub' => true,       // <-- new
    'processes' => [],
];
```

### Constructor Change
DevUpCommand needs `PubSubDetector` injected:
```php
public function __construct(
    private ConfigRepositoryInterface $config,
    private DockerDetector $dockerDetector,
    private FrontendDetector $frontendDetector,
    private PubSubDetector $pubsubDetector,    // <-- new
    private PidFile $pidFile,
    private ProcessManager $processManager,
) {}
```
