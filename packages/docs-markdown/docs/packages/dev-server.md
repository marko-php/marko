---
title: marko/dev-server
description: Start your full development environment with a single command.
---

Start your full development environment with a single command. The Dev Server package provides `up`, `down`, `status`, and `open` CLI commands (aliases for `dev:up`, `dev:down`, `dev:status`, `dev:open`) that orchestrate your PHP built-in server, Docker Compose services, and frontend build tools together. It auto-detects your project's Docker Compose file and package manager, so zero configuration is required for most projects. All services are managed as background processes tracked via a PID file, letting you stop everything cleanly with `marko down`.

## Installation

```bash
composer require marko/dev-server
```

## Usage

### Starting the Environment

```bash
marko up
```

This starts all detected services:

- **PHP server** --- always started at `http://localhost:8000` (serving `public/`)
- **Docker** --- started if a `compose.yaml` / `docker-compose.yml` file is found
- **Frontend** --- started if `package.json` has a `dev` script (uses bun, pnpm, yarn, or npm)

By default `marko up` runs in detached (background) mode. Use `marko status` and `marko down` to manage running services.

### Foreground Mode

```bash
marko up --foreground
# alias: marko up -f
```

Runs services in the foreground. Press `Ctrl+C` to stop all services. This overrides the `detach` default.

### Detached Mode

`marko up` runs detached by default. You can also make this explicit:

```bash
marko up --detach
```

Use `marko status` and `marko down` to manage background services.

### Checking Status

```bash
marko status
```

Shows the name, PID, status (running/stopped), port, and start time for each managed process.

### Opening in Browser

```bash
marko open
```

Opens the running PHP development server in your default browser. The URL is determined dynamically from the running process, so it works with custom ports (e.g. `--port=8080`). Throws a helpful error if no dev environment is running.

### Stopping the Environment

```bash
marko down
```

Stops all processes started by `marko up`.

### Application Entry Point Requirement

`marko up` requires a `public/index.php` entry point. If the file is missing, a `DevServerException` is thrown with a helpful message showing the bootstrap code to create it:

```php title="public/index.php"
<?php

declare(strict_types=1);

use Marko\Core\Application;

require dirname(__DIR__) . '/vendor/autoload.php';

Application::boot(dirname(__DIR__))->handleRequest();
```

### Changing the Port

```bash
marko dev:up --port=8080
```

Overrides the configured port for the PHP built-in server.

## Configuration

Publish or create `config/dev.php` in your application:

```php title="config/dev.php"
<?php

declare(strict_types=1);

return [
    'port'      => 8000,
    'detach'    => true,
    'docker'    => true,
    'frontend'  => true,
    'pubsub'    => true,
    'processes' => [],
];
```

### Configuration Options

| Key | Type | Default | Description |
|---|---|---|---|
| `port` | `int` | `8000` | Port for the PHP built-in server |
| `detach` | `bool` | `true` | Run services in background by default (default: true) |
| `docker` | `true\|string\|false` | `true` | Auto-detect Docker (`true`), custom command (`string`), or disable (`false`) |
| `frontend` | `true\|string\|false` | `true` | Auto-detect frontend (`true`), custom command (`string`), or disable (`false`) |
| `pubsub` | `true\|string\|false` | `true` | Auto-detect pub/sub listener (`true`), custom command (`string`), or disable (`false`) |
| `processes` | `array<string, string>` | `[]` | Named custom processes to run alongside the dev environment |

### The `true | string | false` Pattern

The `docker` and `frontend` keys accept three forms:

```php
// Auto-detect (default): scan for compose file / package.json
'docker' => true,

// Custom command: run exactly this
'docker' => 'docker compose -f infrastructure/compose.yaml up -d',

// Disabled: skip entirely
'docker' => false,
```

### Custom Processes

Use the `processes` key to run additional named processes alongside the standard services:

```php
'processes' => [
    'tailwind' => './tailwindcss -i src/css/app.css -o public/css/app.css --watch',
    'queue' => 'marko queue:work',
],
```

Each process is managed by `ProcessManager` --- output is prefixed with the process name (e.g. `[tailwind]`), and processes are tracked in the PID file when running in detached mode.

### CLI Flag Overrides

Flags passed to `dev:up` take precedence over config file values:

| Flag | Description |
|---|---|
| `--port=N`, `-p=N` | Override the server port |
| `--detach`, `-d` | Run in background (detached mode) |
| `--foreground`, `-f` | Run in foreground mode (overrides detach default) |

## API Reference

### DevUpCommand

```php
use Marko\Core\Attributes\Command;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;

#[Command(name: 'dev:up', description: 'Start the development environment', aliases: ['up'])]
public function execute(Input $input, Output $output): int;
```

### DevDownCommand

```php
use Marko\Core\Attributes\Command;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;

#[Command(name: 'dev:down', description: 'Stop the development environment', aliases: ['down'])]
public function execute(Input $input, Output $output): int;
```

### DevOpenCommand

```php
use Marko\Core\Attributes\Command;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;

#[Command(name: 'dev:open', description: 'Open the running development server in a browser', aliases: ['open'])]
public function execute(Input $input, Output $output): int;
```

### DevStatusCommand

```php
use Marko\Core\Attributes\Command;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;

#[Command(name: 'dev:status', description: 'Show development environment status', aliases: ['status'])]
public function execute(Input $input, Output $output): int;
```

### DockerDetector

Scans the project root for Docker Compose files (`compose.yaml`, `compose.yml`, `docker-compose.yaml`, `docker-compose.yml`) and returns the appropriate up/down commands.

```php
use Marko\DevServer\Detection\DockerDetector;

$dockerDetector = new DockerDetector(projectRoot: '/path/to/project');

/** @return array{upCommand: string, downCommand: string}|null */
$dockerDetector->detect();
```

### FrontendDetector

Checks for a `package.json` with a `dev` script and auto-detects the package manager by looking for lock files (bun, pnpm, yarn, npm --- in that order).

```php
use Marko\DevServer\Detection\FrontendDetector;

$frontendDetector = new FrontendDetector(projectRoot: '/path/to/project');
$frontendDetector->detect(); // e.g. 'bun run dev', or null
```

### ProcessManager

Manages named background processes with prefixed output streaming and signal handling for graceful shutdown.

```php
use Marko\DevServer\Process\ProcessManager;

/** @throws DevServerException */
$processManager->start(string $name, string $command): int;
$processManager->stop(string $name): void;
$processManager->stopAll(): void;
$processManager->getPid(string $name): ?int;
$processManager->getPids(): array; // array<string, int>
$processManager->isRunning(string $name): bool;
$processManager->runForeground(): void;
```

### PidFile

Persists process entries to `.marko/dev.json` for tracking detached processes across commands.

```php
use Marko\DevServer\Process\PidFile;
use Marko\DevServer\Process\ProcessEntry;

/** @param array<ProcessEntry> $entries */
$pidFile->write(array $entries): void;

/** @return array<ProcessEntry> */
$pidFile->read(): array;
$pidFile->clear(): void;
$pidFile->isRunning(int $pid): bool;
```

### ProcessEntry

```php
use Marko\DevServer\Process\ProcessEntry;

readonly class ProcessEntry
{
    public function __construct(
        public string $name,
        public int $pid,
        public string $command,
        public int $port,
        public string $startedAt,
    ) {}
}
```

### DevServerException

Extends `MarkoException` with contextual error messages and suggestions:

- `processFailedToStart(string $name, string $command)` --- thrown when a process fails to start. Suggests checking the command and running `marko status`.
- `portInUse(int $port)` --- thrown when the PHP server port is already in use. Suggests using `--port=XXXX` to pick a different port.
