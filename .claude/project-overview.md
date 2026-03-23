# Project Overview

## Identity

- **Name**: Marko Framework
- **Tagline**: Enterprise-grade extensibility with modern developer experience
- **Website**: marko.build
- **License**: MIT

## Tech Stack

- **Language**: PHP 8.5+
- **Testing**: Pest 4 (parallel, min 80% coverage)
- **Linting**: PHP-CS-Fixer + PHP_CodeSniffer (Slevomat)
- **Refactoring**: Rector
- **Templates**: Latte 3
- **Databases**: MySQL/MariaDB, PostgreSQL
- **Queue**: RabbitMQ, database-backed, synchronous
- **Cache**: File, in-memory (array), Redis
- **HTTP Client**: Guzzle 7

## Repository Structure

- **Type**: Monorepo at `github.com/devtomic/marko`
- **Organization**: `github.com/devtomic`
- **Packages**: 70+ packages in `packages/` directory
- **Split repos**: Each package auto-split to read-only repos for Packagist
- **Versioning**: Unified — all packages share the same version number

## Architecture Summary

- **Everything is a module** — framework, vendor, and app code follow the same rules
- **Interface/driver split** — base packages define contracts, driver packages implement them
- **DI with Preferences & Plugins** — Magento-inspired extensibility without XML
- **Attribute-based metadata** — routes, plugins, observers, commands via PHP attributes
- **PHP-only configuration** — no XML, YAML, or DSL
- **Loud errors** — no silent failures, every error explains what went wrong and how to fix it

## Key Directories

```
packages/       # All framework packages (monorepo)
demo/           # Minimal integration bootstrap (not for demos/showcases)
docs/           # Documentation site content
```

## Package Categories

- **Core**: core, config, env, routing, cli
- **Database**: database (interface), database-mysql, database-pgsql
- **Cache**: cache (interface), cache-file, cache-array, cache-redis
- **View**: view (interface), view-latte
- **Mail**: mail (interface), mail-smtp, mail-log
- **Queue**: queue (interface), queue-sync, queue-database, queue-rabbitmq
- **Session**: session (interface), session-file, session-database
- **Filesystem**: filesystem (interface), filesystem-local, filesystem-s3
- **Errors**: errors (interface), errors-simple, errors-advanced
- **Log**: log (interface), log-file
- **Encryption**: encryption (interface), encryption-openssl
- **Auth**: authentication, authentication-token, authorization
- **Admin**: admin, admin-auth, admin-api, admin-panel
- **Translation**: translation (interface), translation-file
- **Notification**: notification (interface), notification-database
- **PubSub**: pubsub (interface), pubsub-pgsql, pubsub-redis
- **Media**: media, media-gd, media-imagick
- **Other**: blog, validation, hashing, health, http, cors, rate-limiting, scheduler, search, security, testing, api, webhook, pagination, sse, amphp, dev-server
- **Metapackage**: framework (bundles common interface packages)
