---
name: marko-create-module
description: Scaffold a new Marko module — a self-contained, composer-installable package with a composer.json, namespaced src/, and Pest tests. Use whenever the user asks to create, add, or scaffold a new Marko module or package, whether in the monorepo's packages/ directory, as a standalone vendor package, or as an app-local module. Marko makes no distinction between core and third-party modules: layout is identical.
---

# Create a Marko module

A Marko module is a Composer package that the framework auto-discovers via the `extra.marko.module` flag. Modules can live anywhere — `packages/{name}/` in the monorepo, `vendor/{vendor}/{package}/` once installed from Packagist, or `app/{Module}/` inside a project. The layout is the same in every case.

## When to use

The user asked to create a new module, package, or extension for Marko, or to break existing functionality out into its own package.

## Step 1 — Pick a location and name

- Monorepo package: `packages/{name}/` (e.g. `packages/payment/`)
- Vendor package: standalone repo, will resolve to `vendor/{vendor}/{name}/`
- App-local module: `app/{Module}/` inside the host project

The composer name is `{vendor}/{name}` (e.g. `marko/payment`, `acme/payment`). The PHP namespace is the StudlyCase form: `Marko\Payment`, `Acme\Payment`.

## Step 2 — Write composer.json

Required keys: `name`, `type: marko-module`, `require.marko/core: self.version` (in the monorepo) or a real constraint like `^1.0` (standalone), psr-4 autoload, and `extra.marko.module: true` to flag it for the codeindexer. **Never set a `version` field** — let Composer infer it from the branch.

```json
{
    "name": "acme/payment",
    "description": "Payment gateway integration for Marko",
    "license": "MIT",
    "type": "marko-module",
    "require": {
        "php": "^8.5",
        "marko/core": "^1.0",
        "marko/config": "^1.0"
    },
    "require-dev": {
        "marko/testing": "^1.0",
        "pestphp/pest": "^4.0"
    },
    "autoload": {
        "psr-4": {
            "Acme\\Payment\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Acme\\Payment\\Tests\\": "tests/"
        }
    },
    "config": {
        "allow-plugins": {
            "pestphp/pest-plugin": true
        }
    },
    "extra": {
        "marko": {
            "module": true
        }
    }
}
```

In the monorepo, replace `^1.0` with `self.version` for any `marko/*` requirement.

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

`tests/Pest.php`:

```php
<?php

declare(strict_types=1);

use Marko\Testing\TestCase;

uses(TestCase::class)->in(__DIR__);
```

## Step 4 — Decide whether you need module.php

`module.php` is **optional**. Only create it if the module needs:

- Explicit DI bindings (interface → concrete class wiring)
- Singleton declarations
- Boot callbacks for lifecycle hooks

If the module is just classes that auto-resolve, **omit `module.php` entirely**. Do not create an empty manifest.

When you do need it, place `module.php` at the module root (next to `composer.json`):

```php
<?php

declare(strict_types=1);

use Acme\Payment\Contracts\GatewayInterface;
use Acme\Payment\Gateway\StripeGateway;
use Marko\Core\Container\ContainerInterface;

return [
    'bindings' => [
        GatewayInterface::class => function (ContainerInterface $container): GatewayInterface {
            // resolve config, build the gateway, return it
            return new StripeGateway(/* … */);
        },
    ],
    'singletons' => [
        // Class names registered as shared instances
    ],
];
```

The `bindings` and `singletons` keys are both optional inside `module.php`. Include only what you need.

## Step 5 — Add a slim README

Per `docs/DOCS-STANDARDS.md`, package READMEs are slim pointers — title, install command, one quick example, and a link to the full docs page. The substantive documentation belongs in `docs/src/content/docs/packages/{name}.md`, not the README.

## Step 6 — Verify the module is discovered

After installing or registering the module, ask the agent to call the MCP tool `list_modules`. The new module should appear in the list. If not, check that:

- `composer.json` has `extra.marko.module: true`
- Composer has run (`composer dump-autoload` or `composer update`)
- The module's psr-4 namespace resolves correctly

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
