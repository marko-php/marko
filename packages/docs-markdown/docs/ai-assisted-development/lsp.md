---
title: LSP Reference
description: Complete reference for marko/lsp — wired LSP methods, advertised capabilities, per-feature behavior, and editor setup.
---

`marko/lsp` is a Language Server Protocol implementation that gives any LSP-capable editor (or AI agent) Marko-specific code intel: completions, go-to-definition, hover, diagnostics, and code lenses for config keys, templates, translations, and plugin/observer attributes.

Start it with:

```bash
marko lsp:serve
```

The server speaks JSON-RPC over stdio. For Claude Code, the `marko-lsp` plugin (shipped via the `marko` marketplace at `packages/claude-plugins/plugins/marko-lsp/`) registers the server through its `.lsp.json` and a POSIX shim at `bin/marko-lsp` that locates `vendor/bin/marko` and execs `marko lsp:serve`. `marko/devai` writes the marketplace registration into `.claude/settings.json` (`extraKnownMarketplaces.marko` + `enabledPlugins`), and Claude Code installs the plugin on first folder-trust prompt.

## Wired LSP methods

| Method | Purpose |
|---|---|
| `initialize` | Lifecycle handshake. Returns capabilities (see below) |
| `initialized` | Lifecycle notification — no response |
| `shutdown` | Graceful shutdown request |
| `textDocument/didOpen` | Track an opened document in the in-memory store |
| `textDocument/didChange` | Update the tracked document |
| `textDocument/didClose` | Drop the document |
| `textDocument/completion` | Symbol completion at a cursor position |
| `textDocument/definition` | Go-to-definition for a quoted string under the cursor |
| `textDocument/hover` | Hover documentation for a quoted string under the cursor |
| `textDocument/diagnostic` | Full-document diagnostics for the active file |
| `textDocument/codeLens` | Code lenses for plugin/observer attributes in a PHP file |

Anything outside this set is unimplemented and will return a JSON-RPC method-not-found error.

## Advertised capabilities

The server's `initialize` response declares:

```json
{
  "textDocumentSync": 1,
  "completionProvider": {
    "triggerCharacters": ["\"", "'", ":", "."],
    "resolveProvider": false
  },
  "definitionProvider": true,
  "hoverProvider": true,
  "codeLensProvider": { "resolveProvider": false },
  "diagnosticProvider": {
    "interFileDependencies": false,
    "workspaceDiagnostics": false
  }
}
```

`textDocumentSync: 1` means full-document sync (the editor sends the entire document on each change, no incremental ranges).

## Features

Five feature classes route the LSP methods. For `definition` and `hover`, the server tries each in order and returns the first non-null result (first-feature-wins). For `completion` and `diagnostic`, all features contribute and results merge.

### ConfigKeyFeature

Indexes every `config()` key declared across installed packages (read from the codeindexer's `IndexCache`).

| LSP feature | Behavior |
|---|---|
| Completion | Inside `config('` or `config("`, suggest known config keys with their type and default |
| Definition | Jump to the source location where the config key was declared |
| Hover | Markdown popup with the key's type, default value, and source location |
| Diagnostic | Flag references to config keys that don't exist in the index |

### TemplateFeature

Indexes every template in `resources/views/` across installed packages.

| LSP feature | Behavior |
|---|---|
| Completion | Inside `view('` or `view("`, suggest known template names using `module::path/to/template` syntax |
| Definition | Jump to the resolved template file |
| Hover | Markdown popup with the resolved file path |
| Diagnostic | Flag template references that resolve to nothing |

### TranslationFeature

Indexes every translation key from `resources/translations/` across installed packages.

| LSP feature | Behavior |
|---|---|
| Completion | Inside `__('` or `__("`, suggest known translation keys |
| Definition | Jump to the translation file containing the key |
| Hover | Markdown popup with the translated value(s) |
| Diagnostic | Flag references to translation keys that don't exist |

### AttributeFeature

Targets Marko's PHP attributes (`#[Plugin]`, `#[Before]`, `#[After]`, `#[Observer]`, `#[Preference]`, `#[Command]`).

| LSP feature | Behavior |
|---|---|
| Completion | Suggest attribute parameter names (e.g. `target:`, `event:`, `sortOrder:`) inside an attribute argument list |
| Diagnostic | Flag malformed attribute usage (missing required parameters, wrong target, etc.) |

### CodeLensFeature

Adds inline actions above plugin and observer classes.

| LSP feature | Behavior |
|---|---|
| Code lens | Show "→ N targets" above `#[Plugin]` classes; show "← Observed by N" above `#[Observer]` classes — clicking jumps to the related code |

## How the LSP reads the index

Every feature reads from the `IndexCache` provided by `marko/codeindexer`. The cache loads lazily on first access and rebuilds automatically when source files change. No editor restart is needed when you add new config keys, templates, translations, or plugin/observer classes — the next LSP query rebuilds and returns fresh data.

## Editor setup

`marko/devai` writes the registration file appropriate for each agent that supports LSP. For other editors, point your LSP client at `marko lsp:serve` over stdio.

**Neovim (with `nvim-lspconfig`)**:

```lua
require('lspconfig.configs').marko_lsp = {
  default_config = {
    cmd = { 'marko', 'lsp:serve' },
    filetypes = { 'php' },
    root_dir = require('lspconfig.util').root_pattern('composer.json'),
  },
}
require('lspconfig').marko_lsp.setup{}
```

**VS Code**: any generic LSP client extension pointed at `marko lsp:serve`. The `vscode-marko` extension (if installed) handles this automatically.

## Verification

After starting the server, send an `initialize` request over stdio to confirm capabilities:

```bash
echo '{"jsonrpc":"2.0","method":"initialize","params":{},"id":1}' | marko lsp:serve
```

You should see a JSON response with the capabilities object listed above.

## Package READMEs

- [`marko/lsp`](https://github.com/markshust/marko/tree/develop/packages/lsp)
- [`marko/codeindexer`](https://github.com/markshust/marko/tree/develop/packages/codeindexer) — provides the index every LSP feature reads from
