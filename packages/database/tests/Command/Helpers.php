<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Command;

use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Database\Command\DiffCommand;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Diff\DiffCalculator;
use Marko\Database\Entity\EntityDiscovery;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\SchemaBuilder;
use Marko\Database\Introspection\IntrospectorInterface;
use Marko\Database\Schema\Table;

/**
 * Helper to capture command output.
 *
 * @return array{stream: resource, output: Output}
 */
function createOutputStream(): array
{
    $stream = fopen('php://memory', 'r+');

    return [
        'stream' => $stream,
        'output' => new Output($stream),
    ];
}

/**
 * Helper to get output content from stream.
 *
 * @param resource $stream
 */
function getOutputContent(
    mixed $stream,
): string {
    rewind($stream);

    return stream_get_contents($stream);
}

/**
 * Helper to create a stub EntityDiscovery.
 *
 * @param array<class-string> $entities
 */
function createStubEntityDiscovery(
    array $entities = [],
): EntityDiscovery {
    return new class ($entities) extends EntityDiscovery
    {
        public function __construct(
            private readonly array $entities,
        ) {}

        public function discoverInVendor(
            string $vendorPath,
        ): array {
            return $this->entities;
        }

        public function discoverInModules(
            string $modulesPath,
        ): array {
            return [];
        }

        public function discoverInApp(
            string $appPath,
        ): array {
            return [];
        }
    };
}

/**
 * Helper to create a stub IntrospectorInterface.
 *
 * @param array<string, Table> $tables
 */
function createStubIntrospector(
    array $tables = [],
): IntrospectorInterface {
    return new readonly class ($tables) implements IntrospectorInterface
    {
        /**
         * @param array<string, Table> $tables
         */
        public function __construct(
            private array $tables,
        ) {}

        public function getTables(): array
        {
            return array_keys($this->tables);
        }

        public function getTable(
            string $name,
        ): ?Table {
            return $this->tables[$name] ?? null;
        }

        public function tableExists(
            string $name,
        ): bool {
            return isset($this->tables[$name]);
        }

        public function getColumns(
            string $table,
        ): array {
            return $this->tables[$table]?->columns ?? [];
        }

        public function getIndexes(
            string $table,
        ): array {
            return $this->tables[$table]?->indexes ?? [];
        }

        public function getForeignKeys(
            string $table,
        ): array {
            return $this->tables[$table]?->foreignKeys ?? [];
        }

        public function getPrimaryKey(
            string $table,
        ): array {
            foreach ($this->getColumns($table) as $column) {
                if ($column->primaryKey) {
                    return [$column->name];
                }
            }

            return [];
        }
    };
}

/**
 * Helper to create a stub ConnectionInterface.
 */
function createStubConnection(): ConnectionInterface
{
    return new class () implements ConnectionInterface
    {
        public function connect(): void {}

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return true;
        }

        public function query(
            string $sql,
            array $bindings = [],
        ): array {
            return [];
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            return 0;
        }

        public function prepare(
            string $sql,
        ): StatementInterface {
            return new class () implements StatementInterface
            {
                public function execute(
                    array $bindings = [],
                ): bool {
                    return true;
                }

                public function fetchAll(): array
                {
                    return [];
                }

                public function fetch(): ?array
                {
                    return null;
                }

                public function rowCount(): int
                {
                    return 0;
                }
            };
        }

        public function lastInsertId(): int
        {
            return 0;
        }
    };
}

/**
 * Helper to create a DiffCommand with standard dependencies.
 *
 * @param array<string, Table> $tables Tables for introspector
 */
function createDiffCommand(
    ?DiffCalculator $diffCalculator = null,
    array $tables = [],
): DiffCommand {
    return new DiffCommand(
        discovery: createStubEntityDiscovery(),
        introspector: createStubIntrospector($tables),
        metadataFactory: new EntityMetadataFactory(),
        schemaBuilder: new SchemaBuilder(),
        diffCalculator: $diffCalculator ?? new DiffCalculator(),
        vendorPath: '/vendor',
        modulesPath: '/modules',
        appPath: '/app',
    );
}

/**
 * Helper to execute a DiffCommand and return the output.
 *
 * @return array{output: string, exitCode: int}
 */
function executeDiffCommand(
    DiffCommand $command,
): array {
    ['stream' => $stream, 'output' => $output] = createOutputStream();
    $input = new Input(['marko', 'db:diff']);

    $exitCode = $command->execute($input, $output);
    $result = getOutputContent($stream);

    return ['output' => $result, 'exitCode' => $exitCode];
}
