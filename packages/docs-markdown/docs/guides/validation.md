---
title: Validation
description: Validate input data with clear, composable rules.
---

Marko's validation system provides clear, composable rules for validating input data. Inject `ValidatorInterface` and define rules as simple arrays --- `validate()` returns a `ValidationErrors` object you can inspect, or use `validateOrFail()` to throw on failure.

## Setup

```bash
composer require marko/validation
```

## Basic Validation

```php
<?php

declare(strict_types=1);

use Marko\Validation\Contracts\ValidatorInterface;
use Marko\Validation\Validation\ValidationErrors;

readonly class PostController
{
    public function __construct(
        private ValidatorInterface $validator,
    ) {}

    public function store(array $data): void
    {
        $errors = $this->validator->validate($data, [
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'body' => ['required', 'string'],
            'email' => ['required', 'email'],
            'status' => ['in:draft,published'],
        ]);

        if ($errors->isNotEmpty()) {
            // Handle validation errors
            // $errors->all() returns ['field' => ['message', ...]]
        }
    }
}
```

## Handling Validation Errors

The `validate()` method returns a `ValidationErrors` object:

```php
use Marko\Validation\Contracts\ValidatorInterface;
use Marko\Validation\Validation\ValidationErrors;

$errors = $this->validator->validate($data, $rules);

if ($errors->isNotEmpty()) {
    $errors->all();           // ['title' => ['The title field is required.']]
    $errors->has('title');    // true
    $errors->get('title');    // ['The title field is required.']
    $errors->first('title');  // 'The title field is required.'
}
```

To throw an exception on validation failure, use `validateOrFail()`:

```php
use Marko\Validation\Contracts\ValidatorInterface;
use Marko\Validation\Exceptions\ValidationException;

try {
    $this->validator->validateOrFail($data, $rules);
} catch (ValidationException $e) {
    $errors = $e->errors(); // ValidationErrors instance
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
