<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Fixtures\Variant;

use Marko\Database\Introspection\IntrospectorInterface;
use Marko\Database\Schema\Table;

class VariantIntrospector implements IntrospectorInterface
{
    public function getTables(): array
    {
        return [];
    }

    public function getTable(string $name): ?Table
    {
        return null;
    }

    public function tableExists(string $name): bool
    {
        return false;
    }

    public function getColumns(string $table): array
    {
        return [];
    }

    public function getIndexes(string $table): array
    {
        return [];
    }

    public function getForeignKeys(string $table): array
    {
        return [];
    }

    public function getPrimaryKey(string $table): array
    {
        return [];
    }
}
