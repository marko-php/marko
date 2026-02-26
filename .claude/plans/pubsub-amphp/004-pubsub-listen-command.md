# Task 004: Create pubsub:listen Command

**Status**: completed
**Depends on**: 003
**Retry count**: 0

## Description
Create the `pubsub:listen` CLI command that starts the Revolt event loop and runs the pub/sub listener process. This is the entry point for the async pub/sub runtime — it subscribes to configured channels and keeps the process alive to feed SSE connections. Lives in the `marko/amphp` package since it manages the event loop lifecycle.

## Context
- Follow existing command pattern in `packages/dev-server/src/Command/DevUpCommand.php`
- Uses `#[Command]` attribute for registration
- Implements `CommandInterface` with `execute(Input, Output): int`
- Depends on `EventLoopRunner` from task 003
- The command starts the event loop and blocks until stopped (Ctrl+C or signal)
- Mirrors `queue:work` pattern — describes what it does, not how
- Future companion commands: `pubsub:status`, `pubsub:channels`
- Should handle SIGINT/SIGTERM for graceful shutdown

## Requirements (Test Descriptions)
- [ ] `it has Command attribute with name pubsub:listen and description`
- [ ] `it implements CommandInterface`
- [ ] `it starts the event loop via EventLoopRunner when executed`
- [ ] `it outputs startup message to Output`
- [ ] `it returns 0 on successful completion`

## Acceptance Criteria
- All requirements have passing tests
- Command follows existing patterns (readonly class, constructor injection)
- Uses `#[Command]` attribute with name `pubsub:listen`
- Proper @throws tags
- Delegates to EventLoopRunner for loop lifecycle

## Implementation Notes

### File Location
```
packages/amphp/src/Command/PubSubListenCommand.php
```

### Command Design
```php
/** @noinspection PhpUnused */
#[Command(name: 'pubsub:listen', description: 'Start the pub/sub listener')]
readonly class PubSubListenCommand implements CommandInterface
{
    public function __construct(
        private EventLoopRunner $runner,
    ) {}

    public function execute(Input $input, Output $output): int
    {
        $output->writeLine('Starting pub/sub listener...');
        $output->writeLine('Press Ctrl+C to stop.');
        $this->runner->run();
        $output->writeLine('Listener stopped.');
        return 0;
    }
}
```
