# marko/forms

Server-rendered form round trips for Marko --- redirect back with flashed validation errors and old input, plus `csrf_field()`, `old()` and `errors()` template helpers for Latte and Twig.

## Installation

```bash
composer require marko/forms
```

## Quick Example

```php
use Marko\Forms\Middleware\RedirectBackOnValidationError;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post;

#[Post('/register')]
#[Middleware(RedirectBackOnValidationError::class)]
public function store(Request $request): Response
{
    // On failure: 303 back to the form with the errors and old input flashed
    $this->validator->validateOrFail($request->post(), ['email' => ['required', 'email']]);

    return Response::redirect('/welcome', 303);
}
```

```latte
<form method="post">
    {csrf_field()}
    <input name="email" value={old('email')}>
    {if $errors->has('email')}<p>{$errors->first('email')}</p>{/if}
</form>
```

## Documentation

Full usage, API reference, and examples: [marko/forms](https://marko.build/docs/packages/forms/)
