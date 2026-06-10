# Task 006: Log line-injection CR/LF escaping (line formatter)

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
`LineFormatter::format()` interpolates `{message}` (and `{context}`) raw, so a newline in the message forges a second physical log line. Add a default-on option to escape CR/LF in the interpolated message/context segments so a multi-line value renders on a single physical line, preventing log forgery without breaking deliberately multi-line human logs (the option can be disabled).

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/log/src/Formatter/LineFormatter.php` (~23; interpolates `{message}`/`{context}`)
  - `/Users/markshust/Sites/marko/packages/log/src/LogRecord.php` (`interpolatedMessage()`, `contextAsJson()` — source of the interpolated values)
  - `/Users/markshust/Sites/marko/packages/log/src/Config/LogConfig.php` (add `escapeNewlines()` getter — `LogConfig` DOES use lazy getter methods, so `escapeNewlines()` is the correct style here, unlike `WebhookConfig`)
  - `/Users/markshust/Sites/marko/packages/log/config/log.php` (add `escape_newlines` default `true`)
  - `/Users/markshust/Sites/marko/packages/log/module.php` — IMPORTANT: this is where the wiring actually happens. `LogFormatterInterface` is bound via a CLOSURE in `log/module.php` that constructs `new LineFormatter(format: ..., dateFormat: ...)`. `FileLoggerFactory` does NOT build the formatter — it receives a `LogFormatterInterface` via constructor injection. So the new flag must be threaded through the `log/module.php` closure: `new LineFormatter(format: $config->format(), dateFormat: $config->dateFormat(), escapeNewlines: $config->escapeNewlines())`. Do NOT touch `FileLoggerFactory` for this.
  - Tests: `/Users/markshust/Sites/marko/packages/log/tests/` (LineFormatter tests) and `/Users/markshust/Sites/marko/packages/log/tests/` (a `module.php` closure / binding test if one exists, or a direct test that the closure passes the flag)
- Patterns to follow:
  - `LineFormatter` is a `readonly class` with constructor-promoted `$format` and `$dateFormat`. Add a third promoted `bool $escapeNewlines = true` parameter (default safe).
  - When enabled, replace `\r` and `\n` in the interpolated `{message}` and `{context}` values with literal `\\r` / `\\n` (two-character escape sequences) BEFORE substitution into the format template (the template itself has no newlines, so escaping the interpolated values is sufficient). Do this per-value, not on the whole output, to avoid touching the format string's own structure.
  - The final `rtrim($output) . "\n"` trailing-newline behavior must be preserved (exactly one trailing newline per record). Note: because escaping happens on the interpolated values BEFORE the final `rtrim`, an escaped trailing `\n` (now the literal two chars `\n`) will not be stripped by `rtrim` — confirm the single-physical-line outcome holds with a trailing-newline message.
  - Config default lives in `config/log.php`; `LogConfig::escapeNewlines()` calls `getBool('log.escape_newlines')` with no fallback parameter.
  - The `log/module.php` closure (NOT the factory) reads the flag from `LogConfig` and passes it to `LineFormatter`.

## Requirements (Test Descriptions)
- [ ] `it escapes a newline in the message so the output is a single physical line when escaping is enabled`
- [ ] `it escapes a carriage return in the message when escaping is enabled`
- [ ] `it preserves the raw newline in the message when escaping is disabled`
- [ ] `it escapes newlines embedded in context values when escaping is enabled`
- [ ] `it produces exactly one trailing newline per formatted record even when the message ends in a newline`
- [ ] `it defaults escapeNewlines to true when the LineFormatter is constructed without the flag`
- [ ] `it wires escape_newlines from LogConfig into the LineFormatter via the log module binding`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
