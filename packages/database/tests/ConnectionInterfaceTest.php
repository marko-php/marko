<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Tests\Query\Helpers;

describe('ConnectionInterface', function (): void {
    it('defines ConnectionInterface with connect, disconnect, and isConnected methods', function (): void {
        $reflection = new ReflectionClass(ConnectionInterface::class);

        expect($reflection->isInterface())->toBeTrue()
            ->and($reflection->hasMethod('connect'))->toBeTrue()
            ->and($reflection->hasMethod('disconnect'))->toBeTrue()
            ->and($reflection->hasMethod('isConnected'))->toBeTrue();

        $connect = $reflection->getMethod('connect');
        expect($connect->getReturnType()?->getName())->toBe('void');

        $disconnect = $reflection->getMethod('disconnect');
        expect($disconnect->getReturnType()?->getName())->toBe('void');

        $isConnected = $reflection->getMethod('isConnected');
        expect($isConnected->getReturnType()?->getName())->toBe('bool');
    });

    it('defines ConnectionInterface with query and execute methods', function (): void {
        $reflection = new ReflectionClass(ConnectionInterface::class);

        expect($reflection->hasMethod('query'))->toBeTrue()
            ->and($reflection->hasMethod('execute'))->toBeTrue();

        $query = $reflection->getMethod('query');
        expect($query->getReturnType()?->getName())->toBe('array');
        Helpers::assertSqlBindingsParams($query->getParameters());

        $execute = $reflection->getMethod('execute');
        expect($execute->getReturnType()?->getName())->toBe('int');
        Helpers::assertSqlBindingsParams($execute->getParameters());
    });

    it('defines ConnectionInterface with prepare and statement execution', function (): void {
        $reflection = new ReflectionClass(ConnectionInterface::class);

        expect($reflection->hasMethod('prepare'))->toBeTrue();

        $prepare = $reflection->getMethod('prepare');
        $prepareParams = $prepare->getParameters();
        expect($prepare->getReturnType()?->getName())->toBe('Marko\\Database\\Connection\\StatementInterface')
            ->and($prepareParams)->toHaveCount(1)
            ->and($prepareParams[0]->getName())->toBe('sql')
            ->and($prepareParams[0]->getType()?->getName())->toBe('string');
    });
});
