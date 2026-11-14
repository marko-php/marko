<?php

declare(strict_types=1);

namespace Marko\Testing\Tests\DatabaseApp;

use Marko\Database\Introspection\IntrospectorInterface;
use Marko\Database\Schema\Table;

/**
 * Reports the tables listed in $tables as existing.
 */
class RecordingIntrospector implements IntrospectorInterface
{
    /** @var list<string> */
    public array $tables = ['migrations', 'shows', 'venues'];

    public function getTables(): array
    {
        return $this->tables;
    }

    public function getTable(
        string $name,
    ): ?Table {
        return null;
    }

    public function tableExists(
        string $name,
    ): bool {
        return in_array($name, $this->tables, true);
    }

    public function getColumns(
        string $table,
    ): array {
        return [];
    }

    public function getIndexes(
        string $table,
    ): array {
        return [];
    }

    public function getForeignKeys(
        string $table,
    ): array {
        return [];
    }

    public function getPrimaryKey(
        string $table,
    ): array {
        return [];
    }
}
