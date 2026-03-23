# Task 002: Restructure Root composer.json (Require + Replace + Remove Manual PSR-4)

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
Restructure the root composer.json to follow the Symfony/Laravel monorepo pattern: `require` all 70 packages with `self.version`, add `replace` for all 70, add path repositories for all 70, and remove the manual PSR-4 autoload entries. This ensures each package's own `composer.json` is exercised during development — catching missing dependencies, autoload typos, and other issues before they reach users.

## Context
- Root file: `composer.json` (name: `marko/marko`, type: `project`)
- Currently has NO `replace`, NO `repositories`, and NO packages in `require`
- Currently has manual PSR-4 autoload for all 70 packages — this must be REMOVED
- Currently has manual PSR-4 autoload-dev for all 70 packages' tests — this must be REMOVED
- The `autoload.files` entry for `packages/env/src/functions.php` must also be removed (the env package's own composer.json should declare this)
- Symfony's root composer.json uses this exact pattern: require + replace + path repos, no manual autoload
- `minimum-stability` must stay `"stable"` — `replace` bypasses stability checks

## Requirements (Test Descriptions)
- [x] `it adds a require section entry for all 70 marko packages set to self.version`
- [x] `it adds a replace section with all 70 marko packages set to self.version`
- [x] `it adds repositories section with path repos for all 70 packages`
- [x] `it removes all manual PSR-4 autoload entries for marko packages`
- [x] `it removes all manual PSR-4 autoload-dev entries for marko package tests`
- [x] `it removes the autoload files entry for packages/env/src/functions.php`
- [x] `it preserves existing require (php, ext-*) and require-dev (third-party) entries`
- [x] `it preserves scripts, config, and other root-level settings`
- [x] `it keeps minimum-stability as stable (replace bypasses stability checks for replaced packages)`
- [x] `it keeps prefer-stable as true`

## Acceptance Criteria
- Root composer.json has `"require"` with all 70 marko packages at `"self.version"` plus existing php/ext entries
- Root composer.json has `"replace"` with exactly 70 entries, all `"self.version"`
- Root composer.json has `"repositories"` with 70 path repo entries pointing to `packages/*`
- Root composer.json has NO PSR-4 autoload entries for `Marko\\*` namespaces
- Root composer.json has NO PSR-4 autoload-dev entries for `Marko\\*\\Tests\\` namespaces
- Root composer.json has NO `autoload.files` entry
- `composer validate` passes for root composer.json
- `minimum-stability` remains `"stable"`

## Implementation Notes
The final structure should look like:
```json
{
    "name": "marko/marko",
    "type": "project",
    "license": "MIT",
    "repositories": [
        {"type": "path", "url": "packages/admin"},
        {"type": "path", "url": "packages/admin-api"},
        ...
    ],
    "require": {
        "php": "^8.5",
        "ext-pdo": "*",
        "ext-fileinfo": "*",
        "ext-gd": "*",
        "ext-imagick": "*",
        "marko/admin": "self.version",
        "marko/admin-api": "self.version",
        ...
    },
    "replace": {
        "marko/admin": "self.version",
        "marko/admin-api": "self.version",
        ...
    },
    "require-dev": { ... existing third-party ... },
    "scripts": { ... existing ... },
    "config": { ... existing ... },
    "minimum-stability": "stable",
    "prefer-stable": true
}
```

**Important**: The `framework` package is `"type": "metapackage"` -- include it in `require`, `replace`, and `repositories` like the others. Composer handles metapackages differently (no autoload, no source to symlink). If `composer update` warns about the path repo for framework, it can be removed from `repositories` while keeping it in `require` and `replace`.

**Important**: Verify that `packages/env/composer.json` has `"autoload": {"files": ["src/functions.php"]}` so that the env functions are still auto-loaded via the package's own declaration. If not, add it.

**Important**: Do NOT change `minimum-stability` from `"stable"` to `"dev"`. The `replace` section tells Composer "the root provides these packages" and bypasses version/stability resolution entirely.

Package list (alphabetical, 70 total):
admin, admin-api, admin-auth, admin-panel, amphp, api, authentication, authentication-token, authorization, blog, cache, cache-array, cache-file, cache-redis, cli, config, core, cors, database, database-mysql, database-pgsql, dev-server, encryption, encryption-openssl, env, errors, errors-advanced, errors-simple, filesystem, filesystem-local, filesystem-s3, framework, hashing, health, http, http-guzzle, log, log-file, mail, mail-log, mail-smtp, media, media-gd, media-imagick, notification, notification-database, pagination, pubsub, pubsub-pgsql, pubsub-redis, queue, queue-database, queue-rabbitmq, queue-sync, rate-limiting, routing, scheduler, search, security, session, session-database, session-file, sse, testing, translation, translation-file, validation, view, view-latte, webhook
