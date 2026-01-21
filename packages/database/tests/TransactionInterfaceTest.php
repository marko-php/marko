<?php

declare(strict_types=1);

use Marko\Database\Connection\TransactionInterface;

describe('TransactionInterface', function (): void {
    it('defines TransactionInterface with begin, commit, and rollback methods', function (): void {
        $reflection = new ReflectionClass(TransactionInterface::class);

        expect($reflection->isInterface())->toBeTrue()
            ->and($reflection->hasMethod('beginTransaction'))->toBeTrue()
            ->and($reflection->hasMethod('commit'))->toBeTrue()
            ->and($reflection->hasMethod('rollback'))->toBeTrue();

        $begin = $reflection->getMethod('beginTransaction');
        expect($begin->getReturnType()?->getName())->toBe('void');

        $commit = $reflection->getMethod('commit');
        expect($commit->getReturnType()?->getName())->toBe('void');

        $rollback = $reflection->getMethod('rollback');
        expect($rollback->getReturnType()?->getName())->toBe('void');
    });

    it('defines TransactionInterface with transaction callback method', function (): void {
        $reflection = new ReflectionClass(TransactionInterface::class);

        expect($reflection->hasMethod('transaction'))->toBeTrue();

        $transaction = $reflection->getMethod('transaction');
        $params = $transaction->getParameters();
        expect($transaction->getReturnType()?->getName())->toBe('mixed')
            ->and($params)->toHaveCount(1)
            ->and($params[0]->getName())->toBe('callback')
            ->and($params[0]->getType()?->getName())->toBe('callable');
    });
});
