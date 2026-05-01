# Task 009: Update non-Claude-Code agent installers to source from canonical skill location

**Status**: completed
**Depends on**: 006, 007
**Retry count**: 0

## Description
Update the non-Claude-Code agent installers (Codex, Cursor, Copilot, Gemini, Junie) to source skill content from the new canonical location at `packages/claude-plugins/plugins/marko-skills/skills/` instead of the legacy `packages/devai/resources/ai/skills/`. These agents do not have plugin systems analogous to Claude Code, so they continue to distribute skills as standalone files in their own conventions (e.g., `.cursor/rules/`, `.gemini/skills/`, `.agents/skills/`, `junie/skills/`). The goal is to eliminate duplicate skill content — there is one canonical source.

## Context
- Related files:
  - Modify: `packages/devai/src/Agents/CodexAgent.php`
  - Modify: `packages/devai/src/Agents/CursorAgent.php`
  - Modify: `packages/devai/src/Agents/CopilotAgent.php`
  - Modify: `packages/devai/src/Agents/GeminiCliAgent.php`
  - Modify: `packages/devai/src/Agents/JunieAgent.php`
  - Modify (likely): `packages/devai/src/Skills/SkillsDistributor.php` — point its source path at `packages/claude-plugins/plugins/marko-skills/skills/` rather than the legacy `packages/devai/resources/ai/skills/`
  - Modify (or remove): `packages/devai/resources/ai/skills/` — directory and contents are no longer the canonical home; legacy skills (the original SKILL.md files moved by tasks 006 and 007) are already removed there
- Patterns to follow: existing per-agent `distributeSkills()` implementations; only the *source* path changes, not the per-agent destination conventions
- The skill content format (SKILL.md frontmatter + bundled assets) was designed for Claude Code's anatomy. Other agents may not understand `assets/` references the same way. Each per-agent installer must decide: (a) copy SKILL.md verbatim and let the agent figure out the assets reference, (b) copy SKILL.md + assets together so paths resolve, or (c) inline the templates back into a flat SKILL.md per agent. Pick the simplest correct option per agent (likely (b) — copy the whole skill directory).

## Requirements (Test Descriptions)
- [x] `CodexAgent installer reads skill source from packages/claude-plugins/plugins/marko-skills/skills/ (not the legacy path)`
- [x] `CursorAgent installer reads skill source from packages/claude-plugins/plugins/marko-skills/skills/`
- [x] `CopilotAgent installer reads skill source from packages/claude-plugins/plugins/marko-skills/skills/`
- [x] `GeminiCliAgent installer reads skill source from packages/claude-plugins/plugins/marko-skills/skills/`
- [x] `JunieAgent installer reads skill source from packages/claude-plugins/plugins/marko-skills/skills/`
- [x] `each agent copies the entire skill directory (SKILL.md plus assets/ and references/) so template references resolve`
- [x] `the legacy packages/devai/resources/ai/skills/ directory is fully removed (parent dir included, not just child SKILL.md files removed by tasks 006/007)`
- [x] `no duplicate skill content exists across the codebase — grep verification`
- [x] `no PHP file under packages/devai/src/ contains the literal string "resources/ai/skills" — grep assertion ensures the legacy path is no longer referenced`

## Acceptance Criteria
- All requirements have passing tests
- All five non-Claude-Code agent installers source from one canonical location
- `composer test` passes for `marko/devai`
- After this task plus tasks 006-007, the codebase has exactly one home for skill content

## Implementation Notes

- **Single source change**: All five agent installers (Codex, Cursor, Copilot, Gemini, Junie) route through `SkillsDistributor.collect()` whose result is passed in as `$bundles`. The agents themselves needed no changes — only `SkillsDistributor` needed updating.

- **SkillsDistributor reworked**: Renamed `$devaiPackageRoot` constructor param to `$projectRoot`. The default `dirname(__DIR__, 4)` correctly resolves to project root from both monorepo (`packages/devai/src/Skills/`) and vendor-installed (`vendor/marko/devai/src/Skills/`) positions. Added `resolveCoreSkillsDir()` which checks monorepo path first (`packages/claude-plugins/plugins/marko-skills/skills/`) then vendor path (`vendor/marko/claude-plugins/plugins/marko-skills/skills/`).

- **Bundle source label**: Changed from `marko/devai` to `marko/claude-plugins` to reflect the new canonical home.

- **Module-level skill discovery unchanged**: Third-party modules continue to ship skills via `resources/ai/skills/` in their own package directories. The constant `MODULE_SKILLS_REL_PATH` preserves this convention. The grep assertion test allows exactly one occurrence of `resources/ai/skills` in `SkillsDistributor.php` (the module walker constant).

- **Legacy directory already gone**: `packages/devai/resources/ai/skills/` was removed by tasks 006/007. Only `resources/ai/guidelines/` remains, which is still needed.

- **composer test pre-existing failure**: `composer test` fails due to a `makeTempDir()` redeclaration conflict between `packages/codeindexer/tests/Unit/Cache/IndexCacheTest.php` and `packages/devai/tests/Unit/Agents/ClaudeCodeAgentTest.php` — this was introduced by task 008 modifications to `ClaudeCodeAgentTest.php` and is not within scope of this task.

- **9 new tests added** to `SkillsDistributorTest.php` covering all requirements. The previously-failing `ships a core skill set from devai own resources` test was replaced with `ships a core skill set from packages/claude-plugins/plugins/marko-skills/skills/ (monorepo)`. Total devai test count: 150 (was 141).
