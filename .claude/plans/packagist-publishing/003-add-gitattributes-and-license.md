# Task 003: Add .gitattributes and LICENSE to All Packages

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Add `.gitattributes` files to all 70 packages so that `composer install --prefer-dist` downloads lean archives without tests, docs, CI config, or other development files. Also ensure every package has a `LICENSE` file with the correct MIT copyright for Devtomic LLC — required for split repos so users see the license on Packagist and in their `vendor/` directory. Update the root LICENSE file as well.

## Context
- Each package needs its own `.gitattributes` in `packages/{name}/`
- Each package needs its own `LICENSE` in `packages/{name}/`
- The split repos will contain these files, so Packagist/Composer archives respect them
- Also add/update root `.gitattributes` and root `LICENSE` for the monorepo
- Standard exclusions: tests, CI config, editor config, etc.
- Copyright holder: Devtomic LLC
- License: MIT (already declared in all package composer.json files)

## Requirements (Test Descriptions)
- [ ] `it creates .gitattributes in all 70 package directories`
- [ ] `it excludes tests/ directory from exports`
- [ ] `it excludes .gitattributes itself from exports`
- [ ] `it excludes .gitignore from exports if present`
- [ ] `it excludes phpunit.xml or phpunit.xml.dist from exports if present`
- [ ] `it creates or updates root .gitattributes for the monorepo`
- [ ] `it creates LICENSE (MIT) in all 70 package directories with copyright Devtomic LLC`
- [ ] `it updates the root LICENSE file with copyright Devtomic LLC`

## Acceptance Criteria
- Every `packages/*/` directory contains a `.gitattributes` file
- Every `packages/*/` directory contains a `LICENSE` file
- All `.gitattributes` files use `export-ignore` for non-essential files
- All `LICENSE` files contain identical MIT license text with `Copyright (c) Devtomic LLC`
- Root `.gitattributes` exists with appropriate settings
- Root `LICENSE` exists with `Copyright (c) Devtomic LLC`
- No package source code (src/) is excluded from archives

## Implementation Notes

### Standard package .gitattributes
```
/tests              export-ignore
/.gitattributes     export-ignore
/.gitignore         export-ignore
/phpunit.xml.dist   export-ignore
```

Keep it minimal — only exclude what actually exists or commonly exists in packages. Don't exclude `composer.json`, `LICENSE`, or `README.md` (those must ship).

### Root .gitattributes
```
# Auto-detect text files
* text=auto

# Ensure consistent line endings
*.php text eol=lf
*.md text eol=lf
*.json text eol=lf
*.yml text eol=lf
*.yaml text eol=lf
*.xml text eol=lf
*.neon text eol=lf
*.sh text eol=lf
```

### MIT LICENSE template (for all packages and root)
```
MIT License

Copyright (c) Devtomic LLC

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

Note: No year in the copyright line. This is intentional — MIT doesn't require it, and omitting it avoids needing to update it annually. Laravel, Symfony, and many modern projects omit the year. If the user prefers a year, use `2026`.

Package list (70 total):
admin, admin-api, admin-auth, admin-panel, amphp, api, authentication, authentication-token, authorization, blog, cache, cache-array, cache-file, cache-redis, cli, config, core, cors, database, database-mysql, database-pgsql, dev-server, encryption, encryption-openssl, env, errors, errors-advanced, errors-simple, filesystem, filesystem-local, filesystem-s3, framework, hashing, health, http, http-guzzle, log, log-file, mail, mail-log, mail-smtp, media, media-gd, media-imagick, notification, notification-database, pagination, pubsub, pubsub-pgsql, pubsub-redis, queue, queue-database, queue-rabbitmq, queue-sync, rate-limiting, routing, scheduler, search, security, session, session-database, session-file, sse, testing, translation, translation-file, validation, view, view-latte, webhook
