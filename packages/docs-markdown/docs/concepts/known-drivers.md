---
title: Known Drivers
description: How Marko interface packages declare their official drivers via known-drivers.php — and the conventions that keep descriptions consistent.
---

Most Marko interface packages (`marko/cache`, `marko/database`, `marko/queue`, etc.) define an abstraction without an implementation. The implementation lives in a separate sibling driver package (`marko/cache-redis`, `marko/database-pgsql`, `marko/queue-rabbitmq`). For a given interface package, multiple driver siblings may exist, but only one can be bound at a time.

Each interface package ships a `known-drivers.php` file at its package root that lists every official driver for that interface. This file is the curated source of truth: it tells `NoDriverException` what to suggest when no driver is bound, and it feeds skeleton's `composer.json` `suggest` block so `composer create-project marko/skeleton` surfaces driver options at install time.

## File Format

`known-drivers.php` returns a flat associative array of package name → description string:

```php title="packages/cache/known-drivers.php"
<?php

declare(strict_types=1);

return [
    'marko/cache-file' => 'File-based cache driver (recommended; no infrastructure, single-server apps)',
    'marko/cache-redis' => 'Redis cache driver (distributed deployments and high-throughput)',
    'marko/cache-array' => 'In-memory cache driver (request-lifetime only; testing and dev)',
];
```

**Ordering matters.** The recommended driver (if any) comes first. `NoDriverException` lists drivers in this order; `composer create-project` displays suggestions in this order.

**Only drivers belong here.** Optional add-ons like `marko/database-readwrite` (a decorator) or `marko/page-cache-entity` (an observer add-on) are NOT drivers — they coexist with a base driver rather than replacing it. Add-ons go into skeleton's `suggest` block directly, not into any `known-drivers.php`.

## Description Conventions

Driver descriptions follow a strict format so they read consistently across all 18+ interface packages, both in `NoDriverException` output and in skeleton's `suggest` block.

### Canonical Shape

```
{Tech} {category} driver ({parenthetical})
```

- **`{Tech}`** — the concrete tech name (`Redis`, `PostgreSQL`, `Twig`) OR a descriptive `{Adjective}-based` form (`File-based`, `Token-based`, `OpenSSL-based`) when there's no single product name.
- **`{category}`** — the interface package's family name, used as a noun (`cache`, `database`, `view`, `pub/sub`).
- **`driver`** — always present. Every entry ends with the word "driver".
- **`{parenthetical}`** — optional; one short phrase, all detail inside the parens.

### The `recommended` Marker

At most **one driver per family** can be marked recommended. Use it for the driver that answers "which one should I pick when in doubt?" If every option has a use case, none of them is the default — describe each one's use case instead.

```
'Twig view driver (recommended; broader ecosystem familiarity)'
'Latte view driver (compile-time safety, n:attribute syntax)'
```

The recommended driver's parenthetical follows the shape `(recommended; {justification})`. The semicolon separates the marker from the reason. Don't use `(recommended for X)` — `for` reads ambiguously (sometimes "use in" a scenario, sometimes "because of" a feature).

### Non-Recommended Parentheticals

For non-recommended drivers, describe the driver's nature in one short phrase. Avoid scenario-based "use this if X" framing — those belong in the package docs, not a one-liner.

```
'Redis cache driver (distributed deployments and high-throughput)'
'MySQL/MariaDB database driver'
```

When the parenthetical genuinely combines two independent facts, use a semicolon:

```
'In-memory cache driver (request-lifetime only; testing and dev)'
'ImageMagick media driver (higher fidelity; requires ext-imagick)'
```

### Single-Driver Families

When an interface has only one official driver, no `(recommended)` marker is needed — there's nothing to recommend against. The parenthetical is optional, used only when meaningful tech detail clarifies the driver.

```
'Database-backed notification driver'
'Token-based authentication driver (signed token sessions)'
```

### Punctuation Rules

- **Em-dash (`—`):** not used in driver descriptions. (Skeleton's add-on descriptions may use one for the "optional add-on" qualifier, since add-ons don't follow the driver convention.)
- **Semicolon (`;`):** separates the `(recommended)` marker from its justification, or separates two independent facts in a non-recommended parenthetical.
- **Comma (`,`):** lists items inside a single fact.

### Documented Category Exceptions

Three families use slightly extended category nouns because the bare family name wouldn't be informative enough:

| Family | Category noun used | Why |
|---|---|---|
| `http` | `HTTP client` | "HTTP" alone wouldn't read as a noun |
| `inertia` | `Inertia.js frontend` | Inertia drivers are frontend frameworks, not "Inertia drivers" of various kinds |
| `page-cache` | `page cache` | two-word family name, kept readable with a space |

## Why a Curated Registry?

Marko could have mechanically derived "which packages are drivers" from on-disk introspection — looking for any package whose `module.php` binds a particular interface. We considered that and chose curation instead, for three reasons:

1. **Third-party drivers exist.** Anyone can publish a `marko/cache-*` package. The official `known-drivers.php` lists the drivers the interface maintainer supports. It's the curated answer to "which driver should I pick?", not the exhaustive answer to "what driver packages exist on Packagist?"
2. **Recommendations need a maintainer's voice.** Mechanical derivation can't tell you that `cache-file` is the sensible default and `cache-redis` is the opt-in for distributed deployments. That judgment belongs in `known-drivers.php`.
3. **CI can mechanically enforce sync.** The [`KnownDriversValidator`](/packages/testing/#knowndriversvalidator) helper compares each interface's `known-drivers.php` against skeleton's `suggest` block. Drift fails the build.

## NoDriverException Integration

Every interface package's `NoDriverException` reads its driver list from `known-drivers.php` at exception-construction time. When no driver is bound, the suggestion text lists every known driver with its description, the `composer require` command, and a docs URL derived from the package basename:

```
Install one of these drivers:
- marko/cache-file: File-based cache driver (recommended; no infrastructure, single-server apps)
  Install: composer require marko/cache-file
  Docs: https://marko.build/docs/packages/cache-file/
- marko/cache-redis: Redis cache driver (distributed deployments and high-throughput)
  Install: composer require marko/cache-redis
  Docs: https://marko.build/docs/packages/cache-redis/
- marko/cache-array: In-memory cache driver (request-lifetime only; testing and dev)
  Install: composer require marko/cache-array
  Docs: https://marko.build/docs/packages/cache-array/
```

The description string is taken verbatim from `known-drivers.php`. This is why description-string consistency matters — every divergence shows up in user-facing error output.

## Adding a New Driver

Maintainers of an interface package add a new driver by appending an entry to that package's `known-drivers.php`:

```php
return [
    // existing entries...
    'marko/cache-memcached' => 'Memcached cache driver (legacy clusters, broad client support)',
];
```

The per-package `KnownDriversValidationTest` will then fail until the matching entry is added to skeleton's `composer.json` `suggest` block with the same description string. That's the intended workflow — the CI test guarantees the two stay in sync.

When picking the description string, follow the conventions above. If you're unsure, look at the existing entries in [`packages/cache/known-drivers.php`](https://github.com/marko-php/marko/blob/develop/packages/cache/known-drivers.php) and [`packages/queue/known-drivers.php`](https://github.com/marko-php/marko/blob/develop/packages/queue/known-drivers.php) for representative shapes.
