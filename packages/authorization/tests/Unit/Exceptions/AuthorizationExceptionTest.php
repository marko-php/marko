<?php

declare(strict_types=1);

namespace Marko\Authorization\Tests\Unit\Exceptions;

use Exception;
use Marko\Authorization\Exceptions\AuthorizationException;

it('creates AuthorizationException with ability and resource context', function (): void {
    $exception = new AuthorizationException(
        message: 'Forbidden',
        ability: 'update',
        resource: 'Post',
    );

    expect($exception)->toBeInstanceOf(Exception::class)
        ->and($exception->getMessage())->toBe('Forbidden')
        ->and($exception->getAbility())->toBe('update')
        ->and($exception->getResource())->toBe('Post');
});

it('creates AuthorizationException via forbidden factory method', function (): void {
    $exception = AuthorizationException::forbidden(
        ability: 'delete',
        resource: 'Comment',
    );

    expect($exception)->toBeInstanceOf(AuthorizationException::class)
        ->and($exception->getMessage())->toBe('Forbidden')
        ->and($exception->getAbility())->toBe('delete')
        ->and($exception->getResource())->toBe('Comment')
        ->and($exception->getContext())->toContain('delete')
        ->and($exception->getContext())->toContain('Comment');
});

it('creates AuthorizationException via missingPolicy factory method', function (): void {
    $exception = AuthorizationException::missingPolicy(
        entityClass: 'App\\Entity\\Post',
        ability: 'update',
    );

    expect($exception)->toBeInstanceOf(AuthorizationException::class)
        ->and($exception->getMessage())->toContain('No policy')
        ->and($exception->getContext())->toContain('App\\Entity\\Post')
        ->and($exception->getContext())->toContain('update')
        ->and($exception->getSuggestion())->toContain('policy');
});

it('provides context and suggestion on AuthorizationException', function (): void {
    $exception = new AuthorizationException(
        message: 'Access denied',
        ability: 'create',
        resource: 'Article',
        context: 'User lacks create permission on Article',
        suggestion: 'Ensure the user has the create ability or register a policy',
    );

    expect($exception->getContext())->toBe('User lacks create permission on Article')
        ->and($exception->getSuggestion())->toBe('Ensure the user has the create ability or register a policy')
        ->and($exception->getAbility())->toBe('create')
        ->and($exception->getResource())->toBe('Article');
});
