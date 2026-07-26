# Changelog

All notable changes to Marko are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project follows [Semantic Versioning](https://semver.org/). While Marko is in `0.x`, the API may change between minor versions.

Entries from `0.4.0` onward are generated automatically by `bin/release.sh` from merged PR titles and labels (see `.github/release.yml`). Earlier entries were backfilled from GitHub Releases. The full list of changes for any version is also available at https://github.com/marko-php/marko/releases.

<!-- new-entries-below — do not remove this marker; bin/release.sh inserts new versions directly below it -->

## [0.8.5] - 2026-07-26

### New Features
* feat: surface Claude Code multi-instance config-isolation tip on devai:install by @markshust in https://github.com/marko-php/marko/pull/143
* feat: add /release skill for version assessment and release execution by @markshust in https://github.com/marko-php/marko/pull/146
### Bug Fixes
* fix: support union-typed entity columns via explicit Column type by @TuVanDev in https://github.com/marko-php/marko/pull/145
### Documentation
* docs: correct module/plugin naming convention in skills by @markshust in https://github.com/marko-php/marko/pull/142
* docs: correct multi-instance MCP guidance to lead with per-project scoping by @markshust in https://github.com/marko-php/marko/pull/144
### CI
* ci: gate every PR on tests, lint, and static analysis by @markshust in https://github.com/marko-php/marko/pull/147

## New Contributors
* @TuVanDev made their first contribution in https://github.com/marko-php/marko/pull/145


## [0.8.4] - 2026-06-24

### New Features
* feat: make a fresh skeleton work with zero bootstrapping by @markshust in https://github.com/marko-php/marko/pull/139
* feat: self-refreshing mcp/lsp code index by @markshust in https://github.com/marko-php/marko/pull/141
### Bug Fixes
* fix: show devai:install prompt text and stream live progress by @markshust in https://github.com/marko-php/marko/pull/138
* fix: prevent devai:install composer hang on invisible prompt by @markshust in https://github.com/marko-php/marko/pull/140


## [0.8.3] - 2026-06-24

### Bug Fixes
* fix: remove orphaned devai bootstrap shim from skeleton src by @markshust in https://github.com/marko-php/marko/pull/137


## [0.8.2] - 2026-06-24

### New Features
* feat(devai): offer to install a docs search driver during devai:install by @markshust in https://github.com/marko-php/marko/pull/133
* feat: add marko/testing as a skeleton dev dependency by @markshust in https://github.com/marko-php/marko/pull/134
* feat: warm caches on install and add MCP handshake timeout by @markshust in https://github.com/marko-php/marko/pull/135
### Documentation
* docs: clarify discovery cache vs code index and when a reindex is needed by @markshust in https://github.com/marko-php/marko/pull/136
### CI
* ci: retry split-repo pushes on concurrent ref-lock by @markshust in https://github.com/marko-php/marko/pull/131
### Maintenance
* chore: remove deprecated marko/docs-vec driver in favor of docs-fts by @markshust in https://github.com/marko-php/marko/pull/132


## [0.8.1] - 2026-06-24

### New Features
* feat: tier 1 — close six critical/high security audit findings by @markshust in https://github.com/marko-php/marko/pull/116
* feat: tier 2 — fix ten high-severity correctness defects by @markshust in https://github.com/marko-php/marko/pull/117
* feat: tier 3 — fix twenty medium-severity defects by @markshust in https://github.com/marko-php/marko/pull/118
* feat: tier 4 — eliminate N+1 query loops on hot paths by @markshust in https://github.com/marko-php/marko/pull/119
* feat: tier 5 — harden twenty-four low-severity gaps by @markshust in https://github.com/marko-php/marko/pull/120
* feat: compiled discovery cache (+ recovered tier2 tokenizer prerequisite) by @markshust in https://github.com/marko-php/marko/pull/121
* feat: make devai guideline files marker-based and fully user-overridable by @markshust in https://github.com/marko-php/marko/pull/124
### Bug Fixes
* fix: ship a correct .gitignore to generated projects by @markshust in https://github.com/marko-php/marko/pull/123
* fix(marko-skills): derive scaffold vendor from project dir, never marko by @markshust in https://github.com/marko-php/marko/pull/126
* fix(docs-fts): sanitize natural-language queries into safe FTS5 expressions by @markshust in https://github.com/marko-php/marko/pull/127
* fix(docs-vec): make the hybrid driver buildable and runnable on stock PHP by @markshust in https://github.com/marko-php/marko/pull/130
### Documentation
* docs: catalog 0.8.0 packages in main README by @markshust in https://github.com/marko-php/marko/pull/110
* docs: document GitHub Actions version convention by @markshust in https://github.com/marko-php/marko/pull/113
* docs: add audit remediation implementation plans by @markshust in https://github.com/marko-php/marko/pull/114
* docs: fold remaining audit findings into remediation plans by @markshust in https://github.com/marko-php/marko/pull/115
* docs: document devai marker-based override model in agent docs by @markshust in https://github.com/marko-php/marko/pull/125
* docs: improve search_docs ranking for module-system and config queries by @markshust in https://github.com/marko-php/marko/pull/129
### CI
* ci: add README package catalog drift check by @markshust in https://github.com/marko-php/marko/pull/111
* ci: bump actions/checkout to v6 in readme drift check by @markshust in https://github.com/marko-php/marko/pull/112
### Maintenance
* chore: migrate hcf pipeline to agent frontmatter and prune stray docs by @markshust in https://github.com/marko-php/marko/pull/122


## [0.8.0] - 2026-06-03

### New Features
* feat: add marko/codeindexer (with interface subtraction) by @markshust in https://github.com/marko-php/marko/pull/97
* feat: add marko/claude-plugins by @markshust in https://github.com/marko-php/marko/pull/99
* feat: add marko/docs (documentation search contract) by @markshust in https://github.com/marko-php/marko/pull/100
* feat: add marko/docs-markdown (docs content as a module) by @markshust in https://github.com/marko-php/marko/pull/101
* feat: add marko/lsp by @markshust in https://github.com/marko-php/marko/pull/102
* feat: add marko/docs-fts + marko/docs-vec (docs search drivers) by @markshust in https://github.com/marko-php/marko/pull/103
* feat: add marko/mcp (PersistLastErrorPlugin + LastErrorTool dropped, Runtime/Contracts flattened) by @markshust in https://github.com/marko-php/marko/pull/104
* feat: add marko/devai (4 marker interfaces collapsed into single install()) by @markshust in https://github.com/marko-php/marko/pull/105
### Bug Fixes
* fix: raise memory_limit in composer test scripts by @markshust in https://github.com/marko-php/marko/pull/107
* fix: raise memory_limit in release.sh test invocation by @markshust in https://github.com/marko-php/marko/pull/108
* fix: raise memory_limit in IntegrationVerificationTest pest subprocesses by @markshust in https://github.com/marko-php/marko/pull/109
### Documentation
* docs: add marko/codeindexer reference page + fix README doc link by @markshust in https://github.com/marko-php/marko/pull/98
* docs: widen sidebar + fix Claude Code install method + clarify MCP verification by @markshust in https://github.com/marko-php/marko/pull/106
### Other Changes
* rename: rate-limiting → ratelimiter and dev-server → devserver by @markshust in https://github.com/marko-php/marko/pull/96


## [0.7.0] - 2026-05-27

### Breaking Changes
* feat: extract admin-panel templates into engine-specific sibling packages by @markshust in https://github.com/marko-php/marko/pull/94
### New Features
* feat(database): add selectRaw, whereRaw, and orderByRaw to QueryBuilderInterface by @michalbiarda in https://github.com/marko-php/marko/pull/78
* feat(core): allow packages to declare global middleware in module.php by @michalbiarda in https://github.com/marko-php/marko/pull/80
* feat(database-readwrite): add marko/database-readwrite package by @markshust in https://github.com/marko-php/marko/pull/86
* feat: add Twig template engine driver as sibling to Latte by @markshust in https://github.com/marko-php/marko/pull/88
* feat: centralize driver registries with known-drivers.php pattern by @markshust in https://github.com/marko-php/marko/pull/91
### Bug Fixes
* fix(docs): resolve expressive-code build warnings for latte and env by @markshust in https://github.com/marko-php/marko/pull/83
* fix: point split workflow at MARKO_BUILD_PAT secret by @markshust in https://github.com/marko-php/marko/pull/84
* fix: drop workflow-file PUT that 404s on freshly created split repos by @markshust in https://github.com/marko-php/marko/pull/87
* fix: harden 0.7.0 release (lint config, test fixes, dep refresh) by @markshust in https://github.com/marko-php/marko/pull/95
### Documentation
* docs: document boot-callback dialect override pattern for postgres-wire-compatible databases by @markshust in https://github.com/marko-php/marko/pull/85
### Refactoring
* refactor(view): align ViewInterface bindings with Marko's simple-binding preference by @markshust in https://github.com/marko-php/marko/pull/90
* refactor(view): drop mutual conflict; align with multi-driver pattern by @markshust in https://github.com/marko-php/marko/pull/92


## [0.6.1] - 2026-05-26

### Bug Fixes
* fix(database): use SchemaRegistry in DiffCommand and MigrateCommand for extender merge by @michalbiarda in https://github.com/marko-php/marko/pull/67
* fix(database-pgsql): normalise jsonb → json in introspector type map by @michalbiarda in https://github.com/marko-php/marko/pull/69
* fix(database-pgsql): JSON-encode array bindings before passing to PDO by @michalbiarda in https://github.com/marko-php/marko/pull/72
* fix(database): link entity extenders at boot so companions hydrate during HTTP requests by @michalbiarda in https://github.com/marko-php/marko/pull/74
* fix(database-mysql): JSON-encode array bindings before passing to PDO by @markshust in https://github.com/marko-php/marko/pull/82


## [0.6.0] - 2026-05-12

### New Features
* feat(page-cache): full-page HTTP cache with file driver, tag invalidation, and entity bridge by @michalbiarda in https://github.com/marko-php/marko/pull/58
* feat(routing): memoize RouteMatcher and share one instance with Router by @markshust in https://github.com/marko-php/marko/pull/62
* feat: extend existing entity tables via #[Table(extends:)] by @markshust in https://github.com/marko-php/marko/pull/64
### Bug Fixes
* fix(tests): align RepoManagementScriptsTest with batched gh repo list by @markshust in https://github.com/marko-php/marko/pull/60
* fix: auto-create missing split repos and cancel superseded runs by @markshust in https://github.com/marko-php/marko/pull/65
### Maintenance
* chore(release): batch split-repo lookup and auto-create from release.sh by @markshust in https://github.com/marko-php/marko/pull/56

## New Contributors
* @michalbiarda made their first contribution in https://github.com/marko-php/marko/pull/58


## [0.5.0] - 2026-05-01

### New Features
* feat: Add marko/inertia-react package by @ps-carvalho in https://github.com/marko-php/marko/pull/51
* feat: Add marko/inertia-vue package by @ps-carvalho in https://github.com/marko-php/marko/pull/52
* feat: Add marko/inertia-svelte package by @ps-carvalho in https://github.com/marko-php/marko/pull/53
### Bug Fixes
* fix(tests): eliminate uniqid() parallel-flake and PHP 8.5 setAccessible() deprecation by @markshust in https://github.com/marko-php/marko/pull/55
### Maintenance
* chore: sort root composer.json replace and autoload-dev alphabetically by @markshust in https://github.com/marko-php/marko/pull/54


## [0.4.2] - 2026-05-01

### Bug Fixes
* fix: harden release pipeline (deterministic changelog, self-healing Packagist) by @markshust in https://github.com/marko-php/marko/pull/50


## [0.4.1] - 2026-05-01

### Bug Fixes
* fix: push main before generating release notes by @markshust in https://github.com/marko-php/marko/pull/49


## [0.4.0] - 2026-05-01

### New Features
* feat: close database layer gaps for 1.0 by @markshust in https://github.com/marko-php/marko/pull/40
* feat: Add marko/vite package by @ps-carvalho in https://github.com/marko-php/marko/pull/42
* feat: add marko/debugbar package by @ps-carvalho in https://github.com/marko-php/marko/pull/43
* feat: Add marko/inertia package by @ps-carvalho in https://github.com/marko-php/marko/pull/47
### Documentation
* docs: use composer test for faster local test runs by @markshust in https://github.com/marko-php/marko/pull/38
* docs: document integration-destructive group and run it in release script by @markshust in https://github.com/marko-php/marko/pull/39
* docs: expand PR review process with package-PR checklist by @markshust in https://github.com/marko-php/marko/pull/46
### Refactoring
* refactor: preference discovery and class extraction logic by @iamlasse in https://github.com/marko-php/marko/pull/44
* refactor: offload plugin discovery to PluginDiscovery class by @iamlasse in https://github.com/marko-php/marko/pull/45
### Maintenance
* feat: add CHANGELOG.md with release-script automation by @markshust in https://github.com/marko-php/marko/pull/48

## New Contributors
* @ps-carvalho made their first contribution in https://github.com/marko-php/marko/pull/42
* @iamlasse made their first contribution in https://github.com/marko-php/marko/pull/44

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

### Maintenance
* fix(split): notify Packagist after tag push to prevent missed updates by @markshust in https://github.com/marko-php/marko/pull/2
* feat(release-workflow): add automated release workflow and contribution conventions by @markshust in https://github.com/marko-php/marko/pull/5

## [0.1.0] - 2026-03-30

Initial public-ready release. Improved first-application guide flow and clarity, added `marko open` command, and clarified `app/foo` directory creation.

## [0.0.2] - 2026-03-26

Standardized `NoDriverException` across all interface packages. Each interface package ships its own `NoDriverException` with a `DRIVER_PACKAGES` constant listing known implementations; the container detects and throws these specific exceptions instead of the generic `BindingException`.

## [0.0.1] - 2026-03-25

First tagged release. Established `integration-destructive` test group with `--parallel` execution to prevent OOM, and configured the release script to exclude that group during normal test runs.
