# Marko Core

The foundation of Marko—provides dependency injection, modules, plugins, events, and preferences so you can extend any class without modifying its source.

## Overview

Core gives you the extensibility primitives: replace any class with `#[Preference]`, modify any method with `#[Before]`/`#[After]` plugins, react to events with `#[Observer]`. Everything is a module, and modules are discovered automatically from `vendor/`, `modules/`, and `app/`.

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
class PaymentPlugin
{
    #[Before]
    public function beforeCharge(
        float $amount,
    ): ?float {
        // Modify input or return early
        return $amount * 1.1; // Add 10% fee
    }

    #[After]
    public function afterCharge(
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

#[Observer(event: 'user.created')]
class SendWelcomeEmail
{
    public function handle(
        Event $event,
    ): void {
        $user = $event->data['user'];
        // Send email...
    }
}
```

Dispatch events from anywhere:

```php
$this->eventDispatcher->dispatch('user.created', ['user' => $user]);
```

### Creating Modules

Create a directory in `app/` with a `composer.json`:

```
app/
  mymodule/
    composer.json    # Required: name, autoload
    module.php       # Optional: enabled, bindings
    src/
      MyService.php
```

Modules are discovered automatically. Use `module.php` for bindings:

```php
return [
    'enabled' => true,
    'bindings' => [
        PaymentInterface::class => StripePayment::class,
    ],
];
```

The `boot` callback runs after all module bindings are registered. Parameters are auto-injected from the container — type-hint any registered dependency:

```php
return [
    'bindings' => [
        PaymentInterface::class => StripePayment::class,
    ],
    'boot' => function (ErrorHandlerInterface $handler): void {
        $handler->register();
    },
];
```

### Environment-Specific Bindings

When different environments need different implementations (e.g., a mock service in development vs the real one in production), use the `boot` callback to conditionally override bindings:

```php
return [
    'bindings' => [
        // Default binding — used in all environments
        PaymentGatewayInterface::class => StripePaymentGateway::class,
    ],
    'boot' => function (ContainerInterface $container): void {
        if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
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

**Use config instead when** the difference is just values (API URLs, credentials, feature flags). Keep the same class everywhere and let config drive the behavior:

```php
// config/payments.php — same class, different behavior per environment
return [
    'gateway_url' => $_ENV['PAYMENT_GATEWAY_URL'] ?? 'https://sandbox.stripe.com',
    'dry_run' => (bool) ($_ENV['PAYMENT_DRY_RUN'] ?? true),
];
```

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

## API Reference

### Attributes

```php
#[Preference(replaces: ClassName::class)]      // Replace a class globally
#[Plugin(target: ClassName::class)]            // Mark class as plugin
#[Before]                                       // Run before target method
#[After]                                        // Run after target method
#[Observer(event: 'event.name')]               // React to events
#[Command(name: 'cmd:name', description: '')] // Register CLI command
```

### Container

```php
interface ContainerInterface
{
    public function get(string $id): mixed;
    public function has(string $id): bool;
    public function bind(string $abstract, string $concrete): void;
}
```

### Events

```php
interface EventDispatcherInterface
{
    public function dispatch(string|Event $event, array $data = []): Event;
}
```

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
}
```
