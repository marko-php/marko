# Task 001: GuidelinesWriter — marker-aware file writer

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create a `GuidelinesWriter` static helper that owns all marker-aware writes for devai-generated guideline files. It is the single mechanism guaranteeing user overridability: create when absent, replace only between markers when present, and back off untouched (with a loud notice) when markers are missing. This is the foundation every agent depends on.

## Context
- New file: `packages/devai/src/Writing/GuidelinesWriter.php` (namespace `Marko\DevAi\Writing`).
- New tests: `packages/devai/tests/Unit/Writing/GuidelinesWriterTest.php`.
- Pattern to follow: `packages/devai/src/Skills/SkillsDistributor.php` — established static filesystem-sync helper called directly by agents. Mirror that style (static methods, real-dir behavior).
- Marker format: `<!-- BEGIN marko:devai -->` and `<!-- END marko:devai -->`. The BEGIN line (or an adjacent line in the wrapped region) must communicate: generated, do-not-edit between markers, re-run `marko devai:update` to regenerate, and content OUTSIDE the markers is the user's. This is the SINGLE place the "do not edit / regenerate" stamp lives (task 003 removes the renderer's standalone header so it is not duplicated). The BEGIN line must literally contain the string `marko devai:update` so the renderer test that previously asserted that hint can re-target the writer output.
- Return a `WriteOutcome` enum at `Marko\DevAi\Writing\WriteOutcome` (NOT a value object, NOT under `ValueObject` — pin the namespace so dependent tasks don't guess) with cases `Created`, `Updated`, `SkippedNoMarkers`. Callers use the returned outcome to surface a loud notice and tests assert on it.
- **Notice-surfacing contract (interface gap — must be resolved here so agent tasks 004–009 have a channel).** `GuidelinesWriter::write()` returns the `WriteOutcome`; it must NOT echo/print or write to STDERR itself (keeps it pure and testable). The agent calling the writer is responsible for surfacing a `SkippedNoMarkers` outcome. Because `AgentInterface::install()` returns `void` and agents have no `Output`, the chosen mechanism is: add a static notice-collector to `GuidelinesWriter` — `GuidelinesWriter::write()` appends a human-readable message to an internal static `array $notices` whenever it returns `SkippedNoMarkers`, exposed via `GuidelinesWriter::takeNotices(): array` (returns and clears). The orchestrator calls `GuidelinesWriter::takeNotices()` after the agent loop and appends each message to `$this->log` so the install/update command prints it (loud, visible). Define and unit-test this collector here; the orchestrator wiring is added in task 010.
- Idempotency detail: when markers are present, replace ONLY the text between the first BEGIN and the first matching END; never append a second pair; preserve the exact bytes before BEGIN and after END (including trailing newline shape).
- Edge case: a file that contains a BEGIN marker but NO END marker (or END before BEGIN) is treated as malformed — return `SkippedNoMarkers` and leave it untouched (loud notice). Do not attempt a partial rewrite.
- Follow Marko standards: `declare(strict_types=1)`, constructor property promotion where applicable, no final, explicit types, loud errors.

## Requirements (Test Descriptions)
- [x] `it creates the file with wrapped markers when the path does not exist`
- [x] `it returns created outcome when it writes a new file`
- [x] `it replaces only the content between existing markers and returns updated`
- [x] `it preserves user content outside the markers byte for byte when updating`
- [x] `it leaves the file completely untouched and returns skipped when markers are absent`
- [x] `it does not append a second marker pair when markers already exist`
- [x] `it wraps arbitrary generated content not just the guidelines body`
- [x] `it returns skipped and leaves the file untouched when a begin marker exists but the end marker is missing`
- [x] `it embeds the marko devai:update regenerate hint inside the wrapped region`
- [x] `it records a loud notice retrievable via takeNotices when it skips a marker-stripped file`
- [x] `it clears recorded notices after takeNotices is called`

## Acceptance Criteria
- All requirements have passing tests
- Marker constants are defined once on `GuidelinesWriter` and reused everywhere
- `WriteOutcome` enum lives at `Marko\DevAi\Writing\WriteOutcome` with cases `Created`, `Updated`, `SkippedNoMarkers`
- The notice collector (`takeNotices()`) is the sole surfacing channel; the writer never prints/echoes directly
- No decrease in test coverage
- Code follows code standards

## Implementation Notes
- `WriteOutcome` enum at `packages/devai/src/Writing/WriteOutcome.php` with cases `Created`, `Updated`, `SkippedNoMarkers`.
- `GuidelinesWriter` at `packages/devai/src/Writing/GuidelinesWriter.php` — static-method helper, no constructor.
- `MARKER_BEGIN` and `MARKER_END` are typed `public const string` on `GuidelinesWriter`; tests reference them via `GuidelinesWriter::MARKER_BEGIN`.
- `NOTICE_HEADER` is a `private const string` embedded inside the wrapped region (contains `marko devai:update`).
- `$notices` is a `private static array` drained by `takeNotices(): array` which returns and clears in one call.
- Malformed-file guard: if `MARKER_END` is missing or appears before `MARKER_BEGIN`, returns `SkippedNoMarkers` + notice, file untouched.
- Tests use real temp dirs via `sys_get_temp_dir()` with `uniqid` suffix, cleaned up per test.
- Static notice state is drained at the start of any test that asserts notices to avoid cross-test pollution.
- All 11 tests pass; full devai suite (183 tests) green; php-cs-fixer applied to all three files.
