<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Fixtures\Variant;

use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Diff\SqlGeneratorInterface;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\Table;

class VariantSqlGenerator implements SqlGeneratorInterface
{
    public function generateUp(SchemaDiff $diff): array
    {
        return [];
    }

    public function generateDown(SchemaDiff $diff): array
    {
        return [];
    }

    public function generateCreateTable(Table $table): string
    {
        return '';
    }

    public function generateDropTable(string $tableName): string
    {
        return '';
    }

    public function generateAddColumn(
        string $table,
        Column $column,
    ): string
    {
        return '';
    }

    public function generateDropColumn(
        string $table,
        string $columnName,
    ): string
    {
        return '';
    }

    public function generateModifyColumn(
        string $table,
        Column $column,
        Column $oldColumn,
    ): string
    {
        return '';
    }

    public function generateAddIndex(
        string $table,
        Index $index,
    ): string
    {
        return '';
    }

    public function generateDropIndex(
        string $table,
        string $indexName,
    ): string
    {
        return '';
    }

    public function generateAddForeignKey(
        string $table,
        ForeignKey $foreignKey,
    ): string
    {
        return '';
    }

    public function generateDropForeignKey(
        string $table,
        string $keyName,
    ): string
    {
        return '';
    }
}
