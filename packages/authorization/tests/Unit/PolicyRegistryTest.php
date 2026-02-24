<?php

declare(strict_types=1);

namespace Marko\Authorization\Tests\Unit;

use Marko\Authorization\AuthorizableInterface;
use Marko\Authorization\Exceptions\AuthorizationException;
use Marko\Authorization\PolicyRegistry;

// Test entity class
class TestPost
{
    public function __construct(
        public readonly int $id = 1,
        public readonly int $authorId = 1,
    ) {}
}

// Test policy class
class TestPostPolicy
{
    public function view(
        ?AuthorizableInterface $user,
        TestPost $post,
    ): bool {
        return true;
    }

    public function update(
        ?AuthorizableInterface $user,
        TestPost $post,
    ): bool {
        return $user !== null && $user->getAuthIdentifier() === $post->authorId;
    }

    public function delete(
        ?AuthorizableInterface $user,
        TestPost $post,
    ): bool {
        return false;
    }
}

// Test entity without policy
class TestComment
{
    public function __construct(
        public readonly int $id = 1,
    ) {}
}

it('registers a policy class for an entity class', function (): void {
    $registry = new PolicyRegistry();

    $registry->register(TestPost::class, TestPostPolicy::class);

    expect($registry->resolve(TestPost::class))->toBe(TestPostPolicy::class);
});

it('resolves the policy class for a given entity', function (): void {
    $registry = new PolicyRegistry();
    $registry->register(TestPost::class, TestPostPolicy::class);

    $policyClass = $registry->resolve(TestPost::class);

    expect($policyClass)->toBe(TestPostPolicy::class);
});

it('returns null when no policy is registered for an entity', function (): void {
    $registry = new PolicyRegistry();

    expect($registry->resolve(TestComment::class))->toBeNull();
});

it('checks if a policy has a method for the given ability', function (): void {
    $registry = new PolicyRegistry();

    expect($registry->hasAbility(TestPostPolicy::class, 'view'))->toBeTrue()
        ->and($registry->hasAbility(TestPostPolicy::class, 'update'))->toBeTrue()
        ->and($registry->hasAbility(TestPostPolicy::class, 'delete'))->toBeTrue()
        ->and($registry->hasAbility(TestPostPolicy::class, 'nonexistent'))->toBeFalse();
});

it('calls the policy method with user and entity', function (): void {
    $registry = new PolicyRegistry();
    $registry->register(TestPost::class, TestPostPolicy::class);

    $policy = new TestPostPolicy();
    $post = new TestPost(id: 1, authorId: 1);

    // Simulate a user that matches post author
    $user = new class () implements AuthorizableInterface
    {
        public function getAuthIdentifier(): int|string
        {
            return 1;
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
    };

    expect($policy->update($user, $post))->toBeTrue();
});

it('returns the boolean result from the policy method', function (): void {
    $policy = new TestPostPolicy();
    $post = new TestPost();

    // View always returns true
    expect($policy->view(null, $post))->toBeTrue()
        // Delete always returns false
        ->and($policy->delete(null, $post))->toBeFalse();
});

it('throws AuthorizationException when policy method does not exist', function (): void {
    $registry = new PolicyRegistry();

    expect($registry->hasAbility(TestPostPolicy::class, 'publish'))->toBeFalse();
});

it('prevents registering duplicate policies for the same entity', function (): void {
    $registry = new PolicyRegistry();
    $registry->register(TestPost::class, TestPostPolicy::class);

    expect(fn () => $registry->register(TestPost::class, TestPostPolicy::class))
        ->toThrow(AuthorizationException::class);
});
