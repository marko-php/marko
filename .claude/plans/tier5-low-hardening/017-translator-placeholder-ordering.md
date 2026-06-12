# Task 017: Translator placeholder replacement ordering

**Status**: complete
**Depends on**: [none]
**Retry count**: 0

## Description
`Translator::applyReplacements()` loops the `$replacements` array and does a sequential `str_replace(":$placeholder", $replacement, $value)` for each entry. Two bugs follow from the sequential, unordered substitution:
1. A short placeholder name that is a prefix of a longer one corrupts the longer one. With `['attr' => 'x', 'attribute' => 'y']` and a string `:attribute`, the `:attr` pass rewrites it to `xibute` before the `:attribute` pass can match.
2. A replacement value that itself contains `:something` can be re-processed by a later iteration's `str_replace`.

Fix by replacing in a single non-recursive pass: order placeholders longest-name-first (so `:attribute` is tried before `:attr`) and substitute without re-scanning already-substituted text (e.g. one `strtr()` call built from `[":$name" => $value]`, which replaces non-overlapping and never re-processes output).

## Description-note
`strtr()` with an array is the idiomatic single-pass fix: it scans left-to-right, replaces the longest matching key at each position, and does NOT re-examine inserted text — solving both the prefix-collision and the re-replacement bug at once. Building the `[":$name" => $value]` map directly avoids the longest-first sort entirely (strtr already prefers the longest key). If `str_replace` is retained instead, an explicit longest-first sort of keys is required.

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/translation/src/Translator.php` (`applyReplacements()` ~165-180 — the `foreach ($replacements as $placeholder => $replacement) { $value = str_replace(":$placeholder", $replacement, $value); }` loop; called from `get()` ~45 and `choice()` ~73)
  - Tests: `/Users/markshust/Sites/marko/packages/translation/tests/` (locate the existing `TranslatorTest.php` and extend it)
- Verified findings (source-confirmed):
  - `applyReplacements(string $value, array $replacements): string` does exactly the sequential `str_replace(":$placeholder", $replacement, $value)` in a `foreach` with no ordering. Both `get()` and `choice()` route their final value through it.
  - `$replacement` values are interpolated as-is, so a value containing `:x` is exposed to later `str_replace` iterations.
- Patterns to follow:
  - Prefer building a `$map = [];` of `$map[":$name"] = (string) $value;` and returning `strtr($value, $map)`. `strtr` is single-pass and longest-key-preferring, which fixes both bugs cleanly with no manual sort.
  - Cast replacement values to `string` if the existing signature allows non-string values (confirm the param type; match it — do not widen or narrow the public contract).
  - Keep the method `private` and its signature unchanged. No config or interface changes.

## Requirements (Test Descriptions)
- [x] `it resolves :attribute to its own value when :attr is also a placeholder`
- [x] `it does not re-replace a placeholder appearing inside a replacement value`
- [x] `it replaces a single placeholder with its value`
- [x] `it leaves a string with no placeholders unchanged`

## Acceptance Criteria
- With replacements `['attr' => 'x', 'attribute' => 'y']`, the string `:attribute` resolves to `y` (not corrupted by the `:attr` substitution).
- A replacement value that contains `:x` is inserted literally and not re-substituted by another placeholder.
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
Replaced the sequential `foreach`/`str_replace` loop in `applyReplacements()` with a single `strtr($value, $map)` call where `$map` is built as `[":$placeholder" => $replacement]`. `strtr` is single-pass and longest-key-preferring, which fixes both bugs (overlapping prefix corruption and re-substitution of replacement values) with no manual sorting. Four tests added to `TranslatorTest.php` covering: overlapping prefix, no re-replacement, single placeholder, and no-placeholder passthrough.
