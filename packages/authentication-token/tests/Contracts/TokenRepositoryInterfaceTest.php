<?php

declare(strict_types=1);

namespace Marko\AuthenticationToken\Tests\Contracts;

use Marko\AuthenticationToken\Contracts\TokenRepositoryInterface;
use Marko\AuthenticationToken\Entity\PersonalAccessToken;
use ReflectionClass;

it('defines TokenRepositoryInterface with find, findByToken, create, and revoke methods', function (): void {
    $reflection = new ReflectionClass(TokenRepositoryInterface::class);

    expect($reflection->isInterface())->toBeTrue();

    // find(int $id): ?PersonalAccessToken
    $findMethod = $reflection->getMethod('find');
    $findParams = $findMethod->getParameters();
    $findReturn = $findMethod->getReturnType();
    expect($reflection->hasMethod('find'))->toBeTrue()
        ->and($findParams)->toHaveCount(1)
        ->and($findParams[0]->getName())->toBe('id')
        ->and($findParams[0]->getType()->getName())->toBe('int')
        ->and($findReturn->allowsNull())->toBeTrue()
        ->and($findReturn->getName())->toBe(PersonalAccessToken::class);

    // findByToken(string $tokenHash): ?PersonalAccessToken
    $findByTokenMethod = $reflection->getMethod('findByToken');
    $findByTokenParams = $findByTokenMethod->getParameters();
    $findByTokenReturn = $findByTokenMethod->getReturnType();
    expect($reflection->hasMethod('findByToken'))->toBeTrue()
        ->and($findByTokenParams)->toHaveCount(1)
        ->and($findByTokenParams[0]->getName())->toBe('tokenHash')
        ->and($findByTokenParams[0]->getType()->getName())->toBe('string')
        ->and($findByTokenReturn->allowsNull())->toBeTrue()
        ->and($findByTokenReturn->getName())->toBe(PersonalAccessToken::class);

    // create(PersonalAccessToken $token): PersonalAccessToken
    $createMethod = $reflection->getMethod('create');
    $createParams = $createMethod->getParameters();
    $createReturn = $createMethod->getReturnType();
    expect($reflection->hasMethod('create'))->toBeTrue()
        ->and($createParams)->toHaveCount(1)
        ->and($createParams[0]->getName())->toBe('token')
        ->and($createParams[0]->getType()->getName())->toBe(PersonalAccessToken::class)
        ->and($createReturn->getName())->toBe(PersonalAccessToken::class);

    // revoke(int $id): void
    $revokeMethod = $reflection->getMethod('revoke');
    $revokeParams = $revokeMethod->getParameters();
    $revokeReturn = $revokeMethod->getReturnType();
    expect($reflection->hasMethod('revoke'))->toBeTrue()
        ->and($revokeParams)->toHaveCount(1)
        ->and($revokeParams[0]->getName())->toBe('id')
        ->and($revokeParams[0]->getType()->getName())->toBe('int')
        ->and($revokeReturn->getName())->toBe('void');
});
