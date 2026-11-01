<?php

declare(strict_types=1);

use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Contracts\PasswordConfirmationInterface;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Authentication\Exceptions\TooManyPasswordConfirmationAttemptsException;
use Marko\Authentication\Hashing\BcryptPasswordHasher;
use Marko\Authentication\Hashing\PasswordVerifier;
use Marko\Authentication\PasswordConfirmation\PasswordConfirmation;
use Marko\Cache\CacheOperations;
use Marko\Cache\Config\CacheConfig;
use Marko\Cache\Memory\Driver\ArrayCacheDriver;
use Marko\Core\Path\ProjectPaths;
use Marko\RateLimiter\RateLimiter;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeSession;

/**
 * A PasswordConfirmation wired to a fake clock, a started fake session and an
 * in-memory rate limiter, plus a user whose password is "secret".
 */
class PasswordConfirmationHarness
{
    public FakeClock $clock;

    public FakeSession $session;

    public FakeAuthenticatable $user;

    private BcryptPasswordHasher $hasher;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly array $config = [],
    ) {
        $this->clock = new FakeClock('2026-06-01 12:00:00');
        $this->session = new FakeSession();
        $this->session->start();
        $this->hasher = new BcryptPasswordHasher(cost: 4);
        $this->user = new FakeAuthenticatable(id: 7, password: $this->hasher->hash('secret'));
    }

    public function confirmation(): PasswordConfirmation
    {
        $authConfig = new AuthConfig(new FakeConfigRepository([
            'authentication.password_confirmation.timeout_seconds' => 10800,
            'authentication.password_confirmation.route' => '/confirm-password',
            'authentication.password_confirmation.throttle.max_attempts' => 3,
            'authentication.password_confirmation.throttle.decay_seconds' => 60,
            ...$this->config,
        ]));
        $cache = new ArrayCacheDriver(new CacheConfig(
            new FakeConfigRepository([
                'cache.path' => sys_get_temp_dir(),
                'cache.default_ttl' => 3600,
                'cache.driver' => 'array',
            ]),
            new ProjectPaths(),
        ), $this->clock, new CacheOperations());

        return new PasswordConfirmation(
            passwordVerifier: new PasswordVerifier($this->hasher),
            session: $this->session,
            clock: $this->clock,
            rateLimiter: new RateLimiter($cache, $this->clock),
            authConfig: $authConfig,
        );
    }
}

it('implements PasswordConfirmationInterface', function (): void {
    expect(new PasswordConfirmationHarness()->confirmation())->toBeInstanceOf(PasswordConfirmationInterface::class);
});

it('stamps the session with the clock time when the password is correct', function (): void {
    $harness = new PasswordConfirmationHarness();
    $confirmation = $harness->confirmation();

    expect($confirmation->confirm($harness->user, 'secret'))->toBeTrue()
        ->and($harness->session->get('auth.password_confirmed_at'))->toBe($harness->clock->now()->getTimestamp())
        ->and($confirmation->isRecentlyConfirmed())->toBeTrue();
});

it('does not stamp the session when the password is wrong', function (): void {
    $harness = new PasswordConfirmationHarness();
    $confirmation = $harness->confirmation();

    expect($confirmation->confirm($harness->user, 'wrong'))->toBeFalse()
        ->and($harness->session->has('auth.password_confirmed_at'))->toBeFalse()
        ->and($confirmation->isRecentlyConfirmed())->toBeFalse();
});

it('keeps an earlier stamp unchanged when a later password is wrong', function (): void {
    $harness = new PasswordConfirmationHarness();
    $confirmation = $harness->confirmation();
    $confirmation->confirm($harness->user, 'secret');
    $stamp = $harness->session->get('auth.password_confirmed_at');

    $harness->clock->travel('+5 minutes');
    $confirmation->confirm($harness->user, 'wrong');

    expect($harness->session->get('auth.password_confirmed_at'))->toBe($stamp);
});

it('marks the session confirmed without a password through markConfirmed', function (): void {
    $harness = new PasswordConfirmationHarness();
    $confirmation = $harness->confirmation();

    $confirmation->markConfirmed();

    expect($confirmation->isRecentlyConfirmed())->toBeTrue();
});

it('treats a confirmation as stale once the timeout has passed', function (): void {
    $harness = new PasswordConfirmationHarness(['authentication.password_confirmation.timeout_seconds' => 900]);
    $confirmation = $harness->confirmation();
    $confirmation->markConfirmed();

    $harness->clock->travel('+899 seconds');
    $stillFresh = $confirmation->isRecentlyConfirmed();
    $harness->clock->travel('+1 second');

    expect($stillFresh)->toBeTrue()
        ->and($confirmation->isRecentlyConfirmed())->toBeFalse();
});

it('is not recently confirmed when the session has no stamp', function (): void {
    expect(new PasswordConfirmationHarness()->confirmation()->isRecentlyConfirmed())->toBeFalse();
});

it('throttles confirmation attempts per user', function (): void {
    $harness = new PasswordConfirmationHarness();
    $confirmation = $harness->confirmation();

    foreach (range(1, 3) as $ignored) {
        $confirmation->confirm($harness->user, 'wrong');
    }

    try {
        $confirmation->confirm($harness->user, 'secret');
        $this->fail('Expected TooManyPasswordConfirmationAttemptsException');
    } catch (TooManyPasswordConfirmationAttemptsException $exception) {
        expect($exception->getStatusCode())->toBe(429)
            ->and($exception->getHeaders())->toBe(['Retry-After' => '60'])
            ->and($harness->session->has('auth.password_confirmed_at'))->toBeFalse();
    }

    $otherUser = new FakeAuthenticatable(id: 8, password: 'irrelevant');

    expect($confirmation->confirm($otherUser, 'wrong'))->toBeFalse();
});

it('lets the user try again once the throttle window has passed', function (): void {
    $harness = new PasswordConfirmationHarness();
    $confirmation = $harness->confirmation();

    foreach (range(1, 4) as $ignored) {
        try {
            $confirmation->confirm($harness->user, 'wrong');
        } catch (TooManyPasswordConfirmationAttemptsException) {
        }
    }

    $harness->clock->travel('+61 seconds');

    expect($confirmation->confirm($harness->user, 'secret'))->toBeTrue();
});

it('resets the attempt count after a correct password', function (): void {
    $harness = new PasswordConfirmationHarness();
    $confirmation = $harness->confirmation();

    $confirmation->confirm($harness->user, 'wrong');
    $confirmation->confirm($harness->user, 'wrong');
    $confirmation->confirm($harness->user, 'secret');
    $confirmation->confirm($harness->user, 'wrong');
    $confirmation->confirm($harness->user, 'wrong');

    expect($confirmation->confirm($harness->user, 'secret'))->toBeTrue();
});

it('rejects a timeout below one second', function (): void {
    $harness = new PasswordConfirmationHarness(['authentication.password_confirmation.timeout_seconds' => 0]);
    $confirmation = $harness->confirmation();
    $confirmation->markConfirmed();

    expect(fn () => $confirmation->isRecentlyConfirmed())
        ->toThrow(
            AuthException::class,
            'authentication.password_confirmation.timeout_seconds must be a positive integer, got 0',
        );
});
