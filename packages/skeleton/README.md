# Marko Skeleton

Application skeleton for the [Marko Framework](https://marko.build).

## Installation

```bash
composer create-project marko/skeleton my-app
cd my-app
```

## What's Included

- `public/index.php` — Web entry point
- `app/` — Your application modules
- `modules/` — Third-party modules
- `config/` — Root configuration
- `storage/` — Logs, cache, sessions
- `.env.example` — Environment template

## Getting Started

1. Copy `.env.example` to `.env`
2. Install dev tools: `composer install`
3. Start the dev server: `marko up`
4. Visit http://localhost:8000

## Next Steps

Create your first controller inside `app/`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Marko\Http\Request;
use Marko\Http\Response;

class HomeController
{
    public function index(Request $request): Response
    {
        return new Response('Hello, Marko!');
    }
}
```

## Documentation

- [Your First Application](https://marko.build/docs/getting-started/first-application/)
- [Project Structure](https://marko.build/docs/getting-started/project-structure/)
- [Full Documentation](https://marko.build/docs/)
