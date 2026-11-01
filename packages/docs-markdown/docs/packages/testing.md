---
title: marko/testing
description: Reusable fakes with built-in assertions that eliminate test boilerplate.
---

Testing utilities for Marko — reusable fakes with built-in assertions that eliminate test boilerplate. This package provides in-memory fakes for the core Marko contracts: events, broadcasting, mail, notifications, queues, sessions, cookies, logging, config, authentication, guards, HTTP clients, encryption, the clock, filesystems, and console confirmations. Each fake records interactions and exposes assertion methods so your tests stay focused on behavior rather than mock setup. An in-process HTTP test client (`TestClient`) sends requests through your real routes, middleware and controllers for feature tests. Pest expectation extensions (`toHaveDispatched`, `toHaveBroadcast`, `toHaveSent`, `toHaveNotified`, `toHavePushed`, `toHaveStored`, `toHaveLogged`, `toHaveAttempted`, `toBeAuthenticated`, `toHaveStatus`, `toHaveJsonPath`) are included for fluent assertions.

Available fakes: `FakeEventDispatcher`, `FakeBroadcaster`, `FakeMailer`, `FakeNotificationSender`, `FakeQueue`, `FakeSession`, `FakeCookieJar`, `FakeLogger`, `FakeConfigRepository`, `FakeAuthenticatable`, `FakeUserProvider`, `FakeGuard`, `FakeHttpClient`, `FakeEncryptor`, `FakeClock`, `FakeFilesystem`, `FakeSleeper`, `FakeConfirmationPrompter`.

## Installation

```bash
composer require marko/testing --dev
```

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

### FakeBroadcaster

Implements `BroadcasterInterface` from [`marko/broadcasting`](/docs/packages/broadcasting/). A string channel matches by name; a `Channel`, `PrivateChannel` or `PresenceChannel` also matches its kind (public, private or presence). The optional callback receives the event data and id.

```php
use Marko\Broadcasting\PrivateChannel;
use Marko\Testing\Fake\FakeBroadcaster;

$broadcaster = new FakeBroadcaster();
$broadcaster->broadcast(new PrivateChannel('orders.7'), 'order.shipped', ['id' => 7]);

$broadcaster->assertBroadcast('orders.7', 'order.shipped');
$broadcaster->assertBroadcast(new PrivateChannel('orders.7'), 'order.shipped', fn (array $data) => $data['id'] === 7);
$broadcaster->assertNotBroadcast('orders.7', 'order.cancelled');
$broadcaster->assertBroadcastCount(1);
```

### FakeMailer

```php
use Marko\Testing\Fake\FakeMailer;

$mailer = new FakeMailer();
$mailer->send($message);

$mailer->assertSent();
$mailer->assertSent(fn (Message $m) => $m->subject === 'Welcome');
$mailer->assertSentCount(1);
$mailer->assertNothingSent();
```

### FakeNotificationSender

Implements `NotificationSenderInterface` from [marko/notification](/docs/packages/notification/). Bind it in place of the real sender; code typed against the concrete `NotificationSender` is not faked.

```php
use Marko\Notification\Contracts\NotificationSenderInterface;
use Marko\Testing\Fake\FakeNotificationSender;

$notifications = new FakeNotificationSender();
$container->instance(NotificationSenderInterface::class, $notifications);

// ... run the code under test ...

$notifications->assertSentTo($user, OrderShippedNotification::class);
$notifications->assertSentTo(
    $user,
    OrderShippedNotification::class,
    fn (OrderShippedNotification $n, array $channels) => $n->trackingNumber === 'TRK-1' && in_array('mail', $channels, true),
);
$notifications->assertQueuedTo($admin, OrderShippedNotification::class);
$notifications->assertNotSentTo($guest, OrderShippedNotification::class);
$notifications->assertSentTimes(OrderShippedNotification::class, 1);
$notifications->assertNothingSent();
```

Each notifiable gets one record in the public `$notifications` property: the `notifiable`, the `notification`, the `channels` a real send would use (resolved by the same `ChannelResolver` as `NotificationSender`) and `queued`. Notifiables match by `getNotifiableType()` + `getNotifiableId()` (ids compared as strings), so a reloaded entity still matches.

Sent and queued are kept apart: `assertSentTo()` and `assertSentTimes()` see only `send()`, `assertQueuedTo()` only `queue()`. `assertNotSentTo()` and `assertNothingSent()` fail on either, because a queued notification is still delivered.

A queued `SendNotificationJob` always resolves the concrete `NotificationSender`, so running the queue in a test delivers for real even with the fake bound.

### FakeQueue

```php
use Marko\Testing\Fake\FakeQueue;

$queue = new FakeQueue();
$queue->push(new SendEmailJob($user));

$queue->assertPushed(SendEmailJob::class);
$queue->assertPushed(SendEmailJob::class, fn ($job) => $job->userId === $user->id);
$queue->assertPushedCount(1);
$queue->assertNothingPushed();
$queue->assertNotPushed(ProcessPaymentJob::class);
```

### FakeSession

```php
use Marko\Testing\Fake\FakeSession;

$session = new FakeSession();
$session->start();
$session->set('user_id', 42);

expect($session->started)->toBeTrue()
    ->and($session->get('user_id'))->toBe(42)
    ->and($session->has('user_id'))->toBeTrue();

$session->destroy();
expect($session->destroyed)->toBeTrue();
```

`has()` returns `true` when a key is stored, even if its value is `null`. This matches the behavior of the production `Session` implementation.

To test code against a [lazily started](/docs/packages/session/#lazy-start) session, call `arm()` instead of `start()`. The fake records it in `$armed`, reports `isAvailable()` as `true`, and starts on the first data access, as `Session` does for a cookieless request:

```php
$session = new FakeSession();
$session->arm();

$guard->login($user);

expect($session->started)->toBeTrue()
    ->and($session->regenerated)->toBeTrue();
```

Unlike `Session`, a fake that was neither armed nor started does not throw on data access, and `started` stays `false`.

`isModified()` reports whether data changed since `start()` (or the ID was regenerated), and `discard()` sets the public `$discarded` flag without setting `$saved` --- the two calls `SessionMiddleware` makes for [lazy persistence](/docs/packages/session/#lazy-persistence).

### FakeCookieJar

```php
use Marko\Testing\Fake\FakeCookieJar;

$cookies = new FakeCookieJar();
$cookies->set('remember_me', 'token-value', minutes: 60);

expect($cookies->get('remember_me'))->toBe('token-value');

$cookies->delete('remember_me');
expect($cookies->get('remember_me'))->toBeNull();
```

### FakeLogger

```php
use Marko\Testing\Fake\FakeLogger;
use Marko\Log\LogLevel;

$logger = new FakeLogger();
$logger->info('User logged in', ['user_id' => 1]);
$logger->error('Payment failed');

$logger->assertLogged('User logged in');
$logger->assertLogged('User logged in', LogLevel::Info);
$logger->assertNothingLogged(); // Would fail here
$logger->clear();
$logger->assertNothingLogged(); // Passes after clear
```

### FakeConfigRepository

```php
use Marko\Testing\Fake\FakeConfigRepository;

$config = new FakeConfigRepository([
    'app.name' => 'Marko',
    'mail.driver' => 'smtp',
    'default.cache.ttl' => 3600,
    'scopes.store_1.cache.ttl' => 7200,
]);

expect($config->getString('app.name'))->toBe('Marko')
    ->and($config->getInt('default.cache.ttl'))->toBe(3600);

$config->set('app.debug', true);
expect($config->getBool('app.debug'))->toBeTrue();
```

### FakeGuard

```php
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeGuard;

$guard = new FakeGuard(name: 'web', attemptResult: true);

// Set current user directly
$user = new FakeAuthenticatable(id: 1);
$guard->setUser($user);

expect($guard->check())->toBeTrue()
    ->and($guard->id())->toBe(1);

// Simulate a login attempt
$guard->setUser(null);
$guard->attempt(['email' => 'user@example.com', 'password' => 'secret']);

$guard->assertAttempted();
$guard->assertAttempted(fn (array $creds) => $creds['email'] === 'user@example.com');

// Simulate logout
$guard->logout();
$guard->assertLoggedOut();
$guard->assertGuest();
```

### FakeEncryptor

`FakeEncryptor` implements `EncryptorInterface` from [`marko/encryption`](/docs/packages/encryption/), so code that encrypts values can be tested without a key. Encryption is a reversible encoding: the output starts with `fake-encrypted:`, never contains the plain text, and differs on every call as a real cipher's would. Any `FakeEncryptor` instance can decrypt another's output.

Like a real AEAD encryptor, the ciphertext is bound to the associated data (`$aad`) it was encrypted with. `decrypt()` with different associated data throws a `DecryptionException` naming both values, so a missing or wrong `$aad` fails the test instead of passing silently. A value it did not produce throws `DecryptionException::invalidPayload()`.

```php
use Marko\Testing\Fake\FakeEncryptor;

$encryptor = new FakeEncryptor();
$encrypted = $encryptor->encrypt('123-45-6789', 'users.ssn');

$encryptor->decrypt($encrypted, 'users.ssn');   // '123-45-6789'
$encryptor->decrypt($encrypted, 'users.email'); // throws DecryptionException

$encryptor->assertEncrypted('123-45-6789', 'users.ssn');
$encryptor->assertDecrypted(aad: 'users.ssn');
$encryptor->encrypted; // [['value' => '123-45-6789', 'aad' => 'users.ssn', 'encrypted' => 'fake-encrypted:...']]
```

Both arguments of `assertEncrypted()` and `assertDecrypted()` are optional; pass only what the test cares about. Only successful `decrypt()` calls are recorded. Like every fake, it is never bound automatically: pass it to the class under test, or register it with `$container->instance(EncryptorInterface::class, $encryptor)`.

### FakeClock

`FakeClock` implements the PSR-20 `Psr\Clock\ClockInterface`, so it drops into anything that takes the injected clock from [`marko/clock`](/docs/packages/clock/). It is frozen: time never moves on its own, so expiry, TTL and freshness checks can be tested at exact boundaries without `sleep()` or loose time ranges.

```php
use Marko\Testing\Fake\FakeClock;

$clock = new FakeClock('2026-01-01 12:00:00 UTC');
$guard = new TokenGuard($repository, $currentRequest, $clock, DatabaseTimezoneConfig::fromName('UTC'), $userProvider);

$clock->now();                         // always 2026-01-01 12:00:00
$clock->travel('+59 minutes');         // relative move
$clock->travelTo('2026-01-02 00:00');  // absolute jump
$clock->setNow(new DateTimeImmutable('2026-06-01 09:00:00 UTC'));

$clock->assertNowIs('2026-06-01 09:00:00 UTC');
```

`travel()` accepts any `DateTimeImmutable::modify()` string; a malformed modifier throws `DateMalformedStringException`. With no argument, the fake is frozen at the moment it was created.

### FakeFilesystem

`FakeFilesystem` implements `FilesystemInterface` from [`marko/filesystem`](/docs/packages/filesystem/) in memory, so tests check what code writes to a disk or a bucket without touching the real filesystem or S3. Both constructor arguments are required: the clock that stamps `lastModified` (a `FakeClock` keeps it exact) and the visibility of new files when a write passes no `visibility` option.

Put it in front of the code under test with [`FilesystemManagerInterface::useDisk()`](/docs/packages/filesystem/#replacing-a-disk). Replace a named disk, or pass `null` for the default disk (which `FilesystemInterface` forwards to):

```php
use Marko\Filesystem\Contracts\FilesystemManagerInterface;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeFilesystem;

$fake = new FakeFilesystem(clock: new FakeClock(), public: false);
$container->get(FilesystemManagerInterface::class)->useDisk('s3', $fake);

// ... run the code under test ...

$fake->assertExists('avatars/1.png');
$fake->assertMissing('avatars/tmp.png');
$fake->assertContent('reports/2026.csv', "id,total\n1,42\n");
$fake->assertDirectoryEmpty('uploads/pending');
$fake->assertWrittenCount(2);
$fake->written; // ['avatars/1.png', 'reports/2026.csv']
$fake->clear();
```

It behaves like a real driver, and one contract test suite runs against it and the local driver:

- `read()`, `readStream()`, `info()`, `size()`, `lastModified()`, `mimeType()`, `copy()`, `move()`, `setVisibility()` and `visibility()` throw `FileNotFoundException` for a missing file.
- Paths follow the shared [path rules](/docs/packages/filesystem/#path-rules), with backslashes rejected (the strictest driver rule, so a path that passes here works on every disk). `deleteDirectory('/')` throws `PathException::rootDeletion()`.
- Writing a file creates its parent directories, and a directory stays after its last file is deleted. Directories report `public` visibility, as a local `0755` directory does.
- `mimeType()` detects the type from the contents with `finfo`.

`$written` lists the destination of every call that stored contents (`write`, `writeStream`, `append`, `copy`, `move`); `assertWrittenCount()` counts it. Reads, deletes, visibility changes and directory creation are not writes. Assertions normalize the path the same way, so `/avatars/1.png` and `avatars/1.png` match.

### FakeSleeper

`FakeSleeper` implements `Marko\Database\Connection\SleeperInterface` from [`marko/database`](/docs/packages/database/) (install it to use this fake). It records each requested delay in milliseconds instead of sleeping, so you can assert the [backoff](/docs/packages/database/#backoff) between `transaction()` retries without waiting for it:

```php
use Marko\Database\Connection\TransactionBackoff;
use Marko\Testing\Fake\FakeSleeper;

$sleeper = new FakeSleeper();
$connection = new PgSqlConnection($config, transactionBackoff: new TransactionBackoff($sleeper));

// ... a transaction that conflicts twice, run with attempts: 3, backoff: 50

$sleeper->assertSlept(50, 50);
$sleeper->sleeps;            // [50, 50]
$sleeper->clear();
$sleeper->assertNotSlept();
```

The connection sleeps once per retry, including a `0` delay. It never sleeps after the last attempt, with `attempts: 1`, or in a nested `transaction()`. For repeatable default (jittered) delays, pass a seeded `new Randomizer(new Mt19937($seed))` as the second `TransactionBackoff` argument.

### FakeConfirmationPrompter

`FakeConfirmationPrompter` implements core's [`ConfirmationPrompterInterface`](/docs/packages/core/#asking-for-confirmation). Script one answer per question; it records each question asked:

```php
use Marko\Core\Command\Input;
use Marko\Testing\Fake\FakeConfirmationPrompter;

$prompter = new FakeConfirmationPrompter(answers: [true]);
$command = new PurgeCommand($prompter);

$command->execute(new Input(['marko', 'billing:purge']), $output);

$prompter->assertAsked('Delete all archived invoices?');
$prompter->asked; // ['Delete all archived invoices?']
```

Pass `interactive: false` to test the `--no-interaction` / no-terminal path: like the real prompter, `confirm()` then asks nothing and returns its `$default`, and `assertNothingAsked()` passes. Asking more questions than answers were scripted throws `AssertionFailedException`.

### FakeAuthenticatable and FakeUserProvider

```php
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeUserProvider;

$user = new FakeAuthenticatable(id: 1, password: 'hashed');
$provider = new FakeUserProvider(users: [1 => $user]);

$found = $provider->retrieveById(1);
expect($found)->toBe($user);

$valid = $provider->validateCredentials($user, ['password' => 'secret']);
expect($valid)->toBeTrue(); // Default validator always returns true

$provider->updateRememberToken($user, 'new-token', new DateTimeImmutable('+30 days'));
expect($provider->lastRememberTokenUpdate['token'])->toBe('new-token');
```

### FakeHttpClient

`FakeHttpClient` implements `HttpClientInterface` from [`marko/http`](/docs/packages/http/), so any service that takes the interface can be tested without a live server or Guzzle internals.

```php
use Marko\Http\Exceptions\ConnectionException;
use Marko\Http\HttpResponse;
use Marko\Testing\Fake\FakeHttpClient;
use Marko\Testing\Fake\Http\RecordedRequest;

$http = new FakeHttpClient();

// Exact URL, or * as a wildcard
$http->stub('https://api.example.com/orders/*', new HttpResponse(200, '{"id":1}', ['Content-Type' => 'application/json']));
$http->stub('https://api.example.com/fail', new HttpResponse(500, 'boom'));

// Simulate a network failure
$http->stub('https://api.example.com/slow', new ConnectionException('timeout'));

// Sequential responses for requests that match no stub, whatever their URL
$http->queue(new HttpResponse(201, ''), new HttpResponse(200, ''));

$service = new OrderSync($http);
$service->push($order);

$http->assertSent(fn (RecordedRequest $r) => $r->method === 'POST' && $r->json()['id'] === 1);
$http->assertSentCount(1);
$http->assertNotSent(fn (RecordedRequest $r) => str_contains($r->url, '/fail'));
```

To fake repeated headers such as two `Set-Cookie` values, pass them as `headerValues`. The code under test reads them back with `headerValues()`, and `headers()` returns them joined with `", "`:

```php
$http->stub('https://sso.example.com/*', new HttpResponse(200, '', headerValues: [
    'Set-Cookie' => ['session=abc; Expires=Wed, 21 Oct 2026 07:28:00 GMT', 'theme=dark'],
]));
```

Each request is resolved in this order: the first registered stub whose pattern matches the URL, then the next queued response. A request that matches neither is a **stray request** and throws `AssertionFailedException`, so a test never silently talks to an unexpected endpoint. Call `$http->preventStrayRequests(false)` to answer stray requests with an empty `200` response instead.

The fake applies the same rules as a real driver:

- Options are validated with `RequestOptions::validate()`, so an unknown key such as `form_param` (or the Guzzle-only `guzzle` key) throws `InvalidRequestOptionException`.
- A stubbed 4xx/5xx response throws `HttpException` with the response attached, unless the request passes `'http_errors' => false`, in which case the `HttpResponse` is returned.

Every sent request is recorded as a readonly `RecordedRequest` in `$http->requests`, with `method` (uppercased), `url` and `options`, plus helpers:

```php
$request = $http->requests[0];

$request->header('authorization'); // case-insensitive, null when absent
$request->json();                  // the 'json' option
$request->body();                  // raw 'body', encoded 'json', or encoded 'form_params'
```

### KnownDriversValidator

`KnownDriversValidator` is a static utility for package authors to assert that a package's `known-drivers.php` file is well-formed and stays in sync with the skeleton's `suggest` block. See [Known Drivers](/concepts/known-drivers/) for the file format and description-string conventions.

```php
use Marko\Testing\KnownDrivers\KnownDriversValidator;

// Assert every key in known-drivers.php follows the 'marko/*' prefix pattern
KnownDriversValidator::assertDocsUrlsResolveToValidPattern(
    __DIR__ . '/../known-drivers.php',
);

// Assert skeleton composer.json suggest block contains every entry from known-drivers.php
KnownDriversValidator::assertSkeletonSuggestContainsAll(
    __DIR__ . '/../known-drivers.php',
    __DIR__ . '/../../skeleton/composer.json',
);
```

Both methods check through `PHPUnit\Framework\Assert`, so each checked driver counts as an assertion and a test that only calls the validator is not reported as risky. `assertSkeletonSuggestContainsAll()` skips the test when the skeleton `composer.json` is missing or has no `suggest` block.

## HTTP Tests

`TestClient` sends requests through your application in process: the real router, global and route middleware (sessions, CSRF, auth, CORS, rate limits) and controllers all run, with no web server and no superglobals. It boots the application once and serves any number of requests, so a feature test costs about as much as a unit test.

```php
use Marko\Testing\Http\TestClient;

$client = TestClient::boot(basePath: $projectRoot); // boots the Application once

$client->get('/api/v1/shows/42')
    ->assertOk()
    ->assertJsonPath('data.status', 'live');

$client->withHeaders(['X-Request-Id' => 'abc'])
    ->postJson('/api/v1/shows/42/events', ['type' => 'view'])
    ->assertStatus(202);

$client->post('/login', ['email' => 'a@b.c', 'password' => 'secret']) // form-encoded
    ->assertRedirect('/dashboard')
    ->assertCookie('marko_session');

$client->actingAs($user)->get('/dashboard')->assertOk();
$client->withCookie('locale', 'nl')->get('/')->assertSee('Welkom');
```

Use `TestClient::forApplication($app)` to wrap an `Application` you booted yourself. `TestClient::boot()` throws when the base path does not exist.

### Pest setup

Create one client per test, so state from one test (cookies, `actingAs()`, headers) never reaches the next:

```php title="tests/Pest.php"
use Marko\Testing\Http\TestClient;

uses()->beforeEach(function () {
    $this->http = TestClient::boot(dirname(__DIR__));
})->in('Feature');
```

```php title="tests/Feature/ShowTest.php"
it('shows a live show', function () {
    $this->http->getJson('/api/v1/shows/42')
        ->assertOk()
        ->assertJsonPath('data.status', 'live');
});
```

### Sending requests

| Method | Sends |
|--------|-------|
| `get($uri, $query = [], $headers = [])`, `head(...)` | `$query` merged into the URI's query string |
| `post($uri, $data = [], $headers = [])`, `put`, `patch`, `delete`, `options` | `$data` as form fields (`Request::post()`), form-encoded |
| `postJson($uri, $data = [], $headers = [])`, `putJson`, `patchJson`, `deleteJson` | `json_encode($data)` as the body, with `Content-Type` and `Accept: application/json` |
| `getJson($uri, $query = [], $headers = [])` | A GET with `Accept: application/json`; GET carries no body, so `$query` is the query string |
| `json($method, $uri, $data = [], $headers = [])` | Any method with a JSON body |
| `call($method, $uri, $data = [], $headers = [], ?string $body = null)` | Any method; pass `$body` for a raw body (e.g. XML). Passing both form `$data` and a `$body` throws |

The client builds a `Marko\Routing\Http\Request` the way PHP would for a real request:

- Headers become `HTTP_*` server keys; `Content-Type` and `Content-Length` become `CONTENT_TYPE` and `CONTENT_LENGTH`.
- `REQUEST_METHOD`, `REQUEST_URI` (with the query string), `QUERY_STRING`, `HTTP_HOST` and `SERVER_NAME` (`localhost`, or the host of a full URL such as `https://shop.test/cart`), and `REMOTE_ADDR` (`127.0.0.1`) are set. A relative path is an HTTPS request (`HTTPS=on`, port 443). Only an explicit `http://` URL is plain HTTP.
- `withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])` overrides any server key for later requests.
- Controllers read the payload exactly as in production: `$request->json('type')`, `$request->post('email')`, `$request->input('page')`.
- Requests go through the real `Router`, so its method handling is the same as in production. A `head()` response never has a body, and HEAD falls back to the GET route when no `#[Head]` route exists. An `options()` call to a path that has no `#[Options]` route returns an automatic `204` with an `Allow` header. A method the path has no route for returns `405` with `Allow`. Global middleware runs on all of these.

### Client state

The client behaves like a browser, so what you set stays set for later requests on the same client:

- `withHeaders(array)` / `withHeader($name, $value)`: sent with every later request. Headers passed to one call win for that call only.
- **Cookie jar**: cookies a response sets are stored and sent back with later requests (as request cookies and a `Cookie` header). Session-backed flows such as a form login followed by a protected page, or a CSRF token round trip, just work. `withCookie()` and `withCookies(array)` add cookies, and `withoutCookies()` empties the jar. The jar scopes cookies the way a browser does; see [Cookie scope](#cookie-scope).
- `withFile($field, $path, ?$clientFilename = null, ?$clientMediaType = null)`: uploads a copy of the file with the **next** request as a `Marko\Routing\Http\UploadedFile` (multipart). The original file is never moved. A missing file throws `TestClientException`. `$field` uses form notation, so one request can carry several files; see [File uploads](#file-uploads).

### File uploads

Name the field the way an HTML form would. The controller gets the same nested shape PHP builds from `$_FILES`:

```php title="tests/Feature/GalleryTest.php"
$client
    ->withFile('photos[]', __DIR__ . '/fixtures/beach.jpg')
    ->withFile('photos[]', __DIR__ . '/fixtures/sunset.jpg')
    ->withFile('documents[passport]', __DIR__ . '/fixtures/passport.pdf')
    ->post('/gallery')
    ->assertOk();

// In the controller:
$request->files('photos');               // [UploadedFile (beach.jpg), UploadedFile (sunset.jpg)], in order
$request->file('documents.passport');    // UploadedFile (passport.pdf)
```

- `photos[]` adds one more file to the `photos` list. `documents[passport]` nests the file under `documents`. Fields can nest further, e.g. `gallery[photos][]`.
- `withFiles('photos', [$first, $second])` sends a list of files in one call. It does the same as one `withFile('photos[]', ...)` per path.
- A plain field such as `avatar` holds one file. Calling `withFile('avatar', ...)` twice throws `TestClientException` instead of dropping the first file. Using one name in two shapes also throws: a single file and a list (`photos` and `photos[]`), or a single file and nested fields. So does a malformed name such as `photos[`.

### Cookie scope

The jar follows RFC 6265, so a test can't pass by sending a cookie that a browser would keep back:

- **Path**: a cookie set with `Path=/admin` is sent to `/admin` and `/admin/users`, but not to `/api` or `/administrator`. A cookie set without `Path` gets the directory of the request that set it: a cookie set by `/account/login` gets `/account`.
- **Domain**: requests go to `localhost` unless the URI is a full URL (`http://shop.test/cart`). A cookie set without `Domain` is sent back only to the exact host that set it. One set with `Domain=shop.test` is also sent to its subdomains. A response that sets a `Domain` that doesn't cover the request host is ignored, as a browser would ignore it.
- **Public suffixes**: a response that sets `Domain` to a public suffix, such as `Domain=co.uk` from `a.example.co.uk` or `Domain=com`, is ignored, as browsers ignore it, so a misconfigured cookie fails in the test instead of only in production. `assertCookie()` still passes, because the response did send the cookie; check `cookieJar()` to see what the client kept. When the public suffix is the request host itself (`Domain=localhost` from `localhost`), the cookie is kept as host-only. Every single-label domain counts as a public suffix, plus a built-in list of common ones (`co.uk`, `com.au`, `co.jp`, `github.io`, ...); the client doesn't ship the full [Public Suffix List](https://publicsuffix.org), so a rarer suffix is treated as an ordinary domain.
- **Secure**: a `Secure` cookie is sent only over HTTPS. A relative path such as `/dashboard` is an HTTPS request to `localhost`, so `Secure` cookies (including `marko/session`'s session cookie, which is `Secure` by default) round-trip in ordinary tests. Pass an explicit `http://` URL, or set `withServerVariables(['HTTPS' => 'off'])`, to test plain HTTP: a `Secure` cookie is then not sent.
- **Expiry**: the jar records when each cookie expires and stops sending it once that time passes. `Max-Age`, counted from when the response is received, wins over `Expires`; a `Max-Age` of zero or less, or an `Expires` already past, removes the cookie at once. Time is read from the application's bound `ClockInterface`, the same clock that sets `REQUEST_TIME` on each request, so it agrees with code that expires cookies relative to that clock. Bind a [`FakeClock`](#fakeclock) (`$client->application()->container->instance(ClockInterface::class, $clock)`) and `travel()` it to test session or remember-me expiry. With no clock bound, the client uses `marko/clock`'s `SystemClock`.
- **SameSite**: every request is same-site unless it says otherwise, so `SameSite` changes nothing by default. A request is cross-site when it sends `Sec-Fetch-Site: cross-site`, or, without `Sec-Fetch-Site`, an `Origin` header whose site (registrable domain) differs from the target host's, or `Origin: null`. A cross-site request never carries `SameSite=Strict` cookies, and carries `SameSite=Lax` cookies only on `GET`. Cookies with `SameSite=None`, or with no `SameSite` attribute, are always sent (the client follows RFC 6265 here, not Chrome's Lax-by-default).
- **Same name**: cookies are stored per name, domain and path, so `token` on `/admin` and `token` on `/api` are separate cookies. A request that matches both gets both in its `Cookie` header, the more specific path first, and `$request->cookie('token')` returns that first one, as PHP does. When a response expires a cookie, only the cookie with that name, domain and path is removed.

`withCookie($name, $value, $path = '/', ?$domain = null, $secure = false)` adds a cookie that is sent to every host unless you pass `$domain`. A response that sets or expires a cookie with the same name and path replaces it. `cookies()` returns the cookies whose path is `/` (whatever their host or `Secure` flag) as name => value pairs. `cookieJar()` returns every cookie as a `Marko\Testing\Http\JarCookie`, with its `name`, `value`, `domain`, `path`, `secure`, `hostOnly`, `expiresAt` and `sameSite` properties. Both `cookies()` and `cookieJar()` first drop the cookies that have expired on the bound clock:

```php title="tests/Feature/AdminTest.php"
$client->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'secret']);

$client->get('/admin/dashboard')->assertOk();
$client->get('/api/me')->assertStatus(401); // the /admin cookie is not sent here

expect($client->cookieJar()[0]->path)->toBe('/admin');

// A cross-site form post does not carry the SameSite=Lax session cookie
$client->post('/admin/users/1/delete', headers: ['Origin' => 'https://evil.example'])
    ->assertRedirect('/admin/login');
```

:::caution
With an explicit `http://` URL, a `Secure` session cookie (the `marko/session` default) is not sent back, so a login doesn't carry over to the next request. Use relative paths or `https://` URLs for session flows, or set `'secure' => false` under `cookie` in your test environment's session config.
:::

### Acting as a user

`actingAs($user, ?string $guard = null)` authenticates every later request as `$user` without a login request and without writing to the session. It puts a `FakeGuard` holding the user in place of the guard named `$guard` (the default guard from `config/authentication.php` when `null`) in the application's `AuthManager`; for the default guard, the container's `GuardInterface` is replaced as well. `AuthMiddleware` and any controller that injects `GuardInterface` or `AuthManager` see the user.

```php
use Marko\Testing\Fake\FakeAuthenticatable;

$this->http->actingAs(new FakeAuthenticatable(id: 7))
    ->get('/dashboard')
    ->assertOk();

$this->http->actingAs($apiUser, 'api')->getJson('/api/me')->assertOk();
```

`actingAs()` needs the `marko/authentication` module loaded by the application.

### Request-scoped state and exceptions

Before each request, the client calls `reset()` on every resolved `ResettableInterface` service (`Marko\Core\RequestStateResetter`, the same code the RoadRunner worker runs), so the session, the session guard and similar request-scoped state never leak from one request to the next.

An exception thrown by a controller or middleware propagates out of `$client->get()`, so the test shows the real stack trace. HTTP exceptions (`HttpExceptionInterface`, e.g. `HttpException::notFound()`) are rendered into a response by the router, exactly as in production, so `assertNotFound()` and friends work on them.

The client opens no database connections of its own.

### Assertions

Requests return a `Marko\Testing\Http\TestResponse`. Every assertion returns the response, so they chain, and throws `AssertionFailedException` on failure. The message includes the response status and the first 500 bytes of the body:

```
Expected response status 200 but got 500.

Response status: 500
Response body: {"message":"Internal Server Error"}
```

- Status: `assertStatus($status)`, `assertOk()`, `assertCreated()`, `assertNoContent()` (also checks the body is empty), `assertUnauthorized()`, `assertForbidden()`, `assertNotFound()`, `assertUnprocessable()`
- Redirects: `assertRedirect(?string $to = null)` checks for a 3xx status with a `Location` header, matching `$to` exactly when given
- Headers (names are case-insensitive): `assertHeader($name, ?$value = null)`, `assertHeaderMissing($name)`
- Cookies the response sets: `assertCookie($name, ?$value = null)`, `assertCookieMissing($name)`
- Body: `assertSee($text)`, `assertDontSee($text)` (raw substring, not HTML-escaped)
- JSON (dot paths, with numeric segments for list items: `data.items.0.id`):
  - `assertJson(array $subset)`: the body contains the subset, compared recursively
  - `assertExactJson(array $data)`: the body equals the data; object key order is ignored
  - `assertJsonPath($path, $expected)`: the value at the path is identical (`===`) to `$expected`
  - `assertJsonCount($count, ?$path = null)`: the root, or the array at the path, has `$count` items
  - `assertJsonMissingPath($path)`: the path does not exist

- Downloads: `assertDownload(?string $filename = null)` checks for `Content-Disposition: attachment`, with the filename when given. Both `filename=` and the RFC 6266 `filename*=UTF-8''...` form are read (`filename*` wins), through `Marko\Routing\Http\ContentDisposition::fromHeader()`, so it works on a `FileResponse` and on any response that sets the header
- JSON validation errors (the `{message, errors}` body a `ValidationException` renders with a 422):
  - `assertJsonValidationErrors(array|string $fields)`: each field has at least one error. A `'field' => 'message'` entry also requires that exact message among the field's errors
  - `assertJsonMissingValidationErrors(array|string $fields = [])`: none of the fields has an error; with no fields, the body has no errors at all. An empty body (a 204) passes

A body that is not valid JSON fails a JSON assertion with a clear message. The accessors `status()`, `body()`, `header($name)`, `json(?$path = null)` and `response()` (the wrapped `Response`) are available for anything else.

### Session and auth assertions

The session assertions on `TestResponse` and the auth assertions on `TestClient` answer one question: what would the client's next request see? A `Marko\Testing\Http\SessionProbe` works it out without sending a request. It runs the same request-state reset a request runs (services passed to `withoutResetting()` are left alone), resumes the session from the client's session cookie the way `SessionMiddleware` does (the cookie named by `session.cookie.name` that a repeat of the last request would carry), reads it, and then discards it. A probe never writes the session. Without a session cookie, because none was set or the last response expired it, the session is empty and nobody is logged in.

```php
it('remembers the chosen plan and flashes a confirmation', function (): void {
    $this->http->post('/plans', ['plan' => 'pro'])
        ->assertRedirect('/billing')
        ->assertSessionHas('plan', 'pro')
        ->assertSessionHas('plan', fn (string $plan): bool => in_array($plan, ['pro', 'team'], true))
        ->assertSessionMissing('trial')
        ->assertSessionHasFlash('success', 'Plan updated.');
});

it('logs the user in and out', function (): void {
    $this->http->post('/login', ['email' => 'ada@example.com', 'password' => 'secret']);
    $this->http->assertAuthenticated()->assertAuthenticatedAs($ada);

    $this->http->post('/logout');
    $this->http->assertGuest();
});
```

- `assertSessionHas($key, $value = null)`: the session has `$key`. With `$value`, the stored value must be identical (`===`). A `Closure` is called with the stored value instead and must return `true`. Other callables, such as the string `'date'`, are compared as values. Keys are exact top-level session keys, not dot paths
- `assertSessionMissing($key)`
- `assertSessionHasFlash($type, ?$message = null)`: the session holds a `FlashBag` message of `$type`, and `$message` among them when given. The raw session data is read, never through `FlashBag::get()`, so asserting doesn't consume the flash: the next request still sees it
- `$client->assertAuthenticated(?string $guard = null)`, `$client->assertGuest(?string $guard = null)`: the real guard from `AuthManager::guard($guard)` (the default guard when `null`), with its cached user cleared, so it reads the session
- `$client->assertAuthenticatedAs($user, ?string $guard = null)`: compares `getAuthIdentifier()`, not object identity, so a fresh instance of the same user passes

Session assertions work only on the client's latest response. Once the client has sent another request, an earlier response is stale and its session assertions throw `TestClientException`, so they never quietly read newer state. A `TestResponse` you build yourself (`new TestResponse($response)`) has no session, and its session assertions throw `TestClientException` too.

:::note
After `actingAs()`, the guard is a `FakeGuard`, so the auth assertions report the `FakeGuard`'s user, not the session. A guard that implements `StatelessGuardInterface` (a token guard) keeps no state between requests, so the auth assertions throw `TestClientException` for it: assert on a response instead, e.g. `assertUnauthorized()`. The probe has no current request, so it never logs a user in from a remember-me cookie. Test remember-me flows with a real follow-up request.
:::

### Streamed and file responses

`TestClient` dispatches through the router and never calls `send()`, so a [streamed response](/docs/packages/routing/#file-and-streamed-responses) (`FileResponse`, `StreamedResponse`, an SSE `StreamingResponse`) has an empty `body()`. `streamedContent()` runs the response's `sendBody()` once under an output buffer and returns what it wrote (`''` for a HEAD request). It throws `AssertionFailedException` when the response is not streamed. `assertSee()`, `assertDontSee()`, `assertNoContent()`, `json()` and the JSON assertions read it automatically for streamed responses:

```php
it('serves the second kilobyte of the video', function (): void {
    $response = $this->client->get('/videos/intro.mp4', headers: ['Range' => 'bytes=1024-2047']);

    $response->assertStatus(206)->assertHeader('Accept-Ranges', 'bytes');
    expect(strlen($response->streamedContent()))->toBe(1024);
});

it('exports orders as CSV', function (): void {
    $this->client->get('/reports/orders.csv')->assertOk()->assertSee('id,total');
});
```

The body is captured through `Marko\Testing\Http\BufferedOutputStreamer`, an `OutputStreamer` that leaves output buffers and the time limit alone. Pass it to `withOutputStreamer()` to capture a streamed response you built yourself.

## Database Tests

Database tests need `marko/database` and a driver. `marko/testing` only suggests them:

```bash
composer require --dev marko/database-pgsql   # or marko/database-mysql
```

`TestDatabase::boot($basePath)` boots your application once per process (the same `Application::boot()` that `TestClient::boot()` uses) and applies the committed migrations once with `Migrator::migrate()`. Every later call for the same base path returns the same instance, so a suite pays for boot and migrations once, not once per test. Pair it with one of two isolation strategies:

| Strategy | Per test | Use it for |
|----------|----------|------------|
| `RefreshDatabase` | Opens a transaction before the test and rolls it back after | Almost everything. It is the fast default |
| `TruncateDatabase` | Empties every entity table | Code that must see committed data: a queue worker, a second process, or a test of the commit itself |

### Test environment

The helpers refuse to run in production, and an unset `APP_ENV` counts as production. Set the environment for the test run and point `config/database.php` at a dedicated test database:

```xml title="phpunit.xml"
<php>
    <env name="APP_ENV" value="testing"/>
    <env name="DB_DATABASE" value="myapp_test"/>
</php>
```

Migrating the database (`TestDatabase::boot()`) and `RefreshDatabase` run in any environment except production, because they leave existing data in place. Operations that delete data (`TruncateDatabase::truncate()` and `fresh: true`) run only when the environment is `testing` or `test`. Production, development (`development`, `dev`, `local`), `staging`, `qa` and any other name, including a typo, are refused, because only a testing environment shows that the database is disposable. If `MARKO_ENV` is set, it takes precedence over `APP_ENV`.

This follows the [destructive-command policy](/docs/packages/database/#environment-behaviour) of the `db:*` commands, but is stricter: development is refused too, and there is no `--force` to override it.

### RefreshDatabase

```php title="tests/Pest.php"
use Marko\Testing\Database\RefreshDatabase;
use Marko\Testing\Database\TestDatabase;

uses()
    ->beforeEach(function () {
        $this->database = TestDatabase::boot(dirname(__DIR__));
        $this->refresh = new RefreshDatabase($this->database);
        $this->refresh->begin();
        $this->http = $this->database->client();
    })
    ->afterEach(function () {
        $this->refresh->rollback();
    })
    ->in('Feature');
```

```php title="tests/Feature/ShowTest.php"
use App\Shows\Repository\ShowRepository;

it('publishes a show', function () {
    $this->http->postJson('/api/v1/shows', ['slug' => 'opening-night'])->assertCreated();

    $shows = $this->database->application()->container->get(ShowRepository::class);

    expect($shows->findOneBy(['slug' => 'opening-night']))->not->toBeNull();
});
```

- Every repository, query builder and service shares one connection, so the test transaction covers all of their writes.
- Code under test that calls `transaction()` gets a savepoint and behaves as in production: an inner rollback undoes only its own work.
- `rollback()` also rolls back any savepoints the code under test left open. If the rollback itself fails, the connection is reset before the error is rethrown, so the next test can still begin.
- Create HTTP clients with `$this->database->client()`. It returns a `TestClient` on the shared application that leaves the connection alone between requests. A client from `TestClient::boot()` boots a second application with its own connection and never sees the test transaction.
- On PostgreSQL, a failed statement outside a nested `transaction()` aborts the whole test transaction, and later statements in that test fail. Wrap code that is expected to hit a constraint in `transaction()`, the way production code should.

PHPUnit test cases call the same methods from `setUp()` and `tearDown()`:

```php
use Marko\Testing\Database\RefreshDatabase;
use Marko\Testing\Database\TestDatabase;
use PHPUnit\Framework\TestCase;

class ShowRepositoryTest extends TestCase
{
    private RefreshDatabase $refresh;

    protected function setUp(): void
    {
        $this->refresh = new RefreshDatabase(TestDatabase::boot(dirname(__DIR__, 2)));
        $this->refresh->begin();
    }

    protected function tearDown(): void
    {
        $this->refresh->rollback();
    }
}
```

#### After-commit callbacks

The test transaction never commits, so `afterCommit()` callbacks queued by the code under test do not run on their own. Run them when the test needs to assert on them:

```php
$service->publish('opening-night');   // queues a notification with afterCommit()

$this->refresh->runAfterCommitCallbacks();

expect($queue)->toHavePushed(SendShowPublished::class);
```

The callbacks run as though the outermost transaction had committed, but nothing is committed. `afterRollback()` callbacks registered by the code under test run when `rollback()` ends the test.

### TruncateDatabase

```php
use Marko\Testing\Database\TestDatabase;
use Marko\Testing\Database\TruncateDatabase;

beforeEach(function () {
    $this->database = TestDatabase::boot(dirname(__DIR__, 2));
    new TruncateDatabase($this->database)->truncate();
});
```

`truncate()` empties the tables of every `#[Table]` entity the application discovers, and restarts their identity sequences. On PostgreSQL it runs one `TRUNCATE ... RESTART IDENTITY CASCADE`. On MySQL it truncates table by table with foreign key checks switched off. Table names are quoted through the connection's `quoteIdentifier()` on both. The `migrations` table, and tables created only by hand-written migrations, are left alone. Package tables that ship an entity are entity tables too, so `jobs` and `failed_jobs` ([`marko/queue-database`](/docs/packages/queue-database/)) and `sessions` ([`marko/session-database`](/docs/packages/session-database/)) are emptied. It throws inside an open transaction, so don't combine it with `RefreshDatabase` in the same test, and it runs only in a testing environment (see [Test environment](#test-environment)).

### Fresh schema

`TestDatabase::boot($basePath, fresh: true)` rolls back every migration and runs them again on the first boot of the process, like `db:rebuild`. Use it when the schema has drifted, for example from an environment flag in `tests/Pest.php`:

```php
TestDatabase::boot(dirname(__DIR__), fresh: getenv('DB_FRESH') === '1');
```

Pass the same `fresh` value on every call; a different value for a base path that is already booted throws. Because it deletes all data, `fresh: true` runs only in a testing environment (see [Test environment](#test-environment)).

### Seeding rows

`seedTable()` and `getTableRowCount()` insert and count raw rows through the shared connection, so they are covered by the test transaction too:

```php
$this->database->seedTable('venues', [
    ['name' => 'Paradiso', 'city' => 'Amsterdam'],
]);

expect($this->database->getTableRowCount('venues'))->toBe(1);
```

To build entities, use [entity factories](/docs/packages/database/#entity-factories) or plain `new`.

## Pest Expectations

The expectations register automatically. `marko/testing` declares a Pest plugin (`Marko\Testing\Pest\ExpectationsPlugin`) under `extra.pest.plugins` in its `composer.json`, and Pest boots it once `expect()` exists, including in `--parallel` workers. There is nothing to add to `Pest.php`.

The expectations need Pest 4 (`composer require --dev pestphp/pest`). The fakes and their `assert*()` methods work with plain PHPUnit.

:::note
If an expectation such as `toHavePushed` is reported as an undefined method, Pest's plugin list is stale. Run `composer dump-autoload` to regenerate `vendor/pest-plugins.json`, and make sure `pestphp/pest-plugin` is allowed under `config.allow-plugins`.
:::

Use them in tests:

```php
expect($dispatcher)->toHaveDispatched(OrderPlaced::class);
expect($broadcaster)->toHaveBroadcast('orders.7', 'order.shipped');
expect($mailer)->toHaveSent();
expect($mailer)->toHaveSent(fn (Message $m) => $m->to === 'user@example.com');
expect($notifications)->toHaveNotified($user, OrderShippedNotification::class);
expect($notifications)->toHaveNotified($user, OrderShippedNotification::class, fn ($n, array $channels) => $channels === ['mail']);
expect($queue)->toHavePushed(SendEmailJob::class);
expect($filesystem)->toHaveStored('avatars/1.png');
expect($filesystem)->toHaveStored('avatars/1.png', $pngBytes);
expect($logger)->toHaveLogged('Payment failed');
expect($logger)->toHaveLogged('Payment failed', LogLevel::Error);
expect($http)->toHaveSentRequest();
expect($http)->toHaveSentRequest(fn (RecordedRequest $r) => $r->url === 'https://api.example.com/orders');

// TestResponse
expect($response)->toHaveStatus(201);
expect($response)->toHaveJsonPath('data.status', 'live');
expect($response)->toHaveSession('plan', 'pro');

// FakeGuard, or a TestClient (through the session, like assertAuthenticated())
expect($guard)->toBeAuthenticated();
expect($client)->toBeAuthenticated('web');
```

## API Reference

### FakeEventDispatcher

```php
public function dispatch(Event $event): void;
public function dispatched(string $eventClass): array;
public function assertDispatched(string $eventClass): void;
public function assertNotDispatched(string $eventClass): void;
public function assertDispatchedCount(string $eventClass, int $expected): void;
public function clear(): void;
```

### FakeBroadcaster

```php
public function broadcast(string|Channel $channel, string $event, array $data, ?string $id = null): void;
public function dispatch(BroadcastableInterface $broadcastable): void;
public function broadcastsOf(string|Channel $channel, string $event): array;
public function assertBroadcast(string|Channel $channel, string $event, ?callable $callback = null): void;
public function assertNotBroadcast(string|Channel $channel, string $event): void;
public function assertBroadcastCount(int $expected): void;
public function assertNothingBroadcast(): void;
public function clear(): void;
```

Recorded entries are in the public `$broadcasts` property (`channel`, `event`, `data`, `id`).

### FakeMailer

```php
public function send(Message $message): bool;
public function sendRaw(string $to, string $raw): bool;
public function assertSent(?callable $callback = null): void;
public function assertNothingSent(): void;
public function assertSentCount(int $expected): void;
public function clear(): void;
```

### FakeNotificationSender

```php
public function send(NotifiableInterface|array $notifiables, NotificationInterface $notification): void;
public function queue(NotifiableInterface|array $notifiables, NotificationInterface $notification): void;
public function sentTo(NotifiableInterface $notifiable, string $notificationClass, ?callable $filter = null): array;
public function queuedTo(NotifiableInterface $notifiable, string $notificationClass, ?callable $filter = null): array;
public function assertSentTo(NotifiableInterface $notifiable, string $notificationClass, ?callable $filter = null): void;
public function assertNotSentTo(NotifiableInterface $notifiable, string $notificationClass, ?callable $filter = null): void;
public function assertQueuedTo(NotifiableInterface $notifiable, string $notificationClass, ?callable $filter = null): void;
public function assertNothingSent(): void;
public function assertSentTimes(string $notificationClass, int $expected): void;
public function clear(): void;
```

Filters receive `(NotificationInterface $notification, array $channels)` and return `bool`.

### FakeQueue

```php
public function push(JobInterface $job, ?string $queue = null): PushResult;
public function later(int $delay, JobInterface $job, ?string $queue = null): PushResult;
public function pop(?string $queue = null): ?JobInterface;
public function size(?string $queue = null): int;
public function clear(?string $queue = null): int;
public function delete(string $jobId): bool;
public function release(JobInterface $job, int $delay = 0): bool; // stores the given job and delay on its pushed entry
public function assertPushed(string $jobClass, ?callable $callback = null): void;
public function assertNotPushed(string $jobClass): void;
public function assertPushedCount(int $expected): void;
public function assertNothingPushed(): void;
```

### FakeSession

```php
public function start(): void;
public function arm(): void;         // sets $armed; the next data access starts the session
public function isAvailable(): bool; // started or armed
public function get(string $key, mixed $default = null): mixed;
public function set(string $key, mixed $value): void;
public function has(string $key): bool;
public function remove(string $key): void;
public function clear(): void;
public function all(): array;
public function regenerate(bool $deleteOldSession = true): void;
public function destroy(): void;
public function getId(): string;
public function setId(string $id): void;
public function flash(): FlashBag;
public function save(): void;
public function isModified(): bool; // data changed since start(), or regenerate() was called
public function discard(): void;    // sets $discarded, never $saved
```

### FakeCookieJar

```php
public function get(string $name): ?string;
public function set(string $name, string $value, int $minutes = 0): void;
public function delete(string $name): void;
```

### FakeLogger

```php
public function emergency(string $message, array $context = []): void;
public function alert(string $message, array $context = []): void;
public function critical(string $message, array $context = []): void;
public function error(string $message, array $context = []): void;
public function warning(string $message, array $context = []): void;
public function notice(string $message, array $context = []): void;
public function info(string $message, array $context = []): void;
public function debug(string $message, array $context = []): void;
public function log(LogLevel $level, string $message, array $context = []): void;
public function entriesForLevel(LogLevel $level): array;
public function assertLogged(string $message, ?LogLevel $level = null): void;
public function assertNothingLogged(): void;
public function clear(): void;
```

### FakeConfigRepository

```php
public function __construct(array $config = [], ?string $defaultScope = null);
public function get(string $key, ?string $scope = null): mixed;
public function has(string $key, ?string $scope = null): bool;
public function getString(string $key, ?string $scope = null): string;
public function getInt(string $key, ?string $scope = null): int;
public function getBool(string $key, ?string $scope = null): bool;
public function getFloat(string $key, ?string $scope = null): float;
public function getArray(string $key, ?string $scope = null): array;
public function all(?string $scope = null): array;
public function withScope(string $scope): ConfigRepositoryInterface;
public function set(string $key, mixed $value): void;
```

### FakeAuthenticatable

```php
public function __construct(int|string $id = 1, string $password = 'hashed-password', ?string $rememberToken = null, string $identifierName = 'id', string $rememberTokenName = 'remember_token', ?DateTimeImmutable $rememberTokenExpiresAt = null);
public function getAuthIdentifier(): int|string;
public function getAuthIdentifierName(): string;
public function getAuthPassword(): string;
public function getRememberToken(): ?string;
public function setRememberToken(?string $token): void;
public function getRememberTokenExpiresAt(): ?DateTimeImmutable;
public function setRememberTokenExpiresAt(?DateTimeImmutable $expiresAt): void;
public function getRememberTokenName(): string;
```

### FakeUserProvider

```php
public function __construct(array $users = [], ?callable $credentialValidator = null);
public function retrieveById(int|string $identifier): ?AuthenticatableInterface;
public function retrieveByCredentials(array $credentials): ?AuthenticatableInterface;
public function validateCredentials(AuthenticatableInterface $user, array $credentials): bool;
public function rehashPasswordIfNeeded(AuthenticatableInterface $user, array $credentials): void;
public function retrieveByRememberToken(int|string $identifier, string $token): ?AuthenticatableInterface;
public function updateRememberToken(AuthenticatableInterface $user, ?string $token, ?DateTimeImmutable $expiresAt): void;
```

`rehashPasswordIfNeeded()` stores no hash; it appends each call to the public `rehashChecks` array (`['user' => ..., 'credentials' => ...]`), so a test can assert `SessionGuard::attempt()` asked for a rehash.

### FakeGuard

```php
public function __construct(string $name = 'test', bool $attemptResult = true);
public function check(): bool;
public function guest(): bool;
public function user(): ?AuthenticatableInterface;
public function id(): int|string|null;
public function attempt(array $credentials): bool;
public function login(AuthenticatableInterface $user): void;
public function loginById(int|string $id): ?AuthenticatableInterface;
public function logout(): void;
public function getName(): string;
public function setUser(?AuthenticatableInterface $user): void;
public function setAttemptResult(bool $result): void;
public function assertAuthenticated(): void;
public function assertGuest(): void;
public function assertAttempted(?callable $callback = null): void;
public function assertNotAttempted(): void;
public function assertLoggedOut(): void;
public function clear(): void;
```

### FakeHttpClient

```php
public array $requests; // array<RecordedRequest>, read-only from outside
public function stub(string $urlPattern, HttpResponse|HttpException $response): self;
public function queue(HttpResponse|HttpException ...$responses): self;
public function preventStrayRequests(bool $prevent = true): self;
public function request(string $method, string $url, array $options = []): HttpResponse;
public function get(string $url, array $options = []): HttpResponse;
public function post(string $url, array $options = []): HttpResponse;
public function put(string $url, array $options = []): HttpResponse;
public function patch(string $url, array $options = []): HttpResponse;
public function delete(string $url, array $options = []): HttpResponse;
public function assertSent(?callable $callback = null): void;
public function assertNotSent(callable $callback): void;
public function assertSentCount(int $expected): void;
public function assertNothingSent(): void;
public function clear(): void;
```

### RecordedRequest

```php
public function __construct(string $method, string $url, array $options = []);
public function header(string $name): ?string;
public function json(): mixed;
public function body(): string;
```

### FakeEncryptor

```php
public private(set) array $encrypted; // list<array{value: string, aad: string, encrypted: string}>
public private(set) array $decrypted; // list<array{encrypted: string, aad: string, value: string}>
public function encrypt(string $value, string $aad = ''): string;
public function decrypt(string $encrypted, string $aad = ''): string; // throws DecryptionException on an AAD mismatch
public function assertEncrypted(?string $value = null, ?string $aad = null): void;
public function assertDecrypted(?string $value = null, ?string $aad = null): void;
public function assertNothingEncrypted(): void;
public function assertNothingDecrypted(): void;
public function clear(): void;
```

### FakeClock

```php
public function __construct(DateTimeImmutable|string $now = 'now');
public function now(): DateTimeImmutable;
public function setNow(DateTimeImmutable|string $now): void;
public function travel(string $modifier): void;
public function travelTo(DateTimeImmutable|string $now): void;
public function assertNowIs(DateTimeImmutable|string $expected): void;
```

### FakeFilesystem

Implements every `FilesystemInterface` method, plus:

```php
public function __construct(ClockInterface $clock, bool $public);
public function assertExists(string $path): void;
public function assertMissing(string $path): void;
public function assertContent(string $path, string $expected): void;
public function assertDirectoryEmpty(string $path): void;
public function assertWrittenCount(int $expected): void;
public function clear(): void;
```

Recorded write destinations are in the public `$written` property.

### FakeSleeper

```php
public array $sleeps; // list<int>, milliseconds in the order requested
public function sleep(int $milliseconds): void;
public function clear(): void;
public function assertSlept(int ...$milliseconds): void;
public function assertNotSlept(): void;
```

### FakeConfirmationPrompter

```php
public private(set) array $asked; // list<string>
public function __construct(array $answers = [], bool $interactive = true);
public function isInteractive(): bool;
public function confirm(string $question, bool $default = false): bool;
public function assertAsked(string $question): void;
public function assertNothingAsked(): void;
```

### TestClient

```php
public static function boot(string $basePath): self;
public static function forApplication(Application $application): self;
public function application(): Application;
public function withHeaders(array $headers): static;
public function withHeader(string $name, string $value): static;
public function withServerVariables(array $variables): static;
public function withCookie(string $name, string $value, string $path = '/', ?string $domain = null, bool $secure = false): static;
public function withCookies(array $cookies): static;
public function withoutCookies(): static;
public function cookies(): array;
public function cookieJar(): array; // list<JarCookie>
public function withFile(string $field, string $path, ?string $clientFilename = null, ?string $clientMediaType = null): static;
public function withFiles(string $field, array $paths): static;
public function actingAs(AuthenticatableInterface $user, ?string $guard = null): static;
public function withoutResetting(ResettableInterface ...$services): static;
public function resetRequestState(): void;              // the reset run before each request
public function requestCount(): int;                     // requests sent so far
public function lastRequestCookie(string $name): ?string; // the jar cookie a repeat of the last request would carry
public function assertAuthenticated(?string $guard = null): static;
public function assertGuest(?string $guard = null): static;
public function assertAuthenticatedAs(AuthenticatableInterface $user, ?string $guard = null): static;
public function get(string $uri, array $query = [], array $headers = []): TestResponse;
public function head(string $uri, array $query = [], array $headers = []): TestResponse;
public function post(string $uri, array $data = [], array $headers = []): TestResponse;
public function put(string $uri, array $data = [], array $headers = []): TestResponse;
public function patch(string $uri, array $data = [], array $headers = []): TestResponse;
public function delete(string $uri, array $data = [], array $headers = []): TestResponse;
public function options(string $uri, array $data = [], array $headers = []): TestResponse;
public function getJson(string $uri, array $query = [], array $headers = []): TestResponse;
public function postJson(string $uri, array $data = [], array $headers = []): TestResponse;
public function putJson(string $uri, array $data = [], array $headers = []): TestResponse;
public function patchJson(string $uri, array $data = [], array $headers = []): TestResponse;
public function deleteJson(string $uri, array $data = [], array $headers = []): TestResponse;
public function json(string $method, string $uri, array $data = [], array $headers = []): TestResponse;
public function call(string $method, string $uri, array $data = [], array $headers = [], ?string $body = null): TestResponse;
```

### SessionProbe

```php
public function __construct(TestClient $client);
public function ensureLatest(int $requestSequence): void; // throws TestClientException for a stale response
public function sessionData(): array;                     // raw session data the next request would see
public function user(?string $guard = null): ?AuthenticatableInterface; // throws TestClientException for a stateless guard
```

### JarCookie

```php
public string $name;
public string $value;
public ?string $domain; // null: added with withCookie() without a domain, sent to any host
public string $path;
public bool $secure;
public bool $hostOnly;  // set without a Domain attribute: sent to $domain exactly, not its subdomains
public ?int $expiresAt; // Unix timestamp from Max-Age (preferred) or Expires; null for a session cookie
public ?string $sameSite; // Strict, Lax or None as the response sent it; null is treated like None
public function matches(string $host, string $path, bool $secure): bool;
public function isExpired(int $now): bool;
public function allowsCrossSite(string $method): bool; // false for Strict, GET only for Lax
```

### PublicSuffixList

```php
public static function isPublicSuffix(string $domain): bool; // `com`, `co.uk`; never an IP address
public static function site(string $host): string;           // registrable domain: `shop.example.co.uk` => `example.co.uk`
```

### TestResponse

```php
public function response(): Response;
public function status(): int;
public function body(): string;
public function streamedContent(): string; // the body a StreamedResponseInterface writes; throws for a plain Response
public function header(string $name): ?string;
public function json(?string $path = null): mixed;
public function assertStatus(int $status): static;
public function assertOk(): static;
public function assertCreated(): static;
public function assertNoContent(): static;
public function assertUnauthorized(): static;
public function assertForbidden(): static;
public function assertNotFound(): static;
public function assertUnprocessable(): static;
public function assertRedirect(?string $to = null): static;
public function assertHeader(string $name, ?string $value = null): static;
public function assertHeaderMissing(string $name): static;
public function assertCookie(string $name, ?string $value = null): static;
public function assertCookieMissing(string $name): static;
public function assertSee(string $text): static;
public function assertDontSee(string $text): static;
public function assertJson(array $subset): static;
public function assertExactJson(array $data): static;
public function assertJsonPath(string $path, mixed $expected): static;
public function assertJsonCount(int $count, ?string $path = null): static;
public function assertJsonMissingPath(string $path): static;
public function assertJsonValidationErrors(array|string $fields): static;
public function assertJsonMissingValidationErrors(array|string $fields = []): static;
public function assertDownload(?string $filename = null): static;
public function assertSessionHas(string $key, mixed $value = null): static;
public function assertSessionMissing(string $key): static;
public function assertSessionHasFlash(string $type, ?string $message = null): static;
```

### TestDatabase

```php
public static function boot(string $basePath, bool $fresh = false): self;
public function application(): Application;
public function connection(): ConnectionInterface;
public function transaction(): TransactionInterface;
public function environment(): AppEnvironment;
public function appliedMigrations(): array;
public function client(): TestClient;
public function seedTable(string $tableName, array $rows): void;
public function getTableRowCount(string $tableName): int;
public function assertNotProduction(): void;
public function assertDisposable(string $operation): void;
```

### RefreshDatabase

```php
public function __construct(TestDatabase $database);
public function begin(): void;
public function rollback(): void;
public function runAfterCommitCallbacks(): void;
public function database(): TestDatabase;
```

### TruncateDatabase

```php
public function __construct(TestDatabase $database);
public function truncate(): void;
public function tables(): array;
```

### KnownDriversValidator

```php
public static function assertSkeletonSuggestContainsAll(string $knownDriversPath, string $skeletonComposerPath): void;
public static function assertDocsUrlsResolveToValidPattern(string $knownDriversPath): void;
```

### AssertionFailedException

```php
public static function expectedDispatched(string $eventClass): self;
public static function unexpectedDispatched(string $eventClass): self;
public static function expectedCount(string $type, int $expected, int $actual): self;
public static function expectedContains(string $type, string $needle): self;
public static function unexpectedContains(string $type, string $needle): self;
public static function expectedEmpty(string $type): self;
public static function unexpectedEmpty(string $type): self;
public static function strayRequest(string $method, string $url): self;
public static function responseAssertion(string $expectation, int $status, string $body): self;
public static function invalidJsonResponse(int $status, string $body, string $error): self;
```
