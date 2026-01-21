# Plan: Validation Package (marko/validation)

## Created
2026-01-21

## Status
pending

## Objective
Build the `marko/validation` package providing a type-safe, attribute-based validation system with declarative rules on DTOs/entities, validator service, validation error collection, and integration with routing for request data validation - following the framework's "loud errors" philosophy.

## Scope

### In Scope
- Package structure (composer.json, module.php, PSR-4 autoloading)
- Validation rule attributes (`#[Required]`, `#[Email]`, `#[MinLength]`, `#[MaxLength]`, `#[Min]`, `#[Max]`, `#[Numeric]`, `#[Regex]`, `#[In]`, `#[Url]`, `#[Date]`, `#[Confirmed]`)
- `ValidatorInterface` - main validation contract
- `Validator` - implementation that reads attributes and validates data
- `ValidationResult` - value object containing validation outcome (valid/invalid + errors)
- `ValidationError` - value object for individual field errors (field, rule, message)
- `ValidationException` - thrown when validation fails (integrates with error system)
- `RuleInterface` - contract for custom validation rules
- Built-in rule classes implementing validation logic
- Attribute-based validation on DTO/entity classes
- Array validation with nested rules
- Request data validation via `ValidatedRequest` attribute on controller parameters
- Custom rule support (implement `RuleInterface`)
- Configurable error messages with field name interpolation
- Integration with routing for automatic request validation

### Out of Scope
- Database-related rules (unique, exists) - requires database package integration, future enhancement
- Async/deferred validation
- Conditional validation rules (when/unless) - can be added later
- File upload validation (separate concern)
- CSRF protection (middleware responsibility)
- Form builder/generator
- Client-side validation generation

## Success Criteria
- [ ] Validation rule attributes can be applied to DTO properties
- [ ] `Validator::validate($object)` validates an object against its attributes
- [ ] `Validator::validateArray($data, $rules)` validates array data against rule definitions
- [ ] `ValidationResult` indicates success/failure and contains all errors
- [ ] `ValidationError` provides field name, rule name, and human-readable message
- [ ] `ValidationException` is thrown when validation fails in strict mode
- [ ] Custom rules can be created by implementing `RuleInterface`
- [ ] Error messages support field name interpolation (`{field}` placeholder)
- [ ] Controller parameters with `#[ValidatedRequest]` are automatically validated
- [ ] Validation errors return 422 response with JSON error details for API routes
- [ ] Loud errors when validation rules are misconfigured
- [ ] All tests passing
- [ ] Code follows project standards (strict types, no final, etc.)

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Package scaffolding (composer.json, module.php) | - | pending |
| 002 | Exception classes (ValidationException, RuleException) | 001 | pending |
| 003 | ValidationError value object | 001 | pending |
| 004 | ValidationResult value object | 003 | pending |
| 005 | RuleInterface contract | 001 | pending |
| 006 | Base rule attributes (#[Required], #[Email], etc.) | 005 | pending |
| 007 | Built-in rule implementations | 005, 006 | pending |
| 008 | ValidatorInterface contract | 004 | pending |
| 009 | Validator implementation (attribute reading + validation) | 006, 007, 008 | pending |
| 010 | Array validation support | 009 | pending |
| 011 | Nested validation support | 009, 010 | pending |
| 012 | Custom error messages support | 009 | pending |
| 013 | ValidatedRequest attribute | 009 | pending |
| 014 | Request validation middleware/integration | 009, 013 | pending |
| 015 | ValidationResponseFormatter (JSON error responses) | 002, 004 | pending |
| 016 | Unit tests for rules | 007 | pending |
| 017 | Unit tests for Validator | 009, 010, 011 | pending |
| 018 | Integration tests with routing | 014 | pending |

## Architecture Notes

### Package Structure
```
packages/validation/
  src/
    Attributes/
      Required.php
      Email.php
      MinLength.php
      MaxLength.php
      Min.php
      Max.php
      Numeric.php
      Regex.php
      In.php
      Url.php
      Date.php
      Confirmed.php
      ValidatedRequest.php
    Contracts/
      RuleInterface.php
      ValidatorInterface.php
    Exceptions/
      ValidationException.php
      RuleException.php
    Rules/
      RequiredRule.php
      EmailRule.php
      MinLengthRule.php
      MaxLengthRule.php
      MinRule.php
      MaxRule.php
      NumericRule.php
      RegexRule.php
      InRule.php
      UrlRule.php
      DateRule.php
      ConfirmedRule.php
    ValidationError.php
    ValidationResult.php
    Validator.php
    ValidationResponseFormatter.php
  tests/
    Unit/
      Rules/
      ValidatorTest.php
      ValidationResultTest.php
    Feature/
      RequestValidationTest.php
  composer.json
  module.php
```

### Rule Attribute Pattern
Each validation rule is an attribute that contains its configuration:

```php
// packages/validation/src/Attributes/MinLength.php
declare(strict_types=1);

namespace Marko\Validation\Attributes;

use Attribute;
use Marko\Validation\Contracts\RuleInterface;
use Marko\Validation\Rules\MinLengthRule;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class MinLength implements RuleInterface
{
    public function __construct(
        public int $min,
        public ?string $message = null,
    ) {}

    public function validate(string $field, mixed $value): ?string
    {
        return (new MinLengthRule($this->min, $this->message))->validate($field, $value);
    }

    public function ruleName(): string
    {
        return 'min_length';
    }
}
```

### RuleInterface Contract
```php
// packages/validation/src/Contracts/RuleInterface.php
declare(strict_types=1);

namespace Marko\Validation\Contracts;

interface RuleInterface
{
    /**
     * Validate a field value.
     *
     * @param string $field The field name (for error messages)
     * @param mixed $value The value to validate
     * @return string|null Error message if invalid, null if valid
     */
    public function validate(string $field, mixed $value): ?string;

    /**
     * Get the rule name for error reporting.
     */
    public function ruleName(): string;
}
```

### ValidatorInterface Contract
```php
// packages/validation/src/Contracts/ValidatorInterface.php
declare(strict_types=1);

namespace Marko\Validation\Contracts;

use Marko\Validation\ValidationResult;

interface ValidatorInterface
{
    /**
     * Validate an object against its attribute rules.
     */
    public function validate(object $object): ValidationResult;

    /**
     * Validate array data against defined rules.
     *
     * @param array<string, mixed> $data
     * @param array<string, array<RuleInterface>> $rules
     */
    public function validateArray(array $data, array $rules): ValidationResult;

    /**
     * Validate and throw if invalid (strict mode).
     *
     * @throws ValidationException
     */
    public function validateOrFail(object $object): void;
}
```

### ValidationResult Value Object
```php
// packages/validation/src/ValidationResult.php
declare(strict_types=1);

namespace Marko\Validation;

readonly class ValidationResult
{
    /**
     * @param array<string, array<ValidationError>> $errors Errors keyed by field
     */
    public function __construct(
        private array $errors = [],
    ) {}

    public function isValid(): bool
    {
        return empty($this->errors);
    }

    public function isFailed(): bool
    {
        return !$this->isValid();
    }

    /**
     * @return array<string, array<ValidationError>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<ValidationError>
     */
    public function errorsFor(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    public function firstError(string $field): ?ValidationError
    {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * @return array<string, array<string>>
     */
    public function toArray(): array
    {
        $result = [];
        foreach ($this->errors as $field => $fieldErrors) {
            $result[$field] = array_map(
                fn (ValidationError $e) => $e->message,
                $fieldErrors,
            );
        }
        return $result;
    }
}
```

### ValidationError Value Object
```php
// packages/validation/src/ValidationError.php
declare(strict_types=1);

namespace Marko\Validation;

readonly class ValidationError
{
    public function __construct(
        public string $field,
        public string $rule,
        public string $message,
    ) {}
}
```

### DTO Validation Example
```php
// Example usage in application code
declare(strict_types=1);

namespace App\Blog\Dto;

use Marko\Validation\Attributes\Required;
use Marko\Validation\Attributes\MinLength;
use Marko\Validation\Attributes\MaxLength;
use Marko\Validation\Attributes\Email;

class CreatePostRequest
{
    #[Required]
    #[MinLength(3)]
    #[MaxLength(255)]
    public string $title;

    #[Required]
    #[MinLength(3)]
    public string $slug;

    #[MinLength(10)]
    public ?string $content = null;

    #[Required]
    #[Email]
    public string $authorEmail;
}
```

### Validator Usage
```php
// Manual validation
$validator = $container->get(ValidatorInterface::class);

$request = new CreatePostRequest();
$request->title = '';
$request->slug = 'ab';
$request->authorEmail = 'invalid';

$result = $validator->validate($request);

if ($result->isFailed()) {
    foreach ($result->errors() as $field => $errors) {
        echo "$field: " . $errors[0]->message . "\n";
    }
}

// Strict mode - throws ValidationException
$validator->validateOrFail($request);
```

### Array Validation
```php
// Validating array data without a DTO
$validator = $container->get(ValidatorInterface::class);

$data = [
    'name' => '',
    'email' => 'invalid',
];

$rules = [
    'name' => [new Required(), new MinLength(2)],
    'email' => [new Required(), new Email()],
];

$result = $validator->validateArray($data, $rules);
```

### Nested Validation
```php
// Nested object validation
class OrderRequest
{
    #[Required]
    public string $orderId;

    #[Required]
    #[Nested] // Validates the Address object's rules
    public Address $shippingAddress;
}

class Address
{
    #[Required]
    #[MaxLength(100)]
    public string $street;

    #[Required]
    #[MaxLength(50)]
    public string $city;
}
```

### Controller Integration with ValidatedRequest
```php
// packages/blog/src/Controllers/PostController.php
declare(strict_types=1);

namespace Marko\Blog\Controllers;

use App\Blog\Dto\CreatePostRequest;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Response;
use Marko\Validation\Attributes\ValidatedRequest;

class PostController
{
    #[Post('/api/posts')]
    public function store(
        #[ValidatedRequest] CreatePostRequest $request,
    ): Response {
        // $request is already validated
        // If validation failed, a 422 response was returned automatically
        return Response::json(['id' => $newPost->id], 201);
    }
}
```

### Request Validation Flow
```
HTTP Request → Router → ValidatedRequest Detected
    ↓
Hydrate DTO from request body (POST data / JSON)
    ↓
Validator::validate($dto)
    ↓
If invalid → Return 422 with JSON errors
    ↓
If valid → Pass DTO to controller method
    ↓
Controller executes
```

### 422 Error Response Format
```json
{
    "message": "Validation failed",
    "errors": {
        "title": [
            "The title field is required",
            "The title must be at least 3 characters"
        ],
        "authorEmail": [
            "The author email must be a valid email address"
        ]
    }
}
```

### ValidationException
```php
// packages/validation/src/Exceptions/ValidationException.php
declare(strict_types=1);

namespace Marko\Validation\Exceptions;

use Marko\Core\Exceptions\MarkoException;
use Marko\Validation\ValidationResult;

class ValidationException extends MarkoException
{
    public function __construct(
        private readonly ValidationResult $result,
        ?string $message = null,
    ) {
        $errors = $result->toArray();
        $firstField = array_key_first($errors);
        $firstError = $errors[$firstField][0] ?? 'Validation failed';

        parent::__construct(
            message: $message ?? $firstError,
            context: json_encode($errors, JSON_PRETTY_PRINT),
            suggestion: 'Fix the validation errors and try again.',
        );
    }

    public function result(): ValidationResult
    {
        return $this->result;
    }

    public function errors(): array
    {
        return $this->result->toArray();
    }
}
```

### Built-in Rules Reference
| Attribute | Parameters | Description |
|-----------|------------|-------------|
| `#[Required]` | `message?` | Value must be present and not empty |
| `#[Email]` | `message?` | Value must be valid email format |
| `#[MinLength(min)]` | `min`, `message?` | String must be at least `min` characters |
| `#[MaxLength(max)]` | `max`, `message?` | String must be at most `max` characters |
| `#[Min(min)]` | `min`, `message?` | Numeric value must be >= `min` |
| `#[Max(max)]` | `max`, `message?` | Numeric value must be <= `max` |
| `#[Numeric]` | `message?` | Value must be numeric |
| `#[Regex(pattern)]` | `pattern`, `message?` | Value must match regex pattern |
| `#[In(...values)]` | `values`, `message?` | Value must be in allowed list |
| `#[Url]` | `message?` | Value must be valid URL format |
| `#[Date(format?)]` | `format?`, `message?` | Value must be valid date (optional format) |
| `#[Confirmed]` | `field?`, `message?` | Value must match `{field}_confirmation` |

### Default Error Messages
```php
// Built into each rule, with {field} interpolation
'required' => 'The {field} field is required.',
'email' => 'The {field} must be a valid email address.',
'min_length' => 'The {field} must be at least {min} characters.',
'max_length' => 'The {field} must not exceed {max} characters.',
'min' => 'The {field} must be at least {min}.',
'max' => 'The {field} must not exceed {max}.',
'numeric' => 'The {field} must be a number.',
'regex' => 'The {field} format is invalid.',
'in' => 'The selected {field} is invalid.',
'url' => 'The {field} must be a valid URL.',
'date' => 'The {field} must be a valid date.',
'confirmed' => 'The {field} confirmation does not match.',
```

### Custom Rule Example
```php
// Application code - creating a custom rule
declare(strict_types=1);

namespace App\Validation\Rules;

use Attribute;
use Marko\Validation\Contracts\RuleInterface;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class StrongPassword implements RuleInterface
{
    public function __construct(
        public ?string $message = null,
    ) {}

    public function validate(string $field, mixed $value): ?string
    {
        if (!is_string($value)) {
            return $this->formatMessage($field, 'The {field} must be a string.');
        }

        if (strlen($value) < 8) {
            return $this->formatMessage($field, 'The {field} must be at least 8 characters.');
        }

        if (!preg_match('/[A-Z]/', $value)) {
            return $this->formatMessage($field, 'The {field} must contain at least one uppercase letter.');
        }

        if (!preg_match('/[0-9]/', $value)) {
            return $this->formatMessage($field, 'The {field} must contain at least one number.');
        }

        return null;
    }

    public function ruleName(): string
    {
        return 'strong_password';
    }

    private function formatMessage(string $field, string $default): string
    {
        $message = $this->message ?? $default;
        return str_replace('{field}', $field, $message);
    }
}
```

### Module Bindings
```php
// packages/validation/module.php
declare(strict_types=1);

use Marko\Validation\Contracts\ValidatorInterface;
use Marko\Validation\Validator;

return [
    'enabled' => true,
    'bindings' => [
        ValidatorInterface::class => Validator::class,
    ],
];
```

### Integration with Routing
The validation package integrates with routing through:
1. `ValidatedRequest` attribute on controller parameters
2. Router detects the attribute during dispatch
3. Hydrates DTO from request data
4. Validates using Validator
5. On failure, returns 422 response (short-circuits controller)
6. On success, passes validated DTO to controller

This requires a small integration point in the routing package's Router class.

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| Reflection performance for attribute reading | Cache rule definitions per class; attributes are read once per class at first validation |
| Complex nested object graphs | Limit nesting depth with configurable max; detect circular references |
| DTO hydration from request data | Start with simple property assignment; more complex hydration in future iteration |
| Type coercion edge cases | Document supported types; use PHP's native type system where possible |
| Routing integration coupling | Define clear integration interface; routing can optionally support validation |
| Custom rule discoverability | Rules are plain PHP classes with attributes; IDE autocomplete works naturally |
