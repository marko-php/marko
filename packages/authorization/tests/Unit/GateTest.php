<?php

declare(strict_types=1);

namespace Marko\Authorization\Tests\Unit;

use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Authorization\AuthorizableInterface;
use Marko\Authorization\Contracts\GateInterface;
use Marko\Authorization\Exceptions\AuthorizationException;
use Marko\Authorization\Gate;
use Marko\Authorization\PolicyRegistry;

// Stub for GuardInterface
class StubGuard implements GuardInterface
{
    private ?AuthenticatableInterface $authenticatedUser = null;

    public ?UserProviderInterface $provider = null {
        set {
            $this->provider = $value;
        }
    }

    public function setUser(
        ?AuthenticatableInterface $user,
    ): void {
        $this->authenticatedUser = $user;
    }

    public function check(): bool
    {
        return $this->authenticatedUser !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function user(): ?AuthenticatableInterface
    {
        return $this->authenticatedUser;
    }

    public function id(): int|string|null
    {
        return $this->authenticatedUser?->getAuthIdentifier();
    }

    public function attempt(
        array $credentials,
    ): bool {
        return false;
    }

    public function login(
        AuthenticatableInterface $user,
    ): void {
        $this->authenticatedUser = $user;
    }

    public function loginById(
        int|string $id,
    ): ?AuthenticatableInterface {
        return null;
    }

    public function logout(): void
    {
        $this->authenticatedUser = null;
    }

    public function getName(): string
    {
        return 'test';
    }
}

// Stub AuthorizableInterface user
class StubUser implements AuthorizableInterface
{
    public function __construct(
        private readonly int $id = 1,
    ) {}

    public function getAuthIdentifier(): int|string
    {
        return $this->id;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthPassword(): string
    {
        return 'hashed';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken(
        ?string $token,
    ): void {}

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }

    public function can(
        string $ability,
        mixed ...$arguments,
    ): bool {
        return false;
    }
}

function createGate(
    ?GuardInterface $guard = null,
): Gate {
    $stubGuard = $guard ?? new StubGuard();

    return new Gate(
        guard: $stubGuard,
        policyRegistry: new PolicyRegistry(),
    );
}

it('defines abilities with closures via define method', function (): void {
    $gate = createGate();

    $gate->define('edit-post', fn (?AuthorizableInterface $user): bool => true);

    expect($gate)->toBeInstanceOf(GateInterface::class);
});

it('checks if an ability is allowed via allows method', function (): void {
    $gate = createGate();
    $gate->define('create-post', fn (?AuthorizableInterface $user): bool => true);

    expect($gate->allows('create-post'))->toBeTrue();
});

it('checks if an ability is denied via denies method', function (): void {
    $gate = createGate();
    $gate->define('delete-post', fn (?AuthorizableInterface $user): bool => false);

    expect($gate->denies('delete-post'))->toBeTrue();
});

it('passes the current user to ability closures', function (): void {
    $guard = new StubGuard();
    $user = new StubUser(id: 42);
    $guard->setUser($user);

    $gate = createGate(guard: $guard);

    $receivedUser = null;
    $gate->define('view-post', function (?AuthorizableInterface $user) use (&$receivedUser): bool {
        $receivedUser = $user;

        return true;
    });

    $gate->allows('view-post');

    expect($receivedUser)->toBe($user)
        ->and($receivedUser->getAuthIdentifier())->toBe(42);
});

it('passes additional arguments to ability closures', function (): void {
    $guard = new StubGuard();
    $guard->setUser(new StubUser());
    $gate = createGate(guard: $guard);

    $receivedArgs = [];
    $gate->define('update-post', function (?AuthorizableInterface $user, mixed ...$args) use (&$receivedArgs): bool {
        $receivedArgs = $args;

        return true;
    });

    $gate->allows('update-post', 'arg1', 'arg2');

    expect($receivedArgs)->toBe(['arg1', 'arg2']);
});

it('returns false for undefined abilities', function (): void {
    $gate = createGate();

    expect($gate->allows('nonexistent'))->toBeFalse()
        ->and($gate->denies('nonexistent'))->toBeTrue();
});

it('throws AuthorizationException from authorize when denied', function (): void {
    $gate = createGate();
    $gate->define('delete-all', fn (?AuthorizableInterface $user): bool => false);

    expect(fn () => $gate->authorize('delete-all'))
        ->toThrow(AuthorizationException::class);
});

it('returns true from authorize when allowed', function (): void {
    $gate = createGate();
    $gate->define('view-dashboard', fn (?AuthorizableInterface $user): bool => true);

    expect($gate->authorize('view-dashboard'))->toBeTrue();
});

it('handles guest users by passing null to closures', function (): void {
    // Guard with no user set
    $gate = createGate();

    $receivedUser = 'not-null-sentinel';
    $gate->define('public-page', function (?AuthorizableInterface $user) use (&$receivedUser): bool {
        $receivedUser = $user;

        return true;
    });

    $gate->allows('public-page');

    expect($receivedUser)->toBeNull();
});

it('allows overwriting previously defined abilities', function (): void {
    $gate = createGate();

    $gate->define('edit-post', fn (?AuthorizableInterface $user): bool => false);
    expect($gate->allows('edit-post'))->toBeFalse();

    $gate->define('edit-post', fn (?AuthorizableInterface $user): bool => true);
    expect($gate->allows('edit-post'))->toBeTrue();
});
