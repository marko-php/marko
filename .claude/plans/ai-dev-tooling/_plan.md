# Plan: AI-Assisted Development Tooling

## Created
2026-04-24

## Status
in_progress

## Objective
Ship Marko's AI-first development workflow for 1.0 launch: eight new packages (codeindexer, mcp, lsp, devai, docs, docs-markdown, docs-fts, docs-vec) plus two naming-convention renames (rate-limiting → ratelimiter, dev-server → devserver). Provides an MCP server, LSP server, and multi-agent installer so any Marko project can be first-class-citizen ready for AI-generated code across Claude Code, Codex, Cursor, Copilot, Gemini CLI, and Junie.

## Related Issues
none

## Scope

### In Scope

**Renames (establish naming convention):**
- `marko/rate-limiting` → `marko/ratelimiter`
- `marko/dev-server` → `marko/devserver`

**Eight new packages (all pure PHP 8.5+):**

1. **`marko/codeindexer`** — shared static analysis library. Walks `composer.json`/`module.php` files, parses Marko attributes (`#[Observer]`, `#[Plugin]`, `#[Before]`, `#[After]`, `#[Preference]`, `#[Command]`, route attributes), scans config/translation/view dirs, builds symbol table, caches to `.marko/index.cache`. No dependency on devai.

2. **`marko/mcp`** — MCP server. Pure PHP, stdio JSON-RPC. MCP tools:
   - Static (indexer-backed): `search_docs`, `list_modules`, `resolve_preference`, `find_event_observers`, `find_plugins_targeting`, `resolve_template`, `list_commands`, `list_routes`, `get_config_schema`
   - Runtime: `run_console_command`, `query_database`, `read_log_entries`, `last_error`, `app_info`
   - Validation: `validate_module`, `check_config_key`

3. **`marko/lsp`** — LSP server. Pure PHP, stdio JSON-RPC. Features:
   - Config key completion + goto + diagnostics (111+ call sites)
   - Template name resolver for `'module::template'` syntax (129+ call sites)
   - Translation key completion + goto
   - Custom attribute parameter completion (#[Command], #[Observer], #[Plugin], #[Route], etc.)
   - Inverse index code-lenses (observers of event, plugins targeting class)

4. **`marko/docs`** — docs-search contract. `DocsSearchInterface`, `DocsResult`, `DocsPage` value objects. No content, no driver.

5. **`marko/docs-markdown`** — raw markdown source. Relocates monorepo `docs/` contents to `packages/docs-markdown/`. Drives marko.build/docs (build pipeline updates to point there).

6. **`marko/docs-fts`** — FTS5 lexical driver. Requires `marko/docs` + `marko/docs-markdown`. Build step generates docs.sqlite with FTS5 + BM25. Lightweight alternative.

7. **`marko/docs-vec`** — sqlite-vec + FTS5 hybrid driver. Same deps + bundled bge-small-en-v1.5 ONNX model (~40MB). Query-time embeds via transformers-php. Reciprocal Rank Fusion combines lexical + semantic. Default recommendation.

8. **`marko/devai`** — installer/orchestrator. Requires `marko/mcp` and `marko/lsp`. Provides `devai:install` interactive command:
   - Prompts which agents to configure (Claude Code, Codex, Cursor, Copilot, Gemini CLI, Junie)
   - Writes canonical `AGENTS.md`
   - Writes `CLAUDE.md` with `@AGENTS.md` import + Claude-specific additions
   - Registers `marko/mcp` per agent (claude mcp add / codex mcp add / Cursor JSON / etc.)
   - Registers `marko/lsp` for agents that support custom LSPs
   - Distributes skills to `.claude/skills/`, `.agents/skills/`
   - Renders Marko guidelines per agent
   - Prompts for docs driver (docs-vec default, docs-fts alt)
   - Agent adapter contract for community extensibility
   - Third-party `marko/*` packages can contribute via `resources/ai/` convention
   - Re-runnable as `devai:update`

**Skeleton integration:**
- Update `marko/skeleton` installer to prompt "Install marko/devai for AI-assisted development? (recommended)" — **checked by default** (nearly every developer will want the recommended AI-assisted setup).

**Documentation:**
- Add a comprehensive "AI-assisted development" section to marko.build/docs covering devai/mcp/lsp overview, per-agent setup, docs driver comparison, contribution guide, troubleshooting, and manual-verification checklist.

### Out of Scope
- Remote docs API (local-only at launch)
- Custom IDE extensions beyond LSP wiring
- Competitor to Intelephense/Phpactor for general PHP
- `llms.txt` as a load-bearing channel (publish one on docs site as insurance, don't rely on it)
- Claude Code plugin marketplace submission (post-1.0)
- Per-agent sibling packages (rejected: 90% shared, only wiring differs)
- Route name resolution (Marko has none — attribute-based with paths only)
- DI binding navigation (Intelephense handles `::class` syntax)
- Magic method resolution (Marko forbids magic)

## Success Criteria
- [ ] All eight new packages created with PSR-4 autoloading, composer.json, module.php, tests
- [ ] Both renames complete with namespace + composer.json + reference updates throughout monorepo
- [ ] `marko/codeindexer` parses all Marko attributes + config/translation/view dirs, builds cached index, passes unit + integration tests
- [ ] `marko/mcp` responds to MCP handshake + exposes all listed tools via stdio JSON-RPC, verified with Claude Code and Codex end-to-end
- [ ] `marko/lsp` responds to LSP initialize + provides completion/goto/diagnostics for config keys, templates, translations, and attribute params
- [ ] `marko/docs-fts` generates and queries FTS5 index from markdown source at build time
- [ ] `marko/docs-vec` generates hybrid FTS5 + vector index and performs RRF queries with bundled ONNX model
- [ ] `marko/devai` writes valid per-agent configs for all six supported agents in one installer run
- [ ] Skeleton installer prompts for devai and triggers `devai:install` post-create when accepted
- [ ] marko.build/docs build pipeline updated to read from `packages/docs-markdown/`
- [ ] All tests passing (`composer test`)
- [ ] Code follows Marko standards (strict types, constructor promotion, readonly where appropriate, no magic, no final, typed constants)
- [ ] Devils-advocate review passes for each phase

## Task Overview

### Phase 1 — Renames (establish convention)
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Rename rate-limiting → ratelimiter package | - | completed |
| 002 | Update rate-limiting references across monorepo | 001 | completed |
| 003 | Rename dev-server → devserver package | - | completed |
| 004 | Update dev-server references across monorepo | 002, 003 | completed |

### Phase 2 — codeindexer (shared dependency)
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 005 | Create codeindexer package skeleton | - | completed |
| 006 | Implement module walker (vendor/modules/app scanning) | 005 | completed |
| 007 | Implement attribute parser for Observer/Plugin/Before/After/Preference/Command/Route | 005 | completed |
| 008 | Implement config file scanner with dot-notation key extraction | 005 | completed |
| 009 | Implement template file scanner for resources/views/ | 005 | completed |
| 010 | Implement translation file scanner for resources/translations/ | 005 | completed |
| 011 | Implement index cache with serialization + invalidation | 006, 007, 008, 009, 010 | completed |
| 012 | Add `indexer:rebuild` CLI command | 011 | completed |

### Phase 3 — docs contract + markdown + FTS
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 013 | Create marko/docs contract package (interfaces, value objects) | - | completed |
| 014 | Create marko/docs-markdown package; relocate monorepo docs/ contents | - | completed |
| 015 | Update marko.build build pipeline to read from packages/docs-markdown/ | 014 | completed |
| 016 | Create marko/docs-fts package skeleton | 013, 014 | completed |
| 017 | Implement FTS5 SQLite index builder (build step) | 016 | completed |
| 018 | Implement FTS5 search driver | 017 | completed |
| 019 | Wire docs-fts DI binding to DocsSearchInterface | 018 | completed |

### Phase 4 — MCP server
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 020 | Create marko/mcp package skeleton | 011, 013 | completed |
| 021 | Implement JSON-RPC stdio protocol handler | 020 | completed |
| 022 | Implement MCP server init + capability negotiation | 021 | completed |
| 023 | Implement search_docs MCP tool | 022, 013 | completed |
| 024 | Implement list_modules, list_commands, list_routes tools | 022 | completed |
| 025 | Implement resolve_preference, resolve_template tools | 022 | completed |
| 026 | Implement find_event_observers, find_plugins_targeting tools | 022 | completed |
| 027 | Implement get_config_schema, check_config_key tools | 022 | completed |
| 028 | Implement validate_module tool | 022 | completed |
| 029 | Implement runtime tools: run_console_command, query_database, read_log_entries, last_error, app_info | 022 | completed |
| 030 | Add `mcp:serve` CLI command | 022 | completed |

### Phase 5 — LSP server
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 031 | Create marko/lsp package skeleton | 011 | completed |
| 032 | Implement LSP stdio JSON-RPC protocol handler | 031 | completed |
| 033 | Implement LSP initialize + capability negotiation | 032 | completed |
| 034 | Implement config key completion + goto + diagnostics | 033 | completed |
| 035 | Implement template name completion + resolution + diagnostics | 033 | completed |
| 036 | Implement translation key completion + goto | 033 | completed |
| 037 | Implement custom attribute parameter completion | 033 | completed |
| 038 | Implement inverse index code-lenses | 033 | completed |
| 039 | Add `lsp:serve` CLI command | 033 | completed |

### Phase 6 — docs-vec (semantic hybrid driver)
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 040 | Create marko/docs-vec package skeleton | 013, 014 | completed |
| 041 | Integrate sqlite-vec extension + bundle bge-small-en-v1.5 ONNX model | 040 | completed |
| 042 | Implement hybrid index builder (FTS5 + vector columns) | 041 | completed |
| 043 | Implement query-time embedding via transformers-php | 041 | completed |
| 044 | Implement RRF (Reciprocal Rank Fusion) combining lexical + semantic ranks | 042, 043 | completed |
| 045 | Wire docs-vec DI binding to DocsSearchInterface | 044 | completed |

### Phase 7 — devai installer
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 046 | Create marko/devai package skeleton | 030, 039 | completed |
| 047 | Define Agent adapter contract + base class | 046 | completed |
| 048 | Implement Claude Code adapter (CLAUDE.md, .mcp.json, .lsp.json, claude mcp add) | 047 | completed |
| 049 | Implement Codex adapter (AGENTS.md, codex mcp add) | 047 | completed |
| 050 | Implement Cursor adapter (.cursor/rules, .cursor/mcp.json) | 047 | completed |
| 051 | Implement Copilot adapter (.github/copilot-instructions.md, AGENTS.md) | 047 | completed |
| 052 | Implement Gemini CLI adapter (GEMINI.md, gemini mcp add) | 047 | completed |
| 053 | Implement Junie adapter (junie/ folder, AGENTS.md) | 047 | completed |
| 054 | Implement AGENTS.md canonical renderer | 047 | completed |
| 055 | Implement CLAUDE.md renderer with @AGENTS.md import + Claude-specific additions | 047, 054 | completed |
| 056 | Implement guidelines aggregation from marko/* packages (resources/ai/ convention) | 047 | completed |
| 057 | Implement skills distribution to per-agent locations | 047 | completed |
| 058 | Implement `devai:install` interactive command with agent picker + docs driver prompt | 048, 049, 050, 051, 052, 053, 054, 055, 056, 057 | completed |
| 059 | Implement `devai:update` re-run command | 058 | completed |

### Phase 8 — Skeleton integration
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 060 | Update marko/skeleton installer to prompt for devai opt-in | 058 | completed |
| 061 | Implement skeleton post-create hook to trigger devai:install | 060 | completed |

### Phase 9 — READMEs (final, after all packages exist)
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 062 | Create READMEs for all eight new packages | 011, 012, 019, 030, 039, 045, 059 | completed |

### Phase 10 — User-facing documentation
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 063 | Write AI-assisted development guide for marko.build/docs | 011, 012, 019, 030, 039, 045, 059, 061 | completed |

## Architecture Notes

**Follow existing Marko patterns:**
- Interface/driver split (docs contract + docs-fts + docs-vec mirrors cache contract + cache-file + cache-redis)
- `module.php` for bindings, singletons, boot callbacks
- Constructor injection everywhere; interface parameter names follow InterfaceName → interfaceName camelCase rule
- PHP attributes for framework metadata; `module.php` for DI wiring
- Strict types on every file; `readonly class` when all properties immutable; typed constants
- Loud errors via MarkoException subclasses with (message, context, suggestion)
- No final classes (blocks Preferences); no magic methods; no traits

**Hyphen naming rule (enforced by this plan):**
- Hyphens signify sibling/child of a parent package (`docs-fts` is sibling of `docs`)
- Standalone packages are concatenated single tokens (`codeindexer`, `devai`, `ratelimiter`, `devserver`)
- Phase 1 renames establish this rule before any new packages use it

**Reference implementations:**
- Laravel Boost (https://github.com/laravel/boost) — single package + internal agent adapters + `boost:install` installer pattern; six supported agents (Cursor, Claude Code, Codex, Gemini CLI, Copilot, Junie)
- mage-os-lab/magento2-lsp (https://github.com/mage-os-lab/magento2-lsp) — dual-binary pattern (LSP + MCP sharing one static index); static XML/PHP analysis with file-watched cache

**Extension points:**
- `marko/devai` supports community agent adapters via Agent contract
- Any `marko/*` package may ship `resources/ai/guidelines.md` and `resources/ai/skills/*/SKILL.md` — `marko/devai` aggregates these on install
- Marko's Preference system lets users hot-swap any class in these packages

**Docs driver choice:**
- `marko/docs-fts` (lexical FTS5+BM25, ~5MB) — lightweight alternative
- `marko/docs-vec` (hybrid FTS5 + sqlite-vec + bundled ONNX, ~40MB) — default, better for natural-language AI queries
- User picks one via composer require, configured as DI binding like any other Marko driver

## Known Coupling Notes

- **Tasks 014 + 015 are atomic**: The docs content move and the marko.build pipeline update must land in a single commit/PR; otherwise the docs site build breaks. Workers should treat them as a paired unit.
- **Tasks 019 vs 045 DI conflict**: Users install either `marko/docs-fts` OR `marko/docs-vec`. Both bind `DocsSearchInterface` in `module.php`. Task 045 handles the "both installed" case by throwing `BindingConflictException`; Marko's Preference system is the intended escape hatch for users who want both registered and swap between them. Tests for 019 and 045 must each install only their own driver in isolation.
- **Task 007 parser strategy**: AttributeParser uses `nikic/php-parser` AST (not reflection) because scanning vendor modules cannot assume autoloadability. ConfigScanner (008) and TranslationScanner (010) apply the same rule — no file inclusion.

## Risks & Mitigations

- **ONNX runtime install failures on some platforms** → transformers-php ships ONNX binaries for Linux x64/ARM64, macOS x64/ARM64, Windows x64; docs-vec documents fallback to docs-fts on unsupported platforms
- **sqlite-vec extension loading on non-default PHP SQLite builds** → require PHP 8.4+ with `pdo_sqlite` enabled (Marko already requires 8.5+); docs-vec checks extension availability at build time with loud error
- **MCP/LSP protocol churn** → pin to current MCP + LSP spec versions; isolate protocol layer from tool logic for easy upgrades
- **Agent config file format drift (CLAUDE.md, AGENTS.md conventions evolving)** → keep each Agent adapter in its own class; changes scoped to single file
- **Large scope risk** → rigorous phasing with devils-advocate review between phases; renames first to de-risk convention change
- **Pre-built SQLite index staleness** → `marko/docs-fts` and `marko/docs-vec` ship as versioned packages; rebuilt on every release tag from monorepo `docs/` source
- **Breaking package renames pre-1.0** → acceptable; no downstream users yet; fixes convention before 1.0 freezes it
- **ONNX model size (40MB)** → not committed to git; fetched on demand via `marko docs-vec:download-model` post-install (task 041). `.gitignore` excludes weights.
- **query_database safety regression** → read-only enforcement moves to the `ConnectionInterface` layer (task 029). Regex prefix allowlist is secondary defense only.
- **Multi-repo coordination (monorepo + marko.build)** → task 015 spans both. Requires coordinated PRs or a transition-window copy in the monorepo. Called out explicitly in task 015 notes.
- **devai:install re-run policy** → early-exit on detected prior install (via `.marko/devai.json`); `--force` flag for full regeneration. See task 058.
