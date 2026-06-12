# Task 010: F7a — Concrete StreamSocket implementing SocketInterface

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
`marko/mail-smtp` ships only a `SocketInterface` and test fakes — there is NO concrete
socket, so the SMTP driver can never open a connection in production. Implement a real
stream-based `StreamSocket` that satisfies `SocketInterface` using PHP stream
functions, with loud `TransportException`s on failure.

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/mail-smtp/src/SocketInterface.php`
    (VERIFIED full contract: `connect(string $host, int $port, ?string $encryption = null,
    int $timeout = 30): void`, `read(): string`, `write(string $data): void`,
    `enableTls(): bool`, `close(): void`, AND a property hook
    `public bool $connected { get; }`. The `$connected` get-hook is a REQUIRED interface
    member — `StreamSocket` is fatal at load time without it. Back it with whether the
    stream resource is currently open.)
  - `/Users/markshust/Sites/marko/packages/mail/src/Exception/TransportException.php`
    (`connectionFailed(host, port)`, `tlsFailed(host)`, `authenticationFailed(username)`,
    `unexpectedResponse(code, response)` — use these loud factories)
  - `/Users/markshust/Sites/marko/packages/mail-smtp/tests/` (the `createMockSocket`
    fake pattern with `$connected`, `$written`, `$tlsEnabled`, response queue)
- Patterns to follow:
  - `connect()`: `stream_socket_client("tcp://$host:$port", ...)` honouring `$timeout`;
    on `ssl`/`tls` implicit encryption use the `ssl://` transport; throw
    `TransportException::connectionFailed($host, $port)` on failure.
  - `read()`: read a full CRLF-terminated SMTP reply line (handle multi-line `250-` …
    `250 ` continuations via `fgets` loop).
  - `write()`: `fwrite` the raw bytes (caller supplies CRLF).
  - `enableTls()`: `stream_socket_enable_crypto($stream, true,
    STREAM_CRYPTO_METHOD_TLS_CLIENT)` returning the bool result.
  - `close()`: `fclose` and null the resource.
  - `$connected` (property get-hook): return `true` while the stream resource is a live
    open resource, `false` after `close()` or before `connect()`. Implement it as a
    `public bool $connected { get => $this->stream !== null; }` hook (or equivalent) to
    satisfy the interface property member.
  - No magic methods; full types; `declare(strict_types=1)`. Live network behaviour is
    covered by the thin integration test only.
- Note: the live socket test opens a real TCP stream — group it `integration-destructive`
  so the fast `composer test` run stays offline/deterministic. Protocol behaviour is
  tested via the fake socket in Tasks 011/012.

## Requirements (Test Descriptions)
- [x] `it implements SocketInterface (declares connect, read, write, enableTls, close,
      and the $connected property get-hook with the interface signatures)`
- [x] `it reports $connected as false before connect and after close, and true while the
      stream is open`
- [x] `it throws a loud TransportException when the connection cannot be established`
- [x] `it reads a single CRLF-terminated reply line from the stream`
- [x] `it accumulates a multi-line SMTP reply (250- continuations) into one read result`
- [x] `it writes raw bytes to the stream verbatim`
- [x] `(integration-destructive) it opens, reads the greeting from, and closes a real TCP
      stream against a reachable SMTP endpoint`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
- `StreamSocket` implemented at `packages/mail-smtp/src/StreamSocket.php`
- Uses `protected` visibility for `$stream` property so test subclasses can inject it
- `$connected` property hook: `public bool $connected { get => $this->stream !== null; }`
- `connect()`: uses `stream_socket_client` with ssl:// transport for ssl/tls encryption, throws `TransportException::connectionFailed` on failure
- `read()`: loops `fgets` accumulating multi-line SMTP replies (250-...) until a terminating line (4th char is space or line < 4 chars), strips trailing CRLF
- `write()`: calls `fwrite` with raw data
- `enableTls()`: calls `stream_socket_enable_crypto` with `STREAM_CRYPTO_METHOD_TLS_CLIENT`
- `close()`: calls `fclose` and nulls `$stream`
- Integration test at `packages/mail-smtp/tests/Integration/StreamSocketIntegrationTest.php` tagged `integration-destructive`
- Unit tests use `TestableStreamSocket` subclass with `injectStream()` for read/write testing without network
