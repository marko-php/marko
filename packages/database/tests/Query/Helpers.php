<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Query;

use ReflectionException;
use ReflectionParameter;

/**
 * Query test assertion helpers.
 */
final class Helpers
{
    /**
     * Asserts that method parameters match column, operator, value signature.
     *
     * @param array<ReflectionParameter> $params
     */
    public static function assertColumnOperatorValueParams(
        array $params,
    ): void {
        expect($params)->toHaveCount(3)
            ->and($params[0]->getName())->toBe('column')
            ->and($params[0]->getType()?->getName())->toBe('string')
            ->and($params[1]->getName())->toBe('operator')
            ->and($params[1]->getType()?->getName())->toBe('string')
            ->and($params[2]->getName())->toBe('value')
            ->and($params[2]->getType()?->getName())->toBe('mixed');
    }

    /**
     * Asserts that method parameters match join signature (table, first, operator, second).
     *
     * @param array<ReflectionParameter> $params
     */
    public static function assertJoinParams(
        array $params,
    ): void {
        expect($params)->toHaveCount(4)
            ->and($params[0]->getName())->toBe('table')
            ->and($params[1]->getName())->toBe('first')
            ->and($params[2]->getName())->toBe('operator')
            ->and($params[3]->getName())->toBe('second');
    }

    /**
     * Asserts that method parameters match sql + bindings signature.
     *
     * @param array<ReflectionParameter> $params
     *
     * @throws ReflectionException
     */
    public static function assertSqlBindingsParams(
        array $params,
    ): void {
        expect($params)->toHaveCount(2)
            ->and($params[0]->getName())->toBe('sql')
            ->and($params[0]->getType()?->getName())->toBe('string')
            ->and($params[1]->getName())->toBe('bindings')
            ->and($params[1]->getType()?->getName())->toBe('array')
            ->and($params[1]->isDefaultValueAvailable())->toBeTrue()
            ->and($params[1]->getDefaultValue())->toBe([]);
    }
}
