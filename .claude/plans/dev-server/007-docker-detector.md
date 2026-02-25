# Task 007: Implement DockerDetector

**Status**: completed
**Depends on**: 006
**Retry count**: 0

## Description
Create `DockerDetector` that detects Docker Compose files in the project root and determines the correct binary to use. Returns a detection result with the compose file found and the command to run, or null if no Docker setup detected.

## Context
- Related files: `packages/dev-server/src/Detection/DockerDetector.php`
- Compose file priority order: `compose.yaml`, `compose.yml`, `docker-compose.yaml`, `docker-compose.yml`
- Binary detection: prefer `docker compose` (V2), fall back to `docker-compose` (V1 legacy)
- The up command should use `-d` (detached) since Docker runs as background services
- The class should be injectable with the project root path
- Down command is `docker compose down` (using same binary detected)

## Requirements (Test Descriptions)
- [ ] `it detects compose.yaml in project root`
- [ ] `it detects docker-compose.yml in project root`
- [ ] `it returns null when no compose file exists`
- [ ] `it returns up command with detached flag`
- [ ] `it returns down command for stopping containers`
- [ ] `it checks compose files in priority order`
- [ ] `it uses compose file path in command with -f flag`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- Uses filesystem checks (testable with temp directories)

## Implementation Notes
(Left blank - filled in by programmer during implementation)
