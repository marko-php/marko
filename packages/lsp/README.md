# marko/lsp

Language Server Protocol implementation for Marko — powers editor completions, diagnostics, go-to-definition, and hover for Marko-specific semantics.

## Installation

```bash
composer require marko/lsp
```

## Quick Example

```bash
marko lsp:serve
```

Configure your editor (example for Neovim with `nvim-lspconfig`):

```lua
require('lspconfig').marko.setup({
  cmd = { 'marko', 'lsp:serve' },
})
```

## Documentation

Supported `textDocument/*` methods, feature coverage, and editor setup: [marko/lsp](https://marko.build/docs/ai-assisted-development/lsp/)
