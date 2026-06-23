---
title: Claude Code
description: Set up Marko's AI tooling with Anthropic Claude Code — CLAUDE.md guidelines, Claude Code plugins, MCP tools, and LSP completions.
---

[Claude Code](https://claude.ai/code) is Anthropic's official CLI for Claude. `devai:install` gives it full Marko awareness by writing `.claude/settings.json` with the Marko plugin marketplace registration and enabling three Claude Code plugins: `marko-skills`, `marko-lsp`, and `marko-mcp`.

## Prerequisites

- **Claude Code installed.** The recommended method is the native installer (no Node.js required, auto-updates in the background):

  ```bash
  curl -fsSL https://claude.ai/install.sh | bash
  ```

  This covers macOS, Linux, and WSL; see the [Claude Code setup docs](https://docs.claude.com/en/docs/claude-code/setup) for Windows and other options. The legacy `npm install -g @anthropic-ai/claude-code` still works, but the native installer is now the preferred path.
- **Authenticated.** Run `claude` in your terminal and complete the browser sign-in on first launch (there is no separate `auth login` command — use `/login` inside a session to switch or manage credentials). Run `claude doctor` to confirm the installation type.
- `marko/devai` installed (see [Installation](../installation/))

## What devai:install writes

Running `marko devai:install` with Claude Code detected produces the following files:

```
AGENTS.md                          # Merged Marko guidelines (shared across agents)
CLAUDE.md                          # Includes @AGENTS.md and Claude-specific notes
.claude/settings.json              # Marketplace registration + enabled plugins
```

### AGENTS.md and CLAUDE.md

The installer writes merged Marko guidelines to `AGENTS.md`. The `CLAUDE.md` file references it via `@AGENTS.md` and adds a short Marko tooling section that describes the three plugins and the skill authority directive (skills are canonical spec — do not infer from sibling code).

Both files are written inside a `<!-- BEGIN/END marko:devai -->` marker block: each is created if absent, and on later runs only the marked region is refreshed — so anything you add outside the markers (your own project instructions in `CLAUDE.md`, extra guidelines in `AGENTS.md`) is preserved. Remove the markers to take full ownership and devai leaves the file alone. See [Editing generated files](../#editing-generated-files). This is separate from the `.marko/devai.json` install marker described below, which only tracks whether devai has run.

### .claude/settings.json

`devai:install` writes (or merges into) `.claude/settings.json` with two keys:

- `extraKnownMarketplaces.marko` — registers the Marko plugin marketplace so Claude Code can fetch plugin metadata.

  This is a named entry under Claude Code's `extraKnownMarketplaces` map. Its `source` tells Claude Code where to read the marketplace manifest (`.claude-plugin/marketplace.json`) from: when run inside the Marko monorepo the source is the local `packages/claude-plugins/plugins` path, and for external projects it is the `marko-php/marko` GitHub repo. Registering the marketplace does not install anything on its own — it only makes the `@marko` plugins discoverable.

- `enabledPlugins` — activates `marko-skills@marko`, `marko-lsp@marko`, and `marko-mcp@marko`.

  Each entry is a `plugin@marketplace` key set to `true`. Listing a plugin here tells Claude Code to install it from the registered marketplace and keep it enabled. On first trust of the project folder, Claude Code reads these entries and fetches the three plugins automatically — wiring the MCP server, the LSP server, and the scaffolding skills in a single step. `devai:install` only ever adds or replaces the `*@marko` entries, so any other plugins you have enabled are preserved.

### Plugins

The three Claude Code plugins ship from `packages/claude-plugins/plugins/` (or `vendor/marko-php/marko/packages/claude-plugins/plugins/` in external projects):

| Plugin | What it provides |
|---|---|
| `marko-skills@marko` | Scaffolding skills invokable as `/marko-skills:create-module`, `/marko-skills:create-plugin`, etc. |
| `marko-lsp@marko` | Marko-aware language server — real-time diagnostics, completions, and hover in PHP files |
| `marko-mcp@marko` | MCP server exposing codebase introspection tools (`search_docs`, `find_event_observers`, `validate_module`, etc.) |

### Skills

Skills live under `marko-skills/skills/{skill-name}/SKILL.md` inside the plugin. Each skill has a `description` frontmatter field describing when to use it and a slash command for explicit invocation:

- `/marko-skills:create-module` — scaffold a new Marko module
- `/marko-skills:create-plugin` — scaffold a new Marko plugin

Skills also load automatically when Claude judges the user's request matches the skill's `description` frontmatter. The skill file is the canonical specification — do not infer module/plugin structure from sibling code in the project.

### Legacy artifact cleanup

If a previous install left a `.claude/plugins/marko/.lsp.json` file or a `claude mcp add` registration named `marko-mcp`, `devai:install` removes them automatically before writing the new settings.

## Idempotency

`devai:install` records a marker at `.marko/devai.json` on first run. Subsequent runs without `--force` exit early with a message. Pass `--force` to re-run and overwrite the Marko plugin entries:

```bash
marko devai:install --force
```

## Manual verification

1. Open Claude Code in your project root.
2. Confirm `.claude/settings.json` contains `extraKnownMarketplaces.marko` and `enabledPlugins` with all three `@marko` plugins.
3. Run `/mcp` in Claude Code — the `marko-mcp` server should appear in the list.
4. Ask Claude: `What MCP tools are available from marko-mcp?` — it should enumerate tools like `search_docs`, `find_event_observers`, `validate_module`, `read_log_entries`.
5. Ask: `Search the Marko docs for routing.` — Claude should call `search_docs` and return results.
6. Invoke a skill explicitly: `/marko-skills:create-module` — Claude should load the skill and follow the contained workflow.
7. Open a PHP file and type `config('` — the `marko-lsp` plugin should surface Marko config key completions.

## Agent-specific tips

- **Context windows**: Claude Code reads `CLAUDE.md` on every session start. Keep it lean — substantive guidance belongs in `AGENTS.md` (project-wide) or skill files (task-specific, loaded on demand).
- **Skill authority**: When a Marko skill loads, it is the canonical specification. Do not inspect sibling modules to infer layout — siblings may have drifted. Use the skill's bundled templates verbatim.
- **LSP verification gate**: After writing or editing files, `marko-lsp` surfaces diagnostics in the same turn. Resolve all diagnostics before declaring a task complete.
- **Re-running install**: Pass `--force` to pick up any updated plugin entries after upgrading `marko/devai`.

## Package READMEs

- [`marko/devai`](https://github.com/markshust/marko/tree/develop/packages/devai)
- [`marko/mcp`](https://github.com/markshust/marko/tree/develop/packages/mcp)
- [`marko/lsp`](https://github.com/markshust/marko/tree/develop/packages/lsp)
