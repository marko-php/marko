# Task 012: F7c — DATA dot-stuffing + multi-recipient correctness

**Status**: complete
**Depends on**: [011]
**Retry count**: 0

## Description
`SmtpTransport::data()` writes the message body followed by `\r\n.\r\n` without
performing SMTP dot-stuffing, so any message line that begins with a `.` is
misinterpreted by the server (a line `.` prematurely terminates DATA; lines like
`...text` lose a dot) — corrupting or truncating the email. Add RFC 5321 dot-stuffing
(every line beginning with `.` gets an extra leading `.`) and verify multi-recipient
envelope handling end-to-end.

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/mail-smtp/src/SmtpTransport.php`
    (`data(string $content)`: writes `"DATA\r\n"`, expects 354, then writes
    `$content . "\r\n.\r\n"` with NO dot-stuffing; `mailFrom`/`rcptTo` send one
    envelope command each and `expectSuccess`)
  - `/Users/markshust/Sites/marko/packages/mail-smtp/src/SmtpMailer.php`
    (`send()` calls `mailFrom($from->email)`, then `rcptTo()` per recipient from
    `getAllRecipients()` (to+cc+bcc), then `data($rawMessage)`; throws
    `MessageException::noRecipients()` when empty)
  - `/Users/markshust/Sites/marko/packages/mail/src/Message.php` (recipient accessors)
  - `/Users/markshust/Sites/marko/packages/mail-smtp/tests/` (`createMockSocket` fake)
- Patterns to follow:
  - Dot-stuff before sending: split `$content` on CRLF, prefix any line starting with
    `.` with an extra `.`, rejoin, then append the `\r\n.\r\n` terminator. Do not
    double-stuff the terminating dot.
  - Multi-recipient: one `RCPT TO` per recipient across to/cc/bcc; a failing recipient
    surfaces a loud `TransportException` (no silent skip).

## Requirements (Test Descriptions)
- [x] `it doubles a leading dot on a message body line that begins with "." in DATA`
- [x] `it doubles the leading dot on a line consisting solely of "." so it is not treated
      as the DATA terminator`
- [x] `it leaves message lines that do not begin with a dot unchanged`
- [x] `it appends the \r\n.\r\n terminator exactly once and does not dot-stuff the terminator`
- [x] `it sends one RCPT TO command per recipient across to, cc, and bcc`
- [x] `it throws a loud TransportException when the server rejects a RCPT TO recipient`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
- Added `dotStuff(string $content): string` private method to `SmtpTransport` that splits on `\r\n`, prefixes any line starting with `.` with an extra `.`, and rejoins.
- Called from `data()` before appending the `\r\n.\r\n` terminator — the terminator is appended after stuffing so it is never double-stuffed.
- Requirements 2–6 passed immediately (existing implementation already covered multi-recipient, rejection throwing, and the terminator placement); only requirement 1 required new implementation code.
- 6 new tests added: 4 in `SmtpTransportTest.php`, 2 in `SmtpMailerTest.php`.
