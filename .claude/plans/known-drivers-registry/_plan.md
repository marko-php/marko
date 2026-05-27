# Plan: known-drivers.php Registry Pattern

## Created
2026-05-27

## Status
ready

## Objective
Establish `known-drivers.php` as the single curated source of truth for each interface package's drivers. Eliminate hardcoded driver lists scattered across `NoDriverException` classes and skeleton's `suggest` block — mechanically enforce sync via CI tests. Roll out to every interface package with ≥1 driver.

**Note on scope reduction:** an earlier version of this plan also added mutual Composer `conflict` declarations to every multi-driver family. PR #92 settled the broader design question: Marko relies on DI-level `BindingConflictException` (boot-time) for double-binding detection, NOT Composer-level conflict declarations. So this plan no longer adds conflict blocks, no longer validates them, and the pilot's standalone "add conflict blocks" task has been removed.

## Related Issues
Closes #89

## Discovery Notes

**Existing state:**
- 18 packages already define a `NoDriverException` class. 17 use a `noDriverInstalled()` static factory; **`marko/page-cache` is the lone outlier — it uses `noBinding()`** (will be standardized to `noDriverInstalled()` in task 023). Most have a hardcoded `private const array DRIVER_PACKAGES = [...]`; a few have just a single hardcoded package in the suggestion text. Pattern is established but lists drift independently.
- `marko/view`'s `NoDriverException` currently lists both `marko/view-latte` and `marko/view-twig` (both packages now in monorepo after PR #88 and #92). This plan formalizes the registry pattern; the view rollout (task 016) refactors that exception to read from `known-drivers.php`.
- **`marko/inertia` does NOT currently have a `NoDriverException`** — it must be created as part of task 010 following the established pattern.
- The `admin` package has a `NoDriverException` but no drivers (admin-api/admin-auth/admin-panel are sub-modules of an admin system, not drivers). Excluded as a separate concern.
- **Several existing tests assert against the soon-to-be-removed `DRIVER_PACKAGES` const** (e.g., `packages/database/tests/NoDriverExceptionTest.php` line 9-16). The refactor tasks (003, 007-016) must update or remove those assertions in addition to the source-file changes.

**Driver classification (multi-driver, mutually-exclusive — bind same interface):**
| Interface | Drivers | Recommended (listed first) |
|-----------|---------|----------------------------|
| cache | array, file, redis | file |
| database | mysql, pgsql | pgsql |
| errors | simple, advanced | simple (prod-safe default) |
| filesystem | local, s3 | local |
| inertia | react, svelte, vue | react |
| mail | log, smtp | log (dev-safe default; smtp for prod) |
| media | gd, imagick | gd (broader availability) |
| pubsub | pgsql, redis | redis (purpose-built) |
| queue | sync, database, rabbitmq | sync (zero-infrastructure default) |
| session | file, database | file |
| view | twig, latte | twig (broader ecosystem familiarity) |

**Single-driver interfaces (one driver currently — known-drivers.php still applies):**
authentication (token), encryption (openssl), http (guzzle), log (file), notification (database), translation (file), page-cache (file)

**Optional add-ons (NOT enrolled in known-drivers.php):**
- `marko/database-readwrite` — decorator wrapping the configured driver via boot callback; coexists with mysql/pgsql. **Not present in this monorepo's `packages/` directory** (lives in a sibling split repo). Skeleton's suggest block still references it; no on-disk validation required since add-ons don't appear in known-drivers.php.
- `marko/page-cache-entity` — bridge package adding observer-based cache invalidation; coexists with page-cache-file. Present in the monorepo.

**Mechanical rule:** a package is a driver iff its `module.php` `bindings` array contains the interface's defining contract. Add-ons have empty `bindings: []` (database-readwrite, page-cache-entity confirmed). For v1, `known-drivers.php` is the curated source — interface-package maintainer decides. Marker interfaces or `extra.marko.driver_for` declarations are out of scope but viable follow-ups if drift becomes a problem.

**errors-advanced URL rendering:** currently `PrettyHtmlFormatter::formatDevelopment` only renders `$report->message` through `escape()` — it does NOT currently render `$report->context` or `$report->suggestion` at all (verified in `packages/errors-advanced/src/PrettyHtmlFormatter.php` line 58-89). Task 005 in this plan does TWO things: (1) adds rendering of `context` and `suggestion` to the HTML output (so users actually see the NoDriverException's installation guidance), and (2) adds URL detection + `target="_blank" rel="noopener noreferrer"` linkification, applied uniformly to message, context, and suggestion fields. Without (1), the docs URLs we're adding to NoDriverException won't be visible in errors-advanced output at all.

**marko/view test leak:** `packages/view/tests/Feature/IntegrationTest.php` uses `Marko\View\Latte\LatteEngineFactory` — a hard dependency on view-latte from inside the interface package's test suite. Cleanup task moves it to view-latte (where the integration test logically belongs).

**Test infrastructure decision:** A shared `KnownDriversValidator` class in `marko/testing` provides one assertion helper: `assertSkeletonSuggestContainsAll`. Each interface package's test file is a thin wrapper passing its own `known-drivers.php` path. Skip gracefully when skeleton isn't on disk (enables marko/view-only installs to pass tests without skeleton present).

## Scope

### In Scope

**Phase A — Pilot (database):**
- Create `packages/database/known-drivers.php` with pgsql-first ordering
- Refactor `marko/database`'s `NoDriverException` to read from known-drivers.php; include descriptions and derived docs URLs (`https://marko.build/docs/packages/{basename}/`)
- Add CI validation test in `marko/database` (uses shared helper) — asserts skeleton suggest parity only

**Phase B — Shared infrastructure (parallel with pilot):**
- Create `KnownDriversValidator` in `marko/testing` with assertion helpers
- Extend `marko/errors-advanced`'s `PrettyHtmlFormatter` to (1) render `context` and `suggestion` fields from `ErrorReport` (currently dropped), and (2) auto-detect `http(s)://` URLs in message/context/suggestion text and wrap them with `<a target="_blank" rel="noopener noreferrer">`
- Clean up `marko/view` test suite: move `IntegrationTest.php` from `marko/view/tests/Feature/` to `marko/view-latte/tests/Feature/`. Verify marko/view tests pass with view-latte uninstalled.

**Phase C — Roll out to multi-driver interfaces (10, parallel):**
- cache, errors, filesystem, inertia, mail, media, pubsub, queue, session, view
- Each gets: known-drivers.php, refactored NoDriverException, validation test (skeleton-suggest parity)

**Phase D — Roll out to single-driver interfaces (7, parallel):**
- authentication, encryption, http, log, notification, translation, page-cache
- Each gets: known-drivers.php (one entry), refactored NoDriverException, validation test

**Phase E — Skeleton consolidation:**
- Update `marko/skeleton`'s `composer.json` `suggest` block with all drivers (recommended-first per interface) and the two confirmed add-ons (`marko/database-readwrite`, `marko/page-cache-entity`) with explanatory text

### Out of Scope

- `admin/NoDriverException` cleanup — admin-{api,auth,panel} are sub-modules not drivers; that NoDriverException is vestigial. Flagged for separate investigation.
- Marker interfaces (e.g., `ViewDriverInterface`) for mechanical driver enforcement — viable follow-up if curation drifts from reality
- `extra.marko.driver_for` declarations in driver composer.json — same reason as above
- Wiring `NoDriverException` to actually be thrown when no driver is bound — pre-existing gap across all 18 packages; the exception classes are defined but no code throws them. The container throws a generic `BindingException` today. Out of scope for this plan; tracked as a follow-up.
- Removing `marko/view-latte` from monorepo-wide CI runs — view-latte tests still run as part of the full suite, just no longer via marko/view's test directory.

## Success Criteria
- [ ] Every interface package with ≥1 driver has a `known-drivers.php` file
- [ ] Every interface package with a `NoDriverException` reads its driver list from `known-drivers.php` (no more hardcoded `DRIVER_PACKAGES` consts)
- [ ] Skeleton's `suggest` block includes every entry from every `known-drivers.php` file plus optional add-ons
- [ ] CI validation tests enforce sync between `known-drivers.php` and skeleton `suggest` — fails build on drift
- [ ] CI tests skip gracefully (not fail) when sibling driver packages aren't on disk
- [ ] `marko/view` test suite has zero dependency on `marko/view-latte` — passes with view-latte uninstalled
- [ ] `marko/errors-advanced` renders URLs in exception suggestion text as `target="_blank"` links
- [ ] `NoDriverException::noDriverInstalled()` output includes derived docs URL for each driver
- [ ] All tests passing (`composer test`)
- [ ] Code follows project standards

## Task Overview

| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Add `KnownDriversValidator` to marko/testing | - | pending |
| 002 | Pilot: database known-drivers.php | - | pending |
| 003 | Pilot: refactor database `NoDriverException` (read from file + docs URLs) | 002 | pending |
| 004 | Pilot: database validation test | 001, 002, 003 | pending |
| 005 | Render context/suggestion + URL linkification in errors-advanced | - | pending |
| 006 | Clean up marko/view test suite (move IntegrationTest) | - | pending |
| 007 | Roll out: cache | 001, 004 | pending |
| 008 | Roll out: errors | 001, 004 | pending |
| 009 | Roll out: filesystem | 001, 004 | pending |
| 010 | Roll out: inertia (creates new NoDriverException) | 001, 004 | pending |
| 011 | Roll out: mail | 001, 004 | pending |
| 012 | Roll out: media | 001, 004 | pending |
| 013 | Roll out: pubsub | 001, 004 | pending |
| 014 | Roll out: queue | 001, 004 | pending |
| 015 | Roll out: session | 001, 004 | pending |
| 016 | Roll out: view (both view-latte and view-twig) | 001, 004, 006 | pending |
| 017 | Roll out: authentication (single-driver) | 001, 004 | pending |
| 018 | Roll out: encryption (single-driver) | 001, 004 | pending |
| 019 | Roll out: http (single-driver) | 001, 004 | pending |
| 020 | Roll out: log (single-driver) | 001, 004 | pending |
| 021 | Roll out: notification (single-driver) | 001, 004 | pending |
| 022 | Roll out: translation (single-driver) | 001, 004 | pending |
| 023 | Roll out: page-cache (single-driver; entity is add-on; renames noBinding to noDriverInstalled) | 001, 004 | pending |
| 024 | Skeleton consolidation (suggest block with all drivers + add-ons) | 003, 007–023 | pending |

## Architecture Notes

**File format (`known-drivers.php`):**
```php
<?php

declare(strict_types=1);

return [
    'marko/database-pgsql' => 'PostgreSQL driver (recommended for new projects — strong JSON, FTS, pgvector support)',
    'marko/database-mysql' => 'MySQL/MariaDB driver',
];
```

Flat `package => description` array. Same shape as composer's `suggest` block, so the CI test can do literal equality between known-drivers.php and the relevant subset of skeleton's suggest.

**Docs URL derivation (in NoDriverException):**
```php
private static function docsUrl(string $package): string
{
    $basename = substr($package, strlen('marko/'));
    return "https://marko.build/docs/packages/{$basename}/";
}
```

Pattern works for all `marko/*` packages. If third-party drivers ever enroll, we can switch to explicit URLs in the file structure — but for now derivation suffices.

**Refactored NoDriverException shape (every interface):**
```php
public static function noDriverInstalled(): self
{
    $drivers = require __DIR__ . '/../../known-drivers.php';
    $packageList = implode("\n", array_map(
        fn (string $pkg, string $description) =>
            "- {$pkg}: {$description}\n  Install: composer require {$pkg}\n  Docs: " . self::docsUrl($pkg),
        array_keys($drivers),
        array_values($drivers),
    ));

    return new self(
        message: 'No {interface} driver installed.',
        context: 'Attempted to resolve {InterfaceName} but no implementation is bound.',
        suggestion: "Install one of these drivers:\n{$packageList}",
    );
}
```

**KnownDriversValidator interface (marko/testing):**
```php
namespace Marko\Testing\KnownDrivers;

class KnownDriversValidator
{
    public static function assertSkeletonSuggestContainsAll(string $knownDriversPath, string $skeletonComposerPath): void;
    public static function assertDocsUrlsResolveToValidPattern(string $knownDriversPath): void;
}
```

Each assertion:
- Reads `known-drivers.php`
- For `assertSkeletonSuggestContainsAll`: locates skeleton's composer.json, compares its `suggest` block to known-drivers.php entries
- **Skips gracefully** (not fails) when files missing — since static methods cannot call `markTestSkipped()` directly, throw `\PHPUnit\Framework\SkippedWithMessageException` which Pest treats as a skip
- Also skip when skeleton.composer.json exists but has no `suggest` key yet — required because per-interface validation tests (004, 007-023) run BEFORE skeleton consolidation (024) in topological order
- Asserts the expected sync invariant when files are present and populated

**errors-advanced URL handling:**
The `PrettyHtmlFormatter::formatDevelopment` currently only renders `$report->message` through a private `escape()` method; `$report->context` and `$report->suggestion` are never rendered. This plan: (1) adds context/suggestion rendering into the HTML heredoc (positioned after the message, with `.context` and `.suggestion` CSS classes for styling); (2) introduces a private `escapeAndLinkifyUrls()` helper that detects `http(s)://...` URLs (regex), splits the text, htmlspecialchars-escapes the non-URL portions, and wraps URL portions in `<a href="..." target="_blank" rel="noopener noreferrer">...</a>`. The `escape()` callsite for `$report->message` is replaced with the new helper, and the new context/suggestion rendering also uses it. Plaintext output (errors-simple) is unchanged — URLs stay as plain text.

## Risks & Mitigations

- **Risk:** Refactoring 18 NoDriverException classes is touch-heavy; subtle bugs (typos in interface names, wrong docs URL pattern) could slip through.
  **Mitigation:** The validation tests (task 004's pattern, replicated per interface in tasks 007-023) mechanically catch drift between known-drivers.php and skeleton suggest entries. The docs URL derivation is centralized in one place (one helper per NoDriverException).

- **Risk:** Tests run in CI where all packages are present (monorepo), but a user installing marko/view standalone could fail validation tests if they're written naively.
  **Mitigation:** Skip-gracefully behavior in `KnownDriversValidator` (skips when skeleton.composer.json is missing). Validate by running `marko/view` tests with marko/view-latte uninstalled as part of task 006 acceptance criteria.

- **Risk:** `errors-advanced` URL linkification could break existing output formatting if the regex over-matches (e.g., catching text that looks URL-ish but isn't).
  **Mitigation:** Conservative regex pattern (require `http://` or `https://` prefix; stop at whitespace or `<`). Add regression tests for non-URL text passing through unchanged. Tests run before merge.

- **Risk:** Skeleton consolidation task (024) creates a long, hard-to-read suggest block in composer.json.
  **Mitigation:** Composer suggest is read at install time only, never at runtime. Length is a one-time cost during scaffolding. The recommended-first ordering with descriptions makes it navigable.

- **Risk:** The `admin/NoDriverException` excluded from this plan could confuse maintainers who see other packages refactored but admin left behind.
  **Mitigation:** Add a one-line code comment in `admin/NoDriverException` flagging it as vestigial pending investigation. Document in PR.
