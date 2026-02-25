# Task 011: Implement DevUpCommand

**Status**: completed
**Depends on**: 007, 008, 009, 010
**Retry count**: 0

## Description
Create the `DevUpCommand` — the main orchestrator that starts the development environment. Reads config from `config/dev.php`, runs detection for Docker and frontend, starts the PHP built-in server, and manages all processes. Supports `--port` and `--detach` flag overrides.

## Context
- Related files: `packages/dev-server/src/Command/DevUpCommand.php`
- Command: `#[Command(name: 'dev:up', description: 'Start the development environment', aliases: ['up'])]`
- Config keys: `dev.port` (int, default 8000), `dev.detach` (bool, default false), `dev.docker` (true|string|false), `dev.frontend` (true|string|false)
- When config value is `true`: auto-detect using DockerDetector/FrontendDetector
- When config value is a string: use that string as the command directly
- When config value is `false`: skip that service
- CLI flags `--port=N` and `--detach` override config values for that invocation
- PHP server command: `php -S localhost:{port} -t public/`
- On startup, output which services were detected and started
- If no services detected (no Docker, no frontend), still start PHP server

## Requirements (Test Descriptions)
- [ ] `it has Command attribute with name dev:up and alias up`
- [ ] `it starts PHP server on configured port`
- [ ] `it overrides port with --port flag`
- [ ] `it starts Docker when docker config is true and compose file exists`
- [ ] `it skips Docker when docker config is false`
- [ ] `it uses custom Docker command when docker config is a string`
- [ ] `it starts frontend when frontend config is true and package.json has dev script`
- [ ] `it skips frontend when frontend config is false`
- [ ] `it uses custom frontend command when frontend config is a string`
- [ ] `it writes PID file when --detach flag is used`
- [ ] `it outputs service summary on startup`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- Loud errors for failures (port in use, missing binary, etc.)

## Implementation Notes
(Left blank - filled in by programmer during implementation)
