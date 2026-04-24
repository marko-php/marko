---
title: Installation
description: Install Marko and create your first project.
---

Get Marko installed and running in under a minute. This guide covers requirements, project creation, and choosing the right package set for your use case.

## Requirements

- **PHP 8.5+** with the following extensions:
  - `mbstring`
  - `openssl`
  - `pdo`
  - `json`
- **Composer 2.x**

## Create a New Project

```bash
composer create-project marko/skeleton my-app
cd my-app
```

## Choose Your Stack

Marko's `marko/framework` metapackage bundles the most common packages. You can also install only what you need:

### Full Web Application

```bash
composer require marko/framework
```

Includes core, routing, CLI, error handling, configuration, hashing, and validation. Add database, caching, sessions, and other packages as needed.

### Minimal API

```bash
composer require marko/core marko/routing marko/config marko/env
```

Just the essentials for a lightweight JSON API.

### Headless / CLI

```bash
composer require marko/core marko/cli marko/config marko/env
```

For command-line tools and background workers without HTTP overhead.

## Directory Structure

After installation, your project looks like this:

```
my-app/
├── app/                  # Your application modules (highest priority)
├── modules/              # Third-party modules (medium priority)
├── vendor/               # Composer packages (lowest priority)
├── public/
│   └── index.php         # Web entry point
├── config/               # Application configuration
├── storage/              # Logs, cache, sessions
├── tests/                # Application tests
├── composer.json
└── .env.example
```

## Install the CLI

Install the `marko` command globally so you can run it from anywhere:

```bash
composer global require marko/cli
```

Make sure Composer's global bin directory is in your `PATH`. Add this to your shell profile (`~/.bashrc`, `~/.zshrc`, etc.) if it isn't already:

```bash
export PATH="$HOME/.composer/vendor/bin:$PATH"
```

Verify it works:

```bash
marko list
```

This shows all available commands. Each package registers its own commands — for example, installing `marko/database` adds `db:migrate`, `db:seed`, and other database commands. The more packages you install, the more commands become available.

> You can also run commands locally with `./vendor/bin/marko` if you prefer not to install globally.

## Next Steps

- [Build your first application](/docs/getting-started/first-application/)
- [Understand the project structure](/docs/getting-started/project-structure/)
- [Configure your application](/docs/getting-started/configuration/)
