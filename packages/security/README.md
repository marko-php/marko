# marko/security

CSRF protection, CORS handling, and security headers middleware -- secure your routes with drop-in middleware.

## Installation

```bash
composer require marko/security
```

## Quick Example

```php
use Marko\Routing\Attributes\Post;
use Marko\Routing\Attributes\Middleware;
use Marko\Security\Middleware\CsrfMiddleware;

class FormController
{
    #[Post('/contact')]
    #[Middleware(CsrfMiddleware::class)]
    public function submit(): Response
    {
        // Token validated automatically
        return new Response('Submitted');
    }
}
```

## Documentation

Full usage, API reference, and examples: [marko/security](https://marko.build/docs/packages/security/)
