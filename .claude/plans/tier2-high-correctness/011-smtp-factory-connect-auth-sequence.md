# Task 011: F7b — Factory connect→EHLO→STARTTLS(220)→AUTH(case-insensitive) sequence + SmtpConfig defaults + module binding

**Status**: complete
**Depends on**: [010]
**Retry count**: 0

## Description
The SMTP driver never establishes a session. `SmtpMailerFactory::create()` constructs
an `SmtpTransport` but never calls `connect()`/`ehlo()`/`startTls()`/`authenticate()`,
and `SmtpConfig` is entirely unused. `SmtpTransport::startTls()` writes STARTTLS and
reads but never checks for the 220 reply before enabling crypto, and
`authenticate()` only matches the exact strings `'LOGIN'`/`'PLAIN'` while
`SmtpConfig::authMode()` defaults to lowercase `'login'` — so auth is silently
skipped. Wire the full connect/auth/STARTTLS sequence from `SmtpConfig`, make auth
mode matching case-insensitive, make STARTTLS assert the 220 reply, remove
`SmtpConfig`'s hardcoded fallbacks, and bind the concrete socket in `module.php`.

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/mail-smtp/src/SmtpMailerFactory.php`
    (`create()` returns `new SmtpMailer(transport: new SmtpTransport($this->socket))`
    with no session setup; `$this->config` SmtpConfig unused)
  - `/Users/markshust/Sites/marko/packages/mail-smtp/src/SmtpTransport.php`
    (`connect/ehlo/startTls/authenticate/authenticateLogin/authenticatePlain/mailFrom/
    rcptTo/data`; `authenticate($u,$p,$mode='LOGIN')` only branches on exact `'LOGIN'`/
    `'PLAIN'`; `startTls()` writes STARTTLS, reads, then `enableTls()` with NO 220 check)
  - `/Users/markshust/Sites/marko/packages/mail-smtp/src/SmtpConfig.php`
    (`host()` returns `?? 'localhost'`, `port()` `?? 587`, `encryption()` `?? 'tls'`,
    `authMode()` `?? 'login'` (lowercase), `username/password/timeout` — all with
    hardcoded `??` fallbacks that VIOLATE the no-fallback config standard)
  - `/Users/markshust/Sites/marko/packages/mail-smtp/module.php`
    (binds `MailerInterface` via `SmtpMailerFactory`; does NOT bind `SocketInterface`)
  - `/Users/markshust/Sites/marko/packages/mail/config/mail.php` (where the smtp driver
    defaults belong) and `/Users/markshust/Sites/marko/packages/mail-smtp/tests/Unit/
    SmtpMailerFactoryTest.php`
- Patterns to follow:
  - **Config-key reality check.** `config/mail.php`'s `smtp` section currently has
    `host, port, encryption, username (null), password (null), timeout` — it does NOT
    have `auth_mode`. `SmtpConfig` reads via `MailConfig::driverConfig('smtp')` which
    returns the whole array (`$config->getArray('mail.smtp')`), then does array-`??`.
    Two required changes: (1) ADD `'auth_mode' => 'login'` to the `smtp` block in
    `config/mail.php` (otherwise dropping the `?? 'login'` fallback makes `authMode()`
    fail). (2) Replace each `SmtpConfig` getter's `?? <default>` with a present-key read:
    `array_key_exists` check and throw a loud domain exception (e.g.
    `MailException`/`ConfigNotFoundException`) when a REQUIRED key
    (`host/port/encryption/timeout/auth_mode`) is missing — no hardcoded fallback, per
    `.claude/code-standards.md`.
  - **username/password stay nullable — do NOT make them throw.** They are present in
    config as `null` and represent "no-auth SMTP". `username()`/`password()` return
    `?string`; read the key (which exists, value null) without a `?? null` literal
    default, but treat a genuinely-absent key as null is acceptable here ONLY for these
    two optional credentials. The "skips authentication when no username/password is
    configured" requirement depends on null being valid. Document this exception in a
    code comment so a future reviewer does not "fix" it back to throwing.
  - `authenticate()` matches mode via `strtoupper($mode)` so `'login'`/`'plain'` work.
  - `startTls()` asserts the STARTTLS reply code is 220 (reuse `expectResponseCode`/
    `expectSuccess` helpers) before `enableTls()`, throwing `TransportException::tlsFailed`.
  - Factory sequence using SmtpConfig: connect(host,port,encryption) → ehlo(hostname) →
    perform STARTTLS only when `encryption === 'tls'` (explicit STARTTLS upgrade); when
    `encryption === 'ssl'` the socket is already encrypted from connect (implicit TLS) so
    do NOT issue STARTTLS; after a successful STARTTLS re-`ehlo` →
    authenticate(username,password,authMode) only when username AND password are present.
    Choose a stable EHLO hostname (e.g. config-driven or `gethostname()`); do not leave it
    empty.
  - `module.php` binds `SocketInterface => StreamSocket` (from Task 010).
  - Protocol tests use the existing `createMockSocket` fake (queued responses,
    `$written`, `$tlsEnabled`).

## Requirements (Test Descriptions)
- [x] `it connects, sends EHLO, and authenticates using values from SmtpConfig when create() builds the mailer`
- [x] `it authenticates when the configured auth mode is lowercase "login" (case-insensitive matching)`
- [x] `it authenticates when the configured auth mode is lowercase "plain"`
- [x] `it performs STARTTLS only after confirming a 220 reply, and throws TransportException
      when the server does not reply 220 to STARTTLS`
- [x] `it skips authentication when no username/password is configured (null credentials
      are valid; no exception thrown)`
- [x] `it does not issue STARTTLS when encryption is "ssl" (implicit TLS from connect)`
- [x] `it throws a loud exception (no hardcoded fallback) when a required SmtpConfig key
      (host/port/encryption/timeout/auth_mode) is missing`
- [x] `config/mail.php smtp section includes an auth_mode default`
- [x] `it binds SocketInterface to the concrete StreamSocket in module.php`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
- Added `auth_mode => 'login'` to `packages/mail/config/mail.php` smtp section
- Updated `SmtpConfig` to throw `MailException::missingRequiredSmtpKey()` for required keys (host/port/encryption/timeout/auth_mode); username/password remain nullable with `?? null` fallback
- Added `MailException::missingRequiredSmtpKey()` static factory method
- Updated `SmtpTransport::startTls()` to assert 220 reply before calling `enableTls()`
- Updated `SmtpTransport::authenticate()` to use `strtoupper($mode)` for case-insensitive matching
- Updated `SmtpMailerFactory::create()` to run full connect→ehlo→[STARTTLS+re-ehlo]→[authenticate] sequence; STARTTLS only when encryption==='tls'; auth skipped when username/password are null
- Added `SocketInterface => StreamSocket` binding to `module.php`
- Extracted `MockSocket` class to `packages/mail-smtp/tests/Unit/MockSocket.php` (autoloaded via PSR-4)
- Created `packages/mail-smtp/tests/Unit/Helpers.php` with `Helpers::createMockSocket()` and `Helpers::createSmtpConfig()` static helpers
- Updated `SmtpTransportTest.php` to delegate `createMockSocket()` to `Helpers::createMockSocket()`
