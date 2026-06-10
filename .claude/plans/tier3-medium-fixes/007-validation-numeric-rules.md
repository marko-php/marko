# Task 007: Numeric-aware Min/Max/Between and loose In/NotIn for numeric strings

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
`Min`/`Max`/`Between` check `is_string` (string length) before `is_numeric`, so HTTP input — always strings — makes `integer|min:18` reject `"25"` (length 2 < 18). They must compare numerically when the field is numeric, falling back to string-length only for genuinely non-numeric strings. Separately, `In`/`NotIn` use strict `in_array(..., true)`, so a submitted `"1"` never matches an allowed `1`. Make In/NotIn match numeric strings against numeric allow-list entries.

## Context
- Related files: `packages/validation/src/Rules/Min.php` (passes ~15-37, `is_string` BEFORE `is_numeric`), `Max.php` (passes ~15-37, same ordering), `Between.php` (passes ~16-44, same ordering), `In.php` (passes ~22-32, strict `in_array(..., true)`, plain `class` — not `readonly`), `NotIn.php` (passes ~22-32, plain `class`), `Integer.php`, `Numeric.php`, `packages/validation/src/Contracts/RuleInterface.php` (`passes(field, value, data)`), `packages/validation/src/Validation/Validator.php` (rule pipeline)
- Patterns to follow: the fix is a **value-level reorder**, not a cross-rule lookup. A `Min`/`Max`/`Between` rule instance cannot see the sibling `integer`/`numeric` rule on the same field (the `$data` array holds field *values*, not the rule set), so do NOT try to detect "the field is declared numeric" from `$data`. Instead, **check `is_numeric($value)` BEFORE `is_string($value)`** so a numeric string like `"25"` compares as the number `25` (→ `25 >= 18` passes), while a genuinely non-numeric string like `"abc"` still falls through to `mb_strlen` length mode. Keep the array `count` branch first. Verified current ordering in all three rules is `is_string` → `is_array` → `is_numeric`; the reorder is `is_array` → `is_numeric` → `is_string`.
- In/NotIn: loose-match numeric strings against numeric entries (so submitted `"1"` matches allowed `1`) while leaving non-numeric values strict. Note In/NotIn are currently plain `class` (variadic `mixed ...$values` assigned in the constructor body, not promoted); promoting to `readonly class` is optional — if left as `class`, mark `$values` `readonly` since it is write-once.
- The `message()` methods in Min/Max/Between also branch on `is_string` and would emit a misleading "...characters" message for a numeric string. Apply the same numeric-before-string ordering in `message()` so a numeric value yields the value-oriented message, not the character-length one.

## Requirements (Test Descriptions)
- [ ] `it accepts a numeric string at or above the minimum for the integer min rule`
- [ ] `it rejects a numeric string below the minimum for the integer min rule`
- [ ] `it still measures string length for the min rule on a non-numeric string`
- [ ] `it compares numerically for the max rule on a numeric string`
- [ ] `it compares numerically for the between rule on a numeric string and keeps length mode for non-numeric strings`
- [ ] `it counts array items for the min and between rules on an array value`
- [ ] `it matches a numeric string against a numeric allow-list entry for the In rule`
- [ ] `it keeps strict matching for a non-numeric string in the In rule`
- [ ] `it rejects a numeric string present in a numeric disallow-list for the NotIn rule`
- [ ] `it produces a value-oriented (not character-length) failure message for a numeric string failing min`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
