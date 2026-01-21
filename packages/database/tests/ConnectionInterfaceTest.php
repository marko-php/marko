<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;

describe('ConnectionInterface', function (): void {
    it('defines ConnectionInterface with connect, disconnect, and isConnected methods', function (): void {
        $reflection = new ReflectionClass(ConnectionInterface::class);

        expect($reflection->isInterface())->toBeTrue();
        expect($reflection->hasMethod('connect'))->toBeTrue();
        expect($reflection->hasMethod('disconnect'))->toBeTrue();
        expect($reflection->hasMethod('isConnected'))->toBeTrue();

        $connect = $reflection->getMethod('connect');
        expect($connect->getReturnType()?->getName())->toBe('void');

        $disconnect = $reflection->getMethod('disconnect');
        expect($disconnect->getReturnType()?->getName())->toBe('void');

        $isConnected = $reflection->getMethod('isConnected');
        expect($isConnected->getReturnType()?->getName())->toBe('bool');
    });

    it('defines ConnectionInterface with query and execute methods', function (): void {
        $reflection = new ReflectionClass(ConnectionInterface::class);

        expect($reflection->hasMethod('query'))->toBeTrue();
        expect($reflection->hasMethod('execute'))->toBeTrue();

        $query = $reflection->getMethod('query');
        expect($query->getReturnType()?->getName())->toBe('array');
        $queryParams = $query->getParameters();
        expect($queryParams)->toHaveCount(2);
        expect($queryParams[0]->getName())->toBe('sql');
        expect($queryParams[0]->getType()?->getName())->toBe('string');
        expect($queryParams[1]->getName())->toBe('bindings');
        expect($queryParams[1]->getType()?->getName())->toBe('array');
        expect($queryParams[1]->isDefaultValueAvailable())->toBeTrue();

        $execute = $reflection->getMethod('execute');
        expect($execute->getReturnType()?->getName())->toBe('int');
        $executeParams = $execute->getParameters();
        expect($executeParams)->toHaveCount(2);
        expect($executeParams[0]->getName())->toBe('sql');
        expect($executeParams[0]->getType()?->getName())->toBe('string');
        expect($executeParams[1]->getName())->toBe('bindings');
        expect($executeParams[1]->getType()?->getName())->toBe('array');
        expect($executeParams[1]->isDefaultValueAvailable())->toBeTrue();
    });

    it('defines ConnectionInterface with prepare and statement execution', function (): void {
        $reflection = new ReflectionClass(ConnectionInterface::class);

        expect($reflection->hasMethod('prepare'))->toBeTrue();

        $prepare = $reflection->getMethod('prepare');
        expect($prepare->getReturnType()?->getName())->toBe('Marko\\Database\\Connection\\StatementInterface');
        $prepareParams = $prepare->getParameters();
        expect($prepareParams)->toHaveCount(1);
        expect($prepareParams[0]->getName())->toBe('sql');
        expect($prepareParams[0]->getType()?->getName())->toBe('string');
    });
});
