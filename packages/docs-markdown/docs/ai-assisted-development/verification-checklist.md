---
title: Verification Checklist
description: Manual smoke test to confirm your Marko AI tooling setup works end-to-end with at least one agent.
---

Work through this checklist after running `marko devai:install` to confirm the full integration is working. Each section is independent — if one step fails, consult [Troubleshooting](./troubleshooting/) before continuing.

## 1. Package installation

- [ ] `composer show marko/devai` returns a version without error
- [ ] `marko list` includes `devai:install`, `mcp:serve`, `lsp:serve`, and `indexer:rebuild` in the output

## 2. devai:install output

Run `marko devai:install` and verify:

- [ ] The installer reports at least one agent detected
- [ ] No error messages are printed (warnings are acceptable)
- [ ] The installer reports writing at least one agent guidelines file
- [ ] Re-running `marko devai:install` a second time completes without error (idempotent)

## 3. Agent guidelines file

Choose one agent and verify its guidelines file:

**Claude Code:**
- [ ] `CLAUDE.md` exists in the project root and references `@AGENTS.md`
- [ ] `AGENTS.md` exists in the project root and includes a `## Package Guidelines` section
- [ ] `claude mcp list` includes the `marko-mcp` server

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

## 5. Code index

The MCP/LSP servers lazy-load and auto-rebuild `.marko/index.cache` on first read,
so explicit indexing is optional. To pre-warm or to force a rebuild after large
codebase changes:

- [ ] `marko indexer:rebuild` completes without error and prints module/route/config-key counts
- [ ] After indexing, asking the agent "List all Marko modules" (which calls the `list_modules` MCP tool) returns the expected modules
- [ ] `find_event_observers` returns results when given a known event class FQN that has at least one `#[Observer]` in your codebase

## 6. LSP server

- [ ] `marko lsp:serve` starts without error (blocks waiting for stdin — Ctrl+C to exit)
- [ ] The editor's LSP configuration references `marko lsp:serve`
- [ ] In a PHP file, type a config getter and place the cursor inside the quotes (e.g., `$config->get("|")`) — Marko config key completions appear (`textDocument/completion`)
- [ ] Hover over a quoted config key string — markdown hover with type, default, and source location appears (`textDocument/hover`)
- [ ] Go-to-definition on a quoted config key navigates to its definition (`textDocument/definition`)

## 7. End-to-end agent task

Perform one real agentic task to confirm the full integration:

1. Open your chosen agent in the project root
2. Ask: **"Create a new Marko module called `Greet` with a single GET route at `/greet` that returns 'Hello, world!'"**
3. Verify the agent:
   - [ ] Creates `app/Greet/` with the correct module structure
   - [ ] Uses PHP attribute routing (`#[Get('/greet')]`)
   - [ ] Does not use any patterns that violate Marko conventions
4. Ask the agent to call the `validate_module` MCP tool against `app/Greet` — it should pass with no errors

## 8. Skills (if applicable)

The skill files devai distributes live under each agent's skills directory
(e.g., `.claude/skills/`, `.agents/skills/`, `.gemini/skills/`, `junie/skills/`):

- [ ] At least one `SKILL.md` file exists in the chosen agent's skills directory
- [ ] Asking the agent to perform a skill-specific task triggers the correct skill workflow

## All checks pass?

Your AI-assisted development setup is complete. Return to [AI-assisted Development overview](./index/) to explore what else you can do.

If any check failed, see [Troubleshooting](./troubleshooting/) for targeted fixes.
