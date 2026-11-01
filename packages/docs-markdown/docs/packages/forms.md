---
title: marko/forms
description: Server-rendered form round trips --- redirect back with flashed validation errors and old input, plus csrf_field(), old() and errors() template helpers.
---

Server-rendered form round trips for Marko. A form posts, validation fails, the user is redirected back, and the form shows each field's errors with the previous input filled in. `marko/forms` provides each step once: the `RedirectBackOnValidationError` middleware, next-request storage for the errors and old input, and template helpers that work in Latte and Twig alike.

Nothing is automatic: a route opts in with the middleware. JSON requests are left alone and keep getting the `422` response from [marko/validation](/docs/packages/validation/#throw-on-failure).

## Installation

```bash
composer require marko/forms
```

It needs a session driver ([marko/session-file](/docs/packages/session-file/) or [marko/session-database](/docs/packages/session-database/)) and a view driver ([marko/view-latte](/docs/packages/view-latte/) or [marko/view-twig](/docs/packages/view-twig/)). The template helpers need no engine-specific setup.

## Configuration

```php title="config/forms.php"
return [
    // Input fields that are never flashed back into the form after a failed
    // submission. Dot paths reach nested fields (for example 'user.password').
    // The CSRF token and uploaded files are never flashed either.
    'never_flash' => [
        'password',
        'password_confirmation',
        'current_password',
    ],

    // Where RedirectBackOnValidationError sends the user when the request has
    // no Referer, or one from another origin. Must be a path on this site.
    'fallback_redirect' => '/',
];
```

Both keys are required. `fallback_redirect` must start with a single `/`; anything else (a full URL, `//host`) throws `FormsException`.

## Usage

### A Complete Form

The controller validates with `validateOrFail()` and opts into the middleware. On success it redirects as usual. On failure the middleware catches the `ValidationException`:

```php title="app/account/src/Controller/RegisterController.php"
use Marko\Forms\Middleware\RedirectBackOnValidationError;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Validation\Contracts\ValidatorInterface;
use Marko\View\ViewInterface;

class RegisterController
{
    public function __construct(
        private ViewInterface $view,
        private ValidatorInterface $validator,
    ) {}

    #[Get('/register')]
    public function show(): Response
    {
        return $this->view->render('account::register');
    }

    #[Post('/register')]
    #[Middleware(RedirectBackOnValidationError::class)]
    public function store(
        Request $request,
    ): Response {
        $this->validator->validateOrFail($request->post(), [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        // ...create the account

        return Response::redirect('/welcome', 303);
    }
}
```

The template prints the CSRF field, each field's first error, and the old input:

```latte title="app/account/resources/views/register.latte"
<form method="post" action="/register">
    {csrf_field()}

    <label>Name <input name="name" value={old('name')}></label>
    {if $errors->has('name')}<p class="error">{$errors->first('name')}</p>{/if}

    <label>Email <input name="email" type="email" value={old('email')}></label>
    {if $errors->has('email')}<p class="error">{$errors->first('email')}</p>{/if}

    <label>Password <input name="password" type="password"></label>
    <label>Confirm <input name="password_confirmation" type="password"></label>
    {if $errors->has('password')}<p class="error">{$errors->first('password')}</p>{/if}

    <button>Create account</button>
</form>
```

The same template in Twig:

```twig title="app/account/resources/views/register.twig"
<form method="post" action="/register">
    {{ csrf_field() }}

    <label>Name <input name="name" value="{{ old('name') }}"></label>
    {% if errors.has('name') %}<p class="error">{{ errors.first('name') }}</p>{% endif %}

    <label>Email <input name="email" type="email" value="{{ old('email') }}"></label>
    {% if errors.has('email') %}<p class="error">{{ errors.first('email') }}</p>{% endif %}

    <label>Password <input name="password" type="password"></label>
    <label>Confirm <input name="password_confirmation" type="password"></label>
    {% if errors.has('password') %}<p class="error">{{ errors.first('password') }}</p>{% endif %}

    <button>Create account</button>
</form>
```

The password fields are never refilled: `password` and `password_confirmation` are in `never_flash`.

### Testing a Form

The [HTTP test client](/docs/packages/testing/#flashed-validation-errors-and-old-input) asserts on the flashed errors and old input through `FlashedFormStateInterface`, without consuming them:

```php
it('sends the registration form back with errors', function (): void {
    $this->http->post('/register', ['name' => '', 'email' => 'not-an-email', 'password' => 'short'])
        ->assertRedirect()
        ->assertSessionHasErrors(['name', 'email', 'password' => 'at least 12'])
        ->assertSessionHasInput('email', 'not-an-email')
        ->assertSessionMissingInput('password');
});
```

### What the Middleware Does

When the controller throws `ValidationException`, `RedirectBackOnValidationError`:

1. Rethrows it if the request wants JSON (`Request::wantsJson()`), so API clients get the `422` JSON response.
2. Flashes the errors and `$request->post()` for the next request, leaving out the `_token` CSRF field, uploaded files and every `never_flash` field.
3. Redirects with `303 See Other` to the `Referer` --- only when it has the request's host and port --- and otherwise to `fallback_redirect`. Only the Referer's path and query are used.

The scheme is not compared, so a site behind a TLS-terminating proxy (the app sees `http`, the browser sends an `https` Referer) still redirects back. A Referer from another host, another port, or with a non-HTTP scheme goes to the fallback.

The middleware needs a session: it must run inside `SessionMiddleware`. On a route without one it throws `FormsSessionUnavailableException` on every request, so the mistake shows up immediately. Other exceptions pass through untouched.

Apply it to a whole controller by putting `#[Middleware(RedirectBackOnValidationError::class)]` on the class.

### Template Helpers

`FormTemplateFunctionProvider` adds four functions to every template, in every engine:

| Function | Returns |
|----------|---------|
| `csrf_field()` | `<input type="hidden" name="_token" value="...">`, printed unescaped (the token inside is escaped) |
| `csrf_token()` | The CSRF token, escaped like any value --- for a `<meta>` tag or JavaScript |
| `old(field, default)` | The flashed input at a dot path (`'address.city'`, `'items.0.sku'`), or `default` |
| `errors()` | The flashed `ValidationErrors` |

`FormViewDataProvider` shares two variables with every render:

| Variable | Type | Latte | Twig |
|----------|------|-------|------|
| `$errors` | `ValidationErrors` | `{$errors->first('email')}` | `{{ errors.first('email') }}` |
| `$old` | `OldInput` | `{$old['email']}`, `{$old['address.city']}` | `{{ old.email }}`, `{{ old.address.city }}` |

Old values are escaped like any other output. `$old` is read-only array access; a missing field reads as `null`. Data passed to `render()` under the key `errors` or `old` wins over the shared value. `ValidationErrors` has `has()`, `first()`, `get()`, `all()`, `keys()`, `isEmpty()` and `count()` --- see [marko/validation](/docs/packages/validation/#validationerrors).

The helpers read the state of the request the user was redirected to. On a request where no session was started --- a visitor without a session cookie, a CLI render, a queued mail --- they see no errors and no old input, and never start a session just to look.

### Flashing Manually

To flash errors and input without the middleware, for example after a check that does not go through the validator, use `FlashedFormStateWriter`:

```php
use Marko\Forms\FlashedFormStateWriter;
use Marko\Routing\Http\Response;
use Marko\Validation\Validation\ValidationErrors;

public function __construct(
    private FlashedFormStateWriter $flashedFormStateWriter,
) {}

// ...
$this->flashedFormStateWriter->flash(
    new ValidationErrors(['coupon' => ['This coupon has expired.']]),
    $request->post(),
);

return Response::redirect('/checkout', 303);
```

It applies the same exclusions as the middleware: `_token`, uploaded files and `never_flash` fields are left out.

### Reading Flashed State

Code that needs the errors or old input outside a template reads them through [`FlashedFormStateInterface`](/docs/packages/validation/#flashedformstateinterface). It is the only reader: the view helpers, the [Inertia](/docs/packages/inertia/#validation-errors) `errors` prop and test assertions all use it, and none of them knows the storage keys.

```php
use Marko\Validation\Contracts\FlashedFormStateInterface;

public function __construct(
    private FlashedFormStateInterface $flashedFormState,
) {}

if ($this->flashedFormState->hasErrors()) {
    $emailError = $this->flashedFormState->errors()->first('email');
    $city = $this->flashedFormState->old('address.city', '');
}
```

Reading never consumes the state. It is stored with [next-request flash data](/docs/packages/session/#next-request-flash-data), so it is available on exactly the request after the failed submission and gone on the one after that, whether or not anything read it.

## Errors

| Exception | When |
|-----------|------|
| `FormsSessionUnavailableException` | `RedirectBackOnValidationError` runs on a route without a session |
| `FormsException` | A `never_flash` entry is not a non-empty string, `fallback_redirect` is not a local path, or a template writes to `$old` |
| `InvalidFlashDataException` | The flashed input holds an object other than an uploaded file (from [marko/session](/docs/packages/session/#exceptions)) |
| `ConfigNotFoundException` | `forms.never_flash` or `forms.fallback_redirect` is missing |

## API Reference

### RedirectBackOnValidationError

```php
use Marko\Forms\Middleware\RedirectBackOnValidationError;

public function handle(Request $request, callable $next): Response;
```

### FlashedFormStateWriter

```php
use Marko\Forms\FlashedFormStateWriter;

public function flash(ValidationErrors $errors, array $input): void;
```

### SessionFlashedFormState

Implements `FlashedFormStateInterface` over `FlashDataInterface`. `marko/forms` binds it as the implementation.

```php
use Marko\Forms\SessionFlashedFormState;

public function errors(): ValidationErrors;
public function hasErrors(): bool;
public function old(string $key, mixed $default = null): mixed;
```

### FormTemplateFunctionProvider

`#[TemplateFunctions]` provider for `csrf_field()`, `csrf_token()`, `old()` and `errors()`.

```php
use Marko\Forms\View\FormTemplateFunctionProvider;

public function functions(): array;
public function csrfField(): string;
```

### FormViewDataProvider

`#[ViewData]` provider for `$errors` and `$old`.

```php
use Marko\Forms\View\FormViewDataProvider;

public function data(): array; // ['errors' => ValidationErrors, 'old' => OldInput]
```

### FormsConfig

```php
use Marko\Forms\Config\FormsConfig;

public function neverFlash(): array;        // list<string> of dot paths
public function fallbackRedirect(): string;
```

## Related Packages

- [`marko/validation`](/docs/packages/validation/) --- throws `ValidationException` and defines `FlashedFormStateInterface`
- [`marko/session`](/docs/packages/session/) --- next-request flash data the form state is stored in
- [`marko/security`](/docs/packages/security/) --- CSRF tokens for `csrf_field()` and `csrf_token()`
- [`marko/view`](/docs/packages/view/) --- template function and view data providers
- [`marko/inertia`](/docs/packages/inertia/) --- fills its `errors` prop from the flashed errors
- [`marko/testing`](/docs/packages/testing/) --- `assertSessionHasErrors()` and the other flashed form state assertions
