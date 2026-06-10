# Task 010: F4 — Defensive header hardening in SmtpMailer::buildHeaders()

**Status**: pending
**Depends on**: [009]
**Retry count**: 0

## Description
Defense in depth at the SMTP layer: `SmtpMailer::buildHeaders()` writes From/To/Cc/Reply-To via `Address::toString()` and custom headers as `"$name: $value"` directly into the CRLF-joined header block, with only the Subject passing through `encodeHeader()`. Even though task 009 rejects CRLF at the value-object boundary, the mailer must not be the sole line of defense — reject any CR/LF that reaches a header name or value here too, so a future code path that bypasses the value objects cannot inject headers. Keep the existing `encodeHeader()` RFC-2047 path for the Subject unchanged.

## Context
- Related files:
  - `packages/mail-smtp/src/SmtpMailer.php` (`buildHeaders()` ~137-215; custom-header loop near ~205; `encodeHeader()` ~406)
  - `packages/mail-smtp/tests/` (Pest)
- Patterns to follow:
  - Reuse the `MessageException::headerInjection()` factory added in task 009 (or a transport-level equivalent if the package boundary requires `marko/mail-smtp` to throw its own — prefer reusing `marko/mail`'s `MessageException` since mail-smtp already depends on `marko/mail`).
  - Do NOT change the Subject `encodeHeader()` behavior; do NOT auto-encode display names.
- Gotchas (verified against source — `buildHeaders()` is lines 137-211):
  - The defensive CR/LF check must cover BOTH the custom-header loop (line ~206-208: `"$name: $value"`) AND the address-derived header lines (From/To/Cc/Reply-To built from `Address::toString()`, lines ~151-171). Although task 009 already guards `Address::$name`, this layer must reject a CR/LF in any assembled header line so a future bypass cannot inject. The cleanest implementation: a single private helper `assertNoCrlf(string $line): void` (or assert on each appended header line) applied to every entry except the controlled MIME/Content-Type lines the mailer itself generates.
  - Headers are joined with `"\r\n"` at line ~210 — that join is correct; the check ensures no individual header value already contains `\r`/`\n` (which would create extra header lines).
  - Reject `\r` and `\n` (and ideally `\x00`); do not strip.

## Requirements (Test Descriptions)
- [ ] `it rejects a custom header name containing CR or LF when building headers`
- [ ] `it rejects a custom header value containing CR or LF when building headers`
- [ ] `it builds a valid header block for a message with legitimate From, To, and Subject`
- [ ] `it still RFC-2047 encodes a Subject containing non-ASCII characters`
- [ ] `it joins headers with CRLF only between distinct headers (no injected CRLF inside a single header)`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
