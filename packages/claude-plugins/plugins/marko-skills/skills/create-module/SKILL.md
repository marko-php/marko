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

A Marko module is a Composer package that the framework auto-discovers via the `extra.marko.module` flag. Modules can live anywhere — `packages/{name}/` in the monorepo, `vendor/{vendor}/{package}/` once installed from Packagist, or `app/{Module}/` inside a project. The layout is identical in every case.

**This skill is the canonical specification for a Marko module. Do not inspect existing modules in this project to infer layout — siblings may have drifted from spec. Copy the templates from `assets/` verbatim, substitute placeholders, and stop.**

## Step 1 — Pick a location and name

- Monorepo package: `packages/{name}/` (e.g. `packages/payment/`)
- Vendor package: standalone repo, resolves to `vendor/{vendor}/{name}/`
- App-local module: `app/{Module}/` inside the host project

The composer name is `{vendor}/{name}` and the PHP namespace is its StudlyCase form (e.g. `acme/payment` → `Acme\Payment`).

**Choosing `{vendor}` — derive it from the host project, never hardcode it.** Use the project's root **directory name** as the vendor: a project in `~/Sites/acme` → vendor `acme`, namespace `Acme`. **Never suggest `marko` as the vendor for an application module.** The `marko` vendor is reserved for packages contributed to the Marko framework monorepo itself — only relevant when you are actually working inside that monorepo (its root contains `packages/core/`). Do **not** read the vendor from the project's `composer.json` `name`: a project scaffolded from `marko/skeleton` still carries `marko/skeleton` there, so the directory name is the reliable signal. When you offer the user a default, lead with the project-derived vendor (`{project-dir}/{name}`), not `marko/{name}`.

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

After installing or registering the module, call the MCP tool `list_modules`. The new module should appear in the list. If not, check that:

- `composer.json` has `extra.marko.module: true`
- Composer has run (`composer dump-autoload` or `composer update`)
- The module's psr-4 namespace resolves correctly

## Verification

After writing files, expect LSP diagnostics from `marko-lsp` to surface in the same turn. Resolve all diagnostics before declaring the module complete — diagnostics are the verification gate, not optional warnings. Then call the `list_modules` MCP tool to confirm the module is discovered by the framework.

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
