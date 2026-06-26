---
name: create-module
description: >
  Scaffold a new Marko module — a self-contained Composer package with composer.json, namespaced src/, and Pest tests.
  **Use this skill whenever the user asks to create, add, or scaffold a new Marko module or package.**
  Concrete triggers: 'create a module named payment', 'scaffold an acme/blog package', 'add a new module for X',
  'break this code out into its own package', 'I need a new module', 'make me a Marko module'.
  Marko makes no distinction between core and third-party modules: layout is identical.
---

# Create a Marko module

A Marko module is a Composer package that the framework auto-discovers via the `extra.marko.module` flag. Modules can live anywhere — `app/{name}/` for application code, `modules/{vendor}/{name}/` for a distributable package dropped into a host project, `packages/{name}/` in the framework monorepo, or `vendor/{vendor}/{name}/` once installed from Packagist. The layout is identical in every case. Directory and Composer-name segments are always lowercase; only the PHP namespace is StudlyCase.

**This skill is the canonical specification for a Marko module. Do not inspect existing modules in this project to infer layout — siblings may have drifted from spec. Copy the templates from `assets/` verbatim, substitute placeholders, and stop.**

## Step 1 — Confirm the name, then pick a location

**If the user did not give the module a name, ask for one before scaffolding anything.** Do not invent a name, reuse a vendor as the name, or proceed with a placeholder.

### Casing rule — read this before creating any directory

A module's identity has two forms that must stay in sync:

- **Directory and Composer name → always lowercase.** They are identical segment-for-segment, so a module can move between tiers (e.g. `modules/` → `vendor/`) or be symlinked with zero path rewrites.
- **PHP namespace → always StudlyCase.** `blog` → `Blog`, `my-payment` → `MyPayment`.

So the name `blog` yields directory `blog/`, Composer name `…/blog`, and namespace `…\Blog`. **Lowercase module directories are correct** — do not capitalize them. The casing only flips to StudlyCase in the PHP namespace and the psr-4 autoload key. (If you have seen "lowercase module names" before and assumed it was a bug, it was not: the directory is supposed to be lowercase; only the namespace is StudlyCase.)

### Pick the tier by where the module lives

| Tier | Directory | Composer name | Namespace | When |
|---|---|---|---|---|
| **App-local** | `app/{name}/` | `app/{name}` | `App\{Name}` | Application code, not distributed. The vendor segment is always the literal `app` — there is no project-derived vendor. |
| **Distributable** | `modules/{vendor}/{name}/` | `{vendor}/{name}` | `{Vendor}\{Name}` | A package you intend to publish or share, dropped into a host project. Two-segment, vendor-scoped. |
| **Framework monorepo** | `packages/{name}/` | `marko/{name}` | `Marko\{Name}` | Only when working inside the Marko monorepo itself (root contains `packages/core/`). |

**Never author into `vendor/`** — it is ephemeral and populated by Composer. The `modules/` tier is the manual-install mirror of `vendor/`: same two-segment `{vendor}/{name}` shape, so a module can later move to `vendor/` (or be symlinked) without changes.

**Default tier:** if the user does not specify, assume an **app-local** module (`app/{name}/`, namespace `App\{Name}`). Only use `modules/{vendor}/{name}/` when the user signals the module is meant to be distributed/shared.

**Choosing `{vendor}` (distributable modules only — app-local modules have no vendor):** derive it from the host project's root **directory name** (a project in `~/Sites/acme` → vendor `acme`). Do **not** read it from the project's `composer.json` `name`: a project scaffolded from `marko/skeleton` still carries `marko/skeleton` there, so the directory name is the reliable signal. **Never use `marko` as the vendor** for an application or third-party module — that vendor is reserved for packages inside the framework monorepo.

## Step 2 — Write composer.json

Copy the appropriate template to `<module-root>/composer.json` and substitute all placeholders.

Required keys: `name`, `type: marko-module`, `require.marko/core`, psr-4 autoload, and `extra.marko.module: true` to flag it for the code indexer. **Never set a `version` field** — let Composer infer it from the branch.

### Deterministic constraint selection rule

Resolve `{{marko_constraint}}` and choose the template using this ordered decision tree:

1. **Inside the Marko monorepo** (root directory contains `packages/core/`) → use `assets/composer.json.monorepo.tmpl`. Set `{{marko_constraint}}` to `self.version`. This ensures all packages in the monorepo resolve against each other at the same version without manual pinning.

2. **Otherwise (host project / standalone module)** → use `assets/composer.json.tmpl`. Determine `{{marko_constraint}}` by reading the host project's root `composer.json`:
   - Find the first `marko/*` constraint that is already declared (e.g. `"marko/framework": "*"` → constraint is `*`; `"marko/framework": "dev-develop"` → constraint is `dev-develop`; `"marko/framework": "^1.2"` → constraint is `^1.2`).
   - If no `marko/*` constraint is present in the host `composer.json`, default to `*`.

   Using `*` (or matching the host's existing constraint) ensures the scaffolded module resolves against whatever version of Marko the host project is consuming — whether that is a tagged release, a `dev-develop` branch alias, or a Composer path symlink. **Never default to `^1.0`**: that constraint is incompatible with any host consuming `dev-develop` or `*`, which is the most common case during active development (e.g. `marko/skeleton` requires `marko/framework: *`).

## Step 3 — Create the directory layout

```
{module-root}/
  composer.json
  src/                      # PSR-4 source
  tests/
    Pest.php                # Pest bootstrap
    Unit/
    Feature/
  README.md                 # Slim pointer per docs/DOCS-STANDARDS.md
```

Copy `assets/Pest.php.tmpl` to `tests/Pest.php`. No placeholder substitution needed.

## Step 4 — Decide whether you need module.php

`module.php` is **optional**. Only create it if the module needs explicit DI bindings (interface → concrete class wiring), singleton declarations, or boot callbacks for lifecycle hooks.

If the module is just classes that auto-resolve, **omit `module.php` entirely**. Do not create an empty manifest.

When you do need it, copy `assets/module.php.tmpl` to `<module-root>/module.php` and substitute `{{Vendor}}` and `{{Name}}` placeholders.

## Step 5 — Add a slim README

Copy `assets/README.md.tmpl` to `<module-root>/README.md` and substitute `{{vendor}}` and `{{name}}` placeholders.

Per `docs/DOCS-STANDARDS.md`, package READMEs are slim pointers — title, install command, one quick example, and a link to the full docs page. Substantive documentation belongs in `docs/src/content/docs/packages/{name}.md`, not the README.

## Step 6 — Verify the module is discovered

The module exists because you wrote it. The runtime discovers and autoloads it live on the next request — no `composer dump-autoload`, no index rebuild, no extra registration step. The MCP/LSP index self-refreshes (scoped to `app/` and `modules/`), so `list_modules` and `validate_module` will reflect freshly created code automatically.

If you want to double-check structure, confirm:

- `composer.json` has `extra.marko.module: true`
- The module's psr-4 namespace resolves correctly

You may call `list_modules` or `validate_module` as an optional cross-check, but these are not gates — the module is live as soon as the files are in place.

## Verification

After writing files, expect LSP diagnostics from `marko-lsp` to surface in the same turn. Resolve all diagnostics before declaring the module complete — diagnostics are the verification gate, not optional warnings. Calling `list_modules` is an optional cross-check to confirm the MCP index reflects the new module; it is not required for the module to be active.

## Conventions to enforce

- Every PHP file: `declare(strict_types=1);`
- Constructor property promotion always
- Type declarations on every parameter, return, and property
- No `final` classes (blocks Preferences extensibility)
- No magic methods — be explicit
- Use `readonly` where immutability is appropriate, not as a blanket rule

## What this skill does not cover

- Authoring plugins for the new module — see the `marko-create-plugin` skill
- Adding routes, observers, commands — see the relevant Marko docs pages
- Database migrations — see the `marko/database` package docs

## See also

- [Marko docs: modularity](https://marko.build/docs/concepts/modularity/)
- [`marko/core` README](https://github.com/markshust/marko/tree/develop/packages/core)
