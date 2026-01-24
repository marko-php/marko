<?php

declare(strict_types=1);

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
                /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
                private array &$order,
            ) {}

            public function run(): void
            {
                $this->order[] = 'third';
            }
        };

        $seeder2 = new class ($executionOrder) implements SeederInterface
        {
            public function __construct(
                /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
                private array &$order,
            ) {}

            public function run(): void
            {
                $this->order[] = 'first';
            }
        };

        $seeder3 = new class ($executionOrder) implements SeederInterface
        {
            public function __construct(
                /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
                private array &$order,
            ) {}

            public function run(): void
            {
                $this->order[] = 'second';
            }
        };

        $definitions = [
            new SeederDefinition(seederClass: get_class($seeder1), name: 'third', order: 30),
            new SeederDefinition(seederClass: get_class($seeder2), name: 'first', order: 10),
            new SeederDefinition(seederClass: get_class($seeder3), name: 'second', order: 20),
        ];

        $runner = new SeederRunner(
            seeders: [
                get_class($seeder1) => $seeder1,
                get_class($seeder2) => $seeder2,
                get_class($seeder3) => $seeder3,
            ],
            isProduction: false,
        );

        $runner->runAll($definitions);

        expect($executionOrder)->toBe(['first', 'second', 'third']);
    });

    it('provides SeederRunner to execute discovered seeders', function (): void {
        $executed = false;

        $seeder = new class ($executed) implements SeederInterface
        {
            public function __construct(
                /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
                private bool &$executed,
            ) {}

            public function run(): void
            {
                $this->executed = true;
            }
        };

        $definitions = [
            new SeederDefinition(seederClass: get_class($seeder), name: 'test', order: 0),
        ];

        $runner = new SeederRunner(
            seeders: [get_class($seeder) => $seeder],
            isProduction: false,
        );

        $runner->runAll($definitions);

        expect($executed)->toBeTrue();
    });

    it('blocks seeder execution in production environment', function (): void {
        $seeder = new class () implements SeederInterface
        {
            public function run(): void
            {
                // This should not execute
            }
        };

        $definitions = [
            new SeederDefinition(seederClass: get_class($seeder), name: 'test', order: 0),
        ];

        $runner = new SeederRunner(
            seeders: [get_class($seeder) => $seeder],
            isProduction: true,
        );

        expect(fn () => $runner->runAll($definitions))
            ->toThrow(SeederException::class, 'cannot be run in production');
    });

    it('supports running specific seeder by name', function (): void {
        $userSeederRan = false;
        $postSeederRan = false;

        $userSeeder = new class ($userSeederRan) implements SeederInterface
        {
            public function __construct(
                /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
                private bool &$ran,
            ) {}

            public function run(): void
            {
                $this->ran = true;
            }
        };

        $postSeeder = new class ($postSeederRan) implements SeederInterface
        {
            public function __construct(
                /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
                private bool &$ran,
            ) {}

            public function run(): void
            {
                $this->ran = true;
            }
        };

        $definitions = [
            new SeederDefinition(seederClass: get_class($userSeeder), name: 'users', order: 0),
            new SeederDefinition(seederClass: get_class($postSeeder), name: 'posts', order: 10),
        ];

        $runner = new SeederRunner(
            seeders: [
                get_class($userSeeder) => $userSeeder,
                get_class($postSeeder) => $postSeeder,
            ],
            isProduction: false,
        );

        $runner->runByName('users', $definitions);

        expect($userSeederRan)->toBeTrue()
            ->and($postSeederRan)->toBeFalse();
    });

    it('shows error when seeder not found', function (): void {
        $definitions = [];

        $runner = new SeederRunner(
            seeders: [],
            isProduction: false,
        );

        expect(fn () => $runner->runByName('nonexistent', $definitions))
            ->toThrow(SeederException::class, "'nonexistent' not found");
    });
});
