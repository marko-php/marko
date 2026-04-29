---
title: Troubleshooting
description: Fix common install failures, ONNX download issues, and agent registration problems for Marko's AI tooling.
---

This page covers the most common issues encountered when setting up `marko/devai`, `marko/mcp`, and `marko/lsp`.

## Installation failures

### "marko command not found" after composer require

The `marko` binary is published to `vendor/bin/`. Make sure your `PATH` includes `vendor/bin/`:

```bash
export PATH="vendor/bin:$PATH"
```

Or call the binary with its full path:

```bash
./vendor/bin/marko devai:install
```

### devai:install exits with "No agents detected"

The installer detects agents by looking for known configuration files and binaries. If no agents are found:

1. Confirm the agent is installed and accessible on your `PATH` (e.g., run `claude --version`)
2. If the agent uses a non-standard config location, use the `--agent` flag to force detection:

```bash
marko devai:install --agent=claude-code
```

Supported agent identifiers: `claude-code`, `codex`, `cursor`, `copilot`, `gemini-cli`, `junie`.

### Permission denied writing agent files

If the installer cannot write `CLAUDE.md` or other files, check directory permissions:

```bash
ls -la . | head -5
```

If running inside a container or mounted volume, ensure the working directory is writable by the PHP process.

## ONNX download issues (docs-vec driver)

### Download hangs or times out

The ONNX embedding model is approximately 80 MB and is downloaded on first use when the `docs-vec` driver is active. If the download hangs:

1. Check your network connection and proxy settings
2. Set a longer timeout in `config/devai.php`:

```php
'onnx_download_timeout' => 120, // seconds
```

3. Download the model manually and place it in the expected path:

```bash
# Find the expected path
marko devai:onnx-path

# Download manually (example — use the URL from the output above)
curl -L https://example.com/model.onnx -o /path/shown/by/command
```

### "FFI not enabled" error with docs-vec

The `docs-vec` driver requires PHP's FFI extension. Check if it is enabled:

```bash
php -m | grep ffi
```

If FFI is missing, either enable it in `php.ini`:

```ini
extension=ffi
ffi.enable=true
```

Or switch to the `docs-fts` driver, which has no FFI requirement:

```bash
MARKO_DOCS_DRIVER=docs-fts
```

See [Docs driver comparison](./docs-drivers/) for a full feature comparison.

### "ONNX model checksum mismatch"

If the downloaded model file is corrupted:

```bash
# Delete the cached model and re-download
marko devai:onnx-clear
marko codeindexer:index --driver=docs-vec
```

## MCP server problems

### Agent reports "MCP server failed to start"

1. Confirm `marko mcp:serve` runs without error:

```bash
marko mcp:serve
# Should block waiting for stdin — press Ctrl+C to exit
```

2. Check the agent's MCP configuration file references the correct command:

```json
{
  "command": "marko",
  "args": ["mcp:serve"]
}
```

3. Ensure the `marko` binary is on the `PATH` the agent uses. Some agents (e.g., Claude Code) use a restricted environment. Use the full binary path if needed:

```json
{
  "command": "/path/to/your/project/vendor/bin/marko",
  "args": ["mcp:serve"]
}
```

### "Tool not found" when calling search_docs

The `search_docs` tool is only registered when a `DocsSearchInterface` binding is present. This requires installing a docs driver package such as `marko/docs-fts` or `marko/docs-vec`. If neither is installed, the tool will not appear in the MCP tool list regardless of the index state.

If the tool is listed but returns no results, the index may be stale. Trigger a rebuild:

```bash
marko indexer:rebuild
```

The `IndexCache` also rebuilds automatically on next read if any tracked source file is newer than the cache.

### "Tool not found" when calling query_database

The `query_database` tool is only registered when `marko/database` is bound in the container. Install the database package and ensure it is configured before expecting this tool to appear.

## LSP problems

### No completions appearing in the editor

1. Confirm `marko lsp:serve` runs:

```bash
marko lsp:serve
# Should block waiting for stdin — press Ctrl+C
```

2. Check the editor's LSP configuration points to `marko lsp:serve`.

3. In VS Code: open the Output panel, select "Marko Language Server" from the dropdown — connection errors appear here.

4. In PhpStorm: check **Settings > Languages & Frameworks > Language Servers** for connection status.

### Completions appear but are stale or incorrect

Completions are sourced from the codeindex. Rebuild it after making structural changes to your project:

```bash
marko codeindexer:index
```

## Agent registration problems

### CLAUDE.md / AGENTS.md not updated after re-running devai:install

By default, the installer skips files that already exist and contain a `## Marko` section to avoid duplicating content. To force a refresh:

```bash
marko devai:install --force
```

This regenerates the Marko section in every agent guidelines file based on the current set of installed packages.

### Guidelines from a newly installed package are not appearing

After adding a new package, re-run the installer:

```bash
composer require my-vendor/my-package
marko devai:install
```

The installer reads `resources/ai/guidelines.md` from every package in `vendor/` each time it runs.

## Getting more help

- [Verification checklist](./verification-checklist/) — step-by-step smoke test to isolate where the problem is
- [Architecture](./architecture/) — understand how the components connect
- [GitHub Issues](https://github.com/markshust/marko/issues) — search for known issues or file a new one
