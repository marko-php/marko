# marko/testing

Testing utilities for Marko — reusable fakes with built-in assertions that eliminate test boilerplate.

## Overview

This package provides in-memory fakes for the core Marko contracts: events, mail, queues, sessions, cookies, logging, config, and authentication. Each fake records interactions and exposes assertion methods so your tests stay focused on behavior rather than mock setup. Pest expectation extensions (`toHaveDispatched`, `toHaveSent`, `toHavePushed`, `toHaveLogged`) are included for fluent assertions.

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

$provider->updateRememberToken($user, 'new-token');
expect($provider->lastRememberTokenUpdate['token'])->toBe('new-token');
```

## Pest Expectations

Load `packages/testing/src/Pest/Expectations.php` in your `Pest.php` to enable fluent assertions:

```php
require_once __DIR__ . '/../vendor/marko/testing/src/Pest/Expectations.php';
```

Then use in tests:

```php
expect($dispatcher)->toHaveDispatched(OrderPlaced::class);
expect($mailer)->toHaveSent();
expect($mailer)->toHaveSent(fn (Message $m) => $m->to === 'user@example.com');
expect($queue)->toHavePushed(SendEmailJob::class);
expect($logger)->toHaveLogged('Payment failed');
expect($logger)->toHaveLogged('Payment failed', LogLevel::Error);
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

### FakeMailer

```php
public function send(Message $message): bool;
public function sendRaw(string $to, string $raw): bool;
public function assertSent(?callable $callback = null): void;
public function assertNothingSent(): void;
public function assertSentCount(int $expected): void;
public function clear(): void;
```

### FakeQueue

```php
public function push(JobInterface $job, ?string $queue = null): string;
public function later(int $delay, JobInterface $job, ?string $queue = null): string;
public function pop(?string $queue = null): ?JobInterface;
public function size(?string $queue = null): int;
public function clear(?string $queue = null): int;
public function delete(string $jobId): bool;
public function release(string $jobId, int $delay = 0): bool;
public function assertPushed(string $jobClass, ?callable $callback = null): void;
public function assertNotPushed(string $jobClass): void;
public function assertPushedCount(int $expected): void;
public function assertNothingPushed(): void;
```

### FakeSession

```php
public function start(): void;
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
public function __construct(int|string $id = 1, string $password = 'hashed-password', ?string $rememberToken = null, string $identifierName = 'id', string $rememberTokenName = 'remember_token');
public function getAuthIdentifier(): int|string;
public function getAuthIdentifierName(): string;
public function getAuthPassword(): string;
public function getRememberToken(): ?string;
public function setRememberToken(?string $token): void;
public function getRememberTokenName(): string;
```

### FakeUserProvider

```php
public function __construct(array $users = [], ?callable $credentialValidator = null);
public function retrieveById(int|string $identifier): ?AuthenticatableInterface;
public function retrieveByCredentials(array $credentials): ?AuthenticatableInterface;
public function validateCredentials(AuthenticatableInterface $user, array $credentials): bool;
public function retrieveByRememberToken(int|string $identifier, string $token): ?AuthenticatableInterface;
public function updateRememberToken(AuthenticatableInterface $user, ?string $token): void;
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
```
