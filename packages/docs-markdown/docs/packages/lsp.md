---
title: marko/lsp
description: Language Server Protocol implementation for Marko-aware editor completions, diagnostics, and navigation.
---

Language Server Protocol implementation for Marko — powers editor completions, diagnostics, go-to-definition, hover, and code lenses for Marko-specific semantics. `marko/lsp` runs as a stdio JSON-RPC server (`marko lsp:serve`) that any LSP-capable editor can connect to.

It builds on [`marko/codeindexer`](/docs/packages/codeindexer/): rather than re-parsing the project, the language server reads the cached symbol index, so completions and navigation reflect the same module graph the rest of the tooling sees. (Installing `marko/lsp` pulls in `marko/codeindexer` automatically.)

## Installation

```bash
composer require marko/lsp
```

## Usage

Start the server (editors launch this for you once configured):

```bash
marko lsp:serve
```

Configure your editor to launch it. Example for Neovim with `nvim-lspconfig`:

```lua
require('lspconfig').marko.setup({
  cmd = { 'marko', 'lsp:serve' },
})
```

## Capabilities

The server advertises the following on `initialize`:

| Capability | What it provides |
|------------|------------------|
| `completionProvider` | Completion for config keys, template names, translation keys, and Marko attribute parameters |
| `definitionProvider` | Go-to-definition for config keys, templates, and translations |
| `diagnosticProvider` | Diagnostics for unknown config keys, unresolved templates, and missing translations |
| `codeLensProvider` | Inverse-index code lenses (e.g. "who observes this event", "what plugins target this class") |
| `hoverProvider` | Hover documentation for Marko semantics |

Feature coverage is implemented by focused feature classes — config keys, templates, translations, attributes, and code lenses — each wired into the protocol dispatch.

## Related Packages

- [`marko/codeindexer`](/docs/packages/codeindexer/) — the cached index the language server reads
- [`marko/mcp`](/docs/packages/mcp/) — the MCP server peer that exposes the same index to AI agents
