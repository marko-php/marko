---
title: marko/core
description: The foundation of Marko — provides dependency injection, modules, plugins, events, and preferences so you can extend any class without modifying its source.
---

The foundation of Marko — provides dependency injection, modules, plugins, events, and preferences so you can extend any class without modifying its source. Core gives you the extensibility primitives: replace any class with `#[Preference]`, modify any method with `#[Before]`/`#[After]` plugins, react to events with `#[Observer]`. Everything is a module, and modules are discovered automatically from `vendor/`, `modules/`, and `app/`.

## Installation

```bash
composer require marko/core
```

Note: Most applications install this via a metapackage or implementation package.

## Usage

### Replacing Classes with Preferences

Override any class globally without touching its source:

```php
use Marko\Core\Attributes\Preference;

#[Preference(replaces: OriginalService::class)]
class MyService extends OriginalService
{
    public function doSomething(): string
    {
        // Your implementation
        return 'custom behavior';
    }
}
```

Anywhere `OriginalService` is injected, `MyService` is provided instead.

### Modifying Methods with Plugins

Intercept method calls without replacing the whole class:

```php
use Marko\Core\Attributes\Plugin;
use Marko\Core\Attributes\Before;
use Marko\Core\Attributes\After;

#[Plugin(target: PaymentService::class)]
class PaymentValidationPlugin
{
    #[Before]
    public function charge(
        float $amount,
    ): null|array {
        // Modify input — return an array to replace the arguments
        return [$amount * 1.1]; // Add 10% fee
    }
}

#[Plugin(target: PaymentService::class)]
class PaymentAuditPlugin
{
    #[After]
    public function charge(
        Receipt $result,
    ): Receipt {
        // Modify output
        return $result->withTax();
    }
}
```

### Reacting to Events

Decouple "something happened" from "react to it":

```php
use Marko\Core\Attributes\Observer;
use Marko\Core\Event\Event;

#[Observer(event: UserCreatedEvent::class)]
class SendWelcomeEmail
{
    public function handle(
        UserCreatedEvent $event,
    ): void {
        $user = $event->user;
        // Send email...
    }
}
```

Dispatch events from anywhere:

```php
$this->eventDispatcher->dispatch(new UserCreatedEvent(user: $user));
```

### Creating Modules

Create a directory in `app/` with a `composer.json`:

```
app/
  mymodule/
    composer.json    # Required: name, autoload
    module.php       # Optional: enabled, sequence, bindings, singletons, boot, shutdown, globalMiddleware, discovery
    src/
      MyService.php
```

Modules are discovered automatically. Use `module.php` for bindings:

```php title="module.php"
return [
    'enabled' => true,
    'bindings' => [
        PaymentInterface::class => StripePayment::class,
    ],
];
```

The `boot` callback runs after all module bindings are registered. Parameters are auto-injected from the container — type-hint any registered dependency:

```php title="module.php"
return [
    'bindings' => [
        PaymentInterface::class => StripePayment::class,
    ],
    'boot' => function (ErrorHandlerInterface $handler): void {
        $handler->register();
    },
];
```

Boot callbacks run in module load order. A callback can't see what a module that boots later sets up in its own `boot` callback. To check the finished setup, observe `Marko\Core\Event\ApplicationBooted`. `Application` dispatches it once per boot, on live and cached boots, after every `boot` callback has run:

```php
use Marko\Core\Attributes\Observer;
use Marko\Core\Discovery\CachedDiscovery;
use Marko\Core\Event\ApplicationBooted;

#[Observer(event: ApplicationBooted::class)]
readonly class CheckWidgetSetup
{
    public function __construct(
        private CachedDiscovery $cachedDiscovery,
    ) {}

    public function handle(
        ApplicationBooted $event,
    ): void {
        if ($this->cachedDiscovery->isCached()) {
            return; // checked when the discovery cache was compiled
        }

        // validate, and throw a MarkoException naming what is missing
    }
}
```

Under PHP-FPM every request is a boot, so keep these observers cheap. `marko/authorization` uses this event to [check `#[Can]` routes at boot](/docs/packages/authorization/#cost-on-routes-without-can).

#### Shutting down

A `boot` callback that changes process-wide state (pushes a global error or exception handler, opens a resource) has a matching `shutdown` callback that undoes it. `Application::shutdown()` runs every module's `shutdown` callback in reverse module load order, so handlers come off PHP's stacks in the reverse order they went on. Parameters are auto-injected like `boot`'s:

```php title="module.php"
return [
    'singletons' => [
        ErrorHandlerInterface::class, // shared, so shutdown gets the instance boot registered
    ],
    'boot' => function (ErrorHandlerInterface $handler): void {
        $handler->register();
    },
    'shutdown' => function (ErrorHandlerInterface $handler): void {
        $handler->unregister();
    },
];
```

Call `$app->shutdown()` when the application is done but the PHP process is not. [marko/testing](/docs/packages/testing/#shutdown-after-each-test) does it after every test that booted an application, so the test leaves PHP's error and exception handlers as it found them. A long-running worker can call it before it exits or boots a fresh application. Only the first call does anything. It also removes the [bootstrap error handler](#errors-during-boot) if a failed boot left it installed. Under PHP-FPM there is nothing to call: the process ends with the request.

### Application Environment

`AppEnvironment` is the single answer to "which environment is this application running in?". `Application` registers one shared instance in the container at boot, so any class or boot callback can type-hint it:

```php
use Marko\Core\Environment\AppEnvironment;

class ReportMailer
{
    public function __construct(
        private AppEnvironment $appEnvironment,
    ) {}

    public function recipients(): array
    {
        return $this->appEnvironment->isProduction()
            ? ['finance@example.com']
            : ['dev@example.com'];
    }
}
```

It reads `MARKO_ENV` first, then `APP_ENV`, from `$_ENV` with a `getenv()` fallback (through [`EnvironmentVariables`](#environmentvariables)), so it works whether or not [marko/env](/docs/packages/env/) is installed and regardless of PHP's `variables_order` setting. Values are compared case-insensitively:

| Method | Returns `true` for |
|---|---|
| `isProduction()` | `production`, `prod`, or no value at all |
| `isDevelopment()` | `development`, `dev`, `local` |
| `isTesting()` | `testing`, `test` |

Any other name (for example `staging`) is none of these. Code that should only act on a disposable environment checks for one by name rather than for the absence of production: [marko/database](/docs/packages/database/#environment-behaviour)'s destructive commands run freely only when `isDevelopment()` or `isTesting()` is true. `name()` returns the trimmed, lowercased value, or `production` when neither variable is set (or both are empty). Defaulting to production means a deployment that forgets to set the environment fails safe instead of exposing development behavior.

### Errors During Boot

An errors module ([marko/errors-simple](/docs/packages/errors-simple/) or [marko/errors-advanced](/docs/packages/errors-advanced/)) registers its handler from its boot callback, which runs near the end of boot. To cover everything before that (`.env` loading, discovery, `module.php` files, earlier boot callbacks), `Application::initialize()` first installs `Marko\Core\Error\BootstrapErrorHandler`:

- Outside development (including an unset environment) it sets `display_errors` to `0` and answers an uncaught exception with a generic `500`: `Server Error` as plain text, or `{"message":"Server Error"}` when the request's `Accept` header asks for JSON. The full exception, stack trace included, goes to `error_log()`.
- In development (`development`, `dev`, `local`, including when set only in `.env`) it shows the exception's class and message, escaped.
- The process exits with status `255`, so a failed boot in a CLI command or deploy script is still a failure.

The errors module's boot callback removes the bootstrap handler before registering its own, so the two never stack. Without an errors module, `initialize()` removes it once boot succeeds. When boot fails, the handler stays installed so it can handle the uncaught exception. A test that expects `initialize()` to throw and catches the exception removes it with `$app->bootstrapErrorHandler->unregister()`.

### Environment-Specific Bindings

When different environments need different implementations (e.g., a mock service in development vs the real one in production), use the `boot` callback to conditionally override bindings:

```php title="module.php"
use Marko\Core\Container\Container;
use Marko\Core\Environment\AppEnvironment;

return [
    'bindings' => [
        // Default binding — used in all environments
        PaymentGatewayInterface::class => StripePaymentGateway::class,
    ],
    'boot' => function (Container $container, AppEnvironment $appEnvironment): void {
        if ($appEnvironment->isDevelopment()) {
            $container->bind(
                PaymentGatewayInterface::class,
                MockPaymentGateway::class,
            );
        }
    },
];
```

Since boot callbacks run after all static bindings are registered, `$container->bind()` in a boot callback overrides the static binding from the same module. The override is explicit and visible in the module's own `module.php`.

**Use boot callbacks when** you need a completely different implementation class per environment (mock vs real).

**Use config instead when** the difference is just values (API URLs, credentials, feature flags). Keep the same class everywhere and let [config](/docs/packages/config/) drive the behavior:

```php title="config/payments.php"
use Marko\Config\Env;

return [
    'gateway_url' => Env::string('PAYMENT_GATEWAY_URL', 'https://sandbox.stripe.com'),
    'dry_run' => Env::bool('PAYMENT_DRY_RUN', true),
];
```

### Resetting Request-Scoped State in Long-Running Processes

PHP-FPM ends the process after every request, so any state a singleton accumulates disappears automatically. A long-running process --- a worker or event loop that reuses one PHP process across many requests --- has no such reset, so a singleton that caches per-request state (the current session, the authenticated user, a sticky database routing flag) leaks across requests once the process picks up a different user.

Implement `ResettableInterface` on any singleton that holds this kind of state:

```php
use Marko\Core\Contracts\ResettableInterface;

class RequestScopedCache implements ResettableInterface
{
    private array $entries = [];

    public function remember(string $key, mixed $value): void
    {
        $this->entries[$key] = $value;
    }

    public function reset(): void
    {
        $this->entries = [];
    }
}
```

`reset()` must be non-destructive --- it clears the instance's in-memory tracking, not anything persisted. Resetting a session service forgets which session the instance was serving; it does not delete the stored session.

A long-running process discovers what to reset via `Container::resolvedInstances()`, which returns only instances the container has already built --- never triggering resolution --- optionally filtered to those implementing an interface:

```php
use Marko\Core\Contracts\ResettableInterface;

foreach ($container->resolvedInstances(ResettableInterface::class) as $resettable) {
    $resettable->reset();
}
```

`Marko\Core\RequestStateResetter` wraps that loop: `new RequestStateResetter($container)->reset()` resets every resolved `ResettableInterface` instance in ascending binding-id order, and lets a failing `reset()` propagate. Pass instances to `reset(ResettableInterface ...$except)` to leave them alone (matched by identity); the testing database helpers use this to keep a test transaction open. The RoadRunner worker and the [marko/testing](/docs/packages/testing/) HTTP test client both call it before each request.

`resolvedInstances()` is declared on `ContainerInterface` and implemented by `Container`.

Current implementors: `Session` ([marko/session](/docs/packages/session/)), `SessionGuard` ([marko/authentication](/docs/packages/authentication/)), and `ReadWriteConnection` ([marko/database-readwrite](/docs/packages/database-readwrite/)).

### Registering Console Commands

Mark a class implementing `CommandInterface` with `#[Command]` and it is discovered automatically. Declare value-less boolean options in `flags` so they never swallow the positional argument that follows them:

```php title="app/billing/src/Command/RefundCommand.php"
use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;

#[Command(name: 'billing:refund', description: 'Refund an order', flags: ['force'])]
class RefundCommand implements CommandInterface
{
    public function execute(Input $input, Output $output): int
    {
        $orderId = $input->getArgument(0);           // positional --- options are never included
        $reason = $input->getOption('reason');       // --reason "late" or --reason=late
        $force = $input->hasOption('force');         // declared flag

        // ...

        return 0;
    }
}
```

`marko billing:refund --force 1001 --reason late` and `marko billing:refund 1001 --reason=late --force` are equivalent. Arguments and options can come in any order, `--` ends option parsing, and repeated options are read with `getOptionValues()`. See [marko/cli](/docs/packages/cli/#arguments-and-options) for the full option syntax.

The running command's `Input` and `Output` are also registered in the container, so a console service the command depends on reads the same options and writes to the same stream.

To report a warning without mixing it into the command's regular output, inject `ErrorOutput`. It is an `Output` that writes to STDERR (`new ErrorOutput($stream)` writes elsewhere, which is how tests capture it), so the warning stays visible when STDOUT is piped or captured by a deploy script.

### Asking for Confirmation

Inject `ConfirmationPrompterInterface` to ask the person running the command a yes/no question. The prompter writes the question with a `[y/N]` or `[Y/n]` hint through the command's `Output` and reads the answer from standard input:

```php title="app/billing/src/Command/PurgeCommand.php"
use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\ConfirmationPrompterInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;

#[Command(name: 'billing:purge', description: 'Delete archived invoices', flags: ['force'], destructive: true)]
class PurgeCommand implements CommandInterface
{
    public function __construct(
        private ConfirmationPrompterInterface $confirmationPrompter,
    ) {}

    public function execute(Input $input, Output $output): int
    {
        if (!$input->hasOption('force')) {
            if (!$this->confirmationPrompter->isInteractive()) {
                $output->writeLine('Error: Refusing to purge without confirmation. Re-run with --force.');

                return 1;
            }

            if (!$this->confirmationPrompter->confirm('Delete all archived invoices?')) {
                return 0;
            }
        }

        // ...

        return 0;
    }
}
```

| Answer | Result |
|--------|--------|
| `y`, `yes` (any case) | `true` |
| `n`, `no` (any case) | `false` |
| Empty, end of input, anything else | `$default` (`false` unless you pass `default: true`) |

**`--no-interaction`** is accepted by every command; you don't declare it. When it is passed, `isInteractive()` is `false`, `$input->isInteractive()` is `false`, and `confirm()` asks nothing and returns `$default`. `isInteractive()` is also `false` when standard input is not a terminal (CI, a pipe), although `confirm()` still reads a piped answer such as `echo y | marko billing:purge`.

A command that must not go ahead without a person (a destructive action) checks `isInteractive()` first and refuses loudly with a flag such as `--force`, as above. Mark it `destructive: true` on `#[Command]` too, so callers that run commands for someone else, such as the MCP `run_console_command` tool, refuse it unless explicitly allowed (see [Destructive Commands](/docs/packages/cli/#destructive-commands)). Relying on the default is for optional offers that are safe to skip.

`StdinConfirmationPrompter` is the default implementation, bound by `Application`. To replace it, bind `ConfirmationPrompterInterface` in your module's `module.php`. In tests, use [`FakeConfirmationPrompter`](/docs/packages/testing/#fakeconfirmationprompter) from `marko/testing`.

### Pausing the Current Process

Code that has to wait, such as a cache lock's `block()` retries or the backoff between `transaction()` retries in `marko/database`, sleeps through `Marko\Core\Sleep\SleeperInterface` instead of calling `usleep()` itself. Inject it the same way when your own code waits:

```php title="app/billing/src/Service/GatewayPoller.php"
use Marko\Core\Sleep\SleeperInterface;

class GatewayPoller
{
    public function __construct(
        private SleeperInterface $sleeper,
    ) {}

    public function waitBeforeRetry(): void
    {
        $this->sleeper->sleep(200); // milliseconds; 0 or less returns at once
    }
}
```

`Marko\Core\Sleep\UsleepSleeper` is the default implementation, bound by `Application`; it blocks the process with `usleep()`. To replace it, bind `SleeperInterface` in your module's `module.php`. In tests, use [`FakeSleeper`](/docs/packages/testing/#fakesleeper) from `marko/testing`, which records each delay instead of sleeping.

### Discovery Cache

Without a cache, every boot discovers the application from scratch: it scans every `vendor/*/*` package and parses its `composer.json` to find modules, sorts them by dependency, scans every module PHP file for `#[Preference]`, `#[Plugin]`, `#[Observer]`, `#[Command]` and route attributes, resolves global middleware, and (with `marko/database`) scans for entities. Boot cost grows with every class in the app. In production all of this is compiled once into a single PHP file --- the discovery cache --- so a request skips every scan.

The cache holds:

- the resolved module list, in load order (the `composer.json` fields only --- each `module.php` is still loaded, so its bindings and `boot` closures stay live)
- preferences, plugins, observers and commands
- the global middleware order
- one section per [discovery contributor](#adding-a-section-to-the-discovery-cache): routes from [`marko/routing`](/docs/packages/routing/#route-cache), entities from [`marko/database`](/docs/packages/database/#entity-discovery-cache), the merged config from [`marko/config`](/docs/packages/config/#config-caching)
- a fingerprint of the installed packages, module directories and root config files, so a stale cache fails loudly

On a cached boot no `vendor/` directory is scanned, no `composer.json` is parsed and no PHP file is tokenized. Controllers and other classes load only when a request needs them.

#### Compiling the cache

```bash
marko discovery:cache
```

Scans all modules, writes the compiled cache, and reports per-section counts:

```
Discovery cache compiled successfully.
Cache path: /var/www/html/storage/cache/discovery.php
preferences: 4
plugins: 12
observers: 7
commands: 9
modules: 31
global middleware: 3
sections: routes (48), entities (12)
skipped files: 1
  /var/www/html/app/blog/src/Plugin/SearchPlugin.php (App\Blog\Plugin\SearchPlugin): missing Marko\Search\SearchInterface (marko/search)
```

`discovery:cache` and `discovery:clear` always boot from live discovery, so they work even when the existing cache is stale, corrupt or from an older version.

#### Skipped files

Discovery skips a class file that references a class from a Marko package that is not installed, so a module can ship an integration with an optional package without a hard dependency on it. A skip is never silent:

- `discovery:cache` lists every skipped file, the class it declares, and the missing class and package.
- Outside `production`, each skip is written once to the PHP error log, e.g. `[marko] Discovery skipped App\Blog\Plugin\SearchPlugin (...): it references Marko\Search\SearchInterface, which is not available. Install marko/search if this class should be active, or fix the reference if it is a typo.`
- `Marko\Core\Discovery\DiscoverySkips::all()` returns the skips recorded in the current process, and `ClassFileParser::skippedFiles()` returns the ones a single parser recorded.

Check this list when a Preference or Plugin seems to have no effect: a typo in a `Marko\*` class name or a missing dependency drops the class and leaves the original implementation in place. A missing class outside the `Marko\` namespace is not skipped; it throws.

#### Clearing the cache

```bash
marko discovery:clear
```

Deletes the compiled cache file. Idempotent --- safe to run when no cache exists.

#### How boot uses the cache

At boot, `Application::initialize()` reads these environment variables directly from `$_ENV`, falling back to `getenv()` (not via `marko/config`, so the gate works before any config package is loaded):

| Variable | Default | Description |
|---|---|---|
| `MARKO_ENV` / `APP_ENV` | `production` | Application environment, read through [`AppEnvironment`](#application-environment). `development`, `dev`, or `local` disable the cache. `MARKO_ENV` wins when both are set. |
| `DISCOVERY_CACHE_ENABLED` | `true` | `1`, `true`, `yes`, `on` enable; `0`, `false`, `no`, `off`, or empty disable. Case-insensitive and trimmed. Any other value (`enabled`, `ture`) throws `DiscoveryCacheException`. |
| `DISCOVERY_CACHE_PATH` | `storage/cache/discovery.php` | Path to the cache file. Relative paths resolve from the project root; absolute paths are used as-is. The directory must not be shared (see [Cache file security](#cache-file-security)). |

The cache is used when **all three conditions** are true:

1. `DISCOVERY_CACHE_ENABLED` is enabled
2. `AppEnvironment::isDevelopment()` is false (the environment is not `development`, `dev`, or `local`)
3. The cache file exists at `DISCOVERY_CACHE_PATH`

If the cache file is **missing**, boot falls back to a normal full rescan --- no error.

If the cache file is **corrupt, malformed, version-mismatched or stale**, boot throws `DiscoveryCacheException` immediately. There is no silent fallback and the cache is never rebuilt behind your back. Run `marko discovery:cache` to rebuild it, or `marko discovery:clear` to go back to live discovery.

#### Cache file security

The cache file is executable PHP that boot loads with `include`, so whoever can write it can run code in your application. Keep it in a directory that only the application user can write to. The default `storage/cache/` is fine; a shared directory such as `/tmp` is not.

Before including the file, boot checks the file and the directory that holds it. It throws `DiscoveryCacheException` without running the file if either one is:

- **world-writable**, or
- **owned by another user**: the owner must be the user running PHP, or root. This check is skipped on Windows and when the `posix` extension is not loaded.

Run `marko discovery:cache` as the same user that serves the application (for example `sudo -u www-data vendor/bin/marko discovery:cache`), and make sure that user (or root) owns the cache directory. Otherwise the ownership check refuses the file. Temp files written during `discovery:cache` get random names so other users cannot guess them.

In a **development** environment (`development`, `dev`, or `local` --- the skeleton ships `APP_ENV=local`) the cache is always bypassed, so adding or editing a module, route, `#[Plugin]`, `#[Observer]`, `#[Preference]`, or `#[Command]` takes effect on the next request without any manual step.

#### Stale cache detection

The cache stores a fingerprint of:

- the contents of `vendor/composer/installed.json` --- any `composer install`, `update`, `require` or `remove` changes it
- every directory holding a `composer.json` under `modules/` (recursively, stopping at a module) and `app/` (one level), with a hash of that `composer.json`
- every `config/*.php` file in the project root, with a hash of its contents

Each boot recomputes the fingerprint (file hashes plus a directory listing of `modules/`, `app/` and `config/`) and throws a stale `DiscoveryCacheException` when it differs. Each module's `module.php` is loaded on every boot anyway, so a module whose `module.php` now disables it, or changes its `sequence` or `globalMiddleware`, is also reported as stale.

The fingerprint does not cover the PHP files inside a module: a new route, plugin, observer, preference, command, entity or config file in an existing module under `modules/` or `app/` is only picked up by recompiling. Enabling a module that was disabled when the cache was compiled is not detected either.

#### Deploying to production

`marko discovery:cache` is a required deploy step. Run it after installing dependencies, every time:

```bash
composer install --no-dev --optimize-autoloader
marko discovery:cache
```

With [`marko/config`](/docs/packages/config/#config-caching) installed, this also compiles your config, so a cached boot reads no config files. Config values, including those read from environment variables through `Env`, are fixed when the cache is built: change an environment variable and boot throws `DiscoveryCacheException` naming it until you run `discovery:cache` again. That includes the variables behind `config/discovery.php` (`MARKO_ENV`, `APP_ENV`, `DISCOVERY_CACHE_ENABLED`, `DISCOVERY_CACHE_PATH`), so a changed `APP_ENV` fails loudly instead of leaving `discovery.environment` stale. `.env` is still loaded on every boot, because the boot gate reads `APP_ENV` and `DISCOVERY_CACHE_ENABLED` from it before the cache is loaded.

The cache file holds resolved config, including secrets, so `discovery:cache` writes it with mode `0600` (readable and writable by its owner only).

Recompiling on every deploy covers new code in existing modules (which the fingerprint cannot see) and framework upgrades that change the cache format: a cache written by an older version fails boot with a version-mismatch `DiscoveryCacheException` rather than loading incomplete data. Module paths are stored relative to the project root, so a cache compiled during a build step stays valid when the build is moved to its final location.

Under a long-running worker such as [`marko/roadrunner`](/docs/packages/roadrunner/) the application boots once per worker, so the cache only shortens worker start-up.

Choose log handlers for the environment before you build the cache: `stream` for containers and PaaS, `file` for servers you SSH into --- see [Logging in Production](/docs/guides/logging/#logging-in-production).

With a view driver installed, run `marko view:cache` after `discovery:cache` and set `view.auto_refresh` to `false` in production --- see [deploying views to production](/docs/packages/view/#deploying-to-production).

#### Adding a section to the discovery cache

A package that runs its own discovery at boot can store the result in the cache. Implement `DiscoveryCacheContributorInterface` and declare the class under the `discovery` key of `module.php`:

```php title="src/Discovery/WidgetCacheContributor.php"
use Acme\Widgets\WidgetScanner;
use Marko\Core\Discovery\DiscoveryCacheContributorInterface;

class WidgetCacheContributor implements DiscoveryCacheContributorInterface
{
    public function __construct(
        private WidgetScanner $widgetScanner,
    ) {}

    public function key(): string
    {
        return 'widgets';
    }

    public function compile(array $modules): array
    {
        return $this->widgetScanner->scan($modules); // scalars, null and arrays only
    }
}
```

```php title="module.php"
use Acme\Widgets\Discovery\WidgetCacheContributor;
use Acme\Widgets\WidgetRegistry;
use Acme\Widgets\WidgetScanner;
use Marko\Core\Discovery\CachedDiscovery;

return [
    'discovery' => [WidgetCacheContributor::class],
    'boot' => function (CachedDiscovery $cachedDiscovery, WidgetScanner $widgetScanner, WidgetRegistry $widgetRegistry): void {
        $widgets = $cachedDiscovery->section('widgets') ?? $widgetScanner->scan(/* ... */);
        $widgetRegistry->register($widgets);
    },
];
```

`discovery:cache` resolves each contributor through the container and stores its result under `key()`. At boot, `Marko\Core\Discovery\CachedDiscovery::section()` returns `null` when the boot did not use the cache (run your own discovery) and the stored array when it did. A contributor class that does not exist or does not implement the interface, two contributors with the same key, or data that `var_export()` cannot write as plain arrays (objects, closures) fails `discovery:cache` with a `DiscoveryCacheException`.

#### Configuration via `marko/config`

When `marko/config` is installed, the same keys are available as a config file:

```php title="config/discovery.php"
use Marko\Core\Discovery\DiscoveryEnvironment;

$discoveryEnvironment = new DiscoveryEnvironment();

return [
    'enabled' => $discoveryEnvironment->enabled(),
    'environment' => $discoveryEnvironment->environment(),
    'cache_path' => $discoveryEnvironment->cachePath(),
];
```

The core-owned `config/discovery.php` is shipped with `marko/core` and mirrors the boot gate by delegating to `DiscoveryEnvironment`, the single parser for these variables. `enabled` follows `DISCOVERY_CACHE_ENABLED` with the strict values above, `environment` follows `MARKO_ENV` then `APP_ENV` (lowercased, default `production`), and `cache_path` follows `DISCOVERY_CACHE_PATH`. An invalid `DISCOVERY_CACHE_ENABLED` value fails here the same way it fails at boot. The boot gate reads `DiscoveryEnvironment` directly and does not depend on `marko/config`. `DiscoveryEnvironment` reads through [`EnvironmentVariables`](#environmentvariables), so when `discovery:cache` compiles your config, the variables it read are recorded with the rest and changing one afterwards fails the next cached boot, naming the variable (never its value).

#### Not the same as the code index

Marko has **two separate caches** that are easy to confuse --- different files, different commands, different consumers:

| Cache | File | Built by | Consumed by | Rebuild when |
|---|---|---|---|---|
| **Discovery cache** | `storage/cache/discovery.php` | `marko discovery:cache` | the **running app** at boot | every deploy to a non-`development` environment |
| **Code index** | `.marko/index.cache` | `marko indexer:rebuild` | **MCP / LSP tooling** ([`marko/codeindexer`](/docs/packages/codeindexer/)) | the AI tools show stale or missing symbols |

The discovery cache makes the **app** boot faster in production. The code index lets **AI tooling** answer questions about your code. Rebuilding one has no effect on the other.


### Throwing Rich Exceptions

Include context and fix suggestions:

```php
use Marko\Core\Exceptions\MarkoException;

throw new MarkoException(
    message: 'Payment failed',
    context: 'Processing order #12345',
    suggestion: 'Check that the API key is configured in .env',
);
```

### Unsupported Features

A driver that cannot do what its interface declares throws `UnsupportedFeatureException::forDriver()`, or a package subclass that also implements `UnsupportedFeatureExceptionInterface`. Both expose `getFeature()`, `getDriver()`, `getContext()` and `getSuggestion()`:

```php
use Marko\Core\Exceptions\UnsupportedFeatureException;

throw UnsupportedFeatureException::forDriver(
    feature: 'Object containment in whereJsonContains()',
    driver: 'SQLite',
    suggestion: 'Use a scalar or list needle',
);
```

See [Modularity](/docs/concepts/modularity/#unsupported-features) for the rules.

### Capturing PHP Warning Reasons

Many PHP functions (`mkdir()`, `rename()`, `file_put_contents()`, `stream_socket_client()`, `unserialize()`) report failure by returning `false` and raising a warning that holds the only explanation. Don't hide that warning with `@`. Run the call through `Marko\Core\Support\ErrorCapture::run()`, which captures the message instead of emitting it, and put the reason into your exception:

```php
use Marko\Core\Support\ErrorCapture;

if (!ErrorCapture::run($reason, fn (): bool => rename($tmp, $path))) {
    throw ExportException::notWritable($path, $reason); // e.g. "rename(...): Permission denied"
}
```

`run()` returns the call's result and sets `$reason` to the message, or to `null` when nothing was raised. When the call raises several messages, they're joined with `; `. It always restores the previous error handler, even if the call throws. It captures every error level, so wrap only the one call that can fail.

### Resolving Paths Against the Project Root

Resolve a configured path with `ProjectPaths::resolve()`, never against `getcwd()`: FPM, CGI and mod_php `chdir` into `public/` before running the front controller. Absolute paths come back unchanged; relative paths are joined onto the project root.

```php
use Marko\Core\Path\ProjectPaths;

$paths->resolve('storage/views');   // "/srv/app/storage/views"
$paths->resolve('/var/cache/views'); // unchanged
$paths->resolve('C:\\cache\\views');  // unchanged

ProjectPaths::isAbsolute('\\\\server\\share'); // true
```

`isAbsolute()` recognizes Unix paths (`/var/cache`), Windows drive paths (`C:\cache` or `C:/cache`) and UNC paths (`\\server\share`) on every OS. A drive-relative path such as `C:cache` is not absolute.

Every framework setting that takes a filesystem path resolves it this way, including `DISCOVERY_CACHE_PATH`, the session, debugbar, page-cache and queue restart-signal paths, the compiled-template cache directory and `rr:serve --config`.

## API Reference

### Attributes

```php
#[Preference(replaces: ClassName::class)]      // Replace a class globally
#[Plugin(target: ClassName::class)]            // Mark class as plugin
#[Before]                                       // Run before target method
#[After]                                        // Run after target method
#[Observer(event: EventClass::class)]           // React to events (synchronous)
#[Observer(event: EventClass::class, async: true)] // Queue it (needs marko/queue; throws EventException without it)
#[Command(name: 'cmd:name', description: '', aliases: [], flags: [])] // Register CLI command; flags never take a value
#[Command(name: 'cmd:wipe', destructive: true)]  // Changes or deletes stored state (MCP refuses it by default)
```

### Container

```php
interface ContainerInterface extends PsrContainerInterface
{
    public function get(string $id): mixed;
    public function has(string $id): bool;
    public function singleton(string $id): void;
    public function instance(string $id, object $instance): void;
    public function call(Closure $callable): mixed;
}
```

`has()` returns `true` for existing classes, interface bindings and instances registered with `instance()`, so it works for an interface that only has a pre-built instance and no binding.

The concrete `Container` class additionally provides `resolvedInstances(?string $interface = null): array` --- not part of `ContainerInterface`. It returns only instances already built, optionally filtered to those implementing `$interface`, and never triggers resolution as a side effect. See [Resetting Request-Scoped State](#resetting-request-scoped-state-in-long-running-processes) above.

### Console

```php
interface ConfirmationPrompterInterface
{
    public function isInteractive(): bool;
    public function confirm(string $question, bool $default = false): bool;
}

readonly class Input
{
    public const string NO_INTERACTION = 'no-interaction';

    public function isInteractive(): bool; // false when --no-interaction is passed
    // getCommand(), getArguments(), getArgument(), hasOption(), getOption(), getOptionValues(), withFlags()
}
```

`StdinConfirmationPrompter(Input $input, Output $output, mixed $stream = null)` is the default binding for `ConfirmationPrompterInterface`. See [Asking for Confirmation](#asking-for-confirmation) above.

### AppEnvironment

```php
use Marko\Core\Environment\AppEnvironment;

class AppEnvironment
{
    public function __construct(?array $variables = null);

    public function name(): string;
    public function isProduction(): bool;
    public function isDevelopment(): bool;
    public function isTesting(): bool;
}
```

Registered as a shared container instance by `Application`. Pass `$variables` (for example `['APP_ENV' => 'local']`) to read from that array only, instead of the real environment --- useful in tests. See [Application Environment](#application-environment) above.

### ProjectPaths

```php
use Marko\Core\Path\ProjectPaths;

readonly class ProjectPaths
{
    public string $base;
    public string $vendor;
    public string $modules;
    public string $app;
    public string $config;
    public string $database;

    public function __construct(?string $basePath = null);

    public function resolve(string $path): string;
    public static function isAbsolute(string $path): bool;
}
```

`$base` defaults to `getcwd()`. See [Resolving Paths Against the Project Root](#resolving-paths-against-the-project-root) above.

### EnvironmentVariables

```php
use Marko\Core\Environment\EnvironmentVariables;

class EnvironmentVariables
{
    public static function read(string $name): ?string;
    public static function record(string $name): void;
    public static function startRecording(): void;
    public static function stopRecording(): array;
    public static function isRecording(): bool;
    public static function valueHashes(array $names): array;
}
```

The single raw reader for environment variables: `read()` returns the `$_ENV` value (when scalar), then the `getenv()` value, or `null` when the variable is unset. `AppEnvironment` and `DiscoveryEnvironment` read through it, and [`Env`](/docs/packages/config/#environment-variables) reports its reads with `record()`. Between `startRecording()` and `stopRecording()` every name read is recorded; `stopRecording()` returns them once each, in first-read order. Starting twice, or stopping without starting, throws `EnvironmentException`. `valueHashes()` returns a hash of each variable's current value keyed by name (an empty value hashes differently from an unset one) and is not recorded. The [config cache](/docs/packages/config/#config-caching) uses these to detect environment changes on a cached boot. The methods are static because the readers are created inside config files, before the container exists.

### Contracts

```php
interface ResettableInterface
{
    public function reset(): void;
}
```

Implemented by services that hold request-scoped state which must be cleared between requests in a long-running process. See [Resetting Request-Scoped State](#resetting-request-scoped-state-in-long-running-processes) above.

```php
interface UploadedFileInterface
{
    public function clientFilename(): string;
    public function clientMediaType(): string;
    public function size(): int;
    public function error(): int;
    public function isValid(): bool;
    public function isMoved(): bool;
    public function moveTo(string $targetPath): void;
    public function stream(): mixed; // resource
    public function contents(): string;
    public function mimeType(): string;
    public function guessExtension(): ?string;
}
```

A file uploaded with a request. [`marko/routing`](/docs/packages/routing/#uploadedfile) implements it as `Marko\Routing\Http\UploadedFile`; packages that only inspect uploads, such as the [file rules in `marko/validation`](/docs/packages/validation/#validating-file-uploads), depend on this contract instead of on routing. Methods that read or move the file throw a `MarkoException` subclass when the upload failed or was moved.

```php
interface SleeperInterface
{
    public function sleep(int $milliseconds): void;
}
```

`Marko\Core\Sleep\SleeperInterface` pauses the current process. `Application` binds it to `Marko\Core\Sleep\UsleepSleeper`. See [Pausing the Current Process](#pausing-the-current-process) above.

### Events

```php
interface EventDispatcherInterface
{
    public function dispatch(Event $event): void;
}
```

`Marko\Core\Event\ApplicationBooted` is the one event core dispatches itself: once per boot, after every module `boot` callback. It carries no data. See [Creating Modules](#creating-modules).

### MarkoException

```php
class MarkoException extends Exception
{
    public function __construct(
        string $message,
        string $context = '',
        string $suggestion = '',
    );

    public function getContext(): string;
    public function getSuggestion(): string;

    // Install hints: the package a Marko class name most likely comes from
    public static function inferPackageName(string $className): ?string;     // Marko\Cache\Redis\Driver\RedisCacheDriver → marko/cache-redis
    public static function inferBasePackageName(string $className): ?string; // Marko\Cache\Redis\Driver\RedisCacheDriver → marko/cache
}
```

### BindingException

```php
use Marko\Core\Exceptions\BindingException;
```

Thrown by the container when it can't build what you asked for: an interface with no binding, a constructor parameter it can't autowire, or a type that doesn't exist at all. The last case usually means an optional package isn't installed. For example, resolving a class whose constructor takes `Marko\Cache\Contracts\CacheInterface` without `marko/cache` installed throws `Cannot resolve 'Marko\Cache\Contracts\CacheInterface': no class or interface with that name exists`, naming the class that needed it and suggesting `composer require marko/cache`. You never get a bare `ReflectionException`.

Implements `Psr\Container\ContainerExceptionInterface`.

### CircularDependencyException

```php
use Marko\Core\Exceptions\CircularDependencyException;
```

Thrown by the container when a mutual constructor dependency cycle is detected (e.g. class A requires class B which requires class A). Rather than exhausting the call stack, the container detects the cycle and throws immediately with a human-readable chain (`A -> B -> A`) and a suggestion to remove the circular reference.

Implements `Psr\Container\ContainerExceptionInterface`.

### DiscoveryCacheException

```php
use Marko\Core\Exceptions\DiscoveryCacheException;
```

Thrown by `Application::initialize()` when the discovery cache file is corrupt, structurally invalid, stale, or carries a version number that does not match the running core version, and by `discovery:cache` when a contributor is invalid. There is no silent fallback --- a bad cache is a loud error.

Named constructors:

| Method | When thrown |
|---|---|
| `DiscoveryCacheException::unreadable($path)` | Cache file exists but cannot be read |
| `DiscoveryCacheException::malformed($path, $reason)` | Cache file structure is invalid or missing required keys |
| `DiscoveryCacheException::versionMismatch($path, $found, $expected)` | Cache was compiled by a different core version |
| `DiscoveryCacheException::stale($path, $reason)` | Installed packages, module directories or a module's `module.php` changed since the cache was compiled |
| `DiscoveryCacheException::missingSection($path, $key)` | A cached boot asked for a contributor section the cache does not hold |
| `DiscoveryCacheException::malformedSection($key, $reason)` | A contributor section has the wrong shape when hydrated |
| `DiscoveryCacheException::invalidContributor($module, $class, $reason)` | A `discovery` entry in `module.php` is not an existing `DiscoveryCacheContributorInterface` (thrown by `discovery:cache`) |
| `DiscoveryCacheException::duplicateContributorKey($key, $first, $second)` | Two contributors use the same `key()` (thrown by `discovery:cache`) |
| `DiscoveryCacheException::unexportableSection($key, $class, $reason)` | A contributor returned objects or closures (thrown by `discovery:cache`) |
| `DiscoveryCacheException::notWritable($path, $reason)` | Cache directory or file is not writable (thrown by `discovery:cache`); the context carries the operating system's reason, such as `Permission denied` |

Fix for a bad cache file: `marko discovery:cache` (or `marko discovery:clear` to fall back to live discovery). Both commands boot without the cache, so they work while it is broken.

### EnvironmentException

```php
use Marko\Core\Exceptions\EnvironmentException;
```

Thrown by [`EnvironmentVariables`](#environmentvariables): `EnvironmentException::alreadyRecording()` when `startRecording()` is called during a recording, and `EnvironmentException::notRecording()` when `stopRecording()` is called without one.
