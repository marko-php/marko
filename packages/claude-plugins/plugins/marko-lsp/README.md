# marko-lsp

Marko-aware PHP language server for Claude Code. Wraps [intelephense](https://intelephense.com/) with Marko-tuned initialization options that make Claude Code understand Marko's module system, service providers, and routing conventions.

## What marko-lsp adds beyond plain intelephense

- **Live diagnostics via `textDocument/publishDiagnostics`**: as you open or edit a PHP file, marko-lsp pushes diagnostics to the client immediately — no poll or manual request needed. Unknown config keys (`config('not.real.key')`) and unknown translation keys are flagged in real time with `source: marko-lsp`.
- Framework-aware completions: suggests correct namespaces and class names based on module structure
- Navigation tied to Marko semantics: go-to-definition follows Preferences (interface → concrete) rather than raw PHP class hierarchy

`marko devai:install` automatically installs the `intelephense` npm package (required by `php-lsp@claude-plugins-official`) if it is not already on `PATH`. Pass `--skip-lsp-deps` to opt out.

## Coexistence with php-lsp@claude-plugins-official

Claude Code loads all registered LSP servers simultaneously. If you have both `php-lsp@claude-plugins-official` (Anthropic's intelephense plugin) and `marko-lsp` installed, both run and both deliver diagnostics for every `.php` file.

**Recommendation**: uninstall `php-lsp@claude-plugins-official` after installing marko-lsp. Since marko-lsp wraps the same intelephense binary with Marko-tuned init options, keeping both active produces duplicate diagnostics. This is a UX preference — both plugins work correctly together at a technical level.

To uninstall the official PHP LSP plugin:

```
/plugin uninstall php-lsp@claude-plugins-official
```

## Installation

Install via the Marko marketplace:

```
/plugin install marko-lsp@marko
```

If the Marko marketplace is not yet registered, add it first:

```
/plugin marketplace add marko-php/marko
```

## Verify installation

```
claude plugin list
```

You should see `marko-lsp` in the output with status `enabled`.

## Requirements

- `marko/devai` installed in your project (`composer require marko/devai`)
- The `vendor/bin/marko` binary must be present, or `marko` must be on your `PATH`
