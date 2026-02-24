<?php

declare(strict_types=1);

namespace Marko\Authorization\Tests\Unit;

use Marko\Authorization\AuthorizableInterface;
use Marko\Authorization\Exceptions\AuthorizationException;
use Marko\Authorization\Gate;
use Marko\Authorization\PolicyRegistry;
use Marko\Testing\Fake\FakeGuard;

// Entity classes for policy integration tests
class Article
{
    public function __construct(
        public readonly int $id = 1,
        public readonly int $authorId = 1,
    ) {}
}

class ArticlePolicy
{
    public function view(
        ?AuthorizableInterface $user,
        Article $article,
    ): bool {
        return true;
    }

    public function update(
        ?AuthorizableInterface $user,
        Article $article,
    ): bool {
        return $user !== null && $user->getAuthIdentifier() === $article->authorId;
    }

    public function delete(
        ?AuthorizableInterface $user,
        Article $article,
    ): bool {
        return false;
    }
}

// User stub for integration tests
class IntegrationStubUser implements AuthorizableInterface
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

function createIntegrationGate(
    ?FakeGuard $guard = null,
    ?PolicyRegistry $registry = null,
): Gate {
    return new Gate(
        guard: $guard ?? new FakeGuard(name: 'integration-test', attemptResult: false),
        policyRegistry: $registry ?? new PolicyRegistry(),
    );
}

it('delegates to policy when ability argument is an entity with registered policy', function (): void {
    $guard = new FakeGuard(name: 'integration-test', attemptResult: false);
    $guard->setUser(new IntegrationStubUser(id: 1));

    $gate = createIntegrationGate(guard: $guard);
    $gate->policy(Article::class, ArticlePolicy::class);

    $article = new Article(id: 1, authorId: 1);

    expect($gate->allows('view', $article))->toBeTrue()
        ->and($gate->allows('update', $article))->toBeTrue()
        ->and($gate->allows('delete', $article))->toBeFalse();
});

it('prefers gate closure over policy when both are defined', function (): void {
    $guard = new FakeGuard(name: 'integration-test', attemptResult: false);
    $guard->setUser(new IntegrationStubUser(id: 1));

    $gate = createIntegrationGate(guard: $guard);
    $gate->policy(Article::class, ArticlePolicy::class);

    // Define a closure that always denies 'view', even though policy allows it
    $gate->define('view', fn (?AuthorizableInterface $user, mixed ...$args): bool => false);

    $article = new Article(id: 1, authorId: 1);

    // Closure takes precedence
    expect($gate->allows('view', $article))->toBeFalse();
});

it('falls back to policy when no gate closure matches', function (): void {
    $guard = new FakeGuard(name: 'integration-test', attemptResult: false);
    $guard->setUser(new IntegrationStubUser(id: 1));

    $gate = createIntegrationGate(guard: $guard);
    $gate->policy(Article::class, ArticlePolicy::class);

    // Define closure for different ability, not 'view'
    $gate->define('something-else', fn (?AuthorizableInterface $user): bool => true);

    $article = new Article(id: 1, authorId: 1);

    // 'view' falls back to policy
    expect($gate->allows('view', $article))->toBeTrue();
});

it('denies by default when neither closure nor policy exists', function (): void {
    $gate = createIntegrationGate();

    expect($gate->allows('unknown-ability'))->toBeFalse()
        ->and($gate->denies('unknown-ability'))->toBeTrue();
});

it('registers policies via gate policy method', function (): void {
    $gate = createIntegrationGate();

    $gate->policy(Article::class, ArticlePolicy::class);

    $article = new Article();

    expect($gate->allows('view', $article))->toBeTrue();
});

it('passes user and entity to policy method', function (): void {
    $guard = new FakeGuard(name: 'integration-test', attemptResult: false);
    $user = new IntegrationStubUser(id: 5);
    $guard->setUser($user);

    $gate = createIntegrationGate(guard: $guard);
    $gate->policy(Article::class, ArticlePolicy::class);

    // Article author is 1 but user is 5, so update should be denied
    $article = new Article(id: 1, authorId: 1);

    expect($gate->allows('update', $article))->toBeFalse();

    // Article author matches user
    $ownedArticle = new Article(id: 2, authorId: 5);

    expect($gate->allows('update', $ownedArticle))->toBeTrue();
});

it('handles authorize with entity policies throwing on denial', function (): void {
    $guard = new FakeGuard(name: 'integration-test', attemptResult: false);
    $guard->setUser(new IntegrationStubUser(id: 1));

    $gate = createIntegrationGate(guard: $guard);
    $gate->policy(Article::class, ArticlePolicy::class);

    $article = new Article(id: 1, authorId: 1);

    // View is allowed
    expect($gate->authorize('view', $article))->toBeTrue();

    // Delete is denied - should throw
    expect(fn () => $gate->authorize('delete', $article))
        ->toThrow(AuthorizationException::class);
});
