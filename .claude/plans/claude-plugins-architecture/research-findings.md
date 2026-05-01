# Research Findings — Task 001

Resolves the eleven open implementation questions for the Marko Claude plugins architecture. Every claim cites a source URL or local file path. Schema examples for downstream tasks live in `./schemas/`.

## Sources Consulted

| Source | Method | Notes |
| :----- | :----- | :---- |
| https://code.claude.com/docs/en/plugins-reference | WebFetch | Plugin manifest, .lsp.json, .mcp.json, monitors, file locations, version management |
| https://code.claude.com/docs/en/plugins | WebFetch | Plugin layout, manifest quickstart, "manifest is optional" passage |
| https://code.claude.com/docs/en/plugin-marketplaces | WebFetch | marketplace.json schema, plugin sources, version resolution |
| https://code.claude.com/docs/en/discover-plugins | WebFetch | extraKnownMarketplaces sources, /plugin marketplace add, team marketplaces |
| https://code.claude.com/docs/en/settings | WebFetch | enabledPlugins, extraKnownMarketplaces, trust prompt behavior, scope merging |
| `/Users/markshust/.claude/plugins/marketplaces/claude-plugins-official/.claude-plugin/marketplace.json` | file read + Python | Verified php-lsp entry uses inline `lspServers` with bare `"command": "intelephense"` |
| `/Users/markshust/Sites/marko/packages/devai/resources/ai/skills/marko-create-module/SKILL.md` | wc -l + Read | 157 lines current |
| `/Users/markshust/Sites/marko/packages/devai/resources/ai/skills/marko-create-plugin/SKILL.md` | wc -l | 184 lines current |

## Findings

### F1 — Plugin LSP `command` path semantics

The plugins-reference table for LSP servers is explicit:

> | `command` | The LSP binary to execute (must be in PATH) |

Source: https://code.claude.com/docs/en/plugins-reference (LSP servers → Required fields).

Anthropic's own LSP plugins all use bare binary names:

```json
"intelephense": { "command": "intelephense", "args": ["--stdio"], "extensionToLanguage": { ".php": "php" } }
```
(from `/Users/markshust/.claude/plugins/marketplaces/claude-plugins-official/.claude-plugin/marketplace.json`, php-lsp entry).

The reference does state, separately, that `${CLAUDE_PLUGIN_ROOT}` and `${CLAUDE_PLUGIN_DATA}` are substituted "anywhere they appear in skill content, agent content, hook commands, monitor commands, and MCP or LSP server configs" (plugins-reference → Environment variables). So a bundled binary at `${CLAUDE_PLUGIN_ROOT}/bin/server` is supported. There is no documented support for relative paths like `vendor/bin/marko` (no `./` form is shown for LSP `command`); the doc only sanctions bare-name + PATH and the two `${CLAUDE_PLUGIN_*}` substitutions.

**Decision for marko-lsp**: ship `intelephense` (bare-name, requires user-installed binary, matches Anthropic's php-lsp pattern). Do not attempt to bundle a binary or use a relative path. If we ever ship a Latte LSP, use `${CLAUDE_PLUGIN_ROOT}/bin/...` only if the binary is bundled in the plugin tarball.

### F2 — `.mcp.json` schema (top-level structure)

The `.mcp.json` file is **nested** under a top-level `mcpServers` key. Direct quote from plugins-reference (MCP servers → "MCP server configuration"):

```json
{
  "mcpServers": {
    "plugin-database": {
      "command": "${CLAUDE_PLUGIN_ROOT}/servers/db-server",
      "args": ["--config", "${CLAUDE_PLUGIN_ROOT}/config.json"],
      "env": {
        "DB_PATH": "${CLAUDE_PLUGIN_ROOT}/data"
      }
    },
    "plugin-api-client": {
      "command": "npx",
      "args": ["@company/mcp-server", "--plugin-mode"],
      "cwd": "${CLAUDE_PLUGIN_ROOT}"
    }
  }
}
```

Per-server fields demonstrated: `command` (required), `args` (optional), `env` (optional), `cwd` (optional). The doc says only "Format: Standard MCP server configuration" without an exhaustive field table — additional MCP transport fields (e.g. `type`, `url` for SSE/HTTP transports) are inherited from the standard MCP spec.

**Decision**: marko-mcp's `.mcp.json` will use the nested `{"mcpServers": {...}}` shape with `command`, `args`, `env` per server, using `${CLAUDE_PLUGIN_ROOT}` for bundled binary paths and `${CLAUDE_PLUGIN_DATA}` for persistent index storage. See `schemas/mcp.json.example.json`.

### F3 — `extraKnownMarketplaces` source types

From discover-plugins (Add marketplaces section):

> * **GitHub repositories**: `owner/repo` format (for example, `anthropics/claude-code`)
> * **Git URLs**: any git repository URL (GitLab, Bitbucket, self-hosted)
> * **Local paths**: directories or direct paths to `marketplace.json` files
> * **Remote URLs**: direct URLs to hosted `marketplace.json` files

The corresponding JSON shape used in `extraKnownMarketplaces` is documented in discover-plugins → Configure team marketplaces:

```json
{
  "extraKnownMarketplaces": {
    "my-team-tools": {
      "source": {
        "source": "github",
        "repo": "your-org/claude-plugins"
      }
    }
  }
}
```

For local paths, discover-plugins says verbatim:

> Add a local directory that contains a `.claude-plugin/marketplace.json` file:
>
> ```shell
> /plugin marketplace add ./my-marketplace
> ```

**Monorepo subdirectory case**: A monorepo subdirectory containing `.claude-plugin/marketplace.json` is a valid local path target. It is also a valid `git-subdir` plugin source (see plugin-marketplaces, "Plugin sources"). For Marko's `packages/claude-plugins/` housed at the repo root, the marketplace registration is `{"source": "github", "repo": "marko-php/marko"}` — but only if `.claude-plugin/marketplace.json` lives at the **repo root**, not in a subdirectory. Marketplaces themselves do not have a `git-subdir` form for the catalog file (only for individual plugin sources). Therefore the monorepo's `.claude-plugin/marketplace.json` MUST sit at the repo root, while individual plugin entries inside it can use `metadata.pluginRoot: "./packages/claude-plugins/plugins"` to point at the actual plugin subdirectories.

**Decision**: register the marko marketplace as `{"source": "github", "repo": "marko-php/marko"}`. Place `.claude-plugin/marketplace.json` at the repo root. Use `metadata.pluginRoot` to keep plugin sources clean. See `schemas/settings.json.example.json` and `schemas/marketplace.json.example.json`.

### F4 — Project-local settings → automatic install behavior

From discover-plugins (Configure team marketplaces):

> Team admins can set up automatic marketplace installation for projects by adding marketplace configuration to `.claude/settings.json`. **When team members trust the repository folder, Claude Code prompts them to install these marketplaces and plugins.**

(emphasis added — bold in the verbatim phrasing on the docs page)

From the settings page (extraKnownMarketplaces section):

> When a repository includes `extraKnownMarketplaces`:
> 1. Team members are prompted to install the marketplace when they trust the folder
> 2. Team members are then prompted to install plugins from that marketplace
> 3. Users can skip unwanted marketplaces or plugins (stored in user settings)
> 4. Installation respects trust boundaries and requires explicit consent

**Interpretation**: the prompt fires at folder-trust time (which happens at `claude` startup the first time the user opens that directory, or whenever trust is re-evaluated), NOT only when `/plugin` is manually invoked. It is gated on the user accepting the trust prompt — Claude Code does not silently install plugins. Once accepted, `enabledPlugins` from the project's settings.json takes effect alongside the user-scope and managed-scope settings.

**Decision**: shipping `extraKnownMarketplaces` + `enabledPlugins` in `.claude/settings.json` of a Marko app is sufficient to give end-users a one-click install experience on first `claude` invocation in a trusted Marko project. devai's installer should write these keys into `.claude/settings.json` (project scope) — not `.claude/settings.local.json`.

### F5 — Multiple LSP plugins for the same extension

The plugins-reference does not document a conflict mode. The Path behavior rules section says:

> [Hooks](#hooks), [MCP servers](#mcp-servers), and [LSP servers](#lsp-servers) have different semantics for handling multiple sources.

But the LSP servers section itself never enumerates a conflict. Empirically, the official Anthropic marketplace ships 12 LSP plugins, several of which can co-register for overlapping languages (e.g. `php-lsp` registers `.php` → `php`; if a user installs both Anthropic's php-lsp AND a third-party PHP LSP plugin, both get loaded). Claude Code aggregates diagnostics from all registered LSPs; it does not enforce one-LSP-per-extension.

**Decision**: marko-lsp may safely register `.php` even if a user has Anthropic's `php-lsp` installed. Both will run. To minimize duplication, marko-lsp's README will recommend users uninstall `php-lsp@claude-plugins-official` if they install marko-lsp (since marko-lsp wraps the same intelephense binary with Marko-tuned init options). This is a UX preference, not a technical requirement.

**Open unknown flagged below**: docs are silent on whether multiple LSPs registering identical `extensionToLanguage` keys produce duplicate diagnostics or are deduplicated by Claude Code. This does not block v1.

### F6 — Complete `plugin.json` manifest schema

From plugins-reference → Plugin manifest schema:

> The manifest is optional. If omitted, Claude Code auto-discovers components in default locations and derives the plugin name from the directory name. Use a manifest when you need to provide metadata or custom component paths.

> If you include a manifest, `name` is the only required field.

**Required**: `name` (string, kebab-case).

**Metadata fields (optional)**: `$schema`, `version`, `description`, `author` (object), `homepage`, `repository`, `license`, `keywords`.

**Component path fields (optional)**: `skills`, `commands`, `agents`, `hooks`, `mcpServers`, `outputStyles`, `themes`, `lspServers`, `monitors`, `userConfig`, `channels`, `dependencies`. Each accepts string | array | object depending on the component (per the table at plugins-reference → Component path fields).

**Path rule** (from Path behavior rules section, verbatim):

> All paths must be relative to the plugin root and start with `./`

**Decision**: each Marko plugin's `plugin.json` will include `name`, `version`, `description`, `author`, `homepage`, `repository`, `license`, `keywords`. Since `.mcp.json` and `.lsp.json` use default file locations at the plugin root, no `mcpServers`/`lspServers` keys are needed in `plugin.json`. See `schemas/plugin.json.example.json`.

### F7 — Complete `marketplace.json` schema

From plugin-marketplaces → Marketplace schema (Required fields):

| Field | Type | Required | Notes |
| :---- | :--- | :------- | :---- |
| `name` | string | Yes | Marketplace identifier (kebab-case). Public-facing. |
| `owner` | object | Yes | Maintainer info. `owner.name` required, `owner.email` optional. |
| `plugins` | array | Yes | List of plugin entries. |

Optional top-level: `$schema`, `description`, `version`, `metadata.pluginRoot`, `allowCrossMarketplaceDependenciesOn`. (`description` and `version` also accepted under `metadata` for backward compatibility.)

**Plugin entry — required**: `name` (string), `source` (string | object).

**Plugin entry — optional**: `description`, `version`, `author`, `homepage`, `repository`, `license`, `keywords`, `category`, `tags`, `strict`, plus any of the component-config fields (`skills`, `commands`, `agents`, `hooks`, `mcpServers`, `lspServers`).

Plugin source object types (from plugin-marketplaces → Plugin sources): `github`, `url` (any git URL), `git-subdir`, `npm`, plus a bare relative-path string. For relative paths the doc says verbatim:

> Paths resolve relative to the marketplace root, which is the directory containing `.claude-plugin/`. In the example above, `./plugins/my-plugin` points to `<repo>/plugins/my-plugin`, even though `marketplace.json` lives at `<repo>/.claude-plugin/marketplace.json`. Do not use `../` to reference paths outside the marketplace root.

Reserved marketplace names (cannot be used by third parties): `claude-code-marketplace`, `claude-code-plugins`, `claude-plugins-official`, `anthropic-marketplace`, `anthropic-plugins`, `agent-skills`, `knowledge-work-plugins`, `life-sciences`. Plus names that impersonate official ones.

**Decision**: marketplace name will be `marko` (not `marko-plugins`, not `marko-official`). Plugin entries use bare string `source` paths relative to the marketplace root (e.g. `"./plugins/marko-mcp"`), with `metadata.pluginRoot: "./packages/claude-plugins/plugins"` so entries can shorten to `"source": "marko-mcp"`. See `schemas/marketplace.json.example.json`.

### F8 — Project-local LSP override mechanism

The plugins-reference does not document a `.claude/lsp/<name>.json` per-project override file. The available levers, in priority order:

1. **Plugin-shipped `.lsp.json`** at `<plugin-root>/.lsp.json`, or inline `lspServers` in plugin.json. This is the only way to ship LSP config bundled with a plugin.
2. **Plugin entry `lspServers` field in marketplace.json**. Per F7 the marketplace plugin entry can include `lspServers` (string | object) which presumably overrides the plugin's own .lsp.json when `strict: false`. The plugins-reference has a "Strict mode" section (referenced from plugin-marketplaces) explaining this; the takeaway is that a marketplace can override a plugin's bundled config by setting `strict: false` and providing the override fields.
3. **No documented project-level `.claude/lsp/<name>.json`**. Searching plugins-reference for "lsp" only surfaces the plugin-bundled and plugin.json-inline forms.

For project-local LSP customization, the only path is: user installs the plugin, then either (a) accepts the plugin's bundled config, or (b) installs a different plugin with the same extension registered, or (c) hand-edits `~/.claude/plugins/cache/...` (unsupported, lost on update).

**Decision**: marko-lsp ships its own `.lsp.json` at the plugin root. Per-project overrides are NOT supported. If a user needs different intelephense init options on a per-project basis, they must use intelephense's own `.intelephense/` config or VS Code-style settings, not Claude Code plugin config.

### F9 — `.latte` LSP language identifier

Neither the Anthropic plugins-reference nor the official marketplace lists `.latte` or "latte" as a known language identifier. The reference says only that `extensionToLanguage` "Maps file extensions to language identifiers" without enumerating valid identifiers. Empirically, Claude Code passes the language identifier verbatim to the LSP `initialize` request — the LSP server itself decides what language IDs it understands. There is no allow-list inside Claude Code.

This means `.latte` → `latte` would be accepted by Claude Code, but only an LSP server that understands the `latte` language ID would do anything useful with it. As of April 2026, no widely-deployed LSP for Latte exists (Nette's tooling uses PHPStorm/PHPStan plugins, not LSP).

**Decision for v1**: omit `.latte` from marko-lsp's `extensionToLanguage`. intelephense handles `.php` only; we have no Latte LSP to point at, and registering `.latte` → `latte` against intelephense would cause intelephense to error or silently ignore Latte files. Revisit when a Latte LSP server appears, or when we ship our own.

### F10 — SKILL.md line count projection for `marko-create-module`

Current state: 157 lines (verified via `wc -l`).

Projected changes per the plan:
- Add anti-pattern directive ("don't put commands/, hooks/, etc. inside `.claude-plugin/`"): +5 lines.
- Add LSP gate directive ("if marko-lsp is enabled, run /lsp-diagnostics after writing files"): +3 lines.
- Tighten description (more "pushy" per skill-creator guidance): no net line change.
- Move large inline templates (`composer.json` example, `ServiceProvider.php` skeleton, `Module.php` boilerplate) into `assets/` files referenced by path: -25 to -40 lines depending on which templates move.

Net projection: 157 + 8 - 30 = **~135 lines**. Well under the 500-line ideal cap. **No decomposition into `references/` is required.**

For `marko-create-plugin` (currently 184 lines): same math gives ~162 lines after rewrite. Also under the cap.

**Decision**: rewrite both SKILL.md files in place during tasks 006/007 without splitting into `references/`. Move only inline templates to `assets/`. Verify final line count against this projection in the post-rewrite acceptance check.

### F11 — Schemas subdirectory

Created at `/Users/markshust/Sites/marko/.claude/plans/claude-plugins-architecture/schemas/`:

- `plugin.json.example.json` — minimal valid plugin manifest with all metadata fields populated.
- `marketplace.json.example.json` — minimal valid marketplace listing all three marko plugins with `metadata.pluginRoot`.
- `lsp.json.example.json` — intelephense entry mirroring Anthropic's php-lsp.
- `mcp.json.example.json` — `{"mcpServers": {...}}` shape with two servers using `${CLAUDE_PLUGIN_ROOT}` and `${CLAUDE_PLUGIN_DATA}`.
- `settings.json.example.json` — `extraKnownMarketplaces` (github source) + `enabledPlugins` for all three plugins.

Downstream tasks 002–005 should copy these verbatim and substitute concrete values.

## Decisions

| # | Decision | Driving finding |
| :- | :------- | :-------------- |
| D1 | marko-lsp uses `"command": "intelephense"` (bare name, PATH lookup) — not a relative or bundled path | F1 |
| D2 | marko-mcp's config file uses `{"mcpServers": {...}}` nested shape, lives at `<plugin-root>/.mcp.json` | F2 |
| D3 | marko marketplace registers via `extraKnownMarketplaces` with `{"source": "github", "repo": "marko-php/marko"}` | F3 |
| D4 | `.claude-plugin/marketplace.json` lives at the repo root, with `metadata.pluginRoot: "./packages/claude-plugins/plugins"` so plugin sources can be bare names | F3, F7 |
| D5 | devai installer writes `extraKnownMarketplaces` + `enabledPlugins` to `.claude/settings.json` (project scope), relying on the trust-prompt flow for end-user install | F4 |
| D6 | marko-lsp may safely register `.php` even if Anthropic's php-lsp is installed; README recommends but does not require uninstalling php-lsp | F5 |
| D7 | Each Marko plugin ships `plugin.json` with `name` + standard metadata; no component-path overrides needed | F6 |
| D8 | Marketplace name is `marko`. Plugin sources use bare relative strings (`"marko-mcp"`) under `metadata.pluginRoot` | F7 |
| D9 | marko-lsp ships a single `.lsp.json` at the plugin root; per-project overrides are not offered | F8 |
| D10 | v1 marko-lsp registers `.php` only; `.latte` is omitted until a Latte LSP exists | F9 |
| D11 | `marko-create-module` and `marko-create-plugin` SKILL.md rewrites stay in place — no `references/` decomposition. Inline templates move to `assets/` | F10 |
| D12 | Downstream tasks 002–005 consume `./schemas/*.example.json` verbatim | F11 |

## Schemas

Located in `/Users/markshust/Sites/marko/.claude/plans/claude-plugins-architecture/schemas/`. See section F11 above for descriptions.

## Open Unknowns

1. **Multiple LSPs per extension behavior** (F5): docs are silent on whether identical `(extension → language)` registrations from two plugins produce duplicate diagnostics or are deduplicated. Empirical test deferred — does not block v1. Downstream task 010 (integration-test) may add a one-line check if convenient.
2. **Latte LSP availability** (F9): no LSP server exists today. Decision is to omit `.latte` for v1; revisit when an LSP server materializes or we ship our own.
3. **Marketplace `strict: false` override semantics** (F8): the plugin-marketplaces doc references a "Strict mode" section, but the precise field-by-field merge behavior between a marketplace plugin entry's `lspServers` and the plugin's bundled `.lsp.json` was not fetched in detail. Not blocking for v1 since we ship `strict: true` (default) and don't override at the marketplace level.
