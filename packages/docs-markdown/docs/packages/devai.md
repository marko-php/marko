---
title: marko/devai
description: One-command installer that wires Marko's MCP and LSP tooling into your AI coding agents.
---

`marko/devai` is the integrator for Marko's AI development tooling. A single `marko devai:install` writes per-agent guidelines, registers the [`marko/mcp`](/docs/packages/mcp/) server and [`marko/lsp`](/docs/packages/lsp/) language server, distributes Marko skills, and builds the docs search index — for whichever of the six supported agents you use: Claude Code, OpenAI Codex, Cursor, GitHub Copilot, Gemini CLI, and JetBrains Junie.

It is the one point at which "AI tooling is available in this project" becomes true. For the conceptual guide and per-agent walkthroughs, see [AI-assisted Development](/docs/ai-assisted-development/).

## Installation

```bash
composer require --dev marko/devai
```

To enable docs search (`search_docs`), a docs driver is required — `marko/docs-fts` is the recommended default. When you run `devai:install` in an interactive terminal and no driver is found, it offers to install one for you:

```
No docs search driver installed. Install marko/docs-fts to enable search_docs? [Y/n]
```

Answering yes runs `composer require --dev marko/docs-fts` and builds the index. Without a terminal (CI) or when `--no-interaction` is passed, the prompt is skipped; the question goes through core's [`ConfirmationPrompterInterface`](/docs/packages/core/#asking-for-confirmation). To install manually:

```bash
composer require --dev marko/docs-fts
```

## Usage

```bash
# Detects installed agent CLIs and installs for them
marko devai:install

# Or target specific agents explicitly
marko devai:install --agents=claude-code,cursor

# Re-run after adding packages (reuses the prior agent selection)
marko devai:update
```

`devai:install` writes the marker file `.marko/devai.json` recording the selected agents and shipped skills. Re-running without `--force` is a no-op once a project is installed; use `marko devai:update` to refresh, or `--force` to re-run from scratch.

## Commands

| Command | Purpose |
|---------|---------|
| `devai:install` | Install AI tooling for the selected agents (guidelines, MCP/LSP registration, skills, docs index) |
| `devai:update` | Re-run the install using the previously recorded agent selection |

### Options for `devai:install`

| Option | Effect |
|--------|--------|
| `--agents=<a,b>` | Install for an explicit comma-separated agent list instead of auto-detecting |
| `--force` | Overwrite an existing install |
| `--update-gitignore` | Append devai's generated paths to `.gitignore` |
| `--skip-lsp-deps` | Skip installing the intelephense LSP dependency (Claude Code) |
| `--yes` | Install the pinned intelephense globally without asking first (Claude Code) |

### Global intelephense install

Claude Code's PHP language server needs [intelephense](https://intelephense.com). When it is not on `PATH`, `devai:install` installs one exact, pinned release (`IntelephenseEnsurer::VERSION`) with `npm install -g intelephense@<version>` — never whatever is latest. Because a global install changes your machine outside the project, an interactive run asks first; answering no skips it and prints the command to run later. `--yes` accepts without asking, and a non-interactive run (`--no-interaction`, CI, no TTY) installs without a prompt. `--skip-lsp-deps` skips it entirely.

## Configuration

devai reads `config/devai.php`. Override any key in your project's `config/devai.php`:

```php
<?php

declare(strict_types=1);

return [
    'claude_code' => [
        'marketplace_ref' => null,
    ],
    'guidelines' => [
        'allow_packages' => null,
    ],
];
```

| Key | Default | Effect |
|-----|---------|--------|
| `claude_code.marketplace_ref` | `null` | Git tag or commit the Claude Code plugin marketplace (`github: marko-php/marko`) is pinned to. `null` uses the installed `marko/devai` release tag, falling back to a known-good tag (`ClaudeCodeAgent::DEFAULT_MARKETPLACE_REF`) on dev installs. `devai:install` and Claude Code install the plugins from this ref on every developer's machine, so never point this at a branch. |
| `guidelines.allow_packages` | `null` | Which third-party (non-`marko/*`) packages may contribute `resources/ai/guidelines.md` to the generated guidelines. `null` includes every installed package; a list such as `['acme/blog']` includes only those; `[]` includes none. `marko/*` packages are always included. |

An invalid value (an empty ref, or an allowlist that is not a list of package names) fails the install with an error naming the key.

### Third-party guidelines

Any installed module can ship `resources/ai/guidelines.md`, and devai copies it into `AGENTS.md`/`CLAUDE.md` so your agent reads it. Guidelines from packages outside `marko/*` are written under a `### Third-party guidelines: vendor/package` header with a note that they come from that package, not from Marko, and end with a matching closing line — so you (and the agent) can see exactly which instructions a dependency added. Review them as you would the package's code, and use `guidelines.allow_packages` to limit which packages may contribute.

## How agents are installed

Each supported agent implements a single `AgentInterface::install()` method. The orchestrator renders the shared install data once (guidelines, skills, MCP registration) and hands it to every selected agent through an `InstallationContext` — so adding a new agent means implementing one method, with no capability-flag plumbing.

## Override model

Every guideline file devai writes --- `AGENTS.md`, `CLAUDE.md`, `GEMINI.md`, `.github/copilot-instructions.md`, `junie/guidelines.md`, `.cursor/rules/marko.mdc` --- contains a single managed region delimited by HTML comments:

```
<!-- BEGIN marko:devai -->
...generated content...
<!-- END marko:devai -->
```

On `devai:update`, devai rewrites **only** the content between these markers. Everything outside the markers belongs to you and is never modified.

### Taking full ownership of a file

Remove the markers from any file to make devai stop managing that file entirely. On the next `devai:update`, devai detects the absent `<!-- BEGIN marko:devai -->` / `<!-- END marko:devai -->` pair, backs off, and surfaces a loud notice in the install log reminding you to either restore the markers or edit the file manually. This is the escape hatch for teams that want full ownership of a specific agent config.

### Depth comes from the MCP server

devai ships no static reference docs. Depth in the generated guidelines comes from the `search_docs` tool provided by the [`marko/mcp`](/docs/packages/mcp/) server, which is registered during install. Agents use `search_docs` at request time to retrieve accurate, up-to-date documentation from your installed packages.

## Dependencies

`marko/devai` requires:

- **[`marko/mcp`](/docs/packages/mcp/)** — the MCP server it registers with each agent, so agents can introspect the codebase (`search_docs`, `validate_module`, `find_event_observers`, …).
- **[`marko/lsp`](/docs/packages/lsp/)** — the language server it wires up for real-time, Marko-aware editor diagnostics.
- **[`marko/docs`](/docs/packages/docs/)** — the docs-search *contract*. devai depends on the interface, not a specific driver; install [`marko/docs-fts`](/docs/packages/docs-fts/) (SQLite FTS5 lexical search) to bind it and enable `search_docs`.
- **`marko/claude-plugins`** — the skills/plugins marketplace devai distributes into each agent (e.g. `/marko-skills:create-module`).

`marko/config` supplies the [configuration](#configuration) above. `marko/cli` and `marko/core` are pulled in transitively as the command/runtime foundation.

## Related Packages

- [`marko/mcp`](/docs/packages/mcp/) — codebase introspection over MCP
- [`marko/lsp`](/docs/packages/lsp/) — Marko-aware language server
- [`marko/docs`](/docs/packages/docs/) — docs search contract
- [`marko/docs-fts`](/docs/packages/docs-fts/) — docs search driver
