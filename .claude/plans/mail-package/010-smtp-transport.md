# Task 010: SmtpTransport

**Status**: pending
**Depends on**: 002
**Retry count**: 0

## Description
Create the SmtpTransport class for low-level SMTP protocol communication.

## Context
- Handles socket connection to SMTP server
- EHLO handshake
- STARTTLS negotiation
- SSL/TLS connection support
- Authentication (LOGIN, PLAIN)
- MAIL FROM / RCPT TO / DATA commands
- Proper response code handling
- Throws TransportException on failures

## Requirements (Test Descriptions)
- [ ] `SmtpTransport connects to server`
- [ ] `SmtpTransport throws on connection failure`
- [ ] `SmtpTransport sends EHLO command`
- [ ] `SmtpTransport handles STARTTLS`
- [ ] `SmtpTransport throws on TLS failure`
- [ ] `SmtpTransport authenticates with LOGIN`
- [ ] `SmtpTransport authenticates with PLAIN`
- [ ] `SmtpTransport throws on auth failure`
- [ ] `SmtpTransport sends MAIL FROM command`
- [ ] `SmtpTransport sends RCPT TO command`
- [ ] `SmtpTransport sends DATA command`
- [ ] `SmtpTransport handles unexpected response codes`

## Implementation Notes
