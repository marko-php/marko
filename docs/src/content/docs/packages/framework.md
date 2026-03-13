---
title: marko/framework
description: A metapackage that bundles all core Marko packages for typical web applications.
---

A metapackage that bundles all core Marko packages for typical web applications.

## Installation

```bash
composer require marko/framework
```

## Included Packages

These packages are automatically installed with `marko/framework`:

| Package | Description |
|---|---|
| [`marko/core`](/docs/packages/core/) | Bootstrap, DI container, module loader, plugins, events |
| [`marko/routing`](/docs/packages/routing/) | Route attributes, router, middleware |
| [`marko/cli`](/docs/packages/cli/) | Command-line interface and console commands |
| [`marko/errors`](/docs/packages/errors/) | Error handling abstraction |
| [`marko/errors-simple`](/docs/packages/errors-simple/) | Simple error handler for production |
| [`marko/config`](/docs/packages/config/) | Configuration management with scoped values |
| [`marko/hashing`](/docs/packages/hashing/) | Password hashing and verification |
| [`marko/validation`](/docs/packages/validation/) | Data validation with attribute-based rules |

## Optional Packages

Install these packages as needed for your application:

### Database

```bash
composer require marko/database marko/database-mysql
# or
composer require marko/database marko/database-pgsql
```

| Package | Description |
|---|---|
| [`marko/database`](/docs/packages/database/) | Database abstraction layer |
| [`marko/database-mysql`](/docs/packages/database-mysql/) | MySQL database driver |
| [`marko/database-pgsql`](/docs/packages/database-pgsql/) | PostgreSQL database driver |

### Cache

```bash
composer require marko/cache marko/cache-file
```

| Package | Description |
|---|---|
| [`marko/cache`](/docs/packages/cache/) | Cache abstraction layer |
| [`marko/cache-file`](/docs/packages/cache-file/) | File-based cache driver |

### Session

```bash
composer require marko/session marko/session-file
```

| Package | Description |
|---|---|
| [`marko/session`](/docs/packages/session/) | Session abstraction layer |
| [`marko/session-file`](/docs/packages/session-file/) | File-based session driver |

### Authentication

```bash
composer require marko/authentication
```

| Package | Description |
|---|---|
| [`marko/authentication`](/docs/packages/authentication/) | Authentication abstraction layer |

### Logging

```bash
composer require marko/log marko/log-file
```

| Package | Description |
|---|---|
| [`marko/log`](/docs/packages/log/) | Logging abstraction layer |
| [`marko/log-file`](/docs/packages/log-file/) | File-based logging driver |

### Filesystem

```bash
composer require marko/filesystem marko/filesystem-local
```

| Package | Description |
|---|---|
| [`marko/filesystem`](/docs/packages/filesystem/) | Filesystem abstraction layer |
| [`marko/filesystem-local`](/docs/packages/filesystem-local/) | Local filesystem driver |

### Advanced Error Handling

```bash
composer require marko/errors-advanced
```

| Package | Description |
|---|---|
| [`marko/errors-advanced`](/docs/packages/errors-advanced/) | Advanced error handling with detailed debugging |

## Installation Examples

### Full Web Application

For a complete web application with database, caching, sessions, and authentication:

```bash
composer require marko/framework \
    marko/database marko/database-pgsql \
    marko/cache marko/cache-file \
    marko/session marko/session-file \
    marko/authentication \
    marko/log marko/log-file
```

### Minimal API

For a lightweight API without sessions or views:

```bash
composer require marko/framework \
    marko/database marko/database-mysql \
    marko/cache marko/cache-file
```

### Headless/CLI Application

For command-line tools or background workers:

```bash
composer require marko/framework \
    marko/database marko/database-pgsql \
    marko/log marko/log-file \
    marko/filesystem marko/filesystem-local
```

## Requirements

- PHP 8.5 or higher
