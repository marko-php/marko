<?php

declare(strict_types=1);

use Marko\Queue\JobInterface;

describe('JobInterface', function (): void {
    it('defines handle method', function (): void {
        $reflection = new ReflectionClass(JobInterface::class);

        expect($reflection->isInterface())->toBeTrue()
            ->and($reflection->hasMethod('handle'))->toBeTrue();

        $method = $reflection->getMethod('handle');

        expect($method->isPublic())->toBeTrue()
            ->and($method->getReturnType()?->getName())->toBe('void');
    });

    it('defines id property and setId method', function (): void {
        $reflection = new ReflectionClass(JobInterface::class);

        // Interface uses property hook: public ?string $id { get; }
        expect($reflection->hasProperty('id'))->toBeTrue();

        $idProperty = $reflection->getProperty('id');

        expect($idProperty->isPublic())->toBeTrue()
            ->and($idProperty->getType()?->allowsNull())->toBeTrue()
            ->and($idProperty->getType()?->getName())->toBe('string')
            ->and($reflection->hasMethod('setId'))->toBeTrue();

        // setId method still exists for setting the ID

        $setId = $reflection->getMethod('setId');

        expect($setId->isPublic())->toBeTrue()
            ->and($setId->getReturnType()?->getName())->toBe('void')
            ->and($setId->getParameters())->toHaveCount(1)
            ->and($setId->getParameters()[0]->getName())->toBe('id')
            ->and($setId->getParameters()[0]->getType()?->getName())->toBe('string');
    });

    it('defines attempt properties and incrementAttempts method', function (): void {
        $reflection = new ReflectionClass(JobInterface::class);

        // Interface uses property hooks: public int $attempts { get; } and public int $maxAttempts { get; }
        expect($reflection->hasProperty('attempts'))->toBeTrue()
            ->and($reflection->hasProperty('maxAttempts'))->toBeTrue()
            ->and($reflection->hasMethod('incrementAttempts'))->toBeTrue();

        $attemptsProperty = $reflection->getProperty('attempts');

        expect($attemptsProperty->isPublic())->toBeTrue()
            ->and($attemptsProperty->getType()?->getName())->toBe('int');

        $maxAttemptsProperty = $reflection->getProperty('maxAttempts');

        expect($maxAttemptsProperty->isPublic())->toBeTrue()
            ->and($maxAttemptsProperty->getType()?->getName())->toBe('int');

        $incrementAttempts = $reflection->getMethod('incrementAttempts');

        expect($incrementAttempts->isPublic())->toBeTrue()
            ->and($incrementAttempts->getReturnType()?->getName())->toBe('void');
    });

    it('defines serialization methods', function (): void {
        $reflection = new ReflectionClass(JobInterface::class);

        expect($reflection->hasMethod('serialize'))->toBeTrue()
            ->and($reflection->hasMethod('unserialize'))->toBeTrue();

        $serialize = $reflection->getMethod('serialize');

        expect($serialize->isPublic())->toBeTrue()
            ->and($serialize->getReturnType()?->getName())->toBe('string');

        $unserialize = $reflection->getMethod('unserialize');

        expect($unserialize->isPublic())->toBeTrue()
            ->and($unserialize->isStatic())->toBeTrue()
            ->and($unserialize->getReturnType()?->getName())->toBe('static')
            ->and($unserialize->getParameters())->toHaveCount(1)
            ->and($unserialize->getParameters()[0]->getName())->toBe('data')
            ->and($unserialize->getParameters()[0]->getType()?->getName())->toBe('string');
    });
});
