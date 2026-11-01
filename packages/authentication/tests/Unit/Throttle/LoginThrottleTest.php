<?php

declare(strict_types=1);

use Marko\Authentication\Contracts\LoginThrottleInterface;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Authentication\Exceptions\TooManyLoginAttemptsException;
use Marko\Authentication\Http\CurrentRequest;
use Marko\Authentication\Throttle\LoginThrottle;
use Marko\Authentication\Throttle\NullLoginThrottle;
use Marko\Cache\CacheOperations;
use Marko\Cache\Config\CacheConfig;
use Marko\Cache\Memory\Driver\ArrayCacheDriver;
use Marko\Core\Exceptions\HttpExceptionInterface;
use Marko\RateLimiter\Contracts\RateLimitKeyResolverInterface;
use Marko\Routing\Http\Request;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;

const THROTTLE_CREDENTIALS = ['email' => 'admin@example.com', 'password' => 'guess'];

/**
 * @param array<string, mixed> $overrides
 */
function createLoginThrottle(
    FakeClock $clock,
    ?CurrentRequest $currentRequest = null,
    array $overrides = [],
): LoginThrottle {
    $cache = new ArrayCacheDriver(new CacheConfig(new FakeConfigRepository([
        'cache.path' => sys_get_temp_dir(),
        'cache.default_ttl' => 3600,
        'cache.driver' => 'array',
    ])), $clock, new CacheOperations());

    return new LoginThrottle(...[
        'cache' => $cache,
        'clock' => $clock,
        'currentRequest' => $currentRequest ?? currentRequestFrom('203.0.113.7'),
        'maxAttempts' => 3,
        'decaySeconds' => 60,
        'lockoutSeconds' => 30,
        'maxLockoutSeconds' => 100,
        ...$overrides,
    ]);
}

function currentRequestFrom(
    string $ip,
): CurrentRequest {
    $currentRequest = new CurrentRequest();
    $currentRequest->set(new Request(server: ['REMOTE_ADDR' => $ip]));

    return $currentRequest;
}

/**
 * @param array<string, mixed> $credentials
 */
function failLogins(
    LoginThrottle $throttle,
    int $times,
    array $credentials = THROTTLE_CREDENTIALS,
): void {
    for ($i = 0; $i < $times; $i++) {
        $throttle->recordFailure('web', $credentials);
    }
}

/**
 * Seconds the throttle would ask the client to wait, or null when not locked out.
 *
 * @param array<string, mixed> $credentials
 */
function lockoutRetryAfter(
    LoginThrottle $throttle,
    array $credentials = THROTTLE_CREDENTIALS,
): ?int {
    try {
        $throttle->ensureNotLockedOut('web', $credentials);
    } catch (TooManyLoginAttemptsException $exception) {
        return $exception->getRetryAfter();
    }

    return null;
}

describe('LoginThrottle', function (): void {
    beforeEach(function (): void {
        $this->clock = new FakeClock('2026-01-01 12:00:00 UTC');
        $this->throttle = createLoginThrottle($this->clock);
    });

    it('implements LoginThrottleInterface', function (): void {
        expect($this->throttle)->toBeInstanceOf(LoginThrottleInterface::class);
    });

    it('allows attempts below the failure limit', function (): void {
        failLogins($this->throttle, 2);

        expect(lockoutRetryAfter($this->throttle))->toBeNull();
    });

    it('locks out after max_attempts failures for lockout_seconds', function (): void {
        failLogins($this->throttle, 3);

        expect(lockoutRetryAfter($this->throttle))->toBe(30);
    });

    it('lets the client try again once the lockout ends', function (): void {
        failLogins($this->throttle, 3);
        $this->clock->travel('+31 seconds');

        expect(lockoutRetryAfter($this->throttle))->toBeNull();
    });

    it('forgets failures older than decay_seconds', function (): void {
        failLogins($this->throttle, 2);
        $this->clock->travel('+61 seconds');
        failLogins($this->throttle, 2);

        expect(lockoutRetryAfter($this->throttle))->toBeNull();
    });

    it('doubles each further lockout, capped at max_lockout_seconds', function (): void {
        $durations = [];

        for ($lockout = 0; $lockout < 4; $lockout++) {
            failLogins($this->throttle, 3);
            $durations[] = lockoutRetryAfter($this->throttle);
            $this->clock->travel('+' . $durations[$lockout] . ' seconds');
        }

        expect($durations)->toBe([30, 60, 100, 100]);
    });

    it('clears failures and lockout history on clear()', function (): void {
        failLogins($this->throttle, 3);
        $this->throttle->clear('web', THROTTLE_CREDENTIALS);
        failLogins($this->throttle, 2);

        expect(lockoutRetryAfter($this->throttle))->toBeNull();

        failLogins($this->throttle, 1);

        expect(lockoutRetryAfter($this->throttle))->toBe(30);
    });

    it('normalises the login identifier by case and surrounding whitespace', function (): void {
        failLogins($this->throttle, 3, ['email' => '  Admin@Example.COM ', 'password' => 'a']);

        expect(lockoutRetryAfter($this->throttle, ['email' => 'admin@example.com', 'password' => 'b']))->toBe(30);
    });

    it('keys the lockout on the identifier, not the guessed password', function (): void {
        failLogins($this->throttle, 3);

        expect(lockoutRetryAfter($this->throttle, ['email' => 'other@example.com', 'password' => 'guess']))
            ->toBeNull();
    });

    it('locks an identifier out only for the client that failed', function (): void {
        $currentRequest = currentRequestFrom('203.0.113.7');
        $throttle = createLoginThrottle($this->clock, $currentRequest);
        failLogins($throttle, 3);

        $currentRequest->set(new Request(server: ['REMOTE_ADDR' => '198.51.100.9']));

        expect(lockoutRetryAfter($throttle))->toBeNull();
    });

    it('keys the client by the rate limiter key resolver when one is given', function (): void {
        $currentRequest = currentRequestFrom('2001:db8::1');
        $resolver = new class () implements RateLimitKeyResolverInterface
        {
            public function resolve(
                Request $request,
            ): string {
                return '2001:db8::/64';
            }
        };
        $throttle = createLoginThrottle($this->clock, $currentRequest, ['rateLimitKeyResolver' => $resolver]);
        failLogins($throttle, 3);

        // Another address in the same /64 shares the lockout
        $currentRequest->set(new Request(server: ['REMOTE_ADDR' => '2001:db8::ffff']));

        expect(lockoutRetryAfter($throttle))->toBe(30);
    });

    it('throttles by identifier alone outside an HTTP request', function (): void {
        $throttle = createLoginThrottle($this->clock, new CurrentRequest());
        failLogins($throttle, 3);

        expect(lockoutRetryAfter($throttle))->toBe(30);
    });

    it('throws a helpful error when enabled without a cache driver', function (): void {
        $throttle = new LoginThrottle(cache: null, clock: $this->clock, currentRequest: new CurrentRequest());

        expect(fn () => $throttle->ensureNotLockedOut('web', THROTTLE_CREDENTIALS))
            ->toThrow(AuthException::class, 'no cache driver is installed');
    });

    it('rejects a non-positive limit', function (string $parameter, string $configKey): void {
        expect(fn () => createLoginThrottle($this->clock, overrides: [$parameter => 0]))
            ->toThrow(AuthException::class, "authentication.throttle.$configKey must be a positive integer, got 0");
    })->with([
        ['maxAttempts', 'max_attempts'],
        ['decaySeconds', 'decay_seconds'],
        ['lockoutSeconds', 'lockout_seconds'],
        ['maxLockoutSeconds', 'max_lockout_seconds'],
    ]);
});

describe('NullLoginThrottle', function (): void {
    it('never locks anyone out', function (): void {
        $throttle = new NullLoginThrottle();

        for ($i = 0; $i < 10; $i++) {
            $throttle->recordFailure('web', THROTTLE_CREDENTIALS);
        }

        $throttle->ensureNotLockedOut('web', THROTTLE_CREDENTIALS);
        $throttle->clear('web', THROTTLE_CREDENTIALS);

        expect($throttle)->toBeInstanceOf(LoginThrottleInterface::class);
    });
});

describe('TooManyLoginAttemptsException', function (): void {
    it('renders as 429 Too Many Requests with Retry-After', function (): void {
        $exception = TooManyLoginAttemptsException::lockedOut('web', 42);

        expect($exception)->toBeInstanceOf(HttpExceptionInterface::class)
            ->and($exception)->toBeInstanceOf(AuthException::class)
            ->and($exception->getStatusCode())->toBe(429)
            ->and($exception->getHeaders())->toBe(['Retry-After' => '42'])
            ->and($exception->getResponseData())->toBe([
                'message' => 'Too many login attempts. Please try again later.',
                'retry_after' => 42,
            ])
            ->and($exception->getContext())->toContain("guard 'web'");
    });

    it('never asks the client to wait less than one second', function (): void {
        expect(TooManyLoginAttemptsException::lockedOut('web', 0)->getRetryAfter())->toBe(1);
    });
});
