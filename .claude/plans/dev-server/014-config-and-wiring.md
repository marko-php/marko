# Task 014: Add config/dev.php and wire configuration

**Status**: completed
**Depends on**: 011
**Retry count**: 0

## Description
Create the default `config/dev.php` configuration file for the dev-server package and ensure the DevUpCommand reads configuration correctly via `ConfigRepositoryInterface`. Verify the full config flow: defaults in config file, overrides via application config, CLI flag overrides on top.

## Context
- Related files: `packages/dev-server/config/dev.php`, `packages/dev-server/src/Command/DevUpCommand.php`
- Config follows the `true | string | false` pattern for each service
- Default config:
  ```php
  return [
      'port' => 8000,
      'detach' => false,
      'docker' => true,
      'frontend' => true,
  ];
  ```
- Config keys accessed as: `dev.port`, `dev.detach`, `dev.docker`, `dev.frontend`
- CLI `--port=N` overrides `dev.port`, `--detach` overrides `dev.detach`
- The ConfigRepository uses dot notation from the filename, so `config/dev.php` with key `port` becomes `dev.port`

## Requirements (Test Descriptions)
- [ ] `it provides default config values in config/dev.php`
- [ ] `it reads port from config`
- [ ] `it reads docker setting from config`
- [ ] `it reads frontend setting from config`
- [ ] `it reads detach setting from config`
- [ ] `it overrides config port with --port flag`
- [ ] `it overrides config detach with --detach flag`

## Acceptance Criteria
- All requirements have passing tests
- Config file has correct defaults
- Code follows code standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
