# Task 010: F7a — Concrete StreamSocket implementing SocketInterface

**Status**: pending
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
    (methods: `connect(string $host, int $port, ?string $encryption = null, int
    $timeout = 30): void`, `read(): string`, `write(string $data): void`,
    `enableTls(): bool`, and a `close()`-style method — confirm the full interface)
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
  - No magic methods; full types; `declare(strict_types=1)`. Live network behaviour is
    covered by the thin integration test only.
- Note: the live socket test opens a real TCP stream — group it `integration-destructive`
  so the fast `composer test` run stays offline/deterministic. Protocol behaviour is
  tested via the fake socket in Tasks 011/012.

## Requirements (Test Descriptions)
- [ ] `it implements SocketInterface (declares connect, read, write, enableTls, close
      with the interface signatures)`
- [ ] `it throws a loud TransportException when the connection cannot be established`
- [ ] `it reads a single CRLF-terminated reply line from the stream`
- [ ] `it accumulates a multi-line SMTP reply (250- continuations) into one read result`
- [ ] `it writes raw bytes to the stream verbatim`
- [ ] `(integration-destructive) it opens, reads the greeting from, and closes a real TCP
      stream against a reachable SMTP endpoint`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
