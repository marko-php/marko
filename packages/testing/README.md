# marko/testing

Testing utilities for Marko---reusable fakes with built-in assertions, and an in-process HTTP test client for feature tests.

## Overview

`marko/testing` provides a set of in-memory fakes that replace real infrastructure dependencies during tests. Instead of mocking interfaces by hand or spinning up real services, you drop in a fake, run your code, and call assertion methods directly on the fake. This removes hundreds of lines of boilerplate and keeps tests focused on behavior.

## Installation

```bash
composer require marko/testing --dev
```

## Pest Base Test Case

`TestCase` registers PSR-4 autoloaders for `app/*` and `modules/*` before tests run, so module classes resolve without per-project Composer classmap configuration. No container or application boot is performed.

Wire it as the global base in `tests/Pest.php`:

```php title="tests/Pest.php"
use Marko\Testing\TestCase;

uses(TestCase::class)->in(__DIR__);
```

After that, every Pest test in the project can reference module and app classes directly---no additional Composer path repos or classmaps required.

## HTTP Tests

`TestClient` sends requests through your application in process (real router, middleware and controllers) and boots the application once for many requests:

```php
use Marko\Testing\Http\TestClient;

$client = TestClient::boot(dirname(__DIR__));

$client->postJson('/api/shows/42/events', ['type' => 'view'])
    ->assertStatus(202)
    ->assertJsonPath('data.type', 'view');

$client->actingAs($user)->get('/dashboard')->assertOk();
```

Cookies persist across requests like a browser's, so session-backed flows work. Assertions on the returned `TestResponse` throw `AssertionFailedException` with the status and a body excerpt. See the [HTTP tests docs](https://marko.build/docs/packages/testing/#http-tests).

## Database Tests

With `marko/database` and a driver installed, `TestDatabase` boots and migrates your application once per process, and `RefreshDatabase` wraps each test in a transaction that is rolled back afterwards:

```php
use Marko\Testing\Database\RefreshDatabase;
use Marko\Testing\Database\TestDatabase;

beforeEach(function () {
    $this->database = TestDatabase::boot(dirname(__DIR__));
    $this->refresh = new RefreshDatabase($this->database);
    $this->refresh->begin();
    $this->http = $this->database->client(); // shares the test transaction
});

afterEach(fn () => $this->refresh->rollback());
```

`TruncateDatabase` empties the entity tables instead, for code that must see committed data. Both refuse to run in production, and `TruncateDatabase` runs only in a testing environment (`testing`, `test`); set `APP_ENV=testing`. See the [database tests docs](https://marko.build/docs/packages/testing/#database-tests).

## Available Fakes

`FakeEventDispatcher`, `FakeBroadcaster`, `FakeMailer`, `FakeNotificationSender`, `FakeQueue`, `FakeSession`, `FakeCookieJar`, `FakeLogger`, `FakeConfigRepository`, `FakeAuthenticatable`, `FakeUserProvider`, `FakeUserPersister`, `FakeGuard`, `FakeHttpClient`, `FakeEncryptor`, `FakeClock`, `FakeFilesystem`, `FakeSleeper`, `FakeConfirmationPrompter`


## Usage

### FakeEventDispatcher

```php
use Marko\Testing\Fake\FakeEventDispatcher;

$dispatcher = new FakeEventDispatcher();
$dispatcher->dispatch(new OrderPlaced($order));

$dispatcher->assertDispatched(OrderPlaced::class);
$dispatcher->assertDispatchedCount(OrderPlaced::class, 1);
$dispatcher->assertNotDispatched(OrderShipped::class);
```

### FakeMailer

```php
use Marko\Testing\Fake\FakeMailer;

$mailer = new FakeMailer();
$mailer->send($message);

$mailer->assertSent(WelcomeEmail::class);
$mailer->assertSentCount(WelcomeEmail::class, 1);
$mailer->assertNothingSent();
```

### FakeQueue

```php
use Marko\Testing\Fake\FakeQueue;

$queue = new FakeQueue();
$queue->push(new ProcessOrder($order));

$queue->assertPushed(ProcessOrder::class);
$queue->assertPushedCount(ProcessOrder::class, 1);
$queue->assertNotPushed(SendInvoice::class);
$queue->assertNothingPushed();
```

### FakeSession

```php
use Marko\Testing\Fake\FakeSession;

$session = new FakeSession();
$session->put('user_id', 42);

$value = $session->get('user_id'); // 42
$session->forget('user_id');
```

### FakeCookieJar

```php
use Marko\Testing\Fake\FakeCookieJar;

$cookies = new FakeCookieJar();
$cookies->set('token', 'abc123');

$value = $cookies->get('token'); // 'abc123'
```

### FakeLogger

```php
use Marko\Testing\Fake\FakeLogger;

$logger = new FakeLogger();
$logger->info('User logged in');
$logger->error('Something failed');

$logger->assertLogged('User logged in');
$logger->assertNothingLogged();
```

### FakeConfigRepository

```php
use Marko\Testing\Fake\FakeConfigRepository;

$config = new FakeConfigRepository([
    'auth.defaults.guard' => 'web',
    'app.name' => 'Marko',
]);

$value = $config->get('auth.defaults.guard'); // 'web'
```

### FakeAuthenticatable

```php
use Marko\Testing\Fake\FakeAuthenticatable;

$user = new FakeAuthenticatable(id: 1, password: 'hashed-secret');

$user->getAuthIdentifier(); // 1
$user->getAuthPassword();   // 'hashed-secret'
```

### FakeUserProvider

```php
use Marko\Testing\Fake\FakeUserProvider;
use Marko\Testing\Fake\FakeAuthenticatable;

$user = new FakeAuthenticatable(id: 1);
$provider = new FakeUserProvider($user);

$found = $provider->retrieveById(1); // returns $user
```

### FakeGuard

```php
use Marko\Testing\Fake\FakeGuard;
use Marko\Testing\Fake\FakeAuthenticatable;

$guard = new FakeGuard(name: 'web', attemptResult: true);

// Set a user directly
$user = new FakeAuthenticatable(id: 1);
$guard->setUser($user);
$guard->assertAuthenticated();

// Test login attempt
$guard->attempt(['email' => 'user@example.com', 'password' => 'secret']);
$guard->assertAttempted();

// Assert no user logged in
$guard->logout();
$guard->assertGuest();
$guard->assertLoggedOut();
```

### FakeHttpClient

```php
use Marko\Http\HttpResponse;
use Marko\Testing\Fake\FakeHttpClient;
use Marko\Testing\Fake\Http\RecordedRequest;

$http = new FakeHttpClient();
$http->stub('https://api.example.com/orders/*', new HttpResponse(200, '{"id":1}'));

$service = new OrderSync($http);
$service->push($order);

$http->assertSent(fn (RecordedRequest $r) => $r->method === 'POST');
$http->assertSentCount(1);
```

### FakeEncryptor

```php
use Marko\Testing\Fake\FakeEncryptor;

$encryptor = new FakeEncryptor();
$encrypted = $encryptor->encrypt('123-45-6789', 'users.ssn');

$encryptor->decrypt($encrypted, 'users.ssn');   // '123-45-6789'
$encryptor->decrypt($encrypted, 'users.email'); // throws DecryptionException: AAD mismatch

$encryptor->assertEncrypted('123-45-6789', 'users.ssn');
$encryptor->assertDecrypted(aad: 'users.ssn');
```

### KnownDriversValidator

```php
use Marko\Testing\KnownDrivers\KnownDriversValidator;

KnownDriversValidator::assertDocsUrlsResolveToValidPattern(
    __DIR__ . '/../known-drivers.php',
);

KnownDriversValidator::assertSkeletonSuggestContainsAll(
    __DIR__ . '/../known-drivers.php',
    __DIR__ . '/../../skeleton/composer.json',
);
```

## API Reference

### TestCase

- `setUp(): void` — Calls `registerModuleAutoloaders()` before each test
- `registerModuleAutoloaders(): void` — Registers PSR-4 autoloaders for `app/*` and `modules/*`; safe to call multiple times (skips already-registered roots)
- `projectRoot(): string` — Walks up the directory tree from cwd to find the nearest ancestor containing `vendor/`

### FakeEventDispatcher

- `dispatch($event): void` — Record a dispatched event
- `dispatched(string $class): array` — Return all dispatched events of a class
- `assertDispatched(string $class): void` — Assert an event was dispatched
- `assertNotDispatched(string $class): void` — Assert an event was not dispatched
- `assertDispatchedCount(string $class, int $count): void` — Assert exact dispatch count

### FakeMailer

- `send($message): void` — Record a sent message
- `assertSent(string $class): void` — Assert a message was sent
- `assertNothingSent(): void` — Assert no messages were sent
- `assertSentCount(string $class, int $count): void` — Assert exact sent count

### FakeQueue

- `push($job): PushResult` — Record a pushed job and return a pushed `PushResult` with its ID
- `assertPushed(string $class): void` — Assert a job was pushed
- `assertNotPushed(string $class): void` — Assert a job was not pushed
- `assertPushedCount(string $class, int $count): void` — Assert exact pushed count
- `assertNothingPushed(): void` — Assert no jobs were pushed

### FakeLogger

- `info(string $message): void`, `error()`, `warning()`, etc. — Record log entries
- `assertLogged(string $message): void` — Assert a message was logged
- `assertNothingLogged(): void` — Assert nothing was logged

### FakeGuard

- `new FakeGuard(string $name, bool $attemptResult)` — Create guard; `$attemptResult` controls what `attempt()` returns
- `setUser(?AuthenticatableInterface $user): void` — Set the current authenticated user
- `attempt(array $credentials): bool` — Record credentials attempt and return configured result
- `login(AuthenticatableInterface $user): void` — Log in a user directly
- `logout(): void` — Clear current user and record logout
- `assertAuthenticated(): void` — Assert a user is currently authenticated
- `assertGuest(): void` — Assert no user is authenticated
- `assertAttempted(?callable $callback = null): void` — Assert attempt() was called, optionally matching credentials via callback
- `assertNotAttempted(): void` — Assert attempt() was never called
- `assertLoggedOut(): void` — Assert logout() was called

### FakeHttpClient

- `stub(string $urlPattern, HttpResponse|HttpException $response): self` — Respond to matching URLs (`*` wildcard)
- `queue(HttpResponse|HttpException ...$responses): self` — Sequential responses for unmatched URLs
- `preventStrayRequests(bool $prevent = true): self` — Throw on unmatched requests (default on)
- `assertSent(?callable $callback = null): void`, `assertNotSent(callable $callback): void`, `assertSentCount(int $expected): void`, `assertNothingSent(): void`

### TestClient

- `TestClient::boot(string $basePath): self`, `TestClient::forApplication(Application $app): self` — Boot once, serve many requests
- `get()`, `post()`, `put()`, `patch()`, `delete()`, `options()`, `head()`, `getJson()`, `postJson()`, `putJson()`, `patchJson()`, `deleteJson()`, `call()` — Send a request, return a `TestResponse`
- `withHeaders()`, `withServerVariables()`, `withCookie()`, `withoutCookies()`, `withFile()`, `withFiles()`, `actingAs($user, ?string $guard = null)` — Client state for later requests
- `cookies()`, `cookieJar()` — The cookie jar, scoped by path, domain, `Secure`, expiry and `SameSite` like a browser
- `withoutResetting(ResettableInterface ...$services): static` — Services the client must not reset between requests

### TestDatabase, RefreshDatabase, TruncateDatabase

- `TestDatabase::boot(string $basePath, bool $fresh = false): self` — Boot and migrate once per process; refuses production, and `fresh: true` runs only in a testing environment
- `application()`, `connection()`, `transaction()`, `client()`, `seedTable()`, `getTableRowCount()`, `appliedMigrations()`
- `new RefreshDatabase(TestDatabase $database)` — `begin()`, `rollback()`, `runAfterCommitCallbacks()`
- `new TruncateDatabase(TestDatabase $database)` — `truncate()`, `tables()`

### TestResponse

- `assertStatus()`, `assertOk()`, `assertCreated()`, `assertNoContent()`, `assertNotFound()`, `assertForbidden()`, `assertUnauthorized()`, `assertUnprocessable()`, `assertRedirect(?string $to = null)`
- `assertHeader()`, `assertHeaderMissing()`, `assertCookie()`, `assertCookieMissing()`, `assertSee()`, `assertDontSee()`
- `assertJson()`, `assertExactJson()`, `assertJsonPath()`, `assertJsonCount()`, `assertJsonMissingPath()`
- `status()`, `body()`, `header()`, `json()`, `response()`

### KnownDriversValidator

- `assertDocsUrlsResolveToValidPattern(string $knownDriversPath): void` — Assert every key in `known-drivers.php` follows the `marko/*` prefix pattern
- `assertSkeletonSuggestContainsAll(string $knownDriversPath, string $skeletonComposerPath): void` — Assert the skeleton's `suggest` block contains every entry from `known-drivers.php` with matching descriptions

## Pest Expectations

`marko/testing` ships Pest custom expectations. They register automatically through a Pest plugin (`extra.pest.plugins`), so `Pest.php` needs no `require`. Pest 4 is required for the expectations; the fakes and their `assert*()` methods work without it.

```php
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\Testing\Fake\FakeGuard;
use Marko\Testing\Fake\FakeMailer;
use Marko\Testing\Fake\FakeQueue;
use Marko\Testing\Fake\FakeLogger;

// FakeEventDispatcher
expect($dispatcher)->toHaveDispatched(OrderPlaced::class);

// FakeMailer
expect($mailer)->toHaveSent();
expect($mailer)->toHaveSent(fn ($msg) => $msg instanceof WelcomeEmail);

// FakeQueue
expect($queue)->toHavePushed(ProcessOrder::class);

// FakeLogger
expect($logger)->toHaveLogged('User logged in');

// FakeGuard
expect($guard)->toHaveAttempted();
expect($guard)->toHaveAttempted(fn ($creds) => $creds['email'] === 'user@example.com');
expect($guard)->toBeAuthenticated();

// FakeHttpClient
expect($http)->toHaveSentRequest(fn ($request) => $request->method === 'POST');

// TestResponse
expect($response)->toHaveStatus(201);
expect($response)->toHaveJsonPath('data.status', 'live');
```

## Documentation

Full usage, API reference, and examples: [marko/testing](https://marko.build/docs/packages/testing/)
