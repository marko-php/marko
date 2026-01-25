# Marko Framework

A metapackage that bundles all core Marko packages for typical web applications.

## Installation

```bash
composer require marko/framework
```

## Included Packages

These packages are automatically installed with `marko/framework`:

| Package               | Description                                             |
|-----------------------|---------------------------------------------------------|
| `marko/core`          | Bootstrap, DI container, module loader, plugins, events |
| `marko/routing`       | Route attributes, router, middleware                    |
| `marko/cli`           | Command-line interface and console commands             |
| `marko/errors`        | Error handling abstraction                              |
| `marko/errors-simple` | Simple error handler for production                     |
| `marko/config`        | Configuration management with scoped values             |
| `marko/hashing`       | Password hashing and verification                       |
| `marko/validation`    | Data validation with attribute-based rules              |

## Optional Packages

Install these packages as needed for your application:

### Database

```bash
composer require marko/database marko/database-mysql
# or
composer require marko/database marko/database-pgsql
```

| Package                | Description                |
|------------------------|----------------------------|
| `marko/database`       | Database abstraction layer |
| `marko/database-mysql` | MySQL database driver      |
| `marko/database-pgsql` | PostgreSQL database driver |

### Cache

```bash
composer require marko/cache marko/cache-file
```

| Package            | Description             |
|--------------------|-------------------------|
| `marko/cache`      | Cache abstraction layer |
| `marko/cache-file` | File-based cache driver |

### Session

```bash
composer require marko/session marko/session-file
```

| Package              | Description               |
|----------------------|---------------------------|
| `marko/session`      | Session abstraction layer |
| `marko/session-file` | File-based session driver |

### Authentication

```bash
composer require marko/auth
```

| Package      | Description                      |
|--------------|----------------------------------|
| `marko/auth` | Authentication abstraction layer |

### Logging

```bash
composer require marko/log marko/log-file
```

| Package          | Description               |
|------------------|---------------------------|
| `marko/log`      | Logging abstraction layer |
| `marko/log-file` | File-based logging driver |

### Filesystem

```bash
composer require marko/filesystem marko/filesystem-local
```

| Package                  | Description                  |
|--------------------------|------------------------------|
| `marko/filesystem`       | Filesystem abstraction layer |
| `marko/filesystem-local` | Local filesystem driver      |

### Advanced Error Handling

```bash
composer require marko/errors-advanced
```

| Package                 | Description                                     |
|-------------------------|-------------------------------------------------|
| `marko/errors-advanced` | Advanced error handling with detailed debugging |

## Installation Examples

### Full Web Application

For a complete web application with database, caching, sessions, and authentication:

```bash
composer require marko/framework \
    marko/database marko/database-pgsql \
    marko/cache marko/cache-file \
    marko/session marko/session-file \
    marko/auth \
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
