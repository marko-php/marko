# Task 005: Implement `marko-skills` plugin scaffold (manifest only)

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Build the `marko-skills` Claude Code plugin scaffold under `packages/claude-plugins/plugins/marko-skills/`. This task creates the plugin manifest and the empty `skills/` directory; the actual skill content (`create-module`, `create-plugin`) is populated in tasks 006 and 007. Splitting this from skill content keeps the manifest-validation tests focused.

## Context
- Related files:
  - New: `packages/claude-plugins/plugins/marko-skills/.claude-plugin/plugin.json`
  - New: `packages/claude-plugins/plugins/marko-skills/skills/.gitkeep` (or equivalent — the directory must exist for tasks 006 and 007 to populate)
  - New: `packages/claude-plugins/plugins/marko-skills/README.md`
- Patterns to follow:
  - Plugin manifest schema from Task 001 findings
  - Anthropic's `skills/skill-creator/` directory layout in the cloned `/tmp/anthropics-skills/skills/`
- Plugin namespace: per Anthropic's namespacing rule, skills inside this plugin invoke as `/marko-skills:create-module`, `/marko-skills:create-plugin`. The plugin name `marko-skills` is what becomes the namespace prefix.

## Requirements (Test Descriptions)
- [x] `plugin.json declares name "marko-skills"`
- [x] `plugin.json description states the plugin provides scaffolding skills for Marko modules and plugins`
- [x] `plugin.json includes author with name "Marko Framework"`
- [x] `plugin.json does not include a version field`
- [x] `skills/ directory exists at the plugin root (not inside .claude-plugin/)`
- [x] `README.md lists the included skills (create-module, create-plugin), how they're invoked with the plugin namespace, and how to install via the marko marketplace`

## Acceptance Criteria
- All requirements have passing tests
- Plugin directory layout matches Anthropic's documented anatomy
- `composer test` for the claude-plugins package passes
- The `skills/` directory is empty but exists, ready for tasks 006-007 to populate

## Implementation Notes
- Created `packages/claude-plugins/plugins/marko-skills/.claude-plugin/plugin.json` with `name`, `description` (matching marketplace.json entry verbatim), `author.name: "Marko Framework"`, and standard metadata fields (`$schema`, `homepage`, `repository`, `license`, `keywords`). No `version` field per task requirements.
- Created `packages/claude-plugins/plugins/marko-skills/skills/.gitkeep` so the `skills/` directory exists at the plugin root (not inside `.claude-plugin/`) ready for tasks 006/007.
- Created `packages/claude-plugins/plugins/marko-skills/README.md` listing both skills, their namespaced invocation forms (`/marko-skills:create-module`, `/marko-skills:create-plugin`), and install via `/plugin install marko-skills@marko`.
- Tests at `packages/claude-plugins/tests/Unit/MarkoSkillsPluginTest.php` — 6 tests, 16 assertions, all passing.
