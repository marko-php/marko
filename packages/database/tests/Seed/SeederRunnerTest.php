<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Exceptions\SeederException;
use Marko\Database\Seed\SeederDefinition;
use Marko\Database\Seed\SeederInterface;
use Marko\Database\Seed\SeederRunner;

describe('SeederRunner', function (): void {
    it('runs seeders in order specified by attribute', function (): void {
        $executionOrder = [];

        // Create mock seeders with different orders
        $seeder1 = new class ($executionOrder) implements SeederInterface
        {
            public function __construct(
                private array &$order,
            ) {}

            public function run(
                ConnectionInterface $connection,
            ): void {
                $this->order[] = 'third';
            }
        };

        $seeder2 = new class ($executionOrder) implements SeederInterface
        {
            public function __construct(
                private array &$order,
            ) {}

            public function run(
                ConnectionInterface $connection,
            ): void {
                $this->order[] = 'first';
            }
        };

        $seeder3 = new class ($executionOrder) implements SeederInterface
        {
            public function __construct(
                private array &$order,
            ) {}

            public function run(
                ConnectionInterface $connection,
            ): void {
                $this->order[] = 'second';
            }
        };

        $definitions = [
            new SeederDefinition(seederClass: get_class($seeder1), name: 'third', order: 30),
            new SeederDefinition(seederClass: get_class($seeder2), name: 'first', order: 10),
            new SeederDefinition(seederClass: get_class($seeder3), name: 'second', order: 20),
        ];

        $connection = $this->createMock(ConnectionInterface::class);

        $runner = new SeederRunner(
            seeders: [
                get_class($seeder1) => $seeder1,
                get_class($seeder2) => $seeder2,
                get_class($seeder3) => $seeder3,
            ],
            isProduction: false,
        );

        $runner->runAll($definitions, $connection);

        expect($executionOrder)->toBe(['first', 'second', 'third']);
    });

    it('provides SeederRunner to execute discovered seeders', function (): void {
        $executed = false;

        $seeder = new class ($executed) implements SeederInterface
        {
            public function __construct(
                private bool &$executed,
            ) {}

            public function run(
                ConnectionInterface $connection,
            ): void {
                $this->executed = true;
            }
        };

        $definitions = [
            new SeederDefinition(seederClass: get_class($seeder), name: 'test', order: 0),
        ];

        $connection = $this->createMock(ConnectionInterface::class);

        $runner = new SeederRunner(
            seeders: [get_class($seeder) => $seeder],
            isProduction: false,
        );

        $runner->runAll($definitions, $connection);

        expect($executed)->toBeTrue();
    });

    it('blocks seeder execution in production environment', function (): void {
        $seeder = new class () implements SeederInterface
        {
            public function run(
                ConnectionInterface $connection,
            ): void {
                // This should not execute
            }
        };

        $definitions = [
            new SeederDefinition(seederClass: get_class($seeder), name: 'test', order: 0),
        ];

        $connection = $this->createMock(ConnectionInterface::class);

        $runner = new SeederRunner(
            seeders: [get_class($seeder) => $seeder],
            isProduction: true,
        );

        expect(fn () => $runner->runAll($definitions, $connection))
            ->toThrow(SeederException::class, 'cannot be run in production');
    });

    it('supports running specific seeder by name', function (): void {
        $userSeederRan = false;
        $postSeederRan = false;

        $userSeeder = new class ($userSeederRan) implements SeederInterface
        {
            public function __construct(
                private bool &$ran,
            ) {}

            public function run(
                ConnectionInterface $connection,
            ): void {
                $this->ran = true;
            }
        };

        $postSeeder = new class ($postSeederRan) implements SeederInterface
        {
            public function __construct(
                private bool &$ran,
            ) {}

            public function run(
                ConnectionInterface $connection,
            ): void {
                $this->ran = true;
            }
        };

        $definitions = [
            new SeederDefinition(seederClass: get_class($userSeeder), name: 'users', order: 0),
            new SeederDefinition(seederClass: get_class($postSeeder), name: 'posts', order: 10),
        ];

        $connection = $this->createMock(ConnectionInterface::class);

        $runner = new SeederRunner(
            seeders: [
                get_class($userSeeder) => $userSeeder,
                get_class($postSeeder) => $postSeeder,
            ],
            isProduction: false,
        );

        $runner->runByName('users', $definitions, $connection);

        expect($userSeederRan)->toBeTrue()
            ->and($postSeederRan)->toBeFalse();
    });

    it('shows error when seeder not found', function (): void {
        $definitions = [];

        $connection = $this->createMock(ConnectionInterface::class);

        $runner = new SeederRunner(
            seeders: [],
            isProduction: false,
        );

        expect(fn () => $runner->runByName('nonexistent', $definitions, $connection))
            ->toThrow(SeederException::class, "'nonexistent' not found");
    });
});
