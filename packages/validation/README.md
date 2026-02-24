# Marko Validation

Input validation with built-in rules, string-based rule parsing, and custom rule support--validate data before it reaches your domain.

## Overview

Validation provides a `ValidatorInterface` for checking data against rules. Rules can be specified as pipe-delimited strings (`'required|email|max:255'`), arrays, or `RuleInterface` objects. Validation errors are collected into a `ValidationErrors` bag with per-field messages. Use `validateOrFail()` to throw on invalid data.

## Installation

```bash
composer require marko/validation
```

## Usage

### Basic Validation

Inject `ValidatorInterface` and pass data with rules:

```php
use Marko\Validation\Contracts\ValidatorInterface;

class UserController
{
    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {}

    public function store(
        array $input,
    ): void {
        $errors = $this->validator->validate($input, [
            'name' => 'required|string|max:100',
            'email' => 'required|email',
            'age' => 'nullable|integer|min:18',
        ]);

        if ($errors->isNotEmpty()) {
            // handle errors
        }
    }
}
```

### Throw on Failure

Use `validateOrFail()` to throw `ValidationException` when data is invalid:

```php
$this->validator->validateOrFail($input, [
    'title' => 'required|string|max:200',
    'body' => 'required|string',
]);
// Throws ValidationException with errors() method
```

### Quick Boolean Check

```php
if ($this->validator->passes($input, ['email' => 'required|email'])) {
    // data is valid
}

if ($this->validator->fails($input, ['email' => 'required|email'])) {
    // data is invalid
}
```

### Working with Errors

`ValidationErrors` provides methods for inspecting failures:

```php
$errors = $this->validator->validate($input, $rules);

$errors->has('email');        // true if email has errors
$errors->get('email');        // ['The email field must be a valid email.']
$errors->first('email');      // 'The email field must be a valid email.'
$errors->all();               // ['email' => [...], 'name' => [...]]
$errors->toFlatArray();       // ['email: ...', 'name: ...']
$errors->isEmpty();           // true if no errors
$errors->count();             // total error count across all fields
```

### Built-in Rules

| Rule | String Syntax | Description |
|------|--------------|-------------|
| Required | `required` | Must be present and non-empty |
| Nullable | `nullable` | Skips other rules if null/empty |
| String | `string` | Must be a string |
| Integer | `integer` | Must be an integer |
| Numeric | `numeric` | Must be numeric |
| Boolean | `boolean` | Must be boolean-like |
| Email | `email` | Must be a valid email |
| URL | `url` | Must be a valid URL |
| Alpha | `alpha` | Letters only |
| AlphaNumeric | `alpha_num` | Letters and numbers only |
| Min | `min:5` | Minimum value/length |
| Max | `max:255` | Maximum value/length |
| Between | `between:1,100` | Value/length within range |
| In | `in:draft,published` | Must be one of the listed values |
| NotIn | `not_in:admin,root` | Must not be one of the listed values |
| Same | `same:other_field` | Must match another field |
| Different | `different:other_field` | Must differ from another field |
| Confirmed | `confirmed` | Must have a matching `_confirmation` field |
| Regex | `regex:/^\d{3}$/` | Must match the pattern |
| Date | `date` or `date:Y-m-d` | Must be a valid date |
| Array | `array` | Must be an array |

### Mixed Rule Formats

Rules can be strings, arrays, or `RuleInterface` instances:

```php
use Marko\Validation\Rules\Max;
use Marko\Validation\Rules\Required;

$this->validator->validate($input, [
    'name' => 'required|string',             // String format
    'email' => ['required', 'email'],        // Array of strings
    'bio' => [new Required(), new Max(500)], // Rule objects
]);
```

### Custom Rules

Implement `RuleInterface` for custom validation logic:

```php
use Marko\Validation\Contracts\RuleInterface;

class UniqueEmail implements RuleInterface
{
    public function passes(
        string $field,
        mixed $value,
        array $data,
    ): bool {
        // Check uniqueness against database
        return !$this->emailExists($value);
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        return "The $field has already been taken.";
    }
}

// Usage:
$this->validator->validate($input, [
    'email' => ['required', 'email', new UniqueEmail()],
]);
```

## API Reference

### ValidatorInterface

```php
interface ValidatorInterface
{
    public function validate(array $data, array $rules): ValidationErrors;
    public function validateOrFail(array $data, array $rules): void;
    public function passes(array $data, array $rules): bool;
    public function fails(array $data, array $rules): bool;
}
```

### RuleInterface

```php
interface RuleInterface
{
    public function passes(string $field, mixed $value, array $data): bool;
    public function message(string $field, mixed $value): string;
}
```

### ValidationErrors

```php
class ValidationErrors implements Countable, IteratorAggregate
{
    public function add(string $field, string $message): self;
    public function has(string $field): bool;
    public function get(string $field): array;
    public function first(string $field): ?string;
    public function all(): array;
    public function keys(): array;
    public function isEmpty(): bool;
    public function isNotEmpty(): bool;
    public function count(): int;
    public function toFlatArray(): array;
}
```
