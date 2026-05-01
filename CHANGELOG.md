# Changelog

All notable changes to Marko are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project follows [Semantic Versioning](https://semver.org/). While Marko is in `0.x`, the API may change between minor versions.

Entries from `0.4.0` onward are generated automatically by `bin/release.sh` from merged PR titles and labels (see `.github/release.yml`). Earlier entries were backfilled from GitHub Releases. The full list of changes for any version is also available at https://github.com/marko-php/marko/releases.

<!-- new-entries-below — do not remove this marker; bin/release.sh inserts new versions directly below it -->

## [0.4.0] - 2026-05-01



## [0.3.1] - 2026-04-21

### Bug Fixes
- `save()` silently skipped update for entities inserted in the same request ([#37](https://github.com/marko-php/marko/pull/37))

### Documentation
- Fix database config examples to use flat format ([#33](https://github.com/marko-php/marko/pull/33))
- Add intro video to README after Why Marko section ([#34](https://github.com/marko-php/marko/pull/34))
- Fix code-standards violations across tutorials and guides ([#35](https://github.com/marko-php/marko/pull/35))

## [0.3.0] - 2026-04-15

### Breaking Changes
- Auto-convert camelCase property names to snake_case column names ([#30](https://github.com/marko-php/marko/pull/30))

### New Features
- Add `route:list` CLI command to `marko/routing` ([#25](https://github.com/marko-php/marko/pull/25))
- Add `doc-updater` to post-implementation pipeline ([#26](https://github.com/marko-php/marko/pull/26))
- Add ORM relationships, collections, and query specifications ([#28](https://github.com/marko-php/marko/pull/28))
- Allow overriding host for dev server up ([#11](https://github.com/marko-php/marko/pull/11))
- Optional TLS for database connections ([#6](https://github.com/marko-php/marko/pull/6))

### Bug Fixes
- Use DI container to instantiate layout components ([#29](https://github.com/marko-php/marko/pull/29))

### Documentation
- Add layout package to README, remove blog reference ([#23](https://github.com/marko-php/marko/pull/23))

## [0.2.0] - 2026-04-10

### New Features
- Add issue type to bug report and feature request templates ([#19](https://github.com/marko-php/marko/pull/19))
- Attribute-driven layout system ([#20](https://github.com/marko-php/marko/pull/20))

### Bug Fixes
- Issue template dropdown validation errors ([#16](https://github.com/marko-php/marko/pull/16))
- Restore package options to issue template dropdowns ([#22](https://github.com/marko-php/marko/pull/22))

### Refactoring
- Remove `marko/blog` package from framework ([#21](https://github.com/marko-php/marko/pull/21))

## [0.1.3] - 2026-04-06

### Bug Fixes
- Derive repo from git remote for `gh release create` ([#14](https://github.com/marko-php/marko/pull/14))
- Replace `PluginProxy` with generated interceptor classes ([#15](https://github.com/marko-php/marko/pull/15))

## [0.1.2] - 2026-04-05

### Breaking Changes
- Preload project autoloader in `bin` ([#10](https://github.com/marko-php/marko/pull/10))
- Add missing `QueryBuilderFactoryInterface` implementation in `marko/database-mysql` ([#7](https://github.com/marko-php/marko/pull/7))

### New Features
- Integrate plugin interception into container resolution ([#12](https://github.com/marko-php/marko/pull/12))

### Documentation
- Add PR review process guide ([#13](https://github.com/marko-php/marko/pull/13))

## [0.1.1] - 2026-04-05

Maintenance release. No user-facing changes.

## [0.1.0] - 2026-03-30

Initial public-ready release. Improved first-application guide flow and clarity, added `marko open` command, and clarified `app/foo` directory creation.

## [0.0.2] - 2026-03-26

Standardized `NoDriverException` across all interface packages. Each interface package ships its own `NoDriverException` with a `DRIVER_PACKAGES` constant listing known implementations; the container detects and throws these specific exceptions instead of the generic `BindingException`.

## [0.0.1] - 2026-03-25

First tagged release. Established `integration-destructive` test group with `--parallel` execution to prevent OOM, and configured the release script to exclude that group during normal test runs.
