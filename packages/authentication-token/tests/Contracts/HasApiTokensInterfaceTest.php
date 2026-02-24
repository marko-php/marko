<?php

declare(strict_types=1);

namespace Marko\AuthenticationToken\Tests\Contracts;

use Marko\AuthenticationToken\Contracts\HasApiTokensInterface;
use Marko\AuthenticationToken\Contracts\NewAccessToken;
use ReflectionClass;

it('defines HasApiTokensInterface for entities that can have tokens', function (): void {
    $reflection = new ReflectionClass(HasApiTokensInterface::class);

    expect($reflection->isInterface())->toBeTrue();

    // getTokens(): array
    $getTokensMethod = $reflection->getMethod('getTokens');
    $getTokensReturn = $getTokensMethod->getReturnType();
    expect($reflection->hasMethod('getTokens'))->toBeTrue()
        ->and($getTokensReturn->getName())->toBe('array')
        ->and($getTokensMethod->getParameters())->toHaveCount(0);

    // createToken(string $name, array $abilities = []): NewAccessToken
    $createTokenMethod = $reflection->getMethod('createToken');
    $createTokenParams = $createTokenMethod->getParameters();
    $createTokenReturn = $createTokenMethod->getReturnType();
    expect($reflection->hasMethod('createToken'))->toBeTrue()
        ->and($createTokenParams)->toHaveCount(2)
        ->and($createTokenParams[0]->getName())->toBe('name')
        ->and($createTokenParams[0]->getType()->getName())->toBe('string')
        ->and($createTokenParams[1]->getName())->toBe('abilities')
        ->and($createTokenParams[1]->getType()->getName())->toBe('array')
        ->and($createTokenParams[1]->isDefaultValueAvailable())->toBeTrue()
        ->and($createTokenReturn->getName())->toBe(NewAccessToken::class);
});
