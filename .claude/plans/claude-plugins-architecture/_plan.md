# Plan: Claude Plugins Architecture

## Created
2026-04-29

## Status
completed

## Objective
Refactor the AI-assisted dev workflow on `feature/ai-dev-tooling` (PR #41) so Claude Code generates predictable, spec-driven code through the official Claude Code plugin system. Replaces the current ad-hoc skill+lsp+mcp registration (LSP is empirically broken — proven via `claude plugin list` showing no `marko-lsp`) with three Claude Code plugins distributed via a project-shipped marketplace.

## Related Issues
Relates to PR #41 (feature/ai-dev-tooling)

## Scope

### In Scope
- New monorepo package `packages/claude-plugins/` housing a Claude Code marketplace + three plugins:
  - `marko-skills` — scaffolding skills with bundled `assets/` templates and `references/` deep docs
  - `marko-lsp` — thin manifest plugin around `marko lsp:serve` (existing `packages/lsp/`)
  - `marko-mcp` — thin manifest plugin around `marko mcp:serve` (existing `packages/mcp/`)
- Refactor `packages/devai/src/Agents/ClaudeCodeAgent.php`: remove broken `.lsp.json` writer, remove direct `claude mcp add` calls, add `.claude/settings.json` writer (`extraKnownMarketplaces` + `enabledPlugins`), upgrade CLAUDE.md content with authority directives ("skill is the spec, don't infer from siblings", "LSP diagnostics are the verification gate")
- Restructure existing skill content (currently in `packages/devai/resources/ai/skills/`): move templates from inline markdown to bundled `assets/*.tmpl` files; add anti-pattern directives; tighten descriptions for higher trigger reliability; keep within Anthropic's 500-line SKILL.md cap
- Update non-Claude-Code agents (Codex, Cursor, Copilot, Gemini, Junie) to source skill content from the new canonical location (the `marko-skills` plugin's `skills/` directory)
- Tests asserting correct file shapes (settings.json, plugin.json, .lsp.json, .mcp.json) match the official Anthropic schemas
- Integration test that runs `devai:install --agents=claude-code` in a tempdir and validates output

### Out of Scope
- Migrating non-Claude-Code agents to plugin-style distribution (those agents have no plugin system)
- Submitting the marketplace to Anthropic's official catalog
- Rewriting `marko/lsp` or `marko/mcp` PHP package internals
- Implementing additional skills beyond `create-module` and `create-plugin`

## Success Criteria
- [ ] `packages/claude-plugins/` exists with marketplace.json + three plugin subdirectories, each with valid `.claude-plugin/plugin.json`
- [ ] `marko-mcp` plugin's `.mcp.json` matches the schema documented at code.claude.com/docs/en/plugins-reference
- [ ] `marko-lsp` plugin's `.lsp.json` uses the official shape: `{"<name>": {"command", "args", "extensionToLanguage": {".php": "php"}}}`
- [ ] `marko-skills` plugin contains both `create-module` and `create-plugin` skills with bundled `assets/` templates and anti-pattern directives in SKILL.md
- [ ] `ClaudeCodeAgent::registerLspServer` and `registerMcpServer` no longer write the broken format; `.claude/settings.json` is written instead with `extraKnownMarketplaces` and `enabledPlugins`
- [ ] CLAUDE.md content includes the authority directive (skill is canonical spec, don't infer from siblings, LSP diagnostics are verification gate)
- [ ] All non-Claude-Code agent installers source skill content from the new canonical location — no duplication
- [ ] `composer test` passes for all touched packages
- [ ] `architecture.md` Package Inventory section lists `marko/claude-plugins`
- [ ] `packages/claude-plugins/README.md` follows project Package README Standards

### Manual verification (post-orchestration, run by user)
- [ ] On `~/Sites/my-app-local` after cleanse + `marko devai:install --agents=claude-code`, `claude plugin list` shows `marko-skills@marko`, `marko-lsp@marko`, `marko-mcp@marko`
- [ ] `claude mcp list` shows `marko-mcp` connected
- [ ] LSP diagnostics from `marko-lsp` appear after editing a `.php` file (alongside intelephense from `php-lsp`)

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Research open implementation questions → findings doc | - | completed |
| 002 | Create `packages/claude-plugins/` package skeleton + marketplace.json | 001 | completed |
| 003 | Implement `marko-mcp` plugin (manifest + .mcp.json) | 001, 002 | completed |
| 004 | Implement `marko-lsp` plugin (manifest + .lsp.json) | 001, 002 | completed |
| 005 | Implement `marko-skills` plugin scaffold (manifest only) | 001, 002 | completed |
| 006 | Restructure `create-module` skill into marko-skills with bundled assets + directives | 005 | completed |
| 007 | Restructure `create-plugin` skill into marko-skills with bundled assets + directives | 005 | completed |
| 008 | Refactor `ClaudeCodeAgent`: settings.json writer + CLAUDE.md authority directives + delete legacy registrations | 001, 002 | completed |
| 009 | Update non-Claude-Code agent installers to source from canonical skill location | 006, 007 | completed |
| 010 | Integration test: `devai:install --agents=claude-code` end-to-end shape validation | 003, 004, 005, 008 | completed |
| 011 | `packages/claude-plugins/README.md` slim-pointer shell (doc-updater fills the rest post-orchestration) | 002 | completed |

## Architecture Notes

### Three-tier skill loading (Anthropic's published anatomy)
1. **Metadata** — `name` + `description` (~100 words), always in context
2. **SKILL.md body** — <500 lines ideal, in context when triggered
3. **Bundled resources** — `assets/` (templates), `references/` (deep docs), `scripts/` (deterministic generators); only loaded as referenced

### Anti-pattern directives in CLAUDE.md (verbatim language)
> Marko ships task-oriented skills via the `marko-skills` plugin. When a skill loads, it is the canonical spec — do not infer structure from sibling modules; siblings may have drifted from spec. Use bundled templates verbatim, substitute placeholders only. After writing files, expect LSP diagnostics from `marko-lsp`; resolve all diagnostics before declaring the task complete.

### `.claude/settings.json` shape (target output of refactored ClaudeCodeAgent)
```json
{
  "extraKnownMarketplaces": {
    "marko": { "source": { "source": "github", "repo": "<owner>/marko", "path": "packages/claude-plugins" } }
  },
  "enabledPlugins": {
    "marko-skills@marko": true,
    "marko-lsp@marko": true,
    "marko-mcp@marko": true
  }
}
```
(Exact `source` shape pending Task 001 findings on whether `extraKnownMarketplaces` accepts local paths and git-subdir refs.)

### `.lsp.json` schema (verified from code.claude.com/docs/en/plugins-reference)
```json
{
  "marko-lsp": {
    "command": "<binary>",
    "args": ["lsp:serve"],
    "extensionToLanguage": { ".php": "php", ".latte": "latte" }
  }
}
```
Required: `command`, `extensionToLanguage`. `extensionToLanguage` keys must include leading dot.

### Single source of truth for skill content
Canonical home: `packages/claude-plugins/plugins/marko-skills/skills/`. Other agents (Codex, Cursor, Copilot, Gemini, Junie) read/copy from this location during their respective installs. No duplication.

### `marko/claude-plugins` is a Composer package, not a Marko module
The package is Composer-distributed (so external projects can `composer require marko/claude-plugins` directly or transitively via marko/devai, and read its plugin assets from `vendor/marko/claude-plugins/`). But it ships only Claude Code plugin assets — no PHP runtime behavior — so it must NOT be discovered by Marko's module loader. The two concepts are orthogonal: every Marko module is a Composer package, but not every Composer package is a Marko module. The composer.json reflects this by omitting `extra.marko.module` and `module.php`.

### LSP command path resolution contract (REVISED per Task 001 finding F8)
Task 001's research established Claude Code does NOT support project-local LSP overrides — plugin-shipped `.lsp.json` is the only registration path. Therefore the plugin must self-contain its command resolution. The shipped `marko-lsp` and `marko-mcp` plugins each ship a small POSIX shell shim script at `bin/marko-lsp` (and `bin/marko-mcp`) that locates the project's `vendor/bin/marko` binary and execs it with the right subcommand. The `.lsp.json` / `.mcp.json` reference the shim via `${CLAUDE_PLUGIN_ROOT}` substitution (supported per finding F1):
```json
{ "marko-lsp": { "command": "${CLAUDE_PLUGIN_ROOT}/bin/marko-lsp", "args": [], "extensionToLanguage": { ".php": "php" } } }
```
The shim script:
1. Sets CWD to where `claude` was invoked (already the case)
2. Locates `marko` binary by searching: `vendor/bin/marko` (project-local Composer install) → `marko` on global PATH → error
3. Execs `marko <subcommand> "$@"` (subcommand is `lsp:serve` for marko-lsp, `mcp:serve` for marko-mcp)
4. Errors loudly if no `marko` binary found

This means **devai's `ClaudeCodeAgent` no longer performs LSP/MCP path substitution** — it only writes `extraKnownMarketplaces` + `enabledPlugins` into `.claude/settings.json`. The marketplace plugin is fully self-contained and works for any project that has `vendor/bin/marko` available (which is true after `composer require marko/devai`).

### Skill content portability contract (consumed by Tasks 006, 007, 009)
Frontmatter fields universal across agents: `name`, `description`. Claude-Code-specific fields (e.g., `assets`, `references`) are present in SKILL.md but ignored by non-Claude agents.
- Codex (AGENTS.md): non-Claude agents receive the SKILL.md body with `assets/` references inlined as fenced code blocks, concatenated into a single AGENTS.md section per skill.
- Cursor (`.cursor/rules/*.mdc`): one rule file per skill, frontmatter mapped to Cursor's format, body inlined.
- Copilot, Gemini, Junie: same inline-and-concatenate as Codex into their respective canonical files.
Task 009 implements the transformations; Tasks 006/007 must keep `assets/` references resolvable as relative paths from SKILL.md so the inliner can read them.

### Legacy artifact cleanup (consumed by Task 008)
On install, ClaudeCodeAgent must idempotently remove:
- Any project-level `.lsp.json` written by the previous (broken) code.
- Any MCP server registered via `claude mcp add` named `marko-mcp`.

## Risks & Mitigations

- **Risk**: `extraKnownMarketplaces` may not accept local paths within the same repo, requiring a github source. **Mitigation**: Task 001 verifies; if not supported, settings.json points at the github repo's `packages/claude-plugins/` subdir, which works for installed projects but requires a separate handling for the monorepo itself (likely `--plugin-dir` or skipping marketplace registration when running inside the monorepo source tree).

- **Risk**: Plugin-shipped LSPs may require absolute `command` paths, breaking shipping. **Mitigation**: Task 001 verifies; if absolute-only, the marketplace plugin ships a placeholder and `ClaudeCodeAgent` substitutes the absolute path at install time (writing into a project-local override, not the marketplace plugin itself).

- **Risk**: Skill content + anti-pattern directives + LSP gate may exceed 500-line SKILL.md cap. **Mitigation**: Task 001 line-counts the projected content; if over, decompose the runbook detail into `references/` files referenced from SKILL.md.

- **Risk**: Plugin namespacing (`/marko-skills:create-module`) is a UX change from current standalone `/marko-create-module`. **Mitigation**: Already accepted by user; documented in CLAUDE.md content updates so users know the new invocation.

- **Risk**: The official `php-lsp` plugin (intelephense) may conflict with `marko-lsp` since both register `.php`. **Mitigation**: LSP plugins in Claude Code can register multiple servers per extension (verified by inspecting Anthropic's marketplace.json — multiple LSPs share extensions); both deliver diagnostics simultaneously. Confirm during manual verification.
