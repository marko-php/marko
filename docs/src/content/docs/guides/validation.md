---
title: Validation
description: Validate input data with clear, composable rules.
---

Marko's validation system provides clear, composable rules for validating input data. Inject `ValidatorInterface` and define rules as simple arrays -- validation failures throw exceptions with detailed error information.

## Setup

```bash
composer require marko/validation
```

## Basic Validation

```php
<?php

declare(strict_types=1);

use Marko\Validation\ValidatorInterface;

class PostController
{
    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {}

    public function store(array $data): void
    {
        $validated = $this->validator->validate($data, [
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'body' => ['required', 'string'],
            'email' => ['required', 'email'],
            'status' => ['in:draft,published'],
        ]);

        // $validated contains only the validated fields
    }
}
```

## Handling Validation Errors

When validation fails, a `ValidationException` is thrown with all error details:

```php
use Marko\Validation\ValidationException;

try {
    $validated = $this->validator->validate($data, $rules);
} catch (ValidationException $e) {
    $errors = $e->errors();
    // ['title' => ['The title field is required.']]
}
```

## Available Rules

| Rule | Description |
|---|---|
| `required` | Field must be present and not empty |
| `string` | Must be a string |
| `int` | Must be an integer |
| `email` | Must be a valid email address |
| `min:n` | Minimum length (string) or value (number) |
| `max:n` | Maximum length (string) or value (number) |
| `in:a,b,c` | Must be one of the listed values |
| `url` | Must be a valid URL |
| `confirmed` | Must have a matching `_confirmation` field |

## Next Steps

- [Routing](/docs/guides/routing/) — validate request input in controllers
- [Error Handling](/docs/guides/error-handling/) — customize error responses
- [Validation package reference](/docs/packages/validation/) — full API details
