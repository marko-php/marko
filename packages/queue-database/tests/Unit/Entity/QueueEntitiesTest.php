<?php

declare(strict_types=1);

use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Diff\SqlGeneratorInterface;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\SchemaBuilder;
use Marko\Database\MySql\Sql\MySqlGenerator;
use Marko\Database\PgSql\Sql\PgSqlGenerator;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\Table;
use Marko\Queue\Database\Entity\DatabaseFailedJob;
use Marko\Queue\Database\Entity\DatabaseJob;

/**
 * The schema table an entity class declares, as db:migrate builds it.
 */
function queueEntityTable(
    string $entityClass,
): Table {
    return new SchemaBuilder()->build(new EntityMetadataFactory()->parse($entityClass));
}

/**
 * Each column as "name type(length) null|not null default".
 *
 * @return list<string>
 */
function queueEntityColumns(
    Table $table,
): array {
    return array_map(
        fn (Column $column): string => trim(sprintf(
            '%s %s%s %s %s',
            $column->name,
            $column->type,
            $column->length !== null ? "($column->length)" : '',
            $column->nullable ? 'null' : 'not null',
            $column->default !== null ? 'default ' . var_export($column->default, true) : '',
        )),
        $table->columns,
    );
}

/**
 * @return list<string>
 */
function queueEntityDdl(
    SqlGeneratorInterface $generator,
    Table $table,
): array {
    return $generator->generateUp(new SchemaDiff(tablesToCreate: [$table->name => $table]));
}

describe('queue-database entities', function (): void {
    it('maps DatabaseJob to the jobs table with the documented columns', function (): void {
        $table = queueEntityTable(DatabaseJob::class);

        expect($table->name)->toBe('jobs')
            ->and(queueEntityColumns($table))->toBe([
                'id varchar(36) not null',
                "queue varchar(255) not null default 'default'",
                'payload text not null',
                'attempts integer not null default 0',
                'reserved_at timestamp null',
                'available_at timestamp not null',
                'created_at timestamp not null default \'CURRENT_TIMESTAMP\'',
            ]);
    });

    it('indexes jobs on queue and available_at as jobs_queue_available_at_index', function (): void {
        $indexes = queueEntityTable(DatabaseJob::class)->indexes;

        expect($indexes)->toHaveCount(1)
            ->and($indexes[0]->name)->toBe('jobs_queue_available_at_index')
            ->and($indexes[0]->columns)->toBe(['queue', 'available_at']);
    });

    it('maps DatabaseFailedJob to the failed_jobs table with the documented columns', function (): void {
        $table = queueEntityTable(DatabaseFailedJob::class);

        expect($table->name)->toBe('failed_jobs')
            ->and(queueEntityColumns($table))->toBe([
                'id varchar(36) not null',
                'queue varchar(255) not null',
                'payload text not null',
                'exception text not null',
                'failed_at timestamp not null default \'CURRENT_TIMESTAMP\'',
            ]);
    });

    it('generates the documented jobs and failed_jobs DDL on MySQL and PostgreSQL', function (): void {
        $jobs = queueEntityTable(DatabaseJob::class);
        $failedJobs = queueEntityTable(DatabaseFailedJob::class);

        expect(queueEntityDdl(new MySqlGenerator(), $jobs))->toBe([
            'CREATE TABLE `jobs` (`id` VARCHAR(36) NOT NULL, `queue` VARCHAR(255) NOT NULL DEFAULT \'default\', '
            . '`payload` TEXT NOT NULL, `attempts` INT NOT NULL DEFAULT 0, `reserved_at` TIMESTAMP NULL, '
            . '`available_at` TIMESTAMP NOT NULL, `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, '
            . 'PRIMARY KEY (`id`), INDEX `jobs_queue_available_at_index` (`queue`, `available_at`))',
        ])
            ->and(queueEntityDdl(new MySqlGenerator(), $failedJobs))->toBe([
                'CREATE TABLE `failed_jobs` (`id` VARCHAR(36) NOT NULL, `queue` VARCHAR(255) NOT NULL, '
                . '`payload` TEXT NOT NULL, `exception` TEXT NOT NULL, '
                . '`failed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`))',
            ])
            ->and(implode("\n", queueEntityDdl(new PgSqlGenerator(), $jobs)))
            ->toContain('"id" VARCHAR(36) PRIMARY KEY')
            ->toContain('"queue" VARCHAR(255) NOT NULL DEFAULT \'default\'')
            ->toContain('"attempts" INTEGER NOT NULL DEFAULT 0')
            ->toContain('"reserved_at" TIMESTAMP,')
            ->toContain('"available_at" TIMESTAMP NOT NULL')
            ->toContain('"created_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP')
            ->toContain('CREATE INDEX "jobs_queue_available_at_index" ON "jobs" ("queue", "available_at")')
            ->and(implode("\n", queueEntityDdl(new PgSqlGenerator(), $failedJobs)))
            ->toContain('"exception" TEXT NOT NULL')
            ->toContain('"failed_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
    });

    it('ships no migration classes', function (): void {
        expect(is_dir(dirname(__DIR__, 3) . '/src/Migration'))->toBeFalse()
            ->and(is_dir(dirname(__DIR__, 3) . '/src/Entity'))->toBeTrue();
    });
});
