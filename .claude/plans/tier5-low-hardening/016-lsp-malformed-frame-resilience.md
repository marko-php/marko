# Task 016: lsp server dies on one malformed frame

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
`LspProtocol::readMessage()` returns `null` for TWO different conditions: genuine EOF (`fgets()` returns `false`) and a malformed header block (no/zero `Content-Length`, where it returns `null` after the header loop). `serve()` treats any `null` from `readMessage()` as EOF and `break`s the loop, so a single malformed frame kills the entire language server. Distinguish a malformed frame from real EOF; on a malformed frame, write a JSON-RPC parse error (`-32700`) and continue serving; only true EOF ends the loop.

## Description-note
`handleMessage()` already emits `-32700` for un-decodable JSON bodies, so the parse-error response shape exists. The gap is that a header-level malformation (zero `Content-Length`) never reaches `handleMessage()` — it collapses to the same `null` as EOF in `serve()`. The fix is to make the EOF-vs-malformed distinction explicit at the protocol boundary.

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/lsp/src/Protocol/LspProtocol.php` (`serve()` ~33-42 — the `$message = $this->readMessage(); if (... === null) break;` loop; `readMessage()` ~45-80 — returns `null` on `fgets()===false` AND on `$contentLength === 0`; `handleMessage()` ~82+ already writes `-32700` for JSON errors)
  - `/Users/markshust/Sites/marko/packages/lsp/src/Server/LspServer.php` (`serve()` ~37-39 delegates to `protocol->serve()`)
  - Tests: `/Users/markshust/Sites/marko/packages/lsp/tests/` (locate the existing LSP protocol test and extend it)
- Verified findings (source-confirmed):
  - `readMessage()`: returns `null` when `fgets()===false` (EOF) inside the header loop, AND returns `null` when `$contentLength === 0` after the header loop completes. `serve()` cannot tell these apart — both `break` the loop.
  - `handleMessage()` already does `json_decode(..., JSON_THROW_ON_ERROR)` in a try/catch and writes `['jsonrpc'=>'2.0','error'=>['code'=>-32700,'message'=>'Parse error'],'id'=>null]` on `JsonException`.
- Patterns to follow:
  - Make the EOF-vs-malformed signal explicit. Two viable shapes (pick the one that reads cleanest against the existing code, do not over-engineer):
    1. Have `readMessage()` distinguish: return `null` ONLY on true EOF (`fgets()===false` before any header bytes / clean stream end); on a header block that ends with `$contentLength === 0`, signal "malformed" distinctly (e.g. a sentinel or a small `ReadResult` value object with an `isEof()`/`isMalformed()` distinction), so `serve()` can respond `-32700` and `continue`.
    2. Alternatively keep `readMessage()` returning `?string` but track whether the loop reached EOF vs. terminated a header block; expose that via a tiny private helper or a second return channel.
  - In `serve()`: on true EOF → `break` (loop ends, server shuts down). On a malformed frame → write the same JSON-RPC parse-error response used by `handleMessage()` (code `-32700`, `id => null`) and `continue` so subsequent valid frames are still processed.
  - Reuse the existing `writeResponse(...)` for the `-32700` payload; keep the `-32700`/`id=>null` shape consistent with `handleMessage()`. No interface signature changes to `LspServer`.
  - Tests should drive a fake input stream containing: a malformed frame (e.g. a header block with `Content-Length: 0` or no Content-Length), followed by a valid framed message, followed by EOF — and assert the valid message is still handled and the loop terminates only at EOF.

## Requirements (Test Descriptions)
- [ ] `it does not terminate the serve loop on a malformed frame`
- [ ] `it writes a JSON-RPC parse error for a malformed frame`
- [ ] `it processes a valid message that follows a malformed frame`
- [ ] `it ends the serve loop on genuine end of input`

## Acceptance Criteria
- A malformed frame produces a `-32700` parse-error response and the server keeps serving.
- A valid framed message after a malformed one is still processed.
- True EOF still ends the serve loop (server shuts down cleanly).
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
