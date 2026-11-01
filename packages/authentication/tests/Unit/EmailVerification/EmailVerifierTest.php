<?php

declare(strict_types=1);

use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Contracts\EmailVerifierInterface;
use Marko\Authentication\Contracts\UserPersisterInterface;
use Marko\Authentication\EmailVerification\CurrentVerifiableUser;
use Marko\Authentication\EmailVerification\EmailVerifier;
use Marko\Authentication\EmailVerification\VerificationLinkStatus;
use Marko\Authentication\EmailVerification\VerificationStatus;
use Marko\Authentication\Event\EmailVerifiedEvent;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Authentication\Exceptions\UnauthenticatedException;
use Marko\Authentication\Tests\Fixtures\RecordingVerificationNotifier;
use Marko\Authentication\Tests\Fixtures\VerifiableUser;
use Marko\Authentication\UserPersisterResolver;
use Marko\Cache\CacheOperations;
use Marko\Cache\Config\CacheConfig;
use Marko\Cache\Memory\Driver\ArrayCacheDriver;
use Marko\Core\Container\Container;
use Marko\RateLimiter\RateLimiter;
use Marko\Routing\Exceptions\InvalidSignatureException;
use Marko\Routing\Http\Request;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;
use Marko\Routing\RoutingConfig;
use Marko\Routing\Signing\HmacUrlSigner;
use Marko\Routing\UrlGenerator;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\Testing\Fake\FakeGuard;
use Marko\Testing\Fake\FakeUserPersister;

/**
 * An EmailVerifier wired to a real HMAC URL signer and in-memory collaborators.
 */
class EmailVerifierHarness
{
    public FakeClock $clock;

    public FakeGuard $guard;

    public RecordingVerificationNotifier $notifier;

    public FakeUserPersister $persister;

    public FakeEventDispatcher $events;

    public VerifiableUser $user;

    private EmailVerifier $verifier;

    public function __construct()
    {
        $this->clock = new FakeClock('2026-10-31 12:00:00');
        $this->guard = new FakeGuard(name: 'session');
        $this->notifier = new RecordingVerificationNotifier();
        $this->persister = new FakeUserPersister();
        $this->events = new FakeEventDispatcher();
        $this->user = new VerifiableUser(id: 7, email: 'ada@example.com');

        $authConfig = new AuthConfig(new FakeConfigRepository([
            'authentication.guards' => ['session' => ['driver' => 'session', 'provider' => 'users']],
            'authentication.providers' => ['users' => []],
            'authentication.verification.verify_route' => 'verification.verify',
            'authentication.verification.notice_route' => '/email/verify',
            'authentication.verification.expire_seconds' => 3600,
            'authentication.verification.throttle_seconds' => 60,
        ]));
        $routingConfig = new RoutingConfig(new FakeConfigRepository([
            'routing.url' => 'https://example.com',
            'routing.signing.key' => 'test-signing-key-0123456789abcdef0123456789',
            'routing.signing.previous_keys' => [],
            'routing.signing.ignored_parameters' => [],
        ]));
        $routes = new RouteCollection();
        $routes->add(new RouteDefinition(
            method: 'GET',
            path: '/email/verify/confirm',
            controller: 'VerificationController',
            action: 'verify',
            name: 'verification.verify',
        ));
        $container = new Container();
        $container->instance(UserPersisterInterface::class, $this->persister);
        $cache = new ArrayCacheDriver(new CacheConfig(new FakeConfigRepository([
            'cache.path' => sys_get_temp_dir(),
            'cache.default_ttl' => 3600,
            'cache.driver' => 'array',
        ])), $this->clock, new CacheOperations());

        $this->verifier = new EmailVerifier(
            authConfig: $authConfig,
            currentUser: new CurrentVerifiableUser($this->guard),
            userPersisterResolver: new UserPersisterResolver($authConfig, $container),
            urlSigner: new HmacUrlSigner(new UrlGenerator($routes, $routingConfig), $routingConfig, $this->clock),
            verificationNotifier: $this->notifier,
            rateLimiter: new RateLimiter($cache, $this->clock),
            eventDispatcher: $this->events,
            clock: $this->clock,
        );
    }

    public function verifier(): EmailVerifier
    {
        return $this->verifier;
    }

    /**
     * Send a link to the user and return it.
     */
    public function sendLink(): string
    {
        $this->verifier->sendVerificationLink($this->user);

        return $this->notifier->sent[array_key_last($this->notifier->sent)]['url'];
    }

    /**
     * The request a browser sends when the logged-in user clicks $url.
     */
    public function click(
        string $url,
    ): Request {
        $this->guard->setUser($this->user);
        $parts = parse_url($url);
        $query = [];
        parse_str($parts['query'] ?? '', $query);

        return new Request(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => $parts['path'] . '?' . ($parts['query'] ?? ''),
                'HTTP_HOST' => $parts['host'],
                'HTTPS' => 'on',
            ],
            query: $query,
        );
    }
}

it('implements EmailVerifierInterface', function (): void {
    expect(new EmailVerifierHarness()->verifier())->toBeInstanceOf(EmailVerifierInterface::class);
});

it(
    'sends a signed link with the user id and a SHA-256 hash of the email that expires after expire_seconds',
    function (): void {
        $harness = new EmailVerifierHarness();

        $status = $harness->verifier()->sendVerificationLink($harness->user);

        $url = $harness->notifier->sent[0]['url'];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        expect($status)->toBe(VerificationLinkStatus::Sent)
            ->and($harness->notifier->sent[0]['user'])->toBe($harness->user)
            ->and($url)->toStartWith('https://example.com/email/verify/confirm?')
            ->and($query['id'])->toBe('7')
            ->and($query['hash'])->toBe(hash('sha256', 'ada@example.com'))
            ->and($query['expires'])->toBe((string) ($harness->clock->now()->getTimestamp() + 3600))
            ->and($query['signature'])->toMatch('/^[0-9a-f]{64}$/');
    },
);

it('verifies and persists the user from a valid link', function (): void {
    $harness = new EmailVerifierHarness();
    $request = $harness->click($harness->sendLink());

    $status = $harness->verifier()->verify($request);

    expect($status)->toBe(VerificationStatus::Verified)
        ->and($harness->user->hasVerifiedEmail())->toBeTrue()
        ->and($harness->user->emailVerifiedAt)->toEqual($harness->clock->now())
        ->and($harness->persister->persisted())->toBe([$harness->user]);
});

it('dispatches EmailVerifiedEvent with the user once verified', function (): void {
    $harness = new EmailVerifierHarness();

    $harness->verifier()->verify($harness->click($harness->sendLink()));

    $harness->events->assertDispatched(EmailVerifiedEvent::class);
    expect($harness->events->dispatched)->toHaveCount(1)
        ->and($harness->events->dispatched[0]->user)->toBe($harness->user);
});

it('rejects a tampered link without verifying the user', function (): void {
    $harness = new EmailVerifierHarness();
    $url = str_replace('id=7', 'id=8', $harness->sendLink());

    expect(fn () => $harness->verifier()->verify($harness->click($url)))
        ->toThrow(InvalidSignatureException::class, 'This link is invalid.')
        ->and($harness->user->hasVerifiedEmail())->toBeFalse()
        ->and($harness->persister->persisted())->toBe([]);
});

it('rejects an expired link without verifying the user', function (): void {
    $harness = new EmailVerifierHarness();
    $url = $harness->sendLink();
    $harness->clock->travel('+3600 seconds');

    expect(fn () => $harness->verifier()->verify($harness->click($url)))
        ->toThrow(InvalidSignatureException::class, 'This link has expired.')
        ->and($harness->user->hasVerifiedEmail())->toBeFalse()
        ->and($harness->persister->persisted())->toBe([]);
});

it('rejects a link once the user changed their email', function (): void {
    $harness = new EmailVerifierHarness();
    $url = $harness->sendLink();
    $harness->user->email = 'ada@new.example.com';

    expect(fn () => $harness->verifier()->verify($harness->click($url)))
        ->toThrow(InvalidSignatureException::class, 'This link is invalid.')
        ->and($harness->user->hasVerifiedEmail())->toBeFalse()
        ->and($harness->persister->persisted())->toBe([]);
});

it('rejects a valid link sent to another user', function (): void {
    $harness = new EmailVerifierHarness();
    $other = new VerifiableUser(id: 8, email: 'grace@example.com');
    $harness->verifier()->sendVerificationLink($other);
    $request = $harness->click($harness->notifier->sent[0]['url']);

    expect(fn () => $harness->verifier()->verify($request))
        ->toThrow(InvalidSignatureException::class, 'This link is invalid.')
        ->and($harness->user->hasVerifiedEmail())->toBeFalse()
        ->and($other->hasVerifiedEmail())->toBeFalse();
});

it(
    'treats verifying an already verified user as a success without persisting or dispatching again',
    function (): void {
        $harness = new EmailVerifierHarness();
        $url = $harness->sendLink();
        $harness->verifier()->verify($harness->click($url));
        $verifiedAt = $harness->user->emailVerifiedAt;
        $harness->clock->travel('+5 minutes');

        $status = $harness->verifier()->verify($harness->click($url));

        expect($status)->toBe(VerificationStatus::AlreadyVerified)
            ->and($harness->user->emailVerifiedAt)->toBe($verifiedAt)
            ->and($harness->persister->persisted())->toHaveCount(1)
            ->and($harness->events->dispatched)->toHaveCount(1);
    },
);

it('throttles resends for the same user within throttle_seconds', function (): void {
    $harness = new EmailVerifierHarness();
    $verifier = $harness->verifier();

    $first = $verifier->sendVerificationLink($harness->user);
    $harness->clock->travel('+30 seconds');
    $second = $verifier->sendVerificationLink($harness->user);
    $harness->clock->travel('+31 seconds');
    $third = $verifier->sendVerificationLink($harness->user);

    expect($first)->toBe(VerificationLinkStatus::Sent)
        ->and($second)->toBe(VerificationLinkStatus::Throttled)
        ->and($third)->toBe(VerificationLinkStatus::Sent)
        ->and($harness->notifier->sent)->toHaveCount(2);
});

it('throttles each user separately', function (): void {
    $harness = new EmailVerifierHarness();
    $verifier = $harness->verifier();

    $verifier->sendVerificationLink($harness->user);
    $other = $verifier->sendVerificationLink(new VerifiableUser(id: 8, email: 'grace@example.com'));

    expect($other)->toBe(VerificationLinkStatus::Sent)
        ->and($harness->notifier->sent)->toHaveCount(2);
});

it('sends no link to a user who is already verified', function (): void {
    $harness = new EmailVerifierHarness();
    $harness->user->markEmailAsVerified($harness->clock->now());

    $status = $harness->verifier()->sendVerificationLink($harness->user);

    expect($status)->toBe(VerificationLinkStatus::AlreadyVerified)
        ->and($harness->notifier->sent)->toBe([]);
});

it('throws UnauthenticatedException when no user is logged in on the guard', function (): void {
    $harness = new EmailVerifierHarness();
    $request = $harness->click($harness->sendLink());
    $harness->guard->setUser(null);

    expect(fn () => $harness->verifier()->verify($request))->toThrow(UnauthenticatedException::class);
});

it(
    'throws a configuration error when the logged-in user does not implement MustVerifyEmailInterface',
    function (): void {
        $harness = new EmailVerifierHarness();
        $request = $harness->click($harness->sendLink());
        $harness->guard->setUser(new FakeAuthenticatable(id: 7));

        expect(fn () => $harness->verifier()->verify($request))
            ->toThrow(AuthException::class, 'does not implement Marko\Authentication\MustVerifyEmailInterface');
    },
);
