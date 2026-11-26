<?php

declare(strict_types=1);

use Marko\Core\Path\ProjectPaths;
use Marko\Database\Exceptions\MigrationException;
use Marko\Database\Migration\DataMigrationDiscovery;
use Marko\Database\Migration\DataMigrator;
use Marko\Database\Migration\MigrationRepository;
use Marko\Database\Tests\Migration\Helpers;
use Marko\Database\Tests\Migration\TransactionalConnectionFake;

describe('DataMigrator', function (): void {
    beforeEach(function (): void {
        $this->basePath = sys_get_temp_dir() . '/marko_data_migrator_unit_' . bin2hex(random_bytes(8));
        $this->dataPath = $this->basePath . '/app/blog/Data';
        mkdir($this->dataPath, 0777, true);
        $this->paths = new ProjectPaths($this->basePath);
        $this->dataMigrator = fn (TransactionalConnectionFake $connection): DataMigrator => new DataMigrator(
            $connection,
            new MigrationRepository(),
            new DataMigrationDiscovery($this->paths),
        );
    });

    afterEach(function (): void {
        Helpers::removeDirectory($this->basePath);
    });

    describe('transactions', function (): void {
        it(
            'runs a data migration and its record in one transaction when the connection supports transactional DDL',
            function (): void {
                file_put_contents(
                    $this->dataPath . '/001_seed.php',
                    Helpers::getStatementMigrationContent('INSERT INTO a', dataMigration: true),
                );
                $connection = new TransactionalConnectionFake();

                ($this->dataMigrator)($connection)->migrate();

                expect($connection->transactions)->toHaveCount(1)
                    ->and($connection->transactions[0])->toHaveCount(2)
                    ->and($connection->transactions[0][0])->toBe('INSERT INTO a')
                    ->and($connection->transactions[0][1])->toContain('INSERT INTO "migrations"');
            },
        );

        it('does not record a data migration whose up fails inside the transaction', function (): void {
            file_put_contents(
                $this->dataPath . '/001_seed.php',
                Helpers::getStatementMigrationContent('INSERT INTO a', failAfterStatement: true, dataMigration: true),
            );
            $connection = new TransactionalConnectionFake();
            $migrator = ($this->dataMigrator)($connection);

            expect(fn () => $migrator->migrate())
                ->toThrow(MigrationException::class, 'boom')
                ->and($connection->transactions)->toHaveCount(1)
                ->and($connection->committed)->not->toContain('INSERT INTO a')
                ->and(implode("\n", $connection->committed))->not->toContain('INSERT INTO "migrations"');
        });

        it('runs a data migration rollback and its delete in one transaction', function (): void {
            file_put_contents(
                $this->dataPath . '/001_seed.php',
                Helpers::getStatementMigrationContent('INSERT INTO a', 'DELETE FROM a', dataMigration: true),
            );
            $connection = new TransactionalConnectionFake(applied: ['001_seed']);

            ($this->dataMigrator)($connection)->rollback();

            expect($connection->transactions)->toHaveCount(1)
                ->and($connection->transactions[0])->toHaveCount(2)
                ->and($connection->transactions[0][0])->toBe('DELETE FROM a')
                ->and($connection->transactions[0][1])->toContain('DELETE FROM "migrations"');
        });

        it('runs a data migration marked WithoutTransaction outside a transaction', function (): void {
            file_put_contents(
                $this->dataPath . '/001_seed.php',
                Helpers::getStatementMigrationContent('INSERT INTO a', withoutTransaction: true, dataMigration: true),
            );
            $connection = new TransactionalConnectionFake();

            ($this->dataMigrator)($connection)->migrate();

            expect($connection->transactions)->toBeEmpty()
                ->and($connection->committed)->toContain('INSERT INTO a');
        });

        it('says a failed data migration was rolled back when it ran inside a transaction', function (): void {
            file_put_contents(
                $this->dataPath . '/001_seed.php',
                Helpers::getStatementMigrationContent('INSERT INTO a', failAfterStatement: true, dataMigration: true),
            );
            $migrator = ($this->dataMigrator)(new TransactionalConnectionFake());

            expect(fn () => $migrator->migrate())
                ->toThrow(
                    MigrationException::class,
                    "Migration '001_seed' failed: boom. The migration was rolled back: nothing from it was applied "
                    . 'and it was not recorded. Fix it and run the migrations again.',
                );
        });
    });
});
