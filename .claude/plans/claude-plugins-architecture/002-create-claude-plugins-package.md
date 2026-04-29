# Task 002: Create `packages/claude-plugins/` package skeleton + repo-root marketplace.json

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create the new monorepo package `packages/claude-plugins/` (Composer package, hosts plugin assets and tests). Create the Claude Code marketplace manifest at the **monorepo repo root** at `.claude-plugin/marketplace.json` listing the three plugins (marko-skills, marko-lsp, marko-mcp), using `metadata.pluginRoot` to point at `packages/claude-plugins/plugins`. Update `.claude/architecture.md` Package Inventory section to register the new package. The actual plugin contents are built in tasks 003-007.

**Important — marketplace location is the repo root, not the package dir.** Per Task 001 finding F3 (verbatim from plugin-marketplaces docs): "`.claude-plugin/marketplace.json` MUST live at the repo root" because Claude Code's `github`-source marketplaces fetch from the repo root (no `git-subdir` form for the catalog file itself). Use `metadata.pluginRoot: "./packages/claude-plugins/plugins"` so plugin entries can reference plugin subdirs by short name.

## Context
- Related files:
  - New: `.claude-plugin/marketplace.json` (at the **monorepo root** — `/Users/markshust/Sites/marko/.claude-plugin/marketplace.json`)
  - New: `packages/claude-plugins/composer.json` (minimal Composer package, primarily for hosting tests)
  - New: `packages/claude-plugins/plugins/.gitkeep` (the directory must exist for tasks 003-005 to populate)
  - New: `packages/claude-plugins/tests/Pest.php` and at least one schema-validation test
  - Modify: `.claude/architecture.md` — append `marko/claude-plugins` to AI Development Tooling table
  - Reference: `.claude/plans/claude-plugins-architecture/schemas/marketplace.json.example.json` (output of Task 001) — copy this verbatim as the starting point for the new marketplace.json
- Patterns to follow:
  - Existing package composer.json files in `packages/*/composer.json` (for the Composer package shape, but with the deviations below)
  - Anthropic's official marketplace.json at `~/.claude/plugins/marketplaces/claude-plugins-official/.claude-plugin/marketplace.json` (for marketplace structure)
- **`marko/claude-plugins` is NOT a Marko module** — it ships only Claude Code plugin assets, has no PHP runtime behavior, and must not be discovered by the module loader. Concretely:
  - `type` should be `library` (or omit `type`, which defaults to `library`)
  - Do NOT set `extra.marko.module: true`
  - Do NOT include a `module.php`
  - Do NOT declare an autoload namespace unless future PHP helpers are added (none planned)
  - No `version` field (Composer infers from branch, per project convention)
- Verify module discovery skips this package cleanly: scan logic in `marko/core` reads `extra.marko.module`; without that flag the package is invisible to the loader. Tests must assert this.
- Tests directory exists for schema-validation tests added in tasks 003-005.
- **Marketplace.json schema** comes from Task 001's `schemas/` artifact (required vs optional fields). Use that schema to drive the validation tests in this task — do not hand-roll a schema interpretation.

## Requirements (Test Descriptions)
- [x] `composer.json declares package name marko/claude-plugins`
- [x] `composer.json type field is "library" or omitted (defaulting to library)`
- [x] `composer.json does not set extra.marko.module`
- [x] `composer.json does not include a version field`
- [x] `composer.json does not declare a Marko\\ClaudePlugins\\ autoload namespace (no PHP code shipped)`
- [x] `module discovery in marko/core does not register marko/claude-plugins as a Marko module`
- [x] `no module.php file exists at the package root`
- [x] `marketplace.json lives at the monorepo repo root at .claude-plugin/marketplace.json (NOT inside packages/claude-plugins/)`
- [x] `marketplace.json conforms to the schema captured in Task 001's schemas/marketplace.json artifact`
- [x] `marketplace.json references the official schema URL via $schema`
- [x] `marketplace.json declares marketplace name "marko" and owner field per Task 001's required-fields finding`
- [x] `marketplace.json sets metadata.pluginRoot to "./packages/claude-plugins/plugins"`
- [x] `marketplace.json plugins array lists three entries (marko-skills, marko-lsp, marko-mcp) with bare-name source values resolved relative to pluginRoot`
- [x] `marketplace.json each plugin entry includes name, description, author, category fields per Task 001's required-fields finding`
- [x] `architecture.md AI Development Tooling table includes a row for marko/claude-plugins`

## Acceptance Criteria
- All requirements have passing tests (PHP-side asserting JSON file structure via Pest)
- `composer test` passes for the new package
- The package directory exists with the expected layout
- `architecture.md` is updated and follows the existing table format

## Implementation Notes

### Files created
- `/Users/markshust/Sites/marko/.claude-plugin/marketplace.json` — monorepo-root Claude Code marketplace manifest; name `marko`, owner `Mark Shust`, `metadata.pluginRoot` points to `./packages/claude-plugins/plugins`, three plugins (marko-skills, marko-lsp, marko-mcp) each with bare-name source, description, author, and category.
- `/Users/markshust/Sites/marko/packages/claude-plugins/composer.json` — minimal library-type Composer package; no `type` key (defaults to library), no `extra.marko.module`, no `version`, no `autoload` namespace, only `autoload-dev` for test namespace `Marko\ClaudePlugins\Tests\`.
- `/Users/markshust/Sites/marko/packages/claude-plugins/plugins/.gitkeep` — empty placeholder so the plugins/ directory is tracked by git for tasks 003-005.
- `/Users/markshust/Sites/marko/packages/claude-plugins/tests/Pest.php` — minimal Pest bootstrap (`uses()->in(__DIR__);`).
- `/Users/markshust/Sites/marko/packages/claude-plugins/tests/Unit/MarketplaceTest.php` — 15 Pest tests grouped into `describe('composer.json')` and `describe('marketplace.json')` blocks; all pass (56 assertions).

### Files modified
- `/Users/markshust/Sites/marko/.claude/architecture.md` — appended `marko/claude-plugins` row to the AI Development Tooling table.

### Key decisions
- `type` omitted in composer.json (defaults to `library`) — matches spec "omit OR set to library".
- Plugin sources use bare names (`marko-skills`, `marko-lsp`, `marko-mcp`) rather than path strings; they resolve under `metadata.pluginRoot` per F7/D8.
- Module discovery test is a pure JSON-parse assertion on composer.json (no need to invoke core's discovery logic in isolation) — satisfies the requirement with minimal coupling.
- Tests for composer.json requirements 2–6 pass immediately because the file was written correctly from the start; noted per TDD rules.
- php-cs-fixer reports no changes needed on the two PHP files.

### Test results
- 15 tests, 56 assertions, all passed.
