<?php

declare(strict_types=1);

use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Connection\ConnectionFactoryInterface;
use Marko\Database\Connection\ConnectionInterface;

it('defines ConnectionFactoryInterface with a make method returning ConnectionInterface', function (): void {
    $reflection = new ReflectionClass(ConnectionFactoryInterface::class);

    expect($reflection->isInterface())->toBeTrue()
        ->and($reflection->hasMethod('make'))->toBeTrue();

    $make = $reflection->getMethod('make');
    $params = $make->getParameters();

    expect($make->getReturnType()?->getName())->toBe(ConnectionInterface::class)
        ->and($params)->toHaveCount(1)
        ->and($params[0]->getName())->toBe('config')
        ->and($params[0]->getType()?->getName())->toBe(DatabaseConfig::class);
});
