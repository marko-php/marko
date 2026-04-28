# marko/lsp

Language Server Protocol implementation for Marko — powers editor completions, diagnostics, and navigation specific to Marko semantics.

## Overview

`marko/lsp` implements LSP over stdio, connecting your editor to Marko's module system. It understands Marko-specific concepts that generic PHP language servers miss: config key completion, template resolution across module overrides, attribute-based registration, and translation key lookup. Works with any LSP-compatible editor (VS Code, Neovim, Zed, etc.).

## Installation

```bash
composer require marko/lsp
```

## Usage

Start the LSP server (stdio transport):

```bash
marko lsp:serve
```

Configure your editor to use this command as the PHP language server for Marko projects. Example for Neovim (`nvim-lspconfig`):

```lua
require('lspconfig').marko.setup({
  cmd = { 'marko', 'lsp:serve' },
})
```

## Features

| Feature | Description |
|---------|-------------|
| **Config key completion** | Autocomplete `config('module.key')` with valid keys |
| **Template resolution** | Go-to-definition for template handles, respects module overrides |
| **Attribute diagnostics** | Validate `#[Observer]`, `#[Plugin]`, `#[Preference]` attribute arguments |
| **Code lens** | Show observer count, plugin count on classes |
| **Translation keys** | Autocomplete and validate `__('module::key')` calls |

## Documentation

Full editor setup and configuration: [marko/lsp](https://marko.build/docs/ai-assisted-development/lsp/)
