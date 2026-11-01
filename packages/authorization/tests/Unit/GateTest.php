<?php

declare(strict_types=1);

namespace Marko\Authorization\Tests\Unit;

use DateTimeImmutable;
use Marko\Authentication\Contracts\AbilityScopedGuardInterface;
use Marko\Authorization\AuthorizableInterface;
use Marko\Authorization\Contracts\GateInterface;
use Marko\Authorization\Exceptions\AuthorizationException;
use Marko\Authorization\Gate;
use Marko\Authorization\PolicyRegistry;
use Marko\Authorization\Tests\Support\GateHooks;
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\Testing\Fake\FakeGuard;

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

    public function getRememberTokenExpiresAt(): ?DateTimeImmutable
    {
        return null;
    }

    public function setRememberTokenExpiresAt(
        ?DateTimeImmutable $expiresAt,
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

/**
 * A guard whose credential (like an API token) grants only the listed abilities.
 */
class ScopedFakeGuard extends FakeGuard implements AbilityScopedGuardInterface
{
    /** @var array<string> */
    public array $checkedAbilities = [];

    /**
     * @param array<string> $grantedAbilities
     */
    public function __construct(
        private readonly array $grantedAbilities,
    ) {
        parent::__construct(name: 'api', attemptResult: false);
    }

    public function hasAbility(
        string $ability,
    ): bool {
        $this->checkedAbilities[] = $ability;

        return in_array($ability, $this->grantedAbilities, true);
    }
}

class ScopedPost {}

class ScopedPostPolicy
{
    public function update(
        ?AuthorizableInterface $user,
        ScopedPost $post,
    ): bool {
        return true;
    }
}

function createGate(
    ?FakeGuard $guard = null,
): Gate {
    $stubGuard = $guard ?? new FakeGuard(name: 'test', attemptResult: false);

    return new Gate(
        guard: $stubGuard,
        policyRegistry: new PolicyRegistry(),
        gateHookRegistry: GateHooks::registry(),
        eventDispatcher: new FakeEventDispatcher(),
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
    $guard = new FakeGuard(name: 'test', attemptResult: false);
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
    $guard = new FakeGuard(name: 'test', attemptResult: false);
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

it('throws a 403 AuthorizationException from authorize when denied', function (): void {
    $gate = createGate();
    $gate->define('delete-all', fn (?AuthorizableInterface $user): bool => false);

    try {
        $gate->authorize('delete-all');
        $this->fail('Expected AuthorizationException');
    } catch (AuthorizationException $exception) {
        expect($exception->getStatusCode())->toBe(403)
            ->and($exception->getAbility())->toBe('delete-all')
            ->and($exception->getResponseData())->toBe(['message' => 'Forbidden.']);
    }
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

describe('ability-scoped guards (API tokens)', function (): void {
    it('denies an ability the credential does not grant even when the closure allows it', function (): void {
        $guard = new ScopedFakeGuard(['posts:read']);
        $guard->setUser(new StubUser());
        $gate = createGate(guard: $guard);
        $gate->define('posts:delete', fn (?AuthorizableInterface $user): bool => true);

        expect($gate->allows('posts:delete'))->toBeFalse()
            ->and($gate->denies('posts:delete'))->toBeTrue()
            ->and($guard->checkedAbilities)->toBe(['posts:delete', 'posts:delete']);
    });

    it('allows an ability the credential grants when the closure allows it', function (): void {
        $guard = new ScopedFakeGuard(['posts:read']);
        $guard->setUser(new StubUser());
        $gate = createGate(guard: $guard);
        $gate->define('posts:read', fn (?AuthorizableInterface $user): bool => true);

        expect($gate->allows('posts:read'))->toBeTrue();
    });

    it('still denies when the credential grants the ability but the closure denies it', function (): void {
        $guard = new ScopedFakeGuard(['posts:read']);
        $guard->setUser(new StubUser());
        $gate = createGate(guard: $guard);
        $gate->define('posts:read', fn (?AuthorizableInterface $user): bool => false);

        expect($gate->allows('posts:read'))->toBeFalse();
    });

    it('denies an ability the credential does not grant even when a policy allows it', function (): void {
        $guard = new ScopedFakeGuard([]);
        $guard->setUser(new StubUser());
        $gate = createGate(guard: $guard);
        $gate->policy(ScopedPost::class, ScopedPostPolicy::class);

        expect($gate->allows('update', new ScopedPost()))->toBeFalse();
    });

    it('throws a 403 from authorize when the credential lacks the ability', function (): void {
        $guard = new ScopedFakeGuard([]);
        $guard->setUser(new StubUser());
        $gate = createGate(guard: $guard);
        $gate->define('reports:export', fn (?AuthorizableInterface $user): bool => true);

        expect(fn () => $gate->authorize('reports:export'))->toThrow(AuthorizationException::class);
    });

    it('does not consult the credential for a guest', function (): void {
        $guard = new ScopedFakeGuard([]);
        $gate = createGate(guard: $guard);
        $gate->define('public-page', fn (?AuthorizableInterface $user): bool => true);

        expect($gate->allows('public-page'))->toBeTrue()
            ->and($guard->checkedAbilities)->toBe([]);
    });
});
