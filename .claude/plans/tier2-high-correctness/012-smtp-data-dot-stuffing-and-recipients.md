# Task 012: F7c — DATA dot-stuffing + multi-recipient correctness

**Status**: pending
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
- [ ] `it doubles a leading dot on a message body line that begins with "." in DATA`
- [ ] `it doubles the leading dot on a line consisting solely of "." so it is not treated
      as the DATA terminator`
- [ ] `it leaves message lines that do not begin with a dot unchanged`
- [ ] `it appends the \r\n.\r\n terminator exactly once and does not dot-stuff the terminator`
- [ ] `it sends one RCPT TO command per recipient across to, cc, and bcc`
- [ ] `it throws a loud TransportException when the server rejects a RCPT TO recipient`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
