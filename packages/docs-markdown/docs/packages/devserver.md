---
title: marko/devserver
description: Start your full development environment with a single command.
---

Start your full development environment with a single command. The Dev Server package provides `up`, `down`, `status`, and `open` CLI commands (aliases for `dev:up`, `dev:down`, `dev:status`, `dev:open`) that orchestrate your PHP built-in server, Docker Compose services, and frontend build tools together. It auto-detects your project's Docker Compose file and package manager, so zero configuration is required for most projects. All services are managed as background processes tracked via a PID file, letting you stop everything cleanly with `marko down`.

## Installation

```bash
composer require marko/devserver
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

`Ctrl+C` waits until every service has exited before returning, including any child processes a service started (such as PHP server workers or the process behind `npx`). Each service gets `SIGTERM` and up to 3 seconds to shut down cleanly; anything still running after that is sent `SIGKILL`. All services share that grace period, so shutdown takes about as long as the slowest service.

### Detached Mode

`marko up` runs detached by default. You can also make this explicit:

```bash
marko up --detach
```

Use `marko status` and `marko down` to manage background services.

Detached mode needs the `posix` and `pcntl` PHP extensions. Without them, `marko up` throws a `DevServerException` that tells you to enable them or use `--foreground`.

### Startup Failures

`marko up` reports a service that cannot start with a `DevServerException`, instead of printing it as running. It checks the following in both modes:

- **Port in use**: before starting any service, `marko up` checks whether the PHP server's port is free. If another process is listening on it, it throws `Port 8000 is already in use` and starts nothing.
- **Missing command**: before spawning each service, the first word of its command is looked up (`command -v` for a bare name, or a file check for a path such as `./tailwindcss`). A command that does not exist or is not executable fails right away with a message such as `Failed to start process 'tailwind' with command: ./tailwindcss --watch ('./tailwindcss' does not exist)`. Leading `VAR=value` assignments, `env` and `exec` are skipped. Commands the shell has to interpret first, such as quoted words, `$(...)` or `if ...; fi`, are not looked up. They are caught by the startup probe below.
- **Early exit**: each service is watched for a short probe window (0.5 seconds by default) after it starts:
  - **Foreground mode**: the service fails if it exits with code 126 (not executable) or 127 (command not found) during the window. `start()` returns as soon as a service exits, so the window only delays services that keep running. A service that exits with any other code is reported as `[name] exited with code N` while the environment runs.
  - **Detached mode**: the service fails if it exits during the window for any reason, and the error includes its exit code, for example `(exited with code 127)`. Each detached service runs under a small PHP supervisor that leads the service's process group. The supervisor keeps the service's stdin open, waits for the service to exit, and records its exit code. On a busy machine the supervisor can take longer than the window to take over the process group, so the window ends only once it has. That way `marko status` and `marko down` find the group as soon as `marko up` returns. If the supervisor has not taken over the group 10 seconds after the window ends, the service fails with `(did not become a process group leader within 10 seconds)`.

In foreground mode, `marko up` also waits until the PHP server accepts connections before it starts streaming output. It returns as soon as the first connection succeeds. If the server exits first, `marko up` checks the port again:

- If the port is now taken (another process grabbed it after the first check), it throws `Port N is already in use`.
- Otherwise it throws `PHP server exited with code N before accepting connections on HOST:PORT`, with the server's output in the exception context. Typical causes are an address that is not local to this machine, a syntax error in `public/index.php`, or a missing PHP extension.

If the server neither accepts connections nor exits within 10 seconds, it throws a `DevServerException` naming the host and port.

### Rollback on Failure

`marko up` either starts the whole environment or leaves nothing running. If any step fails after the first service has started (a service that cannot start, the PHP server exiting, or the PHP server not accepting connections in time), `marko up`:

1. Prints `Startup failed. Stopping the services that already started...`
2. Removes any PID file left by an earlier run. Its processes have already been confirmed stopped.
3. Stops every service it started, in both foreground and detached mode, with the same `SIGTERM` and `SIGKILL` escalation as `marko down`.
4. Rethrows the original error.

After a failed detached `marko up`, nothing is left running and there is no PID file, so the next `marko up` starts cleanly.

If a service's process group survives `SIGKILL` during the rollback, `marko up` throws a `DevServerException` that names every surviving service and its PID, with the original failure as the previous exception and in the context. The suggestion gives the `kill -9` command to stop them.

### Checking Status

```bash
marko status
```

Shows the name, PID, status (running/stopped), port, and start time for each managed process.

### Opening in Browser

```bash
marko open
```

Opens the running PHP development server in your default browser. The URL is built from the host and port the server was started with, so it works with custom ports (e.g. `--port=8080`) and hosts. Wildcard hosts (`0.0.0.0`, `::`) open `localhost`, and IPv6 hosts are bracketed (`http://[::1]:8000`). Throws a helpful error if no dev environment is running.

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

### Changing the Host

```bash
marko up --host=0.0.0.0   # all IPv4 interfaces
marko up --host=::1       # IPv6 loopback
marko up --host=[::1]     # the same, bracketed
marko up --host=::        # all IPv6 interfaces
```

Overrides the configured host (`localhost` by default). The host must be a hostname, an IPv4 address, or an IPv6 address with or without one pair of brackets. Anything else, including a bracketed hostname or a value with a port, throws `Invalid host value` before anything starts. IPv6 addresses are passed to the PHP server bracketed (`php -S [::1]:8000`).

## Configuration

Publish or create `config/dev.php` in your application:

```php title="config/dev.php"
<?php

declare(strict_types=1);

return [
    'port'      => 8000,
    'host'      => 'localhost',
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
| `host` | `string` | `'localhost'` | Host for the PHP built-in server: a hostname, an IPv4 address, or an IPv6 address (`::1` or `[::1]`) |
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
| `--host=HOST` | Override the server host (`::1` and `[::1]` are both accepted) |
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
/** @throws DevServerException */
$processManager->startDetached(string $name, string $command): int;
$processManager->stop(string $name): void;
$processManager->stopAll(): void;
$processManager->getPid(string $name): ?int;
$processManager->getPids(): array; // array<string, int>
$processManager->isRunning(string $name): bool;
$processManager->getExitCode(string $name): ?int;
$processManager->collectOutput(string $name): string;
$processManager->runForeground(): void;
$processManager->isPortAvailable(string $host, int $port): bool;
/** @throws DevServerException */
$processManager->waitUntilAccepting(string $name, string $host, int $port): bool;
```

`stop()` and `stopAll()` stop processes started with either `start()` or `startDetached()`. `stopAll()` signals the newest process first. Both return only once each process and its whole process group are gone. They send `SIGTERM` to the process and its group, wait up to the stop timeout, then send `SIGKILL`. If a group survives `SIGKILL` (for example, a process stuck in uninterruptible I/O), they throw a `DevServerException` naming the PID. Processes that did stop are released either way, and the survivors stay in `getPids()`. The stop timeout defaults to 3 seconds. It and the other timings are set through the constructor:

```php
use Marko\DevServer\Process\ProcessManager;

$processManager = new ProcessManager(
    output: $output,
    stopTimeoutSeconds: 10.0, // grace period between SIGTERM and SIGKILL
    startProbeSeconds: 1.0, // how long start()/startDetached() watch a new process for an early exit
    statusDirectory: '/tmp', // where startDetached() keeps its short-lived exit status files
    serverReadyTimeoutSeconds: 30.0, // how long waitUntilAccepting() waits for a connection
);
```

`start()` and `startDetached()` first look up the command's executable and throw a `DevServerException` if it is missing or not executable. Nothing is spawned in that case. They then watch the new process for `startProbeSeconds` (default 0.5 seconds):

- `start()` throws if the process exits with code 126 or 127 in that window, and returns as soon as the process exits.
- `startDetached()` throws if the process exits for any reason in that window, and includes the exit code in the message. Before it throws, it stops anything the command left running in its process group. A process that keeps running is only reported once the full window has passed.

`getExitCode()` returns the exit code of a process started with `start()`, or `null` while it is still running or when the name is unknown. `collectOutput()` returns the stdout and stderr the process has written that `runForeground()` has not streamed yet. Call it before `stop()`, which closes the pipes.

`statusDirectory` defaults to the system temp directory. `startDetached()` removes its status files when the probe window ends.

`isPortAvailable()` returns false when something already accepts connections on the host and port, or when binding it fails with "address already in use". Wildcard hosts (`0.0.0.0`, `::`) are probed through loopback. `waitUntilAccepting()` returns true as soon as a connection to the host and port succeeds, and false if the named process exits first. If neither happens within `serverReadyTimeoutSeconds` (default 10 seconds), it throws a `DevServerException`.

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
        public ?string $host = null,
    ) {}
}
```

`host` is set on the `php` entry (unbracketed for IPv6), and `dev:open` reads it. Entries from PID files written before the host was recorded read as `null`, and `dev:open` falls back to `localhost`.

### ServerHost

Validates and normalizes the PHP server host.

```php
use Marko\DevServer\Process\ServerHost;

$host = ServerHost::fromString('[::1]'); // throws DevServerException::invalidHost() for anything else
$host->address;      // '::1' — unbracketed, for isPortAvailable() and waitUntilAccepting()
$host->forUri();     // '[::1]' — for php -S and URLs
$host->forBrowser(); // '[::1]'; 'localhost' for 0.0.0.0 and ::

ServerHost::formatForUri('::1'); // '[::1]'
```

### DevServerException

Extends `MarkoException` with contextual error messages and suggestions:

- `processFailedToStart(string $name, string $command, ?string $reason = null)` --- thrown when a process fails to start. The reason (for example `Executable 'npx' was not found in PATH` or `exited with code 127`) is appended to the message. Suggests checking the command and running `marko status`.
- `processFailedToStop(string $name, int $pid)` --- thrown when a process group survives `SIGKILL`.
- `portInUse(int $port)` --- thrown when the PHP server port is already in use. Suggests using `--port=XXXX` to pick a different port.
- `serverNotReady(string $host, int $port, float $timeoutSeconds)` --- thrown when the PHP server neither accepts connections nor exits within the timeout.
- `serverExited(string $host, int $port, ?int $exitCode, string $output)` --- thrown in foreground mode when the PHP server exits before it accepts a connection and the port is still free. The message includes the exit code, and the context includes the server's output.
- `invalidHost(string $host)` --- thrown when `--host` or `dev.host` is not a hostname, an IPv4 address, or an IPv6 address with or without one pair of brackets.
- `rollbackFailed(Throwable $original, array $survivors)` --- thrown when `marko up` fails and some of the services it already started survive `SIGKILL`. Names each survivor and its PID, includes the `kill -9` command to stop them, and keeps `$original` as the previous exception.
