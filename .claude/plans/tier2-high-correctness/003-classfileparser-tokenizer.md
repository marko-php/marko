# Task 003: F3 — Tokenizer-based ClassFileParser::extractClassName

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
`ClassFileParser::extractClassName()` extracts the class name with
`preg_match('/class\s+(\w+)/')` over raw file bytes, so the word "class" inside a
docblock, comment, or string wins and the parser returns the wrong name (or a bogus
one) — routes, commands, observers, plugins, and preferences silently vanish from
discovery. Replace the regex scan with a `token_get_all()` tokenizer that correctly
finds the namespace and the first top-level type declaration.

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/core/src/Discovery/ClassFileParser.php`
    (`extractClassName()` — the two `preg_match` calls for namespace + class are the bug)
  - Discovery consumers funnel through this parser (observers, routes, preferences,
    plugins, commands) — confirm with `tests/` in `packages/core` for discovery.
- Patterns to follow:
  - Use `token_get_all($contents)`; track `T_NAMESPACE` then concatenate following
    name tokens (`T_STRING`/`T_NAME_QUALIFIED`/`T_NS_SEPARATOR`) until `;` or `{`.
  - Detect a type keyword `T_CLASS`/`T_INTERFACE`/`T_TRAIT`/`T_ENUM` immediately
    followed (skipping whitespace) by a `T_STRING` name.
  - Skip `::class` (a `T_CLASS` preceded by `T_DOUBLE_COLON`) and anonymous classes
    (`new class` — `T_CLASS` preceded by `T_NEW`).
  - `final`/`abstract`/`readonly` are distinct tokens before `T_CLASS` and need no
    special handling beyond not treating them as the name.
- Note: the standalone `discovery-cache` plan builds on correct discovery output, so
  it must land AFTER this task — record that ordering in the discovery-cache plan when
  it is scheduled.

## Requirements (Test Descriptions)
- [ ] `it extracts the class name when a docblock above the class contains the word "class"`
- [ ] `it extracts the class name when the file body contains the word "class" inside a string literal`
- [ ] `it returns the fully qualified name combining namespace and class`
- [ ] `it extracts the name of a final class declaration`
- [ ] `it extracts the name of an abstract class declaration`
- [ ] `it extracts the name of a readonly class declaration`
- [ ] `it extracts the name of an interface, a trait, and an enum declaration`
- [ ] `it returns null for a file that declares no class, interface, trait, or enum`
- [ ] `it ignores ::class constant references and new class anonymous-class expressions
      when no real top-level type is declared`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
