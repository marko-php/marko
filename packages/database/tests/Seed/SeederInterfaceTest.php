<?php

declare(strict_types=1);

use Marko\Database\Seed\SeederInterface;

describe('SeederInterface', function (): void {
    it('defines SeederInterface with run(Connection) method', function (): void {
        $reflection = new ReflectionClass(SeederInterface::class);

        expect($reflection->isInterface())->toBeTrue()
            ->and($reflection->hasMethod('run'))->toBeTrue();

        $run = $reflection->getMethod('run');
        expect($run->getReturnType()?->getName())->toBe('void');

        $params = $run->getParameters();
        expect($params)->toHaveCount(1)
            ->and($params[0]->getName())->toBe('connection')
            ->and($params[0]->getType()?->getName())->toBe('Marko\\Database\\Connection\\ConnectionInterface');
    });
});
