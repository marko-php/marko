<?php

declare(strict_types=1);

use Marko\Database\Seed\SeederInterface;

describe('SeederInterface', function (): void {
    it('defines SeederInterface with run() method', function (): void {
        $reflection = new ReflectionClass(SeederInterface::class);

        expect($reflection->isInterface())->toBeTrue()
            ->and($reflection->hasMethod('run'))->toBeTrue();

        $run = $reflection->getMethod('run');
        expect($run->getReturnType()?->getName())->toBe('void');

        $params = $run->getParameters();
        expect($params)->toHaveCount(0);
    });
});
