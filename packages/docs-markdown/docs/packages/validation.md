---
title: marko/validation
description: Input validation with built-in rules, string-based rule parsing, and custom rule support — validate data before it reaches your domain.
---

Input validation with built-in rules, string-based rule parsing, and custom rule support — validate data before it reaches your domain. Validation provides a `ValidatorInterface` for checking data against rules. Rules can be specified as pipe-delimited strings (`'required|email|max:255'`), arrays, or `RuleInterface` objects. Validation errors are collected into a `ValidationErrors` bag with per-field messages. Use `validateOrFail()` to throw on invalid data.

## Installation

```bash
composer require marko/validation
```

## Usage

### Basic Validation

Inject `ValidatorInterface` and pass data with rules:

```php
use Marko\Validation\Contracts\ValidatorInterface;

readonly class UserController
{
    public function __construct(
        private ValidatorInterface $validator,
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

The `ValidationException` includes rich context --- call `errors()` to get the `ValidationErrors` bag, or `getContext()` and `getSuggestion()` for diagnostic details.

`ValidationException` implements `Marko\Core\Exceptions\HttpExceptionInterface`. When it escapes a controller, the routing pipeline renders it as **`422 Unprocessable Content`** with the field errors:

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "email": ["The email field must be a valid email address."]
    }
}
```

API clients get JSON; browsers get a minimal HTML page with the message. See [Errors and HTTP Exceptions](/docs/packages/routing/#errors-and-http-exceptions). Redirecting back to an HTML form with errors and old input is not built in --- catch the exception in the controller for that.

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
| RequiredIf | `required_if:type,video,audio` | Required when another field holds one of the values (see [Implicit Rules](#implicit-and-presence-rules)) |
| RequiredUnless | `required_unless:status,approved` | Required unless another field holds one of the values |
| RequiredWith | `required_with:email,phone` | Required when any of the other fields is filled |
| RequiredWithout | `required_without:email,phone` | Required when any of the other fields is empty or absent |
| Present | `present` | The key must exist; the value may be empty or `null` |
| Prohibited | `prohibited` | Must be absent or empty |
| Accepted | `accepted` | `yes`, `on`, `1`, `true`, `"1"` or `"true"` (a ticked checkbox); an absent key fails |
| Declined | `declined` | `no`, `off`, `0`, `false`, `"0"` or `"false"`; an absent key fails |
| Nullable | `nullable` | Skips every rule, implicit ones included, if null/empty |
| Sometimes | `sometimes` | Skips every rule when the key is absent; validates a present key, even `null` (see [`sometimes` vs `nullable`](#sometimes-vs-nullable)) |
| String | `string` | Must be a string |
| Integer | `integer` | Must be an integer |
| Numeric | `numeric` | Must be numeric |
| Boolean | `boolean` | Must be boolean-like |
| Email | `email` | Must be a valid email |
| URL | `url` or `url:http,https,ftp` | Must be a valid URL with a host and an allowed scheme (`http`/`https` unless listed; see [URLs](#validating-urls)) |
| Alpha | `alpha` | Letters only |
| AlphaNumeric | `alpha_num` | Letters and numbers only |
| Min | `min:5` | Minimum value (int/float, or numeric string with `numeric`/`integer`), minimum length (string), or minimum count (array). Fails for a file --- use `min_size` |
| Max | `max:255` | Maximum value (int/float, or numeric string with `numeric`/`integer`), maximum length (string), or maximum count (array). Fails for a file --- use `max_size` |
| Between | `between:1,100` | Value range (int/float, or numeric string with `numeric`/`integer`), length range (string), or count range (array). Fails for a file --- use `min_size`/`max_size` |
| Size | `size:10` | Exact value (int/float, or numeric string with `numeric`/`integer`), exact length (string), or exact count (array). Fails for a file --- use `min_size:n\|max_size:n` |
| In | `in:draft,published` | Must be one of the listed values; numeric strings are compared numerically |
| NotIn | `not_in:admin,root` | Must not be one of the listed values; numeric strings are compared numerically |
| Same | `same:other_field` | Must match another field (a dot path; see [wildcards](#validating-arrays-with-wildcards)) |
| Different | `different:other_field` | Must differ from another field (a dot path; see [wildcards](#validating-arrays-with-wildcards)) |
| Confirmed | `confirmed` | Must have a matching `_confirmation` field next to it (`account.password` checks `account.password_confirmation`) |
| Regex | `regex:/^\d{3}$/` | Must match the pattern |
| Date | `date` or `date:Y-m-d` | Must be a valid date |
| After | `after:today` or `after:starts_at` | A date later than a date or another field (see [Comparing Dates](#comparing-dates)) |
| AfterOrEqual | `after_or_equal:starts_at` | A date no earlier than a date or another field |
| Before | `before:2027-01-01` or `before:ends_at` | A date earlier than a date or another field |
| BeforeOrEqual | `before_or_equal:ends_at` | A date no later than a date or another field |
| Uuid | `uuid` or `uuid:4` | A UUID in 8-4-4-4-12 hex form, either case; with a version (1--8), that version and the RFC 9562 variant |
| Ulid | `ulid` | A 26-character ULID (Crockford base32, either case) |
| Json | `json` | A string that is valid JSON |
| Digits | `digits:4` | Exactly this many digits: a string of `0`--`9` only, or a non-negative integer (`0123` has 4) |
| DigitsBetween | `digits_between:4,6` | A number of digits within the range, inclusive |
| Array | `array` | Must be an array |
| List | `list` | An array whose keys are `0, 1, 2, ...` in order (`array_is_list`) |
| Distinct | `distinct`, `distinct:strict`, `distinct:ignore_case` | No other value under the same wildcard key is equal (see [Distinct Values](#distinct-values)) |
| Enum | `Enum::of(Status::class)` (no string form) | A case of a backed enum, or its backing value (see [Enums](#enums)) |
| Exists | `RecordRules::exists(Channel::class)` (no string form) | A stored record of the entity has this value; needs [`marko/validation-database`](/docs/packages/validation-database/) (see [Exists and Unique](#exists-and-unique)) |
| Unique | `RecordRules::unique(User::class, 'email')` (no string form) | No stored record of the entity has this value yet; needs [`marko/validation-database`](/docs/packages/validation-database/) |
| File | `file` | Must be an uploaded file that arrived without an upload error |
| Image | `image` | Must be a JPEG, PNG, GIF or WebP image, judged from its contents (never SVG) |
| Mimes | `mimes:jpg,png,pdf` | The extension for the file's sniffed MIME type must be listed (`jpeg` and `jpg` are the same) |
| MimeTypes | `mimetypes:image/*,application/pdf` | The sniffed MIME type must be listed; `type/*` matches any subtype except `image/svg+xml` |
| MaxSize | `max_size:2048` | File size at most this many kilobytes (1 KB = 1024 bytes) |
| MinSize | `min_size:1` | File size at least this many kilobytes |

Rules that take a number (`size`, `digits`, `digits_between`, `max_size`, `min_size`) throw `InvalidArgumentException` when it is missing or not a number, as do `after`/`before` without a reference and `uuid` with a version outside 1--8.

Not built in yet: `current_password` and password strength rules ([#561](https://github.com/marko-php/marko/issues/561)). Until then, write a [custom rule](#custom-rules).

### Implicit and Presence Rules

The validator skips an ordinary rule when the value is empty (`null`, a blank string or `[]`), so `email` never complains about a field that was left out. *Implicit* rules are the exception: they run even when the value is empty or the key is absent, because that is the case they exist for.

| Rule | Runs the field's other rules on an empty value? |
|------|---------------|
| `required` | Always |
| `required_if`, `required_unless`, `required_with`, `required_without` | Only when their condition makes the field required |
| `present`, `prohibited`, `accepted`, `declined` | Never (they check the value themselves) |

```php
$this->validator->validate($input, [
    'type' => 'required|in:text,video',
    'url' => 'required_if:type,video|url',       // checked only when type is video
    'phone' => 'required_without:email',         // one of the two must be filled
    'terms' => 'accepted',                       // the checkbox must be ticked
    'tags' => 'present|array',                   // the key must be sent, even as []
    'role' => 'prohibited',                      // clients may not set it
    'items.*.url' => 'required_if:items.*.type,video', // per row: items.2.type for items.2.url
]);
```

When `type` is `text`, an empty `url` is skipped entirely. When `type` is `video`, an empty `url` fails `required_if` and every other rule on the field runs too.

`required_if` and `required_unless` compare values as strings: `true`/`false` and `null` read as those words, numbers as their digits, and a boolean field also matches `1`/`0` (`required_if:subscribe,1` matches `true`). An array never matches. `required_with` and `required_without` count a field as filled when it is present and not empty. Their field references are absolute dot paths whose `*` are filled from the current row, as for [`same`](#cross-field-rules-inside-rows).

A `required_if` or `required_unless` without a field and at least one value, or a `required_with`/`required_without` without a field, throws `InvalidArgumentException`.

### `sometimes` vs `nullable`

Both make a field optional, but they ask different questions:

- **`sometimes`** asks *was the key sent?* An absent key skips every rule on the field. A present key is fully validated, including an explicit `null`, so `sometimes|required|email` lets a PATCH request leave `email` out but rejects `"email": null`.
- **`nullable`** asks *is the value empty?* An empty value (`null`, `''`, `[]`), present or not, skips every rule on the field, implicit ones included. Leave `nullable` off fields with a `required_*` rule: those rules already let the field stay empty when their condition is false.

```php
$this->validator->validate($input, [
    'email' => 'sometimes|required|email', // optional key, but never null or invalid when sent
    'nickname' => 'nullable|string|max:30', // may be null, '' or left out
]);
```

### Numeric-Aware Rules

`Min`, `Max`, and `Between` measure a value by its type: an `int` or `float` by its value, an array by its item count, and a string by its length (`mb_strlen`). A string of digits is still a string: `string|min:8` rejects the password `"9"`, and `string|max:255` rejects a 300-character run of digits.

To compare a form string by *value*, add `numeric` or `integer` to the field. The parser then switches that field's `min`, `max`, and `between` to numeric mode, so `integer|min:18` accepts `"25"` and `numeric|max:100` rejects `"150"`:

```php
$errors = $this->validator->validate(
    ['age' => '25', 'password' => '9'],
    [
        'age' => 'required|integer|min:18',      // "25" compared by value: 25 >= 18
        'password' => 'required|string|min:8',   // "9" measured by length: 1 < 8
    ],
);
// Only password fails: "The password field must be at least 8 characters."
```

Numeric mode applies whether the rules are a string, an array, or rule objects (`[new Numeric(), new Min(8)]`). A size rule object built on its own measures strings by length; call `asNumeric()` (or pass `numeric: true` to the constructor) to compare numeric strings by value.

`Size` follows the same numeric mode: `integer|size:10` accepts `"10"`, while `string|size:10` requires ten characters.

`In` and `NotIn` compare numeric strings numerically: `in:1,2,3` accepts `"2"` even though it is not strictly identical to the integer `2`.

### Comparing Dates

`after`, `before`, `after_or_equal` and `before_or_equal` take one reference: another field or a date. When a field of that name (a dot path) is present in the data, the value is compared with that field; otherwise the reference is read as a date that `strtotime()` understands. Both sides are parsed like the `date` rule, so a `DateTimeInterface` value works too:

```php
$this->validator->validate($input, [
    'starts_at' => 'required|date|after_or_equal:today',
    'ends_at' => 'required|date|after:starts_at',
    'items.*.ends_at' => 'required|date|after:items.*.starts_at', // compared within each row
]);
// "The ends_at field must be a date after starts_at."
```

A `*` in the reference is replaced by the current row's index, exactly as for [`same` and `different`](#cross-field-rules-inside-rows). The rule fails when the value, or the referenced field's value, is not a date.

When the reference is neither a date nor a field present in the data, the rule fails rather than passing: a client that sends `ends_at` but leaves out `starts_at` gets the usual 422 error on `ends_at`, never an exception. The rule cannot see the rest of the rule set, so a misspelled reference (`after:starts_att`, `after:tomorow`) also shows up only as a failing field. Cover each comparison with a test that expects it to pass.

### Distinct Values

`distinct` belongs on a wildcard key and fails every value that another item under the same key repeats:

```php
$errors = $this->validator->validate(
    ['items' => [['sku' => 'A1'], ['sku' => 'B2'], ['sku' => 'A1']]],
    ['items.*.sku' => 'required|distinct'],
);
// items.0.sku and items.2.sku: "The items.0.sku field has a duplicate value."
```

By default numbers compare by value (`1`, `"1"` and `"01"` are duplicates) and other scalars by their string form. `distinct:strict` compares with `===`, `distinct:ignore_case` lowercases strings first, and `distinct:strict,ignore_case` does both. With nested wildcards (`orders.*.lines.*.sku`) every matched value is compared with every other. `distinct` on a key without a `*` throws `InvalidArgumentException`.

### Enums

`Enum` accepts a case of a backed enum, given as the case itself or its backing value. An int-backed enum also accepts an integer string such as `"2"`, since form input arrives as strings. There is no `enum:` rule string --- a class name inside a rule string is the kind of hidden magic Marko avoids --- so pass the rule object in an array:

```php
use App\Blog\Enums\PostStatus;
use Marko\Validation\Rules\Enum;

$this->validator->validate($input, [
    'status' => ['required', Enum::of(PostStatus::class)],
    'public_status' => ['required', Enum::of(PostStatus::class)->except(PostStatus::Archived)],
    'draft_status' => ['required', Enum::of(PostStatus::class)->only(PostStatus::Draft, PostStatus::Review)],
]);
// "The status field must be one of: 'draft', 'review', 'published', 'archived'."
```

`only()` and `except()` return a new rule and leave the original unchanged. `Enum::of()` throws `InvalidArgumentException` for a pure enum (its cases have no value to validate) or a class that is not an enum, and `only()`/`except()` throw for a case of another enum.

### Exists and Unique

`Exists` checks that a stored record has the value (a foreign-key id from a form), and `Unique` checks that none has it yet (an email, a slug). They name an entity class and a property --- never a table or a column --- and the lookup maps them through the entity's metadata. Build them with `RecordRules`, an injectable factory, and pass the rules in an array:

```php
use App\Blog\Entity\Channel;
use App\Blog\Entity\User;
use Marko\Validation\Contracts\ValidatorInterface;
use Marko\Validation\Rules\RecordRules;

public function __construct(
    private readonly ValidatorInterface $validator,
    private readonly RecordRules $recordRules,
) {}

public function update(User $user, array $input): void
{
    $this->validator->validateOrFail($input, [
        'channel_id' => ['required', $this->recordRules->exists(Channel::class)->where('archived', false)],
        'email' => [
            'required',
            'email',
            $this->recordRules->unique(User::class, 'email')->caseInsensitive()->ignore($user->id),
        ],
    ]);
}
// "The selected channel_id is invalid."
// "The email has already been taken."
```

| Method | Returns | Description |
|--------|---------|-------------|
| `RecordRules::exists(string $entityClass, string $property = 'id')` | `Exists` | A record whose `$property` equals the value must exist |
| `RecordRules::unique(string $entityClass, string $property)` | `Unique` | No record may already have the value in `$property` |
| `where(string $property, mixed $value)` | a new rule | On either rule: the record must also have `$property` equal to `$value`; `null` matches `NULL` |
| `Unique::ignore(mixed $id)` | a new rule | Leave out the record with this primary key (the one being updated) |
| `Unique::caseInsensitive()` | a new rule | Compare with `LOWER()` on both sides, on every database |

Every method returns a new rule and leaves the original unchanged. Like the other rules, both pass an empty value (add `required` to reject it), and both fail an array.

`marko/validation` holds the rules and the `RecordLookupInterface` contract, but no lookup. Install [`marko/validation-database`](/docs/packages/validation-database/) for one: until a driver is installed, resolving `RecordRules` throws `NoDriverException` naming the package. There is no `exists:` or `unique:` rule string, because it would name a table and columns the entity already knows; the parser throws `InvalidArgumentException` pointing to `RecordRules`. See [`marko/validation-database`](/docs/packages/validation-database/) for case sensitivity per database, and for why `Unique` does not replace a unique index.

### Validating URLs

`url` accepts only `http` and `https` URLs that have a host. Values such as `javascript://%0aalert(1)`, `file:///etc/passwd` and `gopher://127.0.0.1:6379/_x` fail, so a validated URL is safe to render as a link's `href`. List the schemes to allow others; the list replaces the default, and schemes compare case-insensitively:

```php
use Marko\Validation\Rules\Url;

$errors = $this->validator->validate($input, [
    'website' => 'nullable|url',               // http and https only
    'mirror' => 'nullable|url:http,https,ftp', // also ftp
    'api' => ['required', new Url('https')],   // https only
]);
```

`url` is **not** an SSRF guard. It checks the shape and scheme of a URL, not where it points: `http://127.0.0.1/` and `http://169.254.169.254/` pass. Before your server fetches a user-supplied URL (webhooks, link previews, imports), resolve the host and reject private, loopback and link-local addresses.

### Validating File Uploads

The validator checks an array, and uploaded files live apart from the form fields on the [request](/docs/packages/routing/#handling-file-uploads). Merge them into the data you validate:

```php title="app/profile/src/Controllers/AvatarController.php"
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Validation\Contracts\ValidatorInterface;

class AvatarController
{
    public function __construct(
        private ValidatorInterface $validator,
    ) {}

    #[Post('/profile/avatar')]
    public function upload(Request $request): Response
    {
        $this->validator->validateOrFail([...$request->input(), ...$request->files()], [
            'name' => 'required|string|max:100',
            'avatar' => 'required|file|image|max_size:2048',
            'resume' => 'nullable|file|mimes:pdf,docx|max_size:5120',
        ]);

        $avatar = $request->file('avatar');
        $avatar->moveTo('/var/www/storage/avatars/' . bin2hex(random_bytes(16)) . '.' . $avatar->guessExtension());

        return new Response('Saved');
    }
}
```

A failure throws `ValidationException`, which renders as a 422 with the file errors under their field names, exactly like any other rule. A file field overrides a form field of the same name in the merged array.

The type rules never trust what the client sent. `image`, `mimes` and `mimetypes` read the MIME type that `UploadedFile::mimeType()` detects from the file contents, so a text file named `avatar.png` with a `Content-Type` of `image/png` fails `image` and `mimes:png`. `image` accepts JPEG, PNG, GIF and WebP only: SVG can carry script, so allow it explicitly with `mimes:svg` or `mimetypes:image/svg+xml` when you serve it safely. The `image/*` wildcard does not match SVG either: `mimetypes:image/*` rejects an SVG upload, and `mimetypes:image/*,image/svg+xml` accepts it.

Every file rule fails a value that is not an uploaded file, and an upload that failed or was already moved. Failed uploads get a message that says why --- for example `The avatar file is larger than the server allows.` when the file exceeded `upload_max_filesize`.

Sizes are in kilobytes (1 KB = 1024 bytes) and the limits are inclusive. `max`, `min` and `between` keep their meaning for strings, numbers and arrays; given a file, they fail with a message that points to `max_size` / `min_size` (`The avatar field is a file: use max_size:2048 to limit its size in kilobytes.`). `max_size` and `min_size` without a number throw `InvalidArgumentException`, as do `mimes` and `mimetypes` without a list.

For a multi-file input (`name="photos[]"`), `array` and `max:5` on `photos` check the list and count its files, and rules on `photos.*` check each file (see [Multi-File Uploads](#multi-file-uploads)).

The file rules depend only on `Marko\Core\Contracts\UploadedFileInterface`, which `Marko\Routing\Http\UploadedFile` implements, so `marko/validation` does not require `marko/routing`.

### Validating Arrays with Wildcards

Rules keys use dot paths for nested data (`address.city`). A `*` segment matches every item of an array, so one rule checks each element:

```php
$this->validator->validate($input, [
    'tags' => 'required|array',
    'tags.*' => 'string|max:30',          // every tag
    'items' => 'required|array|min:1',
    'items.*.name' => 'required|string',  // the name of every item
    'items.*.quantity' => 'required|integer|min:1',
    'matrix.*.*' => 'integer',            // every cell of a list of lists
]);
```

The validator expands each wildcard key against the data before it runs the rules, so `items.*.name` becomes `items.0.name`, `items.1.name`, and so on. Errors are keyed by that concrete path, which tells the client exactly which item failed:

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "items.1.name": ["The items.1.name field is required."],
        "matrix.0.2": ["The matrix.0.2 field must be an integer."]
    }
}
```

A few rules govern expansion:

- **Missing or empty parents expand to nothing.** When `items` is absent, `null`, `''` or `[]`, `items.*.name` checks nothing and reports no errors. To require at least one item, put `required|array` or `array|min:1` on the parent (`items`).
- **A non-array where a list is expected fails loudly.** When a `*` meets any other value (a string, a number, a single uploaded file), the validator reports `The items field must be an array to apply the items.*.name rules.` under the path it reached. A wildcard rule never passes silently because the data has the wrong shape.
- **`*` is always a wildcard.** A rules key cannot address a literal `*` array key.
- **Rule strings are parsed up front.** An unknown rule under a wildcard key throws `InvalidArgumentException` even when the parent is empty.

### Cross-Field Rules Inside Rows

`confirmed` looks for its `_confirmation` field next to the field it validates, so `users.*.pin` checks `users.0.pin` against `users.0.pin_confirmation`.

`same` and `different` take an absolute dot path. A `*` in that path is replaced by the current row's index, matching the key's wildcards from left to right:

```php
$this->validator->validate($input, [
    'shipping.email' => 'same:billing.email',             // absolute path, no wildcard
    'items.*.sku_check' => 'same:items.*.sku',            // items.3.sku_check is compared with items.3.sku
    'routes.*.legs.*.to' => 'different:routes.*.legs.*.from',
]);
```

A path with more `*` than its rules key (`'code' => 'same:items.*.sku'`) has no row to fill in, so the validator throws `InvalidArgumentException` instead of comparing against nothing.

### Multi-File Uploads

An `<input type="file" name="photos[]" multiple>` arrives as a list of uploaded files under `photos`. Validate the list on `photos` and each file on `photos.*`:

```php title="app/gallery/src/Controllers/GalleryController.php"
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Validation\Contracts\ValidatorInterface;

class GalleryController
{
    public function __construct(
        private ValidatorInterface $validator,
    ) {}

    #[Post('/gallery')]
    public function store(Request $request): Response
    {
        $this->validator->validateOrFail([...$request->input(), ...$request->files()], [
            'photos' => 'required|array|max:10',
            'photos.*' => 'image|max_size:2048',
        ]);

        // every file in $request->files('photos') is an image of at most 2 MB

        return new Response('Saved');
    }
}
```

An invalid upload fails with a 422 whose errors are keyed per file (`photos.0`, `photos.3`). A single file sent as `photos` instead of `photos[]` fails with the "must be an array" error rather than skipping the per-file rules.

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

### Custom Implicit Rules

A custom rule that must run on an empty or absent value implements `ImplicitRuleInterface`. Its `requiresValue()` tells the validator whether the field must hold a value given the data. When it returns `true`, the field's other rules run on the empty value as well; when it returns `false`, only the implicit rules run.

```php
use Marko\Validation\Contracts\ImplicitRuleInterface;

class RequiredForCountry implements ImplicitRuleInterface
{
    public function __construct(
        private string $country,
    ) {}

    public function passes(
        string $field,
        mixed $value,
        array $data,
    ): bool {
        return !$this->requiresValue($field, $data) || ($value !== null && $value !== '');
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        return "The $field field is required for $this->country addresses.";
    }

    public function requiresValue(
        string $field,
        array $data,
    ): bool {
        return ($data['country'] ?? null) === $this->country;
    }
}

// Usage: state is required, and must be two letters, only for US addresses
$this->validator->validate($input, [
    'state' => [new RequiredForCountry('US'), 'alpha', 'size:2'],
]);
```

A rule that refers to another field which may contain `*` also implements [`WildcardAwareRuleInterface`](#wildcardawareruleinterface).

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

Under a wildcard key, `$field` is the concrete path being checked (`items.0.name`).

### ImplicitRuleInterface

```php
interface ImplicitRuleInterface extends RuleInterface
{
    public function requiresValue(string $field, array $data): bool;
}
```

The validator always runs an implicit rule, even on an empty value or an absent key. When any implicit rule's `requiresValue()` returns `true`, the field's other rules run on an empty value too. `Required` (always `true`), `RequiredIf`, `RequiredUnless`, `RequiredWith`, `RequiredWithout` (their condition), `Present`, `Prohibited`, `Accepted` and `Declined` (always `false`) implement it. See [Custom Implicit Rules](#custom-implicit-rules).

### WildcardAwareRuleInterface

```php
interface WildcardAwareRuleInterface extends RuleInterface
{
    public function forWildcardIndexes(string $key, array $indexes): RuleInterface;
}
```

Implement it on a custom rule that refers to another field which may contain `*`. Before checking each concrete field, the validator passes the rules key and the indexes its wildcards matched (`['3']` for `items.3.sku_check` under `items.*.sku_check`), and uses the rule it returns. `Same`, `Different`, `After`, `Before`, `AfterOrEqual`, `BeforeOrEqual`, `Distinct`, `RequiredIf`, `RequiredUnless`, `RequiredWith` and `RequiredWithout` implement it; throw `InvalidArgumentException` when the referenced field has more `*` than there are indexes.

### NumericAwareRuleInterface

```php
interface NumericAwareRuleInterface extends RuleInterface
{
    public function asNumeric(): RuleInterface;
}
```

Implement it on a custom size rule whose meaning for a numeric string depends on the field's other rules. When a field's rules include `numeric` or `integer`, the parser replaces each such rule with the one `asNumeric()` returns. `Min`, `Max`, `Between`, and `Size` implement it (see [Numeric-Aware Rules](#numeric-aware-rules)).

### RecordLookupInterface

```php
interface RecordLookupInterface
{
    public function exists(string $entityClass, string $property, mixed $value, array $where = []): bool;
    public function isTaken(
        string $entityClass,
        string $property,
        mixed $value,
        mixed $ignoreId = null,
        array $where = [],
        bool $caseInsensitive = false,
    ): bool;
}
```

The contract behind `Exists` and `Unique`; `$where` maps property names to values. [`marko/validation-database`](/docs/packages/validation-database/) implements it. Bind your own implementation to look records up somewhere else.

### RecordRules

```php
readonly class RecordRules
{
    public function __construct(RecordLookupInterface $lookup);
    public function exists(string $entityClass, string $property = 'id'): Exists;
    public function unique(string $entityClass, string $property): Unique;
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

### ValidationException

```php
class ValidationException extends Exception implements HttpExceptionInterface
{
    public static function withErrors(ValidationErrors $errors): self;
    public function errors(): ValidationErrors;
    public function getContext(): string;
    public function getSuggestion(): string;
    public function getStatusCode(): int;      // 422
    public function getHeaders(): array;       // []
    public function getResponseData(): array;  // ['message' => ..., 'errors' => $errors->all()]
}
```
