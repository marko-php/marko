---
title: Verification Checklist
description: Manual smoke test to confirm your Marko AI tooling setup works end-to-end with at least one agent.
---

Work through this checklist after running `marko devai:install` to confirm the full integration is working. Each section is independent — if one step fails, consult [Troubleshooting](./troubleshooting/) before continuing.

## 1. Package installation

- [ ] `composer show marko/devai` returns a version without error
- [ ] `./vendor/bin/marko --version` prints a version string
- [ ] `marko list` includes `devai:install`, `mcp:serve`, and `lsp:serve` in the output

## 2. devai:install output

Run `marko devai:install` and verify:

- [ ] The installer reports at least one agent detected
- [ ] No error messages are printed (warnings are acceptable)
- [ ] The installer reports writing at least one agent guidelines file
- [ ] Re-running `marko devai:install` a second time completes without error (idempotent)

## 3. Agent guidelines file

Choose one agent and verify its guidelines file:

**Claude Code:**
- [ ] `CLAUDE.md` exists in the project root
- [ ] `CLAUDE.md` contains a `## Marko` section
- [ ] The section lists at least one MCP tool (e.g., `search_docs`)

**Codex:**
- [ ] `AGENTS.md` exists in the project root
- [ ] `.agents/skills/` contains skill files

**Cursor:**
- [ ] `.cursor/rules/marko.mdc` exists and contains `alwaysApply: true`
- [ ] `.cursor/mcp.json` exists and uses the `mcpServers` key

**Copilot:**
- [ ] `.github/copilot-instructions.md` exists
- [ ] `.vscode/mcp.json` exists and uses the `servers` key

**Gemini CLI:**
- [ ] `GEMINI.md` exists in the project root
- [ ] `.gemini/skills/` contains skill files

**Junie:**
- [ ] `junie/guidelines.md` exists (under `junie/`, not `.junie/`)
- [ ] `junie/skills/` contains skill files

## 4. MCP server

- [ ] `marko mcp:serve` starts without error (blocks waiting for stdin — Ctrl+C to exit)
- [ ] The agent's MCP configuration file exists and references `marko mcp:serve`

Invoke the MCP server from your chosen agent:

- [ ] Ask the agent: "What MCP tools are available?" — it should list at least `find_event_observers`, `validate_module`, `list_modules`, `list_routes`, `app_info`, `last_error`, `read_log_entries`, and `run_console_command`
- [ ] `search_docs` appears in the list only if a docs driver (`marko/docs-fts` or `marko/docs-vec`) is installed
- [ ] `query_database` appears in the list only if `marko/database` is installed
- [ ] Ask the agent: "Search Marko docs for routing" — `search_docs` should return at least one result (requires a docs driver)

## 5. Codeindex

- [ ] `marko codeindexer:index` completes without error
- [ ] After indexing, `marko mcp:serve` with `search_docs` returns results for "routing"
- [ ] `find_event_observers` returns results when given a known event name (try `marko.request.received`)

## 6. LSP server

- [ ] `marko lsp:serve` starts without error (blocks waiting for stdin — Ctrl+C to exit)
- [ ] The editor's LSP configuration references `marko lsp:serve`
- [ ] Open a PHP file and type `config('` — Marko config key completions appear (`textDocument/completion`)
- [ ] Hover over a quoted config key string — hover documentation appears (`textDocument/hover`)
- [ ] Go-to-definition on a quoted config key navigates to the definition (`textDocument/definition`)

## 7. End-to-end agent task

Perform one real agentic task to confirm the full integration:

1. Open your chosen agent in the project root
2. Ask: **"Create a new Marko module called `Greet` with a single GET route at `/greet` that returns 'Hello, world!'"**
3. Verify the agent:
   - [ ] Creates `app/Greet/` with the correct module structure
   - [ ] Uses PHP attribute routing (`#[Get('/greet')]`)
   - [ ] Does not use any patterns that violate Marko conventions (the `validate_module` tool may run automatically)
4. Run `marko validate_module Greet` manually — it should pass with no errors

## 8. Skills (if applicable)

If any installed packages ship skills under `resources/ai/skills/`:

- [ ] `marko devai:skills:list` lists at least one skill
- [ ] Asking the agent to perform a skill-specific task triggers the correct skill workflow

## All checks pass?

Your AI-assisted development setup is complete. Return to [AI-assisted Development overview](./index/) to explore what else you can do.

If any check failed, see [Troubleshooting](./troubleshooting/) for targeted fixes.
