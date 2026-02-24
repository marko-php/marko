# Plan: API-First Packages

## Created
2026-02-24

## Status
in_progress

## Objective
Build 9 new packages that transform Marko from a server-rendered framework into a modern API-first platform: API resources, CORS, token authentication, health checks, webhooks, search, and media management.

## Scope

### In Scope
- `marko/api` — API resource/response transformation layer
- `marko/cors` — CORS middleware for cross-origin API access
- `marko/authentication-token` — Bearer token authentication for stateless APIs
- `marko/health` — Health check system for production monitoring
- `marko/webhook` — Webhook dispatch and receipt with signature verification
- `marko/search` — Search abstraction with database driver
- `marko/media` — Media management (upload, storage, entities, URLs)
- `marko/media-gd` — GD image processing driver
- `marko/media-imagick` — ImageMagick image processing driver

### Out of Scope
- Elasticsearch/Meilisearch search drivers (future packages)
- OAuth2/JWT authentication (separate concern from simple token auth)
- WebSocket/broadcasting (separate feature area)
- Admin UI panels for any of these packages
- Demo app customizations (tests prove features work)

## Success Criteria
- [ ] All 9 packages pass tests with `./vendor/bin/pest --parallel`
- [ ] All packages follow interface/driver split pattern
- [ ] All packages have composer.json, module.php, config files
- [ ] All packages have README.md following Package README Standards
- [ ] Code passes `./vendor/bin/phpcs` and `./vendor/bin/php-cs-fixer fix`
- [ ] No decrease in overall test coverage (80% minimum)

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | API package interfaces and exceptions | - | completed |
| 002 | JsonResource implementation | 001 | completed |
| 003 | ResourceCollection with pagination | 002 | completed |
| 004 | API package README | 003 | pending |
| 005 | CORS middleware and config | - | completed |
| 006 | CORS package README | 005 | completed |
| 007 | Token entity and repository interface | - | completed |
| 008 | TokenGuard implementation | 007 | completed |
| 009 | Token management service | 008 | completed |
| 010 | Authentication-token package README | 009 | pending |
| 011 | Health check interfaces and registry | - | completed |
| 012 | Built-in checks and controller | 011 | completed |
| 013 | Health package README | 012 | completed |
| 014 | Webhook sending interfaces and implementation | - | completed |
| 015 | Webhook receiving and verification | 014 | completed |
| 016 | Webhook delivery tracking | 015 | completed |
| 017 | Webhook package README | 016 | pending |
| 018 | Search interfaces and value objects | - | completed |
| 019 | Database search driver | 018 | completed |
| 020 | Search package README | 019 | completed |
| 021 | Media entity and core interfaces | - | completed |
| 022 | MediaManager implementation | 021 | completed |
| 023 | URL generation and attachments | 022 | completed |
| 024 | Media package README | 023 | pending |
| 025 | GD image processor | 021 | completed |
| 026 | Media-gd package README | 025 | completed |
| 027 | ImageMagick image processor | 021 | completed |
| 028 | Media-imagick package README | 027 | completed |

## Dependency Graph
```
001 ──► 002 ──► 003 ──► 004
005 ──► 006
007 ──► 008 ──► 009 ──► 010
011 ──► 012 ──► 013
014 ──► 015 ──► 016 ──► 017
018 ──► 019 ──► 020
021 ─┬─► 022 ──► 023 ──► 024
     ├─► 025 ──► 026
     └─► 027 ──► 028
```

## Parallel Batches
- **Batch 1** (7 tasks): 001, 005, 007, 011, 014, 018, 021
- **Batch 2** (9 tasks): 002, 006, 008, 012, 015, 019, 022, 025, 027
- **Batch 3** (8 tasks): 003, 009, 013, 016, 020, 023, 026, 028
- **Batch 4** (4 tasks): 004, 010, 017, 024

## Architecture Notes
- All packages follow the established interface/driver split pattern
- Packages depend on interface packages (marko/filesystem, not marko/filesystem-local)
- Exceptions follow the loud errors pattern: message + context + suggestion
- Config files are the single source of truth; no hardcoded fallbacks
- Entities use PHP attributes for schema definition (#[Table], #[Column])
- Middleware implements MiddlewareInterface from marko/routing
- All classes use constructor property promotion and strict types
- No final classes (blocks Preferences extensibility)
- Use readonly where appropriate for immutability
- All test requirements written as Pest test descriptions

## Risks & Mitigations
- **Cross-package integration**: Each package is tested independently; integration proven via existing patterns
- **Soft dependencies in health**: Health checks gracefully handle missing packages (database, cache, etc.) via optional constructor injection
- **Image extension availability**: GD/Imagick drivers throw loud errors when extensions are missing, with suggestion to install
- **Token security**: Tokens are hashed before storage (SHA-256); plain text only returned at creation time
