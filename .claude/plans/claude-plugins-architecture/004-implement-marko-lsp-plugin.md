# Task 004: Implement `marko-lsp` plugin (manifest + .lsp.json)

**Status**: completed
**Depends on**: 001, 002
**Retry count**: 0

## Description
Build the `marko-lsp` Claude Code plugin under `packages/claude-plugins/plugins/marko-lsp/`. This plugin registers the existing `marko lsp:serve` LSP server (provided by `packages/lsp/`) with Claude Code via the official `.lsp.json` schema. Includes a `bin/marko-lsp` POSIX shim script (mirroring the marko-mcp shim from Task 003) that locates `vendor/bin/marko` and execs `marko lsp:serve`. The LSP coexists with the official `php-lsp@claude-plugins-official` (intelephense) per Task 001 finding F5 — both deliver complementary diagnostics; README recommends users uninstall `php-lsp@claude-plugins-official` if they install marko-lsp since marko-lsp wraps the same intelephense binary with Marko-tuned init options.

## Context
- Related files:
  - New: `packages/claude-plugins/plugins/marko-lsp/.claude-plugin/plugin.json`
  - New: `packages/claude-plugins/plugins/marko-lsp/.lsp.json`
  - New: `packages/claude-plugins/plugins/marko-lsp/bin/marko-lsp` (POSIX shell shim, executable bit set)
  - New: `packages/claude-plugins/plugins/marko-lsp/README.md`
  - Read: `packages/lsp/composer.json` to confirm the `marko lsp:serve` invocation
  - Reference: `.claude/plans/claude-plugins-architecture/schemas/lsp.json.example.json` and `schemas/plugin.json.example.json` (output of Task 001)
- **`.lsp.json` shape** (verified per Task 001 finding F1, with shim path per F8):
  ```json
  {
    "marko-lsp": {
      "command": "${CLAUDE_PLUGIN_ROOT}/bin/marko-lsp",
      "args": [],
      "extensionToLanguage": { ".php": "php" }
    }
  }
  ```
- Required fields: `command`, `extensionToLanguage`. `extensionToLanguage` keys must include leading dot.
- **Omit `.latte`** per Task 001 finding F9 — Latte LSP language identifier is not recognized; including it would either be silently ignored or fail validation. Ship `.php` only for v1; revisit if a Latte LSP appears.
- **Shim script `bin/marko-lsp`** (POSIX sh, executable, mirrors the marko-mcp pattern from Task 003): locate `marko` in `./vendor/bin/marko` → global PATH; exec `marko lsp:serve "$@"`; loud error if no binary found.
- Per Task 001 finding F8, plugin-shipped `.lsp.json` is the only registration path — devai cannot substitute paths at install time.

## Requirements (Test Descriptions)
- [x] `plugin.json declares name "marko-lsp" and a description matching the marketplace.json entry`
- [x] `plugin.json includes author with name "Marko Framework"`
- [x] `plugin.json does not include a version field`
- [x] `.lsp.json top-level structure is an object keyed by server name (not wrapped in lspServers per Task 001 F1 verbatim)`
- [x] `.lsp.json marko-lsp.command is "${CLAUDE_PLUGIN_ROOT}/bin/marko-lsp"`
- [x] `.lsp.json marko-lsp.args is an empty array (subcommand hardcoded inside the shim)`
- [x] `.lsp.json marko-lsp.extensionToLanguage maps only .php to php (no .latte for v1, per Task 001 F9)`
- [x] `.lsp.json each extensionToLanguage key starts with a leading dot`
- [x] `bin/marko-lsp shim script exists, has POSIX shebang, is committed with executable bit set`
- [x] `bin/marko-lsp searches for marko binary in order: ./vendor/bin/marko then marko on PATH`
- [x] `bin/marko-lsp execs the discovered binary with "lsp:serve" plus any forwarded args`
- [x] `bin/marko-lsp prints a loud error to stderr and exits 1 when no marko binary is found`
- [x] `README.md explains marko-lsp coexists with php-lsp (intelephense), what marko-lsp adds beyond it, the recommendation to uninstall php-lsp@claude-plugins-official to avoid duplication, and how to verify with claude plugin list`

## Acceptance Criteria
- All requirements have passing tests (PHP-side schema validation, leading-dot assertion, required-field presence)
- Plugin directory layout: `.claude-plugin/plugin.json` is the only file inside `.claude-plugin/`; `.lsp.json` is at plugin root
- `composer test` for the claude-plugins package passes

## Implementation Notes
- Created `plugins/marko-lsp/.claude-plugin/plugin.json` with `name`, `description` (matching marketplace.json entry), `author.name: "Marko Framework"`, standard metadata fields; no `version` field per convention.
- Created `plugins/marko-lsp/.lsp.json` as a flat object keyed by `"marko-lsp"` (not wrapped in `lspServers`), with `command: "${CLAUDE_PLUGIN_ROOT}/bin/marko-lsp"`, `args: []`, and `extensionToLanguage: {".php": "php"}` only (`.latte` omitted per F9).
- Created `plugins/marko-lsp/bin/marko-lsp` POSIX sh shim that searches `./vendor/bin/marko` then `marko` on PATH, execs `lsp:serve "$@"`, and prints loud stderr error + exits 1 if no binary found. File has executable bit set (chmod +x).
- Created `plugins/marko-lsp/README.md` covering: what marko-lsp adds beyond plain intelephense, coexistence with `php-lsp@claude-plugins-official`, uninstall recommendation to avoid duplicate diagnostics, `/plugin install marko-lsp@marko` install command, and `claude plugin list` verification step.
- All 13 Pest tests pass; full claude-plugins test suite (41 tests, 123 assertions) passes green. Lint clean.
