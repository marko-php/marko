---
title: Your First Application
description: Build a simple web application with Marko from scratch.
---

This guide walks you through building a simple "Hello World" web application with Marko, introducing key concepts along the way.

## 1. Create the Project

```bash
composer create-project marko/skeleton hello-marko
cd hello-marko
```

## 2. Create a Controller

Controllers handle HTTP requests. Create one using PHP attributes to define routes:

```php title="app/foo/src/Controller/GreetingController.php"
<?php

declare(strict_types=1);

namespace App\Foo\Controller;

use Marko\Routing\Attribute\Get;
use Marko\Http\Response;
use Marko\Http\ResponseInterface;

class GreetingController
{
    #[Get('/hello/{name}')]
    public function greet(string $name): ResponseInterface
    {
        return new Response(
            body: "Hello, {$name}! Welcome to Marko.",
        );
    }
}
```

## 3. Register Your Module

Your `app/foo/` directory needs a `composer.json` to be recognized as a module:

```json title="app/foo/composer.json"
{
    "name": "app/foo",
    "type": "marko-module",
    "autoload": {
        "psr-4": {
            "App\\Foo\\": "src/"
        }
    },
    "extra": {
        "marko": {
            "module": true
        }
    }
}
```

Place the controller at `app/foo/src/Controller/GreetingController.php`.

## 4. Start and Test

```bash
marko up
```

Visit `http://localhost:8000/hello/World` and you'll see:

```
Hello, World! Welcome to Marko.
```

## 5. Add a JSON Endpoint

Marko makes JSON responses simple:

```php title="app/foo/src/Controller/ApiController.php"
<?php

declare(strict_types=1);

namespace App\Foo\Controller;

use Marko\Routing\Attribute\Get;
use Marko\Http\JsonResponse;
use Marko\Http\ResponseInterface;

class ApiController
{
    #[Get('/api/hello/{name}')]
    public function greet(string $name): ResponseInterface
    {
        return new JsonResponse(
            data: ['message' => "Hello, {$name}!", 'framework' => 'Marko'],
        );
    }
}
```

## What You've Learned

- **Modules** are Composer packages with `marko.module: true`
- **Routes** are defined with PHP attributes (`#[Get]`, `#[Post]`, etc.)
- **Controllers** are plain classes — no base class inheritance required
- Marko auto-discovers modules in `app/`, `modules/`, and `vendor/`

## Next Steps

- [Learn about project structure](/docs/getting-started/project-structure/) in detail
- [Understand dependency injection](/docs/concepts/dependency-injection/) to wire services together
- [Add a database](/docs/guides/database/) to persist data
