# Task 025: Consolidate skeleton's composer suggest block

**Status**: pending
**Depends on**: 003, 008, 009, 010, 011, 012, 013, 014, 015, 016, 017, 018, 019, 020, 021, 022, 023, 024
**Retry count**: 0

## Description
Update `packages/skeleton/composer.json`'s `suggest` block to include every driver from every interface's `known-drivers.php`, PLUS the two confirmed optional add-ons (`marko/database-readwrite`, `marko/page-cache-entity`) with explanatory descriptions. Recommended-first ordering per interface (matching the per-interface known-drivers.php ordering). Add CI test asserting every known driver appears in skeleton's suggest block — this is the cross-cutting parity check.

## Context
- File to modify: `packages/skeleton/composer.json`
- New test file: `packages/skeleton/tests/KnownDriversSuggestParityTest.php`

**CRITICAL: description-string source of truth.** The exact description string for each driver is defined in its interface's `known-drivers.php` (created in tasks 002, 008-017, 018-024). Skeleton's `suggest` block MUST use those exact strings (literal equality, including em-dashes, parenthetical clauses, and trailing punctuation). The `assertSkeletonSuggestContainsAll` validation test in each interface's KnownDriversValidationTest will fail if any string diverges by even a single character.

**Implementation procedure (do NOT copy from the sample below):**
1. For each driver entry to add to skeleton's suggest, `require` the corresponding `packages/{interface}/known-drivers.php` file
2. Use the description value from that file verbatim
3. Group by interface, recommended-first ordering within each group

Note: `marko/view-twig` is OUT OF SCOPE (does not exist as a package). Only `marko/view-latte` is listed for view.

**Illustrative structure (DESCRIPTION STRINGS ARE PLACEHOLDERS — read actual strings from each known-drivers.php at implementation time):**

```json
"suggest": {
    "marko/view-latte":            "<read from packages/view/known-drivers.php>",

    "marko/database-pgsql":        "<read from packages/database/known-drivers.php>",
    "marko/database-mysql":        "<read from packages/database/known-drivers.php>",
    "marko/database-readwrite":    "Read/write connection splitting decorator (optional — works alongside a base driver)",

    "marko/cache-file":            "<read from packages/cache/known-drivers.php>",
    "marko/cache-redis":           "<read from packages/cache/known-drivers.php>",
    "marko/cache-array":           "<read from packages/cache/known-drivers.php>",

    "marko/errors-simple":         "<read from packages/errors/known-drivers.php>",
    "marko/errors-advanced":       "<read from packages/errors/known-drivers.php>",

    "marko/filesystem-local":      "<read from packages/filesystem/known-drivers.php>",
    "marko/filesystem-s3":         "<read from packages/filesystem/known-drivers.php>",

    "marko/inertia-react":         "<read from packages/inertia/known-drivers.php>",
    "marko/inertia-svelte":        "<read from packages/inertia/known-drivers.php>",
    "marko/inertia-vue":           "<read from packages/inertia/known-drivers.php>",

    "marko/mail-smtp":             "<read from packages/mail/known-drivers.php>",
    "marko/mail-log":              "<read from packages/mail/known-drivers.php>",

    "marko/media-gd":              "<read from packages/media/known-drivers.php>",
    "marko/media-imagick":         "<read from packages/media/known-drivers.php>",

    "marko/page-cache-file":       "<read from packages/page-cache/known-drivers.php>",
    "marko/page-cache-entity":     "Auto-purges page-cache tags on entity save/delete (optional add-on)",

    "marko/pubsub-redis":          "<read from packages/pubsub/known-drivers.php>",
    "marko/pubsub-pgsql":          "<read from packages/pubsub/known-drivers.php>",

    "marko/queue-sync":            "<read from packages/queue/known-drivers.php>",
    "marko/queue-database":        "<read from packages/queue/known-drivers.php>",
    "marko/queue-rabbitmq":        "<read from packages/queue/known-drivers.php>",

    "marko/session-file":          "<read from packages/session/known-drivers.php>",
    "marko/session-database":      "<read from packages/session/known-drivers.php>",

    "marko/authentication-token":  "<read from packages/authentication/known-drivers.php>",
    "marko/encryption-openssl":    "<read from packages/encryption/known-drivers.php>",
    "marko/http-guzzle":           "<read from packages/http/known-drivers.php>",
    "marko/log-file":              "<read from packages/log/known-drivers.php>",
    "marko/notification-database": "<read from packages/notification/known-drivers.php>",
    "marko/translation-file":      "<read from packages/translation/known-drivers.php>"
}
```

Only the two add-on entries (`marko/database-readwrite`, `marko/page-cache-entity`) have free-form descriptions chosen at this task's discretion; everything else MUST be read verbatim from the corresponding known-drivers.php file.

**Description-match constraint:** Every description for a driver entry must match exactly what its interface's `known-drivers.php` file says — the per-interface validation tests assert this. Add-on descriptions are not constrained (no known-drivers.php to match against).

**Ordering convention within each interface group:** matches the known-drivers.php ordering of that interface (recommended-first). Visual grouping in the file (blank lines between interface families, as shown above) is not enforced by composer but improves human readability.

**Cross-cutting parity test (`KnownDriversSuggestParityTest.php`):**
```php
test('skeleton suggest block contains every entry from every known-drivers.php file', function () {
    $knownDriversFiles = glob(__DIR__ . '/../../*/known-drivers.php');
    $skeletonSuggest = json_decode(
        file_get_contents(__DIR__ . '/../composer.json'),
        associative: true,
    )['suggest'] ?? [];

    foreach ($knownDriversFiles as $knownDriversPath) {
        $drivers = require $knownDriversPath;
        foreach ($drivers as $package => $description) {
            expect($skeletonSuggest)->toHaveKey($package);
            expect($skeletonSuggest[$package])->toBe($description);
        }
    }
});

test('skeleton suggest block includes known optional add-ons', function () {
    $skeletonSuggest = json_decode(
        file_get_contents(__DIR__ . '/../composer.json'),
        associative: true,
    )['suggest'] ?? [];

    expect($skeletonSuggest)->toHaveKey('marko/database-readwrite');
    expect($skeletonSuggest)->toHaveKey('marko/page-cache-entity');
});
```

## Requirements (Test Descriptions)
- [ ] `it lists every driver from every known-drivers.php file in skeleton suggest`
- [ ] `it preserves descriptions verbatim between known-drivers.php and skeleton suggest`
- [ ] `it includes marko/database-readwrite as an optional add-on`
- [ ] `it includes marko/page-cache-entity as an optional add-on`
- [ ] `it does not move any view, database, cache, etc. drivers into require or require-dev`
- [ ] `skeleton composer.json remains valid JSON after the consolidation`

## Acceptance Criteria
- `packages/skeleton/composer.json` `suggest` block contains entries for every driver in every interface's known-drivers.php file
- Descriptions match exactly (literal string equality)
- Add-ons (`marko/database-readwrite`, `marko/page-cache-entity`) present with explanatory descriptions
- No drivers added to `require` or `require-dev` (skeleton remains engine-agnostic)
- Cross-cutting parity test passes
- `composer validate packages/skeleton/composer.json` succeeds
- Code follows code standards
