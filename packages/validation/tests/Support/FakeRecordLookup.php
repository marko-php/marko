<?php

declare(strict_types=1);

namespace Marko\Validation\Tests\Support;

use Marko\Validation\Contracts\RecordLookupInterface;

/**
 * An in-memory RecordLookupInterface: records live in $records as entity class => list of property => value rows,
 * and every call is recorded in $calls so a test can check what a rule asked for.
 */
class FakeRecordLookup implements RecordLookupInterface
{
    /**
     * @var list<array{method: string, entityClass: string, property: string, value: mixed, ignoreId: mixed, where: array<string, mixed>, caseInsensitive: bool}>
     */
    public array $calls = [];

    /**
     * @param array<string, list<array<string, mixed>>> $records
     */
    public function __construct(
        private readonly array $records = [],
        private readonly string $primaryKey = 'id',
    ) {}

    public function exists(
        string $entityClass,
        string $property,
        mixed $value,
        array $where = [],
    ): bool {
        $this->calls[] = [
            'method' => 'exists',
            'entityClass' => $entityClass,
            'property' => $property,
            'value' => $value,
            'ignoreId' => null,
            'where' => $where,
            'caseInsensitive' => false,
        ];

        return $this->find($entityClass, $property, $value, null, $where, false);
    }

    public function isTaken(
        string $entityClass,
        string $property,
        mixed $value,
        mixed $ignoreId = null,
        array $where = [],
        bool $caseInsensitive = false,
    ): bool {
        $this->calls[] = [
            'method' => 'isTaken',
            'entityClass' => $entityClass,
            'property' => $property,
            'value' => $value,
            'ignoreId' => $ignoreId,
            'where' => $where,
            'caseInsensitive' => $caseInsensitive,
        ];

        return $this->find($entityClass, $property, $value, $ignoreId, $where, $caseInsensitive);
    }

    /**
     * @param array<string, mixed> $where
     */
    private function find(
        string $entityClass,
        string $property,
        mixed $value,
        mixed $ignoreId,
        array $where,
        bool $caseInsensitive,
    ): bool {
        $normalize = static fn (mixed $v): mixed => $caseInsensitive && is_string($v) ? strtolower($v) : $v;

        foreach ($this->records[$entityClass] ?? [] as $row) {
            if ($normalize($row[$property] ?? null) !== $normalize($value)) {
                continue;
            }

            if ($ignoreId !== null && $row[$this->primaryKey] === $ignoreId) {
                continue;
            }

            if (array_any($where, static fn (mixed $expected, string $key): bool => $row[$key] !== $expected)) {
                continue;
            }

            return true;
        }

        return false;
    }
}
