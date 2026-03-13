---
title: Error Handling
description: Marko's "loud errors" philosophy — every error helps you fix it.
---

Marko follows a **loud errors** philosophy. No silent failures. Every error includes three things:

1. **What went wrong** — a clear description
2. **Context** — the relevant data and state
3. **Suggestion** — how to fix it

## MarkoException

All framework exceptions extend `MarkoException`, which enforces helpful error messages:

```php
<?php

declare(strict_types=1);

namespace Marko\Core\Exception;

class MarkoException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly array $context = [],
        public readonly string $suggestion = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
```

## Throwing Helpful Errors

When creating exceptions in your modules, always include context and a suggestion:

```php
use Marko\Core\Exception\MarkoException;

throw new MarkoException(
    message: "Module 'acme/payments' could not be loaded",
    context: [
        'module' => 'acme/payments',
        'path' => '/app/modules/acme/payments',
        'reason' => 'composer.json missing "extra.marko.module" key',
    ],
    suggestion: 'Add {"extra": {"marko": {"module": true}}} to the module\'s composer.json',
);
```

## Error Display

Marko provides two error display packages:

| Package | Best For |
|---|---|
| `marko/errors-simple` | Production — minimal, safe output |
| `marko/errors-advanced` | Development — full stack trace, context, suggestions |

Swap them with a Preference:

```php title="module.php"
use Marko\Errors\ErrorHandlerInterface;
use Marko\Errors\Advanced\AdvancedErrorHandler;

return [
    'bindings' => [
        ErrorHandlerInterface::class => AdvancedErrorHandler::class,
    ],
];
```

## What Makes Errors "Loud"

A loud error is one that:

- **Never returns null when it should throw** — if something is wrong, you hear about it immediately
- **Fails at boot time, not request time** — route conflicts, missing bindings, and invalid config are caught when the app starts
- **Includes enough context to debug without a stack trace** — the error message alone should tell you what to fix

## Next Steps

- [Testing](/docs/guides/testing/) — test error scenarios
- [Configuration](/docs/getting-started/configuration/) — configure error display
- [Errors package reference](/docs/packages/errors/) — full API details
