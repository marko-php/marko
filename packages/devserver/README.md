# marko/devserver

Start your full development environment with a single command.

## Overview

`marko/devserver` orchestrates the PHP built-in server, Docker services, and frontend build tools under a single CLI entry point. Run `marko up` and your entire stack starts; run `marko down` and it stops cleanly. Supports both detached and foreground modes, with status inspection at any time.

## Installation

```bash
composer require marko/devserver
```

## Usage

```bash
marko up            # Start PHP server, Docker, and frontend tools (detached by default)
marko up -f         # Start in foreground mode (press Ctrl+C to stop)
marko status        # Check what's running
marko down          # Stop everything
```

## Documentation

Full usage, configuration, and API reference: [marko/devserver](https://marko.build/docs/packages/devserver/)
