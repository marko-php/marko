# Task 009: CLI Commands (clear, purge, status)

**Status**: completed
**Depends on**: 005, 006
**Retry count**: 0

## Description
Implement three CLI commands: `page-cache:clear`, `page-cache:purge <target>`, `page-cache:status`. Mirror the pattern in `packages/cache/src/Command/ClearCommand.php` and `StatusCommand.php`.

## Context
- Related files:
  - `packages/cache/src/Command/ClearCommand.php` (template)
  - `packages/cache/src/Command/StatusCommand.php` (template)
  - `packages/core/src/Attributes/Command.php`
  - `packages/core/src/Command/CommandInterface.php`, `Input.php`, `Output.php`
- Patterns to follow:
  - `readonly class` with `#[Command(name, description)]`
  - `/** @noinspection PhpUnused */` on the class (existing pattern)
  - Return 0 on success, 1 on failure
  - Plain `Output->writeLine(...)` for messages

## Requirements (Test Descriptions)
- [ ] `it prints success when ClearCommand clears the cache`
- [ ] `it prints failure and returns non-zero when ClearCommand fails to clear`
- [ ] `it purges by URL when PurgeCommand is invoked with a URL argument`
- [ ] `it purges by tag when PurgeCommand is invoked with --tag flag`
- [ ] `it returns non-zero when PurgeCommand has no target argument`
- [ ] `it prints driver name and storage path in StatusCommand output`

## Acceptance Criteria
- `src/Command/ClearCommand.php` with `#[Command(name: 'page-cache:clear', ...)]`
- `src/Command/PurgeCommand.php` with `#[Command(name: 'page-cache:purge', ...)]` — accepts a positional argument (URL or tag string) and a `--tag` flag (use `Input` API per existing commands)
- `src/Command/StatusCommand.php` with `#[Command(name: 'page-cache:status', ...)]` — shows `driver`, `path`. (File-driver-specific stats like page count are out of scope here; the status command is interface-package code so can't reach into file driver internals.)
- Tests in `tests/Unit/Command/` using fake `PageCacheInterface` and fake `Input`/`Output` (look for existing test patterns in `packages/cache/tests/`)
- Strict types declared
- All `@throws` documented

## Implementation Notes
- Check `packages/cache/tests/` for how existing CLI commands are tested — replicate the pattern.
- The `Input` API is at `packages/core/src/Command/Input.php` (constructor `__construct(array $arguments)` taking raw argv-style array). It exposes:
  - `getArgument(int $index): ?string` — positional arg after the command name (e.g., `getArgument(0)` returns the URL/tag string)
  - `hasOption(string $name): bool` — checks for `--tag` flag presence
  - `getOption(string $name): ?string` — for `--tag=value` style; returns `'true'` for boolean `--tag`
- Implementation: `PurgeCommand::execute()` calls `$input->getArgument(0)` for the target and `$input->hasOption('tag')` to decide between `purgeUrl()` and `purgeTag()`. If `getArgument(0)` is null, write an error message and return 1.
- For the test, build `Input` with raw argv arrays like `['bin/marko', 'page-cache:purge', '--tag', 'product-42']` (note: `Input` slices `[2:]` internally, so positional args start at `getArgument(0)`).
