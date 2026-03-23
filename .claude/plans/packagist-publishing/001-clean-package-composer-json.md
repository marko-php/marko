# Task 001: Clean All Package composer.json Files

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
Remove path repositories and fix version constraints across all 70 package composer.json files. Path repos must never appear in published packages (they're monorepo-only concerns). Internal dependencies must use `self.version` for unified versioning.

## Context
- 38 packages currently have `"repositories"` with path repos — must be removed from all
- All internal `marko/*` dependencies (226 total occurrences of `@dev` or `*` across 67 files) must change to `"self.version"`
- marko/dev-server uses `"*"` for marko/config and marko/core — must change to `"self.version"`
- marko/core has `"marko/testing": "*"` in require-dev — must change to `"self.version"`
- All packages are in `packages/*/composer.json`
- The `marko/framework` metapackage also has `"@dev"` constraints

## Requirements (Test Descriptions)
- [x] `it removes repositories key from all 38 package composer.json files that have path repos`
- [x] `it changes all internal marko/* require constraints from @dev to self.version`
- [x] `it changes all internal marko/* require-dev constraints from @dev to self.version`
- [x] `it changes marko/dev-server wildcard constraints to self.version`
- [x] `it changes any remaining wildcard marko/* constraints to self.version`
- [x] `it preserves all non-marko dependency constraints unchanged (php, psr/*, ext-*, pestphp/*, etc.)`
- [x] `it preserves all other composer.json keys (autoload, extra, config, suggest, etc.) unchanged`

## Acceptance Criteria
- Zero package composer.json files contain a `"repositories"` key
- All `marko/*` dependencies in require and require-dev use `"self.version"`
- All third-party dependencies retain their original constraints
- Each modified composer.json is valid JSON with correct structure (note: `composer validate` will reject `self.version` as a constraint when run on individual sub-packages -- this is expected; it only resolves in the context of a git tag or root `replace`)
- No package has a `"version"` field

## Implementation Notes
This is a bulk mechanical change. A script approach is recommended:
1. Iterate all `packages/*/composer.json`
2. For each file: remove `repositories` key, replace marko/* constraints
3. Validate each file after modification

Complete list of packages (70):
admin, admin-api, admin-auth, admin-panel, amphp, api, authentication, authentication-token, authorization, blog, cache, cache-array, cache-file, cache-redis, cli, config, core, cors, database, database-mysql, database-pgsql, dev-server, encryption, encryption-openssl, env, errors, errors-advanced, errors-simple, filesystem, filesystem-local, filesystem-s3, framework, hashing, health, http, http-guzzle, log, log-file, mail, mail-log, mail-smtp, media, media-gd, media-imagick, notification, notification-database, pagination, pubsub, pubsub-pgsql, pubsub-redis, queue, queue-database, queue-rabbitmq, queue-sync, rate-limiting, routing, scheduler, search, security, session, session-database, session-file, sse, testing, translation, translation-file, validation, view, view-latte, webhook
